<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Tests\Support;

final class Fixtures
{
    /** @return list<string> */
    public static function echoSession(): array
    {
        $lines = file(__DIR__.'/echo-session.jsonl', FILE_IGNORE_NEW_LINES);

        return array_values(array_filter(
            is_array($lines) ? $lines : [],
            static fn (string $line): bool => trim($line) !== '',
        ));
    }

    public static function terminal(string $terminal, string $text, ?string $reason = null): string
    {
        return (string) json_encode([
            'schema_version' => 1,
            'stream' => ['kind' => 'session', 'id' => 'session-one'],
            'sequence' => 13,
            'payload_type' => 'run.terminal.'.$terminal,
            'payload' => [
                'kind' => 'run_terminal',
                'terminal' => $terminal,
                'text' => $text,
                'reason' => $reason,
            ],
        ]);
    }

    public static function delta(string $text): string
    {
        return (string) json_encode([
            'schema_version' => 1,
            'stream' => ['kind' => 'session', 'id' => 'session-one'],
            'sequence' => 11,
            'payload_type' => 'run.output.delta',
            'payload' => ['kind' => 'run_output_delta', 'text' => $text],
        ]);
    }
}
