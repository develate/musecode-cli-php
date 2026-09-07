<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Event;

use Develate\MusecodeCli\Exception\InvalidStreamJson;
use Develate\MusecodeCli\StreamItem;
use Develate\MusecodeCli\Value\Terminal;
use Develate\MusecodeCli\Value\Usage;

/**
 * Parses one `muse exec --json` output line into stream items.
 *
 * Every line is an MSP envelope carrying `payload_type` and `payload`. Only
 * two payloads have dedicated types — output deltas and the run terminal —
 * because only those decide what the run said and how it ended.
 */
final readonly class EventParser
{
    /** @return list<StreamItem> */
    public function parseLine(string $line): array
    {
        try {
            $raw = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidStreamJson($line, $exception);
        }
        if (!is_array($raw) || array_is_list($raw)) {
            throw new InvalidStreamJson($line);
        }

        $type = self::string($raw['payload_type'] ?? null);
        $payload = is_array($raw['payload'] ?? null) ? $raw['payload'] : [];
        $sessionId = self::sessionId($raw);

        if ($type === 'run.output.delta') {
            return [new TextDelta(self::string($payload['text'] ?? null), $sessionId, $raw)];
        }

        if ($type === 'run.terminal.completed' || $type === 'run.terminal.failed' || $type === 'run.terminal.cancelled') {
            return [$this->terminal($type, $payload, $sessionId, $raw)];
        }

        return [new MspEvent($type, $payload, $sessionId, $raw)];
    }

    /** @param array<string, mixed> $payload @param array<string, mixed> $raw */
    private function terminal(string $type, array $payload, ?string $sessionId, array $raw): RunTerminal
    {
        $terminal = Terminal::tryFrom((string) ($payload['terminal'] ?? substr($type, strlen('run.terminal.'))))
            ?? Terminal::Failed;

        $usage = $payload['usage'] ?? $payload['tokenUsage'] ?? null;

        return new RunTerminal(
            terminal: $terminal,
            text: self::string($payload['text'] ?? null),
            reason: self::nullableString($payload['reason'] ?? null),
            sessionId: $sessionId,
            usage: is_array($usage) ? Usage::fromArray($usage) : new Usage,
            raw: $raw,
        );
    }

    /** @param array<string, mixed> $raw */
    private static function sessionId(array $raw): ?string
    {
        $stream = is_array($raw['stream'] ?? null) ? $raw['stream'] : [];

        return self::nullableString($stream['id'] ?? null);
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
