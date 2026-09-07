<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Tests\Support;

use Develate\MusecodeCli\Event\RunTerminal;
use Develate\MusecodeCli\Event\TextDelta;
use Develate\MusecodeCli\Process\ProcessResult;
use Develate\MusecodeCli\Transport\RunMode;
use Develate\MusecodeCli\Transport\RunRequest;
use Develate\MusecodeCli\Transport\Transport;
use Develate\MusecodeCli\Value\Terminal;
use Develate\MusecodeCli\Value\Usage;

final class FakeTransport implements Transport
{
    /** @var list<RunRequest> */
    public array $requests = [];

    public function stream(RunRequest $request): \Generator
    {
        $this->requests[] = $request;
        $id = $request->mode === RunMode::Resume
            ? ($request->sessionId ?? 'session-one')
            : 'session-one';

        yield new TextDelta('answer: '.$request->prompt, $id, []);
        yield new RunTerminal(Terminal::Completed, 'answer: '.$request->prompt, null, $id, new Usage, []);

        return new ProcessResult(0, '');
    }
}
