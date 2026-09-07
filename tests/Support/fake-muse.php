<?php

declare(strict_types=1);

/**
 * A stand-in for `muse`, faithful to the parts of its interface this SDK uses:
 * the `exec --json` flag syntax, the MSP JSONL turn protocol, the terminal
 * record and the diagnostic chatter on stderr.
 */

$arguments = array_slice($argv, 1);
$flags = [];
$positional = [];
foreach ($arguments as $argument) {
    if ($argument === '--') {
        continue;
    }
    if (str_starts_with($argument, '--')) {
        [$name, $value] = array_pad(explode('=', substr($argument, 2), 2), 2, null);
        $flags[$name] = $value ?? true;
    } else {
        $positional[] = $argument;
    }
}

fwrite(STDERR, "muse: workspace root: /tmp/fake-muse (cwd default)\n");

if (isset($flags['version'])) {
    fwrite(STDOUT, "Muse Code 1.0.3 (1.0.3-R2198.1)\n");
    exit(0);
}

$command = $positional[0] ?? null;

if ($command === 'exec' && isset($flags['help'])) {
    fwrite(STDOUT, "muse exec — run one prompt non-interactively (headless)\n");
    exit(0);
}

if ($command !== 'exec' || !isset($flags['json'])) {
    fwrite(STDERR, "usage: muse exec [OPTIONS] [PROMPT] (run `muse exec --help` for options)\n");
    exit(2);
}

$sessionId = is_string($flags['session-id'] ?? null) && $flags['session-id'] !== ''
    ? $flags['session-id']
    : '11111111-2222-4333-8444-555555555555';
$commandId = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
$prompt = end($positional);
if (!is_string($prompt) || $command === $prompt) {
    $prompt = '';
}

$emit = static function (int $sequence, string $payloadType, array $payload) use ($sessionId, $commandId): void {
    fwrite(STDOUT, json_encode([
        'schema_version' => 1,
        'id' => sprintf('018f0000-0000-7000-8000-%012d', $sequence),
        'stream' => ['kind' => 'session', 'id' => $sessionId],
        'sequence' => $sequence,
        'recorded_at' => 1780531400000000 + $sequence,
        'record_type' => 'event',
        'durability' => 'durable',
        'causation_id' => $commandId,
        'payload_type' => $payloadType,
        'payload_schema_version' => 1,
        'payload' => $payload,
    ], JSON_UNESCAPED_SLASHES)."\n");
};

if (isset($flags['crash'])) {
    fwrite(STDERR, "muse: runtime host failed to start: boom\n");
    exit(1);
}

$emit(1, 'runtime.command.accepted', ['kind' => 'command_accepted', 'command_id' => $commandId]);
$emit(2, 'session.run.linked', ['kind' => 'session_run_linked', 'command_id' => $commandId]);

if (isset($flags['fail'])) {
    $emit(3, 'run.terminal.failed', [
        'kind' => 'run_terminal',
        'command_id' => $commandId,
        'terminal' => 'failed',
        'text' => '',
        'reason' => 'provider_unavailable',
    ]);
    exit(0);
}

$text = 'echo: '.$prompt;
$emit(3, 'run.lifecycle.started', ['kind' => 'run_started', 'command_id' => $commandId, 'prompt' => $prompt]);
$emit(4, 'run.output.delta', ['kind' => 'run_output_delta', 'command_id' => $commandId, 'text' => $text]);
$emit(5, 'run.terminal.completed', [
    'kind' => 'run_terminal',
    'command_id' => $commandId,
    'terminal' => 'completed',
    'text' => $text,
    'reason' => null,
]);
exit(0);
