<?php
/**
 * JASPE — cerveau de secours (API LLM compatible OpenAI : Groq, OpenRouter...).
 *
 * Active UNIQUEMENT si les trois fichiers existent (600, www-data) :
 *   /var/lib/schoolsafe-jaspe/fallback-url    ex. https://api.groq.com/openai/v1/chat/completions
 *   /var/lib/schoolsafe-jaspe/fallback-key    la cle API
 *   /var/lib/schoolsafe-jaspe/fallback-model  ex. llama-3.3-70b-versatile
 * Sinon retourne null — le repli scripte existant prend le relais.
 *
 * Regle de confidentialite : l'historique de session (generique) + la question
 * seulement. Aucune donnee d'eleve n'est envoyee par ce chemin.
 */
function SchoolSafeJaspeFallbackLLM(string $message, array $history): ?array
{
    $dir = '/var/lib/schoolsafe-jaspe';
    $url = is_file($dir.'/fallback-url') ? trim((string) file_get_contents($dir.'/fallback-url')) : '';
    $key = is_file($dir.'/fallback-key') ? trim((string) file_get_contents($dir.'/fallback-key')) : '';
    $model = is_file($dir.'/fallback-model') ? trim((string) file_get_contents($dir.'/fallback-model')) : '';
    if ($url === '' || $key === '' || $model === '') { return null; }
    if (!preg_match('#^https://[a-z0-9.-]+/#i', $url)) { return null; }
    $messages = [['role' => 'system', 'content' => "Tu es JASPE, l'assistante de l'application scolaire SchoolSafe. Reponds en francais, breve et utile. Ne mentionne jamais de donnees personnelles d'eleves."]];
    foreach ($history as $echange) {
        if (isset($echange['role'], $echange['content'])
            && in_array($echange['role'], ['user', 'assistant'], true)) {
            $messages[] = ['role' => $echange['role'], 'content' => mb_substr((string) $echange['content'], 0, 500)];
        }
    }
    $messages[] = ['role' => 'user', 'content' => mb_substr($message, 0, 1000)];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 30, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
        CURLOPT_POSTFIELDS => json_encode(['model' => $model, 'messages' => $messages,
            'max_tokens' => 400, 'temperature' => 0.6], JSON_THROW_ON_ERROR)]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($response === false || $status !== 200) { return null; }
    $data = json_decode((string) $response, true, 16);
    $texte = $data['choices'][0]['message']['content'] ?? null;
    if (!is_string($texte) || trim($texte) === '') { return null; }
    return ['reply' => trim($texte), 'actions' => [], 'state' => 'IDLE', 'mode' => 'local'];
}
