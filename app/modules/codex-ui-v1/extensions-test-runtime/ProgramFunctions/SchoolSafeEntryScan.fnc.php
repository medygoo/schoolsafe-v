<?php
// SchoolSafe — Lot ENTRY : arrivée sur site (Rosario moteur principal).
//
// Enchaîne, dans une transaction : vérification QR (registre A1) → contrôle du
// point de passage (entry_exit_checkpoints) → anti-doublon (un seul « in » par
// jour) → record d'arrivée (entry_exit_student_records, heure exacte, école via
// l'inscription, personnel en commentaire). Le passage sur site ne renseigne
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
    $day = date('Y-m-d', $now);
    $clock = date('H:i:s', $now);
    $cardQuery = function ($number, $s) use ($db, $year) {
        return SchoolSafeCardVerifyQuery($number, $s, $year, $db['get']);
    };
    $verify = SchoolSafeCardVerifyQr($qr, $secretHex, $school, $year, $cardQuery);
    if ($verify === null) return null;
    if (($verify['result'] ?? '') !== 'VALID') return $verify;
    $get = $db['get'];
    $exec = $db['exec'];
    $cp = $get('SELECT ID, TITLE, TYPE FROM entry_exit_checkpoints WHERE ID=' . $locationId);
    if (!is_array($cp) || !isset($cp[1])) {
        return ['result' => 'DENIED', 'signature_valid' => true, 'reason' => 'LOCATION_DENIED'] + $verify;
    }
    $checkpoint = ['id' => (int) $cp[1]['ID'], 'label' => $cp[1]['TITLE']];
    $exec('BEGIN');
    try {
        $dup = $get('SELECT ID FROM entry_exit_student_records WHERE STUDENT_ID=' . (int) $verify['student_id']
            . " AND TYPE='in' AND UNAUTHORIZED IS NULL AND RECORDED_AT::date='" . $day . "' LIMIT 1");
        if (is_array($dup) && isset($dup[1])) {
            $exec('COMMIT');
            return ['result' => 'ALREADY_PRESENT', 'decision' => 'allowed'] + $verify;
        }
        $type = $cp[1]['TYPE'];
        if ($type !== null && $type !== '' && $type !== 'in') {
            $exec("INSERT INTO entry_exit_student_records(STUDENT_ID, CHECKPOINT_ID, TYPE, UNAUTHORIZED, COMMENTS, RECORDED_AT)"
                . ' VALUES(' . (int) $verify['student_id'] . ',' . $checkpoint['id'] . ",'in','Y',"
                . "'Portail sens unique — staff #" . $user . "',CURRENT_TIMESTAMP)");
            $exec('COMMIT');
            return ['result' => 'DENIED', 'decision' => 'denied', 'reason' => 'LOCATION_DENIED'] + $verify;
        }
        $late = SchoolSafeEntryIsLate($school, $year, $clock, $get);
        $comment = 'Entrée ' . substr($clock, 0, 5) . ($late ? ' (retard)' : '') . ' — staff #' . $user;
        $commentSql = "'" . str_replace("'", "''", $comment) . "'";
        $inserted = $get("INSERT INTO entry_exit_student_records(STUDENT_ID, CHECKPOINT_ID, TYPE, COMMENTS, RECORDED_AT)"
            . ' VALUES(' . (int) $verify['student_id'] . ',' . $checkpoint['id'] . ",'in'," . $commentSql . ',CURRENT_TIMESTAMP)'
            . ' RETURNING ID, RECORDED_AT');
        if (!is_array($inserted) || !isset($inserted[1])) throw new RuntimeException('ENTRY_WRITE_FAILED');
        $exec('COMMIT');
        return ['result' => 'PRESENT', 'decision' => 'allowed', 'late' => $late,
            'record_id' => (int) $inserted[1]['ID'], 'occurred_at' => $inserted[1]['RECORDED_AT'],
            'checkpoint' => $checkpoint] + $verify;
    } catch (Throwable $error) {
        try { $exec('ROLLBACK'); } catch (Throwable $ignored) { }
        throw $error;
    }
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
