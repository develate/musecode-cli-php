<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Tests\SessionLog;

use Develate\MusecodeCli\SessionLog\SessionLogReader;
use PHPUnit\Framework\TestCase;

final class SessionLogReaderTest extends TestCase
{
    private string $dataHome;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dataHome = sys_get_temp_dir().DIRECTORY_SEPARATOR.'muse-log-'.bin2hex(random_bytes(8));
        mkdir($this->dataHome, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->dataHome);

        parent::tearDown();
    }

    public function testSumsOneRunsCompletionsWithUncachedInput(): void
    {
        $this->writeLog('session-one', [
            $this->completion('run-one', ['input_tokens' => 100, 'output_tokens' => 10, 'cached_tokens' => 70, 'reasoning_tokens' => 4], 'muse-spark-1.3'),
            $this->completion('run-one', ['input_tokens' => 200, 'output_tokens' => 20, 'cached_tokens' => 150, 'reasoning_tokens' => 6], 'muse-spark-1.3'),
            // Another run's completions in the same resumed session stay out.
            $this->completion('run-two', ['input_tokens' => 999, 'output_tokens' => 999], 'muse-spark-1.3'),
            // Malformed lines and foreign records never fail the read.
            'not json {',
            (string) json_encode(['payload_type' => 'task.lifecycle.started', 'payload' => []]),
            (string) json_encode(['payload_type' => 'runtime.session', 'payload' => ['run_id' => 'run-one', 'event' => ['kind' => 'started']]]),
        ]);

        $logged = (new SessionLogReader(['XDG_DATA_HOME' => $this->dataHome]))->readRun('session-one', 'run-one');

        $this->assertNotNull($logged);
        $this->assertSame(30 + 50, $logged->usage->inputTokens);
        $this->assertSame(30, $logged->usage->outputTokens);
        $this->assertSame(220, $logged->usage->cachedTokens);
        $this->assertSame(10, $logged->usage->reasoningTokens);
        $this->assertSame('muse-spark-1.3', $logged->model);
    }

    public function testDominantModelWinsWhenARunSwitchedModels(): void
    {
        $this->writeLog('session-one', [
            $this->completion('run-one', ['input_tokens' => 100, 'output_tokens' => 10], 'muse-spark-1.2'),
            $this->completion('run-one', ['input_tokens' => 900, 'output_tokens' => 90], 'muse-spark-1.3'),
        ]);

        $logged = (new SessionLogReader(['XDG_DATA_HOME' => $this->dataHome]))->readRun('session-one', 'run-one');

        $this->assertNotNull($logged);
        $this->assertSame('muse-spark-1.3', $logged->model);
    }

    public function testReportsNullModelWhenTheLogNamesNone(): void
    {
        $this->writeLog('session-one', [
            $this->completion('run-one', ['input_tokens' => 0, 'output_tokens' => 0]),
        ]);

        $logged = (new SessionLogReader(['XDG_DATA_HOME' => $this->dataHome]))->readRun('session-one', 'run-one');

        $this->assertNotNull($logged);
        $this->assertTrue($logged->usage->isEmpty());
        $this->assertNull($logged->model);
    }

    public function testMissingLogReadsAsAbsent(): void
    {
        $reader = new SessionLogReader(['XDG_DATA_HOME' => $this->dataHome]);

        $this->assertNull($reader->readRun('no-such-session', 'run-one'));
        $this->assertNull($reader->readRun('session-one', ''));
    }

    public function testRunWithoutCompletionsReadsAsAbsent(): void
    {
        $this->writeLog('session-one', [
            $this->completion('run-two', ['input_tokens' => 50, 'output_tokens' => 5], 'muse-spark-1.3'),
        ]);

        $this->assertNull(
            (new SessionLogReader(['XDG_DATA_HOME' => $this->dataHome]))->readRun('session-one', 'run-one'),
        );
    }

    public function testSessionIdOutsideTheCliAlphabetIsRefused(): void
    {
        $reader = new SessionLogReader(['XDG_DATA_HOME' => $this->dataHome]);

        $this->assertNull($reader->readRun('../escape', 'run-one'));
        $this->assertNull($reader->readRun('a/b', 'run-one'));
        $this->assertNull($reader->readRun('', 'run-one'));
    }

    public function testHomeDataHomeIsUsedWhenXdgIsAbsent(): void
    {
        $home = $this->dataHome.DIRECTORY_SEPARATOR.'home';
        mkdir($home.DIRECTORY_SEPARATOR.'.local'.DIRECTORY_SEPARATOR.'share', 0777, true);
        $this->dataHome = $home.DIRECTORY_SEPARATOR.'.local'.DIRECTORY_SEPARATOR.'share';
        $this->writeLog('session-one', [
            $this->completion('run-one', ['input_tokens' => 40, 'output_tokens' => 4], 'muse-spark-1.3'),
        ]);

        $logged = (new SessionLogReader(['HOME' => $home]))->readRun('session-one', 'run-one');

        $this->assertNotNull($logged);
        $this->assertSame(40, $logged->usage->inputTokens);
    }

    public function testRemovedVariablesAreIgnored(): void
    {
        $this->writeLog('session-one', [
            $this->completion('run-one', ['input_tokens' => 40, 'output_tokens' => 4], 'muse-spark-1.3'),
        ]);

        // `false` removes the variable; the reader falls through to the process
        // environment, which in this test run holds no such session.
        $this->assertNull(
            (new SessionLogReader(['XDG_DATA_HOME' => false, 'HOME' => $this->dataHome.DIRECTORY_SEPARATOR.'missing']))->readRun('session-one', 'run-one'),
        );
    }

    /** @param list<string> $lines */
    private function writeLog(string $sessionId, array $lines): void
    {
        $dir = $this->dataHome.DIRECTORY_SEPARATOR.'muse'.DIRECTORY_SEPARATOR.'sessions'
            .DIRECTORY_SEPARATOR.'2026'.DIRECTORY_SEPARATOR.'09'.DIRECTORY_SEPARATOR.'15'
            .DIRECTORY_SEPARATOR.$sessionId;
        mkdir($dir, 0777, true);
        file_put_contents($dir.DIRECTORY_SEPARATOR.'session.jsonl', implode("\n", $lines)."\n");
    }

    /** @param array<string, int> $usage */
    private function completion(string $runId, array $usage, ?string $model = null): string
    {
        return (string) json_encode([
            'schema_version' => 1,
            'stream' => ['kind' => 'session', 'id' => 'session-one'],
            'payload_type' => 'runtime.session',
            'payload' => [
                'kind' => 'run',
                'run_id' => $runId,
                'event' => array_filter([
                    'kind' => 'model_completed',
                    'usage' => $usage,
                    'model' => $model,
                ], static fn (mixed $value): bool => $value !== null),
            ],
        ]);
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
