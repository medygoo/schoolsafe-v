<?php
// SchoolSafe — Lot PICKUP : intégration base réelle (L1 uniquement, jamais en prod).
// Sème checkpoint + carte + principal + externe pour l'élève 1 / école 1,
// joue le parcours via SchoolSafePickupScan (vrai SQL), puis nettoie tout.
// Usage : SCHOOLSAFE_CARD_HMAC_SECRET + POSTGRES_* dans l'environnement.
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(2); }
chdir(dirname(__DIR__, 2));
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafePickupScan.fnc.php';

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
$card = 'TEST-PICKUP-DB';
$cpId = 0;
$gidPrincipal = 0;
$gidExternal = 0;
$today = date('Y-m-d');

try {
    $pdo->exec("INSERT INTO entry_exit_checkpoints(TITLE, TYPE, SCHOOLSAFE_SCAN_SCHOOL_ID, SCHOOLSAFE_SCAN_SYEAR, SCHOOLSAFE_SCAN_ENABLED) VALUES('Portail QA Pickup', NULL,1,2026,TRUE) RETURNING ID");
    $cpId = (int) $pdo->query("SELECT ID FROM entry_exit_checkpoints WHERE TITLE='Portail QA Pickup'")->fetchColumn();
    $pdo->exec("INSERT INTO schoolsafe_student_cards(school_id, student_id, card_number, signature, status)"
        . " VALUES(1, 1, " . $q($card) . ", " . $q($sign($card)) . ", 'active')");
    $pdo->exec("INSERT INTO schoolsafe_pickup_guardians(school_id, student_id, guardian_type, full_name,"
        . " is_primary, is_active, is_authorized_pickup, photo_path, photo_accepted)"
        . " VALUES(1, 1, 'mere', 'Maman QA', TRUE, TRUE, TRUE, '/assets/pickup/qa.jpg', TRUE) RETURNING ID");
    $gidPrincipal = (int) $pdo->query("SELECT ID FROM schoolsafe_pickup_guardians"
        . " WHERE STUDENT_ID=1 AND FULL_NAME='Maman QA'")->fetchColumn();
    $pdo->exec("INSERT INTO schoolsafe_pickup_guardians(school_id, student_id, guardian_type, full_name,"
        . " is_primary, is_active, is_authorized_pickup, photo_path, photo_accepted)"
        . " VALUES(1, 1, 'externe', 'Tonton QA', FALSE, TRUE, FALSE, '/assets/pickup/qa2.jpg', TRUE) RETURNING ID");
    $gidExternal = (int) $pdo->query("SELECT ID FROM schoolsafe_pickup_guardians"
        . " WHERE STUDENT_ID=1 AND FULL_NAME='Tonton QA'")->fetchColumn();
    $pdo->exec("INSERT INTO schoolsafe_pickup_authorizations(guardian_id, student_id, school_id, status,"
        . " slot_no, photo_verified, requested_by, reason)"
        . " VALUES($gidExternal, 1, 1, 'active', 1, TRUE, 9, 'QA')");
    $aid = (int) $pdo->query("SELECT ID FROM schoolsafe_pickup_authorizations WHERE GUARDIAN_ID=$gidExternal")->fetchColumn();
    $qr = 'schoolsafe://card/' . $card . '/' . $sign($card);
    require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeEntryScan.fnc.php';
    $arrival = SchoolSafeEntryScan($qr, (string) $cpId, $secretHex, $ctx, $db);
    check('base => arrivée préalable vérifiée', $arrival['result'], 'PRESENT');

    $r = SchoolSafePickupScan($qr, (string) $cpId, $secretHex, $ctx, $db, []);
    check('base => PICKUP_CHOICE', $r['result'], 'PICKUP_CHOICE');
    check('base => 2 personnes', count($r['people']), 2);
    check('base => rôle principal', $r['people'][0]['role'], 'Mère (principale)');

    $r = SchoolSafePickupScan($qr, (string) $cpId, $secretHex, $ctx, $db, ['authorized_person_id' => 'a:' . $aid]);
    check('base => NEED_HUMAN', $r['result'], 'NEED_HUMAN_CONFIRMATION');
    check('base => préparation sans sortie',
        (int) $pdo->query("SELECT COUNT(*) FROM entry_exit_student_records WHERE STUDENT_ID=1 AND TYPE='out'"
            . " AND RECORDED_AT::date='$today'")->fetchColumn(), 0);

    $r = SchoolSafePickupScan($qr, (string) $cpId, $secretHex, $ctx, $db,
        ['proof_token' => $r['proof_token'], 'human_confirmed' => true]);
    check('base => CONFIRMED', $r['result'], 'CONFIRMED');
    $rec = $pdo->query("SELECT COUNT(*), MIN(comments) FROM entry_exit_student_records"
        . " WHERE STUDENT_ID=1 AND TYPE='out' AND UNAUTHORIZED IS NULL AND RECORDED_AT::date='$today'")->fetch(PDO::FETCH_NUM);
    check('base => 1 sortie', (int) $rec[0], 1);
    check('base => commentaire personne+staff',
        strpos($rec[1], 'Tonton QA') !== false && strpos($rec[1], 'staff #9') !== false, true);

    // Rejeu idempotent.
    $proof = $pdo->query("SELECT TOKEN FROM schoolsafe_pickup_proofs WHERE STUDENT_ID=1"
        . " AND USED_AT IS NOT NULL ORDER BY ID DESC LIMIT 1")->fetchColumn();
    $r = SchoolSafePickupScan($qr, (string) $cpId, $secretHex, $ctx, $db,
        ['proof_token' => $proof, 'human_confirmed' => true]);
    check('base => rejeu CONFIRMED', $r['result'], 'CONFIRMED');
    check('base => rejeu idempotent', $r['idempotent'], true);

    // Le fil parent voit la sortie (source departures, sans panne).
    require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeNotificationFeed.fnc.php';
    $parentId = (int) $pdo->query("SELECT STAFF_ID FROM staff WHERE USERNAME='qa.parent'")->fetchColumn();
    $degraded = [];
    $feed = SchoolSafeNotificationFeed([], [], ['1'], $parentId, 1, 2026, $degraded);
    check('fil => source departures sans panne', in_array('departures', $degraded, true), false);
    $found = false;
    foreach ($feed as $item) {
        if (($item['id'] ?? '') !== '' && strpos($item['id'], 'departure:') === 0
            && ($item['event_type'] ?? '') === 'student.pickup') { $found = true; break; }
    }
    check('fil => item student.pickup présent', $found, true);
} finally {
    $pdo->exec("DELETE FROM schoolsafe_pickup_proofs WHERE STUDENT_ID=1 AND LOCATION_ID=$cpId");
    $pdo->exec("DELETE FROM entry_exit_student_records WHERE CHECKPOINT_ID=$cpId");
    $pdo->exec("DELETE FROM schoolsafe_pickup_authorizations WHERE STUDENT_ID=1 AND REASON='QA'");
    $pdo->exec("DELETE FROM schoolsafe_pickup_guardians WHERE STUDENT_ID=1 AND FULL_NAME IN ('Maman QA','Tonton QA')");
    $pdo->exec("DELETE FROM schoolsafe_student_cards WHERE CARD_NUMBER=" . $q($card));
    $pdo->exec("DELETE FROM entry_exit_checkpoints WHERE TITLE='Portail QA Pickup'");
    $left = $pdo->query("SELECT (SELECT COUNT(*) FROM schoolsafe_pickup_proofs WHERE STUDENT_ID=1)"
        . " + (SELECT COUNT(*) FROM schoolsafe_pickup_guardians WHERE FULL_NAME IN ('Maman QA','Tonton QA'))"
        . " + (SELECT COUNT(*) FROM schoolsafe_student_cards WHERE CARD_NUMBER LIKE 'TEST-PICKUP-%')"
        . " + (SELECT COUNT(*) FROM entry_exit_checkpoints WHERE TITLE='Portail QA Pickup')")->fetchColumn();
    check('nettoyage test', (int) $left, 0);
}

echo $failures === 0 ? "GREEN : intégration base OK.\n" : "STOP : $failures échec(s).\n";
exit($failures === 0 ? 0 : 1);
