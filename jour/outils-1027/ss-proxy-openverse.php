<?php
/**
 * Proxy medias Openverse - recherche d'images reutilisables (licences CC),
 * cache serveur 1 h. Session SchoolSafe requise. ?q=ecole&page=1
 */
header('Content-Type: application/json; charset=utf-8');
session_name('RosarioSIS');
@session_start();
if (empty($_SESSION['STAFF_ID']) && empty($_SESSION['STUDENT_ID'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'SESSION_REQUIRED']));
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit(json_encode(['error' => 'GET_REQUIRED']));
}
$q = trim((string) ($_GET['q'] ?? ''));
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
if ($q === '' || !preg_match('/^[\w \'’&-]{2,80}$/u', $q) || $page === false) {
    http_response_code(400);
    exit(json_encode(['error' => 'MEDIAS_INVALIDES']));
}
$dir = '/var/lib/schoolsafe-api';
if (!is_dir($dir)) @mkdir($dir, 0700, true);
$fichier = $dir . '/openverse_' . md5($q . '|' . $page) . '.json';
if (is_file($fichier) && time() - filemtime($fichier) < 3600) {
    echo (string) file_get_contents($fichier);
    exit;
}
$url = 'https://api.openverse.org/v1/images/?q=' . rawurlencode($q) . '&page=' . $page . '&page_size=20&license_type=all-cc';
$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: SchoolSafe-Medias/1.0']]);
$reponse = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);
$donnees = json_decode((string) $reponse, true);
if (!is_array($donnees) || !isset($donnees['results']) || $code !== 200) {
    http_response_code(502);
    exit(json_encode(['error' => 'MEDIAS_INDISPONIBLES']));
}
$corps = (string) json_encode($donnees);
file_put_contents($fichier, $corps);
echo $corps;
