<?php
// Essai a blanc sur la base reelle (04/11) : migration 063, une note d'essai, relais, puis ROLLBACK.
// Rien n'est garde ni envoye : le distributeur ne voit jamais une transaction non validee.
// Usage (image candidate, reseau du conteneur applicatif) : php dryrun-notes.php /var/www/html
$root = $argv[1] ?? '/var/www/html';
chdir($root);
require_once $root . '/schoolsafe-notify-cli.php';
$conn = pg_connect('host=' . (getenv('SCHOOLSAFE_DB_HOST') ?: 'db') . ' port=' . (getenv('SCHOOLSAFE_DB_PORT') ?: '5432') . ' dbname=' . getenv('POSTGRES_DB') . ' user=' . getenv('POSTGRES_USER') . ' password=' . getenv('POSTGRES_PASSWORD') . ' connect_timeout=5');
if (!$conn) { echo "DB indisponible\n"; exit(1); }
SchoolSafeNotifyCliInit($conn);
require_once $root . '/ProgramFunctions/SchoolSafeGradeNotice.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }
DBQuery('BEGIN');
try {
    $sql = file_get_contents($root . '/schoolsafe-remediation/063-grade-notices.sql');
    $sql = preg_replace('/^BEGIN;\s*$/m', '', preg_replace('/^COMMIT;\s*$/m', '', $sql));
    if (!DBGet("SELECT to_regclass('public.schoolsafe_grade_notices') AS T")[1]['T']) pg_query($conn, $sql) or throw new RuntimeException('migration');
    $a = (int) DBGet("INSERT INTO gradebook_assignments(ASSIGNMENT_TYPE_ID,STAFF_ID,MARKING_PERIOD_ID,COURSE_PERIOD_ID,TITLE,ASSIGNED_DATE,DUE_DATE,POINTS) VALUES(10,12,19,4,'Essai fractions','2026-11-02','2026-11-03',10) RETURNING ASSIGNMENT_ID")[1]['ASSIGNMENT_ID'];
    DBQuery("INSERT INTO gradebook_grades(STUDENT_ID,COURSE_PERIOD_ID,ASSIGNMENT_ID,POINTS,COMMENT,CREATED_AT) VALUES(4,4," . $a . ",4,'Revois les fractions',now()-interval '10 minutes')");
    // Les BEGIN/COMMIT de l'emetteur deviennent des points de sauvegarde dans la transaction d'essai.
    $db = ['get' => function ($q) { return DBGet($q); }, 'exec' => function ($q) {
        $map = ['BEGIN' => 'SAVEPOINT emit', 'COMMIT' => 'RELEASE SAVEPOINT emit', 'ROLLBACK' => 'ROLLBACK TO SAVEPOINT emit'];
        DBQuery($map[trim($q)] ?? $q);
    }];
    $sent = SchoolSafeGradeNoticeRelay($db, 'https://app.shoolsafe.tech');
    $events = DBGet("SELECT EVENT_TYPE,PAYLOAD::text AS P FROM schoolsafe_notify_events WHERE DEDUP_KEY='grade:" . $a . ":4'");
    $parents = (int) DBGet("SELECT count(*) AS N FROM students_join_users WHERE STUDENT_ID=4")[1]['N'];
    check('annonce creee (si parent lie)', $sent, $parents > 0 ? 1 : 0);
    check('note marquee comme annoncee', (int) DBGet("SELECT count(*) AS N FROM schoolsafe_grade_notices WHERE ASSIGNMENT_ID=" . $a . " AND STUDENT_ID=4")[1]['N'], 1);
    check('deuxieme passage : rien de plus', SchoolSafeGradeNoticeRelay($db, 'https://app.shoolsafe.tech'), 0);
    if ($events) { $p = json_decode($events[1]['P'], true); echo "---- message ----\n" . ($p['title'] ?? '') . "\n" . ($p['message'] ?? '') . "\n-----------------\n"; }
    echo 'destinataires (livraisons) : ' . DBGet("SELECT count(*) AS N FROM schoolsafe_notify_deliveries d JOIN schoolsafe_notify_events e ON e.ID=d.EVENT_ID WHERE e.DEDUP_KEY='grade:" . $a . ":4'")[1]['N'] . "\n";
    check('journal 062 a trace la note', (int) DBGet("SELECT count(*) AS N FROM schoolsafe_grade_journal WHERE ASSIGNMENT_ID=" . $a)[1]['N'], 1);
    check('moyenne de classe cachee (1 note)', SchoolSafeGradeNoticeClassAverage($a, function ($q) { return DBGet($q); }), null);
    check('progression lisible', count(SchoolSafeGradeNoticeTrend(4, 4, function ($q) { return DBGet($q); })) >= 1, true);
} catch (Throwable $e) {
    $ko++; echo 'KO   exception : ' . $e->getMessage() . "\n";
} finally {
    DBQuery('ROLLBACK');
}
check('rien n est reste en base', (int) DBGet("SELECT count(*) AS N FROM gradebook_assignments WHERE TITLE='Essai fractions'")[1]['N'], 0);
echo "\n$ok OK, $ko KO\n";
exit($ko ? 1 : 0);
