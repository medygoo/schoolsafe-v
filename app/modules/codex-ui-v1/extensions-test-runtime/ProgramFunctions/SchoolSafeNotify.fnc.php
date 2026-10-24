<?php
// SchoolSafe — Lot U0 : socle Studio central des messages et notifications.
//
// Conventions natives Rosario : aucune table native modifiee, requetes via
// $db injecte (tests) ou DBGet/DBQuery (runtime). Fonctions pures et
// idempotentes : le rejeu d'un meme dedup_key ne cree aucun doublon.
// L'isolation scolaire est garantie par student_enrollment (source officielle),
// jamais par un parametre client seul. Ne modifie ni le relay ni le fil parent.

function SchoolSafeNotifyEventTypes()
{
    // U1 : 'attendance.closure' et 'absence.detected' ajoutes en sur-ensemble
    // (migration 027, CHECK DB elargi a l'identique). Les 5 types U0 restent.
    // U2 : 'case.updated' (migration 028, meme motif).
    // U4 : 'security.p1' (migration 030, alertes immediates individuelles).
    // Scan : 'financial.control' (042), observation distincte d'un paiement.
    return ['security.entry', 'student.pickup', 'payment.receipt',
        'school.announcement', 'remediation.update',
        'attendance.closure', 'absence.detected', 'case.updated',
        'security.p1', 'financial.control'];
}

function SchoolSafeNotifyValidateEvent(array $event)
{
    $school = (int) ($event['school_id'] ?? 0);
    $year = (int) ($event['syear'] ?? 0);
    $type = (string) ($event['event_type'] ?? '');
    $dedup = (string) ($event['dedup_key'] ?? '');
    if ($school < 1 || $year < 1) throw new RuntimeException('INVALID_CONTEXT');
    if (!in_array($type, SchoolSafeNotifyEventTypes(), true)) throw new RuntimeException('INVALID_EVENT_TYPE');
    if (!preg_match('/^[A-Za-z0-9:_-]{1,200}$/D', $dedup)) throw new RuntimeException('INVALID_DEDUP_KEY');
    $studentId = isset($event['student_id']) ? (int) $event['student_id'] : 0;
    if ($studentId < 0) throw new RuntimeException('INVALID_STUDENT');
    if (isset($event['payload']) && !is_array($event['payload'])) throw new RuntimeException('INVALID_PAYLOAD');
    return ['school_id' => $school, 'syear' => $year, 'event_type' => $type,
        'dedup_key' => $dedup, 'student_id' => $studentId ?: null,
        'actor_staff_id' => isset($event['actor_staff_id']) ? (int) $event['actor_staff_id'] : null,
        'payload' => $event['payload'] ?? []];
}

// Resout les destinataires autorises pour un evenement scolaire valide.
// Echec ferme : eleve non inscrit dans (ecole, annee) => SCHOOL_ISOLATION.
// U0 : audience parent via students_join_users (lien natif), canaux feed+email.
// Aucun enseignant arrose par defaut (pas de spam) ; la direction lit les
// registres natifs. Push ajoute seulement si abonnement present (026).
function SchoolSafeNotifyResolveRecipients($eventType, $school, $year, $studentId, array $db)
{
    $school = (int) $school;
    $year = (int) $year;
    $studentId = (int) $studentId;
    if ($school < 1 || $year < 1 || $studentId < 1) throw new RuntimeException('INVALID_CONTEXT');
    if (!in_array($eventType, SchoolSafeNotifyEventTypes(), true)) throw new RuntimeException('INVALID_EVENT_TYPE');
    $get = $db['get'];
    $enrolled = $get('SELECT 1 AS OK FROM student_enrollment WHERE STUDENT_ID=' . $studentId
        . ' AND SCHOOL_ID=' . $school . ' AND SYEAR=' . $year . ' LIMIT 1');
    if (!is_array($enrolled)) throw new RuntimeException('NOTIFY_RECIPIENT_UNAVAILABLE');
    if (!isset($enrolled[1])) throw new RuntimeException('SCHOOL_ISOLATION');
    require_once __DIR__.'/SchoolSafeNotifyRecipient.fnc.php';
    $rows = SchoolSafeNotifyParentRows($school, $year, $studentId, $db);
    $recipients = [];
    if (is_array($rows)) foreach ($rows as $row) {
        if (!isset($row['STAFF_ID'])) continue;
        $parent = (int) $row['STAFF_ID'];
        if ($parent < 1) continue;
        $recipients[] = ['audience' => 'parent', 'staff_id' => $parent, 'channel' => 'feed'];
        $recipients[] = ['audience' => 'parent', 'staff_id' => $parent, 'channel' => 'email'];
    }
    // Dedup strict cote emetteur (par cle audience/staff/canal).
    $seen = [];
    $unique = [];
    foreach ($recipients as $r) {
        $k = $r['audience'] . '|' . $r['staff_id'] . '|' . $r['channel'];
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $unique[] = $r;
    }
    return $unique;
}

function SchoolSafeNotifyEscapeJson(array $payload)
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) throw new RuntimeException('INVALID_PAYLOAD');
    if (strlen($json) > 8000) throw new RuntimeException('INVALID_PAYLOAD');
    return "'" . str_replace("'", "''", $json) . "'::jsonb";
}

// Emetteur minimal : transaction fiable evenement + destinataires (outbox).
// Retourne null quand le socle est indisponible (table absente) : l'appelant
// applique son repli sans bloquer le scanner. Rejeu idempotent : meme
// (school_id, dedup_key) => created=false, aucun doublon.
// ownTransaction=false: caller MUST own the native business transaction;
// never BEGIN/COMMIT/ROLLBACK here, and unavailable outbox is an explicit error.
function SchoolSafeNotifyEmit(array $event, array $recipients, array $db, bool $ownTransaction = true)
{
    $e = SchoolSafeNotifyValidateEvent($event);
    $get = $db['get'];
    $exec = $db['exec'];
    $reg = $get("SELECT to_regclass('public.schoolsafe_notify_events') AS t");
    $found = '';
    if (is_array($reg)) foreach ($reg as $rr) { $found = (string) ($rr['T'] ?? ''); break; }
    if ($found === '') {
        if (!$ownTransaction) throw new RuntimeException('NOTIFY_UNAVAILABLE');
        return null;
    }
    foreach ($recipients as $r) {
        if (!isset($r['audience'], $r['staff_id'], $r['channel'])) throw new RuntimeException('INVALID_RECIPIENT');
        if (!in_array($r['audience'], ['parent', 'teacher', 'direction', 'staff'], true)) throw new RuntimeException('INVALID_RECIPIENT');
        // WhatsApp (037) : sur-ensemble strict — feed/email/push inchanges.
        if (!in_array($r['channel'], ['feed', 'email', 'push', 'whatsapp'], true)) throw new RuntimeException('INVALID_RECIPIENT');
        if ((int) $r['staff_id'] < 1) throw new RuntimeException('INVALID_RECIPIENT');
    }
    $studentSql = $e['student_id'] === null ? 'NULL' : (string) $e['student_id'];
    $actorSql = empty($e['actor_staff_id']) ? 'NULL' : (string) ((int) $e['actor_staff_id']);
    $payloadSql = SchoolSafeNotifyEscapeJson($e['payload']);
    $dedupSql = "'" . str_replace("'", "''", $e['dedup_key']) . "'";
    $typeSql = "'" . str_replace("'", "''", $e['event_type']) . "'";
    if ($ownTransaction) $exec('BEGIN');
    try {
        $inserted = $get('INSERT INTO schoolsafe_notify_events(school_id, syear, event_type, dedup_key, student_id, actor_staff_id, payload)'
            . ' VALUES(' . $e['school_id'] . ',' . $e['syear'] . ',' . $typeSql . ',' . $dedupSql . ','
            . $studentSql . ',' . $actorSql . ',' . $payloadSql . ')'
            . ' ON CONFLICT (school_id, dedup_key) DO NOTHING RETURNING ID');
        if (!is_array($inserted)) throw new RuntimeException('NOTIFY_WRITE_FAILED');
        $created = isset($inserted[1]['ID']);
        $eventId = $created ? (int) $inserted[1]['ID'] : 0;
        if (!$created) {
            $existing = $get('SELECT ID FROM schoolsafe_notify_events WHERE SCHOOL_ID=' . $e['school_id']
                . ' AND DEDUP_KEY=' . $dedupSql . ' LIMIT 1');
            if (!is_array($existing) || !isset($existing[1])) throw new RuntimeException('NOTIFY_WRITE_FAILED');
            $eventId = (int) $existing[1]['ID'];
            // Rejeu idempotent : pas de nouveaux destinataires apres changement de lien.
            $n = SchoolSafeNotifyDeliveryCount($eventId, $db);
            if ($ownTransaction) $exec('COMMIT');
            return ['event_id' => $eventId, 'created' => false, 'deliveries' => $n];
        }
        foreach ($recipients as $r) {
            $delivery = $get('INSERT INTO schoolsafe_notify_deliveries(event_id, school_id, syear, audience, recipient_staff_id, channel)'
                . ' VALUES(' . $eventId . ',' . $e['school_id'] . ',' . $e['syear'] . ",'"
                . $r['audience'] . "'," . ((int) $r['staff_id']) . ",'" . $r['channel'] . "')"
                . ' ON CONFLICT (event_id, audience, recipient_staff_id, channel) DO NOTHING RETURNING ID');
            if (!is_array($delivery)) throw new RuntimeException('NOTIFY_WRITE_FAILED');
            if (!isset($delivery[1]['ID'])) {
                $existingDelivery = $get('SELECT ID FROM schoolsafe_notify_deliveries WHERE EVENT_ID=' . $eventId
                    . " AND AUDIENCE='" . $r['audience'] . "' AND RECIPIENT_STAFF_ID=" . (int)$r['staff_id']
                    . " AND CHANNEL='" . $r['channel'] . "'");
                if (!is_array($existingDelivery) || !isset($existingDelivery[1]['ID'])) throw new RuntimeException('NOTIFY_WRITE_FAILED');
            }
        }
        $n = SchoolSafeNotifyDeliveryCount($eventId, $db);
        if ($ownTransaction) $exec('COMMIT');
        return ['event_id' => $eventId, 'created' => true, 'deliveries' => $n];
    } catch (Throwable $error) {
        if ($ownTransaction) { try { $exec('ROLLBACK'); } catch (Throwable $ignored) { } }
        throw $error;
    }
}

function SchoolSafeNotifyDeliveryCount(int $eventId, array $db): int
{
    $count = $db['get']('SELECT COUNT(*) AS C FROM schoolsafe_notify_deliveries WHERE EVENT_ID=' . $eventId);
    if (!is_array($count) || !isset($count[1]['C']) || preg_match('/^[0-9]+$/D', (string)$count[1]['C']) !== 1) throw new RuntimeException('NOTIFY_WRITE_FAILED');
    return (int)$count[1]['C'];
}

// Fil de TEST : jamais le fil parent occupe. Isolation ecole imposee.
function SchoolSafeNotifyTestFeed($recipientStaffId, $school, array $db, $limit = 50)
{
    $recipientStaffId = (int) $recipientStaffId;
    $school = (int) $school;
    $limit = max(1, min(200, (int) $limit));
    if ($recipientStaffId < 1 || $school < 1) throw new RuntimeException('INVALID_CONTEXT');
    $get = $db['get'];
    $rows = $get('SELECT d.ID, d.CHANNEL, d.STATUS, d.AUDIENCE, e.EVENT_TYPE, e.CREATED_AT, e.PAYLOAD, e.STUDENT_ID'
        . ' FROM schoolsafe_notify_deliveries d JOIN schoolsafe_notify_events e ON e.ID=d.EVENT_ID'
        . ' WHERE d.RECIPIENT_STAFF_ID=' . $recipientStaffId . ' AND d.SCHOOL_ID=' . $school
        . ' ORDER BY d.CREATED_AT DESC, d.ID DESC LIMIT ' . $limit);
    $items = [];
    if (is_array($rows)) foreach ($rows as $row) {
        if (!isset($row['ID'])) continue;
        $payload = is_string($row['PAYLOAD'] ?? null) ? json_decode($row['PAYLOAD'], true) : $row['PAYLOAD'];
        if (!is_array($payload)) $payload = [];
        $items[] = ['id' => 'notify:' . $row['ID'],
            'event_type' => (string) ($row['EVENT_TYPE'] ?? ''),
            'channel' => (string) ($row['CHANNEL'] ?? ''),
            'status' => (string) ($row['STATUS'] ?? ''),
            'created_at' => (string) ($row['CREATED_AT'] ?? ''),
            'student_id' => isset($row['STUDENT_ID']) ? (string) $row['STUDENT_ID'] : null,
            'payload' => $payload];
    }
    return $items;
}
