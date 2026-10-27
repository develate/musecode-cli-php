<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Tests\Value;

use Develate\MusecodeCli\Value\Usage;
use PHPUnit\Framework\TestCase;

final class UsageTest extends TestCase
{
    public function testDefaultsToZero(): void
    {
        $usage = new Usage;

        $this->assertSame(0, $usage->total());
    }

    public function testReadsCamelCaseCounters(): void
    {
        $usage = Usage::fromArray(['inputTokens' => 10, 'outputTokens' => 4, 'reasoningTokens' => 2]);

        $this->assertSame(10, $usage->inputTokens);
        $this->assertSame(4, $usage->outputTokens);
        $this->assertSame(2, $usage->reasoningTokens);
        $this->assertSame(14, $usage->total());
    }

    public function testIgnoresNonNumericCounters(): void
    {
        $usage = Usage::fromArray(['inputTokens' => 'many', 'outputTokens' => -3]);

        $this->assertSame(0, $usage->inputTokens);
        $this->assertSame(0, $usage->outputTokens);
    }

    public function testInputKeepsTheUncachedRemainder(): void
    {
        // Meta reports the total processed input with the cache inside it; the
        // value keeps the uncached remainder beside the cache counters.
        $usage = Usage::fromArray([
            'input_tokens' => 100,
            'output_tokens' => 10,
            'cached_tokens' => 70,
            'cache_write_tokens' => 5,
            'reasoning_tokens' => 3,
        ]);

        $this->assertSame(25, $usage->inputTokens);
        $this->assertSame(70, $usage->cachedTokens);
        $this->assertSame(5, $usage->cacheWriteTokens);
        $this->assertSame(3, $usage->reasoningTokens);
        $this->assertSame(110, $usage->total());
    }

    public function testOverReportedCacheNeverYieldsNegativeInput(): void
    {
        $usage = Usage::fromArray(['input_tokens' => 10, 'cached_tokens' => 40]);

        $this->assertSame(0, $usage->inputTokens);
        $this->assertSame(40, $usage->cachedTokens);
    }

    public function testPlusSumsEveryCounter(): void
    {
        $sum = (new Usage(10, 4, 1, 70, 5))->plus(new Usage(20, 6, 2, 30, 0));

        $this->assertSame(30, $sum->inputTokens);
        $this->assertSame(10, $sum->outputTokens);
        $this->assertSame(3, $sum->reasoningTokens);
        $this->assertSame(100, $sum->cachedTokens);
        $this->assertSame(5, $sum->cacheWriteTokens);
    }

    public function testIsEmptyDistinguishesReportedFromZeroUsage(): void
    {
        $this->assertTrue((new Usage)->isEmpty());
        $this->assertTrue(Usage::fromArray([])->isEmpty());
        $this->assertFalse(Usage::fromArray(['input_tokens' => 1])->isEmpty());
        $this->assertFalse(Usage::fromArray(['cached_tokens' => 1])->isEmpty());
    }
}
