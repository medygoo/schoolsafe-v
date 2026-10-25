<?php
declare(strict_types=1);
// Restore Rosario's SameSite=Strict session with a same-origin navigation,
// using the same approach as its packaged social login plugins.
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
$scriptNonce=base64_encode(random_bytes(18));
header("Content-Security-Policy: default-src 'none'; script-src 'nonce-".$scriptNonce."'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
if (isset($_GET['code']) && !isset($_GET['resume'])) {
    $next = 'endpoint.php?' . http_build_query([
        'resume' => '1', 'code' => (string) $_GET['code'], 'state' => (string) ($_GET['state'] ?? '')]);
    echo '<!doctype html><meta http-equiv="refresh" content="0;url='
        . htmlspecialchars($next, ENT_QUOTES, 'UTF-8') . '">';
    exit;
}
// Warehouse derives the session-cookie path from SCRIPT_NAME. This endpoint lives
// below /plugins/, but its ticket must reach the root-level native index.php.
// Keep Rosario's Secure/HttpOnly/SameSite settings and use its root cookie scope.
setcookie('RosarioSIS','',['expires'=>time()-3600,'path'=>'/plugins/SchoolSafe_Keycloak/',
    'secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
$_SERVER['SCRIPT_NAME']='/schoolsafe-keycloak.php';
// Public access to this endpoint is served by the configured HTTPS reverse proxy.
$localEndpoint=false;
if (($_SERVER['HTTP_HOST']??'')==='127.0.0.1:18682'
    && is_readable('/run/secrets/schoolsafe-keycloak.json')) {
    $deployment=json_decode((string)file_get_contents('/run/secrets/schoolsafe-keycloak.json'),true);
    require_once __DIR__.'/Environment.php';
    $localEndpoint=is_array($deployment) && ($deployment['environment']??'')==='local'
        && schoolSafeIdentityLocalProof($deployment);
}
if ($localEndpoint) {
    $_SERVER['HTTPS']='off';
} else {
    $_SERVER['HTTPS']='on';
}
chdir(dirname(__DIR__, 2));
require_once 'Warehouse.php';
require_once __DIR__ . '/functions.php';
$phase='configuration';
try {
    if (empty($RosarioPlugins['SchoolSafe_Keycloak'])) {
        throw new RuntimeException('Plugin is not activated');
    }
    $config = schoolSafeKeycloakConfig();
    $issuer = $config['issuer'];
    if (!isset($_GET['code'])) {
        if (isset($_GET['error'])) { throw new RuntimeException('Authentication cancelled'); }
        $transaction = ['state' => bin2hex(random_bytes(32)), 'nonce' => bin2hex(random_bytes(32)),
            'verifier' => rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '='), 'created' => time()];
        $_SESSION['schoolsafe-keycloak-transaction'] = $transaction;
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $transaction['verifier'], true)), '+/', '-_'), '=');
        $params = ['client_id'=>$config['client_id'], 'redirect_uri'=>$config['redirect_uri'],
            'response_type'=>'code', 'scope'=>'openid profile email', 'prompt'=>'login', 'state'=>$transaction['state'],
            'nonce'=>$transaction['nonce'], 'code_challenge'=>$challenge, 'code_challenge_method'=>'S256'];
        header('Location: ' . $issuer . '/protocol/openid-connect/auth?' . http_build_query($params));
        exit;
    }
    $phase='state';
    $transaction = $_SESSION['schoolsafe-keycloak-transaction'] ?? null;
    unset($_SESSION['schoolsafe-keycloak-transaction']);
    if (!is_array($transaction) || !is_string($_GET['state'] ?? null)
        || !hash_equals($transaction['state'], $_GET['state'])
        || $transaction['created'] < time()-300) {
        throw new RuntimeException('Invalid state');
    }
    $phase='token_exchange';
    $tokens = schoolSafeKeycloakRequest($issuer . '/protocol/openid-connect/token', [
        'grant_type'=>'authorization_code', 'client_id'=>$config['client_id'],
        'client_secret'=>$config['client_secret'], 'redirect_uri'=>$config['redirect_uri'],
        'code'=>(string)$_GET['code'], 'code_verifier'=>$transaction['verifier']]);
    $phase='signature';
    require_once __DIR__ . '/jwt-autoload.php';
    $jwks = schoolSafeKeycloakRequest($issuer . '/protocol/openid-connect/certs');
    $jwks['keys'] = array_values(array_filter($jwks['keys'] ?? [],
        fn(array $key): bool => ($key['kty'] ?? '') === 'RSA' && ($key['alg'] ?? '') === 'RS256'));
    $keys = Firebase\JWT\JWK::parseKeySet($jwks, 'RS256');
    $claims = (array)Firebase\JWT\JWT::decode($tokens['id_token'], $keys);
    $phase='claims';
    $link = schoolSafeKeycloakIdentity($claims, $transaction, $config, time());
    $phase='native_identity';
    $native=schoolSafeKeycloakRow($link);
    $client=in_array('schoolsafe-client-password',$claims['schoolsafe_roles']??[],true);
    if($client && SchoolSafeScopeParse($native['SCHOOLS'])===null) throw new RuntimeException('Central account requires reinforced authentication');
    session_regenerate_id(true);
    $secret = bin2hex(random_bytes(32));
    if (!is_string($tokens['refresh_token'] ?? null) || !is_string($tokens['access_token'] ?? null)) {
        throw new RuntimeException('Incomplete token response');
    }
    $_SESSION['schoolsafe-keycloak-ticket'] = ['secret'=>$secret,
        'subject'=>$claims['sub'], 'expires'=>time()+60,'client_password_only'=>$client,
        'profile'=>['first'=>(string)($claims['given_name']??''),'last'=>(string)($claims['family_name']??''),'email'=>(string)($claims['email']??'')],
        'tokens'=>['access'=>$tokens['access_token'],'refresh'=>$tokens['refresh_token'],
            'expires'=>time()+(int)$tokens['expires_in'],'checked'=>0]];
    $escape = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="fr"><meta charset="utf-8"><title>SchoolSafe</title>'
        . '<p>Identité vérifiée. Continuer avec les droits de votre compte Rosario.</p>'
        . '<form action="../../index.php" method="post">'
        . '<input type="hidden" name="USERNAME" value="' . $escape($link['username']) . '">'
        . '<input type="hidden" name="PASSWORD" value="' . $escape($secret) . '">'
        . '<button type="submit">Ouvrir SchoolSafe</button></form>'
        . '<script nonce="'.$escape($scriptNonce).'">document.forms[0].submit();</script></html>';
} catch (Throwable $e) {
    $safeReasons=['Invalid state','Invalid issuer','Invalid audience','Invalid token lifetime',
        'Invalid authentication transaction','Identity is not explicitly linked',
        'Rosario administrator access denied','Central account requires reinforced authentication',
        'Identity provider request failed','Authentication cancelled'];
    $reason=in_array($e->getMessage(),$safeReasons,true)?$e->getMessage():'details-withheld';
    error_log('SchoolSafe OIDC refusal phase='.$phase.' class='.get_class($e).' reason='.$reason);
    unset($_SESSION['schoolsafe-keycloak-ticket']);
    http_response_code(403);
    echo 'Connexion administrateur refusée. Revenez à la connexion SchoolSafe ou contactez votre administrateur.';
}
