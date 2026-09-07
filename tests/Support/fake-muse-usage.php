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
if ($command !== '/usage') {
    exit(2);
}
if (getenv('MUSE_TEST_MODE') === 'retry') {
    echo "Session usage Input 0 Total 0\n";
    if (trim((string) fgets(STDIN)) !== '/usage') {
        exit(3);
    }
}
if (getenv('MUSE_TEST_MODE') !== 'timeout') {
    echo "Subscription · Muse Code Everyday Usage\nCurrent 31% used · Resets at 11:38 PM\nWeekly 46% used · Resets Sep 14 at 2:00 AM\nas of 9:50 PM\n";
}
sleep(30);
