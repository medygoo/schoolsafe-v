<?php
function SchoolSafeRemediationPrincipalScope(int $user, int $school, int $year): array
{
    $rows=DBGet('SELECT DISTINCT su.student_id FROM students_join_users su JOIN student_enrollment e ON e.student_id=su.student_id'
        . ' WHERE su.staff_id=' . $user . ' AND e.school_id=' . $school . ' AND e.syear=' . $year);
    $ids=array_values(array_map(static function($row){return (string)$row['STUDENT_ID'];},$rows));
    if (!$ids) return [];
    if (count($ids)>500) throw new RuntimeException('PRINCIPAL_SCOPE_UNAVAILABLE');
    $config=json_decode(file_get_contents('/var/lib/schoolsafe-card-bridge/relay-config.json'),true,16,JSON_THROW_ON_ERROR);
    if (($config['environment']??'')!=='local-isolated-test-only' || ($config['sourceInstance']??'')!=='codex-addons-local'
        || !preg_match('/^[a-f0-9]{64}$/D',$config['secretHex']??'')) throw new RuntimeException('PRINCIPAL_SCOPE_UNAVAILABLE');
    $now=time();
    $body=json_encode(['version'=>1,'audience'=>'schoolsafe-scan-bridge','sourceInstance'=>'codex-addons-local',
        'nativeUserId'=>(string)$user,'nativeSchoolId'=>(string)$school,'nativeYear'=>(string)$year,
        'operation'=>'notifications','input'=>['nativeStudentIds'=>$ids],
        'issuedAt'=>$now,'expiresAt'=>$now+30,'nonce'=>bin2hex(random_bytes(32))],JSON_THROW_ON_ERROR);
    $curl=curl_init('http://codex-card-bridge-http:8080/bridge/scanner/notifications');
    $response='';
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>10,CURLOPT_PROTOCOLS=>CURLPROTO_HTTP,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>json_encode(['body'=>$body,'signature'=>hash_hmac('sha256',$body,hex2bin($config['secretHex']))],JSON_THROW_ON_ERROR),
        CURLOPT_WRITEFUNCTION=>static function($handle,$chunk)use(&$response){$response.=$chunk;return strlen($response)<=300000?strlen($chunk):0;}]);
    try {
        $ok=curl_exec($curl);
        if ($ok===false || curl_getinfo($curl,CURLINFO_RESPONSE_CODE)!==200) throw new RuntimeException('PRINCIPAL_SCOPE_UNAVAILABLE');
        $data=json_decode($response,true,32,JSON_THROW_ON_ERROR);
        $principal=$data['data']['principalStudentIds']??null;
        if (!is_array($principal)) throw new RuntimeException('PRINCIPAL_SCOPE_UNAVAILABLE');
        foreach ($principal as $id) {
            if (!is_string($id) || !preg_match('/^[1-9][0-9]{0,8}$/D',$id) || !in_array($id,$ids,true)) throw new RuntimeException('PRINCIPAL_SCOPE_UNAVAILABLE');
        }
        return array_map('intval',$principal);
    } finally {curl_close($curl);}
}
