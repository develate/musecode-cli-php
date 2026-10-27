<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\SessionLog;

use Develate\MusecodeCli\Value\Usage;

/**
 * Reads per-run token usage from the CLI's durable session logs.
 *
 * `muse exec --json` never reports token counts on its stream; the session log
 * does, as one `model_completed` record per model completion. Each record
 * carries the raw provider counters and the model that ran, and belongs to its
 * run through `payload.run_id`, which is the same id the stream reports as the
 * terminal record's `command_id`/`run_stream.id`.
 *
 * The log lives under the data home the CLI ran with,
 * `<data-home>/muse/sessions/YYYY/MM/DD/<session-id>/session.jsonl`, resolved
 * from the same environment the child process receives. A resumed session's
 * log holds every run so far; only the requested run's completions are summed.
 * Anything missing or unreadable — `--no-session-log` runs, echo runs without
 * completions, malformed lines — reads as absent rather than failing.
 */
final class SessionLogReader
{
    /**
     * @param array<string, string|false> $env the environment the CLI runs
     *                                         under; `false` removes a variable
     */
    public function __construct(private readonly array $env = []) {}

    public function readRun(string $sessionId, string $runId): ?SessionLogUsage
    {
        $path = $this->logPath($sessionId);

        if ($path === null || $runId === '') {
            return null;
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        $usage = new Usage;
        $completions = 0;

        /** @var array<string, int> $inputByModel */
        $inputByModel = [];

        try {
            while (($line = fgets($handle)) !== false) {
                $completion = self::completion($line, $runId);

                if ($completion === null) {
                    continue;
                }

                $completions++;
                $usage = $usage->plus($completion['usage']);

                $model = $completion['model'];

                if ($model !== null) {
                    $inputByModel[$model] = ($inputByModel[$model] ?? 0) + $completion['input'];
                }
            }
        } finally {
            fclose($handle);
        }

        if ($completions === 0) {
            return null;
        }

        return new SessionLogUsage($usage, self::dominantModel($inputByModel));
    }

    /**
     * One log line's completion for this run, if it is one.
     *
     * @return array{usage: Usage, input: int, model: ?string}|null
     */
    private static function completion(string $line, string $runId): ?array
    {
        try {
            $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($record) || ($record['payload_type'] ?? null) !== 'runtime.session') {
            return null;
        }

        $payload = $record['payload'] ?? null;

        if (! is_array($payload) || ($payload['run_id'] ?? null) !== $runId) {
            return null;
        }

        $event = $payload['event'] ?? null;

        if (! is_array($event) || ($event['kind'] ?? null) !== 'model_completed') {
            return null;
        }

        $counters = $event['usage'] ?? null;

        if (! is_array($counters)) {
            return null;
        }

        $model = $event['model'] ?? null;

        return [
            'usage' => Usage::fromArray($counters),
            'input' => is_numeric($counters['input_tokens'] ?? $counters['inputTokens'] ?? null)
                ? max(0, (int) ($counters['input_tokens'] ?? $counters['inputTokens']))
                : 0,
            'model' => is_string($model) && trim($model) !== '' ? trim($model) : null,
        ];
    }

    /**
     * The model that processed most of the run's input, if the log named one.
     *
     * A run that switched models mid-way still prices as one row; the dominant
     * model is the honest single answer. Ties keep their first-seen order.
     *
     * @param array<string, int> $inputByModel
     */
    private static function dominantModel(array $inputByModel): ?string
    {
        $best = null;
        $most = 0;

        foreach ($inputByModel as $model => $input) {
            if ($best === null || $input > $most) {
                $best = $model;
                $most = $input;
            }
        }

        return $best;
    }

    private function logPath(string $sessionId): ?string
    {
        // The id becomes a path segment; anything outside the CLI's own id
        // alphabet is refused rather than resolved.
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9-]*$/', $sessionId) !== 1) {
            return null;
        }

        $home = $this->dataHome();

        if ($home === null) {
            return null;
        }

        $matches = glob(
            $home.DIRECTORY_SEPARATOR.'muse'.DIRECTORY_SEPARATOR.'sessions'
            .DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'*'
            .DIRECTORY_SEPARATOR.$sessionId.DIRECTORY_SEPARATOR.'session.jsonl',
            GLOB_NOSORT,
        );

        if (! is_array($matches)) {
            return null;
        }

        foreach ($matches as $match) {
            if (is_file($match) && is_readable($match)) {
                return $match;
            }
        }

        return null;
    }

    private function dataHome(): ?string
    {
        $override = $this->env['XDG_DATA_HOME'] ?? null;

        if (is_string($override) && trim($override) !== '') {
            return rtrim(trim($override), DIRECTORY_SEPARATOR);
        }

        $home = $this->env['HOME'] ?? null;

        if (is_string($home) && trim($home) !== '') {
            return rtrim(trim($home), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.local'.DIRECTORY_SEPARATOR.'share';
        }

        $fallback = getenv('XDG_DATA_HOME');

        if (is_string($fallback) && trim($fallback) !== '') {
            return rtrim(trim($fallback), DIRECTORY_SEPARATOR);
        }

        $fallbackHome = getenv('HOME');

        if (is_string($fallbackHome) && trim($fallbackHome) !== '') {
            return rtrim(trim($fallbackHome), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.local'.DIRECTORY_SEPARATOR.'share';
        }

        return null;
    }
}
