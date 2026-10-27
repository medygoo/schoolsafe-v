<?php
/**
 * Device Hub — remontée de l'état d'enrôlement des empreintes.
 *
 * Le pont local (machine de l'école, même réseau que l'appareil) lit sur le
 * terminal le nombre d'empreintes de chaque utilisateur (ISAPI UserInfo) et
 * le pousse ici. L'application affiche alors « Empreintes : N/5 » par
 * personnel — la validation de l'enrôlement dans l'application.
 *
 * Sécurité : POST + le même jeton partagé que la poussée de pointages
 * (comparaison à temps constant), validation stricte, UPSERT seul —
 * aucune suppression, aucune autre écriture.
 */

header('Content-Type: application/json; charset=utf-8');

$DEVICE_ID = 1;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'POST_REQUIRED']));
}

$tokenFile = '/var/lib/schoolsafe-device-hub/push-token';
$attendu = is_file($tokenFile) ? trim((string) file_get_contents($tokenFile)) : '';
$recu = (string) ($_GET['token'] ?? '');
if ($attendu === '' || strlen($recu) !== strlen($attendu) || !hash_equals($attendu, $recu)) {
    http_response_code(401);
    exit(json_encode(['error' => 'INVALID_TOKEN']));
}

$raw = file_get_contents('php://input', false, null, 0, 50001);
if ($raw === false || strlen($raw) > 50000) {
    http_response_code(413);
    exit(json_encode(['error' => 'TOO_LARGE']));
}

$data = json_decode($raw, true);
$users = is_array($data) ? ($data['users'] ?? null) : null;
if (!is_array($users) || count($users) < 1 || count($users) > 200) {
    http_response_code(400);
    exit(json_encode(['error' => 'BATCH_INVALID']));
}

$propres = [];
foreach ($users as $u) {
    if (!is_array($u)) continue;
    $emp = (string) ($u['employee_id'] ?? '');
    $num = filter_var($u['num_fp'] ?? null, FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0, 'max_range' => 10]]);
    if (!preg_match('/^[a-zA-Z0-9._:-]{1,64}$/', $emp) || $num === false) continue;
    $propres[] = ['employee_id' => $emp, 'num_fp' => (int) $num];
}
if (!$propres) {
    http_response_code(400);
    exit(json_encode(['error' => 'NO_VALID_USER']));
}

$config = require '/var/www/html/config.inc.php';
$conn = @pg_connect('host=' . $DatabaseServer . ' port=' . ($DatabasePort ?: 5432) .
    ' dbname=' . $DatabaseName . ' user=' . $DatabaseUsername . ' password=' . $DatabasePassword);
if (!$conn) {
    http_response_code(503);
    exit(json_encode(['error' => 'DB_UNAVAILABLE']));
}

$maj = 0;
foreach ($propres as $u) {
    $r = pg_query_params($conn,
        'INSERT INTO schoolsafe_device_enroll (device_id, employee_id, num_fp, checked_at)
         VALUES ($1, $2, $3, now())
         ON CONFLICT (device_id, employee_id)
         DO UPDATE SET num_fp = EXCLUDED.num_fp, checked_at = now()',
        [$DEVICE_ID, $u['employee_id'], $u['num_fp']]);
    if ($r) $maj++;
}

echo json_encode(['maj' => $maj, 'total' => count($propres)]);
