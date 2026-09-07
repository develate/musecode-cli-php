<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Quota;

use Develate\MusecodeCli\Exception\MuseException;
use Develate\MusecodeCli\Exception\ProcessTimedOut;
use Develate\MusecodeCli\Process\MuseExecutable;
use Symfony\Component\Process\Process;

class PtyQuotaReader
{
    /**
     * Expect supplies a controlling terminal with a known size. Bare proc_open
     * PTYs can report zero rows/columns, leaving Muse's display blank.
     */
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
spawn -noecho $binary --trust-workspace --no-session-log --disable-shell --disable-write
stty rows 40 columns 120 < $spawn_out(slave,name)
expect {
    -ex "\033\[c" {send -- "\033\[?1;2c"; exp_continue -continue_timer}
    -ex "\033\[6n" {send -- "\033\[1;1R"; exp_continue -continue_timer}
    -ex "\033\[?25h" {after 500}
    timeout {cleanup; exit 124}
    eof {cleanup; exit 1}
}
while {[clock seconds] < $deadline} {
    send -- "/usage"
    after 300
    send -- "\r"
    set timeout [expr {min(5, max(1, $deadline - [clock seconds]))}]
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
    public function read(string $binary, array $env = [], float $timeout = 30): Quota
    {
        MuseExecutable::assertAvailable($binary);
        if ($timeout <= 0 || ! is_finite($timeout)) {
            throw new \InvalidArgumentException('Quota timeout must be finite and positive.');
        }
        if (! is_executable('/usr/bin/expect')) {
            throw new MuseException('Muse usage requires /usr/bin/expect with PTY support.');
        }
        $cwd = sys_get_temp_dir().'/muse-usage-'.bin2hex(random_bytes(12));
        if (! mkdir($cwd, 0700)) {
            throw new MuseException('Could not create the Muse usage scratch directory.');
        }
        file_put_contents($cwd.'/usage.expect', self::SCRIPT);
        $process = new Process(
            ['/usr/bin/expect', '-f', $cwd.'/usage.expect', (string) max(1, (int) ceil($timeout)), $binary],
            $cwd, array_merge($env, ['TERM' => 'xterm-256color']), null, $timeout + 3,
        );

        try {
            $process->run();
            if ($process->getExitCode() === 124) {
                throw new ProcessTimedOut('Muse Code did not return a readable subscription usage panel before the timeout.');
            }
            $quota = (new UsagePanelParser)->parse($process->getOutput());
            if (! $process->isSuccessful() || $quota === null) {
                throw new MuseException('Muse Code exited without a readable subscription usage panel. Check that this account is signed in.');
            }

            return $quota;
        } finally {
            $process->stop(1);
            @unlink($cwd.'/usage.expect');
            @rmdir($cwd);
        }
    }
}
