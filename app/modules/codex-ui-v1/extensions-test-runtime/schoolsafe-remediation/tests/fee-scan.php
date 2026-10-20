<?php
// SchoolSafe — Lot FEES : test RED/GREEN de la consultation (base simulée).
// Usage : php schoolsafe-remediation/tests/fee-scan.php (conteneur L1).
// Sortie 0 si et seulement si tous les cas passent.
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(2); }
chdir(dirname(__DIR__, 2));
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeFeeScan.fnc.php';

$failures = 0;
function check($label, $actual, $expected) {
    global $failures;
    if ($actual === $expected) { echo "ok   $label\n"; return; }
    $failures++;
    echo "FAIL $label : attendu " . var_export($expected, true)
        . ", obtenu " . var_export($actual, true) . "\n";
}

$secretHex = 'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90';
$secret = hex2bin($secretHex);
$sign = function ($n) use ($secret) {
    return substr(strtr(base64_encode(hash_hmac('sha256', $n, $secret, true)), '+/', '-_'), 0, 32);
};
$qr = function ($n) use ($sign) { return 'schoolsafe://card/' . $n . '/' . $sign($n); };

// --- Base simulée ------------------------------------------------------------
$state = [];
function resetState($fees = [], $payments = []) {
    global $state;
    $state = [
        'cards' => [
            'F-ACTIVE' => ['school_id' => 1, 'student_id' => 11, 'status' => 'active', 'enrolled' => true,
                'first_name' => 'Awa', 'last_name' => 'Diallo', 'class_name' => '6e A'],
        ],
        'fees' => $fees, 'payments' => $payments, 'consults' => [],
    ];
}
resetState();
$db = [
    'get' => function ($sql) {
        global $state, $sign;
        if (strpos($sql, 'to_regclass') !== false) return [1 => ['T' => 'public.schoolsafe_student_cards']];
        if (strpos($sql, 'FROM schoolsafe_student_cards') !== false) {
            preg_match("/card_number='((?:[^']|'')+)'/", $sql, $m);
            $n = str_replace("''", "'", $m[1]);
            preg_match('/c.school_id=(\d+)/', $sql, $s);
            if (!isset($state['cards'][$n]) || (int) $state['cards'][$n]['school_id'] !== (int) $s[1]) return [];
            $c = $state['cards'][$n];
            return [1 => ['STUDENT_ID' => $c['student_id'], 'SIGNATURE' => $sign($n), 'STATUS' => $c['status'],
                'FIRST_NAME' => $c['first_name'], 'LAST_NAME' => $c['last_name'],
                'CLASS_NAME' => $c['class_name'], 'ENROLLED' => $c['enrolled'] ? 't' : 'f']];
        }
        if (strpos($sql, 'FROM billing_fees') !== false && strpos($sql, 'COUNT') === false) {
            $waivedOnly = strpos($sql, 'WAIVED_FEE_ID IS NOT NULL') !== false;
            $rows = [];
            foreach ($state['fees'] as $f) {
                $isWaiver = isset($f['WAIVED_FEE_ID']) && $f['WAIVED_FEE_ID'] !== null;
                if ($waivedOnly !== $isWaiver) continue;
                $rows[] = $f;
            }
            $out = [];
            foreach ($rows as $i => $r) $out[$i + 1] = $r;
            return $out;
        }
        if (strpos($sql, 'FROM billing_fees') !== false) return [];
        if (strpos($sql, 'FROM billing_payments') !== false) {
            $out = [];
            foreach ($state['payments'] as $i => $p) $out[$i + 1] = $p;
            return $out;
        }
        if (strpos($sql, 'INSERT INTO schoolsafe_fee_consults') !== false) {
            $id = 70 + count($state['consults']);
            $state['consults'][] = ['id' => $id, 'sql' => $sql];
            return [1 => ['ID' => $id]];
        }
        return [];
    },
    'exec' => function ($sql) { },
];
$ctx = ['user' => 9, 'school' => 1, 'year' => 2026, 'detail' => true];
$ctxNodetail = ['user' => 9, 'school' => 1, 'year' => 2026];
$day = '2026-05-04';

// 1. Aucune donnée.
resetState();
$r = SchoolSafeFeeScan($qr('F-ACTIVE'), $secretHex, $ctx, $db, $day);
check('vide => FEE_STATUS', $r['result'], 'FEE_STATUS');
check('vide => no_data', $r['balance_status'], 'no_data');
check('vide => historisé', count($state['consults']), 1);

// 2. À jour (payé >= dû).
resetState(
    [['ID' => 1, 'AMOUNT' => '100.00', 'DUE_DATE' => null]],
    [['ID' => 1, 'AMOUNT' => '100.00', 'REFUNDED_PAYMENT_ID' => null]]);
$r = SchoolSafeFeeScan($qr('F-ACTIVE'), $secretHex, $ctx, $db, $day);
check('payé => paid', $r['balance_status'], 'paid');
check('payé => balance 0.00', $r['balance'], '0.00');
check('payé => libellé', SchoolSafeFeeStatusLabel('paid'), 'À jour');

// 3. Partiellement payé.
resetState(
    [['ID' => 1, 'AMOUNT' => '100.00', 'DUE_DATE' => null]],
    [['ID' => 1, 'AMOUNT' => '40.00', 'REFUNDED_PAYMENT_ID' => null]]);
$r = SchoolSafeFeeScan($qr('F-ACTIVE'), $secretHex, $ctx, $db, $day);
check('partiel => partial', $r['balance_status'], 'partial');
check('partiel => balance 60.00', $r['balance'], '60.00');

// 4. Exigible (rien payé, échéance dépassée comptée).
resetState(
    [['ID' => 1, 'AMOUNT' => '100.00', 'DUE_DATE' => '2026-04-01'],
     ['ID' => 2, 'AMOUNT' => '50.00', 'DUE_DATE' => null]], []);
$r = SchoolSafeFeeScan($qr('F-ACTIVE'), $secretHex, $ctx, $db, $day);
check('dû => due', $r['balance_status'], 'due');
check('dû => balance 150.00', $r['balance'], '150.00');
check('dû => 1 échue', $r['overdue_count'], 1);

// 5. Exempté (frais soldé par exonération, rien payé).
resetState(
    [['ID' => 1, 'AMOUNT' => '100.00', 'DUE_DATE' => null],
     ['ID' => 2, 'AMOUNT' => '100.00', 'WAIVED_FEE_ID' => 1, 'DUE_DATE' => null]], []);
$r = SchoolSafeFeeScan($qr('F-ACTIVE'), $secretHex, $ctx, $db, $day);
check('exonéré => exempt', $r['balance_status'], 'exempt');
check('exonéré => dû net 0.00', $r['fee_total'], '0.00');

// 6. Remboursement exclu (paiement remboursé + ligne de remboursement ignorés).
resetState(
    [['ID' => 1, 'AMOUNT' => '100.00', 'DUE_DATE' => null]],
    [['ID' => 1, 'AMOUNT' => '100.00', 'REFUNDED_PAYMENT_ID' => null],
     ['ID' => 2, 'AMOUNT' => '-100.00', 'REFUNDED_PAYMENT_ID' => 1]]);
$r = SchoolSafeFeeScan($qr('F-ACTIVE'), $secretHex, $ctx, $db, $day);
check('remboursé => due', $r['balance_status'], 'due');
check('remboursé => payé 0.00', $r['paid_total'], '0.00');

// 7. Gardien sans habilitation : orientation sans montant.
resetState(
    [['ID' => 1, 'AMOUNT' => '100.00', 'DUE_DATE' => null]], []);
$r = SchoolSafeFeeScan($qr('F-ACTIVE'), $secretHex, $ctxNodetail, $db, $day);
check('gardien => FEE_STATUS', $r['result'], 'FEE_STATUS');
check('gardien => due', $r['balance_status'], 'due');
check('gardien => aucun montant', isset($r['balance']), false);

// 8. Refus : QR falsifié, mauvaise école, sans secret — aucune consultation.
resetState(
    [['ID' => 1, 'AMOUNT' => '100.00', 'DUE_DATE' => null]], []);
$before = count($state['consults']);
$r = SchoolSafeFeeScan('schoolsafe://card/F-ACTIVE/' . str_repeat('B', 32), $secretHex, $ctx, $db, $day);
check('falsifié => INVALID', $r['result'], 'INVALID');
$r = SchoolSafeFeeScan($qr('F-ACTIVE'), $secretHex, array_merge($ctx, ['school' => 2]), $db, $day);
check('mauvaise école => card_not_found', $r['reason'], 'card_not_found');
$r = SchoolSafeFeeScan($qr('F-ACTIVE'), 'pas-un-secret', $ctx, $db, $day);
check('sans secret => null', $r, null);
check('refus => rien historisé', count($state['consults']), $before);

echo $failures === 0 ? "GREEN : tous les cas passent.\n" : "STOP : $failures échec(s).\n";
exit($failures === 0 ? 0 : 1);
