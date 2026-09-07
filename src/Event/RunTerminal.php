<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Event;

use Develate\MusecodeCli\Value\Terminal;
use Develate\MusecodeCli\Value\Usage;

/**
 * The end of a run, from a `run.terminal.*` record.
 *
 * A failed terminal is still a result: it names the session and carries
 * whatever text the run produced, so callers can report it rather than
 * treating every failure as a crashed process.
 */
final readonly class RunTerminal implements Event
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public Terminal $terminal,
        public string $text,
        public ?string $reason,
        public ?string $sessionId,
        public Usage $usage,
        private array $raw = [],
    ) {}

    public function isSuccess(): bool
    {
        return $this->terminal === Terminal::Completed;
    }

    public function payloadType(): string
    {
        return 'run.terminal.'.$this->terminal->value;
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
