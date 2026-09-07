<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Value;

/**
 * How a run ended, verbatim from the runtime's `RunTerminalKind`.
 */
enum Terminal: string
{
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
