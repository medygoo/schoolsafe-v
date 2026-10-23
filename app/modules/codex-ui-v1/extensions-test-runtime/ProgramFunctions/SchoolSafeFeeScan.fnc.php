<?php
// SchoolSafe — Lot FEES : consultation des frais au scanner (Rosario moteur principal).
//
// Lecture STRICTEMENT consultative : `billing_fees` / `billing_payments` ne
// sont ni créés, ni modifiés, ni supprimés. Seule trace écrite : l'historique
// des consultations autorisées (`schoolsafe_fee_consults`, migration 023).
// Un impayé ne bloque ni l'entrée, ni la présence, ni la récupération : ce
// moteur ne touche à aucun de ces chemins (aucun appel, aucune écriture).
//
// Règles de solde (miroir natif `Student_Billing/StudentFees.php`) :
// - R1 : fees_total = SUM des frais à WAIVED_FEE_ID NULL (école+année+élève).
// - R2 : un frais est exonéré s'il porte ≥1 ligne d'exonération
//        (WAIVED_FEE_ID = son ID) ; exempt_total = SUM de ces frais.
// - R3 : net_paid = SUM des paiements effectifs = REFUNDED_PAYMENT_ID NULL
//        ET non remboursés par une autre ligne (miroir `StudentPayments.php`).
// - R4 : balance = (fees_total − exempt_total) − net_paid.
// États : no_data (aucune ligne), exempt (dû soldé par exonérations, rien
// payé), paid/À jour (balance ≤ 0), partial (payé > 0, reste > 0),
// due/exigible (rien payé, reste > 0). Échéances = info (dépassées comptées).
//
// Détails (montants) réservés aux habilités facturation ; sinon réponse
// d'orientation FEE_STATUS sans montant (le gardien n'y voit aucun détail).
// $ctx : ['user'=>int, 'school'=>int, 'year'=>int, 'detail'=>bool].
// $db : ['get'=>callable(string):array, 'exec'=>callable(string):void].
// $day : date Y-m-d (tests uniquement, défaut aujourd'hui).
// Retourne null quand le local est indisponible (secret invalide).
function SchoolSafeFeeScan($qr, $secretHex, array $ctx, array $db, $day = null)
{
    require_once __DIR__ . '/SchoolSafeCardVerify.fnc.php';
    $school = (int) ($ctx['school'] ?? 0);
    $year = (int) ($ctx['year'] ?? 0);
    $user = (int) ($ctx['user'] ?? 0);
    if ($school < 1 || $year < 1 || $user < 1) throw new RuntimeException('INVALID_CONTEXT');
    $day = $day === null ? date('Y-m-d') : $day;
    if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $day)) throw new RuntimeException('INVALID_CONTEXT');
    $cardQuery = function ($number, $s) use ($db, $year) {
        return SchoolSafeCardVerifyQuery($number, $s, $year, $db['get']);
    };
    $verify = SchoolSafeCardVerifyQr($qr, $secretHex, $school, $year, $cardQuery);
    if ($verify === null) return null;
    if (($verify['result'] ?? '') !== 'VALID') return $verify;
    $get = $db['get'];
    $exec = $db['exec'];
    $studentId = (int) $verify['student_id'];
    $where = 'STUDENT_ID=' . $studentId . ' AND SCHOOL_ID=' . $school . ' AND SYEAR=' . $year;

    $fees = $get('SELECT ID, AMOUNT FROM billing_fees WHERE ' . $where . ' AND WAIVED_FEE_ID IS NULL');
    $waivers = $get('SELECT WAIVED_FEE_ID, AMOUNT FROM billing_fees WHERE ' . $where . ' AND WAIVED_FEE_ID IS NOT NULL');
    $waivedIds = [];
    if (is_array($waivers)) foreach ($waivers as $w) {
        if (isset($w['WAIVED_FEE_ID'])) $waivedIds[(int) $w['WAIVED_FEE_ID']] = true;
    }
    $feesTotal = 0.0;
    $exemptTotal = 0.0;
    $feeCount = 0;
    if (is_array($fees)) foreach ($fees as $fee) {
        if (!isset($fee['ID'], $fee['AMOUNT'])) continue;
        $feeCount++;
        $amount = (float) $fee['AMOUNT'];
        $feesTotal += $amount;
        if (isset($waivedIds[(int) $fee['ID']])) $exemptTotal += $amount;
    }
    $payments = $get('SELECT ID, AMOUNT, REFUNDED_PAYMENT_ID FROM billing_payments WHERE ' . $where);
    $refundedIds = [];
    $payRows = [];
    if (is_array($payments)) foreach ($payments as $p) {
        if (!isset($p['ID'])) continue;
        $payRows[(int) $p['ID']] = $p;
        if (isset($p['REFUNDED_PAYMENT_ID']) && $p['REFUNDED_PAYMENT_ID'] !== null && $p['REFUNDED_PAYMENT_ID'] !== '') {
            $refundedIds[(int) $p['REFUNDED_PAYMENT_ID']] = true;
        }
    }
    $netPaid = 0.0;
    $payCount = 0;
    foreach ($payRows as $id => $p) {
        if (!isset($p['AMOUNT']) || $p['AMOUNT'] === null) continue;
        if ((isset($p['REFUNDED_PAYMENT_ID']) && $p['REFUNDED_PAYMENT_ID'] !== null && $p['REFUNDED_PAYMENT_ID'] !== '')
            || isset($refundedIds[$id])) {
            continue;
        }
        $payCount++;
        $netPaid += (float) $p['AMOUNT'];
    }
    $dueNet = round($feesTotal - $exemptTotal, 2);
    $balance = round($dueNet - $netPaid, 2);

    if ($feeCount === 0 && $payCount === 0) {
        $status = 'no_data';
    } elseif ($dueNet <= 0 && $netPaid <= 0 && $exemptTotal > 0) {
        $status = 'exempt';
    } elseif ($balance <= 0) {
        $status = 'paid';
    } elseif ($netPaid > 0) {
        $status = 'partial';
    } else {
        $status = 'due';
    }

    $overdue = 0;
    if ($balance > 0) {
        $safeDay = "'" . str_replace("'", "''", $day) . "'";
        $candidates = $get('SELECT ID, DUE_DATE FROM billing_fees WHERE ' . $where
            . ' AND WAIVED_FEE_ID IS NULL AND DUE_DATE IS NOT NULL AND DUE_DATE<' . $safeDay);
        if (is_array($candidates)) foreach ($candidates as $c) {
            if (!isset($c['ID']) || isset($waivedIds[(int) $c['ID']])) continue;
            if (!isset($c['DUE_DATE']) || $c['DUE_DATE'] === null || $c['DUE_DATE'] === '') continue;
            if (strcmp((string) $c['DUE_DATE'], $day) < 0) $overdue++;
        }
    }

    // Historique de la consultation autorisée (pas un paiement).
    $exec('BEGIN');
    try {
        $consult = $get('INSERT INTO schoolsafe_fee_consults(school_id, student_id, consulted_by,'
            . ' balance_status, fee_total, paid_total, balance)'
            . ' VALUES(' . $school . ',' . $studentId . ',' . $user . ",'" . $status . "',"
            . number_format($dueNet, 2, '.', '') . ',' . number_format($netPaid, 2, '.', '') . ','
            . number_format($balance, 2, '.', '') . ') RETURNING ID');
        if (!is_array($consult) || !isset($consult[1])) throw new RuntimeException('FEE_WRITE_FAILED');
        $exec('COMMIT');
        $consultId = (int) $consult[1]['ID'];
    } catch (Throwable $error) {
        try { $exec('ROLLBACK'); } catch (Throwable $ignored) { }
        throw $error;
    }

    $result = ['result' => 'FEE_STATUS', 'decision' => 'allowed',
        'balance_status' => $status, 'consult_id' => $consultId] + $verify;
    if (!empty($ctx['detail'])) {
        $result['fee_total'] = number_format($dueNet, 2, '.', '');
        $result['paid_total'] = number_format($netPaid, 2, '.', '');
        $result['balance'] = number_format($balance, 2, '.', '');
        $result['fee_count'] = $feeCount;
        $result['overdue_count'] = $overdue;
    }
    return $result;
}

// Libellés FR pour les tableaux de bord (jamais de montant imposé à l'appelant).
function SchoolSafeFeeStatusLabel($status)
{
    return ['paid' => 'À jour', 'partial' => 'Partiellement payé', 'due' => 'Paiement exigible',
        'exempt' => 'Exempté', 'no_data' => 'Aucune donnée'][ $status ] ?? 'Statut indisponible';
}
