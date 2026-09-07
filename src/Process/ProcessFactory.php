<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Process;

class ProcessFactory
{
    /**
     * @param  list<string>  $command
     * @param  array<string, string|false>  $env  merged onto the inherited environment;
     *                                            `false` removes an inherited variable
     */
    public function create(
        array $command,
        ?string $cwd = null,
        ?float $timeout = null,
        array $env = [],
        ?string $input = null,
    ): MuseProcess {
        return new MuseProcess($command, $cwd, $timeout, $env, $input);
    }
}
