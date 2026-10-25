<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
while (true) {
    $exitCode = 1;
    passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/arrival-transfer-cli.php'), $exitCode);
    if ($exitCode !== 0) fwrite(STDERR, "ARRIVAL_WORKER_RETRY_IN_15_SECONDS\n");
    sleep(15);
}
