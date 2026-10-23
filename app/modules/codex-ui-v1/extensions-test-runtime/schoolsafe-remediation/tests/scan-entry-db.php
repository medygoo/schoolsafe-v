<?php
// SchoolSafe — Lot ENTRY : intégration base réelle (L1 uniquement, jamais en prod).
// Sème un checkpoint + une carte TEST-A1-ENTRY-* pour l'élève 1 / école 1 / 2026,
// joue le parcours via SchoolSafeEntryScan (vrai SQL), puis nettoie tout.
// Usage : SCHOOLSAFE_CARD_HMAC_SECRET (test) + POSTGRES_* dans l'environnement.
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(2); }
chdir(dirname(__DIR__, 2));
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeEntryScan.fnc.php';

$failures = 0;
function check($label, $actual, $expected) {
    global $failures;
    if ($actual === $expected) { echo "ok   $label\n"; return; }
    $failures++;
    echo "FAIL $label : attendu " . var_export($expected, true)
        . ", obtenu " . var_export($actual, true) . "\n";
}

$secretHex = getenv('SCHOOLSAFE_CARD_HMAC_SECRET') ?: '';
if (!preg_match('/^[a-f0-9]{64}$/D', $secretHex)) { fwrite(STDERR, "Secret de test manquant.\n"); exit(2); }
if (getenv('SCHOOLSAFE_SCAN_FIXTURE') !== 'schoolcef-isolated-scan-20261023'
    || getenv('POSTGRES_DB') !== 'schoolcef_fixture') {
    fwrite(STDERR, "Base fictive dédiée et marqueur de qualification obligatoires.\n"); exit(2);
}
$dsn = 'pgsql:host=' . (getenv('SCHOOLSAFE_DB_HOST') ?: 'db') . ';dbname=' . (getenv('POSTGRES_DB') ?: 'schoolsafe');
try {
    $pdo = new PDO($dsn, getenv('POSTGRES_USER') ?: 'schoolsafe', getenv('POSTGRES_PASSWORD') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) { fwrite(STDERR, "Connexion base impossible.\n"); exit(2); }

$db = [
    'get' => function ($sql) use ($pdo) {
        $out = [];
        foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $i => $row) {
            $upper = [];
            foreach ($row as $k => $v) $upper[strtoupper($k)] = $v;
            $out[$i + 1] = $upper;
        }
        return $out;
    },
    'exec' => function ($sql) use ($pdo) { $pdo->exec($sql); },
];
if (!function_exists('DBGet')) {
    function DBGet($sql) { $f = $GLOBALS['a1db']; return $f($sql); }
}
$GLOBALS['a1db'] = $db['get'];
$sign = function ($n) use ($secretHex) {
    return substr(strtr(base64_encode(hash_hmac('sha256', $n, hex2bin($secretHex), true)), '+/', '-_'), 0, 32);
};
$q = function ($v) { return "'" . str_replace("'", "''", $v) . "'"; };
$ctx = ['user' => 9, 'school' => 1, 'year' => 2026];
$card = 'TEST-A1-ENTRY-DB';
$cpId = 0;
$today = date('Y-m-d');
$attendanceBefore = $pdo->query("SELECT * FROM attendance_day WHERE STUDENT_ID=1 AND SCHOOL_DATE='$today'")->fetchAll(PDO::FETCH_ASSOC);

try {
    $pdo->exec("INSERT INTO entry_exit_checkpoints(TITLE, TYPE, SCHOOLSAFE_SCAN_SCHOOL_ID, SCHOOLSAFE_SCAN_SYEAR, SCHOOLSAFE_SCAN_ENABLED) VALUES('Portail QA', NULL,1,2026,TRUE) RETURNING ID");
    $cpId = (int) $pdo->query("SELECT ID FROM entry_exit_checkpoints WHERE TITLE='Portail QA'")->fetchColumn();
    $pdo->exec("INSERT INTO schoolsafe_student_cards(school_id, student_id, card_number, signature, status)"
        . " VALUES(1, 1, " . $q($card) . ", " . $q($sign($card)) . ", 'active')");
    $qr = 'schoolsafe://card/' . $card . '/' . $sign($card);

    $r = SchoolSafeEntryScan($qr, (string) $cpId, $secretHex, $ctx, $db);
    check('base => PRESENT', $r['result'], 'PRESENT');
    check('base => checkpoint', $r['checkpoint']['label'], 'Portail QA');
    $rec = $pdo->query("SELECT COUNT(*), MIN(comments) FROM entry_exit_student_records"
        . " WHERE STUDENT_ID=1 AND TYPE='in' AND UNAUTHORIZED IS NULL AND RECORDED_AT::date='$today'")->fetch(PDO::FETCH_NUM);
    check('base => 1 record', (int) $rec[0], 1);
    check('base => commentaire staff', strpos($rec[1], 'staff #9') !== false, true);
    $att = $pdo->query("SELECT * FROM attendance_day WHERE STUDENT_ID=1 AND SCHOOL_DATE='$today'")->fetchAll(PDO::FETCH_ASSOC);
    check('base => présence pédagogique inchangée', $att, $attendanceBefore);

    $r = SchoolSafeEntryScan($qr, (string) $cpId, $secretHex, $ctx, $db);
    check('base => double ALREADY_PRESENT', $r['result'], 'ALREADY_PRESENT');
    $n = $pdo->query("SELECT COUNT(*) FROM entry_exit_student_records WHERE STUDENT_ID=1 AND TYPE='in'"
        . " AND UNAUTHORIZED IS NULL AND RECORDED_AT::date='$today'")->fetchColumn();
    check('base => toujours 1 record', (int) $n, 1);

    // Le tableau enseignant voit l'arrivée (registre natif + inscription).
    require_once __DIR__ . '/../../schoolsafe-attendance/ArrivalRead.php';
    $tctx = ['profile' => 'admin', 'staff' => 9, 'school' => 1, 'year' => 2026];
    $tq = function ($sql) use ($pdo) {
        $out = [];
        foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $i => $row) $out[$i + 1] = $row;
        return $out;
    };
    $read = SchoolSafeArrivalRead($tctx, ['date' => $today], $tq);
    check('tableau => arrivée visible', (int) ($read['summary']['students'] ?? 0) >= 1, true);

    // Le fil parent voit l'arrivée de son enfant (lien qa.parent→élève 1 préexistant, jamais touché).
    require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeNotificationFeed.fnc.php';
    $parentId = (int) $pdo->query("SELECT STAFF_ID FROM staff WHERE USERNAME='qa.parent'")->fetchColumn();
    $degraded = [];
    $feed = SchoolSafeNotificationFeed([], [], ['1'], $parentId, 1, 2026, $degraded);
    check('fil => source arrivals sans panne', in_array('arrivals', $degraded, true), false);
    $found = false;
    foreach ($feed as $item) { if (($item['id'] ?? '') !== '' && strpos($item['id'], 'arrival:') === 0) { $found = true; break; } }
    check('fil => item arrivée présent', $found, true);
} finally {
    $pdo->exec("DELETE FROM entry_exit_student_records WHERE CHECKPOINT_ID=$cpId");
    $pdo->exec("DELETE FROM schoolsafe_student_cards WHERE CARD_NUMBER=" . $q($card));
    $pdo->exec("DELETE FROM entry_exit_checkpoints WHERE TITLE='Portail QA'");
    $left = $pdo->query("SELECT (SELECT COUNT(*) FROM entry_exit_student_records WHERE CHECKPOINT_ID=$cpId)"
        . " + (SELECT COUNT(*) FROM schoolsafe_student_cards WHERE CARD_NUMBER LIKE 'TEST-A1-ENTRY-%')"
        . " + (SELECT COUNT(*) FROM entry_exit_checkpoints WHERE TITLE='Portail QA')")->fetchColumn();
    check('nettoyage test', (int) $left, 0);
}

echo $failures === 0 ? "GREEN : intégration base OK.\n" : "STOP : $failures échec(s).\n";
exit($failures === 0 ? 0 : 1);
