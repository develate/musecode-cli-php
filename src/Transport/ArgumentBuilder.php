<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Transport;

use Develate\MusecodeCli\RunOptions;
use Develate\MusecodeCli\SessionOptions;
use Develate\MusecodeCli\Value\ApprovalMode;

/**
 * Builds the `muse exec --json` command for a run.
 *
 * Kept apart from the process handling so the exact argv is unit-testable
 * without spawning anything.
 */
final readonly class ArgumentBuilder
{
    /** @return list<string> */
    public function build(string $binary, RunRequest $request): array
    {
        $session = $request->session;
        $command = [$binary, 'exec', '--json'];

        if ($request->mode === RunMode::Resume) {
            if ($request->sessionId === null || $request->sessionId === '') {
                throw new \LogicException('Resume runs require a session id.');
            }
            $command[] = '--session-id';
            $command[] = $request->sessionId;
        } elseif ($request->sessionId !== null && $request->sessionId !== '') {
            $command[] = '--session-id';
            $command[] = $request->sessionId;
        }

        array_push($command, ...$this->modelFlags($session));
        array_push($command, ...$this->approvalFlags($session));
        array_push($command, ...$this->sandboxFlags($session));
        array_push($command, ...$this->scopeFlags($session));
        array_push($command, ...$this->limitFlags($session));
        array_push($command, ...$this->imageFlags($session, $request->run));

        $command[] = '--';
        $command[] = $request->prompt;

        return $command;
    }

    /** @return list<string> */
    private function modelFlags(SessionOptions $session): array
    {
        $flags = [];

        if ($session->provider !== null && $session->provider !== '') {
            $flags[] = '--provider';
            $flags[] = $session->provider;
        }
        if ($session->model !== null && $session->model !== '') {
            $flags[] = '--model';
            $flags[] = $session->model;
        }
        if ($session->effort !== null) {
            $flags[] = '--reasoning-effort';
            $flags[] = $session->effort->value;
        }
        if ($session->baseUrl !== null && $session->baseUrl !== '') {
            $flags[] = '--base-url';
            $flags[] = $session->baseUrl;
        }

        return $flags;
    }

    /** @return list<string> */
    private function approvalFlags(SessionOptions $session): array
    {
        $flags = [];

        // The CLI default is `on-request`, so only a deliberate choice is sent.
        if ($session->approvalMode !== ApprovalMode::OnRequest) {
            $flags[] = '--approval-mode';
            $flags[] = $session->approvalMode->value;
        }
        if ($session->permissionProfile !== null && $session->permissionProfile !== '') {
            $flags[] = '--permission-profile';
            $flags[] = $session->permissionProfile;
        }

        return $flags;
    }

    /** @return list<string> */
    private function sandboxFlags(SessionOptions $session): array
    {
        $flags = [];

        if ($session->sandboxNetwork !== null) {
            $flags[] = '--sandbox-network';
            $flags[] = $session->sandboxNetwork->value;
        }
        if ($session->disableWrite) {
            $flags[] = '--disable-write';
        }
        if ($session->disableShell) {
            $flags[] = '--disable-shell';
        }
        if ($session->disableApproval) {
            $flags[] = '--disable-approval';
        }
        if ($session->disableSandbox) {
            $flags[] = '--disable-sandbox';
        }

        return $flags;
    }

    /** @return list<string> */
    private function scopeFlags(SessionOptions $session): array
    {
        $flags = [];

        // The workspace is the policy root for every tool the run may use.
        // Always naming it keeps a run from inheriting whatever directory the
        // process happened to start in.
        $flags[] = '--workspace';
        $flags[] = $session->workspace ?? $session->cwd;

        if ($session->disableWebTools) {
            $flags[] = '--disable-web-tools';
        }
        if ($session->noForeignPersonalContext) {
            $flags[] = '--no-foreign-personal-context';
        }

        return $flags;
    }

    /** @return list<string> */
    private function limitFlags(SessionOptions $session): array
    {
        $flags = [];

        if ($session->maxModelSteps !== null) {
            $flags[] = '--max-model-steps';
            $flags[] = (string) $session->maxModelSteps;
        }
        if ($session->maxToolOutputBytes !== null) {
            $flags[] = '--max-tool-output-bytes';
            $flags[] = (string) $session->maxToolOutputBytes;
        }

        return $flags;
    }

    /** @return list<string> */
    private function imageFlags(SessionOptions $session, RunOptions $run): array
    {
        $flags = [];

        foreach ([...$session->images, ...$run->images] as $image) {
            $flags[] = '--image';
            $flags[] = $image;
        }

        return $flags;
    }
}
