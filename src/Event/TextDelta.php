<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Event;

/**
 * A slice of the model's streamed text, from a `run.output.delta` record.
 */
final readonly class TextDelta implements Event
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $text,
        public ?string $sessionId = null,
        private array $raw = [],
    ) {}

    public function payloadType(): string
    {
        return 'run.output.delta';
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
