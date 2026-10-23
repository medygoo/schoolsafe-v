<?php
// SchoolSafe — Lot ENTRY : arrivée sur site (Rosario moteur principal).
//
// Enchaîne, dans une transaction : vérification QR (registre A1) → contrôle du
// point de passage borné école/année → état du dernier passage du même périmètre
// → record natif d'arrivée. Après une sortie réelle, une nouvelle arrivée ouvre
// un nouveau cycle ; deux scans sans sortie restent idempotents. Le passage ne renseigne
// jamais attendance_day : la présence pédagogique reste une saisie distincte.
//
// Lecture seule quand la carte est refusée : aucun record pour une carte
// invalide/perdue/révoquée ; tentative tracée (unauthorized='Y') pour un portail
// valide au mauvais sens. Retard = arrivée après le début du premier cours + 15
// minutes de tolérance (school_periods, données Rosario, aucune config ajoutée).
//
// $ctx : ['user'=>int, 'school'=>int, 'year'=>int, 'staff_label'=>string].
// $db : ['get'=>callable(string):array, 'exec'=>callable(string):void].
// $now : timestamp unix (tests uniquement, défaut time()).
// Retourne null quand le local est indisponible (secret invalide) : l'appelant
// applique le repli existant. Lève en cas d'échec d'écriture (repli 503).
function SchoolSafeEntryScan($qr, $locationId, $secretHex, array $ctx, array $db, $now = null)
{
    require_once __DIR__ . '/SchoolSafeCardVerify.fnc.php';
    $school = (int) ($ctx['school'] ?? 0);
    $year = (int) ($ctx['year'] ?? 0);
    $user = (int) ($ctx['user'] ?? 0);
    if ($school < 1 || $year < 1 || $user < 1) throw new RuntimeException('INVALID_CONTEXT');
    if (!is_string($locationId) || !preg_match('/^[1-9][0-9]{0,9}$/D', $locationId)) {
        return ['result' => 'DENIED', 'signature_valid' => true, 'reason' => 'LOCATION_DENIED'];
    }
    $now = $now === null ? time() : (int) $now;
    $clock = date('H:i:s', $now);
    $cardQuery = function ($number, $s) use ($db, $year) {
        return SchoolSafeCardVerifyQuery($number, $s, $year, $db['get']);
    };
    $verify = SchoolSafeCardVerifyQr($qr, $secretHex, $school, $year, $cardQuery);
    if ($verify === null) return null;
    if (($verify['result'] ?? '') !== 'VALID') return $verify;
    $get = $db['get'];
    $exec = $db['exec'];
    require_once __DIR__ . '/SchoolSafeScanPassage.fnc.php';
    SchoolSafeScanPassageRequireScope($db);
    $cp = $get('SELECT ID, TITLE, TYPE, SCHOOLSAFE_SCAN_SCHOOL_ID, SCHOOLSAFE_SCAN_SYEAR, SCHOOLSAFE_SCAN_ENABLED FROM entry_exit_checkpoints WHERE ID=' . $locationId);
    if (!is_array($cp) || !isset($cp[1]) || !SchoolSafeScanCheckpointInScope($cp[1], $school, $year)) {
        return ['result' => 'DENIED', 'signature_valid' => true, 'reason' => 'LOCATION_DENIED'] + $verify;
    }
    $checkpoint = ['id' => (int) $cp[1]['ID'], 'label' => $cp[1]['TITLE']];
    return SchoolSafePickupStudentTransaction($verify['student_id'], $ctx, $db, function() use ($verify, $school, $year, $clock, $checkpoint, $user, $db, $get, $exec, $now) {
        $point = $get('SELECT ID, TYPE, SCHOOLSAFE_SCAN_SCHOOL_ID, SCHOOLSAFE_SCAN_SYEAR, SCHOOLSAFE_SCAN_ENABLED FROM entry_exit_checkpoints WHERE ID=' . $checkpoint['id'] . ' FOR SHARE');
        if (!is_array($point) || !isset($point[1]) || !SchoolSafeScanCheckpointInScope($point[1], $school, $year)) {
            return ['result'=>'DENIED','signature_valid'=>true,'reason'=>'LOCATION_DENIED'] + $verify;
        }
        $type = $point[1]['TYPE'];
        if ($type !== null && $type !== '' && $type !== 'in') {
            $exec("INSERT INTO entry_exit_student_records(STUDENT_ID, CHECKPOINT_ID, TYPE, UNAUTHORIZED, COMMENTS, RECORDED_AT, SCHOOLSAFE_SCAN_SCHOOL_ID, SCHOOLSAFE_SCAN_SYEAR)"
                . ' VALUES(' . (int) $verify['student_id'] . ',' . $checkpoint['id'] . ",'in','Y',"
                . "'Portail sens unique — staff #" . $user . "',CURRENT_TIMESTAMP," . $school . ',' . $year . ')');
            return ['result' => 'DENIED', 'decision' => 'denied', 'reason' => 'LOCATION_DENIED'] + $verify;
        }
        $last = SchoolSafeScanLatestPassage($verify['student_id'], $school, $year, $db);
        if ($last !== null && $last['TYPE'] === 'in') {
            return ['result' => 'ALREADY_PRESENT', 'decision' => 'allowed'] + $verify;
        }
        require_once __DIR__.'/SchoolSafeFeePolicy.fnc.php';
        $model=SchoolSafeFeePolicyActive(['school'=>$school,'year'=>$year],$db,true);
        $financial=['state'=>'inactive','color'=>null,'reason'=>'no_active_policy'];
        $consultId=null;
        if($model!==null){
            $ledger=SchoolSafeFeeLedgerRead((int)$verify['student_id'],$school,$year,$get,date('Y-m-d',$now));
            $financial=SchoolSafeFeeClassify($model,$ledger);
            require_once __DIR__.'/SchoolSafeFeeSnapshot.fnc.php';
            $feeCtx=['school'=>$school,'year'=>$year,'user'=>$user];
            $snapshot=SchoolSafeFeeControlSnapshot($verify,$feeCtx,$ledger,$model,$financial,SchoolSafeFeeControlMetadata($feeCtx,$get),'entry');
            $snapshot['checkpoint']=$checkpoint;
            $consultId=SchoolSafeFeeConsultRecord((int)$verify['student_id'],$feeCtx,$ledger,$db,$snapshot);
            if(!$financial['entry_allowed'])return ['result'=>'DENIED','decision'=>'denied',
                'reason'=>$financial['state']==='unavailable'?'FEE_CONTROL_UNAVAILABLE':'FEE_CONTROL_DENIED',
                'financial'=>$financial,'financial_consult_id'=>$consultId]+$verify;
        }
        $late = SchoolSafeEntryIsLate($school, $year, $clock, $get);
        $comment = 'Entrée ' . substr($clock, 0, 5) . ($late ? ' (retard)' : '') . ' — staff #' . $user;
        $commentSql = "'" . str_replace("'", "''", $comment) . "'";
        $inserted = $get("INSERT INTO entry_exit_student_records(STUDENT_ID, CHECKPOINT_ID, TYPE, COMMENTS, RECORDED_AT, SCHOOLSAFE_SCAN_SCHOOL_ID, SCHOOLSAFE_SCAN_SYEAR)"
            . ' VALUES(' . (int) $verify['student_id'] . ',' . $checkpoint['id'] . ",'in'," . $commentSql . ',CURRENT_TIMESTAMP,' . $school . ',' . $year . ')'
            . ' RETURNING ID, RECORDED_AT');
        if (!is_array($inserted) || !isset($inserted[1])) throw new RuntimeException('ENTRY_WRITE_FAILED');
        if($consultId!==null)SchoolSafeFeeConsultEntry($consultId,(int)$inserted[1]['ID'],(int)$verify['student_id'],['school'=>$school,'year'=>$year,'user'=>$user],$db);
        return ['result' => 'PRESENT', 'decision' => 'allowed', 'late' => $late,
            'record_id' => (int) $inserted[1]['ID'], 'occurred_at' => $inserted[1]['RECORDED_AT'],
            'checkpoint' => $checkpoint,'financial'=>$financial,'financial_consult_id'=>$consultId] + $verify;
    });
}

function SchoolSafeEntryIsLate($school, $year, $clock, callable $get)
{
    $first = $get('SELECT START_TIME FROM school_periods WHERE SCHOOL_ID=' . (int) $school
        . ' AND SYEAR=' . (int) $year . ' AND START_TIME IS NOT NULL'
        . ' ORDER BY SORT_ORDER NULLS LAST, START_TIME LIMIT 1');
    if (!is_array($first) || !isset($first[1]['START_TIME'])) return false;
    if (!preg_match('/^([0-9]{1,2}):([0-9]{2})/', $first[1]['START_TIME'], $m)) return false;
    $limit = sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2] + 15);
    if ((int) $m[2] + 15 >= 60) {
        $limit = sprintf('%02d:%02d:00', ((int) $m[1] + 1) % 24, ((int) $m[2] + 15) % 60);
    }
    return strcmp($clock, $limit) > 0;
}
