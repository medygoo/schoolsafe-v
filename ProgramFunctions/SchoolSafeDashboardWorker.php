<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
while (true) {
    $process = proc_open([PHP_BINARY, __DIR__ . '/SchoolSafeDashboardDaily.php'], [0=>['file','/dev/null','r'],1=>STDOUT,2=>STDERR], $pipes);
    if (!is_resource($process)) throw new RuntimeException('DAILY_JOB_START_FAILED');
    $deadline = time()+120;
    do {
        $state = proc_get_status($process);
        if (!$state['running']) break;
        if (time()>$deadline) { proc_terminate($process); break; }
        sleep(1);
    } while (true);
    proc_close($process);
    sleep(3600);
}
