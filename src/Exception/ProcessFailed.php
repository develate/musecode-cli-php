<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Exception;

final class ProcessFailed extends MuseException
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $stderr = '',
    ) {
        parent::__construct(sprintf(
            'Muse Code exited with code %d%s',
            $exitCode,
            $stderr === '' ? '.' : ': '.trim($stderr),
        ), $exitCode);
    }
}
