<?php
/** Match the existing JASPE Worker's canonical JSON and HMAC protocol. */
function SchoolSafeJaspeCloudflare(string $message, string $sessionKey, string $url, string $secret, ?callable $transport = null, array $history = [], string $preamble = ''): array
{
    $parts = parse_url($url);
    if (!$parts || ($parts['scheme'] ?? '') !== 'https' ||
        !preg_match('/^[a-z0-9.-]+\.workers\.dev$/i', $parts['host'] ?? '') ||
        isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) ||
        isset($parts['fragment']) || (isset($parts['port']) && $parts['port'] !== 443) ||
        strlen($secret) < 32 || $sessionKey === '') {
        throw new RuntimeException('CLOUDFLARE_NOT_CONFIGURED');
    }
    // Memoire de conversation (03/11) : le Worker en service ne lit pas `history` ; les derniers
    // echanges de la session PHP sont donc places en tete du message, sans modifier le Worker.
    // Le Worker n'en garde jamais aucune trace.
    $payload = ['message'=>SchoolSafeJaspeContextMessage($message, $history, $preamble),'session_key'=>$sessionKey];
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
                CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>60, CURLOPT_FOLLOWLOCATION=>false,
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

/** Derniers echanges valides de la memoire de session (au plus 8 entrees de 500 caracteres). */
function SchoolSafeJaspeCleanHistory(array $history): array
{
    $clean = array_values(array_filter($history, static function ($h) {
        return is_array($h) && isset($h['role'], $h['content'])
            && in_array($h['role'], ['user','assistant'], true)
            && is_string($h['content']) && trim($h['content']) !== '';
    }));
    $clean = array_slice($clean, -8);
    foreach ($clean as $i => $h) { $clean[$i]['content'] = mb_substr(trim($h['content']), 0, 500); }
    return $clean;
}

/** Message envoye au modele : echanges precedents puis nouveau message (inchange sans memoire). */
function SchoolSafeJaspeContextMessage(string $message, array $history, string $preamble = ''): string
{
    $clean = SchoolSafeJaspeCleanHistory($history);
    $head = trim($preamble) !== '' ? trim($preamble) . "\n\n" : '';
    if (!$clean) return $head === '' ? $message : $head . "Message de l'utilisateur :\n" . $message;
    $lines = [];
    foreach ($clean as $h) $lines[] = ($h['role'] === 'user' ? 'Utilisateur' : 'JASPE') . ' : ' . $h['content'];
    return $head . "Échanges précédents de cette conversation (du plus ancien au plus récent) :\n"
        . implode("\n", $lines)
        . "\n\nNouveau message de l'utilisateur (réponds à celui-ci en tenant compte des échanges précédents) :\n"
        . $message;
}

/** Ajoute un echange a la memoire de session ; l'interface (bulles) ne l'efface jamais. */
function SchoolSafeJaspeRemember(array $memory, string $message, string $reply): array
{
    $memory = SchoolSafeJaspeCleanHistory($memory);
    if (trim($message) !== '') $memory[] = ['role' => 'user', 'content' => mb_substr(trim($message), 0, 500)];
    if (trim($reply) !== '') $memory[] = ['role' => 'assistant', 'content' => mb_substr(trim($reply), 0, 500)];
    return array_slice($memory, -8);
}
