<?php

declare(strict_types=1);

namespace Develate\MusecodeCli;

use Develate\MusecodeCli\Exception\InvalidOptions;
use Develate\MusecodeCli\Transport\RunMode;
use Develate\MusecodeCli\Transport\RunRequest;
use Develate\MusecodeCli\Transport\Transport;
use Develate\MusecodeCli\Value\RunMetadata;

final class Session
{
    private ?Result $lastResult = null;

    private ?string $sessionId;

    /** @var list<callable(StreamItem): void> */
    private array $listeners = [];

    public function __construct(
        private readonly Transport $transport,
        private readonly string $museVersion,
        private readonly SessionOptions $options,
        private readonly ?float $timeout = null,
        ?string $sessionId = null,
        private RunMode $nextMode = RunMode::Start,
    ) {
        $this->sessionId = $sessionId ?? $options->sessionId;
    }

    public function id(): ?string
    {
        return $this->sessionId;
    }

    public function options(): SessionOptions
    {
        return $this->options;
    }

    public function query(string $prompt, ?RunOptions $options = null): Result
    {
        return $this->stream($prompt, $options)->result();
    }

    public function stream(string $prompt, ?RunOptions $options = null): Run
    {
        if (trim($prompt) === '') {
            throw new InvalidOptions('The prompt must not be empty.');
        }

        $run = $options ?? new RunOptions;

        if ($run->timeout === null && $this->timeout !== null) {
            $run = $run->withTimeout($this->timeout);
        }

        return new Run(
            transport: $this->transport,
            request: new RunRequest(
                mode: $this->nextMode,
                sessionId: $this->sessionId,
                prompt: $prompt,
                session: $this->options,
                run: $run,
            ),
            metadata: new RunMetadata($this->museVersion, $this->options->model, $this->options->cwd),
            listeners: $this->listeners,
            onComplete: function (Result $result): void {
                $this->lastResult = $result;
                if ($result->sessionId !== '') {
                    $this->sessionId = $result->sessionId;
                    $this->nextMode = RunMode::Resume;
                }
            },
        );
    }

    public function result(): Result
    {
        return $this->lastResult ?? throw new \LogicException('This session has no completed result yet.');
    }

    /**
     * A copy of this session that runs with different options.
     *
     * The conversation is kept: only what the next run is told about it changes.
     */
    public function with(SessionOptions $options): self
    {
        return new self(
            transport: $this->transport,
            museVersion: $this->museVersion,
            options: $options,
            timeout: $this->timeout,
            sessionId: $this->sessionId,
            nextMode: $this->nextMode,
        );
    }

    /** @param callable(StreamItem): void $listener */
    public function onItem(callable $listener): self
    {
        $this->listeners[] = $listener;

        return $this;
    }
}
