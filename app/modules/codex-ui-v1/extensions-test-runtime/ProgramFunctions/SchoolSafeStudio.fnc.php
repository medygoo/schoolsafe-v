<?php
// SchoolSafe — Lot U3 : Studio central (pas de deuxieme moteur de messagerie).
//
// La messagerie NATIVE Rosario (messages + messagexuser, statuts
// unread/read/archived/sent) est l'unique moteur de messages. SchoolSafeNotify
// (U0) ne fait que NOTIFIER les nouveaux messages. Les notifications metier
// (U1/U2) restent dans l'outbox. Ce fichier assemble l'inbox unifie, applique
// les permissions par profil et execute les boutons reels du Studio.
// Ne modifie ni le relay ni NotificationFeed ni les moteurs QR/ENTRY/PICKUP.

function SchoolSafeStudioValidateText($text, $max)
{
    if (!is_string($text) || $text === '' || strlen($text) > $max) {
        throw new RuntimeException('INVALID_TEXT');
    }
    return $text;
}

// Profil Studio : parent | teacher | direction | gardien | central.
// Gardien = staff rattache a une ecole sans role pedagogique (profil 'none'
// ou enseignant hors classes) : notifications de securite uniquement.
// Central = admin hors contexte d'ecole : sante technique uniquement,
// jamais les conversations privees. Un admin SANS perimetre (SCHOOLS vide =
// toutes ecoles) qui agit DANS une ecole est 'direction' de cette ecole.
function SchoolSafeStudioProfile($staffId, $year, array $db, $school = 0)
{
    $rows = $db['get']('SELECT STAFF_ID, PROFILE, SCHOOLS FROM staff WHERE STAFF_ID=' . (int) $staffId
        . ' AND SYEAR=' . (int) $year . ' LIMIT 1');
    if (!is_array($rows) || !isset($rows[1])) throw new RuntimeException('UNKNOWN_STAFF');
    $profile = (string) $rows[1]['PROFILE'];
    $scope = trim((string) ($rows[1]['SCHOOLS'] ?? ''));
    if ($profile === 'parent') return ['role' => 'parent', 'scope' => null];
    if ($profile === 'admin') {
        if ((int) $school > 0) return ['role' => 'direction', 'scope' => $scope];
        return ['role' => 'central', 'scope' => $scope];
    }
    if ($profile === 'teacher') {
        $teaches = $db['get']('SELECT COURSE_PERIOD_ID FROM course_periods WHERE TEACHER_ID=' . (int) $staffId
            . ' AND SYEAR=' . (int) $year . ' LIMIT 1');
        $hasClass = is_array($teaches) && isset($teaches[1]);
        return ['role' => ($hasClass ? 'teacher' : 'gardien'), 'scope' => $scope];
    }
    return ['role' => 'gardien', 'scope' => $scope];
}

// Rattachement de l'expediteur a l'ecole du Studio. Sans rattachement :
// aucun destinataire, aucun envoi (cloisonnement inter-ecoles strict).
function SchoolSafeStudioAttached($prof, $senderStaffId, $school, $year, array $db)
{
    $school = (int) $school;
    if ($school <= 0) return false;
    if ($prof['role'] === 'parent') {
        return SchoolSafeStudioLinkedChildren($senderStaffId, $school, $year, $db) !== [];
    }
    if ($prof['role'] === 'teacher') {
        $teaches = $db['get']('SELECT COURSE_PERIOD_ID FROM course_periods WHERE TEACHER_ID='
            . (int) $senderStaffId . ' AND SCHOOL_ID=' . $school . ' AND SYEAR=' . (int) $year . ' LIMIT 1');
        if (is_array($teaches) && isset($teaches[1])) return true;
        // Un enseignant sans cours dans l'ecole n'y est rattache que si son
        // perimetre SCHOOLS la cite explicitement (vide = aucun rattachement).
        $list = SchoolSafeStudioScopeSchools($prof['scope']);
        return is_array($list) && in_array($school, $list, true);
    }
    if ($prof['role'] === 'direction') {
        $list = SchoolSafeStudioScopeSchools($prof['scope']);
        return $list === null || (is_array($list) && in_array($school, $list, true));
    }
    return false;
}

function SchoolSafeStudioScopeSchools($scopeStr)
{
    $scopeStr = trim((string) $scopeStr);
    if ($scopeStr === '') return null; // toutes les ecoles (central uniquement)
    if (!preg_match('/^,(?:[1-9][0-9]*,)+$/D', ',' . trim($scopeStr, ',') . ',')) return false;
    return array_values(array_unique(array_map('intval', explode(',', trim($scopeStr, ',')))));
}

// Enfants autorises pour un parent (inscription a l'ecole/annee).
function SchoolSafeStudioLinkedChildren($parentStaffId, $school, $year, array $db)
{
    $rows = $db['get']('SELECT DISTINCT u.STUDENT_ID FROM students_join_users u'
        . ' JOIN student_enrollment e ON e.STUDENT_ID=u.STUDENT_ID'
        . ' WHERE u.STAFF_ID=' . (int) $parentStaffId
        . ' AND e.SCHOOL_ID=' . (int) $school . ' AND e.SYEAR=' . (int) $year);
    $ids = [];
    if (is_array($rows)) foreach ($rows as $row) {
        if (isset($row['STUDENT_ID'])) $ids[] = (int) $row['STUDENT_ID'];
    }
    return $ids;
}

// Destinataires serveur autorises pour l'expediteur (jamais fournis par le client
// au-dela d'un choix dans cette liste). Retourne [key, id, label].
function SchoolSafeStudioAllowedRecipients($senderStaffId, $school, $year, array $db)
{
    $prof = SchoolSafeStudioProfile($senderStaffId, $year, $db, $school);
    $role = $prof['role'];
    $out = [];
    // Sans rattachement a l'ecole : aucun destinataire (inter-ecoles strict).
    if (!SchoolSafeStudioAttached($prof, $senderStaffId, $school, $year, $db)) return [];
    $admins = $db['get']("SELECT STAFF_ID, FIRST_NAME, LAST_NAME FROM staff WHERE PROFILE='admin'"
        . ' AND SYEAR=' . (int) $year);
    $adminIds = [];
    if (is_array($admins)) foreach ($admins as $row) {
        if (!isset($row['STAFF_ID'])) continue;
        $adminIds[(int) $row['STAFF_ID']] = trim(($row['FIRST_NAME'] ?? '') . ' ' . ($row['LAST_NAME'] ?? ''));
    }
    if ($role === 'parent') {
        $children = SchoolSafeStudioLinkedChildren($senderStaffId, $school, $year, $db);
        if (!$children) return [];
        $in = implode(',', $children);
        // Enseignants des classes des enfants + direction.
        $teachers = $db['get']('SELECT DISTINCT cp.TEACHER_ID, s.FIRST_NAME, s.LAST_NAME'
            . ' FROM schedule sc JOIN course_periods cp ON cp.COURSE_PERIOD_ID=sc.COURSE_PERIOD_ID'
            . ' JOIN staff s ON s.STAFF_ID=cp.TEACHER_ID'
            . ' WHERE sc.STUDENT_ID IN (' . $in . ') AND sc.SCHOOL_ID=' . (int) $school
            . ' AND sc.SYEAR=' . (int) $year);
        if (is_array($teachers)) foreach ($teachers as $row) {
            if (isset($row['TEACHER_ID'])) {
                $out[] = ['key' => 'staff_id', 'id' => (int) $row['TEACHER_ID'],
                    'label' => trim(($row['FIRST_NAME'] ?? '') . ' ' . ($row['LAST_NAME'] ?? ''))];
            }
        }
        foreach ($adminIds as $id => $label) {
            $out[] = ['key' => 'staff_id', 'id' => $id, 'label' => $label . ' (direction)'];
        }
    } elseif ($role === 'teacher') {
        // Parents des eleves de ses classes + direction.
        $parents = $db['get']('SELECT DISTINCT su.STAFF_ID, s.FIRST_NAME, s.LAST_NAME'
            . ' FROM course_periods cp JOIN schedule sc ON sc.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID'
            . ' JOIN students_join_users su ON su.STUDENT_ID=sc.STUDENT_ID'
            . ' JOIN staff s ON s.STAFF_ID=su.STAFF_ID'
            . " WHERE cp.TEACHER_ID=" . (int) $senderStaffId . ' AND cp.SCHOOL_ID=' . (int) $school
            . ' AND cp.SYEAR=' . (int) $year);
        if (is_array($parents)) foreach ($parents as $row) {
            if (isset($row['STAFF_ID'])) {
                $out[] = ['key' => 'staff_id', 'id' => (int) $row['STAFF_ID'],
                    'label' => trim(($row['FIRST_NAME'] ?? '') . ' ' . ($row['LAST_NAME'] ?? ''))];
            }
        }
        foreach ($adminIds as $id => $label) {
            $out[] = ['key' => 'staff_id', 'id' => $id, 'label' => $label . ' (direction)'];
        }
    } elseif ($role === 'direction') {
        // Isolation : enseignants du perimetre ou enseignant dans l'ecole,
        // parents d'eleves inscrits dans l'ecole. Jamais d'autre ecole.
        $teachers = $db['get']('SELECT STAFF_ID, FIRST_NAME, LAST_NAME, SCHOOLS FROM staff'
            . ' WHERE SYEAR=' . (int) $year . " AND PROFILE='teacher' LIMIT 500");
        if (is_array($teachers)) foreach ($teachers as $row) {
            if (!isset($row['STAFF_ID'])) continue;
            $scope = trim((string) ($row['SCHOOLS'] ?? ''));
            $inScope = $scope !== ''
                && preg_match('/(^|,)(' . (int) $school . ')(,|$)/', trim($scope, ',')) === 1;
            if (!$inScope) {
                $teaches = $db['get']('SELECT COURSE_PERIOD_ID FROM course_periods WHERE TEACHER_ID='
                    . (int) $row['STAFF_ID'] . ' AND SCHOOL_ID=' . (int) $school
                    . ' AND SYEAR=' . (int) $year . ' LIMIT 1');
                $inScope = is_array($teaches) && isset($teaches[1]);
            }
            if ($inScope) {
                $out[] = ['key' => 'staff_id', 'id' => (int) $row['STAFF_ID'],
                    'label' => trim(($row['FIRST_NAME'] ?? '') . ' ' . ($row['LAST_NAME'] ?? ''))];
            }
        }
        $parents = $db['get']('SELECT DISTINCT su.STAFF_ID, s.FIRST_NAME, s.LAST_NAME'
            . ' FROM students_join_users su JOIN student_enrollment e ON e.STUDENT_ID=su.STUDENT_ID'
            . ' JOIN staff s ON s.STAFF_ID=su.STAFF_ID'
            . ' WHERE e.SCHOOL_ID=' . (int) $school . ' AND e.SYEAR=' . (int) $year . ' LIMIT 500');
        if (is_array($parents)) foreach ($parents as $row) {
            if (isset($row['STAFF_ID'])) {
                $out[] = ['key' => 'staff_id', 'id' => (int) $row['STAFF_ID'],
                    'label' => trim(($row['FIRST_NAME'] ?? '') . ' ' . ($row['LAST_NAME'] ?? ''))];
            }
        }
    } elseif ($role === 'gardien') {
        foreach ($adminIds as $id => $label) {
            $out[] = ['key' => 'staff_id', 'id' => $id, 'label' => $label . ' (direction)'];
        }
    }
    // Deduplique, sans soi-meme.
    $seen = [];
    $unique = [];
    foreach ($out as $r) {
        if ($r['id'] === (int) $senderStaffId) continue;
        $k = $r['key'] . '|' . $r['id'];
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $unique[] = $r;
    }
    return $unique;
}

function SchoolSafeStudioSenderName($staffId, array $db)
{
    $rows = $db['get']('SELECT FIRST_NAME, LAST_NAME FROM staff WHERE STAFF_ID=' . (int) $staffId . ' LIMIT 1');
    if (!is_array($rows) || !isset($rows[1])) throw new RuntimeException('UNKNOWN_STAFF');
    return trim(($rows[1]['FIRST_NAME'] ?? '') . ' ' . ($rows[1]['LAST_NAME'] ?? ''));
}

// Envoi natif : ecrit messages + messagexuser (unread/destinataires, sent/expediteur)
// PUIS notifie via U0 (school.announcement, dedup msg:<id>:<rid>). Transactionnel.
function SchoolSafeStudioSend($senderStaffId, $school, $year, $subject, $body, array $recipientIds, array $db)
{
    $school = (int) $school;
    $year = (int) $year;
    if ($school < 1 || $year < 1) throw new RuntimeException('INVALID_CONTEXT');
    SchoolSafeStudioValidateText($subject, 100);
    SchoolSafeStudioValidateText($body, 8000);
    if (!$recipientIds) throw new RuntimeException('NO_RECIPIENT');
    $allowed = SchoolSafeStudioAllowedRecipients($senderStaffId, $school, $year, $db);
    $allowedIds = [];
    foreach ($allowed as $r) $allowedIds[] = $r['id'];
    $targets = [];
    foreach ($recipientIds as $rid) {
        $rid = (int) $rid;
        if (!in_array($rid, $allowedIds, true)) throw new RuntimeException('RECIPIENT_DENIED');
        $targets[] = $rid;
    }
    $targets = array_values(array_unique($targets));
    $name = SchoolSafeStudioSenderName($senderStaffId, $db);
    $fromSql = "'" . str_replace("'", "''", serialize(['name' => $name])) . "'";
    $labels = [];
    foreach ($allowed as $r) {
        if (in_array($r['id'], $targets, true)) $labels[] = $r['label'];
    }
    $recipSql = "'" . str_replace("'", "''", implode(', ', $labels)) . "'";
    $subjSql = "'" . str_replace("'", "''", $subject) . "'";
    $dataSql = "'" . str_replace("'", "''", serialize(['message' => $body])) . "'";
    $get = $db['get'];
    $exec = $db['exec'];
    $reg = $get("SELECT to_regclass('public.messages') AS t");
    $found = '';
    if (is_array($reg)) foreach ($reg as $rr) { $found = (string) ($rr['T'] ?? ''); break; }
    if ($found === '') return null; // moteur natif indisponible : repli documente
    $exec('BEGIN');
    try {
        $row = $get('INSERT INTO messages(syear, school_id, "from", recipients, subject, data)'
            . " VALUES($year,$school,$fromSql,$recipSql,$subjSql,$dataSql) RETURNING MESSAGE_ID");
        if (!is_array($row) || !isset($row[1])) throw new RuntimeException('MESSAGE_WRITE_FAILED');
        $msgId = (int) $row[1]['MESSAGE_ID'];
        foreach ($targets as $rid) {
            $get("INSERT INTO messagexuser VALUES($rid,'staff_id',$msgId,'unread')");
        }
        $get("INSERT INTO messagexuser VALUES(" . (int) $senderStaffId . ",'staff_id',$msgId,'sent')");
        $exec('COMMIT');
    } catch (Throwable $e) { try { $exec('ROLLBACK'); } catch (Throwable $i) { } throw $e; }
    // Notification U0 (sans bloquer : l'envoi natif est deja commis).
    require_once __DIR__ . '/SchoolSafeNotify.fnc.php';
    foreach ($targets as $rid) {
        SchoolSafeNotifyEmit(['school_id' => $school, 'syear' => $year,
            'event_type' => 'school.announcement',
            'dedup_key' => "msg:$msgId:$rid",
            'payload' => ['kind' => 'new_message', 'message_id' => $msgId, 'subject' => $subject]],
            [['audience' => 'staff', 'staff_id' => $rid, 'channel' => 'feed'],
             ['audience' => 'staff', 'staff_id' => $rid, 'channel' => 'email']], $db);
    }
    return $msgId;
}

// Devoir publie par l'enseignant : notification U0 aux parents de ses eleves
// (ecole/annee), sans ecrire dans la messagerie. Rattachement strict.
function SchoolSafeStudioHomework($teacherStaffId, $school, $year, $title, $body, array $db)
{
    SchoolSafeStudioValidateText($title, 150);
    SchoolSafeStudioValidateText($body, 8000);
    $prof = SchoolSafeStudioProfile($teacherStaffId, (int) $year, $db, $school);
    if ($prof['role'] !== 'teacher') throw new RuntimeException('DECIDER_SCOPE');
    if (!SchoolSafeStudioAttached($prof, $teacherStaffId, $school, $year, $db)) {
        throw new RuntimeException('SCHOOL_ISOLATION');
    }
    $rows = $db['get']('SELECT DISTINCT su.STAFF_ID FROM course_periods cp'
        . ' JOIN schedule sc ON sc.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID'
        . ' JOIN student_enrollment e ON e.STUDENT_ID=sc.STUDENT_ID'
        . ' JOIN students_join_users su ON su.STUDENT_ID=sc.STUDENT_ID'
        . ' WHERE cp.TEACHER_ID=' . (int) $teacherStaffId . ' AND cp.SCHOOL_ID=' . (int) $school
        . ' AND cp.SYEAR=' . (int) $year . ' AND e.SCHOOL_ID=' . (int) $school . ' AND e.SYEAR=' . (int) $year);
    $parentIds = [];
    if (is_array($rows)) foreach ($rows as $row) {
        if (isset($row['STAFF_ID'])) $parentIds[] = (int) $row['STAFF_ID'];
    }
    $parentIds = array_values(array_unique($parentIds));
    if (!$parentIds) throw new RuntimeException('NO_RECIPIENT');
    require_once __DIR__ . '/SchoolSafeNotify.fnc.php';
    $dedup = 'u3-homework:' . md5($school . '|' . $year . '|' . $teacherStaffId . '|' . $title . '|' . $body);
    $recips = [];
    foreach ($parentIds as $pid) {
        $recips[] = ['audience' => 'parent', 'staff_id' => $pid, 'channel' => 'feed'];
        $recips[] = ['audience' => 'parent', 'staff_id' => $pid, 'channel' => 'email'];
    }
    SchoolSafeNotifyEmit(['school_id' => (int) $school, 'syear' => (int) $year,
        'event_type' => 'school.announcement', 'dedup_key' => $dedup,
        'payload' => ['kind' => 'homework', 'title' => $title, 'body' => $body]], $recips, $db);
    return ['event' => $dedup, 'notified' => count($parentIds)];
}

// Reponse native : reservee au destinataire (pas au statut 'sent'), vers l'expediteur d'origine.
function SchoolSafeStudioReply($staffId, $messageId, $body, array $db)
{
    SchoolSafeStudioValidateText($body, 8000);
    $messageId = (int) $messageId;
    $rows = $db['get']('SELECT mxu.STATUS, m.SCHOOL_ID, m.SYEAR FROM messagexuser mxu'
        . ' JOIN messages m ON m.MESSAGE_ID=mxu.MESSAGE_ID'
        . ' WHERE mxu.MESSAGE_ID=' . $messageId . ' AND mxu.USER_ID=' . (int) $staffId
        . " AND mxu.KEY='staff_id' LIMIT 1");
    if (!is_array($rows) || !isset($rows[1]) || ($rows[1]['STATUS'] ?? '') === 'sent') {
        throw new RuntimeException('REPLY_DENIED');
    }
    $school = (int) $rows[1]['SCHOOL_ID'];
    $year = (int) $rows[1]['SYEAR'];
    // Expediteur d'origine = l'unique ligne 'sent'.
    $from = $db['get']("SELECT USER_ID FROM messagexuser WHERE MESSAGE_ID=$messageId AND STATUS='sent' LIMIT 1");
    if (!is_array($from) || !isset($from[1])) throw new RuntimeException('REPLY_DENIED');
    $origId = (int) $from[1]['USER_ID'];
    $orig = $db['get']('SELECT SUBJECT FROM messages WHERE MESSAGE_ID=' . $messageId . ' LIMIT 1');
    $subject = 'Re: ' . (string) ($orig[1]['SUBJECT'] ?? '');
    return SchoolSafeStudioSend($staffId, $school, $year, $subject, $body, [$origId], $db);
}

// Accuse de lecture natif : unread -> read, proprietaire uniquement.
function SchoolSafeStudioMarkRead($staffId, $messageId, array $db)
{
    $row = $db['get']("UPDATE messagexuser SET STATUS='read' WHERE MESSAGE_ID=" . (int) $messageId
        . ' AND USER_ID=' . (int) $staffId . " AND KEY='staff_id' AND STATUS='unread' RETURNING MESSAGE_ID");
    return is_array($row) && isset($row[1]);
}

function SchoolSafeStudioArchive($staffId, $messageId, array $db)
{
    $row = $db['get']("UPDATE messagexuser SET STATUS='archived' WHERE MESSAGE_ID=" . (int) $messageId
        . ' AND USER_ID=' . (int) $staffId . " AND KEY='staff_id' AND STATUS='read' RETURNING MESSAGE_ID");
    return is_array($row) && isset($row[1]);
}

// Inbox unifie : messages natifs (statuts) + notifications U0 (deliveries) +
// dossiers U2 (suivi). Ordre stable, sans second moteur.
function SchoolSafeStudioInbox($staffId, $school, $year, array $db, $limit = 50)
{
    $limit = max(1, min(200, (int) $limit));
    $items = [];
    $msgs = $db['get']('SELECT m.MESSAGE_ID, m.SUBJECT, m.CREATED_AT, mxu.STATUS FROM messages m'
        . ' JOIN messagexuser mxu ON mxu.MESSAGE_ID=m.MESSAGE_ID'
        . ' WHERE mxu.USER_ID=' . (int) $staffId . " AND mxu.KEY='staff_id'"
        . ' AND m.SCHOOL_ID=' . (int) $school . ' AND m.SYEAR=' . (int) $year
        . " AND mxu.STATUS IN ('unread','read') ORDER BY m.CREATED_AT DESC LIMIT $limit");
    if (is_array($msgs)) foreach ($msgs as $row) {
        if (!isset($row['MESSAGE_ID'])) continue;
        $items[] = ['id' => 'message:' . $row['MESSAGE_ID'], 'kind' => 'message',
            'status' => (string) ($row['STATUS'] ?? ''),
            'subject' => (string) ($row['SUBJECT'] ?? ''),
            'created_at' => (string) ($row['CREATED_AT'] ?? '')];
    }
    require_once __DIR__ . '/SchoolSafeNotify.fnc.php';
    foreach (SchoolSafeNotifyTestFeed($staffId, $school, $db, $limit) as $n) {
        $items[] = ['id' => $n['id'], 'kind' => 'notification',
            'status' => $n['status'], 'subject' => $n['event_type'],
            'created_at' => $n['created_at']];
    }
    usort($items, static fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    return array_slice($items, 0, $limit);
}

// Matrice des boutons par role : cle, libelle, endpoint, mutation ou non.
function SchoolSafeStudioButtons($role)
{
    $matrix = [
        'parent' => [
            ['key' => 'inbox', 'label' => 'Messages recus', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => false],
            ['key' => 'sent', 'label' => 'Messages envoyes', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => false],
            ['key' => 'compose', 'label' => 'Ecrire', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => true],
            ['key' => 'reply', 'label' => 'Repondre', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => true],
            ['key' => 'mark-read', 'label' => 'Marquer lu', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => true],
            ['key' => 'archive', 'label' => 'Archiver', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => true],
            ['key' => 'report', 'label' => 'Signaler absence', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => true],
            ['key' => 'justify', 'label' => 'Justifier', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => true],
            ['key' => 'notify-settings', 'label' => 'Notifications', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => true],
        ],
        'teacher' => [
            ['key' => 'inbox', 'label' => 'Messages recus', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => false],
            ['key' => 'sent', 'label' => 'Messages envoyes', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => false],
            ['key' => 'compose', 'label' => 'Ecrire', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => true],
            ['key' => 'reply', 'label' => 'Repondre', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => true],
            ['key' => 'mark-read', 'label' => 'Marquer lu', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => true],
            ['key' => 'archive', 'label' => 'Archiver', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => true],
            ['key' => 'class-absences', 'label' => 'Absences de mes classes', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => false],
            ['key' => 'convocations', 'label' => 'Convocations', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => false],
            ['key' => 'homework', 'label' => 'Devoirs', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => false],
        ],
        'direction' => [
            ['key' => 'inbox', 'label' => 'Messages recus', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => false],
            ['key' => 'sent', 'label' => 'Messages envoyes', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => false],
            ['key' => 'compose', 'label' => 'Ecrire', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => true],
            ['key' => 'reply', 'label' => 'Repondre', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => true],
            ['key' => 'mark-read', 'label' => 'Marquer lu', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => true],
            ['key' => 'archive', 'label' => 'Archiver', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => true],
            ['key' => 'decide', 'label' => 'Valider justifications', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => true],
            ['key' => 'convocations', 'label' => 'Convocations', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => true],
            ['key' => 'templates', 'label' => 'Modeles', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => false],
            ['key' => 'supervision', 'label' => 'Supervision', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => false],
        ],
        'gardien' => [
            ['key' => 'security', 'label' => 'Alertes securite', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => false],
            ['key' => 'mark-read', 'label' => 'Marquer lu', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => true],
        ],
        'central' => [
            ['key' => 'health', 'label' => 'Sante des files', 'endpoint' => 'schoolsafe-studio.php', 'mutates' => false],
        ],
    ];
    return $matrix[$role] ?? [];
}

// Convocation de la direction : message natif + notification U0 aux parents
// des eleves cites (destinataires serveur). Jamais de doublon (dedup).
function SchoolSafeStudioConvocation($directionStaffId, $school, $year, array $studentIds, $title, $body, $dateStr, array $db)
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $dateStr)) throw new RuntimeException('INVALID_DATE');
    $prof = SchoolSafeStudioProfile($directionStaffId, (int) $year, $db, $school);
    if ($prof['role'] !== 'direction') throw new RuntimeException('DECIDER_SCOPE');
    if (!SchoolSafeStudioAttached($prof, $directionStaffId, $school, $year, $db)) {
        throw new RuntimeException('SCHOOL_ISOLATION');
    }
    $studentIds = array_values(array_unique(array_map('intval', (array) $studentIds)));
    if (!$studentIds) throw new RuntimeException('NO_RECIPIENT');
    require_once __DIR__ . '/SchoolSafeCases.fnc.php';
    $parentIds = [];
    foreach ($studentIds as $sid) {
        if (!SchoolSafeCasesIsEnrolled($sid, $school, $year, $db)) throw new RuntimeException('SCHOOL_ISOLATION');
        $rows = $db['get']('SELECT STAFF_ID FROM students_join_users WHERE STUDENT_ID=' . $sid);
        if (is_array($rows)) foreach ($rows as $row) {
            if (isset($row['STAFF_ID'])) $parentIds[] = (int) $row['STAFF_ID'];
        }
    }
    $parentIds = array_values(array_unique($parentIds));
    if (!$parentIds) throw new RuntimeException('NO_RECIPIENT');
    $msgId = SchoolSafeStudioSend($directionStaffId, $school, $year,
        '[Convocation] ' . $title, $body . "\nDate : $dateStr", $parentIds, $db);
    return ['message_id' => $msgId, 'parents' => count($parentIds), 'students' => count($studentIds)];
}
