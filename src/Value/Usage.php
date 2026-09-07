<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Value;

/**
 * Token counters for a run, in the provider's own vocabulary.
 *
 * The echo provider reports none, so every counter defaults to zero rather
 * than null: "no usage reported" and "zero tokens used" are the same answer.
 */
final readonly class Usage
{
    public function __construct(
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $reasoningTokens = 0,
        public int $cachedTokens = 0,
    ) {}

    /** @param array<string, mixed> $usage */
    public static function fromArray(array $usage): self
    {
        return new self(
            inputTokens: self::count($usage['inputTokens'] ?? $usage['input_tokens'] ?? null),
            outputTokens: self::count($usage['outputTokens'] ?? $usage['output_tokens'] ?? null),
            reasoningTokens: self::count($usage['reasoningTokens'] ?? $usage['reasoning_tokens'] ?? null),
            cachedTokens: self::count($usage['cachedTokens'] ?? $usage['cached_tokens'] ?? null),
        );
    }

    public function total(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    private static function count(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }
}
