<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Value;

enum ResultStatus: string
{
    case Success = 'success';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public static function fromTerminal(?Terminal $terminal, bool $error): self
    {
        return match ($terminal) {
            Terminal::Completed => $error ? self::Failed : self::Success,
            Terminal::Cancelled => self::Cancelled,
            default => self::Failed,
        };
    }
}
