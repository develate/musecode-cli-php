<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Tests\Event;

use Develate\MusecodeCli\Event\EventParser;
use Develate\MusecodeCli\Event\MspEvent;
use Develate\MusecodeCli\Event\RunTerminal;
use Develate\MusecodeCli\Event\TextDelta;
use Develate\MusecodeCli\Exception\InvalidStreamJson;
use Develate\MusecodeCli\Tests\Support\Fixtures;
use Develate\MusecodeCli\Value\Terminal;
use PHPUnit\Framework\TestCase;

final class EventParserTest extends TestCase
{
    public function testEchoFixtureParsesToDeltasAndATerminal(): void
    {
        $parser = new EventParser;
        $deltas = [];
        $terminal = null;

        foreach (Fixtures::echoSession() as $line) {
            foreach ($parser->parseLine($line) as $item) {
                if ($item instanceof TextDelta) {
                    $deltas[] = $item->text;
                }
                if ($item instanceof RunTerminal) {
                    $terminal = $item;
                }
            }
        }

        $this->assertSame(['echo: fixture run'], $deltas);
        $this->assertInstanceOf(RunTerminal::class, $terminal);
        $this->assertSame(Terminal::Completed, $terminal->terminal);
        $this->assertSame('echo: fixture run', $terminal->text);
        $this->assertTrue($terminal->isSuccess());
        $this->assertNotSame('', $terminal->sessionId);
    }

    public function testTaskLifecycleNoiseStaysGeneric(): void
    {
        $line = (string) json_encode([
            'payload_type' => 'task.lifecycle.started',
            'stream' => ['kind' => 'session', 'id' => 'session-one'],
            'payload' => ['kind' => 'task_lifecycle', 'task_id' => 'task-one'],
        ]);

        $items = (new EventParser)->parseLine($line);

        $this->assertCount(1, $items);
        $this->assertInstanceOf(MspEvent::class, $items[0]);
        $this->assertSame('task.lifecycle.started', $items[0]->payloadType());
        $this->assertSame('session-one', $items[0]->sessionId);
    }

    public function testFailedTerminalCarriesTheReason(): void
    {
        $items = (new EventParser)->parseLine(Fixtures::terminal('failed', '', 'provider_unavailable'));

        $this->assertCount(1, $items);
        $this->assertInstanceOf(RunTerminal::class, $items[0]);
        $this->assertSame(Terminal::Failed, $items[0]->terminal);
        $this->assertSame('provider_unavailable', $items[0]->reason);
        $this->assertFalse($items[0]->isSuccess());
    }

    public function testMalformedJsonThrows(): void
    {
        $this->expectException(InvalidStreamJson::class);

        (new EventParser)->parseLine('not json {');
    }

    public function testNonObjectJsonThrows(): void
    {
        $this->expectException(InvalidStreamJson::class);

        (new EventParser)->parseLine('["a","b"]');
    }
}
