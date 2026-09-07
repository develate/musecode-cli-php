<?php

declare(strict_types=1);

namespace Develate\MusecodeCli;

use Develate\MusecodeCli\Exception\InvalidOptions;
use Develate\MusecodeCli\Exception\MuseNotFound;
use Develate\MusecodeCli\Process\MuseExecutable;
use Develate\MusecodeCli\Quota\PtyQuotaReader;
use Develate\MusecodeCli\Quota\Quota;
use Develate\MusecodeCli\Transport\ExecTransport;
use Develate\MusecodeCli\Transport\RunMode;
use Develate\MusecodeCli\Transport\Transport;
use Symfony\Component\Process\Exception\RuntimeException as SymfonyRuntimeException;
use Symfony\Component\Process\Process;

/**
 * The entry point: a `muse` binary plus the environment it runs under.
 *
 * The environment is deliberately one setting for the whole object. `--version`,
 * session logs and every run have to observe the same `HOME` and the same
 * `META_API_KEY`, or a host managing several accounts would report one
 * identity and run as another.
 */
final class Muse
{
    private readonly Transport $transport;

    private ?string $versionCache = null;

    private bool $versionResolved = false;

    private ?string $defaultCwd = null;

    /**
     * @param  array<string, string|false>  $env  merged onto the inherited environment;
     *                                            `false` removes an inherited variable
     */
    public function __construct(
        private readonly string $binary = 'muse',
        ?Transport $transport = null,
        private readonly array $env = [],
        private readonly ?float $timeout = null,
        ?string $version = null,
    ) {
        $this->transport = $transport ?? new ExecTransport($binary, $env);

        if ($version !== null) {
            $this->versionCache = $version;
            $this->versionResolved = true;
        }
    }

    /**
     * A conversation, one process per turn.
     */
    public function session(?SessionOptions $options = null): Session
    {
        return new Session(
            transport: $this->transport,
            museVersion: $this->version() ?? 'unknown',
            options: $this->resolveOptions($options),
            timeout: $this->timeout,
        );
    }

    /**
     * An existing conversation, named explicitly.
     *
     * The id is required. Resuming "whatever ran last" is a TUI affordance
     * that on a shared machine is not necessarily this caller's conversation,
     * so this SDK does not offer it.
     */
    public function resume(string $sessionId, ?SessionOptions $options = null): Session
    {
        if (trim($sessionId) === '') {
            throw new InvalidOptions('Resuming requires a session id.');
        }

        return new Session(
            transport: $this->transport,
            museVersion: $this->version() ?? 'unknown',
            options: $this->resolveOptions($options),
            timeout: $this->timeout,
            sessionId: $sessionId,
            nextMode: RunMode::Resume,
        );
    }

    /**
     * A copy of this client that runs in `$cwd`.
     */
    public function in(string $cwd): self
    {
        $clone = clone $this;
        $clone->defaultCwd = $cwd;

        return $clone;
    }

    /**
     * A one-shot turn in a fresh conversation.
     */
    public function query(string $prompt, ?SessionOptions $options = null, ?RunOptions $run = null): Result
    {
        return $this->session($options)->query($prompt, $run);
    }

    /**
     * The CLI's version, or null when it cannot be read.
     *
     * A binary that will not report its version is not thereby incompatible,
     * so this answers "unknown" rather than failing.
     */
    public function version(): ?string
    {
        if ($this->versionResolved) {
            return $this->versionCache;
        }

        $this->versionResolved = true;

        try {
            $output = $this->run(['--version'], 15.0);
        } catch (MuseNotFound) {
            return $this->versionCache = null;
        }

        // Only a recognisable version is reported. Echoing back whatever the
        // binary printed would turn "unknown" into a confident wrong answer.
        // Observed shape: `Muse Code 1.0.3 (1.0.3-R2198.1)`.
        return $this->versionCache = preg_match('/([0-9]+(?:\.[0-9]+){1,3}(?:[-+][^\s]+)?)/', $output, $matches) === 1
            ? $matches[1]
            : null;
    }

    public function isAvailable(): bool
    {
        return MuseExecutable::isAvailable($this->binary);
    }

    public function quota(): Quota
    {
        return (new PtyQuotaReader)->read($this->binary, $this->env, $this->timeout ?? 30.0);
    }

    /**
     * Whether this binary exists and answers a command this SDK relies on.
     *
     * Compatibility is decided by the interface being there, not by the version
     * string: an unreadable version is unknown, not incompatible.
     */
    public function isCompatible(): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        try {
            $this->run(['exec', '--help'], 20.0);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    /**
     * Whether this binary and environment are signed in.
     *
     * `muse login` writes `auth.json` under the configuration home and
     * `META_API_KEY` takes priority over it, so either one counts. Nothing is
     * executed: this must stay safe to call during a render.
     */
    public function isAuthenticated(): bool
    {
        if (($this->env['META_API_KEY'] ?? getenv('META_API_KEY')) !== false
            && trim((string) ($this->env['META_API_KEY'] ?? getenv('META_API_KEY'))) !== ''
        ) {
            return true;
        }

        return is_file($this->authFilePath());
    }

    /**
     * Where this binary and environment keep their credentials.
     */
    public function authFilePath(): string
    {
        return rtrim($this->configHome(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'muse'
            .DIRECTORY_SEPARATOR.'auth.json';
    }

    private function configHome(): string
    {
        $override = $this->env['XDG_CONFIG_HOME'] ?? getenv('XDG_CONFIG_HOME');

        if (is_string($override) && trim($override) !== '') {
            return trim($override);
        }

        return rtrim($this->home(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.config';
    }

    private function home(): string
    {
        $override = $this->env['HOME'] ?? getenv('HOME');

        if (is_string($override) && trim($override) !== '') {
            return trim($override);
        }

        return '';
    }

    /** @param list<string> $arguments */
    private function run(array $arguments, float $timeout): string
    {
        MuseExecutable::assertAvailable($this->binary);

        try {
            $process = new Process([$this->binary, ...$arguments], null, $this->env, null, $timeout);
            $process->mustRun();
        } catch (SymfonyRuntimeException $exception) {
            throw new MuseNotFound(sprintf('Muse Code binary "%s" was not found.', $this->binary), 0, $exception);
        }

        return $process->getOutput();
    }

    private function resolveOptions(?SessionOptions $options): SessionOptions
    {
        $options ??= new SessionOptions(cwd: '');

        if ($options->cwd !== '') {
            return $options;
        }

        $cwd = $this->defaultCwd ?? getcwd();

        return $options->withCwd(is_string($cwd) && $cwd !== '' ? $cwd : '.');
    }
}
