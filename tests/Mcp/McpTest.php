<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Tests\Mcp;

use Develate\MusecodeCli\Exception\InvalidOptions;
use Develate\MusecodeCli\Exception\MuseException;
use Develate\MusecodeCli\Muse;
use PHPUnit\Framework\TestCase;

final class McpTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/muse-mcp-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/muse/*') ?: [] as $path) {
            unlink($path);
        }
        if (is_dir($this->directory.'/muse')) {
            rmdir($this->directory.'/muse');
        }
        rmdir($this->directory);
    }

    private function muse(): Muse
    {
        return new Muse('/not/installed', env: ['XDG_CONFIG_HOME' => $this->directory]);
    }

    public function testLifecyclePreservesOtherSettingsAndJsonObjects(): void
    {
        $mcp = $this->muse()->mcp();
        self::assertSame([], $mcp->list());
        self::assertFileDoesNotExist($mcp->settingsFilePath());
        mkdir($this->directory.'/muse');
        file_put_contents($mcp->settingsFilePath(), '{"schema_version":1,"custom":{"empty":{},"list":[]}}');

        $mcp->add('local', ['transport' => 'stdio', 'command' => 'node', 'args' => ['server.js'], 'env' => []]);
        $mcp->add('remote', ['transport' => 'streamable_http', 'url' => 'https://example.test/mcp', 'headers' => ['Authorization' => 'Bearer test'], 'mode' => 'optional']);
        $mcp->disable('local');
        self::assertFalse($mcp->list()['local']['enabled']);
        $mcp->enable('local');
        self::assertTrue($mcp->list()['local']['enabled']);
        self::assertCount(2, $mcp->list());
        $settings = json_decode(file_get_contents($mcp->settingsFilePath()));
        self::assertInstanceOf(\stdClass::class, $settings->custom->empty);
        self::assertSame([], $settings->custom->list);
        self::assertInstanceOf(\stdClass::class, $settings->mcp_servers->local->env);
        self::assertSame(0600, fileperms($mcp->settingsFilePath()) & 0777);
        $mcp->add('local', ['transport' => 'stdio', 'command' => 'replacement']);
        self::assertSame(['transport' => 'stdio', 'command' => 'replacement'], $mcp->list()['local']);
        $mcp->remove('missing');
        self::assertCount(2, $mcp->list());
        $mcp->remove('local');
        $mcp->remove('remote');
        self::assertSame([], $mcp->list());
        self::assertInstanceOf(\stdClass::class, json_decode(file_get_contents($mcp->settingsFilePath()))->mcp_servers);
    }

    public function testInvalidSettingsAreNeverOverwritten(): void
    {
        $mcp = $this->muse()->mcp();
        mkdir($this->directory.'/muse');
        foreach (['{broken', '[]', '{"schema_version":2}', '{"schema_version":1,"mcp_servers":[]}', '{"schema_version":1,"mcp_servers":{"bad":null}}'] as $json) {
            file_put_contents($mcp->settingsFilePath(), $json);
            try {
                $mcp->add('local', ['transport' => 'stdio', 'command' => 'node']);
                self::fail('Invalid settings accepted.');
            } catch (MuseException) {
                self::assertSame($json, file_get_contents($mcp->settingsFilePath()));
            }
        }
    }

    public function testInvalidServersDoNotCreateSettings(): void
    {
        $mcp = $this->muse()->mcp();
        foreach ([[], ['transport' => 'sse'], ['transport' => 'stdio', 'command' => ' '], ['transport' => 'stdio', 'command' => 'node', 'args' => [1]], ['transport' => 'streamable_http', 'url' => 'https://example.test', 'framing' => 'jsonl']] as $server) {
            try {
                $mcp->add('test', $server);
                self::fail('Invalid server accepted.');
            } catch (InvalidOptions) {
                self::assertFileDoesNotExist($mcp->settingsFilePath());
            }
        }
    }

    public function testUnknownServerCannotBeEnabled(): void
    {
        $this->expectException(InvalidOptions::class);
        $this->muse()->mcp()->enable('missing');
    }

    public function testPathUsesClientEnvironment(): void
    {
        self::assertSame($this->directory.'/muse/settings.json', $this->muse()->mcp()->settingsFilePath());
        $muse = new Muse(env: ['HOME' => $this->directory, 'XDG_CONFIG_HOME' => false]);
        self::assertSame($this->directory.'/.config/muse/settings.json', $muse->mcp()->settingsFilePath());
    }
}
