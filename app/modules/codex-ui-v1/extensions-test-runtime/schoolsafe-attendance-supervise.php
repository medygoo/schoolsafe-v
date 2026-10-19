<?php
// SchoolSafe — Lot U1 : superviseur du planificateur (CLI uniquement).
// Convention reprise de schoolsafe-attendance/arrival-worker-supervise.php
// (boucle + relance 60 s), sans modifier le superviseur existant.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
while (true) {
    $exitCode = 1;
    passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/schoolsafe-attendance-scheduler.php') . ' --once', $exitCode);
    if ($exitCode !== 0) fwrite(STDERR, "ATTENDANCE_SCHEDULER_RETRY_IN_60_SECONDS\n");
    sleep(60);
}
