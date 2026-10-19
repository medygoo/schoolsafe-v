<?php
// SchoolSafe — Lot U1 : horaires et detection automatique des absences.
//
// Conventions natives Rosario : lecture seule des registres officiels
// (student_enrollment, attendance_calendar, calendar_events, attendance_day,
// attendance_period, entry_exit_student_records). L'appel enseignant n'est
// JAMAIS ecrase et aucune absence officielle definitive n'est fabriquee :
// les absences U1 sont 'provisional', resolues en 'resolved_arrived' sans
// suppression d'historique. Les notifications passent par SchoolSafeNotify
// (U0, idempotent, isolation scolaire). Horloge injectee ($nowTs timestamp
// unix, $dateStr 'YYYY-MM-DD') : les scenarios 08h00/09h00/09h30 sont testes
// sans attendre l'heure reelle. Ne modifie ni le relay ni le fil parent.

function SchoolSafeAttendanceValidateDate($dateStr)
{
    if (!is_string($dateStr) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $dateStr)) {
        throw new RuntimeException('INVALID_DATE');
    }
    return $dateStr;
}

function SchoolSafeAttendanceValidateTz($tz)
{
    if (!is_string($tz) || !preg_match('/^[A-Za-z_]{1,32}\/[A-Za-z_]{1,32}$/D', $tz)) {
        throw new RuntimeException('INVALID_TZ');
    }
    return $tz;
}

// Heritage explicite : classe (course_period) > section (gradelevel) > ecole.
// Chaque niveau retient la politique dont effective_on est la plus recente
// <= school_date : aucun changement n'est retroactif.
function SchoolSafeAttendanceResolvePolicy($school, $year, $gradelevelId, $coursePeriodId, $dateStr, array $db)
{
    $school = (int) $school;
    $year = (int) $year;
    if ($school < 1 || $year < 1) throw new RuntimeException('INVALID_CONTEXT');
    $dateStr = SchoolSafeAttendanceValidateDate($dateStr);
    $get = $db['get'];
    $gl = $gradelevelId === null ? null : (int) $gradelevelId;
    $cp = $coursePeriodId === null ? null : (int) $coursePeriodId;
    if ($cp !== null && $cp > 0) {
        $rows = $get('SELECT * FROM schoolsafe_attendance_policy WHERE SCHOOL_ID=' . $school
            . ' AND SYEAR=' . $year . ' AND COURSE_PERIOD_ID=' . $cp
            . " AND EFFECTIVE_ON<='" . $dateStr . "' ORDER BY EFFECTIVE_ON DESC LIMIT 1");
        if (is_array($rows) && isset($rows[1])) { $rows[1]['_level'] = 'class'; return $rows[1]; }
    }
    if ($gl !== null && $gl > 0) {
        $rows = $get('SELECT * FROM schoolsafe_attendance_policy WHERE SCHOOL_ID=' . $school
            . ' AND SYEAR=' . $year . ' AND GRADELEVEL_ID=' . $gl . ' AND COURSE_PERIOD_ID IS NULL'
            . " AND EFFECTIVE_ON<='" . $dateStr . "' ORDER BY EFFECTIVE_ON DESC LIMIT 1");
        if (is_array($rows) && isset($rows[1])) { $rows[1]['_level'] = 'section'; return $rows[1]; }
    }
    $rows = $get('SELECT * FROM schoolsafe_attendance_policy WHERE SCHOOL_ID=' . $school
        . ' AND SYEAR=' . $year . ' AND GRADELEVEL_ID IS NULL AND COURSE_PERIOD_ID IS NULL'
        . " AND EFFECTIVE_ON<='" . $dateStr . "' ORDER BY EFFECTIVE_ON DESC LIMIT 1");
    if (is_array($rows) && isset($rows[1])) { $rows[1]['_level'] = 'school'; return $rows[1]; }
    throw new RuntimeException('NO_POLICY');
}

// Jour de cours : ligne attendance_calendar (minutes NULL ou > 0) ET aucun
// evenement calendar_events ce jour-la (ferie, vacances, exception collective).
function SchoolSafeAttendanceIsSchoolDay($school, $year, $dateStr, array $db)
{
    $school = (int) $school;
    $year = (int) $year;
    $dateStr = SchoolSafeAttendanceValidateDate($dateStr);
    $get = $db['get'];
    $cal = $get('SELECT MINUTES FROM attendance_calendar WHERE SCHOOL_ID=' . $school
        . ' AND SYEAR=' . $year . " AND SCHOOL_DATE='" . $dateStr . "' LIMIT 1");
    if (!is_array($cal) || !isset($cal[1])) return false;
    $minutes = $cal[1]['MINUTES'];
    if ($minutes !== null && (int) $minutes <= 0) return false;
    $ev = $get('SELECT ID FROM calendar_events WHERE SCHOOL_ID=' . $school
        . ' AND SYEAR=' . $year . " AND SCHOOL_DATE='" . $dateStr . "' LIMIT 1");
    if (is_array($ev) && isset($ev[1])) return false;
    return true;
}

// Inscrits actifs (attendus) au jour donne : periode d'inscription couvrante.
function SchoolSafeAttendanceExpectedStudents($school, $year, $dateStr, array $db)
{
    $school = (int) $school;
    $year = (int) $year;
    $dateStr = SchoolSafeAttendanceValidateDate($dateStr);
    $rows = $db['get']('SELECT STUDENT_ID FROM student_enrollment WHERE SCHOOL_ID=' . $school
        . ' AND SYEAR=' . $year . " AND (START_DATE IS NULL OR START_DATE<='" . $dateStr . "')"
        . " AND (END_DATE IS NULL OR END_DATE>='" . $dateStr . "')");
    $ids = [];
    if (is_array($rows)) foreach ($rows as $row) {
        if (isset($row['STUDENT_ID'])) $ids[] = (int) $row['STUDENT_ID'];
    }
    return array_values(array_unique($ids));
}

// Preuve de presence (lecture seule) : scan 'in' valide, appel du jour ou
// presence de periode saisie par l'enseignant. Toute preuve vaut presence.
function SchoolSafeAttendanceHasPresence($studentId, $dateStr, array $db)
{
    $studentId = (int) $studentId;
    $dateStr = SchoolSafeAttendanceValidateDate($dateStr);
    $get = $db['get'];
    $scan = $get('SELECT ID FROM entry_exit_student_records WHERE STUDENT_ID=' . $studentId
        . " AND TYPE='in' AND UNAUTHORIZED IS NULL AND RECORDED_AT::date='" . $dateStr . "' LIMIT 1");
    if (is_array($scan) && isset($scan[1])) return true;
    $day = $get('SELECT STUDENT_ID FROM attendance_day WHERE STUDENT_ID=' . $studentId
        . " AND SCHOOL_DATE='" . $dateStr . "' LIMIT 1");
    if (is_array($day) && isset($day[1])) return true;
    $per = $get('SELECT STUDENT_ID FROM attendance_period WHERE STUDENT_ID=' . $studentId
        . " AND SCHOOL_DATE='" . $dateStr . "' LIMIT 1");
    if (is_array($per) && isset($per[1])) return true;
    return false;
}

function SchoolSafeAttendanceIsExcepted($school, $studentId, $dateStr, array $db)
{
    $rows = $db['get']('SELECT ID FROM schoolsafe_attendance_exceptions WHERE SCHOOL_ID=' . (int) $school
        . ' AND STUDENT_ID=' . (int) $studentId . " AND SCHOOL_DATE='" . SchoolSafeAttendanceValidateDate($dateStr) . "' LIMIT 1");
    return is_array($rows) && isset($rows[1]);
}

// Destinataires serveur cote ecole : enseignants des classes de l'ecole
// (course_periods.teacher_id + secondary) et direction (admins du perimetre).
// Canaux feed+email. Isolation : seuls les staffs du perimetre ecole.
function SchoolSafeAttendanceStaffRecipients($school, $year, array $db)
{
    $school = (int) $school;
    $year = (int) $year;
    $get = $db['get'];
    $recipients = [];
    $teachers = $get('SELECT TEACHER_ID, SECONDARY_TEACHER_ID FROM course_periods'
        . ' WHERE SCHOOL_ID=' . $school . ' AND SYEAR=' . $year);
    if (is_array($teachers)) foreach ($teachers as $row) {
        foreach (['TEACHER_ID', 'SECONDARY_TEACHER_ID'] as $col) {
            $id = isset($row[$col]) ? (int) $row[$col] : 0;
            if ($id > 0) $recipients[] = ['audience' => 'teacher', 'staff_id' => $id];
        }
    }
    $admins = $get("SELECT STAFF_ID, SCHOOLS FROM staff WHERE PROFILE='admin' AND SYEAR=" . $year);
    if (is_array($admins)) foreach ($admins as $row) {
        if (!isset($row['STAFF_ID'])) continue;
        $scope = (string) ($row['SCHOOLS'] ?? '');
        if ($scope === '' || $scope === null) {
            $recipients[] = ['audience' => 'direction', 'staff_id' => (int) $row['STAFF_ID']];
        } elseif (preg_match('/(^|,)(' . $school . ')(,|$)/', trim($scope, ','))) {
            // Ecole listee dans le perimetre '1,2' de l'admin.
            $recipients[] = ['audience' => 'direction', 'staff_id' => (int) $row['STAFF_ID']];
        }
    }
    $seen = [];
    $unique = [];
    foreach ($recipients as $r) {
        foreach (['feed', 'email'] as $channel) {
            $k = $r['audience'] . '|' . $r['staff_id'] . '|' . $channel;
            if (isset($seen[$k])) continue;
            $seen[$k] = true;
            $unique[] = ['audience' => $r['audience'], 'staff_id' => $r['staff_id'], 'channel' => $channel];
        }
    }
    return $unique;
}

function SchoolSafeAttendanceEmitNotify(array $event, array $recipients, array $db)
{
    require_once __DIR__ . '/SchoolSafeNotify.fnc.php';
    return SchoolSafeNotifyEmit($event, $recipients, $db);
}

// Cloture normale (defaut 08h00) : PAS AVANT l'heure. Une seule cloture par
// (ecole, jour, perimetre) : le rejeu relit le passage existant. Enumere
// attendus / arrives / non pointes, sans declarer d'absence definitive.
// Une arrivee tardive ulterieure reste enregistrable (aucun verrou pose).
function SchoolSafeAttendanceClosure($school, $year, $dateStr, $scopeKind, $scopeRef, $nowTime, array $db, $emitNotify = true)
{
    $school = (int) $school;
    $year = (int) $year;
    $dateStr = SchoolSafeAttendanceValidateDate($dateStr);
    if (!in_array($scopeKind, ['school', 'section', 'class'], true)) throw new RuntimeException('INVALID_SCOPE');
    if (!is_string($nowTime) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/D', $nowTime)) throw new RuntimeException('INVALID_TIME');
    $scopeRef = $scopeRef === null ? null : (int) $scopeRef;
    $get = $db['get'];
    $exec = $db['exec'];
    $policy = SchoolSafeAttendanceResolvePolicy($school, $year,
        $scopeKind === 'section' ? $scopeRef : null,
        $scopeKind === 'class' ? $scopeRef : null, $dateStr, $db);
    if (strcmp(substr($nowTime, 0, 5), substr((string) $policy['CLOSURE_TIME'], 0, 5)) < 0) {
        return ['status' => 'TOO_EARLY', 'closure_time' => substr((string) $policy['CLOSURE_TIME'], 0, 5)];
    }
    $scopeSql = $scopeRef === null ? 'NULL' : (string) $scopeRef;
    if (!SchoolSafeAttendanceIsSchoolDay($school, $year, $dateStr, $db)) {
        $exec('BEGIN');
        try {
            $get('INSERT INTO schoolsafe_attendance_runs(school_id, syear, school_date, scope_kind, scope_ref, phase, status, finished_at)'
                . " VALUES($school,$year,'$dateStr','$scopeKind',$scopeSql,'closure','no_school_day',now())"
                . ' ON CONFLICT (school_id, school_date, scope_kind, scope_ref, phase) DO NOTHING');
            $exec('COMMIT');
        } catch (Throwable $e) { try { $exec('ROLLBACK'); } catch (Throwable $i) { } throw $e; }
        return ['status' => 'NO_SCHOOL_DAY'];
    }
    $expected = SchoolSafeAttendanceExpectedStudents($school, $year, $dateStr, $db);
    $arrived = [];
    $pending = [];
    foreach ($expected as $sid) {
        if (SchoolSafeAttendanceHasPresence($sid, $dateStr, $db)) $arrived[] = $sid;
        else $pending[] = $sid;
    }
    $exec('BEGIN');
    try {
        $row = $get('INSERT INTO schoolsafe_attendance_runs(school_id, syear, school_date, scope_kind, scope_ref, phase, status,'
            . ' expected_count, arrived_count, pending_count, finished_at)'
            . " VALUES($school,$year,'$dateStr','$scopeKind',$scopeSql,'closure','done',"
            . count($expected) . ',' . count($arrived) . ',' . count($pending) . ',now())'
            . ' ON CONFLICT (school_id, school_date, scope_kind, scope_ref, phase) DO NOTHING RETURNING ID');
        $created = is_array($row) && isset($row[1]);
        $exec('COMMIT');
    } catch (Throwable $e) { try { $exec('ROLLBACK'); } catch (Throwable $i) { } throw $e; }
    if ($emitNotify && $created) {
        $recipients = SchoolSafeAttendanceStaffRecipients($school, $year, $db);
        SchoolSafeAttendanceEmitNotify(['school_id' => $school, 'syear' => $year,
            'event_type' => 'attendance.closure',
            'dedup_key' => "att-closure:$school:$dateStr:$scopeKind:" . ($scopeRef ?? 0),
            'payload' => ['school_date' => $dateStr, 'scope' => $scopeKind,
                'expected' => count($expected), 'arrived' => count($arrived),
                'pending' => count($pending)]], $recipients, $db);
    }
    return ['status' => $created ? 'CLOSED' : 'ALREADY_CLOSED',
        'expected' => $expected, 'arrived' => $arrived, 'pending' => $pending,
        'expected_count' => count($expected), 'arrived_count' => count($arrived),
        'pending_count' => count($pending)];
}

// Detection automatique (defaut 09h00) : PAS AVANT l'heure, JAMAIS les jours
// sans cours. Elèves sans preuve de presence et sans exception => evenement
// provisoire idempotent + notifications parents/enseignants via U0.
function SchoolSafeAttendanceDetection($school, $year, $dateStr, $scopeKind, $scopeRef, $nowTime, array $db, $emitNotify = true)
{
    $school = (int) $school;
    $year = (int) $year;
    $dateStr = SchoolSafeAttendanceValidateDate($dateStr);
    if (!in_array($scopeKind, ['school', 'section', 'class'], true)) throw new RuntimeException('INVALID_SCOPE');
    if (!is_string($nowTime) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/D', $nowTime)) throw new RuntimeException('INVALID_TIME');
    $scopeRef = $scopeRef === null ? null : (int) $scopeRef;
    $get = $db['get'];
    $exec = $db['exec'];
    $policy = SchoolSafeAttendanceResolvePolicy($school, $year,
        $scopeKind === 'section' ? $scopeRef : null,
        $scopeKind === 'class' ? $scopeRef : null, $dateStr, $db);
    if (strcmp(substr($nowTime, 0, 5), substr((string) $policy['DETECTION_TIME'], 0, 5)) < 0) {
        return ['status' => 'TOO_EARLY', 'detection_time' => substr((string) $policy['DETECTION_TIME'], 0, 5)];
    }
    $scopeSql = $scopeRef === null ? 'NULL' : (string) $scopeRef;
    if (!SchoolSafeAttendanceIsSchoolDay($school, $year, $dateStr, $db)) {
        $exec('BEGIN');
        try {
            $get('INSERT INTO schoolsafe_attendance_runs(school_id, syear, school_date, scope_kind, scope_ref, phase, status, finished_at)'
                . " VALUES($school,$year,'$dateStr','$scopeKind',$scopeSql,'detection','no_school_day',now())"
                . ' ON CONFLICT (school_id, school_date, scope_kind, scope_ref, phase) DO NOTHING');
            $exec('COMMIT');
        } catch (Throwable $e) { try { $exec('ROLLBACK'); } catch (Throwable $i) { } throw $e; }
        return ['status' => 'NO_SCHOOL_DAY', 'absent' => []];
    }
    $expected = SchoolSafeAttendanceExpectedStudents($school, $year, $dateStr, $db);
    $absent = [];
    foreach ($expected as $sid) {
        if (SchoolSafeAttendanceHasPresence($sid, $dateStr, $db)) continue;
        if (SchoolSafeAttendanceIsExcepted($school, $sid, $dateStr, $db)) continue;
        $absent[] = $sid;
    }
    require_once __DIR__ . '/SchoolSafeNotify.fnc.php';
    $notified = 0;
    foreach ($absent as $sid) {
        $exec('BEGIN');
        try {
            $row = $get('INSERT INTO schoolsafe_absence_events(school_id, syear, student_id, school_date)'
                . " VALUES($school,$year,$sid,'$dateStr')"
                . ' ON CONFLICT (school_id, student_id, school_date) DO NOTHING RETURNING ID');
            $exec('COMMIT');
        } catch (Throwable $e) { try { $exec('ROLLBACK'); } catch (Throwable $i) { } throw $e; }
        if ($emitNotify) {
            try {
                $parents = SchoolSafeNotifyResolveRecipients('absence.detected', $school, $year, $sid, $db);
            } catch (Throwable $e) { $parents = []; }
            $staff = SchoolSafeAttendanceStaffRecipients($school, $year, $db);
            $out = SchoolSafeAttendanceEmitNotify(['school_id' => $school, 'syear' => $year,
                'event_type' => 'absence.detected', 'student_id' => $sid,
                'dedup_key' => "absence:$school:$dateStr:$sid",
                'payload' => ['school_date' => $dateStr, 'provisional' => true]], array_merge($parents, $staff), $db);
            if (is_array($out)) {
                $exec('BEGIN');
                try {
                    $escId = $out['event_id'] === null ? 'NULL' : (string) ((int) $out['event_id']);
                    $get('UPDATE schoolsafe_absence_events SET NOTIFY_EVENT_ID=' . $escId
                        . " WHERE SCHOOL_ID=$school AND STUDENT_ID=$sid AND SCHOOL_DATE='$dateStr'");
                    $exec('COMMIT');
                } catch (Throwable $e) { try { $exec('ROLLBACK'); } catch (Throwable $i) { } }
                if ($out['created'] ?? false) $notified++;
            }
        }
    }
    $exec('BEGIN');
    try {
        $row = $get('INSERT INTO schoolsafe_attendance_runs(school_id, syear, school_date, scope_kind, scope_ref, phase, status,'
            . ' expected_count, arrived_count, pending_count, absence_count, finished_at)'
            . " VALUES($school,$year,'$dateStr','$scopeKind',$scopeSql,'detection','done',"
            . count($expected) . ',' . (count($expected) - count($absent)) . ',' . count($absent) . ',' . count($absent) . ',now())'
            . ' ON CONFLICT (school_id, school_date, scope_kind, scope_ref, phase) DO NOTHING RETURNING ID');
        $created = is_array($row) && isset($row[1]);
        $exec('COMMIT');
    } catch (Throwable $e) { try { $exec('ROLLBACK'); } catch (Throwable $i) { } throw $e; }
    return ['status' => $created ? 'DETECTED' : 'ALREADY_DETECTED', 'absent' => $absent,
        'absent_count' => count($absent), 'notified' => $notified];
}

// Arrivee tardive apres detection (ex. 09h30) : l'evenement provisoire est
// CONSERVE, passe en 'resolved_arrived' avec le retard mesure. Aucune preuve
// anterieure n'est supprimee. Retourne late_minutes (90 attendu vs 08h00).
function SchoolSafeAttendanceLateArrival($school, $year, $studentId, $dateStr, array $db, $nowTs = null)
{
    $school = (int) $school;
    $year = (int) $year;
    $studentId = (int) $studentId;
    $dateStr = SchoolSafeAttendanceValidateDate($dateStr);
    $get = $db['get'];
    $exec = $db['exec'];
    $rec = $get('SELECT ID, RECORDED_AT FROM entry_exit_student_records WHERE STUDENT_ID=' . $studentId
        . " AND TYPE='in' AND UNAUTHORIZED IS NULL AND RECORDED_AT::date='$dateStr' ORDER BY RECORDED_AT DESC LIMIT 1");
    if (!is_array($rec) || !isset($rec[1])) return ['status' => 'NO_ARRIVAL'];
    $policy = SchoolSafeAttendanceResolvePolicy($school, $year, null, null, $dateStr, $db);
    $closure = substr((string) $policy['CLOSURE_TIME'], 0, 5);
    [$ch, $cm] = array_map('intval', explode(':', $closure));
    $recordedAt = strtotime((string) $rec[1]['RECORDED_AT']);
    $base = strtotime($dateStr . ' ' . sprintf('%02d:%02d:00', $ch, $cm));
    $lateMinutes = max(0, (int) round(($recordedAt - $base) / 60));
    $nowTs = $nowTs === null ? time() : (int) $nowTs;
    $exec('BEGIN');
    try {
        $row = $get('UPDATE schoolsafe_absence_events SET STATUS=' . "'resolved_arrived'"
            . ', RESOLVED_AT=to_timestamp(' . $nowTs . '), LATE_MINUTES=' . $lateMinutes
            . " WHERE SCHOOL_ID=$school AND STUDENT_ID=$studentId AND SCHOOL_DATE='$dateStr'"
            . " AND STATUS='provisional' RETURNING ID, STATUS");
        $exec('COMMIT');
    } catch (Throwable $e) { try { $exec('ROLLBACK'); } catch (Throwable $i) { } throw $e; }
    if (!is_array($row) || !isset($row[1])) {
        return ['status' => 'NO_ABSENCE', 'late_minutes' => $lateMinutes,
            'record_id' => (int) $rec[1]['ID']];
    }
    return ['status' => 'RESOLVED_ARRIVED', 'late_minutes' => $lateMinutes,
        'record_id' => (int) $rec[1]['ID']];
}
