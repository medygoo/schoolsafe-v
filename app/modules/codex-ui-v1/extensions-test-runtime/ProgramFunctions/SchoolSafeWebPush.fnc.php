<?php
// SchoolSafe — Lot U4 : signal Web Push VAPID (canal push reel).
//
// - Cles lues cote SERVEUR uniquement (env VAPID_PRIVATE_PEM / VAPID_PUBLIC_KEY
//   / VAPID_SUBJECT) : jamais dans le depot ni au navigateur (la cle publique
//   seule est exposee au service worker lors de l'abonnement).
// - Envoi = signal SANS corps chiffre (POST vide) : reveille le service
//   worker, qui affiche la notification depuis son propre texte + va lire le
//   fil. Aucune donnee personnelle ne transite par le service push.
// - 200/201/202 => 'accepted'. 404/410 => abonnement mort (SUBSCRIPTION_EXPIRED,
//   le dispatcher supprime l'abonnement). Autre => PUSH_ERROR (ligne gardee).
// - Sans cles => NO_PUSH_PROVIDER (failed honnete, jamais de faux succes).

function SchoolSafeWebPushBase64UrlEncode($bin)
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function SchoolSafeWebPushConfig()
{
    $pem = trim((string) getenv('VAPID_PRIVATE_PEM'));
    $pub = trim((string) getenv('VAPID_PUBLIC_KEY'));
    $sub = trim((string) getenv('VAPID_SUBJECT'));
    if ($pem === '' || $pub === '') return null; // fournisseur non configure
    if ($sub === '') $sub = 'mailto:securite@mail.shoolsafe.tech';
    return ['private_pem' => $pem, 'public_key' => $pub, 'subject' => $sub];
}

// Signature ES256 brute (R||S 64 octets) a partir d'une cle EC PEM.
function SchoolSafeWebPushSignEs256($data, $privatePem)
{
    $key = openssl_pkey_get_private($privatePem);
    if ($key === false) throw new RuntimeException('PUSH_BAD_KEY');
    $ok = openssl_sign($data, $der, $key, OPENSSL_ALGO_SHA256);
    if (!$ok) throw new RuntimeException('PUSH_SIGN_FAILED');
    // DER -> R||S : SEQUENCE { INTEGER R, INTEGER S }.
    $pos = 0;
    if (ord($der[$pos++]) !== 0x30) throw new RuntimeException('PUSH_SIGN_FAILED');
    $len = ord($der[$pos++]);
    if ($len & 0x80) $pos += $len & 0x7F;
    if (ord($der[$pos++]) !== 0x02) throw new RuntimeException('PUSH_SIGN_FAILED');
    $rLen = ord($der[$pos++]);
    $r = substr($der, $pos, $rLen);
    $pos += $rLen;
    if (ord($der[$pos++]) !== 0x02) throw new RuntimeException('PUSH_SIGN_FAILED');
    $sLen = ord($der[$pos++]);
    $s = substr($der, $pos, $sLen);
    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
    return substr($r, -32) . substr($s, -32);
}

function SchoolSafeWebPushJwt($audience, array $config)
{
    $header = SchoolSafeWebPushBase64UrlEncode('{"typ":"JWT","alg":"ES256"}');
    $claims = SchoolSafeWebPushBase64UrlEncode(json_encode([
        'aud' => $audience, 'exp' => time() + 43200, 'sub' => $config['subject']]));
    $sig = SchoolSafeWebPushSignEs256($header . '.' . $claims, $config['private_pem']);
    return $header . '.' . $claims . '.' . SchoolSafeWebPushBase64UrlEncode($sig);
}

// $subscription : ['endpoint'=>...]. $http injectable (tests).
function SchoolSafeWebPushSend(array $subscription, $config = null, $http = null)
{
    if ($config === null) $config = SchoolSafeWebPushConfig();
    if (!is_array($config)) throw new RuntimeException('NO_PUSH_PROVIDER');
    $endpoint = trim((string) ($subscription['endpoint'] ?? ''));
    if ($endpoint === '' || !preg_match('#^https://#i', $endpoint)) {
        throw new RuntimeException('PUSH_BAD_ENDPOINT');
    }
    $parts = parse_url($endpoint);
    $audience = (isset($parts['scheme']) ? $parts['scheme'] . '://' : 'https://')
        . ($parts['host'] ?? '');
    $jwt = SchoolSafeWebPushJwt($audience, $config);
    $headers = ['Authorization: vapid t=' . $jwt . ', k=' . $config['public_key'],
        'TTL: 2419200', 'Urgency: high', 'Content-Length: 0'];
    if (is_callable($http)) {
        $res = $http($endpoint, $headers, '');
    } else {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '');
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $res = ['http_code' => $code, 'body' => is_string($raw) ? $raw : ''];
        if ($err !== '') $res['curl_error'] = $err;
    }
    if (!empty($res['curl_error'])) {
        throw new RuntimeException('PUSH_NETWORK: ' . substr((string) $res['curl_error'], 0, 200));
    }
    $code = (int) ($res['http_code'] ?? 0);
    if ($code === 404 || $code === 410) throw new RuntimeException('SUBSCRIPTION_EXPIRED');
    if ($code >= 200 && $code < 300) return ['accepted' => true];
    throw new RuntimeException('PUSH_ERROR:' . $code);
}
