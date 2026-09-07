<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Value;

/**
 * Reasoning effort for a session, mapped onto `muse --reasoning-effort`.
 */
enum Effort: string
{
    case None = 'none';
    case Minimal = 'minimal';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case XHigh = 'xhigh';
    case Max = 'max';
    case Ultra = 'ultra';
}
