<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Quota;

final readonly class Quota
{
    public function __construct(
        public float $currentUsedPercent,
        public float $weeklyUsedPercent,
        public string $currentReset,
        public string $weeklyReset,
    ) {}
}
