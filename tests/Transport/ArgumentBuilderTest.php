<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Tests\Transport;

use Develate\MusecodeCli\RunOptions;
use Develate\MusecodeCli\SessionOptions;
use Develate\MusecodeCli\Transport\ArgumentBuilder;
use Develate\MusecodeCli\Transport\RunMode;
use Develate\MusecodeCli\Transport\RunRequest;
use Develate\MusecodeCli\Value\ApprovalMode;
use Develate\MusecodeCli\Value\Effort;
use Develate\MusecodeCli\Value\SandboxNetwork;
use PHPUnit\Framework\TestCase;

final class ArgumentBuilderTest extends TestCase
{
    public function testMinimalStartCommand(): void
    {
        $command = (new ArgumentBuilder)->build('muse', new RunRequest(
            RunMode::Start, null, 'say hi', new SessionOptions(cwd: '/tmp/app'),
        ));

        $this->assertSame(
            ['muse', 'exec', '--json', '--workspace', '/tmp/app', '--', 'say hi'],
            $command,
        );
    }

    public function testResumeNamesTheSession(): void
    {
        $command = (new ArgumentBuilder)->build('muse', new RunRequest(
            RunMode::Resume, 'session-one', 'again', new SessionOptions(cwd: '/tmp/app'),
        ));

        $this->assertContains('--session-id', $command);
        $this->assertContains('session-one', $command);
    }

    public function testResumeWithoutASessionIdThrows(): void
    {
        $this->expectException(\LogicException::class);

        (new ArgumentBuilder)->build('muse', new RunRequest(
            RunMode::Resume, null, 'again', new SessionOptions(cwd: '/tmp/app'),
        ));
    }

    public function testFullOptions(): void
    {
        $command = (new ArgumentBuilder)->build('muse', new RunRequest(
            RunMode::Start,
            null,
            'do it',
            new SessionOptions(
                cwd: '/tmp/app',
                model: 'metamate',
                effort: Effort::Low,
                approvalMode: ApprovalMode::Never,
                permissionProfile: 'profile-one',
                workspace: '/tmp/ws',
                sandboxNetwork: SandboxNetwork::Restricted,
                disableWrite: true,
                disableShell: true,
                disableWebTools: true,
                maxModelSteps: 10,
                maxToolOutputBytes: 1024,
                baseUrl: 'https://example.test',
                provider: 'meta',
            ),
            new RunOptions(images: ['/tmp/shot.png']),
        ));

        foreach ([
            ['--provider', 'meta'],
            ['--model', 'metamate'],
            ['--reasoning-effort', 'low'],
            ['--approval-mode', 'never'],
            ['--permission-profile', 'profile-one'],
            ['--workspace', '/tmp/ws'],
            ['--sandbox-network', 'restricted'],
            ['--max-model-steps', '10'],
            ['--max-tool-output-bytes', '1024'],
            ['--base-url', 'https://example.test'],
            ['--image', '/tmp/shot.png'],
        ] as [$flag, $value]) {
            $position = array_search($flag, $command, true);
            $this->assertNotFalse($position, $flag);
            $this->assertSame($value, $command[$position + 1]);
        }

        $this->assertContains('--disable-write', $command);
        $this->assertContains('--disable-shell', $command);
        $this->assertContains('--disable-web-tools', $command);

        $fullAccess = (new ArgumentBuilder)->build('muse', new RunRequest(
            RunMode::Start,
            null,
            'go',
            (new SessionOptions(cwd: '/tmp/app'))->with(disableApproval: true, disableSandbox: true),
        ));

        $this->assertContains('--disable-approval', $fullAccess);
        $this->assertContains('--disable-sandbox', $fullAccess);
        $this->assertSame('do it', end($command));
        $this->assertSame('--', $command[count($command) - 2]);
    }

    public function testDefaultApprovalModeIsOmitted(): void
    {
        $command = (new ArgumentBuilder)->build('muse', new RunRequest(
            RunMode::Start, null, 'hi', new SessionOptions(cwd: '/tmp/app'),
        ));

        $this->assertNotContains('--approval-mode', $command);
    }
}
