#!/usr/bin/env php
<?php

declare(strict_types=1);

file_put_contents((string) getenv('MUSE_TEST_PID'), (string) getmypid());
file_put_contents((string) getenv('MUSE_TEST_CWD'), (string) getcwd());
if (getenv('MUSE_TEST_MODE') === 'exit') {
    exit(1);
}
echo "\033[?25h";
$command = trim((string) fgets(STDIN));
if ($command !== '/upgrade') {
    exit(2);
}
if (getenv('MUSE_TEST_MODE') === 'retry') {
    echo "Session usage Input 0 Total 0\n";
    if (trim((string) fgets(STDIN)) !== '/upgrade') {
        exit(3);
    }
}
if (getenv('MUSE_TEST_MODE') === 'limit') {
    echo "Usage limit reached · /upgrade\n  (https://accountscenter.meta.com/muse_code/?ep=xgrade) for increased limits,\n  or wait for usage to reset at Sep 14 at 2:00 AM\n";
} elseif (getenv('MUSE_TEST_MODE') !== 'timeout') {
    echo "You are currently subscribed to the Muse Code Everyday Usage usage plan.\nCurrent 31% used · Resets at 11:38 PM\nWeekly 46% used · Resets Sep 14 at 2:00 AM\nas of 9:50 PM\n";
}
sleep(30);
