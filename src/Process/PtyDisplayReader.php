<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Process;

use Develate\MusecodeCli\Exception\MuseException;
use Develate\MusecodeCli\Exception\ProcessTimedOut;
use Symfony\Component\Process\Process;

/** Reads informational TUI commands in a disposable workspace without confirming a selection. */
final class PtyDisplayReader
{
    private const SCRIPT = <<<'TCL'
proc cleanup {} {
    global spawn_id
    catch {exec kill -TERM [exp_pid]}
    after 100
    catch {exec kill -KILL [exp_pid]}
    catch {close}
    catch {wait}
}
trap {cleanup; exit 143} {SIGTERM SIGINT SIGHUP}
set timeout [lindex $argv 0]
set deadline [expr {[clock seconds] + $timeout}]
set binary [lindex $argv 1]
set command [lindex $argv 2]
spawn -noecho $binary --trust-workspace --no-session-log --disable-shell --disable-write
stty rows 200 columns 240 < $spawn_out(slave,name)
expect {
    -ex "\033\[c" {send -- "\033\[?1;2c"; exp_continue -continue_timer}
    -ex "\033\[6n" {send -- "\033\[1;1R"; exp_continue -continue_timer}
    -ex "\033\[?25h" {after 500}
    timeout {cleanup; exit 124}
    eof {cleanup; exit 1}
}
while {[clock seconds] < $deadline} {
    send -- $command
    after 300
    send -- "\r"
    set timeout [expr {min(5, max(1, $deadline - [clock seconds]))}]
    if {$command eq "/model"} {
        set timeout [expr {max(1, $deadline - [clock seconds])}]
        expect {
            -re {go(?:\x1b\[[0-?]*[ -/]*[@-~]|\s)+back} {cleanup; exit 0}
            timeout {cleanup; exit 124}
            eof {cleanup; exit 1}
        }
    }
    expect {
        -re {as(?:\x1b\[[0-?]*[ -/]*[@-~]|\s)+of} {cleanup; exit 0}
        -ex "\033\[6n" {send -- "\033\[1;1R"; exp_continue -continue_timer}
        timeout {}
        eof {cleanup; exit 1}
    }
}
cleanup
exit 124
TCL;

    /** @param array<string, string|false> $env */
    public function read(string $binary, string $command, array $env = [], float $timeout = 30): string
    {
        if (! in_array($command, ['/upgrade', '/model'], true)) {
            throw new \InvalidArgumentException('Unsupported Muse display command.');
        }
        MuseExecutable::assertAvailable($binary);
        if ($timeout <= 0 || ! is_finite($timeout)) {
            throw new \InvalidArgumentException('Display timeout must be finite and positive.');
        }
        if (! is_executable('/usr/bin/expect')) {
            throw new MuseException('Muse display requires /usr/bin/expect with PTY support.');
        }
        $cwd = sys_get_temp_dir().'/muse-display-'.bin2hex(random_bytes(12));
        if (! mkdir($cwd, 0700)) {
            throw new MuseException('Could not create the Muse display scratch directory.');
        }
        file_put_contents($cwd.'/display.expect', self::SCRIPT);
        $process = new Process(
            ['/usr/bin/expect', '-f', $cwd.'/display.expect', (string) max(1, (int) ceil($timeout)), $binary, $command],
            $cwd, array_merge($env, ['TERM' => 'xterm-256color']), null, $timeout + 3,
        );

        try {
            $process->run();
            if ($process->getExitCode() === 124) {
                throw new ProcessTimedOut('Muse Code did not return a readable interactive display before the timeout.');
            }
            if (! $process->isSuccessful()) {
                throw new MuseException('Muse Code exited without a readable interactive display. Check that this account is signed in.');
            }

            return $process->getOutput();
        } finally {
            $process->stop(1);
            @unlink($cwd.'/display.expect');
            @rmdir($cwd);
        }
    }
}
