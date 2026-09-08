<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Tests\Models;

use Develate\MusecodeCli\Exception\MuseException;
use Develate\MusecodeCli\Exception\ProcessTimedOut;
use Develate\MusecodeCli\Muse;
use PHPUnit\Framework\TestCase;

final class PtyModelReaderTest extends TestCase
{
    public function test_reads_models_and_stops_the_child(): void
    {
        $this->probe('success');
    }

    public function test_timeout_stops_the_child(): void
    {
        $this->expectException(ProcessTimedOut::class);
        $this->probe('timeout');
    }

    public function test_premature_exit_does_not_report_zero_usage(): void
    {
        $this->expectException(MuseException::class);
        $this->probe('exit');
    }

    private function probe(string $mode): void
    {
        if (! is_executable('/usr/bin/expect') || ! function_exists('posix_kill')) {
            self::markTestSkipped('Expect and POSIX are required.');
        }
        $pidFile = tempnam(sys_get_temp_dir(), 'muse-pid-');
        $cwdFile = tempnam(sys_get_temp_dir(), 'muse-cwd-');
        $muse = new Muse(binary: dirname(__DIR__).'/Support/fake-muse-models.php', env: [
            'MUSE_TEST_PID' => $pidFile,
            'MUSE_TEST_CWD' => $cwdFile,
            'MUSE_TEST_MODE' => $mode,
        ], timeout: 3);

        try {
            $models = $muse->models();

            self::assertSame(['muse-spark-1.3', 'muse-spark-1.3-contributor'], array_map(static fn ($model) => $model->slug, $models));
            self::assertSame('Your content may be used for product improvement.', $models[1]->description);
        } finally {
            $pid = (int) file_get_contents($pidFile);
            self::assertGreaterThan(0, $pid);
            self::assertFalse(posix_kill($pid, 0), 'The probe must reap the Muse child.');
            self::assertDirectoryDoesNotExist(trim(file_get_contents($cwdFile)));
            unlink($pidFile);
            unlink($cwdFile);
        }
    }
}
