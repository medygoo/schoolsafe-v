<?php
/** Match the existing JASPE Worker's canonical JSON and HMAC protocol. */
function SchoolSafeJaspeCloudflare(string $message, string $sessionKey, string $url, string $secret, ?callable $transport = null, array $history = []): array
{
    $parts = parse_url($url);
    if (!$parts || ($parts['scheme'] ?? '') !== 'https' ||
        !preg_match('/^[a-z0-9.-]+\.workers\.dev$/i', $parts['host'] ?? '') ||
        isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) ||
        isset($parts['fragment']) || (isset($parts['port']) && $parts['port'] !== 443) ||
        strlen($secret) < 32 || $sessionKey === '') {
        throw new RuntimeException('CLOUDFLARE_NOT_CONFIGURED');
    }
    // Memoire de conversation : derniers echanges (max 8, 1000 caracteres max chacun),
    // fournis par la session PHP du VPS — le Worker n'en garde jamais aucune trace.
    $payload = ['message'=>$message,'session_key'=>$sessionKey];
    if ($history) {
        $clean = array_values(array_filter($history, static function ($h) {
            return is_array($h) && isset($h['role'], $h['content'])
                && in_array($h['role'], ['user','assistant'], true)
                && is_string($h['content']) && $h['content'] !== '';
        }));
        $clean = array_slice($clean, -8);
        foreach ($clean as $i => $h) { $clean[$i]['content'] = mb_substr($h['content'], 0, 1000); }
        $payload['history'] = $clean;
    }
    $body = json_encode($payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS);
    if (!is_string($body)) throw new RuntimeException('INVALID_MESSAGE');
    $timestamp = (string) time();
    $headers = ['Content-Type: application/json', 'x-jaspe-timestamp: ' . $timestamp,
        'x-jaspe-signature: ' . hash_hmac('sha256', $timestamp . "\n" . $body, $secret)];
    if ($transport === null) {
        $transport = static function ($url, $headers, $body) {
            if (!function_exists('curl_init')) throw new RuntimeException('CLOUDFLARE_UNAVAILABLE');
            $curl = curl_init($url);
            $received = '';
            curl_setopt_array($curl, [
                CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$body, CURLOPT_HTTPHEADER=>$headers,
                CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>40, CURLOPT_FOLLOWLOCATION=>false,
                CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER=>true,
                CURLOPT_SSL_VERIFYHOST=>2, CURLOPT_PROXY=>'',
                CURLOPT_WRITEFUNCTION=>static function ($curl, $chunk) use (&$received) {
                    if (strlen($received) + strlen($chunk) > 65536) return 0;
                    $received .= $chunk;
                    return strlen($chunk);
                },
            ]);
            $ok = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $errno = curl_errno($curl);
            curl_close($curl);
            if ($errno === CURLE_OPERATION_TIMEDOUT) throw new RuntimeException('CLOUDFLARE_TIMEOUT');
            if ($ok === false) throw new RuntimeException('CLOUDFLARE_NETWORK_ERROR');
            if ($status === 401 || $status === 403) throw new RuntimeException('CLOUDFLARE_AUTH_ERROR');
            if ($status !== 200) throw new RuntimeException('CLOUDFLARE_PROVIDER_ERROR');
            return $received;
        };
    }
    $raw = $transport($url, $headers, $body);
    if (!is_string($raw) || strlen($raw) > 65536) throw new RuntimeException('INVALID_PROVIDER_REPLY');
    $data = json_decode($raw, true);
    $text = $data['reply'] ?? null;
    if (!is_string($text) || trim($text) === '' || mb_strlen($text, 'UTF-8') > 12000) {
        throw new RuntimeException('INVALID_PROVIDER_REPLY');
    }
    return ['reply'=>trim($text),'actions'=>[],'state'=>'SPEAKING','mode'=>'cloudflare'];
}
