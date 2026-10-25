<?php
// SchoolSafe — Lot U4 : alertes de securite P1-P4, prise en charge humaine.
//chemin:
// - Notification IMMEDIATE individuelle par alerte (P1 a P4), jamais de
//   regroupement ni de resume quotidien qui remplacerait l'alerte.
// - L'alerte reste OUVERTE jusqu'a cloture humaine autorisee : ni la lecture,
//   ni les relances, ni l'escalade, ni un redemarrage ne la cloture.
// - Lire n'est pas prendre en charge : seul l'accuse nominatif (ack) vaut
//   prise en charge, reserve aux destinataires habilites.
// - Destinataires habilites : direction de l'ecole + enseignants rattaches.
//   Parents, gardiens, central et autres ecoles : refuses.
require_once __DIR__ . '/SchoolSafeNotify.fnc.php';
require_once __DIR__ . '/SchoolSafeStudio.fnc.php';

function SchoolSafeP1Delays()
{
    return ['P1' => 5, 'P2' => 15, 'P3' => 60, 'P4' => 240];
}

function SchoolSafeP1ValidateText($text, $max)
{
    if (!is_string($text) || $text === '' || strlen($text) > $max) {
        throw new RuntimeException('INVALID_TEXT');
    }
    return $text;
}

// Levee d'alerte : transaction alerte + destinataires + notification U0.
// Idempotent par (school_id, dedup_key) : rejeu => created=false, 0 doublon.
function SchoolSafeP1Raise($school, $year, $kind, $severity, $title, $body,
    $createdBy, array $recipientStaffIds, array $db, $studentId = 0, $dedup = '')
{
    $school = (int) $school;
    $year = (int) $year;
    if ($school < 1 || $year < 1) throw new RuntimeException('INVALID_CONTEXT');
    if (!in_array($kind, ['security', 'health', 'intrusion', 'fire', 'other'], true)) {
        throw new RuntimeException('INVALID_KIND');
    }
    if (!isset(SchoolSafeP1Delays()[$severity])) throw new RuntimeException('INVALID_SEVERITY');
    SchoolSafeP1ValidateText($title, 150);
    if (!is_string($body) || strlen($body) > 2000) throw new RuntimeException('INVALID_TEXT');
    $recipients = array_values(array_unique(array_map('intval', $recipientStaffIds)));
    if (!$recipients) throw new RuntimeException('NO_RECIPIENT');
    // Habilitation : direction de l'ecole ou enseignant rattache. Parents,
    // gardiens, central et autres ecoles : refuses. Note : un enseignant sans
    // classe cette annee mais au perimetre explicite reste habilite aux
    // alertes (le role Studio 'gardien' ne s'applique pas ici).
    foreach ($recipients as $rid) {
        $srow = $db['get']('SELECT PROFILE, SCHOOLS FROM staff WHERE STAFF_ID=' . $rid
            . ' AND SYEAR=' . $year . ' LIMIT 1');
        if (!is_array($srow) || !isset($srow[1])) throw new RuntimeException('RECIPIENT_DENIED');
        $raw = (string) $srow[1]['PROFILE'];
        $scope = trim((string) ($srow[1]['SCHOOLS'] ?? ''));
        if ($raw === 'admin') $prof = ['role' => 'direction', 'scope' => $scope];
        elseif ($raw === 'teacher') $prof = ['role' => 'teacher', 'scope' => $scope];
        else throw new RuntimeException('RECIPIENT_DENIED');
        if (!SchoolSafeStudioAttached($prof, $rid, $school, $year, $db)) {
            throw new RuntimeException('RECIPIENT_DENIED');
        }
    }
    // Eleve cite : inscrit dans l'ecole, sinon isolation.
    $studentId = (int) $studentId;
    if ($studentId > 0) {
        $enr = $db['get']('SELECT 1 AS OK FROM student_enrollment WHERE STUDENT_ID=' . $studentId
            . ' AND SCHOOL_ID=' . $school . ' AND SYEAR=' . $year . ' LIMIT 1');
        if (!is_array($enr) || !isset($enr[1])) throw new RuntimeException('SCHOOL_ISOLATION');
    }
    if ($dedup === '') $dedup = 'p1:' . md5($school . '|' . $year . '|' . $title . '|' . $body . '|' . microtime(true));
    if (!preg_match('/^[A-Za-z0-9:_-]{1,200}$/D', $dedup)) throw new RuntimeException('INVALID_DEDUP_KEY');
    $get = $db['get'];
    $exec = $db['exec'];
    $reg = $get("SELECT to_regclass('public.schoolsafe_p1_alerts') AS t");
    $found = '';
    if (is_array($reg)) foreach ($reg as $rr) { $found = (string) ($rr['T'] ?? ''); break; }
    if ($found === '') return null; // socle indisponible : repli documente
    $titleSql = "'" . str_replace("'", "''", $title) . "'";
    $bodySql = "'" . str_replace("'", "''", $body) . "'";
    $dedupSql = "'" . str_replace("'", "''", $dedup) . "'";
    $bySql = ((int) $createdBy) > 0 ? (string) (int) $createdBy : 'NULL';
    $stuSql = $studentId > 0 ? (string) $studentId : 'NULL';
    $exec('BEGIN');
    try {
        $row = $get('INSERT INTO schoolsafe_p1_alerts(school_id, syear, kind, severity, title, body,'
            . ' student_id, dedup_key, created_by)'
            . " VALUES($school,$year,'$kind','$severity',$titleSql,$bodySql,$stuSql,$dedupSql,$bySql)"
            . ' ON CONFLICT (school_id, dedup_key) DO NOTHING RETURNING ID');
        if (!is_array($row) || !isset($row[1])) {
            $exec('ROLLBACK');
            $existing = $get('SELECT ID FROM schoolsafe_p1_alerts WHERE SCHOOL_ID=' . $school
                . ' AND DEDUP_KEY=' . $dedupSql . ' LIMIT 1');
            $eid = (is_array($existing) && isset($existing[1])) ? (int) $existing[1]['ID'] : 0;
            return ['alert_id' => $eid, 'created' => false, 'notified' => 0];
        }
        $alertId = (int) $row[1]['ID'];
        foreach ($recipients as $rid) {
            $get('INSERT INTO schoolsafe_p1_recipients(alert_id, staff_id)'
                . " VALUES($alertId,$rid) ON CONFLICT (alert_id, staff_id) DO NOTHING");
        }
        $exec('COMMIT');
    } catch (Throwable $e) { try { $exec('ROLLBACK'); } catch (Throwable $i) { } throw $e; }
    // Notification immediate individuelle (P1-P4, sans regroupement).
    $notify = [];
    foreach ($recipients as $rid) {
        $notify[] = ['audience' => 'staff', 'staff_id' => $rid, 'channel' => 'feed'];
        $notify[] = ['audience' => 'staff', 'staff_id' => $rid, 'channel' => 'email'];
        $notify[] = ['audience' => 'staff', 'staff_id' => $rid, 'channel' => 'push'];
    }
    SchoolSafeNotifyEmit(['school_id' => $school, 'syear' => $year,
        'event_type' => 'security.p1', 'dedup_key' => 'p1:' . $alertId,
        'student_id' => $studentId ?: null, 'actor_staff_id' => ((int) $createdBy) ?: null,
        'payload' => ['kind' => 'security_p1', 'alert_id' => $alertId,
            'severity' => $severity, 'title' => $title]], $notify, $db);
    return ['alert_id' => $alertId, 'created' => true, 'notified' => count($recipients)];
}

// Accuse nominatif de prise en charge : destinataire habilite uniquement.
// Idempotent : deja pris en charge => acked=false, sans erreur ni doublon.
function SchoolSafeP1Ack($alertId, $staffId, array $db)
{
    $alertId = (int) $alertId;
    $staffId = (int) $staffId;
    if ($alertId < 1 || $staffId < 1) throw new RuntimeException('INVALID_CONTEXT');
    $get = $db['get'];
    $exec = $db['exec'];
    $rows = $get('SELECT a.STATUS, a.ACK_BY, a.ACK_AT, r.STAFF_ID, r.ACK_AT AS R_ACK_AT'
        . ' FROM schoolsafe_p1_alerts a'
        . ' LEFT JOIN schoolsafe_p1_recipients r ON r.ALERT_ID=a.ID AND r.STAFF_ID=' . $staffId
        . ' WHERE a.ID=' . $alertId . ' LIMIT 1');
    if (!is_array($rows) || !isset($rows[1])) throw new RuntimeException('UNKNOWN_ALERT');
    if (($rows[1]['STATUS'] ?? '') === 'closed') throw new RuntimeException('ALERT_CLOSED');
    if (!isset($rows[1]['STAFF_ID'])) throw new RuntimeException('ACK_DENIED');
    $alertAcked = $rows[1]['ACK_AT'] !== null && (string) $rows[1]['ACK_AT'] !== '';
    if ($alertAcked) {
        // Prise en charge deja attribuee (premier accuse, jamais ecrase) :
        // on trace l'accuse du destinataire, sans doublon d'attribution.
        $get('UPDATE schoolsafe_p1_recipients SET ACK_AT=now()'
            . " WHERE ALERT_ID=$alertId AND STAFF_ID=$staffId AND ACK_AT IS NULL");
        return ['acked' => false];
    }
    $exec('BEGIN');
    try {
        $get('UPDATE schoolsafe_p1_recipients SET ACK_AT=now()'
            . " WHERE ALERT_ID=$alertId AND STAFF_ID=$staffId AND ACK_AT IS NULL");
        $get('UPDATE schoolsafe_p1_alerts SET ACK_BY=' . $staffId . ', ACK_AT=now()'
            . " WHERE ID=$alertId AND ACK_AT IS NULL");
        $exec('COMMIT');
    } catch (Throwable $e) { try { $exec('ROLLBACK'); } catch (Throwable $i) { } throw $e; }
    return ['acked' => true];
}

// Cloture humaine autorisee : direction de l'ecole ou createur. Rien d'autre
// (ni lecture, ni relance, ni escalade, ni script) ne cloture.
function SchoolSafeP1Close($alertId, $staffId, $note, array $db)
{
    $alertId = (int) $alertId;
    $staffId = (int) $staffId;
    if ($alertId < 1 || $staffId < 1) throw new RuntimeException('INVALID_CONTEXT');
    if (!is_string($note) || strlen($note) > 1000) throw new RuntimeException('INVALID_TEXT');
    $get = $db['get'];
    $rows = $get('SELECT SCHOOL_ID, SYEAR, STATUS, CREATED_BY FROM schoolsafe_p1_alerts'
        . ' WHERE ID=' . $alertId . ' LIMIT 1');
    if (!is_array($rows) || !isset($rows[1])) throw new RuntimeException('UNKNOWN_ALERT');
    if (($rows[1]['STATUS'] ?? '') === 'closed') throw new RuntimeException('ALERT_CLOSED');
    $school = (int) $rows[1]['SCHOOL_ID'];
    $year = (int) $rows[1]['SYEAR'];
    $prof = SchoolSafeStudioProfile($staffId, $year, $db, $school);
    $isDirection = $prof['role'] === 'direction'
        && SchoolSafeStudioAttached($prof, $staffId, $school, $year, $db);
    $isCreator = ((int) ($rows[1]['CREATED_BY'] ?? 0)) === $staffId;
    if (!$isDirection && !$isCreator) throw new RuntimeException('CLOSE_DENIED');
    $noteSql = "'" . str_replace("'", "''", $note) . "'";
    $get('UPDATE schoolsafe_p1_alerts SET STATUS=' . "'closed', CLOSED_BY=$staffId,"
        . ' CLOSED_AT=now(), CLOSE_NOTE=' . $noteSql . " WHERE ID=$alertId AND STATUS='open'");
    return ['closed' => true];
}

// Escalade : relance la direction, ne cloture jamais. Retourne false si rien
// a escalader (deja prise en charge, cloturee ou delai non atteint).
function SchoolSafeP1Escalate($alertId, array $db)
{
    $alertId = (int) $alertId;
    if ($alertId < 1) throw new RuntimeException('INVALID_CONTEXT');
    $get = $db['get'];
    $rows = $get('SELECT SCHOOL_ID, SYEAR, STATUS, SEVERITY, ESCALATIONS, CREATED_AT, ACK_AT'
        . ' FROM schoolsafe_p1_alerts WHERE ID=' . $alertId . ' LIMIT 1');
    if (!is_array($rows) || !isset($rows[1])) throw new RuntimeException('UNKNOWN_ALERT');
    $a = $rows[1];
    if (($a['STATUS'] ?? '') !== 'open') return ['escalated' => false];
    if ($a['ACK_AT'] !== null && (string) $a['ACK_AT'] !== '') return ['escalated' => false];
    $delays = SchoolSafeP1Delays();
    $delayMin = $delays[$a['SEVERITY'] ?? 'P4'] ?? 240;
    $created = strtotime((string) ($a['CREATED_AT'] ?? 'now'));
    if ($created === false || (time() - $created) < $delayMin * 60) return ['escalated' => false];
    $school = (int) $a['SCHOOL_ID'];
    $year = (int) $a['SYEAR'];
    $n = ((int) ($a['ESCALATIONS'] ?? 0)) + 1;
    // Direction de l'ecole (perimetre vide = toutes ecoles, sinon citee).
    $admins = $get("SELECT STAFF_ID, SCHOOLS FROM staff WHERE PROFILE='admin' AND SYEAR=$year");
    $dirIds = [];
    if (is_array($admins)) foreach ($admins as $row) {
        if (!isset($row['STAFF_ID'])) continue;
        $scope = trim((string) ($row['SCHOOLS'] ?? ''));
        if ($scope === '') { $dirIds[] = (int) $row['STAFF_ID']; continue; }
        if (preg_match('/(^|,)(' . $school . ')(,|$)/', trim($scope, ',')) === 1) {
            $dirIds[] = (int) $row['STAFF_ID'];
        }
    }
    $dirIds = array_values(array_unique($dirIds));
    if ($dirIds) {
        $notify = [];
        foreach ($dirIds as $did) {
            $notify[] = ['audience' => 'direction', 'staff_id' => $did, 'channel' => 'feed'];
            $notify[] = ['audience' => 'direction', 'staff_id' => $did, 'channel' => 'email'];
        }
        SchoolSafeNotifyEmit(['school_id' => $school, 'syear' => $year,
            'event_type' => 'security.p1', 'dedup_key' => "p1:$alertId:esc:$n",
            'payload' => ['kind' => 'security_p1_escalation', 'alert_id' => $alertId,
                'escalation' => $n]], $notify, $db);
    }
    $get('UPDATE schoolsafe_p1_alerts SET ESCALATED=true, ESCALATIONS=' . $n
        . " WHERE ID=$alertId AND STATUS='open' AND ACK_AT IS NULL");
    return ['escalated' => true, 'escalations' => $n, 'notified' => count($dirIds)];
}

// Lecture controlee : destinataires + direction de l'ecole. LECTURE SEULE,
// jamais un accuse ni une cloture.
function SchoolSafeP1Get($alertId, $staffId, array $db)
{
    $alertId = (int) $alertId;
    $staffId = (int) $staffId;
    if ($alertId < 1 || $staffId < 1) throw new RuntimeException('INVALID_CONTEXT');
    $get = $db['get'];
    $rows = $get('SELECT a.*, r.STAFF_ID AS IS_RECIPIENT FROM schoolsafe_p1_alerts a'
        . ' LEFT JOIN schoolsafe_p1_recipients r ON r.ALERT_ID=a.ID AND r.STAFF_ID=' . $staffId
        . ' WHERE a.ID=' . $alertId . ' LIMIT 1');
    if (!is_array($rows) || !isset($rows[1])) throw new RuntimeException('UNKNOWN_ALERT');
    $a = $rows[1];
    $school = (int) $a['SCHOOL_ID'];
    $year = (int) $a['SYEAR'];
    $prof = SchoolSafeStudioProfile($staffId, $year, $db, $school);
    $isDirection = $prof['role'] === 'direction'
        && SchoolSafeStudioAttached($prof, $staffId, $school, $year, $db);
    if (!isset($a['IS_RECIPIENT']) && !$isDirection) throw new RuntimeException('READ_DENIED');
    $recips = $get('SELECT STAFF_ID, NOTIFIED_AT, ACK_AT FROM schoolsafe_p1_recipients'
        . ' WHERE ALERT_ID=' . $alertId . ' ORDER BY STAFF_ID');
    return ['alert' => $a, 'recipients' => is_array($recips) ? array_values($recips) : []];
}
