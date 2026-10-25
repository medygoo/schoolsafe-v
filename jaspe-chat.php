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
try {
    $result = SchoolSafeJaspeConversation($payload['message'], 'AllowUse', 'SchoolSafeJaspeCanEditDestination');
    if ($result['state'] === 'IDLE' && strpos($result['reply'], 'conversation IA libre') !== false) {
        require_once __DIR__ . '/ProgramFunctions/SchoolSafeJaspeCloudflare.fnc.php';
        $now = time();
        $hits = array_values(array_filter($_SESSION['jaspe_ai_hits'] ?? [], static function ($hit) use ($now) {
            return is_int($hit) && $now - $hit < 60;
        }));
        if (count($hits) >= 10) SchoolSafeJaspeChatFail(429, 'LOCAL_AI_RATE_LIMITED');
        $hits[] = $now;
        $_SESSION['jaspe_ai_hits'] = $hits;
        try {
            $secret = getenv('JASPE_WORKER_HMAC_SECRET') ?: '';
            $sessionKey = hash_hmac('sha256', implode(':', [
                (string) User('STAFF_ID'), (string) UserStudentID(),
                (string) UserSchool(), (string) User('PROFILE_ID'),
            ]), $secret);
            session_write_close();
            $result = SchoolSafeJaspeCloudflare($payload['message'], $sessionKey,
                getenv('JASPE_WORKER_URL') ?: '', $secret);
        } catch (RuntimeException $error) {
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
} catch (InvalidArgumentException $error) {
    SchoolSafeJaspeChatFail(400, 'INVALID_MESSAGE');
}
// SchoolSafe display and speech branding, 2026-10-14; technical route identifiers stay native.
$result['reply'] = SchoolSafeBrandText($result['reply']);
// Deterministic emotion/gesture rules (2026-10-17). The v12 engine already animates these
// families; without this signal the interface could only reach six coarse states.
$motion = SchoolSafeJaspeMotionRules($result);
$result['emotion'] = $motion[0];
$result['gesture'] = $motion[1];
foreach ($result['actions'] as &$action) {
    if (isset($action['title'])) $action['title'] = SchoolSafeBrandText($action['title']);
}
unset($action);
echo json_encode($result, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
