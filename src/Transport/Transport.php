<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Transport;

use Develate\MusecodeCli\Process\ProcessResult;
use Develate\MusecodeCli\StreamItem;

interface Transport
{
    /** @return \Generator<int, StreamItem, void, ProcessResult> */
    public function stream(RunRequest $request): \Generator;
}
