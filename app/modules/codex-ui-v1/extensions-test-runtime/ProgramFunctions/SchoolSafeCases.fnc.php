<?php
// SchoolSafe — Lot U2 : dossiers de suivi, signalements et justifications.
//
// Conventions natives Rosario : lecture seule des registres officiels,
// isolation via student_enrollment + students_join_users. Aucune ecriture
// dans attendance_* (la declaration parentale ne fabrique jamais une
// presence). Historique append-only (trigger DB). Notifications via U0.
// Ne modifie ni le relay ni le fil parent ni les moteurs QR/ENTRY/PICKUP.

function SchoolSafeCasesValidateDate($dateStr)
{
    if (!is_string($dateStr) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $dateStr)) {
        throw new RuntimeException('INVALID_DATE');
    }
    return $dateStr;
}

function SchoolSafeCasesIsLinkedParent($parentStaffId, $studentId, array $db)
{
    $rows = $db['get']('SELECT 1 AS OK FROM students_join_users WHERE STUDENT_ID=' . (int) $studentId
        . ' AND STAFF_ID=' . (int) $parentStaffId . ' LIMIT 1');
    return is_array($rows) && isset($rows[1]);
}

function SchoolSafeCasesIsEnrolled($studentId, $school, $year, array $db)
{
    $rows = $db['get']('SELECT 1 AS OK FROM student_enrollment WHERE STUDENT_ID=' . (int) $studentId
        . ' AND SCHOOL_ID=' . (int) $school . ' AND SYEAR=' . (int) $year . ' LIMIT 1');
    return is_array($rows) && isset($rows[1]);
}

// Personnel habilite d'une ecole : admin/teacher dont le perimetre couvre l'ecole
// (SCHOOLS NULL/vide = plateforme). $roles permet d'exiger 'admin' seul.
function SchoolSafeCasesIsSchoolStaff($staffId, $school, $year, array $db, array $roles = ['admin', 'teacher'])
{
    $rows = $db['get']('SELECT PROFILE, SCHOOLS FROM staff WHERE STAFF_ID=' . (int) $staffId
        . ' AND SYEAR=' . (int) $year . ' LIMIT 1');
    if (!is_array($rows) || !isset($rows[1])) return false;
    if (!in_array((string) $rows[1]['PROFILE'], $roles, true)) return false;
    $scope = trim((string) ($rows[1]['SCHOOLS'] ?? ''));
    if ($scope === '') return true;
    return preg_match('/(^|,)(' . (int) $school . ')(,|$)/', trim($scope, ',')) === 1;
}

function SchoolSafeCasesEscapeJson(array $payload)
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || strlen($json) > 8000) throw new RuntimeException('INVALID_PAYLOAD');
    return "'" . str_replace("'", "''", $json) . "'::jsonb";
}

function SchoolSafeCasesOpen($school, $year, $studentId, $dateStr, $openedFrom, array $db, $absenceEventId = null)
{
    $school = (int) $school;
    $year = (int) $year;
    $studentId = (int) $studentId;
    $dateStr = SchoolSafeCasesValidateDate($dateStr);
    if ($school < 1 || $year < 1 || $studentId < 1) throw new RuntimeException('INVALID_CONTEXT');
    if (!in_array($openedFrom, ['detection', 'parent_report', 'teacher', 'conflict'], true)) {
        throw new RuntimeException('INVALID_SOURCE');
    }
    if (!SchoolSafeCasesIsEnrolled($studentId, $school, $year, $db)) throw new RuntimeException('SCHOOL_ISOLATION');
    $absSql = $absenceEventId === null ? 'NULL' : (string) ((int) $absenceEventId);
    $row = $db['get']('INSERT INTO schoolsafe_case_files(school_id, syear, student_id, school_date, opened_from, absence_event_id)'
        . " VALUES($school,$year,$studentId,'$dateStr','$openedFrom',$absSql)"
        . ' ON CONFLICT (school_id, student_id, school_date) DO NOTHING RETURNING ID');
    if (is_array($row) && isset($row[1])) return (int) $row[1]['ID'];
    $existing = $db['get']('SELECT ID FROM schoolsafe_case_files WHERE SCHOOL_ID=' . $school
        . ' AND STUDENT_ID=' . $studentId . " AND SCHOOL_DATE='$dateStr' LIMIT 1");
    if (!is_array($existing) || !isset($existing[1])) throw new RuntimeException('CASE_WRITE_FAILED');
    return (int) $existing[1]['ID'];
}

function SchoolSafeCasesAddEvent($fileId, $kind, $actorStaffId, array $payload, array $db, $confidentiality = 'normal')
{
    $allowed = ['absence_detected', 'late_arrival', 'parent_report', 'justification',
        'decision', 'document', 'teacher_note', 'conflict', 'arrival_verified'];
    if (!in_array($kind, $allowed, true)) throw new RuntimeException('INVALID_KIND');
    if (!in_array($confidentiality, ['normal', 'restricted_medical'], true)) throw new RuntimeException('INVALID_CONF');
    $actorSql = empty($actorStaffId) ? 'NULL' : (string) ((int) $actorStaffId);
    $confSql = "'" . $confidentiality . "'";
    $payloadSql = SchoolSafeCasesEscapeJson($payload);
    $row = $db['get']('INSERT INTO schoolsafe_case_events(file_id, seq, kind, actor_staff_id, payload, confidentiality)'
        . ' SELECT ' . (int) $fileId . ", COALESCE(MAX(seq),0)+1, '$kind', $actorSql, $payloadSql, $confSql"
        . ' FROM schoolsafe_case_events WHERE FILE_ID=' . (int) $fileId
        . ' RETURNING ID, SEQ');
    if (!is_array($row) || !isset($row[1])) throw new RuntimeException('CASE_WRITE_FAILED');
    return ['id' => (int) $row[1]['ID'], 'seq' => (int) $row[1]['SEQ']];
}

function SchoolSafeCasesEmit(array $event, array $recipients, array $db)
{
    require_once __DIR__ . '/SchoolSafeNotify.fnc.php';
    return SchoolSafeNotifyEmit($event, $recipients, $db);
}

function SchoolSafeCasesFileSchool($fileId, array $db)
{
    $rows = $db['get']('SELECT ID, SCHOOL_ID, SYEAR, STUDENT_ID, SCHOOL_DATE, STATUS FROM schoolsafe_case_files'
        . ' WHERE ID=' . (int) $fileId . ' LIMIT 1');
    if (!is_array($rows) || !isset($rows[1])) throw new RuntimeException('CASE_NOT_FOUND');
    return $rows[1];
}

// Signalement parental (un ou plusieurs jours, max 14) : n'ecrit JAMAIS de presence.
function SchoolSafeCasesReport($parentStaffId, $studentId, $school, $year, $dateFrom, $dateTo, $message, $motive, array $db)
{
    $parentStaffId = (int) $parentStaffId;
    $studentId = (int) $studentId;
    $dateFrom = SchoolSafeCasesValidateDate($dateFrom);
    $dateTo = SchoolSafeCasesValidateDate($dateTo === '' ? $dateFrom : $dateTo);
    if (!in_array($motive, ['medical', 'family', 'transport', 'other'], true)) throw new RuntimeException('INVALID_MOTIVE');
    if (!is_string($message) || strlen($message) > 2000) throw new RuntimeException('INVALID_MESSAGE');
    if (!SchoolSafeCasesIsLinkedParent($parentStaffId, $studentId, $db)) throw new RuntimeException('PARENT_SCOPE');
    if (!SchoolSafeCasesIsEnrolled($studentId, $school, $year, $db)) throw new RuntimeException('SCHOOL_ISOLATION');
    $d0 = new DateTime($dateFrom);
    $d1 = new DateTime($dateTo);
    if ($d1 < $d0 || (int) $d0->diff($d1)->format('%a') > 13) throw new RuntimeException('INVALID_RANGE');
    require_once __DIR__ . '/SchoolSafeAttendance.fnc.php';
    $fileIds = [];
    $period = new DatePeriod($d0, new DateInterval('P1D'), (clone $d1)->modify('+1 day'));
    foreach ($period as $day) {
        $date = $day->format('Y-m-d');
        $fileId = SchoolSafeCasesOpen($school, $year, $studentId, $date, 'parent_report', $db);
        $conf = $motive === 'medical' ? 'restricted_medical' : 'normal';
        SchoolSafeCasesAddEvent($fileId, 'parent_report', $parentStaffId,
            ['message' => $message, 'motive' => $motive, 'from' => $dateFrom, 'to' => $dateTo], $db, $conf);
        $db['exec']('UPDATE schoolsafe_case_files SET STATUS=' . "'pending_decision'"
            . ', UPDATED_AT=now() WHERE ID=' . $fileId);
        $staff = SchoolSafeAttendanceStaffRecipients($school, $year, $db);
        SchoolSafeCasesEmit(['school_id' => (int) $school, 'syear' => (int) $year,
            'event_type' => 'case.updated', 'student_id' => $studentId,
            'dedup_key' => "case:$fileId:report",
            'payload' => ['file_id' => $fileId, 'school_date' => $date, 'action' => 'parent_report']],
            $staff, $db);
        $fileIds[] = $fileId;
    }
    return $fileIds;
}

// Justification deposee par le parent lie (version conservee, statut pending).
function SchoolSafeCasesJustify($parentStaffId, $fileId, $eventKind, $motive, $text, $sensitive, $ref, $sha, array $db)
{
    $file = SchoolSafeCasesFileSchool($fileId, $db);
    if (!SchoolSafeCasesIsLinkedParent($parentStaffId, (int) $file['STUDENT_ID'], $db)) {
        throw new RuntimeException('PARENT_SCOPE');
    }
    if (!in_array($motive, ['medical', 'family', 'transport', 'other'], true)) throw new RuntimeException('INVALID_MOTIVE');
    if (!is_string($text) || $text === '' || strlen($text) > 2000) throw new RuntimeException('INVALID_MESSAGE');
    if (!is_string($ref) || strlen($ref) > 500) throw new RuntimeException('INVALID_REF');
    if ($sha !== '' && !preg_match('/^[0-9a-f]{64}$/D', $sha)) throw new RuntimeException('INVALID_REF');
    $ver = $db['get']('SELECT COALESCE(MAX(VERSION),0)+1 AS V FROM schoolsafe_justifications'
        . ' WHERE FILE_ID=' . (int) $fileId . " AND EVENT_KIND='" . str_replace("'", "''", $eventKind) . "'");
    $version = (is_array($ver) && isset($ver[1])) ? (int) $ver[1]['V'] : 1;
    $conf = ($sensitive || $motive === 'medical') ? 'restricted_medical' : 'normal';
    $refSql = "'" . str_replace("'", "''", $ref) . "'";
    $shaSql = "'" . str_replace("'", "''", $sha) . "'";
    $textSql = "'" . str_replace("'", "''", $text) . "'";
    $row = $db['get']('INSERT INTO schoolsafe_justifications(file_id, event_kind, version, submitted_by, motive,'
        . ' motive_text, sensitive, attachment_ref, attachment_sha256)'
        . ' VALUES(' . (int) $fileId . ",'" . str_replace("'", "''", $eventKind) . "',$version,"
        . (int) $parentStaffId . ",'$motive',$textSql," . ($sensitive ? 'true' : 'false') . ",$refSql,$shaSql)"
        . ' RETURNING ID');
    if (!is_array($row) || !isset($row[1])) throw new RuntimeException('CASE_WRITE_FAILED');
    $justifId = (int) $row[1]['ID'];
    SchoolSafeCasesAddEvent($fileId, 'justification', $parentStaffId,
        ['justification_id' => $justifId, 'motive' => $motive,
            'motive_text' => $conf === 'restricted_medical' ? '[confidentiel]' : $text,
            'version' => $version], $db, $conf);
    $db['exec']('UPDATE schoolsafe_case_files SET STATUS=' . "'pending_decision'"
        . ', UPDATED_AT=now() WHERE ID=' . (int) $fileId);
    return $justifId;
}

// Decision d'un responsable autorise (admin ou enseignant du perimetre).
function SchoolSafeCasesDecide($deciderStaffId, $justifId, $accept, array $db, $note = '')
{
    $rows = $db['get']('SELECT j.ID, j.FILE_ID, j.STATUS, f.SCHOOL_ID, f.SYEAR FROM schoolsafe_justifications j'
        . ' JOIN schoolsafe_case_files f ON f.ID=j.FILE_ID WHERE j.ID=' . (int) $justifId . ' LIMIT 1');
    if (!is_array($rows) || !isset($rows[1])) throw new RuntimeException('JUSTIF_NOT_FOUND');
    $j = $rows[1];
    if ($j['STATUS'] !== 'pending') throw new RuntimeException('JUSTIF_DECIDED');
    if (!SchoolSafeCasesIsSchoolStaff($deciderStaffId, (int) $j['SCHOOL_ID'], (int) $j['SYEAR'], $db)) {
        throw new RuntimeException('DECIDER_SCOPE');
    }
    if (!is_string($note) || strlen($note) > 1000) throw new RuntimeException('INVALID_MESSAGE');
    $status = $accept ? 'accepted' : 'refused';
    $fileStatus = $accept ? 'justified' : 'unjustified';
    $noteSql = "'" . str_replace("'", "''", $note) . "'";
    $db['exec']('BEGIN');
    try {
        $db['get']('UPDATE schoolsafe_justifications SET STATUS=' . "'$status'"
            . ', DECIDED_BY=' . (int) $deciderStaffId . ', DECIDED_AT=now(), DECISION_NOTE=' . $noteSql
            . ' WHERE ID=' . (int) $justifId);
        $db['exec']('UPDATE schoolsafe_case_files SET STATUS=' . "'$fileStatus'"
            . ', UPDATED_AT=now() WHERE ID=' . (int) $j['FILE_ID']);
        $db['exec']('COMMIT');
    } catch (Throwable $e) { try { $db['exec']('ROLLBACK'); } catch (Throwable $i) { } throw $e; }
    SchoolSafeCasesAddEvent((int) $j['FILE_ID'], 'decision', $deciderStaffId,
        ['justification_id' => (int) $justifId, 'decision' => $status, 'note' => $note], $db);
    return $status;
}

// Conflit avec l'appel enseignant : dossier de verification + enseignant notifie.
function SchoolSafeCasesFlagConflict($teacherStaffId, $fileId, $note, array $db)
{
    $file = SchoolSafeCasesFileSchool($fileId, $db);
    if (!SchoolSafeCasesIsSchoolStaff($teacherStaffId, (int) $file['SCHOOL_ID'], (int) $file['SYEAR'], $db)) {
        throw new RuntimeException('DECIDER_SCOPE');
    }
    if (!is_string($note) || $note === '' || strlen($note) > 1000) throw new RuntimeException('INVALID_MESSAGE');
    SchoolSafeCasesAddEvent($fileId, 'conflict', $teacherStaffId, ['note' => $note], $db);
    $db['exec']('UPDATE schoolsafe_case_files SET STATUS=' . "'under_review'"
        . ', UPDATED_AT=now() WHERE ID=' . (int) $fileId);
    require_once __DIR__ . '/SchoolSafeAttendance.fnc.php';
    $staff = SchoolSafeAttendanceStaffRecipients((int) $file['SCHOOL_ID'], (int) $file['SYEAR'], $db);
    SchoolSafeCasesEmit(['school_id' => (int) $file['SCHOOL_ID'], 'syear' => (int) $file['SYEAR'],
        'event_type' => 'case.updated', 'student_id' => (int) $file['STUDENT_ID'],
        'dedup_key' => "case:$fileId:conflict",
        'payload' => ['file_id' => (int) $fileId, 'action' => 'conflict']], $staff, $db);
    return true;
}

// Arrivee reelle verifiee depuis les preuves de scan (lecture seule).
function SchoolSafeCasesVerifyArrival($school, $year, $studentId, $dateStr, array $db)
{
    require_once __DIR__ . '/SchoolSafeAttendance.fnc.php';
    return SchoolSafeAttendanceHasPresence($studentId, $dateStr, $db);
}

// Vue selon le role : parents lies et direction = integral ; enseignants =
// motif medical redige ; tout autre = DENIED. Gardiens : jamais de medical.
function SchoolSafeCasesView($viewerStaffId, $fileId, array $db)
{
    $file = SchoolSafeCasesFileSchool($fileId, $db);
    $school = (int) $file['SCHOOL_ID'];
    $year = (int) $file['SYEAR'];
    $student = (int) $file['STUDENT_ID'];
    $isParent = SchoolSafeCasesIsLinkedParent($viewerStaffId, $student, $db);
    $isDirection = SchoolSafeCasesIsSchoolStaff($viewerStaffId, $school, $year, $db, ['admin']);
    $isTeacher = !$isDirection && SchoolSafeCasesIsSchoolStaff($viewerStaffId, $school, $year, $db, ['teacher']);
    if (!$isParent && !$isDirection && !$isTeacher) throw new RuntimeException('VIEW_DENIED');
    $redact = !$isParent && !$isDirection;
    $events = $db['get']('SELECT ID, SEQ, KIND, ACTOR_STAFF_ID, PAYLOAD, CONFIDENTIALITY, CREATED_AT'
        . ' FROM schoolsafe_case_events WHERE FILE_ID=' . (int) $fileId . ' ORDER BY SEQ');
    $items = [];
    if (is_array($events)) foreach ($events as $ev) {
        if (!isset($ev['ID'])) continue;
        $payload = is_string($ev['PAYLOAD'] ?? null) ? json_decode($ev['PAYLOAD'], true) : $ev['PAYLOAD'];
        if (!is_array($payload)) $payload = [];
        if ($redact && ($ev['CONFIDENTIALITY'] ?? '') === 'restricted_medical') {
            $payload = ['redacted' => true, 'label' => 'Motif medical confidentiel'];
        }
        $items[] = ['seq' => (int) $ev['SEQ'], 'kind' => (string) $ev['KIND'],
            'created_at' => (string) ($ev['CREATED_AT'] ?? ''), 'payload' => $payload];
    }
    $justifs = $db['get']('SELECT ID, EVENT_KIND, VERSION, MOTIVE, MOTIVE_TEXT, SENSITIVE, STATUS, CREATED_AT'
        . ' FROM schoolsafe_justifications WHERE FILE_ID=' . (int) $fileId . ' ORDER BY VERSION');
    $jlist = [];
    if (is_array($justifs)) foreach ($justifs as $just) {
        if (!isset($just['ID'])) continue;
        $text = (string) ($just['MOTIVE_TEXT'] ?? '');
        if ($redact && ((string) ($just['MOTIVE'] ?? '') === 'medical' || ($just['SENSITIVE'] ?? '') === 't')) {
            $text = '[confidentiel]';
        }
        $jlist[] = ['id' => (int) $just['ID'], 'motive' => (string) ($just['MOTIVE'] ?? ''),
            'motive_text' => $text, 'status' => (string) ($just['STATUS'] ?? ''),
            'version' => (int) ($just['VERSION'] ?? 1)];
    }
    return ['file' => ['id' => (int) $file['ID'], 'status' => (string) $file['STATUS'],
        'school_date' => (string) $file['SCHOOL_DATE'], 'student_id' => (string) $student],
        'events' => $items, 'justifications' => $jlist,
        'role' => $isParent ? 'parent' : ($isDirection ? 'direction' : 'teacher')];
}
