<?php

declare(strict_types=1);

namespace Develate\MusecodeCli;

use Develate\MusecodeCli\Event\Event;
use Develate\MusecodeCli\Exception\ProcessFailed;
use Develate\MusecodeCli\Process\ProcessResult;
use Develate\MusecodeCli\SessionLog\SessionLogReader;
use Develate\MusecodeCli\Transport\RunRequest;
use Develate\MusecodeCli\Transport\Transport;
use Develate\MusecodeCli\Value\RunMetadata;

/** @implements \IteratorAggregate<int, StreamItem> */
final class Run implements \IteratorAggregate
{
    private bool $cancelled = false;
    private bool $iterating = false;
    private bool $completed = false;
    private ?Result $lastResult = null;
    private ?\Generator $execution = null;
    private ?\Throwable $failure = null;

    /** @var list<StreamItem> */
    private array $items = [];

    /**
     * @param list<callable(StreamItem): void> $listeners
     * @param callable(Result): void $onComplete
     */
    public function __construct(
        private readonly Transport $transport,
        private readonly RunRequest $request,
        private readonly RunMetadata $metadata,
        private readonly array $listeners,
        private readonly \Closure $onComplete,
        private readonly ?SessionLogReader $usageReader = null,
    ) {
    }

    public function getIterator(): \Traversable
    {
        if ($this->completed) {
            yield from $this->items;

            return;
        }
        if ($this->failure !== null) {
            throw $this->failure;
        }
        if ($this->iterating) {
            throw new \LogicException('A run cannot be iterated concurrently.');
        }

        $this->execution ??= $this->execute();
        $this->iterating = true;
        try {
            while ($this->execution->valid()) {
                yield $this->execution->current();
                $this->execution->next();
            }
        } finally {
            $this->iterating = false;
        }
    }

    public function result(): Result
    {
        if (!$this->completed) {
            foreach ($this as $_) {
            }
        }

        return $this->lastResult ?? throw $this->failure ?? new \LogicException('The run did not produce a result.');
    }

    public function cancel(): void
    {
        $this->cancelled = true;
    }

    public function isComplete(): bool
    {
        return $this->completed;
    }

    /** @return list<StreamItem> */
    public function items(): array
    {
        return $this->items;
    }

    /** @return list<Event> */
    public function events(): array
    {
        return array_values(array_filter($this->items, static fn (StreamItem $item): bool => $item instanceof Event));
    }

    /**
     * The result with the run's logged token usage attached, when the session
     * log recorded any.
     *
     * The stream itself never carries token counts, so the durable log is the
     * only source; a reading there replaces the stream's counters. Reading
     * usage must never fail a turn: an absent or unreadable log keeps the
     * stream's answer.
     */
    private function withSessionLogUsage(Result $result): Result
    {
        if ($this->usageReader === null || $result->sessionId === '' || ($result->runId ?? '') === '') {
            return $result;
        }

        try {
            $logged = $this->usageReader->readRun($result->sessionId, $result->runId);
        } catch (\Throwable) {
            return $result;
        }

        if ($logged === null) {
            return $result;
        }

        if ($logged->usage->isEmpty() && $logged->model === null) {
            return $result;
        }

        return new Result(
            sessionId: $result->sessionId,
            text: $result->text,
            usage: $logged->usage->isEmpty() ? $result->usage : $logged->usage,
            status: $result->status,
            terminal: $result->terminal,
            reason: $result->reason,
            events: $result->events,
            metadata: $result->metadata,
            exitCode: $result->exitCode,
            raw: $result->raw(),
            runId: $result->runId,
            model: $logged->model ?? $result->model,
        );
    }

    /** @return \Generator<int, StreamItem, void, void> */
    private function execute(): \Generator
    {
        $builder = new ResultBuilder($this->request->sessionId ?? '');
        try {
            $stream = $this->transport->stream($this->request->withCancellation(fn (): bool => $this->cancelled));
            foreach ($stream as $item) {
                $builder->add($item);
                $this->items[] = $item;
                foreach ($this->listeners as $listener) {
                    $listener($item);
                }
                yield $item;
            }

            /** @var ProcessResult $process */
            $process = $stream->getReturn();
            $this->lastResult = $this->withSessionLogUsage($builder->build($this->metadata, $process->exitCode));
            if (!$builder->hasTerminal()) {
                // The process died without saying how the run ended: stderr is
                // the only account of what happened.
                throw new ProcessFailed($process->exitCode, $process->stderr);
            }
            ($this->onComplete)($this->lastResult);
            $this->completed = true;
        } catch (\Throwable $exception) {
            $this->failure = $exception;
            throw $exception;
        }
    }
}
