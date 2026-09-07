<?php

declare(strict_types=1);

namespace Develate\MusecodeCli;

/**
 * Everything that describes one run rather than the session it belongs to.
 */
final readonly class RunOptions
{
    /**
     * @param  list<string>  $images  absolute image paths attached to this run,
     *                                in addition to the session's own images
     */
    public function __construct(
        public array $images = [],
        public ?float $timeout = null,
    ) {}

    public function withTimeout(?float $timeout): self
    {
        return new self($this->images, $timeout);
    }
}
