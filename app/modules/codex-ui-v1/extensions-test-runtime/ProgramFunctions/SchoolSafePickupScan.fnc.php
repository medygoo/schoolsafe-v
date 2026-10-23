<?php
// SchoolSafe — Lot PICKUP : récupération sécurisée (Rosario moteur principal).
//
// Enchaîne : vérification QR (registre A1) → contrôle du portail → liste des
// personnes habilitées (principaux natifs `students_join_users` + externes
// autorisés) → préparation (AUCUNE sortie enregistrée) → preuve à usage
// unique de 2 minutes, liée (école, élève, personne, portail) → confirmation
// humaine obligatoire → sortie `entry_exit_student_records` type 'out',
// idempotente (même preuve rejouée = même remise, sans doublon).
//
// Une photo enregistrée n'est jamais une autorisation : `pickup_ready` exige
// personne active + autorisation en cours de validité + photo validée.
// $ctx : ['user'=>int, 'school'=>int, 'year'=>int].
// $db : ['get'=>callable(string):array, 'exec'=>callable(string):void].
// $now : timestamp unix (tests uniquement, défaut time()).
// $input : ['authorized_person_id'=>?string, 'proof_token'=>?string, 'human_confirmed'=>?bool].
// Retourne null quand le local est indisponible (secret invalide) : l'appelant
// applique le repli existant. Lève en cas d'échec d'écriture (repli 503).
function SchoolSafePickupScan($qr, $locationId, $secretHex, array $ctx, array $db, array $input = [], $now = null)
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
    $cp = $get('SELECT ID, TITLE FROM entry_exit_checkpoints WHERE ID=' . $locationId);
    if (!is_array($cp) || !isset($cp[1])) {
        return ['result' => 'DENIED', 'signature_valid' => true, 'reason' => 'LOCATION_DENIED'] + $verify;
    }
    $checkpoint = ['id' => (string) $cp[1]['ID'], 'label' => $cp[1]['TITLE']];
    $studentId = (int) $verify['student_id'];

    $personRef = $input['authorized_person_id'] ?? null;
    $proofToken = $input['proof_token'] ?? null;
    $human = $input['human_confirmed'] ?? null;

    // Confirmation d'une preuve existante.
    if (is_string($proofToken) && $proofToken !== '') {
        if ($human !== true) {
            return ['result' => 'DENIED', 'signature_valid' => true, 'reason' => 'CONFIRMATION_REQUIRED'] + $verify;
        }
        return SchoolSafePickupConfirm($proofToken, $school, $studentId, (int) $locationId, $user, $day, $clock, $db, $verify, $checkpoint);
    }
    if ($human !== null && $human !== false) {
        return ['result' => 'DENIED', 'signature_valid' => true, 'reason' => 'CONFIRMATION_REQUIRED'] + $verify;
    }

    $candidates = SchoolSafePickupCandidates($school, $studentId, $day, $db);

    // Choix d'une personne : prépare la preuve, sans enregistrer de sortie.
    if (is_string($personRef) && $personRef !== '') {
        $picked = null;
        foreach ($candidates as $candidate) {
            if ($candidate['id'] === $personRef) { $picked = $candidate; break; }
        }
        if ($picked === null) {
            return ['result' => 'DENIED', 'signature_valid' => true, 'reason' => 'NOT_DELEGATED'] + $verify;
        }
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', $now + 120);
        $exec('BEGIN');
        try {
            $safe = str_replace("'", "''", $token);
            $stored = $get("INSERT INTO schoolsafe_pickup_proofs(token, school_id, student_id, guardian_id,"
                . ' authorization_id, is_principal, location_id, prepared_by, expires_at)'
                . ' VALUES(' . "'" . $safe . "'" . ',' . $school . ',' . $studentId . ',' . $picked['guardian_id'] . ','
                . ($picked['authorization_id'] === null ? 'NULL' : (int) $picked['authorization_id']) . ','
                . ($picked['is_principal'] ? 'TRUE' : 'FALSE') . ',' . (int) $locationId . ',' . $user
                . ",'" . $expires . "') RETURNING ID");
            if (!is_array($stored) || !isset($stored[1]['ID'])) throw new RuntimeException('PICKUP_PROOF_WRITE_FAILED');
            $exec('COMMIT');
        } catch (Throwable $error) {
            try { $exec('ROLLBACK'); } catch (Throwable $ignored) { }
            throw $error;
        }
        return ['result' => 'NEED_HUMAN_CONFIRMATION', 'decision' => 'confirm_required',
            'proof_token' => $token, 'person' => $picked, 'checkpoint' => $checkpoint] + $verify;
    }

    // Lecture simple : élève + personnes habilitées, aucune écriture.
    return ['result' => 'PICKUP_CHOICE', 'decision' => 'choose_person',
        'people' => $candidates, 'checkpoint' => $checkpoint] + $verify;
}

// Personnes habilitées à cet instant : principaux natifs + externes autorisés.
// Photo validée obligatoire, mais jamais suffisante seule.
function SchoolSafePickupCandidates($school, $studentId, $day, array $db)
{
    $get = $db['get'];
    $school = (int) $school;
    $studentId = (int) $studentId;
    $people = [];

    $principals = $get('SELECT g.ID, g.GUARDIAN_TYPE, g.FULL_NAME, g.PHOTO_PATH, g.STAFF_ID, g.IS_PRIMARY'
        . ' FROM schoolsafe_pickup_guardians g'
        . ' WHERE g.SCHOOL_ID=' . $school . ' AND g.STUDENT_ID=' . $studentId
        . " AND g.GUARDIAN_TYPE IN ('pere','mere','tuteur') AND g.IS_ACTIVE AND g.IS_AUTHORIZED_PICKUP"
        . ' AND g.PHOTO_ACCEPTED AND g.PHOTO_PATH<>\'\''
        . ' ORDER BY g.ID');
    if (is_array($principals)) foreach ($principals as $row) {
        if (!isset($row['ID'], $row['FULL_NAME'])) continue;
        if ($row['STAFF_ID'] !== null && $row['STAFF_ID'] !== '') {
            $link = $get('SELECT 1 FROM students_join_users WHERE STUDENT_ID=' . $studentId
                . ' AND STAFF_ID=' . (int) $row['STAFF_ID']);
            if (!is_array($link) || !isset($link[1])) continue;
        }
        $people[] = [
            'id' => 'g:' . (int) $row['ID'],
            'guardian_id' => (int) $row['ID'],
            'authorization_id' => null,
            'is_principal' => in_array($row['IS_PRIMARY'] ?? false, [true, 't', '1', 1], true),
            'name' => $row['FULL_NAME'],
            'role' => SchoolSafePickupRoleLabel($row['GUARDIAN_TYPE'] ?? 'externe', in_array($row['IS_PRIMARY'] ?? false, [true, 't', '1', 1], true)),
            'photo_path' => $row['PHOTO_PATH'],
            'photo_accepted' => true,
            'pickup_ready' => true,
        ];
    }

    $safeDay = "'" . str_replace("'", "''", $day) . "'";
    $externals = $get('SELECT a.ID AS AUTH_ID, g.ID, g.GUARDIAN_TYPE, g.FULL_NAME, g.PHOTO_PATH'
        . ' FROM schoolsafe_pickup_authorizations a'
        . ' JOIN schoolsafe_pickup_guardians g ON g.ID=a.GUARDIAN_ID AND g.SCHOOL_ID=a.SCHOOL_ID AND g.STUDENT_ID=a.STUDENT_ID'
        . ' WHERE a.SCHOOL_ID=' . $school . ' AND a.STUDENT_ID=' . $studentId
        . " AND a.STATUS='active' AND a.PHOTO_VERIFIED"
        . ' AND (a.STARTS_ON IS NULL OR a.STARTS_ON<=' . $safeDay . ')'
        . ' AND (a.ENDS_ON IS NULL OR a.ENDS_ON>=' . $safeDay . ')'
        . " AND g.GUARDIAN_TYPE='externe' AND g.IS_ACTIVE AND g.PHOTO_ACCEPTED AND g.PHOTO_PATH<>''"
        . ' ORDER BY a.SLOT_NO NULLS LAST, a.ID');
    if (is_array($externals)) foreach ($externals as $row) {
        if (!isset($row['AUTH_ID'], $row['ID'], $row['FULL_NAME'])) continue;
        $people[] = [
            'id' => 'a:' . (int) $row['AUTH_ID'],
            'guardian_id' => (int) $row['ID'],
            'authorization_id' => (int) $row['AUTH_ID'],
            'is_principal' => false,
            'name' => $row['FULL_NAME'],
            'role' => SchoolSafePickupRoleLabel($row['GUARDIAN_TYPE'] ?? 'externe', false),
            'photo_path' => $row['PHOTO_PATH'],
            'photo_accepted' => true,
            'pickup_ready' => true,
        ];
    }
    return $people;
}

function SchoolSafePickupRoleLabel($type, $principal)
{
    if ($principal && in_array($type, ['pere', 'mere', 'tuteur'], true)) {
        return ['pere' => 'Père (principal)', 'mere' => 'Mère (principale)', 'tuteur' => 'Tuteur (principal)'][$type];
    }
    if (in_array($type, ['pere', 'mere', 'tuteur'], true)) {
        return ['pere' => 'Père', 'mere' => 'Mère', 'tuteur' => 'Tuteur'][$type];
    }
    return $principal ? 'Parent principal' : 'Personne autorisée';
}

// Confirmation : preuve valide + personne toujours habilitée → sortie 'out'.
// Preuve déjà utilisée → même remise (idempotence), jamais de doublon.
function SchoolSafePickupConfirm($token, $school, $studentId, $locationId, $user, $day, $clock, array $db, array $verify, array $checkpoint)
{
    $exec = $db['exec'];
    $exec('BEGIN');
    try {
        $result = SchoolSafePickupConfirmLocked($token, $school, $studentId, $locationId, $user, $day, $clock, $db, $verify, $checkpoint);
        $exec('COMMIT');
        return $result;
    } catch (Throwable $error) {
        try { $exec('ROLLBACK'); } catch (Throwable $ignored) { }
        throw $error;
    }
}

// All early refusals, replay and writes run in the transaction owned above.
function SchoolSafePickupConfirmLocked($token, $school, $studentId, $locationId, $user, $day, $clock, array $db, array $verify, array $checkpoint)
{
    $get = $db['get'];
    $exec = $db['exec'];
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
        return ['result' => 'DENIED', 'signature_valid' => true, 'reason' => 'PROOF_UNKNOWN'] + $verify;
    }
    $safe = str_replace("'", "''", $token);
    $rows = $get('SELECT ID, SCHOOL_ID, STUDENT_ID, GUARDIAN_ID, AUTHORIZATION_ID, IS_PRINCIPAL,'
        . ' LOCATION_ID, PREPARED_BY, RECORD_ID, EXPIRES_AT, USED_AT FROM schoolsafe_pickup_proofs'
        . " WHERE TOKEN='" . $safe . "' LIMIT 1 FOR UPDATE");
    if (!is_array($rows) || !isset($rows[1])) {
        return ['result' => 'DENIED', 'signature_valid' => true, 'reason' => 'PROOF_UNKNOWN'] + $verify;
    }
    $proof = $rows[1];
    if ((int) $proof['SCHOOL_ID'] !== (int) $school || (int) $proof['STUDENT_ID'] !== (int) $studentId
        || (int) $proof['LOCATION_ID'] !== (int) $locationId || (int) $proof['PREPARED_BY'] !== (int) $user) {
        return ['result' => 'DENIED', 'signature_valid' => true, 'reason' => 'OUT_OF_SCOPE'] + $verify;
    }
    if (isset($proof['USED_AT']) && $proof['USED_AT'] !== null && $proof['USED_AT'] !== '') {
        $rec = $get('SELECT ID, RECORDED_AT FROM entry_exit_student_records WHERE ID=' . (int) $proof['RECORD_ID']);
        if (!is_array($rec) || !isset($rec[1]['RECORDED_AT']) || (int) $proof['RECORD_ID'] < 1) throw new RuntimeException('PICKUP_RECORD_UNAVAILABLE');
        $at = $rec[1]['RECORDED_AT'];
        return ['result' => 'CONFIRMED', 'decision' => 'allowed', 'idempotent' => true,
            'record_id' => (int) $proof['RECORD_ID'], 'occurred_at' => $at,
            'checkpoint' => $checkpoint] + $verify;
    }
    // Horloge injectée ($day/$clock), jamais l'heure réelle en test.
    $nowStamp = date('Y-m-d H:i:s', strtotime($day . ' ' . $clock));
    if (strcmp((string) $proof['EXPIRES_AT'], $nowStamp) <= 0) {
        return ['result' => 'DENIED', 'signature_valid' => true, 'reason' => 'PROOF_EXPIRED'] + $verify;
    }
    // Lock identity and authorization before re-reading eligibility. Revocation
    // or photo replacement cannot interleave between this check and the exit.
    $guardian = $get('SELECT ID FROM schoolsafe_pickup_guardians WHERE ID=' . (int) $proof['GUARDIAN_ID']
        . ' AND SCHOOL_ID=' . (int) $school . ' AND STUDENT_ID=' . (int) $studentId . ' FOR UPDATE');
    if (!is_array($guardian) || !isset($guardian[1])) return ['result'=>'DENIED','signature_valid'=>true,'reason'=>'NOT_DELEGATED'] + $verify;
    if ($proof['AUTHORIZATION_ID'] !== null) {
        $authorization = $get('SELECT ID FROM schoolsafe_pickup_authorizations WHERE ID=' . (int) $proof['AUTHORIZATION_ID']
            . ' AND GUARDIAN_ID=' . (int) $proof['GUARDIAN_ID'] . ' AND SCHOOL_ID=' . (int) $school
            . ' AND STUDENT_ID=' . (int) $studentId . ' FOR UPDATE');
        if (!is_array($authorization) || !isset($authorization[1])) return ['result'=>'DENIED','signature_valid'=>true,'reason'=>'NOT_DELEGATED'] + $verify;
    }
    // La personne doit être habilitée au moment de confirmer, pas seulement de préparer.
    $candidates = SchoolSafePickupCandidates($school, $studentId, $day, $db);
    $picked = null;
    foreach ($candidates as $candidate) {
        if ((int) $candidate['guardian_id'] === (int) $proof['GUARDIAN_ID']) { $picked = $candidate; break; }
    }
    $authOk = $picked !== null
        && (($proof['AUTHORIZATION_ID'] === null && $picked['authorization_id'] === null)
            || (int) $picked['authorization_id'] === (int) $proof['AUTHORIZATION_ID']);
    if (!$authOk) {
        return ['result' => 'DENIED', 'signature_valid' => true, 'reason' => 'NOT_DELEGATED'] + $verify;
    }
        $label = "Sortie " . substr($clock, 0, 5) . ' — ' . $picked['name'] . ' — staff #' . $user;
        $commentSql = "'" . str_replace("'", "''", $label) . "'";
        $inserted = $get('INSERT INTO entry_exit_student_records(STUDENT_ID, CHECKPOINT_ID, TYPE, COMMENTS, RECORDED_AT)'
            . ' VALUES(' . $studentId . ',' . (int) $locationId . ",'out'," . $commentSql . ',CURRENT_TIMESTAMP)'
            . ' RETURNING ID, RECORDED_AT');
        if (!is_array($inserted) || !isset($inserted[1])) throw new RuntimeException('PICKUP_WRITE_FAILED');
        $recordId = (int) $inserted[1]['ID'];
        $exec('UPDATE schoolsafe_pickup_proofs SET USED_AT=CURRENT_TIMESTAMP, RECORD_ID=' . $recordId
            . " WHERE ID=" . (int) $proof['ID'] . ' AND USED_AT IS NULL');
        return ['result' => 'CONFIRMED', 'decision' => 'allowed', 'idempotent' => false,
            'record_id' => $recordId, 'occurred_at' => $inserted[1]['RECORDED_AT'],
            'person' => $picked, 'checkpoint' => $checkpoint] + $verify;
}
