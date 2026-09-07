<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Tests;

use Develate\MusecodeCli\Muse;
use Develate\MusecodeCli\SessionOptions;
use Develate\MusecodeCli\Tests\Support\FakeTransport;
use Develate\MusecodeCli\Transport\RunMode;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    public function testExplicitSessionIdIsSentOnStart(): void
    {
        $transport = new FakeTransport;
        $muse = new Muse('muse', $transport);

        $muse->session(new SessionOptions(cwd: sys_get_temp_dir(), sessionId: 'custom-id'))->query('start');
        $muse->resume('existing-id', new SessionOptions(cwd: sys_get_temp_dir()))->query('continue');

        $this->assertSame(RunMode::Start, $transport->requests[0]->mode);
        $this->assertSame('custom-id', $transport->requests[0]->sessionId);
        $this->assertSame(RunMode::Resume, $transport->requests[1]->mode);
        $this->assertSame('existing-id', $transport->requests[1]->sessionId);
    }

    public function testResultIsKeptOnTheSession(): void
    {
        $session = (new Muse('muse', new FakeTransport))
            ->session(new SessionOptions(cwd: sys_get_temp_dir()));

        $session->query('hello');

        $this->assertSame('answer: hello', $session->result()->text);
    }

    public function testResultBeforeAnyRunThrows(): void
    {
        $session = (new Muse('muse', new FakeTransport))
            ->session(new SessionOptions(cwd: sys_get_temp_dir()));

        $this->expectException(\LogicException::class);
        $session->result();
    }

    public function testWithKeepsTheConversation(): void
    {
        $transport = new FakeTransport;
        $muse = new Muse('muse', $transport);

        $session = $muse->session(new SessionOptions(cwd: sys_get_temp_dir(), model: 'one'));
        $session->query('first');

        $session->with(new SessionOptions(cwd: sys_get_temp_dir(), model: 'two'))->query('second');

        $this->assertSame('two', $transport->requests[1]->session->model);
        $this->assertSame(RunMode::Resume, $transport->requests[1]->mode);
        $this->assertSame('session-one', $transport->requests[1]->sessionId);
    }

    public function testListenersSeeEveryItem(): void
    {
        $seen = [];
        $session = (new Muse('muse', new FakeTransport))
            ->session(new SessionOptions(cwd: sys_get_temp_dir()));
        $session->onItem(static function ($item) use (&$seen): void {
            $seen[] = $item;
        });

        $session->query('hello');

        $this->assertCount(2, $seen);
    }
}
