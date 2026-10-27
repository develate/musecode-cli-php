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

    public function testResultCarriesLoggedUsageAndModel(): void
    {
        $dataHome = sys_get_temp_dir().DIRECTORY_SEPARATOR.'muse-run-'.bin2hex(random_bytes(8));
        $dir = $dataHome.DIRECTORY_SEPARATOR.'muse'.DIRECTORY_SEPARATOR.'sessions'
            .DIRECTORY_SEPARATOR.'2026'.DIRECTORY_SEPARATOR.'09'.DIRECTORY_SEPARATOR.'15'
            .DIRECTORY_SEPARATOR.'session-one';
        mkdir($dir, 0777, true);
        file_put_contents($dir.DIRECTORY_SEPARATOR.'session.jsonl', (string) json_encode([
            'payload_type' => 'runtime.session',
            'payload' => [
                'kind' => 'run',
                'run_id' => 'run-one',
                'event' => [
                    'kind' => 'model_completed',
                    'usage' => ['input_tokens' => 100, 'output_tokens' => 10, 'cached_tokens' => 60],
                    'model' => 'muse-spark-1.3',
                ],
            ],
        ])."\n");

        try {
            $transport = new ScriptedTransport([
                Fixtures::terminal('completed', 'done', null, 'run-one', 'session-one'),
            ]);
            $result = (new Muse('muse', $transport, ['XDG_DATA_HOME' => $dataHome]))
                ->session(new SessionOptions(cwd: sys_get_temp_dir()))
                ->query('hi');

            $this->assertSame('run-one', $result->runId);
            $this->assertSame('muse-spark-1.3', $result->model);
            $this->assertSame(40, $result->usage->inputTokens);
            $this->assertSame(60, $result->usage->cachedTokens);
            $this->assertSame(10, $result->usage->outputTokens);
        } finally {
            $this->remove($dataHome);
        }
    }

    public function testMissingSessionLogKeepsTheStreamAnswer(): void
    {
        $transport = new ScriptedTransport([
            Fixtures::terminal('completed', 'done', null, 'run-one', 'session-one'),
        ]);
        $result = (new Muse('muse', $transport, ['XDG_DATA_HOME' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'muse-missing-'.bin2hex(random_bytes(8))]))
            ->session(new SessionOptions(cwd: sys_get_temp_dir()))
            ->query('hi');

        $this->assertSame('run-one', $result->runId);
        $this->assertNull($result->model);
        $this->assertTrue($result->usage->isEmpty());
    }

    private function remove(string $path): void
    {
        if (! file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            unlink($path);

            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $this->remove($path.DIRECTORY_SEPARATOR.$entry);
        }

        rmdir($path);
    }
}
