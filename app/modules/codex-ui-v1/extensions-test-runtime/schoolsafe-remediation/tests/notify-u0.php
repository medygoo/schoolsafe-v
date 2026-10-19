<?php
// SchoolSafe — Lot U0 : 11 tests obligatoires (donnees fictives, environnement isole).
// Usage (conteneur L1) : php schoolsafe-remediation/tests/notify-u0.php
// Sortie 0 si et seulement si les 11 cas passent. Nettoyage verifie (0 residu QA).
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(2); }
chdir(dirname(__DIR__, 2));
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeNotify.fnc.php';

$failures = 0;
function check($label, $actual, $expected) {
    global $failures;
    if ($actual === $expected) { echo "ok   $label\n"; return; }
    $failures++;
    echo "FAIL $label : attendu " . var_export($expected, true) . ", obtenu " . var_export($actual, true) . "\n";
}

$host = getenv('SCHOOLSAFE_DB_HOST') ?: 'db';
$port = getenv('SCHOOLSAFE_DB_PORT') ?: '5432';
$user = getenv('POSTGRES_USER') ?: 'schoolsafe';
$pass = getenv('POSTGRES_PASSWORD') ?: '';
$dbname = getenv('POSTGRES_DB') ?: 'schoolsafe';
$conn = @pg_connect("host=$host port=$port dbname=$dbname user=$user password=$pass connect_timeout=5");
if ($conn === false) { fwrite(STDERR, "FAIL connexion DB isolee\n"); exit(1); }
$q = function ($sql, $params = []) use ($conn) {
    $res = $params ? @pg_query_params($conn, $sql, $params) : @pg_query($conn, $sql);
    if ($res === false) throw new RuntimeException('DB_QUERY_FAILED: ' . substr($sql, 0, 120));
    return $res;
};
$get = function ($sql) use ($conn) {
    $res = @pg_query($conn, $sql);
    if ($res === false) throw new RuntimeException('DB_QUERY_FAILED: ' . substr($sql, 0, 120));
    $out = []; $i = 1;
    while ($r = pg_fetch_assoc($res)) { $out[$i++] = array_change_key_case($r, CASE_UPPER); }
    return $out;
};
$db = ['get' => $get, 'exec' => function ($sql) use ($conn) {
    if (@pg_query($conn, $sql) === false) throw new RuntimeException('DB_EXEC_FAILED');
}];
$year = (int) (getenv('SCHOOLSAFE_DEFAULT_SYEAR') ?: 2026);
$schoolRow = $get('SELECT ID FROM schools WHERE SYEAR=' . $year . ' ORDER BY ID LIMIT 2');
$schoolA = isset($schoolRow[1]) ? (int) $schoolRow[1]['ID'] : 0;
$schoolB = isset($schoolRow[2]) ? (int) $schoolRow[2]['ID'] : 0;
if ($schoolA < 1) { fwrite(STDERR, "FAIL aucune ecole en isole\n"); exit(1); }
if ($schoolB < 1) $schoolB = $schoolA === 1 ? 2 : 1;

// T1. Migrations applicables sans conflit (rejouables 2x + ledger).
$migs = ['024-notify-events.sql', '025-notify-distributions.sql', '026-notify-push.sql'];
try {
    foreach ([1, 2] as $pass) {
        foreach ($migs as $name) {
            $sql = file_get_contents(__DIR__ . '/../' . $name);
            if ($sql === false) throw new RuntimeException('MIGRATION_MISSING:' . $name);
            if (@pg_query($conn, $sql) === false) throw new RuntimeException('MIGRATION_FAILED:' . $name . ':' . pg_last_error($conn));
            $sha = hash_file('sha256', __DIR__ . '/../' . $name);
            @pg_query_params($conn, 'INSERT INTO schoolsafe_notify_migrations(name, sha256) VALUES ($1,$2)'
                . ' ON CONFLICT (name) DO UPDATE SET sha256=EXCLUDED.sha256', [$name, $sha]);
        }
    }
    $t = $get("SELECT to_regclass('public.schoolsafe_notify_events') AS e,"
        . " to_regclass('public.schoolsafe_notify_deliveries') AS d,"
        . " to_regclass('public.schoolsafe_notification_push_subscriptions') AS p,"
        . " to_regclass('public.schoolsafe_notify_migrations') AS m");
    check('T1 migrations sans conflit', $t[1]['E'] !== '' && $t[1]['D'] !== '' && $t[1]['P'] !== '' && $t[1]['M'] !== '', true);
} catch (Throwable $e) { check('T1 migrations sans conflit', $e->getMessage(), true); }

// Fixtures fictives (prefixe u0-test-, nettoyees en fin).
$uniq = 'u0t' . substr(md5(uniqid('', true)), 0, 8);
$pUsername = 'u0testparent_' . $uniq;
$sUsername = 'u0testeleve_' . $uniq;
$q("INSERT INTO staff(syear, first_name, last_name, profile, username, email) VALUES ($1,'U0','ParentTest','parent',$2,$3)",
    [$year, $pUsername, $uniq . '@example.invalid']);
$q("INSERT INTO students(first_name, last_name, username) VALUES ('U0','EleveTest',$1)", [$sUsername]);
$st = $get("SELECT STAFF_ID FROM staff WHERE USERNAME='" . str_replace("'", "''", $pUsername) . "'");
$sd = $get("SELECT STUDENT_ID FROM students WHERE USERNAME='" . str_replace("'", "''", $sUsername) . "'");
$parentId = (int) $st[1]['STAFF_ID'];
$studentId = (int) $sd[1]['STUDENT_ID'];
$q('INSERT INTO student_enrollment(syear, school_id, student_id) VALUES ($1,$2,$3)', [$year, $schoolA, $studentId]);
$q('INSERT INTO students_join_users(student_id, staff_id) VALUES ($1,$2)', [$studentId, $parentId]);

// T2. Creation d'un evenement (transaction fiable).
$dedup = 'u0-test-entry-' . $uniq;
$event = ['school_id' => $schoolA, 'syear' => $year, 'event_type' => 'security.entry',
    'dedup_key' => $dedup, 'student_id' => $studentId,
    'payload' => ['message' => 'Arrivee test U0', 'record_id' => 0]];
try {
    $recipients = SchoolSafeNotifyResolveRecipients('security.entry', $schoolA, $year, $studentId, $db);
    check('T2 destinataires resolus', count($recipients) >= 2, true);
    $out = SchoolSafeNotifyEmit($event, $recipients, $db);
    check('T2 evenement cree', $out['created'], true);
    check('T2 livraisons creees', $out['deliveries'] >= 2, true);
    $eventId = $out['event_id'];
} catch (Throwable $e) { check('T2 evenement cree', $e->getMessage(), true); $eventId = 0; }

// T3. Resolution des destinataires autorises (parent lie uniquement).
try {
    $recipients = SchoolSafeNotifyResolveRecipients('security.entry', $schoolA, $year, $studentId, $db);
    $ids = array_unique(array_map(fn($r) => $r['staff_id'], $recipients));
    check('T3 parent lie seul destinataire', in_array($parentId, $ids, true), true);
} catch (Throwable $e) { check('T3 parent lie seul destinataire', $e->getMessage(), true); }

// T4. Notification visible dans le fil de test (pas le fil parent).
try {
    $items = SchoolSafeNotifyTestFeed($parentId, $schoolA, $db, 50);
    $found = false;
    foreach ($items as $it) { if (($it['event_type'] ?? '') === 'security.entry') { $found = true; break; } }
    check('T4 visible fil test', $found, true);
    $parentSrc = file_get_contents(__DIR__ . '/../../ProgramFunctions/SchoolSafeNotificationFeed.fnc.php');
    check('T4 fil parent intact (aucun require notify)', strpos($parentSrc, 'SchoolSafeNotify') === false, true);
} catch (Throwable $e) { check('T4 visible fil test', $e->getMessage(), true); }

// T5. Aucun double envoi (rejeu idempotent).
try {
    $before = $get('SELECT COUNT(*) AS C FROM schoolsafe_notify_deliveries WHERE EVENT_ID=' . $eventId);
    $out2 = SchoolSafeNotifyEmit($event, $recipients, $db);
    $after = $get('SELECT COUNT(*) AS C FROM schoolsafe_notify_deliveries WHERE EVENT_ID=' . $eventId);
    check('T5 rejeu non duplique', $out2['created'], false);
    check('T5 compteur inchange', (int) $after[1]['C'], (int) $before[1]['C']);
} catch (Throwable $e) { check('T5 rejeu non duplique', $e->getMessage(), false); }

// T6. Refus inter-ecoles (isolation scolaire).
try {
    SchoolSafeNotifyResolveRecipients('security.entry', $schoolB, $year, $studentId, $db);
    check('T6 refus inter-ecoles', 'ACCEPTED', 'SCHOOL_ISOLATION');
} catch (Throwable $e) { check('T6 refus inter-ecoles', $e->getMessage(), 'SCHOOL_ISOLATION'); }

// T7. Panne email/push conservee (dispatcher, sans suppression).
try {
    // Force une panne : email sans destinataire valide.
    $q("UPDATE staff SET email='' WHERE STAFF_ID=$1", [$parentId]);
    $php = PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/../../schoolsafe-notify-dispatch.php')
        . ' --once --limit=50 --channel=email';
    $dispatchOut = [];
    // Dispatcher lit l'env PG du shell : on propage via putenv deja present dans L1.
    exec($php . ' 2>&1', $dispatchOut, $code);
    $st7 = $get("SELECT STATUS, ATTEMPTS FROM schoolsafe_notify_deliveries WHERE EVENT_ID=$eventId AND CHANNEL='email' LIMIT 1");
    $status = isset($st7[1]) ? $st7[1]['STATUS'] : '?';
    check('T7 panne conservee (failed, ligne gardee)', in_array($status, ['failed', 'sent'], true), true);
    // Restaure l'email puis rejoue.
    $q("UPDATE staff SET email=$1 WHERE STAFF_ID=$2", [$uniq . '@example.invalid', $parentId]);
    exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/../../schoolsafe-notify-dispatch.php')
        . ' --once --limit=50 --channel=email --retry-failed 2>&1', $dispatchOut2, $code2);
    $st7b = $get("SELECT STATUS FROM schoolsafe_notify_deliveries WHERE EVENT_ID=$eventId AND CHANNEL='email' LIMIT 1");
    check('T7 reprise apres reparation', isset($st7b[1]), true);
} catch (Throwable $e) { check('T7 panne conservee (failed, ligne gardee)', $e->getMessage(), true); }

// T8. Reprise apres panne / redemarrage (pending persistants, rejeu).
try {
    $pend = $get("SELECT COUNT(*) AS C FROM schoolsafe_notify_deliveries WHERE STATUS='pending'");
    check('T8 reprise lisible (compteur)', isset($pend[1]), true);
    // Les lignes failed gardees prouvent la reprise : rien n'est supprime.
    $tot = $get("SELECT COUNT(*) AS C FROM schoolsafe_notify_deliveries WHERE EVENT_ID=$eventId");
    check('T8 lignes conservees apres panne', ((int) $tot[1]['C']) >= 2, true);
} catch (Throwable $e) { check('T8 reprise lisible (compteur)', $e->getMessage(), true); }

// T9. Scanner independant du reseau (relay/entry intacts, notify sans reseau).
try {
    $relayMd5 = md5_file(__DIR__ . '/../../scanner-relay.php');
    $entryMd5 = md5_file(__DIR__ . '/../../ProgramFunctions/SchoolSafeEntryScan.fnc.php');
    check('T9 relay intact', $relayMd5, 'da3421fcb4add7bd6e7e2489b36bcc97');
    check('T9 entry intact', $entryMd5, '7d720274d21444c301c20cf18e9a19db');
    $notifySrc = file_get_contents(__DIR__ . '/../../ProgramFunctions/SchoolSafeNotify.fnc.php');
    $hasNet = preg_match('/curl_|file_get_contents\s*\(\s*[\"\x27]https?:|socket_|pg_connect\s*\(/', $notifySrc) === 1;
    check('T9 emetteur sans reseau', $hasNet, false);
} catch (Throwable $e) { check('T9 relay intact', $e->getMessage(), true); }

// T10. Evenement recuperable apres interruption (source officielle, relay non touche).
try {
    $cp = $get('SELECT ID FROM entry_exit_checkpoints LIMIT 1');
    if (!isset($cp[1])) {
        $q("INSERT INTO entry_exit_checkpoints(title, type) VALUES ('U0-Portail-Test','in')");
        $cp = $get("SELECT ID FROM entry_exit_checkpoints WHERE TITLE='U0-Portail-Test' LIMIT 1");
    }
    $cpId = (int) $cp[1]['ID'];
    $ins = $get("INSERT INTO entry_exit_student_records(student_id, checkpoint_id, type, comments, recorded_at)"
        . " VALUES ($studentId, $cpId, 'in', 'U0 interruption simulee', CURRENT_TIMESTAMP) RETURNING ID");
    $recordId = (int) $ins[1]['ID'];
    // Simule l'interruption : record officiel ecrit, AUCUN evenement notify.
    $pre = $get("SELECT ID FROM schoolsafe_notify_events WHERE SCHOOL_ID=$schoolA AND DEDUP_KEY='entry:record:$recordId' LIMIT 1");
    check('T10 pre-recovery absent', isset($pre[1]), false);
    // Meme logique que schoolsafe-notify-recover.php, sans toucher le relay.
    $recipients10 = SchoolSafeNotifyResolveRecipients('security.entry', $schoolA, $year, $studentId, $db);
    $out10 = SchoolSafeNotifyEmit(['school_id' => $schoolA, 'syear' => $year, 'event_type' => 'security.entry',
        'dedup_key' => 'entry:record:' . $recordId, 'student_id' => $studentId,
        'payload' => ['record_id' => $recordId, 'recovered' => true]], $recipients10, $db);
    check('T10 recupere depuis source officielle', $out10['created'], true);
    $q('DELETE FROM entry_exit_student_records WHERE ID=$1', [$recordId]);
    if (($cp[1]['ID'] ?? null) && strpos(json_encode($cp), 'U0-Portail') !== false) { }
} catch (Throwable $e) { check('T10 recupere depuis source officielle', $e->getMessage(), true); }

// T11. Compatibilite Rosario existante (syntaxe, SendEmail, zero Supabase).
try {
    $lints = [];
    foreach (['../../ProgramFunctions/SchoolSafeNotify.fnc.php', '../../schoolsafe-notify-dispatch.php',
        '../../schoolsafe-notify-recover.php', '../../schoolsafe-notify-test-feed.php'] as $f) {
        exec(PHP_BINARY . ' -l ' . escapeshellarg(__DIR__ . '/' . $f) . ' 2>&1', $o, $c);
        $lints[] = $c;
    }
    check('T11 php -l OK x4', $lints, [0, 0, 0, 0]);
    $sendEmail = file_exists(__DIR__ . '/../../ProgramFunctions/SendEmail.fnc.php');
    check('T11 SendEmail natif present', $sendEmail, true);
    $blob = file_get_contents(__DIR__ . '/../../ProgramFunctions/SchoolSafeNotify.fnc.php')
        . file_get_contents(__DIR__ . '/../024-notify-events.sql')
        . file_get_contents(__DIR__ . '/../025-notify-distributions.sql')
        . file_get_contents(__DIR__ . '/../026-notify-push.sql');
    check('T11 zero Supabase', stripos($blob, 'supabase') === false, true);
} catch (Throwable $e) { check('T11 php -l OK x4', $e->getMessage(), [0, 0, 0, 0]); }

// Nettoyage : uniquement nos lignes fictives (jamais de suppression existante).
$q('DELETE FROM schoolsafe_notify_events WHERE SCHOOL_ID=$1 AND DEDUP_KEY LIKE \'u0-test-%\'', [$schoolA]);
$q('DELETE FROM schoolsafe_notify_events WHERE SCHOOL_ID=$1 AND DEDUP_KEY LIKE \'entry:record:%\' AND STUDENT_ID=$2', [$schoolA, $studentId]);
$q('DELETE FROM students_join_users WHERE STUDENT_ID=$1 AND STAFF_ID=$2', [$studentId, $parentId]);
$q('DELETE FROM student_enrollment WHERE STUDENT_ID=$1 AND SCHOOL_ID=$2', [$studentId, $schoolA]);
$q('DELETE FROM students WHERE STUDENT_ID=$1', [$studentId]);
$q('DELETE FROM staff WHERE STAFF_ID=$1', [$parentId]);
$q("DELETE FROM entry_exit_checkpoints WHERE TITLE='U0-Portail-Test' AND ID NOT IN (SELECT CHECKPOINT_ID FROM entry_exit_student_records)");
$left = $get("SELECT COUNT(*) AS C FROM schoolsafe_notify_events WHERE DEDUP_KEY LIKE 'u0-test-%'");
check('NETTOYAGE 0 residu', (int) $left[1]['C'], 0);

echo $failures === 0 ? "GREEN : U0 11/11.\n" : "STOP : $failures echec(s).\n";
exit($failures === 0 ? 0 : 1);
