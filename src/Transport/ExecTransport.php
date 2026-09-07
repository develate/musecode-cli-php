<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Transport;

use Develate\MusecodeCli\Event\EventParser;
use Develate\MusecodeCli\Process\ProcessFactory;
use Develate\MusecodeCli\Process\ProcessResult;

/**
 * One `muse exec --json` process per run.
 *
 * Human chatter (`muse: ...` warnings) travels on stderr, so the JSONL on
 * stdout parses cleanly; stderr is only surfaced when the run dies without a
 * terminal record.
 */
final readonly class ExecTransport implements Transport
{
    /**
     * @param  array<string, string|false>  $env  process environment applied to every run
     */
    public function __construct(
        private string $binary = 'muse',
        private array $env = [],
        private ProcessFactory $processFactory = new ProcessFactory,
        private ArgumentBuilder $arguments = new ArgumentBuilder,
        private EventParser $parser = new EventParser,
    ) {}

    public function stream(RunRequest $request): \Generator
    {
        $process = $this->processFactory->create(
            $this->command($request),
            $request->cwd(),
            $request->timeout(),
            $this->env,
        );

        $lines = $process->lines($request->isCancelled);
        foreach ($lines as $line) {
            foreach ($this->parser->parseLine($line) as $item) {
                yield $item;
            }
        }

        /** @var ProcessResult $result */
        $result = $lines->getReturn();

        return $result;
    }

    /** @return list<string> */
    public function command(RunRequest $request): array
    {
        return $this->arguments->build($this->binary, $request);
    }
}
