<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Value;

final readonly class RunMetadata
{
    /** @param list<string> $models */
    public function __construct(
        public ?string $museVersion,
        public ?string $requestedModel,
        public string $cwd,
        public array $models = [],
    ) {}
}
