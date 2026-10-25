<?php
/** Ollama stays on the host; the model receives no business tools or session identity. */
function SchoolSafeJaspeLocalAI(string $message, string $model, ?callable $transport = null): array
{
    if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.:\/-]{0,127}$/', $model)) {
        throw new RuntimeException('LOCAL_MODEL_UNAVAILABLE');
    }
    $payload = [
        'model'=>$model, 'stream'=>false, 'keep_alive'=>'5m',
        'messages'=>[
            ['role'=>'system','content'=>"Tu es JASPE, assistant de Rosario. Reponds en francais. Tu ne disposes d'aucun outil, droit ou acces aux dossiers scolaires. Ne pretends jamais avoir enregistre, publie ou consulte des donnees. Oriente les operations metier vers Rosario."],
            ['role'=>'user','content'=>$message],
        ],
        'options'=>['num_predict'=>512],
    ];
    if ($transport === null) {
        $transport = static function ($body) {
            if (!function_exists('curl_init')) throw new RuntimeException('LOCAL_AI_UNAVAILABLE');
            $curl = curl_init('http://host.docker.internal:11434/api/chat');
            curl_setopt_array($curl, [
                CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$body,
                CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>3,
                CURLOPT_TIMEOUT=>60, CURLOPT_FOLLOWLOCATION=>false,
                CURLOPT_PROXY=>'',
            ]);
            $raw = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            if (!is_string($raw) || $status !== 200 || strlen($raw) > 65536) {
                throw new RuntimeException('LOCAL_AI_UNAVAILABLE');
            }
            return $raw;
        };
    }
    $raw = $transport(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    if (!is_string($raw) || strlen($raw) > 65536) throw new RuntimeException('INVALID_LOCAL_AI_REPLY');
    $data = json_decode($raw, true);
    $text = $data['message']['content'] ?? null;
    if (!is_string($text) || trim($text) === '' || mb_strlen($text, 'UTF-8') > 12000) {
        throw new RuntimeException('INVALID_LOCAL_AI_REPLY');
    }
    return ['reply'=>trim($text), 'actions'=>[], 'state'=>'SPEAKING', 'mode'=>'local-ai'];
}
