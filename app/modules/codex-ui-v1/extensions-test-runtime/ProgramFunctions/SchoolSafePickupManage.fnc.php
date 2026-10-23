<?php
// SchoolSafe — Lot PICKUP : rattachement et validation des personnes (Rosario moteur principal).
//
// Le parent principal habilité présente la personne à l'école ; le personnel
// vérifie l'identité, contrôle l'autorité du demandeur et valide le
// rattachement. Aucune personne externe n'est un compte : jamais de doublon
// avec `staff`. Les principaux restent liés par `students_join_users`.
// Photo obligatoire : `photo-set` enregistre le chemin (même origine, sans
// traversal, fichier existant sous la racine), `photo-review` valide le cadre
// (visage + haut du corps, humain obligatoire) ; sans photo validée, aucune
// récupération (`pickup_ready` faux, bouton inactif côté client).
//
// $ctx : ['user'=>int, 'school'=>int, 'year'=>int, 'profile'=>string,
//          'parent'=>bool (lien parent-enfant vérifié par l'appelant),
//          'root'=>string (racine web pour les photos, défaut '.')].
// $db : ['get'=>callable(string):array, 'exec'=>callable(string):void].
// Lève RuntimeException (INVALID_CONTEXT, NOT_FOUND, FORBIDDEN, CONFLICT,
// INVALID_PHOTO, LIMIT_REACHED, REASON_REQUIRED...) : l'appelant mappe en HTTP.

function SchoolSafePickupManageContext(array $ctx): array
{
    $school = (int) ($ctx['school'] ?? 0);
    $year = (int) ($ctx['year'] ?? 0);
    $user = (int) ($ctx['user'] ?? 0);
    if ($school < 1 || $year < 1 || $user < 1) throw new RuntimeException('INVALID_CONTEXT');
    $ctx['school'] = $school;
    $ctx['year'] = $year;
    $ctx['user'] = $user;
    return $ctx;
}

function SchoolSafePickupStudentEnrolled($studentId, array $ctx, array $db): void
{
    $rows = $db['get']('SELECT 1 FROM student_enrollment WHERE STUDENT_ID=' . (int) $studentId
        . ' AND SCHOOL_ID=' . $ctx['school'] . ' AND SYEAR=' . $ctx['year']);
    if (!is_array($rows) || !isset($rows[1])) throw new RuntimeException('NOT_FOUND');
}

function SchoolSafePickupIsStaff(array $ctx): bool
{
    return in_array($ctx['profile'] ?? '', ['admin', 'teacher'], true);
}

function SchoolSafePickupRequireStaff(array $ctx): void
{
    if (!SchoolSafePickupIsStaff($ctx)) throw new RuntimeException('FORBIDDEN');
}

function SchoolSafePickupReason($reason): string
{
    if (!is_string($reason) || trim($reason) === '' || strlen($reason) > 500) {
        throw new RuntimeException('REASON_REQUIRED');
    }
    return trim($reason);
}

// Chemin photo : même origine, relatif, sans traversal, extension image,
// fichier réel sous la racine web (jamais de fuite de chemin OS en réponse).
function SchoolSafePickupPhotoPath($path, $root): string
{
    if (!is_string($path) || !preg_match('#^/[A-Za-z0-9_][A-Za-z0-9_./-]{0,400}\.(jpg|jpeg|png|webp)$#D', $path)
        || strpos($path, '..') !== false) {
        throw new RuntimeException('INVALID_PHOTO');
    }
    $root = $root === '' ? '.' : $root;
    $base = realpath($root);
    $file = realpath(rtrim($root, '/\\') . $path);
    if ($base === false || $file === false || !is_file($file)) throw new RuntimeException('INVALID_PHOTO');
    if (strpos($file, rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) !== 0) {
        throw new RuntimeException('INVALID_PHOTO');
    }
    return $path;
}

// pg_fetch_assoc returns PostgreSQL booleans as 't'/'f'. Unknown values
// must fail closed, never become true through PHP's non-empty string rules.
function SchoolSafePickupDatabaseFlags(array $row): array
{
    foreach (['IS_PRIMARY', 'IS_ACTIVE', 'IS_AUTHORIZED_PICKUP', 'PHOTO_ACCEPTED', 'PHOTO_VERIFIED'] as $key) {
        if (array_key_exists($key, $row)) $row[$key] = in_array($row[$key], [true, 't', '1', 1], true);
    }
    return $row;
}

function SchoolSafePickupGuardian($guardianId, $studentId, array $ctx, array $db): array
{
    $rows = $db['get']('SELECT ID, SCHOOL_ID, STUDENT_ID, GUARDIAN_TYPE, FULL_NAME, PHONE, STAFF_ID,'
        . ' IS_PRIMARY, IS_ACTIVE, IS_AUTHORIZED_PICKUP, PHOTO_PATH, PHOTO_ACCEPTED'
        . ' FROM schoolsafe_pickup_guardians WHERE ID=' . (int) $guardianId
        . ' AND STUDENT_ID=' . (int) $studentId . ' AND SCHOOL_ID=' . $ctx['school'] . ' LIMIT 1');
    if (!is_array($rows) || !isset($rows[1])) throw new RuntimeException('NOT_FOUND');
    return SchoolSafePickupDatabaseFlags($rows[1]);
}

function SchoolSafePickupReady(array $guardian, $auth, $day): bool
{
    $guardian = SchoolSafePickupDatabaseFlags($guardian);
    if (empty($guardian['IS_ACTIVE']) || empty($guardian['PHOTO_ACCEPTED']) || empty($guardian['PHOTO_PATH'])) {
        return false;
    }
    if (in_array($guardian['GUARDIAN_TYPE'] ?? '', ['pere', 'mere', 'tuteur'], true)) {
        return !empty($guardian['IS_AUTHORIZED_PICKUP']);
    }
    if (($guardian['GUARDIAN_TYPE'] ?? '') !== 'externe' || !is_array($auth)) return false;
    $auth = SchoolSafePickupDatabaseFlags($auth);
    if (($auth['STATUS'] ?? '') !== 'active' || empty($auth['PHOTO_VERIFIED'])) return false;
    if (!empty($auth['STARTS_ON']) && strcmp($auth['STARTS_ON'], $day) > 0) return false;
    if (!empty($auth['ENDS_ON']) && strcmp($auth['ENDS_ON'], $day) < 0) return false;
    return true;
}

// Groupes 'family' / 'accredited' attendus par l'interface (photos + statuts).
function SchoolSafePickupList($studentId, array $ctx, array $db, $day = null)
{
    $ctx = SchoolSafePickupManageContext($ctx);
    $studentId = (int) $studentId;
    if ($studentId < 1) throw new RuntimeException('NOT_FOUND');
    SchoolSafePickupStudentEnrolled($studentId, $ctx, $db);
    if (!SchoolSafePickupIsStaff($ctx) && empty($ctx['parent'])) throw new RuntimeException('FORBIDDEN');
    $day = $day === null ? date('Y-m-d') : $day;
    $guardians = $db['get']('SELECT ID, GUARDIAN_TYPE, FULL_NAME, STAFF_ID, IS_PRIMARY, IS_ACTIVE,'
        . ' IS_AUTHORIZED_PICKUP, PHOTO_PATH, PHOTO_ACCEPTED FROM schoolsafe_pickup_guardians'
        . ' WHERE STUDENT_ID=' . $studentId . ' AND SCHOOL_ID=' . $ctx['school'] . ' ORDER BY ID');
    $family = [];
    $accredited = [];
    if (is_array($guardians)) foreach ($guardians as $g) {
        if (!isset($g['ID'])) continue;
        $g = SchoolSafePickupDatabaseFlags($g);
        $auths = $db['get']('SELECT ID, STATUS, SLOT_NO, PHOTO_VERIFIED, STARTS_ON, ENDS_ON'
            . ' FROM schoolsafe_pickup_authorizations WHERE GUARDIAN_ID=' . (int) $g['ID']
            . ' AND STUDENT_ID=' . $studentId . ' AND SCHOOL_ID=' . $ctx['school'] . ' ORDER BY ID DESC');
        $auth = (is_array($auths) && isset($auths[1])) ? $auths[1] : null;
        $row = [
            'guardian_id' => (string) $g['ID'],
            'name' => $g['FULL_NAME'],
            'is_primary' => !empty($g['IS_PRIMARY']),
            'pickup_ready' => SchoolSafePickupReady($g, $auth, $day),
            'photo_path' => !empty($g['PHOTO_ACCEPTED']) ? $g['PHOTO_PATH'] : '',
            'authorization_id' => $auth === null ? '' : (string) $auth['ID'],
            'authorization_status' => $auth === null ? '' : $auth['STATUS'],
        ];
        if (in_array($g['GUARDIAN_TYPE'] ?? '', ['pere', 'mere', 'tuteur'], true)) $family[] = $row;
        elseif (($g['GUARDIAN_TYPE'] ?? '') === 'externe') $accredited[] = $row;
    }
    return ['family' => $family, 'accredited' => $accredited];
}

// Enregistre un responsable familial (Père/Mère/Tuteur) : en attente,
// récupération NON autorisée. Le premier principal exige is_primary.
function SchoolSafePickupRegisterFamily($studentId, array $fields, array $ctx, array $db)
{
    $ctx = SchoolSafePickupManageContext($ctx);
    SchoolSafePickupRequireStaff($ctx);
    $studentId = (int) $studentId;
    if ($studentId < 1) throw new RuntimeException('NOT_FOUND');
    SchoolSafePickupStudentEnrolled($studentId, $ctx, $db);
    $type = $fields['guardianType'] ?? '';
    if (!in_array($type, ['pere', 'mere', 'tuteur'], true)) throw new RuntimeException('INVALID_PERSON');
    $name = trim((string) ($fields['fullName'] ?? ''));
    if ($name === '' || strlen($name) > 200) throw new RuntimeException('INVALID_PERSON');
    $phone = substr(trim((string) ($fields['phone'] ?? '')), 0, 64);
    $wantPrimary = !empty($fields['isPrimary']);
    $existing = $db['get']('SELECT ID FROM schoolsafe_pickup_guardians WHERE STUDENT_ID=' . $studentId
        . ' AND SCHOOL_ID=' . $ctx['school'] . ' AND IS_PRIMARY AND IS_ACTIVE LIMIT 1');
    $hasPrimary = is_array($existing) && isset($existing[1]);
    if (!$hasPrimary && !$wantPrimary) throw new RuntimeException('PRIMARY_SELECTION_REQUIRED');
    if ($hasPrimary && $wantPrimary) throw new RuntimeException('PRIMARY_EXISTS');
    $q = function ($v) { return "'" . str_replace("'", "''", $v) . "'"; };
    $row = $db['get']('INSERT INTO schoolsafe_pickup_guardians(school_id, student_id, guardian_type,'
        . ' full_name, phone, is_primary, is_active, is_authorized_pickup)'
        . ' VALUES(' . $ctx['school'] . ',' . $studentId . ',' . $q($type) . ',' . $q($name) . ',' . $q($phone)
        . ',' . ($wantPrimary ? 'TRUE' : 'FALSE') . ',TRUE,FALSE) RETURNING ID');
    if (!is_array($row) || !isset($row[1])) throw new RuntimeException('WRITE_FAILED');
    return ['guardian_id' => (string) $row[1]['ID'], 'status' => 'pending',
        'is_primary' => $wantPrimary];
}

// Enregistre une demande d'accréditation externe : personne + autorisation
// pending, consentement parental et motif obligatoires, photo à suivre.
function SchoolSafePickupRegisterExternal($studentId, array $fields, array $ctx, array $db)
{
    $ctx = SchoolSafePickupManageContext($ctx);
    SchoolSafePickupRequireStaff($ctx);
    $studentId = (int) $studentId;
    if ($studentId < 1) throw new RuntimeException('NOT_FOUND');
    SchoolSafePickupStudentEnrolled($studentId, $ctx, $db);
    $name = trim((string) ($fields['fullName'] ?? ''));
    if ($name === '' || strlen($name) > 200) throw new RuntimeException('INVALID_PERSON');
    $reason = SchoolSafePickupReason($fields['reason'] ?? '');
    $consent = trim((string) ($fields['consent'] ?? ''));
    if ($consent === '' || strlen($consent) > 500) throw new RuntimeException('CONSENT_REQUIRED');
    $phone = substr(trim((string) ($fields['phone'] ?? '')), 0, 64);
    $q = function ($v) { return "'" . str_replace("'", "''", $v) . "'"; };
    $g = $db['get']('INSERT INTO schoolsafe_pickup_guardians(school_id, student_id, guardian_type,'
        . ' full_name, phone, is_primary, is_active, is_authorized_pickup)'
        . ' VALUES(' . $ctx['school'] . ',' . $studentId . ",'externe'," . $q($name) . ',' . $q($phone)
        . ',FALSE,TRUE,FALSE) RETURNING ID');
    if (!is_array($g) || !isset($g[1])) throw new RuntimeException('WRITE_FAILED');
    $gid = (int) $g[1]['ID'];
    $a = $db['get']('INSERT INTO schoolsafe_pickup_authorizations(guardian_id, student_id, school_id,'
        . " status, photo_verified, requested_by, reason) VALUES(" . $gid . ',' . $studentId . ','
        . $ctx['school'] . ",'pending',FALSE," . $ctx['user'] . ',' . $q($reason . ' — ' . $consent) . ') RETURNING ID');
    if (!is_array($a) || !isset($a[1])) throw new RuntimeException('WRITE_FAILED');
    return ['guardian_id' => (string) $gid, 'authorization_id' => (string) $a[1]['ID'], 'status' => 'pending'];
}

// Le personnel valide la récupération par une personne familiale : photo
// validée exigée, demande présentée à l'école + consentement exigés.
function SchoolSafePickupApproveFamily($studentId, $guardianId, $reason, $consent, array $ctx, array $db)
{
    $ctx = SchoolSafePickupManageContext($ctx);
    SchoolSafePickupRequireStaff($ctx);
    $reason = SchoolSafePickupReason($reason);
    $consent = trim((string) $consent);
    if ($consent === '' || strlen($consent) > 500) throw new RuntimeException('CONSENT_REQUIRED');
    $g = SchoolSafePickupGuardian($guardianId, $studentId, $ctx, $db);
    if (empty($g['IS_ACTIVE']) || !in_array($g['GUARDIAN_TYPE'], ['pere', 'mere', 'tuteur'], true)) {
        throw new RuntimeException('INVALID_PERSON');
    }
    if (empty($g['PHOTO_ACCEPTED']) || empty($g['PHOTO_PATH'])) throw new RuntimeException('PHOTO_NOT_VALIDATED');
    $db['exec']('UPDATE schoolsafe_pickup_guardians SET IS_AUTHORIZED_PICKUP=TRUE, UPDATED_AT=CURRENT_TIMESTAMP'
        . ' WHERE ID=' . (int) $g['ID']);
    return ['guardian_id' => (string) $g['ID'], 'status' => 'active'];
}

function SchoolSafePickupPhotoSet($studentId, $guardianId, $photoPath, array $ctx, array $db)
{
    $ctx = SchoolSafePickupManageContext($ctx);
    SchoolSafePickupRequireStaff($ctx);
    $g = SchoolSafePickupGuardian($guardianId, $studentId, $ctx, $db);
    if (empty($g['IS_ACTIVE'])) throw new RuntimeException('INVALID_PERSON');
    $path = SchoolSafePickupPhotoPath($photoPath, $ctx['root'] ?? '.');
    $q = "'" . str_replace("'", "''", $path) . "'";
    $db['exec']('UPDATE schoolsafe_pickup_guardians SET PHOTO_PATH=' . $q
        . ', PHOTO_ACCEPTED=FALSE, UPDATED_AT=CURRENT_TIMESTAMP WHERE ID=' . (int) $g['ID']);
    return ['guardian_id' => (string) $g['ID'], 'status' => 'pending'];
}

// Validation humaine du cadre (visage + haut du corps, tête→taille) : accepter
// propage aux autorisations, refuser ne supprime jamais le dossier.
function SchoolSafePickupPhotoReview($studentId, $guardianId, $decision, $reason, array $ctx, array $db)
{
    $ctx = SchoolSafePickupManageContext($ctx);
    SchoolSafePickupRequireStaff($ctx);
    if (!in_array($decision, ['accepted', 'rejected'], true)) throw new RuntimeException('INVALID_DECISION');
    SchoolSafePickupReason($reason);
    $g = SchoolSafePickupGuardian($guardianId, $studentId, $ctx, $db);
    if (empty($g['IS_ACTIVE']) || empty($g['PHOTO_PATH'])) throw new RuntimeException('INVALID_PERSON');
    $ok = $decision === 'accepted' ? 'TRUE' : 'FALSE';
    $db['exec']('UPDATE schoolsafe_pickup_guardians SET PHOTO_ACCEPTED=' . $ok
        . ', UPDATED_AT=CURRENT_TIMESTAMP WHERE ID=' . (int) $g['ID']);
    if ($decision === 'accepted') {
        $db['exec']('UPDATE schoolsafe_pickup_authorizations SET PHOTO_VERIFIED=TRUE, UPDATED_AT=CURRENT_TIMESTAMP'
            . ' WHERE GUARDIAN_ID=' . (int) $g['ID']);
    }
    return ['guardian_id' => (string) $g['ID'], 'status' => $decision];
}

// Active une accréditation : photo vérifiée, fenêtre valide, max 3 actives.
function SchoolSafePickupValidateAuthorization($studentId, $authorizationId, array $ctx, array $db, $day = null, $asParent = false)
{
    $ctx = SchoolSafePickupManageContext($ctx);
    // $asParent : réactivation par le principal lui-même (propriété déjà
    // vérifiée par l'appelant) — mêmes contrôles métier, sans rôle staff.
    if (!$asParent) SchoolSafePickupRequireStaff($ctx);
    elseif (SchoolSafePickupIsStaff($ctx) || ($ctx['parent'] ?? false) !== true) throw new RuntimeException('FORBIDDEN');
    $day = $day === null ? date('Y-m-d') : $day;
    $rows = $db['get']('SELECT ID, GUARDIAN_ID, STUDENT_ID, SCHOOL_ID, STATUS, PHOTO_VERIFIED, STARTS_ON, ENDS_ON'
        . ' FROM schoolsafe_pickup_authorizations WHERE ID=' . (int) $authorizationId
        . ' AND STUDENT_ID=' . (int) $studentId . ' AND SCHOOL_ID=' . $ctx['school'] . ' LIMIT 1');
    if (!is_array($rows) || !isset($rows[1])) throw new RuntimeException('NOT_FOUND');
    $a = SchoolSafePickupDatabaseFlags($rows[1]);
    if (($a['STATUS'] ?? '') !== ($asParent ? 'suspended' : 'pending')) throw new RuntimeException('INVALID_STATE');
    $g = SchoolSafePickupGuardian($a['GUARDIAN_ID'], $studentId, $ctx, $db);
    if (empty($g['IS_ACTIVE']) || ($g['GUARDIAN_TYPE'] ?? '') !== 'externe') throw new RuntimeException('INVALID_PERSON');
    if (empty($a['PHOTO_VERIFIED']) || empty($g['PHOTO_ACCEPTED']) || empty($g['PHOTO_PATH'])) throw new RuntimeException('PHOTO_NOT_VALIDATED');
    if (!empty($a['STARTS_ON']) && strcmp($a['STARTS_ON'], $day) > 0) throw new RuntimeException('INVALID_STATE');
    if (!empty($a['ENDS_ON']) && strcmp($a['ENDS_ON'], $day) < 0) throw new RuntimeException('INVALID_STATE');
    $active = $db['get']('SELECT COALESCE(MIN(SLOT_NO),0) AS TAKEN1 FROM schoolsafe_pickup_authorizations'
        . ' WHERE SCHOOL_ID=' . $ctx['school'] . ' AND STUDENT_ID=' . (int) $studentId
        . " AND STATUS='active'");
    $count = $db['get']('SELECT COUNT(*) AS N FROM schoolsafe_pickup_authorizations'
        . ' WHERE SCHOOL_ID=' . $ctx['school'] . ' AND STUDENT_ID=' . (int) $studentId
        . " AND STATUS='active'");
    $n = (is_array($count) && isset($count[1])) ? (int) $count[1]['N'] : 99;
    if ($n >= 3) throw new RuntimeException('LIMIT_REACHED');
    $used = [];
    $slots = $db['get']('SELECT SLOT_NO FROM schoolsafe_pickup_authorizations'
        . ' WHERE SCHOOL_ID=' . $ctx['school'] . ' AND STUDENT_ID=' . (int) $studentId
        . " AND STATUS='active' AND SLOT_NO IS NOT NULL");
    if (is_array($slots)) foreach ($slots as $s) { $used[(int) $s['SLOT_NO']] = true; }
    $slot = null;
    foreach ([1, 2, 3] as $candidate) { if (!isset($used[$candidate])) { $slot = $candidate; break; } }
    if ($slot === null) throw new RuntimeException('LIMIT_REACHED');
    $db['exec']('UPDATE schoolsafe_pickup_authorizations SET STATUS=' . "'active', SLOT_NO=" . $slot
        . ', UPDATED_AT=CURRENT_TIMESTAMP WHERE ID=' . (int) $a['ID']);
    return ['authorization_id' => (string) $a['ID'], 'status' => 'active', 'slot_no' => $slot];
}

// Retire une personne sur demande reçue à l'école : jamais le principal
// (transfert = procédure d'école séparée), historique conservé.
function SchoolSafePickupRetire($studentId, $guardianId, $reason, $consent, array $ctx, array $db)
{
    $ctx = SchoolSafePickupManageContext($ctx);
    SchoolSafePickupRequireStaff($ctx);
    $reason = SchoolSafePickupReason($reason);
    $consent = trim((string) $consent);
    if ($consent === '' || strlen($consent) > 500) throw new RuntimeException('CONSENT_REQUIRED');
    $g = SchoolSafePickupGuardian($guardianId, $studentId, $ctx, $db);
    if (empty($g['IS_ACTIVE'])) throw new RuntimeException('INVALID_STATE');
    if (!empty($g['IS_PRIMARY'])) throw new RuntimeException('PRIMARY_TRANSFER_REQUIRED');
    $db['exec']('UPDATE schoolsafe_pickup_authorizations SET STATUS=' . "'revoked'"
        . ', UPDATED_AT=CURRENT_TIMESTAMP WHERE GUARDIAN_ID=' . (int) $g['ID'] . " AND STATUS<>'revoked'");
    $db['exec']('UPDATE schoolsafe_pickup_guardians SET IS_ACTIVE=FALSE, IS_AUTHORIZED_PICKUP=FALSE,'
        . ' UPDATED_AT=CURRENT_TIMESTAMP WHERE ID=' . (int) $g['ID']);
    return ['guardian_id' => (string) $g['ID'], 'status' => 'revoked'];
}

// Le principal suspend sa propre accréditation / la réactive (fenêtre + photo
// + slot toujours contrôlés à la réactivation).
function SchoolSafePickupParentSuspend($studentId, $authorizationId, $reason, array $ctx, array $db)
{
    $ctx = SchoolSafePickupManageContext($ctx);
    if (SchoolSafePickupIsStaff($ctx) || empty($ctx['parent'])) throw new RuntimeException('FORBIDDEN');
    $reason = SchoolSafePickupReason($reason);
    $rows = $db['get']('SELECT ID, GUARDIAN_ID, STATUS, REQUESTED_BY FROM schoolsafe_pickup_authorizations'
        . ' WHERE ID=' . (int) $authorizationId . ' AND STUDENT_ID=' . (int) $studentId
        . ' AND SCHOOL_ID=' . $ctx['school'] . ' LIMIT 1');
    if (!is_array($rows) || !isset($rows[1])) throw new RuntimeException('NOT_FOUND');
    $a = $rows[1];
    if (($a['STATUS'] ?? '') !== 'active' || (int) $a['REQUESTED_BY'] !== $ctx['user']) {
        throw new RuntimeException('FORBIDDEN');
    }
    $db['exec']('UPDATE schoolsafe_pickup_authorizations SET STATUS=' . "'suspended'"
        . ', UPDATED_AT=CURRENT_TIMESTAMP WHERE ID=' . (int) $a['ID']);
    return ['authorization_id' => (string) $a['ID'], 'status' => 'suspended'];
}

function SchoolSafePickupParentActivate($studentId, $authorizationId, $reason, array $ctx, array $db, $day = null)
{
    $ctx = SchoolSafePickupManageContext($ctx);
    if (SchoolSafePickupIsStaff($ctx) || empty($ctx['parent'])) throw new RuntimeException('FORBIDDEN');
    $reason = SchoolSafePickupReason($reason);
    $rows = $db['get']('SELECT ID, GUARDIAN_ID, STATUS, REQUESTED_BY FROM schoolsafe_pickup_authorizations'
        . ' WHERE ID=' . (int) $authorizationId . ' AND STUDENT_ID=' . (int) $studentId
        . ' AND SCHOOL_ID=' . $ctx['school'] . ' LIMIT 1');
    if (!is_array($rows) || !isset($rows[1])) throw new RuntimeException('NOT_FOUND');
    $a = $rows[1];
    if (($a['STATUS'] ?? '') !== 'suspended' || (int) $a['REQUESTED_BY'] !== $ctx['user']) {
        throw new RuntimeException('FORBIDDEN');
    }
    $res = SchoolSafePickupValidateAuthorization($studentId, $authorizationId, $ctx, $db, $day, true);
    // La réactivation parentale repasse par les mêmes contrôles qu'une validation.
    $db['exec']('UPDATE schoolsafe_pickup_authorizations SET REQUESTED_BY=' . $ctx['user']
        . ' WHERE ID=' . (int) $a['ID']);
    return $res + ['reactivated_by_parent' => true];
}
