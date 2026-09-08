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
if ($command !== '/model') {
    exit(2);
}
if (getenv('MUSE_TEST_MODE') !== 'timeout') {
    echo "\033[15;3HChoose\033[15;10Hmodel\033[17;3Hmuse-spark-1.3\033[18;1H⟩\033[18;3H\033[1mmuse-spark-1.3-contributor\033[18;31H\033[22mYour content may be used for product improvement.\033[22;3H↑↓ move · enter confirm · esc go back";
}
sleep(30);
