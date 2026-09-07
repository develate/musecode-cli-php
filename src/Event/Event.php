<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Event;

use Develate\MusecodeCli\StreamItem;

interface Event extends StreamItem
{
    public function payloadType(): string;
}
