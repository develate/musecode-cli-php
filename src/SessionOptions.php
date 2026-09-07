<?php

declare(strict_types=1);

namespace Develate\MusecodeCli;

use Develate\MusecodeCli\Value\ApprovalMode;
use Develate\MusecodeCli\Value\Effort;
use Develate\MusecodeCli\Value\SandboxNetwork;

/**
 * Everything that describes a session rather than a single run.
 *
 * These values are stable for the lifetime of a session and are re-sent on
 * every start and resume: `muse exec` keeps no flags across processes.
 */
final readonly class SessionOptions
{
    /**
     * @param  string|null  $provider  startup provider (`echo` or `meta`), or null for the CLI default
     * @param  list<string>  $images  absolute image paths attached to every run of this session
     */
    public function __construct(
        public string $cwd,
        public ?string $model = null,
        public ?Effort $effort = null,
        public ApprovalMode $approvalMode = ApprovalMode::OnRequest,
        public ?string $permissionProfile = null,
        public ?string $workspace = null,
        public ?SandboxNetwork $sandboxNetwork = null,
        public bool $disableWrite = false,
        public bool $disableShell = false,
        public bool $disableApproval = false,
        public bool $disableSandbox = false,
        public bool $disableWebTools = false,
        public bool $noForeignPersonalContext = false,
        public ?int $maxModelSteps = null,
        public ?int $maxToolOutputBytes = null,
        public ?string $baseUrl = null,
        public ?string $provider = null,
        public array $images = [],
        public ?string $sessionId = null,
    ) {
        if ($cwd === '') {
            throw new \InvalidArgumentException('cwd must not be empty.');
        }
        if ($maxModelSteps !== null && $maxModelSteps < 1) {
            throw new \InvalidArgumentException('maxModelSteps must be at least 1.');
        }
    }

    public function withCwd(string $cwd): self
    {
        return $this->with(cwd: $cwd);
    }

    public function withModel(?string $model): self
    {
        return new self(
            $this->cwd, $model, $this->effort, $this->approvalMode, $this->permissionProfile,
            $this->workspace, $this->sandboxNetwork, $this->disableWrite, $this->disableShell,
            $this->disableApproval, $this->disableSandbox,
            $this->disableWebTools, $this->noForeignPersonalContext, $this->maxModelSteps,
            $this->maxToolOutputBytes, $this->baseUrl, $this->provider, $this->images, $this->sessionId,
        );
    }

    public function withEffort(?Effort $effort): self
    {
        return $this->with(effort: $effort);
    }

    public function withApprovalMode(ApprovalMode $approvalMode): self
    {
        return $this->with(approvalMode: $approvalMode);
    }

    public function withSessionId(?string $sessionId): self
    {
        return $this->with(sessionId: $sessionId);
    }

    /**
     * @param  list<string>|null  $images
     */
    public function with(
        ?string $cwd = null,
        ?string $model = null,
        ?Effort $effort = null,
        ?ApprovalMode $approvalMode = null,
        ?string $permissionProfile = null,
        ?string $workspace = null,
        ?SandboxNetwork $sandboxNetwork = null,
        ?bool $disableWrite = null,
        ?bool $disableShell = null,
        ?bool $disableApproval = null,
        ?bool $disableSandbox = null,
        ?bool $disableWebTools = null,
        ?bool $noForeignPersonalContext = null,
        ?int $maxModelSteps = null,
        ?int $maxToolOutputBytes = null,
        ?string $baseUrl = null,
        ?string $provider = null,
        ?array $images = null,
        ?string $sessionId = null,
    ): self {
        return new self(
            cwd: $cwd ?? $this->cwd,
            model: $model ?? $this->model,
            effort: $effort ?? $this->effort,
            approvalMode: $approvalMode ?? $this->approvalMode,
            permissionProfile: $permissionProfile ?? $this->permissionProfile,
            workspace: $workspace ?? $this->workspace,
            sandboxNetwork: $sandboxNetwork ?? $this->sandboxNetwork,
            disableWrite: $disableWrite ?? $this->disableWrite,
            disableShell: $disableShell ?? $this->disableShell,
            disableApproval: $disableApproval ?? $this->disableApproval,
            disableSandbox: $disableSandbox ?? $this->disableSandbox,
            disableWebTools: $disableWebTools ?? $this->disableWebTools,
            noForeignPersonalContext: $noForeignPersonalContext ?? $this->noForeignPersonalContext,
            maxModelSteps: $maxModelSteps ?? $this->maxModelSteps,
            maxToolOutputBytes: $maxToolOutputBytes ?? $this->maxToolOutputBytes,
            baseUrl: $baseUrl ?? $this->baseUrl,
            provider: $provider ?? $this->provider,
            images: $images ?? $this->images,
            sessionId: $sessionId ?? $this->sessionId,
        );
    }
}
