<?php
declare(strict_types=1);

/** Deployment-owned settings only; never take the environment from a request. */
function schoolSafeIdentityEnvironment(string $environment, bool $isolatedLocal = false): array
{
    if ($environment === 'production') {
        return ['issuer'=>'https://auth.shoolsafe.tech/realms/schoolsafe',
            'client_id'=>'schoolsafe-rosario-admin',
            'admin_base'=>'https://auth.shoolsafe.tech/admin/realms/schoolsafe/',
            'transport_origin'=>'https://auth.shoolsafe.tech', 'https_only'=>true];
    }
    if ($environment !== 'local' || !$isolatedLocal) {
        throw new RuntimeException('Identity environment refused');
    }
    return ['issuer'=>'http://127.0.0.1:18683/realms/schoolsafe-local',
        'client_id'=>'schoolsafe-rosario-admin-local',
        'admin_base'=>'http://schoolsafe-auth-local:8080/admin/realms/schoolsafe-local/',
        'transport_origin'=>'http://schoolsafe-auth-local:8080', 'https_only'=>false];
}

/** Only the three back-channel OIDC endpoints used by the existing bridge. */
function schoolSafeIdentityEndpoint(array $policy, string $url): string
{
    $prefix=$policy['issuer'].'/protocol/openid-connect/';
    if (!str_starts_with($url,$prefix)) throw new RuntimeException('Untrusted endpoint');
    $operation=substr($url,strlen($prefix));
    if (!in_array($operation,['token','token/introspect','certs'],true)) {
        throw new RuntimeException('Untrusted endpoint');
    }
    $parts=parse_url($policy['issuer']);
    return $policy['transport_origin'].$parts['path'].'/protocol/openid-connect/'.$operation;
}

function schoolSafeIdentityRedirectAllowed(array $policy, string $uri): bool
{
    if (!$policy['https_only']) {
        return $uri === 'http://127.0.0.1:18682/plugins/SchoolSafe_Keycloak/endpoint.php';
    }
    // Preserve production's HTTPS requirement without permitting userinfo or fragments.
    $parts=parse_url($uri);
    return is_array($parts) && ($parts['scheme']??'')==='https'
        && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass'])
        && !isset($parts['fragment']);
}

/** A second, deployment-owned secret proves that a local policy was installed intentionally. */
function schoolSafeIdentityLocalProof(array $config): bool
{
    $path='/run/secrets/schoolsafe-local-proof';
    if (!is_string($config['local_proof']??null) || strlen($config['local_proof'])<32
        || !is_readable($path)) return false;
    $proof=trim((string)file_get_contents($path));
    return strlen($proof)>=32 && hash_equals($proof,$config['local_proof']);
}
