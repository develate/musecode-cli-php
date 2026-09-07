<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Tests;

use Develate\MusecodeCli\Exception\ProcessCancelled;
use Develate\MusecodeCli\Exception\ProcessFailed;
use Develate\MusecodeCli\Muse;
use Develate\MusecodeCli\RunOptions;
use Develate\MusecodeCli\SessionOptions;
use Develate\MusecodeCli\Process\ProcessResult;
use Develate\MusecodeCli\Tests\Support\Fixtures;
use Develate\MusecodeCli\Tests\Support\ScriptedTransport;
use Develate\MusecodeCli\Transport\RunRequest;
use Develate\MusecodeCli\Transport\Transport;
use Develate\MusecodeCli\Value\ResultStatus;
use Develate\MusecodeCli\Value\Terminal;
use PHPUnit\Framework\TestCase;

final class RunTest extends TestCase
{
    public function testStreamYieldsItemsAndBuildsAResult(): void
    {
        $transport = new ScriptedTransport(Fixtures::echoSession());
        $session = (new Muse('muse', $transport))->session(new SessionOptions(cwd: sys_get_temp_dir()));

        $run = $session->stream('fixture run');
        $items = iterator_to_array($run);

        $this->assertNotSame([], $items);
        $this->assertTrue($run->isComplete());

        $result = $run->result();
        $this->assertSame('echo: fixture run', $result->text);
        $this->assertTrue($result->isSuccess());
        $this->assertSame(ResultStatus::Success, $result->status);
        $this->assertSame(Terminal::Completed, $result->terminal);
        $this->assertNotSame('', $result->sessionId);
        $this->assertSame(sys_get_temp_dir(), $transport->requests[0]->session->cwd);
        $this->assertSame('fixture run', $transport->requests[0]->prompt);
    }

    public function testFailedTerminalIsAResultRatherThanACrash(): void
    {
        $transport = new ScriptedTransport([
            Fixtures::delta('partial'),
            Fixtures::terminal('failed', 'partial', 'provider_unavailable'),
        ]);
        $session = (new Muse('muse', $transport))->session(new SessionOptions(cwd: sys_get_temp_dir()));

        $result = $session->query('do it');

        $this->assertFalse($result->isSuccess());
        $this->assertSame(ResultStatus::Failed, $result->status);
        $this->assertSame('provider_unavailable', $result->reason);
        $this->assertSame('partial', $result->text);
    }

    public function testDeltasAreConcatenatedWhenNoTerminalTextExists(): void
    {
        $transport = new ScriptedTransport([
            Fixtures::delta('hello '),
            Fixtures::delta('world'),
            Fixtures::terminal('completed', ''),
        ]);

        $result = (new Muse('muse', $transport))
            ->session(new SessionOptions(cwd: sys_get_temp_dir()))
            ->query('hi');

        $this->assertSame('hello world', $result->text);
    }

    public function testMissingTerminalThrowsWithStderr(): void
    {
        $transport = new ScriptedTransport([Fixtures::delta('half')], exitCode: 1, stderr: 'muse: boom');
        $session = (new Muse('muse', $transport))->session(new SessionOptions(cwd: sys_get_temp_dir()));

        try {
            $session->query('do it');
            $this->fail('Expected a ProcessFailed exception.');
        } catch (ProcessFailed $exception) {
            $this->assertSame(1, $exception->exitCode);
            $this->assertStringContainsString('boom', $exception->stderr);
        }
    }

    public function testCancellationReachesTheTransport(): void
    {
        $transport = new class implements Transport
        {
            public function stream(RunRequest $request): \Generator
            {
                if ($request->isCancelled !== null && ($request->isCancelled)()) {
                    throw new ProcessCancelled('Muse Code run was cancelled.');
                }

                yield from [];

                return new ProcessResult(0, '');
            }
        };
        $session = (new Muse('muse', $transport))->session(new SessionOptions(cwd: sys_get_temp_dir()));

        $run = $session->stream('slow');
        $run->cancel();

        $this->expectException(ProcessCancelled::class);
        foreach ($run as $_) {
        }
    }

    public function testRunOptionsTimeoutReachesTheRequest(): void
    {
        $transport = new ScriptedTransport(Fixtures::echoSession());
        $session = (new Muse('muse', $transport))->session(new SessionOptions(cwd: sys_get_temp_dir()));

        $session->query('hi', new RunOptions(timeout: 12.0));

        $this->assertSame(12.0, $transport->requests[0]->timeout());
    }

    public function testEmptyPromptIsRejected(): void
    {
        $this->expectException(\Develate\MusecodeCli\Exception\InvalidOptions::class);

        (new Muse('muse', new ScriptedTransport([])))
            ->session(new SessionOptions(cwd: sys_get_temp_dir()))
            ->stream('  ');
    }
}
