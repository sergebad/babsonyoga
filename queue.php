<?php

function runCommand ()
{
    $command = 'php ' . __DIR__ . '/artisan queue:listen > /dev/null & echo $!';
    $number = exec($command);
    file_put_contents(__DIR__ . '/queue.pid', $number);
}

if (file_exists(__DIR__ . '/queue.pid')) {
    $pid = file_get_contents(__DIR__ . '/queue.pid');
    $result = exec('ps -e | grep ' . $pid);
    if ($result == '') {
        runCommand();
    }
} else {
    runCommand();
}

?>
