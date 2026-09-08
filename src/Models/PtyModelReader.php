<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Models;

use Develate\MusecodeCli\Exception\MuseException;
use Develate\MusecodeCli\Process\PtyDisplayReader;

class PtyModelReader
{
    /**
     * @param  array<string, string|false>  $env
     * @return list<ModelInfo>
     */
    public function read(string $binary, array $env = [], float $timeout = 30): array
    {
        $output = (new PtyDisplayReader)->read($binary, '/model', $env, $timeout);
        $models = (new ModelPanelParser)->parse($output);

        return $models !== [] ? $models : throw new MuseException('Muse Code did not return a readable model picker.');
    }
}
