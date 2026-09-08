<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Mcp;

use Develate\MusecodeCli\Exception\InvalidOptions;
use Develate\MusecodeCli\Exception\MuseException;

/** Manages the MCP entries shared by all runs using this settings file. */
final readonly class Mcp
{
    public function __construct(private string $path)
    {
    }

    public function settingsFilePath(): string
    {
        return $this->path;
    }

    /** @return array<string, array<string, mixed>> Configured servers, not connection status. */
    public function list(): array
    {
        $settings = $this->read();

        return json_decode(json_encode($settings->mcp_servers ?? new \stdClass, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $server Native Muse server configuration. */
    public function add(string $name, array $server): void
    {
        self::validateName($name);
        $transport = $server['transport'] ?? null;
        $field = match ($transport) {
            'stdio' => 'command',
            'streamable_http' => 'url',
            default => throw new InvalidOptions('MCP transport must be stdio or streamable_http.'),
        };
        if (! is_string($server[$field] ?? null) || trim($server[$field]) === '') {
            throw new InvalidOptions("MCP server requires a non-empty {$field}.");
        }
        if (array_key_exists('mode', $server) && ! in_array($server['mode'], ['required', 'optional'], true)) {
            throw new InvalidOptions('MCP mode must be required or optional.');
        }
        if (array_key_exists('enabled', $server) && ! is_bool($server['enabled'])) {
            throw new InvalidOptions('MCP enabled must be a boolean.');
        }
        foreach (['env', 'headers'] as $map) {
            if (array_key_exists($map, $server)) {
                if (! is_array($server[$map])) {
                    throw new InvalidOptions("MCP {$map} must be a string map.");
                }
                foreach ($server[$map] as $key => $value) {
                    if (! is_string($key) || ! is_string($value)) {
                        throw new InvalidOptions("MCP {$map} must be a string map.");
                    }
                }
                $server[$map] = (object) $server[$map];
            }
        }
        if (array_key_exists('args', $server) && (! is_array($server['args']) || ! array_is_list($server['args']) || count(array_filter($server['args'], 'is_string')) !== count($server['args']))) {
            throw new InvalidOptions('MCP args must be a list of strings.');
        }
        if ($transport === 'streamable_http' && array_key_exists('framing', $server)) {
            throw new InvalidOptions('HTTP MCP servers do not support framing.');
        }

        $this->update(static function (\stdClass $servers) use ($name, $server): void {
            $servers->{$name} = (object) $server;
        });
    }

    public function remove(string $name): void
    {
        self::validateName($name);
        $this->update(static function (\stdClass $servers) use ($name): void {
            unset($servers->{$name});
        });
    }

    public function enable(string $name): void
    {
        $this->setEnabled($name, true);
    }

    public function disable(string $name): void
    {
        $this->setEnabled($name, false);
    }

    private function setEnabled(string $name, bool $enabled): void
    {
        self::validateName($name);
        $this->update(static function (\stdClass $servers) use ($name, $enabled): void {
            if (! isset($servers->{$name})) {
                throw new InvalidOptions('MCP server is not configured: '.$name);
            }
            $servers->{$name}->enabled = $enabled;
        });
    }

    private static function validateName(string $name): void
    {
        if (trim($name) === '') {
            throw new InvalidOptions('An MCP server name must not be empty.');
        }
    }

    private function read(): \stdClass
    {
        if (! file_exists($this->path)) {
            return (object) ['schema_version' => 1];
        }
        $json = @file_get_contents($this->path);
        if ($json === false) {
            throw new MuseException('Cannot read Muse settings: '.$this->path);
        }
        try {
            $settings = json_decode($json, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new MuseException('Invalid JSON in Muse settings: '.$this->path, 0, $exception);
        }
        if (! $settings instanceof \stdClass || ($settings->schema_version ?? null) !== 1) {
            throw new MuseException('Muse settings must be an object with schema_version 1.');
        }
        if (property_exists($settings, 'mcp_servers')) {
            if (! $settings->mcp_servers instanceof \stdClass) {
                throw new MuseException('Muse mcp_servers must be an object.');
            }
            foreach ($settings->mcp_servers as $server) {
                if (! $server instanceof \stdClass) {
                    throw new MuseException('Muse MCP server entries must be objects.');
                }
            }
        }

        return $settings;
    }

    /** Serialize SDK writers and atomically replace settings so CLI readers never see partial JSON. */
    private function update(callable $change): void
    {
        $directory = dirname($this->path);
        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new MuseException('Cannot create Muse configuration directory: '.$directory);
        }
        $lock = @fopen($this->path.'.lock', 'c');
        if ($lock === false) {
            throw new MuseException('Cannot lock Muse settings: '.$this->path);
        }
        $temporary = false;
        try {
            if (! flock($lock, LOCK_EX)) {
                throw new MuseException('Cannot lock Muse settings: '.$this->path);
            }
            $settings = $this->read();
            $settings->mcp_servers ??= new \stdClass;
            $change($settings->mcp_servers);
            $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
            $temporary = tempnam($directory, '.mcp-');
            if ($temporary === false || ! chmod($temporary, 0600)
                || file_put_contents($temporary, $json) !== strlen($json)
                || ! rename($temporary, $this->path)) {
                throw new MuseException('Cannot write Muse settings: '.$this->path);
            }
        } finally {
            if ($temporary !== false && is_file($temporary)) {
                unlink($temporary);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
