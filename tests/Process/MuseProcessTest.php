<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Tests\Process;

use Develate\MusecodeCli\Exception\MuseNotFound;
use Develate\MusecodeCli\Process\MuseProcess;
use Develate\MusecodeCli\Process\ProcessFactory;
use PHPUnit\Framework\TestCase;

final class MuseProcessTest extends TestCase
{
    public function testLinesYieldStdoutAndReturnExitCodeAndStderr(): void
    {
        $process = (new ProcessFactory)->create(
            [PHP_BINARY, __DIR__.'/../Support/fake-muse.php', 'exec', '--json', 'say hi'],
            sys_get_temp_dir(),
        );

        $lines = [];
        $generator = $process->lines();
        foreach ($generator as $line) {
            $lines[] = $line;
        }
        $result = $generator->getReturn();

        $this->assertNotSame([], $lines);
        foreach ($lines as $line) {
            $this->assertIsArray(json_decode($line, true));
        }
        $this->assertSame(0, $result->exitCode);
        $this->assertStringContainsString('muse:', $result->stderr);
    }

    public function testMissingBinaryThrows(): void
    {
        $this->expectException(MuseNotFound::class);

        $generator = (new MuseProcess(['/definitely/not/here-muse-binary']))->lines();
        foreach ($generator as $_) {
        }
    }
}
