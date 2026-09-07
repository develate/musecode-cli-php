<?php

declare(strict_types=1);

namespace Develate\MusecodeCli;

use Develate\MusecodeCli\Event\Event;
use Develate\MusecodeCli\Event\RunTerminal;
use Develate\MusecodeCli\Event\TextDelta;
use Develate\MusecodeCli\Value\ResultStatus;
use Develate\MusecodeCli\Value\RunMetadata;
use Develate\MusecodeCli\Value\Usage;

final class ResultBuilder
{
    private string $sessionId;

    private ?RunTerminal $terminal = null;

    /** @var list<Event> */
    private array $events = [];

    public function __construct(string $sessionId = '')
    {
        $this->sessionId = $sessionId;
    }

    public function add(StreamItem $item): void
    {
        if (!$item instanceof Event) {
            return;
        }

        $this->events[] = $item;

        if ($item->sessionId !== null && $item->sessionId !== '') {
            $this->sessionId = $item->sessionId;
        }

        if ($item instanceof RunTerminal) {
            $this->terminal = $item;
        }
    }

    public function hasTerminal(): bool
    {
        return $this->terminal !== null;
    }

    public function build(RunMetadata $metadata, int $exitCode): Result
    {
        $terminal = $this->terminal;

        return new Result(
            sessionId: $this->sessionId,
            text: $this->finalText(),
            usage: $terminal?->usage ?? new Usage,
            status: ResultStatus::fromTerminal($terminal?->terminal, $exitCode !== 0),
            terminal: $terminal?->terminal,
            reason: $terminal?->reason,
            events: $this->events,
            metadata: $metadata,
            exitCode: $exitCode,
            raw: $terminal?->raw() ?? [],
        );
    }

    private function finalText(): string
    {
        $terminal = $this->terminal;

        if ($terminal !== null && trim($terminal->text) !== '') {
            return $terminal->text;
        }

        $text = '';
        foreach ($this->events as $event) {
            if ($event instanceof TextDelta) {
                $text .= $event->text;
            }
        }

        return $text;
    }
}
