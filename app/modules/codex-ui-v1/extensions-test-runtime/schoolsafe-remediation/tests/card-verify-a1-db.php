<?php
// SchoolSafe — Lot A1 : intégration base réelle (L1 uniquement, jamais en prod).
// Insère des cartes TEST-A1-* pour l'élève 1 / école 1 / année 2026, vérifie
// via SchoolSafeCardVerifyQuery (vrai SQL), puis supprime les lignes de test.
// Usage : POSTGRES_* + SCHOOLSAFE_CARD_HMAC_SECRET (test) dans l'environnement.
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(2); }
chdir(dirname(__DIR__, 2));
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeCardVerify.fnc.php';

$failures = 0;
function check($label, $actual, $expected) {
    global $failures;
    if ($actual === $expected) { echo "ok   $label\n"; return; }
    $failures++;
    echo "FAIL $label : attendu " . var_export($expected, true)
        . ", obtenu " . var_export($actual, true) . "\n";
}

$secretHex = getenv('SCHOOLSAFE_CARD_HMAC_SECRET') ?: '';
$dsn = 'pgsql:host=' . (getenv('SCHOOLSAFE_DB_HOST') ?: 'db')
    . ';dbname=' . (getenv('POSTGRES_DB') ?: 'schoolsafe');
try {
    $pdo = new PDO($dsn, getenv('POSTGRES_USER') ?: 'schoolsafe', getenv('POSTGRES_PASSWORD') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) { fwrite(STDERR, "Connexion base impossible.\n"); exit(2); }

// Adaptateur au format DBGet de Rosario : [1 => [COLONNES_MAJUSCULES]].
$dbget = function ($sql) use ($pdo) {
    $out = [];
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $i => $row) {
        $upper = [];
        foreach ($row as $k => $v) $upper[strtoupper($k)] = $v;
        $out[$i + 1] = $upper;
    }
    return $out;
};

$sign = function ($n) use ($secretHex) {
    return substr(strtr(base64_encode(hash_hmac('sha256', $n, hex2bin($secretHex), true)), '+/', '-_'), 0, 32);
};
$qr = function ($n) use ($sign) { return 'schoolsafe://card/' . $n . '/' . $sign($n); };
$q = function ($v) { return "'" . str_replace("'", "''", $v) . "'"; };

$cards = ['TEST-A1-DB-OK' => 'active', 'TEST-A1-DB-LOST' => 'lost'];
try {
    foreach ($cards as $n => $status) {
        $pdo->exec("INSERT INTO schoolsafe_student_cards(school_id, student_id, card_number, signature, status)"
            . " VALUES(1, 1, " . $q($n) . ", " . $q($sign($n)) . ", " . $q($status) . ")");
    }
    $query = function ($number, $school) use ($dbget) {
        return SchoolSafeCardVerifyQuery($number, $school, 2026, $dbget);
    };

    $r = SchoolSafeCardVerifyQr($qr('TEST-A1-DB-OK'), $secretHex, 1, 2026, $query);
    check('base => VALID', $r['result'], 'VALID');
    check('base => nativeStudentId', $r['nativeStudentId'], 1);
    check('base => nom non vide', $r['student_name'] !== '', true);

    $r = SchoolSafeCardVerifyQr($qr('TEST-A1-DB-LOST'), $secretHex, 1, 2026, $query);
    check('base => LOST', $r['result'], 'LOST');

    $r = SchoolSafeCardVerifyQr($qr('TEST-A1-DB-ABSENT'), $secretHex, 1, 2026, $query);
    check('base => inconnue', $r['reason'], 'card_not_found');

    $r = SchoolSafeCardVerifyQr($qr('TEST-A1-DB-OK'), $secretHex, 999, 2026, $query);
    check('base => autre école', $r['reason'], 'card_not_found');
} finally {
    $pdo->exec("DELETE FROM schoolsafe_student_cards WHERE card_number LIKE 'TEST-A1-DB-%'");
    $left = $pdo->query("SELECT COUNT(*) FROM schoolsafe_student_cards WHERE card_number LIKE 'TEST-A1-%'")->fetchColumn();
    check('nettoyage test', (int) $left, 0);
}

echo $failures === 0 ? "GREEN : intégration base OK.\n" : "STOP : $failures échec(s).\n";
exit($failures === 0 ? 0 : 1);
