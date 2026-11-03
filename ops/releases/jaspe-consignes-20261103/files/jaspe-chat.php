<?php
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/json; charset=utf-8');
function SchoolSafeJaspeChatFail(int $status, string $error): void
{
    http_response_code($status);
    exit(json_encode(['error'=>$error]));
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $_GET) {
    SchoolSafeJaspeChatFail(405, 'POST_REQUIRED');
}
if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
    SchoolSafeJaspeChatFail(415, 'JSON_REQUIRED');
}
$raw = file_get_contents('php://input', false, null, 0, 12001);
if ($raw === false || strlen($raw) > 12000) SchoolSafeJaspeChatFail(413, 'MESSAGE_TOO_LARGE');
$payload = json_decode($raw, true);
if (!is_array($payload) || array_keys($payload) !== ['message'] || !is_string($payload['message'])) {
    SchoolSafeJaspeChatFail(400, 'INVALID_REQUEST');
}
chdir(__DIR__);
$_REQUEST = ['modname'=>'misc/Portal.php', 'modfunc'=>''];
$level = ob_get_level();
ob_start();
require_once __DIR__ . '/Warehouse.php';
while (ob_get_level() > $level) ob_end_clean();
if (!User('STAFF_ID') && !UserStudentID()) SchoolSafeJaspeChatFail(401, 'AUTH_REQUIRED');
if (empty($RosarioModules['SchoolSafe_Jaspe']) || !AllowUse('SchoolSafe_Jaspe/Assistant.php')) {
    SchoolSafeJaspeChatFail(403, 'JASPE_ACCESS_DENIED');
}
$token = $_SERVER['HTTP_X_JASPE_TOKEN'] ?? '';
if (!is_string($token) || empty($_SESSION['schoolsafe_jaspe_token']) ||
    !hash_equals($_SESSION['schoolsafe_jaspe_token'], $token)) {
    SchoolSafeJaspeChatFail(403, 'INVALID_TOKEN');
}
require_once __DIR__ . '/ProgramFunctions/SchoolSafeJaspeActions.fnc.php';
require_once __DIR__ . '/ProgramFunctions/SchoolSafeJaspeConversation.fnc.php';
require_once __DIR__ . '/ProgramFunctions/SchoolSafeJaspeMotion.fnc.php';
require_once __DIR__ . '/ProgramFunctions/SchoolSafeJaspeCloudflare.fnc.php';
require_once __DIR__ . '/ProgramFunctions/SchoolSafeJaspePrompt.fnc.php';
// Memoire de session : les derniers echanges donnent le contexte a l IA (Cloudflare et secours).
// Elle vit dans $_SESSION (effacee a la deconnexion), jamais en base ; l'interface ne l'efface pas.
$history = SchoolSafeJaspeCleanHistory(is_array($_SESSION['jaspe_memory'] ?? null) ? $_SESSION['jaspe_memory'] : []);
try {
    $result = SchoolSafeJaspeConversation($payload['message'], 'AllowUse', 'SchoolSafeJaspeCanEditDestination');
    if ($result['state'] === 'IDLE' && strpos($result['reply'], 'conversation IA libre') !== false) {
        $now = time();
        $hits = array_values(array_filter($_SESSION['jaspe_ai_hits'] ?? [], static function ($hit) use ($now) {
            return is_int($hit) && $now - $hit < 60;
        }));
        if (count($hits) >= 10) SchoolSafeJaspeChatFail(429, 'LOCAL_AI_RATE_LIMITED');
        $hits[] = $now;
        $_SESSION['jaspe_ai_hits'] = $hits;
        // 03/11 : consignes + contexte (prenom, role, ecole) ; noms d'eleves masques avant tout envoi.
        $preamble = SchoolSafeJaspeGuidelines(SchoolSafeJaspeWho());
        $studentNames = SchoolSafeJaspeStudentNames((int) UserSchool(), (int) UserSyear(), static function ($sql) { return DBGet($sql); });
        $sendMessage = SchoolSafeJaspeMask((string) $payload['message'], $studentNames);
        $sendHistory = SchoolSafeJaspeMaskHistory($history, $studentNames);
        try {
            // SchoolSafe : repli sur la table config si la variable d'environnement est absente.
            $secret = getenv('JASPE_WORKER_HMAC_SECRET') ?: (string) Config('JASPE_WORKER_SECRET');
            $sessionKey = hash_hmac('sha256', implode(':', [
                (string) User('STAFF_ID'), (string) UserStudentID(),
                (string) UserSchool(), (string) User('PROFILE_ID'),
            ]), $secret);
            session_write_close();
            $workerUrl = getenv('JASPE_WORKER_URL') ?: (string) Config('JASPE_WORKER_URL');
            $result = SchoolSafeJaspeCloudflare($sendMessage, $sessionKey,
                $workerUrl, $secret, null, $sendHistory, $preamble);
        } catch (RuntimeException $error) {
            require_once __DIR__ . '/ProgramFunctions/SchoolSafeJaspeFallbackLLM.fnc.php';
            $fallback = SchoolSafeJaspeFallbackLLM($sendMessage, $sendHistory, $preamble);
            if ($fallback !== null) {
                error_log('JASPE fallback LLM used.');
                $result = $fallback;
            } else {
            $messages = [
                'CLOUDFLARE_NOT_CONFIGURED'=>'La conversation Cloudflare n\'est pas configuree dans SchoolSafe local.',
                'CLOUDFLARE_TIMEOUT'=>'Cloudflare met trop de temps a repondre. Vous pouvez reessayer.',
                'CLOUDFLARE_AUTH_ERROR'=>'Le raccordement Cloudflare refuse l\'authentification.',
                'CLOUDFLARE_NETWORK_ERROR'=>'SchoolSafe ne parvient pas a joindre Cloudflare.',
            ];
            error_log('JASPE provider failure: ' . $error->getMessage());
            $result = ['reply'=>$messages[$error->getMessage()] ?? 'Le service IA Cloudflare est momentanement indisponible.',
                'actions'=>[], 'state'=>'OFFLINE', 'mode'=>'local'];
            }
        }
    }
} catch (InvalidArgumentException $error) {
    SchoolSafeJaspeChatFail(400, 'INVALID_MESSAGE');
}
// Memoire (03/11) : tout echange abouti (reponse locale, IA ou de secours) entre dans le contexte ;
// seules les pannes (OFFLINE) n'y entrent pas.
if (($result['state'] ?? '') !== 'OFFLINE' && is_string($result['reply'] ?? null)) {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $_SESSION['jaspe_memory'] = SchoolSafeJaspeRemember(
        is_array($_SESSION['jaspe_memory'] ?? null) ? $_SESSION['jaspe_memory'] : [], (string) $payload['message'], $result['reply']);
    session_write_close();
}
// SchoolSafe display and speech branding, 2026-10-14; technical route identifiers stay native.
$result['reply'] = SchoolSafeBrandText($result['reply']);
// Deterministic emotion/gesture rules (2026-10-17). The v12 engine already animates these
// families; without this signal the interface could only reach six coarse states.
$motion = SchoolSafeJaspeMotionRules($result, (string) $payload['message']);
$result['emotion'] = $motion[0];
$result['gesture'] = $motion[1];
foreach ($result['actions'] as &$action) {
    if (isset($action['title'])) $action['title'] = SchoolSafeBrandText($action['title']);
}
unset($action);
echo json_encode($result, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
