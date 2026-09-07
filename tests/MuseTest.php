<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Tests;

use Develate\MusecodeCli\Exception\InvalidOptions;
use Develate\MusecodeCli\Muse;
use Develate\MusecodeCli\SessionOptions;
use Develate\MusecodeCli\Tests\Support\FakeTransport;
use Develate\MusecodeCli\Transport\RunMode;
use PHPUnit\Framework\TestCase;

final class MuseTest extends TestCase
{
    public function testVersionParsesTheObservedShape(): void
    {
        $muse = new Muse(
            binary: self::stub('fwrite(STDOUT, "Muse Code 1.0.3 (1.0.3-R2198.1)");'),
        );

        $this->assertSame('1.0.3', $muse->version());
        $this->assertTrue($muse->isAvailable());
        $this->assertTrue($muse->isCompatible());
    }

    public function testUnparsableVersionIsUnknown(): void
    {
        $muse = new Muse(binary: self::stub('fwrite(STDOUT, "muse: something unexpected");'));

        $this->assertNull($muse->version());
    }

    public function testMissingBinaryIsUnavailable(): void
    {
        $muse = new Muse('/definitely/not/here-muse-binary');

        $this->assertFalse($muse->isAvailable());
        $this->assertFalse($muse->isCompatible());
        $this->assertNull($muse->version());
    }

    public function testResumeRequiresASessionId(): void
    {
        $this->expectException(InvalidOptions::class);

        (new Muse('muse'))->resume('  ');
    }

    public function testQueryStreamsAgainstTheFakeBinary(): void
    {
        $muse = new Muse(self::fakeMuse());

        $result = $muse->in(sys_get_temp_dir())->query(
            'say hi',
            new SessionOptions(cwd: sys_get_temp_dir(), provider: 'echo'),
        );

        $this->assertTrue($result->isSuccess());
        $this->assertSame('echo: say hi', $result->text);
        $this->assertNotSame('', $result->sessionId);
    }

    public function testIsAuthenticatedReadsApiKeyAndAuthFile(): void
    {
        $home = sys_get_temp_dir().'/musecode-auth-'.uniqid();
        mkdir($home.'/.config/muse', 0777, true);

        try {
            $signedOut = new Muse('muse', env: ['HOME' => $home, 'META_API_KEY' => false]);
            $this->assertFalse($signedOut->isAuthenticated());

            $byKey = new Muse('muse', env: ['HOME' => $home, 'META_API_KEY' => 'key-one']);
            $this->assertTrue($byKey->isAuthenticated());

            file_put_contents($home.'/.config/muse/auth.json', '{"token":"abc"}');
            $this->assertTrue($signedOut->isAuthenticated());
            $this->assertSame($home.'/.config/muse/auth.json', $signedOut->authFilePath());
        } finally {
            @unlink($home.'/.config/muse/auth.json');
            @rmdir($home.'/.config/muse');
            @rmdir($home.'/.config');
            @rmdir($home);
        }
    }

    public function testSessionBecomesResumableAfterTheFirstRun(): void
    {
        $transport = new FakeTransport;
        $muse = new Muse('muse', $transport);

        $session = $muse->session(new SessionOptions(cwd: sys_get_temp_dir()));
        $session->query('first');

        $this->assertSame('session-one', $session->id());

        $session->stream('second')->result();
        $modes = array_map(
            static fn ($request): RunMode => $request->mode,
            $transport->requests,
        );

        $this->assertSame([RunMode::Start, RunMode::Resume], $modes);
    }

    private static function stub(string $php): string
    {
        $path = sys_get_temp_dir().'/muse-stub-'.substr(sha1($php), 0, 12).'.sh';
        file_put_contents($path, sprintf("#!/bin/sh\nexec %s -r %s\n", escapeshellarg(PHP_BINARY), escapeshellarg($php)));
        chmod($path, 0700);

        return $path;
    }

    private static function fakeMuse(): string
    {
        $path = sys_get_temp_dir().'/muse-fake-'.substr(sha1(__DIR__), 0, 12).'.sh';
        file_put_contents($path, sprintf(
            "#!/bin/sh\nexec %s %s \"$@\"\n",
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__.'/Support/fake-muse.php'),
        ));
        chmod($path, 0700);

        return $path;
    }
}
