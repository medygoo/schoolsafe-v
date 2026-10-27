<?php
/**
 * Proxy dictionnaire — Wiktionary FR + Free Dictionary EN, cache serveur 24 h.
 * Session SchoolSafe requise.
 * ?mot=école&lang=fr   (lang fr|en, fr par défaut)
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
$mot = trim((string) ($_GET['mot'] ?? ''));
$lang = ($_GET['lang'] ?? 'fr') === 'en' ? 'en' : 'fr';
if ($mot === '' || !preg_match('/^[a-zA-ZÀ-ÖØ-öø-ÿ\' -]{1,40}$/u', $mot)) {
    http_response_code(400);
    exit(json_encode(['error' => 'MOT_INVALIDE']));
}
$dir = '/var/lib/schoolsafe-api';
if (!is_dir($dir)) @mkdir($dir, 0700, true);
$fichier = $dir . '/dico_' . $lang . '_' . md5(mb_strtolower($mot)) . '.json';
if (is_file($fichier) && time() - filemtime($fichier) < 86400) {
    echo (string) file_get_contents($fichier);
    exit;
}
if ($lang === 'fr') {
    $url = 'https://fr.wiktionary.org/api/rest_v1/page/definition/' . rawurlencode($mot);
} else {
    $url = 'https://api.dictionaryapi.dev/api/v2/entries/en/' . rawurlencode($mot);
}
$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: SchoolSafe-Dico/1.0']]);
$reponse = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);
$donnees = json_decode((string) $reponse, true);
if (!is_array($donnees) || $code !== 200) {
    http_response_code(404);
    echo json_encode(['error' => 'MOT_INTROUVABLE']);
    exit;
}
$corps = (string) json_encode(['mot' => $mot, 'lang' => $lang, 'def' => $donnees], JSON_UNESCAPED_UNICODE);
file_put_contents($fichier, $corps);
echo $corps;
