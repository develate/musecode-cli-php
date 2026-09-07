<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Exception;

final class InvalidStreamJson extends MuseException
{
    public function __construct(
        public readonly string $jsonLine,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(sprintf(
            'Muse Code emitted a line that is not a JSON object%s',
            $jsonLine === '' ? '.' : ': '.mb_substr($jsonLine, 0, 200),
        ), 0, $previous);
    }
}
