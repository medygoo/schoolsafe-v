<?php
declare(strict_types=1);
require_once __DIR__ . '/claims.php';
require_once __DIR__ . '/Environment.php';

function schoolSafeKeycloakConfig(): array
{
    $path = '/run/secrets/schoolsafe-keycloak.json';
    if (!is_readable($path)) { throw new RuntimeException('Provider not configured'); }
    $config = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    if (empty($config['enabled'])) { throw new RuntimeException('Provider disabled'); }
    $environment=$config['environment']??'production';
    $policy=schoolSafeIdentityEnvironment($environment,
        $environment==='local' && schoolSafeIdentityLocalProof($config));
    if (($config['issuer']??null)!==$policy['issuer']
        || ($config['client_id']??null)!==$policy['client_id']
        || !is_string($config['redirect_uri']??null)
        || !schoolSafeIdentityRedirectAllowed($policy,$config['redirect_uri'])) {
        throw new RuntimeException('Invalid provider configuration');
    }
    if (!empty($config['durable_links'])) {
        $config['links']=[];
        $rows=DBGet('SELECT i.SUBJECT,s.STAFF_ID,s.USERNAME FROM schoolsafe_identity_links i'
            . ' JOIN staff s ON s.STAFF_ID=i.STAFF_ID WHERE i.ENABLED=\'Y\' AND i.STATE IN (\'active\',\'onboarding\')'
            . ' AND s.SYEAR='.(int)Config('SYEAR'));
        foreach((array)$rows as $row) $config['links'][$row['SUBJECT']]=[
            'staff_id'=>(int)$row['STAFF_ID'],'username'=>$row['USERNAME']];
    }
    return $config;
}

function schoolSafeKeycloakRequest(string $url, ?array $form = null): array
{
    $config=schoolSafeKeycloakConfig();
    $policy=schoolSafeIdentityEnvironment($config['environment']??'production',
        ($config['environment']??'production')==='local' && schoolSafeIdentityLocalProof($config));
    $curl = curl_init(schoolSafeIdentityEndpoint($policy,$url));
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => $policy['https_only']?CURLPROTO_HTTPS:CURLPROTO_HTTP,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    if ($form !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($form));
    }
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if (!is_string($body) || $status !== 200 || strlen($body) > 1048576) {
        throw new RuntimeException('Identity provider request failed');
    }
    return json_decode($body, true, 64, JSON_THROW_ON_ERROR);
}

function schoolSafeKeycloakRow(array $link): array
{
    $rows = DBGet("SELECT STAFF_ID,USERNAME,PROFILE,SCHOOLS FROM staff WHERE SYEAR='"
        . DBEscapeString((string) Config('SYEAR')) . "' AND STAFF_ID=" . (int)$link['staff_id']
        . " AND USERNAME='" . DBEscapeString($link['username']) . "'");
    $row = $rows[1] ?? [];
    if (!schoolSafeKeycloakAdmin($row, $link)) {
        throw new RuntimeException('Rosario administrator access denied');
    }
    return $row;
}

function schoolSafeKeycloakLoginLink(): void
{
    try { schoolSafeKeycloakConfig(); }
    catch (Throwable $e) { return; }
    echo '<p><a href="plugins/SchoolSafe_Keycloak/endpoint.php">Connexion administrateur SchoolSafe</a></p>';
}

function schoolSafeKeycloakMatchPassword($tag, &$crypted, $plain): void
{
    global $username;
    $ticket = $_SESSION['schoolsafe-keycloak-ticket'] ?? null;
    if (!is_array($ticket) || !is_string($plain)
        || !hash_equals($ticket['secret'], $plain)) { return; }
    unset($_SESSION['schoolsafe-keycloak-ticket']);
    try {
        $config = schoolSafeKeycloakConfig();
        $link = $config['links'][$ticket['subject']] ?? null;
        if (!$link || $ticket['expires'] < time() || $username !== $link['username']) {
            return;
        }
        schoolSafeKeycloakRow($link);
        $crypted = encrypt_password($plain);
        $GLOBALS['sskc_accepted_native_ticket']=hash('sha256',$plain);
        $_SESSION['schoolsafe-keycloak-session'] = ['subject'=>$ticket['subject'],
            'client_password_only'=>!empty($ticket['client_password_only']),'profile'=>$ticket['profile']??[],
            'tokens'=>$ticket['tokens']];
    } catch (Throwable $e) {
        // The original Rosario password hash remains unchanged on failure.
        return;
    }
}

add_action('index.php|login_form_link', 'schoolSafeKeycloakLoginLink');
add_action('functions/Password.php|match_password', 'schoolSafeKeycloakMatchPassword', 3);

function schoolSafeKeycloakValidateSession(): void
{
    $session = $_SESSION['schoolsafe-keycloak-session'] ?? null;
    if (!is_array($session) || empty($_SESSION['STAFF_ID'])) return;
    $phase='link';
    try {
        $config = schoolSafeKeycloakConfig();
        $link = $config['links'][$session['subject']] ?? null;
        if (!$link || (int)$_SESSION['STAFF_ID'] !== (int)$link['staff_id']) {
            throw new RuntimeException('Identity link revoked');
        }
        $phase='native_row';
        $row=schoolSafeKeycloakRow($link);
        if(!empty($session['client_password_only']) && SchoolSafeScopeParse($row['SCHOOLS'])===null) {
            throw new RuntimeException('Central access requires reinforced authentication');
        }
        $phase='tokens';
        $tokens = $session['tokens'];
        if (($tokens['checked'] ?? 0) > time()-60) return;
        $base = $config['issuer'] . '/protocol/openid-connect/';
        $auth = ['client_id'=>$config['client_id'],'client_secret'=>$config['client_secret']];
        if ($tokens['expires'] < time()+60) {
            $phase='refresh';
            $renewed = schoolSafeKeycloakRequest($base.'token', $auth+[
                'grant_type'=>'refresh_token','refresh_token'=>$tokens['refresh']]);
            if (!is_string($renewed['access_token'] ?? null) || !is_string($renewed['refresh_token'] ?? null)) {
                throw new RuntimeException('Session renewal refused');
            }
            $tokens = ['access'=>$renewed['access_token'],'refresh'=>$renewed['refresh_token'],
                'expires'=>time()+(int)$renewed['expires_in'],'checked'=>0];
        }
        $phase='introspection_request';
        $status = schoolSafeKeycloakRequest($base.'token/introspect', $auth+['token'=>$tokens['access']]);
        $phase='introspection_claims';
        if (($status['active'] ?? false) !== true || ($status['sub'] ?? '') !== $session['subject']
            || ($status['client_id'] ?? '') !== $config['client_id']) {
            error_log('SchoolSafe session introspection active='.(int)(($status['active']??false)===true)
                .' subject_matches='.(int)(($status['sub']??'')===$session['subject'])
                .' client_matches='.(int)(($status['client_id']??'')===$config['client_id'])
                .' azp_matches='.(int)(($status['azp']??'')===$config['client_id']));
            throw new RuntimeException('Identity session revoked');
        }
        $tokens['checked']=time();
        $_SESSION['schoolsafe-keycloak-session']['tokens']=$tokens;
    } catch (Throwable $e) {
        error_log('SchoolSafe session refusal phase='.$phase.' class='.get_class($e));
        // Fail closed without writing a new native permission or logging tokens.
        session_unset();
        session_destroy();
        http_response_code(403);
        header('Cache-Control: no-store');
        echo 'Session SchoolSafe expirée ou révoquée. Reconnectez-vous depuis la page de connexion.';
        exit;
    }
}

// Loaded by Warehouse before the requested business program, including AJAX.
schoolSafeKeycloakValidateSession();

if (is_file(__DIR__.'/Management.php')) require_once __DIR__.'/Management.php';
if (is_file(__DIR__.'/Opening.php')) require_once __DIR__.'/Opening.php';
