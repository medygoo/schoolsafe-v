<?php
// SchoolSafe — Lot U5 : ponctualite (statistiques + suivis, sans sanction).
//
// - Les retards resolus viennent de schoolsafe_absence_events
//   (status resolved_arrived + late_minutes, historique U1/U2 conserve).
// - Justifie = justification 'accepted' sur le dossier du meme jour (U2).
// - Seuils configurables par ecole/annee (029) ; depassement => suivi
//   notifie a la direction (school.announcement, idempotent).
// - AUCUNE sanction automatique : cette couche ne fait que compter et alerter.
require_once __DIR__ . '/SchoolSafeNotify.fnc.php';

function SchoolSafePunctualityDefaults()
{
    return ['max_unexcused_lates' => 3, 'max_late_minutes' => 120,
        'window_days' => 30, 'notify_direction' => true];
}

function SchoolSafePunctualityThresholds($school, $year, array $db)
{
    $rows = $db['get']('SELECT MAX_UNEXCUSED_LATES, MAX_LATE_MINUTES, WINDOW_DAYS, NOTIFY_DIRECTION'
        . ' FROM schoolsafe_punctuality_thresholds WHERE SCHOOL_ID=' . (int) $school
        . ' AND SYEAR=' . (int) $year . ' LIMIT 1');
    if (!is_array($rows) || !isset($rows[1])) return SchoolSafePunctualityDefaults();
    return ['max_unexcused_lates' => (int) $rows[1]['MAX_UNEXCUSED_LATES'],
        'max_late_minutes' => (int) $rows[1]['MAX_LATE_MINUTES'],
        'window_days' => (int) $rows[1]['WINDOW_DAYS'],
        'notify_direction' => (string) ($rows[1]['NOTIFY_DIRECTION'] ?? 't') !== 'f'];
}

function SchoolSafePunctualitySetThresholds($school, $year, array $vals, array $db)
{
    $school = (int) $school;
    $year = (int) $year;
    if ($school < 1 || $year < 1) throw new RuntimeException('INVALID_CONTEXT');
    $maxL = isset($vals['max_unexcused_lates']) ? (int) $vals['max_unexcused_lates'] : 3;
    $maxM = isset($vals['max_late_minutes']) ? (int) $vals['max_late_minutes'] : 120;
    $win = isset($vals['window_days']) ? (int) $vals['window_days'] : 30;
    if ($maxL < 0 || $maxM < 0 || $win < 1) throw new RuntimeException('INVALID_THRESHOLD');
    $notify = empty($vals['notify_direction']) ? 'false' : 'true';
    $db['get']('INSERT INTO schoolsafe_punctuality_thresholds'
        . '(school_id, syear, max_unexcused_lates, max_late_minutes, window_days, notify_direction)'
        . " VALUES($school,$year,$maxL,$maxM,$win,$notify)"
        . ' ON CONFLICT (school_id, syear) DO UPDATE SET'
        . " max_unexcused_lates=EXCLUDED.max_unexcused_lates,"
        . ' max_late_minutes=EXCLUDED.max_late_minutes,'
        . ' window_days=EXCLUDED.window_days,'
        . ' notify_direction=EXCLUDED.notify_direction, updated_at=now()');
    return SchoolSafePunctualityThresholds($school, $year, $db);
}

function SchoolSafePunctualityValidateRange($from, $to)
{
    if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D', (string) $from)
        || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D', (string) $to)) {
        throw new RuntimeException('INVALID_DATE');
    }
    if (strcmp((string) $from, (string) $to) > 0) throw new RuntimeException('INVALID_RANGE');
    return [(string) $from, (string) $to];
}

// Statistiques d'un eleve sur une periode.
function SchoolSafePunctualityStudent($school, $year, $studentId, $from, $to, array $db)
{
    list($from, $to) = SchoolSafePunctualityValidateRange($from, $to);
    $rows = $db['get']('SELECT ae.SCHOOL_DATE, ae.LATE_MINUTES,'
        . ' CASE WHEN EXISTS (SELECT 1 FROM schoolsafe_case_files cf'
        . ' JOIN schoolsafe_justifications j ON j.FILE_ID=cf.ID AND j.STATUS=' . "'accepted'"
        . ' WHERE cf.SCHOOL_ID=ae.SCHOOL_ID AND cf.STUDENT_ID=ae.STUDENT_ID'
        . ' AND cf.SCHOOL_DATE=ae.SCHOOL_DATE) THEN 1 ELSE 0 END AS JUSTIFIED'
        . ' FROM schoolsafe_absence_events ae WHERE ae.SCHOOL_ID=' . (int) $school
        . ' AND ae.SYEAR=' . (int) $year . ' AND ae.STUDENT_ID=' . (int) $studentId
        . " AND ae.STATUS='resolved_arrived' AND ae.SCHOOL_DATE BETWEEN '$from' AND '$to'"
        . ' ORDER BY ae.SCHOOL_DATE');
    $total = 0; $minutes = 0; $just = 0;
    if (is_array($rows)) foreach ($rows as $r) {
        if (!isset($r['SCHOOL_DATE'])) continue;
        $total++;
        $minutes += (int) ($r['LATE_MINUTES'] ?? 0);
        if ((int) ($r['JUSTIFIED'] ?? 0) === 1) $just++;
    }
    $th = SchoolSafePunctualityThresholds($school, $year, $db);
    return ['student_id' => (int) $studentId, 'total_lates' => $total,
        'justified_lates' => $just, 'unjustified_lates' => $total - $just,
        'total_minutes' => $minutes,
        'repeated_unexcused' => ($total - $just) >= $th['max_unexcused_lates'] && $total > 0,
        'repeated_minutes' => $minutes >= $th['max_late_minutes'] && $total > 0];
}

// Rapport par perimetre : school | class (course_period_id) | section (grade_id).
function SchoolSafePunctualityReport($school, $year, $from, $to, array $scope, array $db)
{
    list($from, $to) = SchoolSafePunctualityValidateRange($from, $to);
    $kind = (string) ($scope['kind'] ?? 'school');
    $ref = (int) ($scope['ref'] ?? 0);
    if ($kind === 'class' && $ref > 0) {
        $q = 'SELECT DISTINCT sc.STUDENT_ID FROM schedule sc'
            . ' JOIN course_periods cp ON cp.COURSE_PERIOD_ID=sc.COURSE_PERIOD_ID'
            . ' WHERE sc.SCHOOL_ID=' . (int) $school . ' AND sc.SYEAR=' . (int) $year
            . ' AND sc.COURSE_PERIOD_ID=' . $ref;
    } elseif ($kind === 'section' && $ref > 0) {
        $q = 'SELECT STUDENT_ID FROM student_enrollment WHERE SCHOOL_ID=' . (int) $school
            . ' AND SYEAR=' . (int) $year . ' AND GRADE_ID=' . $ref;
    } elseif ($kind === 'school') {
        $q = 'SELECT STUDENT_ID FROM student_enrollment WHERE SCHOOL_ID=' . (int) $school
            . ' AND SYEAR=' . (int) $year;
    } else {
        throw new RuntimeException('INVALID_SCOPE');
    }
    $rows = $db['get']($q);
    $out = [];
    if (is_array($rows)) foreach ($rows as $r) {
        if (!isset($r['STUDENT_ID'])) continue;
        $out[] = SchoolSafePunctualityStudent($school, $year, (int) $r['STUDENT_ID'], $from, $to, $db);
    }
    return $out;
}

// Suivi direction : emet school.announcement (kind punctuality.followup) pour
// chaque eleve depassant les seuils sur la fenetre glissante. Idempotent.
function SchoolSafePunctualityFollowup($school, $year, $periodKey, array $db)
{
    $school = (int) $school;
    $year = (int) $year;
    if ($school < 1 || $year < 1) throw new RuntimeException('INVALID_CONTEXT');
    if (!preg_match('/^[A-Za-z0-9_-]{1,40}$/D', (string) $periodKey)) throw new RuntimeException('INVALID_PERIOD');
    $th = SchoolSafePunctualityThresholds($school, $year, $db);
    if (!$th['notify_direction']) return ['followed' => 0, 'disabled' => true];
    $to = date('Y-m-d');
    $from = date('Y-m-d', strtotime('-' . $th['window_days'] . ' days'));
    $report = SchoolSafePunctualityReport($school, $year, $from, $to, ['kind' => 'school'], $db);
    $admins = $db['get']("SELECT STAFF_ID FROM staff WHERE PROFILE='admin' AND SYEAR=$year");
    $dirIds = [];
    if (is_array($admins)) foreach ($admins as $a) {
        if (isset($a['STAFF_ID'])) $dirIds[] = (int) $a['STAFF_ID'];
    }
    if (!$dirIds) return ['followed' => 0, 'no_direction' => true];
    $followed = 0;
    foreach ($report as $row) {
        if (!$row['repeated_unexcused'] && !$row['repeated_minutes']) continue;
        $recips = [];
        foreach ($dirIds as $did) {
            $recips[] = ['audience' => 'direction', 'staff_id' => $did, 'channel' => 'feed'];
            $recips[] = ['audience' => 'direction', 'staff_id' => $did, 'channel' => 'email'];
        }
        $out = SchoolSafeNotifyEmit(['school_id' => $school, 'syear' => $year,
            'event_type' => 'school.announcement', 'student_id' => $row['student_id'],
            'dedup_key' => "punctuality:$school:$year:" . $row['student_id'] . ":$periodKey",
            'payload' => ['kind' => 'punctuality.followup',
                'unjustified' => $row['unjustified_lates'],
                'minutes' => $row['total_minutes']]], $recips, $db);
        if (is_array($out) && !empty($out['created'])) $followed++;
    }
    return ['followed' => $followed];
}
