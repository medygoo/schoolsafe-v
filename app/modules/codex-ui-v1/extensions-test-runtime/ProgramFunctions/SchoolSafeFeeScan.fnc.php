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
// - R1 : dû net = SUM de TOUTES les lignes de frais SIGNÉES du périmètre.
// - R2 : exonération = son montant négatif réel, pas l'annulation fictive du
//        montant actuel du frais lié (qui peut avoir été modifié entretemps).
// - R3 : net_paid = SUM de TOUTES les lignes de paiement SIGNÉES ; un
//        remboursement partiel ne supprime pas le paiement initial entier.
// - R4 : balance = dû net − net_paid, calcul exact en centimes numeric(14,2).
// Lectures fausses/montants invalides : indisponible, jamais un zéro inventé.
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
    if (!is_array($fees)||!is_array($waivers)) throw new RuntimeException('FEE_READ_UNAVAILABLE');
    $waivedAmounts=[];$feeAmounts=[];$feesTotal=0;$waiverTotal=0;
    foreach ($fees as $fee) {
        if (!isset($fee['ID'],$fee['AMOUNT'])) throw new RuntimeException('FEE_READ_UNAVAILABLE');
        $amount=SchoolSafeFeeCents($fee['AMOUNT']);
        $feesTotal=SchoolSafeFeeAddCents($feesTotal,$amount);
        $feeAmounts[(int)$fee['ID']]=$amount;
    }
    foreach ($waivers as $waiver) {
        if (!isset($waiver['WAIVED_FEE_ID'],$waiver['AMOUNT'])) throw new RuntimeException('FEE_READ_UNAVAILABLE');
        $amount=SchoolSafeFeeCents($waiver['AMOUNT']);$id=(int)$waiver['WAIVED_FEE_ID'];
        $waiverTotal=SchoolSafeFeeAddCents($waiverTotal,$amount);
        $waivedAmounts[$id]=SchoolSafeFeeAddCents($waivedAmounts[$id]??0,$amount);
    }
    $feeCount=count($fees);
    $payments=$get('SELECT ID, AMOUNT, REFUNDED_PAYMENT_ID FROM billing_payments WHERE '.$where);
    if (!is_array($payments)) throw new RuntimeException('FEE_READ_UNAVAILABLE');
    $netPaid=0;$payCount=count($payments);
    foreach ($payments as $payment) {
        if (!isset($payment['ID'],$payment['AMOUNT'])) throw new RuntimeException('FEE_READ_UNAVAILABLE');
        $netPaid=SchoolSafeFeeAddCents($netPaid,SchoolSafeFeeCents($payment['AMOUNT']));
    }
    $dueNet=SchoolSafeFeeAddCents($feesTotal,$waiverTotal);
    if (($netPaid>0&&$dueNet<PHP_INT_MIN+$netPaid)||($netPaid<0&&$dueNet>PHP_INT_MAX+$netPaid)) throw new RuntimeException('FEE_READ_UNAVAILABLE');
    $balance=$dueNet-$netPaid;

    if ($feeCount === 0 && count($waivers) === 0 && $payCount === 0) {
        $status = 'no_data';
    } elseif ($dueNet <= 0 && $netPaid <= 0 && $waiverTotal < 0) {
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
        if (!is_array($candidates)) throw new RuntimeException('FEE_READ_UNAVAILABLE');
        // Nominal fee deadlines only: payments have no native per-fee allocation.
        foreach ($candidates as $c) {
            if (!isset($c['ID'])||!isset($feeAmounts[(int)$c['ID']])) throw new RuntimeException('FEE_READ_UNAVAILABLE');
            if (SchoolSafeFeeAddCents($feeAmounts[(int)$c['ID']],$waivedAmounts[(int)$c['ID']]??0)<=0) continue;
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
            . SchoolSafeFeeFormatCents($dueNet) . ',' . SchoolSafeFeeFormatCents($netPaid) . ','
            . SchoolSafeFeeFormatCents($balance) . ') RETURNING ID');
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
        $result['fee_total'] = SchoolSafeFeeFormatCents($dueNet);
        $result['paid_total'] = SchoolSafeFeeFormatCents($netPaid);
        $result['balance'] = SchoolSafeFeeFormatCents($balance);
        $result['fee_count'] = $feeCount;
        $result['overdue_count'] = $overdue;
        $result['overdue_basis'] = 'nominal_unallocated';
    }
    return $result;
}

// PostgreSQL numeric(14,2) rows are decimal strings, not binary floats.
function SchoolSafeFeeCents($value): int
{
    if ((!is_string($value)&&!is_int($value))||preg_match('/^(-?)([0-9]{1,12})(?:\.([0-9]{1,2}))?$/D',(string)$value,$parts)!==1) {
        throw new RuntimeException('FEE_READ_UNAVAILABLE');
    }
    $cents=(int)$parts[2]*100+(int)str_pad($parts[3]??'',2,'0',STR_PAD_RIGHT);
    return $parts[1]==='-'?-$cents:$cents;
}

function SchoolSafeFeeAddCents(int $total,int $amount): int
{
    if (($amount>0&&$total>PHP_INT_MAX-$amount)||($amount<0&&$total<PHP_INT_MIN-$amount)) throw new RuntimeException('FEE_READ_UNAVAILABLE');
    return $total+$amount;
}

function SchoolSafeFeeFormatCents(int $cents): string
{
    return ($cents<0?'-':'').abs(intdiv($cents,100)).'.'.str_pad((string)abs($cents%100),2,'0',STR_PAD_LEFT);
}

// Libellés FR pour les tableaux de bord (jamais de montant imposé à l'appelant).
function SchoolSafeFeeStatusLabel($status)
{
    return ['paid' => 'À jour', 'partial' => 'Partiellement payé', 'due' => 'Paiement exigible',
        'exempt' => 'Exempté', 'no_data' => 'Aucune donnée'][ $status ] ?? 'Statut indisponible';
}
