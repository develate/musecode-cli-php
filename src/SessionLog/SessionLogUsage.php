<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\SessionLog;

use Develate\MusecodeCli\Value\Usage;

/**
 * What one run recorded in its session log: summed token counters plus the
 * model that processed most of the run's input, if the log named one.
 */
final readonly class SessionLogUsage
{
    public function __construct(
        public Usage $usage,
        public ?string $model = null,
    ) {}
}
