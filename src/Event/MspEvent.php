<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Event;

/**
 * Any MSP record this SDK has no dedicated type for.
 *
 * The wire is open for evolution (`x-msp-openness: open` in the schema the
 * binary exports), so task-lifecycle noise and future payloads are carried,
 * never dropped and never fatal.
 */
final readonly class MspEvent implements Event
{
    /** @param array<string, mixed> $payload @param array<string, mixed> $raw */
    public function __construct(
        public string $type,
        public array $payload,
        public ?string $sessionId = null,
        private array $raw = [],
    ) {}

    public function payloadType(): string
    {
        return $this->type;
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
