<?php
/**
 * Proxy météo OpenWeather — cache serveur 30 minutes.
 * Session SchoolSafe requise. Clé API dans /var/lib/schoolsafe-api/openweather-key.
 * ?lat=-4.32&lon=15.31
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
$lat = filter_var($_GET['lat'] ?? null, FILTER_VALIDATE_FLOAT);
$lon = filter_var($_GET['lon'] ?? null, FILTER_VALIDATE_FLOAT);
if ($lat === false || $lon === false || abs($lat) > 90 || abs($lon) > 180) {
    http_response_code(400);
    exit(json_encode(['error' => 'COORDONNEES_INVALIDES']));
}
$lat = round($lat, 2);
$lon = round($lon, 2);
$dir = '/var/lib/schoolsafe-api';
if (!is_dir($dir)) @mkdir($dir, 0700, true);
$fichier = $dir . '/meteo_' . $lat . '_' . $lon . '.json';
if (is_file($fichier) && time() - filemtime($fichier) < 1800) {
    echo (string) file_get_contents($fichier);
    exit;
}
$clef = is_file($dir . '/openweather-key') ? trim((string) file_get_contents($dir . '/openweather-key')) : '';
if ($clef === '') {
    echo json_encode(['error' => 'CLE_METEO_MANQUANTE',
        'aide' => 'Cree une cle gratuite sur openweathermap.org puis place-la dans /var/lib/schoolsafe-api/openweather-key']);
    exit;
}
$url = 'https://api.openweathermap.org/data/2.5/weather?lat=' . $lat . '&lon=' . $lon .
    '&units=metric&lang=fr&appid=' . urlencode($clef);
$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10]);
$reponse = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);
$donnees = json_decode((string) $reponse, true);
if (!is_array($donnees) || $code !== 200) {
    http_response_code(502);
    echo json_encode(['error' => 'METEO_INDISPONIBLE']);
    exit;
}
file_put_contents($fichier, (string) $reponse);
echo (string) $reponse;
