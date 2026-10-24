<?php
// SchoolSafe — Lot U4 : fournisseur courriel Brevo (canal email reel).
//
// - Cle API lue cote SERVEUR uniquement (env BREVO_API_KEY) : jamais dans le
//   depot, jamais au navigateur, jamais dans les journaux applicatifs.
// - Sans cle : fournisseur absent => le dispatcher marque 'failed'
//   (NO_EMAIL_PROVIDER), jamais un faux succes.
// - Reponse Brevo 201 + messageId => 'accepted'. Toute autre reponse ou
//   erreur reseau => exception BREVO_ERROR (ligne conservee, reprise).
// - Le reseau vit ICI, jamais dans SchoolSafeNotify.fnc.php (T9 U0 intouché).

function SchoolSafeBrevoConfig()
{
    $key = trim((string) getenv('BREVO_API_KEY'));
    if ($key === '') return null; // fournisseur non configure
    $url = trim((string) getenv('BREVO_API_URL'));
    if ($url === '') $url = 'https://api.brevo.com/v3';
    return ['api_key' => $key, 'base_url' => rtrim($url, '/')];
}

function SchoolSafeBrevoBuildPayload($toEmail, array $mail)
{
    $toEmail = trim((string) $toEmail);
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('NO_RECIPIENT_EMAIL');
    }
    return ['sender' => ['email' => $mail['from'], 'name' => 'SchoolSafe'],
        'to' => [['email' => $toEmail]],
        'subject' => (string) ($mail['subject'] ?? ''),
        'htmlContent' => (string) ($mail['html'] ?? ''),
        'textContent' => (string) ($mail['text'] ?? '')];
}

// $http injectable (tests) : callable($url, $headers, $jsonBody) => ['http_code'=>int,'body'=>string].
function SchoolSafeBrevoSend($toEmail, array $mail, $config = null, $http = null)
{
    if ($config === null) $config = SchoolSafeBrevoConfig();
    if (!is_array($config)) throw new RuntimeException('NO_EMAIL_PROVIDER');
    $payload = SchoolSafeBrevoBuildPayload($toEmail, $mail);
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($body)) throw new RuntimeException('BREVO_PAYLOAD_FAILED');
    $url = $config['base_url'] . '/smtp/email';
    $headers = ['accept: application/json', 'content-type: application/json',
        'api-key: ' . $config['api_key']];
    if (is_callable($http)) {
        $res = $http($url, $headers, $body);
    } else {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $res = ['http_code' => $code, 'body' => is_string($raw) ? $raw : ''];
        if ($err !== '') $res['curl_error'] = $err;
    }
    if (!empty($res['curl_error'])) {
        throw new RuntimeException('BREVO_NETWORK: ' . substr((string) $res['curl_error'], 0, 200));
    }
    $code = (int) ($res['http_code'] ?? 0);
    $decoded = json_decode((string) ($res['body'] ?? ''), true);
    $msgId = is_array($decoded) ? ($decoded['messageId'] ?? null) : null;
    if (($code === 201 || $code === 200) && is_string($msgId)
        && preg_match('/^[^\\x00-\\x20\\x7f]{1,256}$/uD',$msgId)===1) {
        return ['accepted' => true, 'provider_msg_id' => $msgId];
    }
    throw new RuntimeException('BREVO_ERROR:' . $code . ':' . substr((string) ($res['body'] ?? ''), 0, 200));
}
