<?php

declare(strict_types=1);

namespace Develate\MusecodeCli;

use Develate\MusecodeCli\Event\Event;
use Develate\MusecodeCli\Event\RunTerminal;
use Develate\MusecodeCli\Event\TextDelta;
use Develate\MusecodeCli\Value\ResultStatus;
use Develate\MusecodeCli\Value\RunMetadata;
use Develate\MusecodeCli\Value\Terminal;
use Develate\MusecodeCli\Value\Usage;

final readonly class Result
{
    /**
     * @param  list<Event>  $events
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $sessionId,
        public string $text,
        public Usage $usage,
        public ResultStatus $status,
        public ?Terminal $terminal,
        public ?string $reason,
        public array $events,
        public RunMetadata $metadata,
        public int $exitCode,
        private array $raw,
    ) {}

    public function isSuccess(): bool
    {
        return $this->status === ResultStatus::Success;
    }

    /** @return list<TextDelta> */
    public function deltas(): array
    {
        return array_values(array_filter(
            $this->events,
            static fn (Event $event): bool => $event instanceof TextDelta,
        ));
    }

    public function terminal(): ?RunTerminal
    {
        foreach (array_reverse($this->events) as $event) {
            if ($event instanceof RunTerminal) {
                return $event;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->raw;
    }
}
