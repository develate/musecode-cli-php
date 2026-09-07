<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Process;

use Develate\MusecodeCli\Exception\MuseNotFound;
use Symfony\Component\Process\ExecutableFinder;

final class MuseExecutable
{
    public static function assertAvailable(string $binary): void
    {
        $hasPath = str_contains($binary, '/') || str_contains($binary, '\\');
        $available = $hasPath
            ? is_file($binary) && is_executable($binary)
            : (new ExecutableFinder())->find($binary) !== null;

        if (!$available) {
            throw new MuseNotFound(sprintf('Muse Code binary "%s" was not found or is not executable.', $binary));
        }
    }

    public static function isAvailable(string $binary): bool
    {
        try {
            self::assertAvailable($binary);
        } catch (MuseNotFound) {
            return false;
        }

        return true;
    }
}
