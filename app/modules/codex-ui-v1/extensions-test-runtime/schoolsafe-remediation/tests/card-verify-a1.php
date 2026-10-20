<?php
// SchoolSafe — Lot A1 : test RED/GREEN de la vérification QR locale.
// Usage (depuis le conteneur L1, secret de TEST uniquement, jamais en prod) :
//   SCHOOLSAFE_CARD_HMAC_SECRET=<64 hex de test> php schoolsafe-remediation/tests/card-verify-a1.php
// Sortie 0 si et seulement si tous les cas passent. Ne modifie aucune donnée
// existante : lignes de test préfixées TEST-A1-, supprimées à la fin.
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
if (!preg_match('/^[a-f0-9]{64}$/D', $secretHex)) { fwrite(STDERR, "Secret de test manquant/invalide.\n"); exit(2); }
$secret = hex2bin($secretHex);
$sign = function ($n) use ($secret) {
    return substr(strtr(base64_encode(hash_hmac('sha256', $n, $secret, true)), '+/', '-_'), 0, 32);
};
$qr = function ($n) use ($sign) { return 'schoolsafe://card/' . $n . '/' . $sign($n); };

// --- Registre simulé (unitaire pur, sans base) : école 1 / élève 11 actif.
$rows = [
    ['school_id' => 1, 'student_id' => 11, 'card_number' => 'TEST-A1-ACTIVE',
     'signature' => $sign('TEST-A1-ACTIVE'), 'status' => 'active',
     'first_name' => 'Awa', 'last_name' => 'Diallo', 'class_name' => '6e A', 'enrolled' => true],
    ['school_id' => 1, 'student_id' => 12, 'card_number' => 'TEST-A1-LOST',
     'signature' => $sign('TEST-A1-LOST'), 'status' => 'lost',
     'first_name' => 'Moussa', 'last_name' => 'Keita', 'class_name' => '6e A', 'enrolled' => true],
    ['school_id' => 1, 'student_id' => 13, 'card_number' => 'TEST-A1-REPLACED',
     'signature' => $sign('TEST-A1-REPLACED'), 'status' => 'replaced',
     'first_name' => 'Fatou', 'last_name' => 'Sow', 'class_name' => '5e B', 'enrolled' => true],
    ['school_id' => 1, 'student_id' => 14, 'card_number' => 'TEST-A1-REVOKED',
     'signature' => $sign('TEST-A1-REVOKED'), 'status' => 'revoked',
     'first_name' => 'Ibrahim', 'last_name' => 'Touré', 'class_name' => '5e B', 'enrolled' => true],
    ['school_id' => 1, 'student_id' => 15, 'card_number' => 'TEST-A1-NOENROLL',
     'signature' => $sign('TEST-A1-NOENROLL'), 'status' => 'active',
     'first_name' => 'Aicha', 'last_name' => 'Bah', 'class_name' => '', 'enrolled' => false],
];
$query = function ($cardNumber, $school) use ($rows) {
    foreach ($rows as $r) {
        if ($r['card_number'] === $cardNumber && (int) $r['school_id'] === (int) $school) return $r;
    }
    return null;
};

// 1. Carte active inscrite => VALID + identité.
$r = SchoolSafeCardVerifyQr($qr('TEST-A1-ACTIVE'), $secretHex, 1, 2026, $query);
check('active => VALID', $r['result'], 'VALID');
check('active => signature_valid', $r['signature_valid'], true);
check('active => nativeStudentId', $r['nativeStudentId'], 11);
check('active => nom', $r['student_name'], 'Awa Diallo');

// 2. Signature falsifiée => INVALID/signature_mismatch.
$r = SchoolSafeCardVerifyQr('schoolsafe://card/TEST-A1-ACTIVE/' . str_repeat('A', 32), $secretHex, 1, 2026, $query);
check('forgée => INVALID', $r['result'], 'INVALID');
check('forgée => raison', $r['reason'], 'signature_mismatch');
check('forgée => signature_valid false', $r['signature_valid'], false);

// 3. QR malformé => INVALID/invalid_qr.
foreach (["n'importe quoi", 'schoolsafe://card/seulement', '', 'schoolsafe://card/a/b/c'] as $i => $bad) {
    $r = SchoolSafeCardVerifyQr($bad, $secretHex, 1, 2026, $query);
    check("malformé #$i => INVALID", $r['result'], 'INVALID');
    check("malformé #$i => raison", $r['reason'], 'invalid_qr');
}

// 4. Carte inconnue => INVALID/card_not_found.
$r = SchoolSafeCardVerifyQr($qr('TEST-A1-INCONNUE'), $secretHex, 1, 2026, $query);
check('inconnue => INVALID', $r['result'], 'INVALID');
check('inconnue => raison', $r['reason'], 'card_not_found');

// 5. Statuts perdu/remplacé/révoqué conservés.
foreach ([['TEST-A1-LOST', 'LOST'], ['TEST-A1-REPLACED', 'REPLACED'], ['TEST-A1-REVOKED', 'REVOKED']] as [$n, $want]) {
    $r = SchoolSafeCardVerifyQr($qr($n), $secretHex, 1, 2026, $query);
    check("$n => $want", $r['result'], $want);
    check("$n => signature_valid", $r['signature_valid'], true);
}

// 6. Carte active mais élève non inscrit (année) => STUDENT_NOT_OPERATIONAL.
$r = SchoolSafeCardVerifyQr($qr('TEST-A1-NOENROLL'), $secretHex, 1, 2026, $query);
check('non inscrit => STUDENT_NOT_OPERATIONAL', $r['result'], 'STUDENT_NOT_OPERATIONAL');

// 7. Isolation inter-écoles : carte de l'école 1 vérifiée en école 2 => introuvable.
$r = SchoolSafeCardVerifyQr($qr('TEST-A1-ACTIVE'), $secretHex, 2, 2026, $query);
check('autre école => card_not_found', $r['reason'], 'card_not_found');

// 8. Secret mal formaté => indisponible (repli bridge/503, jamais de faux positif).
$r = SchoolSafeCardVerifyQr($qr('TEST-A1-ACTIVE'), 'pas-un-secret', 1, 2026, $query);
check('secret invalide => null', $r, null);

// 9. QR trop long => INVALID.
$r = SchoolSafeCardVerifyQr('schoolsafe://card/' . str_repeat('X', 2000) . '/' . str_repeat('A', 32), $secretHex, 1, 2026, $query);
check('trop long => INVALID', $r['result'], 'INVALID');

echo $failures === 0 ? "GREEN : tous les cas passent.\n" : "STOP : $failures échec(s).\n";
exit($failures === 0 ? 0 : 1);
