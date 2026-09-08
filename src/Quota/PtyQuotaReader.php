<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Quota;

use Develate\MusecodeCli\Exception\MuseException;
use Develate\MusecodeCli\Process\PtyDisplayReader;

class PtyQuotaReader
{
    /** @param array<string, string|false> $env */
    public function read(string $binary, array $env = [], float $timeout = 30): Quota
    {
        $output = (new PtyDisplayReader)->read($binary, '/upgrade', $env, $timeout);

        return (new UsagePanelParser)->parse($output)
            ?? throw new MuseException('Muse Code did not return a readable subscription plan and quota.');
    }
}
