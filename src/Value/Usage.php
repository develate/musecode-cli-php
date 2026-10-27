<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Value;

/**
 * Token counters for a run, with uncached input beside the cache counters.
 *
 * Meta reports `input_tokens` as the total processed input with cached reads
 * (and any cache writes) inside it, following the Anthropic convention; this
 * value keeps the uncached remainder instead, so callers sum the parts without
 * ever double counting the cache.
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
        public int $cacheWriteTokens = 0,
    ) {}

    /** @param array<string, mixed> $usage */
    public static function fromArray(array $usage): self
    {
        $input = self::count($usage['inputTokens'] ?? $usage['input_tokens'] ?? null);
        $cached = self::count(
            $usage['cachedTokens'] ?? $usage['cached_tokens']
            ?? $usage['cacheReadTokens'] ?? $usage['cache_read_tokens'] ?? null
        );
        $written = self::count($usage['cacheWriteTokens'] ?? $usage['cache_write_tokens'] ?? null);

        return new self(
            inputTokens: max(0, $input - $cached - $written),
            outputTokens: self::count($usage['outputTokens'] ?? $usage['output_tokens'] ?? null),
            reasoningTokens: self::count($usage['reasoningTokens'] ?? $usage['reasoning_tokens'] ?? null),
            cachedTokens: $cached,
            cacheWriteTokens: $written,
        );
    }

    public function total(): int
    {
        return $this->inputTokens + $this->cachedTokens + $this->cacheWriteTokens + $this->outputTokens;
    }

    public function isEmpty(): bool
    {
        return $this->inputTokens === 0
            && $this->outputTokens === 0
            && $this->reasoningTokens === 0
            && $this->cachedTokens === 0
            && $this->cacheWriteTokens === 0;
    }

    public function plus(self $other): self
    {
        return new self(
            inputTokens: $this->inputTokens + $other->inputTokens,
            outputTokens: $this->outputTokens + $other->outputTokens,
            reasoningTokens: $this->reasoningTokens + $other->reasoningTokens,
            cachedTokens: $this->cachedTokens + $other->cachedTokens,
            cacheWriteTokens: $this->cacheWriteTokens + $other->cacheWriteTokens,
        );
    }

    private static function count(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }
}
