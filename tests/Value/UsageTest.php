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
}
