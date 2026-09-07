<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Tests\Support;

use Develate\MusecodeCli\Event\EventParser;
use Develate\MusecodeCli\Process\ProcessResult;
use Develate\MusecodeCli\Transport\RunRequest;
use Develate\MusecodeCli\Transport\Transport;

/**
 * Replays raw JSONL lines through the real parser.
 *
 * Closer to production than handing the run finished objects: everything a
 * real `muse exec --json` process would emit still has to survive parsing.
 */
final class ScriptedTransport implements Transport
{
    /** @var list<RunRequest> */
    public array $requests = [];

    /** @param list<string> $lines */
    public function __construct(
        private readonly array $lines,
        private readonly int $exitCode = 0,
        private readonly string $stderr = '',
    ) {}

    public function stream(RunRequest $request): \Generator
    {
        $this->requests[] = $request;
        $parser = new EventParser;

        foreach ($this->lines as $line) {
            foreach ($parser->parseLine($line) as $item) {
                yield $item;
            }
        }

        return new ProcessResult($this->exitCode, $this->stderr);
    }
}
