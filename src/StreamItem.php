<?php

declare(strict_types=1);

namespace Develate\MusecodeCli;

interface StreamItem
{
    /** @return array<string, mixed> */
    public function raw(): array;
}
