<?php
/**
 * Device Hub — récepteur de poussée des événements de présence.
 *
 * Pour les appareils derrière une box sans IP publique (Airtel/CGNAT) :
 * l'appareil pousse ses événements vers cet endpoint (sens sortant ✓).
 *
 * Deux formats acceptés :
 *  1. Normalisé (relais local / tests) : {"events":[{"id","employee_id","time","direction"},...]}
 *  2. Hikvision natif EventNotificationAlert (un pointage par POST), XML ou JSON :
 *     l'appareil (Network > Advanced > Integration Protocol) poste chaque
 *     vérification d'empreinte : employeeNoString + dateTime + eventType
 *     accessControllerEvent.
 *
 * Sécurité :
 *  - POST uniquement + jeton partagé (comparaison à temps constant)
 *  - validation stricte de chaque événement (identiques aux règles
 *    SchoolSafeDeviceEvent : identifiants [a-zA-Z0-9._:-], horodatage ISO
 *    avec fuseau, pas de futur > 5 minutes, sens in/out/unknown)
 *  - anti-doublon : external_id déjà reçu avec un payload différent = 409 ;
 *    identifiant stable dérivé de (employé, horodatage) donc une repousse
 *    du même pointage = doublon accepté (200), jamais une collision
 *  - pas d'exécution de code : les événements vont en FILE (schoolsafe_device_queue),
 *    le worker Device Hub importe (Deliver) vers entry_exit_staff_records
 *
 * Configuration de l'appareil (interface Hikvision) :
 *  - URL de poussée : https://app.shoolsafe.tech/schoolsafe-device-push.php?token=<jeton>
 *  - Le jeton est stocké dans /var/lib/schoolsafe-device-hub/push-token (600, www-data).
 *  - L'appareil pousseur est enregistré dans schoolsafe_devices (entrée du school 3).
 *
 * Direction : checkIn/checkOut du terminal si présent, sinon « in » —
 * le checkpoint enregistré (Portail principal - Entree, type in) fait foi :
 * une empreinte validée au portail d'entrée est une entrée.
 */

header('Content-Type: application/json; charset=utf-8');

// L'appareil pousseur enregistré (schoolsafe_devices) — l'entrée du school 3.
$DEVICE_ID = 1;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'POST_REQUIRED']));
}

// Le jeton partagé : fichier privé (600, www-data), comparé à temps constant.
$tokenFile = '/var/lib/schoolsafe-device-hub/push-token';
$attendu = is_file($tokenFile) ? trim((string) file_get_contents($tokenFile)) : '';
$recu = (string) ($_GET['token'] ?? '');
if ($attendu === '' || strlen($recu) !== strlen($attendu) || !hash_equals($attendu, $recu)) {
    http_response_code(401);
    exit(json_encode(['error' => 'INVALID_TOKEN']));
}

$raw = file_get_contents('php://input', false, null, 0, 300001);
if ($raw === false || strlen($raw) > 300000) {
    http_response_code(413);
    exit(json_encode(['error' => 'TOO_LARGE']));
}

// Les règles de validation (les mêmes que SchoolSafeDeviceEvent).
$valide = static function (array $e): bool {
    return preg_match('/^[a-zA-Z0-9._:-]{1,100}$/', (string) ($e['id'] ?? ''))
        && preg_match('/^[a-zA-Z0-9._:-]{1,64}$/', (string) ($e['employee_id'] ?? ''))
        && is_string($e['time'] ?? null)
        && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/', (string) $e['time'])
        && in_array($e['direction'] ?? '', ['in', 'out', 'unknown'], true)
        && strtotime((string) $e['time']) <= time() + 300;
};

$data = json_decode($raw, true);
$propres = [];

if (is_array($data) && array_key_exists('events', $data)) {
    // Chemin 1 : format normalisé.
    $events = $data['events'];
    if (!is_array($events) || count($events) < 1 || count($events) > 200) {
        http_response_code(400);
        exit(json_encode(['error' => 'BATCH_INVALID']));
    }
    foreach ($events as $e) {
        if (!is_array($e)) continue;
        $candidat = [
            'id' => (string) ($e['id'] ?? ''),
            'employee_id' => (string) ($e['employee_id'] ?? ''),
            'time' => (string) ($e['time'] ?? ''),
            'direction' => (string) ($e['direction'] ?? 'unknown'),
        ];
        if ($valide($candidat)) $propres[] = $candidat;
    }
    if (!$propres) {
        http_response_code(400);
        exit(json_encode(['error' => 'NO_VALID_EVENT']));
    }
} else {
    // Chemin 2 : EventNotificationAlert Hikvision (XML ou JSON), un pointage par POST.
    $alerte = null;
    if (is_array($data) && isset($data['AccessControllerEvent'])) {
        $alerte = $data; // JSON
    } elseif (strncmp(ltrim($raw), '<', 1) === 0) {
        $xml = @simplexml_load_string($raw); // PHP 8 : entités externes non chargées
        if ($xml !== false) {
            $ace = isset($xml->AccessControllerEvent) ? (array) $xml->AccessControllerEvent : [];
            $alerte = [
                'dateTime' => (string) $xml->dateTime,
                'eventType' => (string) $xml->eventType,
                'AccessControllerEvent' => $ace,
            ];
        }
    }
    if (!is_array($alerte)) {
        http_response_code(400);
        exit(json_encode(['error' => 'NOT_HIKVISION_ALERT']));
    }
    if (stripos((string) ($alerte['eventType'] ?? ''), 'accessControllerEvent') === false) {
        http_response_code(400);
        exit(json_encode(['error' => 'EVENT_TYPE_UNSUPPORTED']));
    }
    $ace = is_array($alerte['AccessControllerEvent'] ?? null) ? $alerte['AccessControllerEvent'] : [];
    $attrs = isset($ace['@attributes']) && is_array($ace['@attributes']) ? $ace['@attributes'] : [];
    $emp = trim((string) ($ace['employeeNoString'] ?? ($attrs['employeeNoString'] ?? '')));
    $time = trim((string) ($alerte['dateTime'] ?? ''));
    if ($emp === '' || $time === '') {
        // Doigt non reconnu ou alerte sans employe : rien a enregistrer,
        // on accuse reception proprement (evite les tentatives de l'appareil).
        echo json_encode(['recus' => 0, 'doublons' => 0, 'total' => 0, 'ignores' => 1,
            'raison' => 'aucun_employe_identifie']);
        exit;
    }
    $statut = strtolower((string) ($ace['attendanceStatus'] ?? ($attrs['attendanceStatus'] ?? '')));
    $direction = $statut === 'checkout' ? 'out' : ($statut === 'checkin' ? 'in' : 'in');
    // Identifiant stable : la même repousse = le même id (anti-doublon, jamais de collision).
    $id = 'hik-' . substr(sha1($emp . '|' . $time . '|' . (string) ($ace['serialNumber'] ?? '')), 0, 32);
    $candidat = ['id' => $id, 'employee_id' => $emp, 'time' => $time, 'direction' => $direction];
    if (!$valide($candidat)) {
        http_response_code(400);
        exit(json_encode(['error' => 'EVENT_INVALID']));
    }
    $propres = [$candidat];
}

$config = require '/var/www/html/config.inc.php';
$conn = @pg_connect('host=' . $DatabaseServer . ' port=' . ($DatabasePort ?: 5432) .
    ' dbname=' . $DatabaseName . ' user=' . $DatabaseUsername . ' password=' . $DatabasePassword);
if (!$conn) {
    http_response_code(503);
    exit(json_encode(['error' => 'DB_UNAVAILABLE']));
}

// Enqueue durable : le worker importe (Deliver) à son prochain passage.
$recus = 0;
$doublons = 0;
foreach ($propres as $e) {
    $hash = hash('sha256', json_encode($e));
    $existant = pg_query_params($conn,
        'SELECT payload_hash FROM schoolsafe_device_queue WHERE device_id = $1 AND external_id = $2',
        [$DEVICE_ID, $e['id']]);
    if ($existant && pg_num_rows($existant)) {
        $row = pg_fetch_row($existant);
        if ($row[0] !== $hash) { http_response_code(409); exit(json_encode(['error' => 'COLLISION'])); }
        $doublons++;
        continue;
    }
    pg_query_params($conn,
        'INSERT INTO schoolsafe_device_queue (device_id, external_id, payload_hash, event) VALUES ($1, $2, $3, $4::jsonb)',
        [$DEVICE_ID, $e['id'], $hash, json_encode($e)]);
    $recus++;
}

echo json_encode(['recus' => $recus, 'doublons' => $doublons, 'total' => count($propres)]);
