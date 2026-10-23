<?php
// SchoolSafe — Lot A1 : vérification QR locale (Rosario moteur principal).
//
// Vérifie une carte `schoolsafe://card/<card_number>/<signature>` avec le même
// secret HMAC-SHA256 que le moteur natif (base64url, 32 caractères), contre le
// registre local `schoolsafe_student_cards` (migration 020) et l'inscription
// Rosario (`student_enrollment`, école + année).
//
// - Ne lit le secret que depuis son paramètre : l'appelant le tire de
//   l'environnement serveur (`SCHOOLSAFE_CARD_HMAC_SECRET`), jamais de git.
// - Ne modifie rien : lecture seule. Retourne null quand la vérification
//   locale est impossible (secret invalide) : l'appelant applique alors le
//   repli existant (bridge, puis 503). Jamais de faux positif.
// - Codes alignés sur le moteur natif : VALID, INVALID (+reason), LOST,
//   REPLACED, REVOKED, STUDENT_NOT_OPERATIONAL.
//
// $query : callable (string $cardNumber, int $school) => ?array, avec les clés
// school_id, student_id, card_number, signature, status, first_name,
// last_name, class_name, enrolled (bool : inscription école+année).
function SchoolSafeCardVerifyQr($qr, $secretHex, $school, $year, callable $query)
{
    if (!is_string($qr) || strlen($qr) > 2000) {
        return ['result' => 'INVALID', 'signature_valid' => false, 'reason' => 'invalid_qr'];
    }
    if (!preg_match('#^schoolsafe://card/([A-Za-z0-9_\\-]{1,64})/([A-Za-z0-9_\\-]{32})$#D', $qr, $m)) {
        return ['result' => 'INVALID', 'signature_valid' => false, 'reason' => 'invalid_qr'];
    }
    list(, $number, $signature) = $m;
    if (!is_string($secretHex) || !preg_match('/^[a-f0-9]{64}$/D', $secretHex)) {
        return null;
    }
    $expected = substr(strtr(base64_encode(hash_hmac('sha256', $number, hex2bin($secretHex), true)), '+/', '-_'), 0, 32);
    if (!hash_equals($expected, $signature)) {
        return ['result' => 'INVALID', 'signature_valid' => false, 'reason' => 'signature_mismatch'];
    }
    $row = $query($number, (int) $school);
    if (!is_array($row)) {
        return ['result' => 'INVALID', 'signature_valid' => true, 'reason' => 'card_not_found'];
    }
    $identity = [
        'signature_valid' => true,
        'status' => $row['status'],
        'nativeStudentId' => (int) $row['student_id'],
        'student_id' => (int) $row['student_id'],
        'student_name' => trim($row['first_name'] . ' ' . $row['last_name']),
        'class_name' => $row['class_name'],
    ];
    if ($row['status'] !== 'active') {
        $refused = ['lost' => 'LOST', 'replaced' => 'REPLACED', 'revoked' => 'REVOKED'];
        return ['result' => $refused[$row['status']] ?? 'INVALID', 'reason' => $row['status']] + $identity;
    }
    if (empty($row['enrolled'])) {
        return ['result' => 'STUDENT_NOT_OPERATIONAL', 'reason' => 'student_missing'] + $identity;
    }
    return ['result' => 'VALID'] + $identity;
}

// Requête réelle sur la base Rosario : registre (école portée par la ligne)
// + inscription école/année + identité + classe (niveau). Retourne null si la
// table 020 n'est pas appliquée. Entiers castés avant interpolation.
function SchoolSafeCardVerifyQuery($number, $school, $year, $dbget = 'DBGet')
{
    static $hasTable = null;
    if ($hasTable === null) {
        $check = $dbget("SELECT to_regclass('public.schoolsafe_student_cards') AS t");
        $found = is_array($check) ? ($check[1]['T'] ?? '') : '';
        $hasTable = $found !== '' && substr($found, -strlen('schoolsafe_student_cards')) === 'schoolsafe_student_cards';
    }
    if (!$hasTable) return null;
    $school = (int) $school;
    $year = (int) $year;
    $safe = str_replace("'", "''", $number);
    $rows = $dbget("SELECT c.student_id, c.signature, c.status, s.first_name, s.last_name,"
        . " (SELECT TITLE FROM school_gradelevels g JOIN student_enrollment e2 ON e2.GRADE_ID=g.ID"
        . " WHERE e2.STUDENT_ID=c.student_id AND e2.SCHOOL_ID=" . $school . " AND e2.SYEAR=" . $year . " LIMIT 1) AS class_name,"
        . " EXISTS(SELECT 1 FROM student_enrollment e WHERE e.STUDENT_ID=c.student_id"
        . " AND e.SCHOOL_ID=" . $school . " AND e.SYEAR=" . $year . ") AS enrolled"
        . " FROM schoolsafe_student_cards c JOIN students s ON s.student_id=c.student_id"
        . " WHERE c.card_number='" . $safe . "' AND c.school_id=" . $school . " LIMIT 1");
    if (!is_array($rows) || !isset($rows[1])) return null;
    $r = $rows[1];
    return [
        'school_id' => $school, 'student_id' => (int) $r['STUDENT_ID'], 'card_number' => $number,
        'signature' => $r['SIGNATURE'], 'status' => $r['STATUS'],
        'first_name' => $r['FIRST_NAME'], 'last_name' => $r['LAST_NAME'],
        'class_name' => $r['CLASS_NAME'] ?? '', 'enrolled' => in_array($r['ENROLLED'], [true, 't', '1', 1], true),
    ];
}
