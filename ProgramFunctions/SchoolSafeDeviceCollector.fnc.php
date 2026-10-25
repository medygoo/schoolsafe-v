<?php
require_once __DIR__.'/SchoolSafeDeviceHub.fnc.php';

function SchoolSafeCollectorKey(): string
{
    $path='/var/lib/schoolsafe-device-hub/key';
    $key=is_file($path)?file_get_contents($path):false;
    if(!is_string($key)||strlen($key)!==SODIUM_CRYPTO_SECRETBOX_KEYBYTES)throw new RuntimeException('COLLECTOR_KEY_UNAVAILABLE');
    return $key;
}
function SchoolSafeCollectorSeal(string $username,string $password): string
{
    if($username===''||strlen($username)>100||$password===''||strlen($password)>500)throw new RuntimeException('COLLECTOR_CREDENTIALS_INVALID');
    $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return base64_encode($nonce.sodium_crypto_secretbox(json_encode([$username,$password],JSON_THROW_ON_ERROR),$nonce,SchoolSafeCollectorKey()));
}
function SchoolSafeCollectorOpen(string $cipher): array
{
    $bytes=base64_decode($cipher,true);
    if($bytes===false||strlen($bytes)<=SODIUM_CRYPTO_SECRETBOX_NONCEBYTES)throw new RuntimeException('COLLECTOR_CREDENTIALS_INVALID');
    $plain=sodium_crypto_secretbox_open(substr($bytes,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),substr($bytes,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),SchoolSafeCollectorKey());
    if($plain===false)throw new RuntimeException('COLLECTOR_CREDENTIALS_INVALID');
    return json_decode($plain,true,4,JSON_THROW_ON_ERROR);
}
function SchoolSafeCollectorContext(int $deviceId,bool $lock=false): array
{
    if(!SchoolSafeDeviceHubPermission(true))throw new RuntimeException('COLLECTOR_PERMISSION_REQUIRED');
    $school=(int)UserSchool();$year=(int)UserSyear();$actor=(int)User('STAFF_ID');
    if(!DBGet("SELECT 1 FROM staff WHERE staff_id=$actor AND syear=$year AND profile='admin'
        AND (current_school_id=$school OR position(',$school,' IN schools)>0)"))throw new RuntimeException('COLLECTOR_CONTEXT_DENIED');
    $rows=DBGet("SELECT d.*,c.actor_id,c.syear,c.credentials,c.interval_seconds,c.cursor_at,c.window_end,
        c.page_position,c.revision FROM schoolsafe_devices d JOIN schoolsafe_device_collectors c ON c.device_id=d.id
        WHERE d.id=$deviceId AND d.school_id=$school AND d.enabled=true AND c.enabled=true
        AND c.actor_id=$actor AND c.syear=$year".($lock?' FOR UPDATE OF d,c':''));
    if(count($rows)!==1)throw new RuntimeException('COLLECTOR_CONTEXT_DENIED');
    $row=reset($rows);
    if($row['ADAPTER']!=='hikvision_isapi'||!SchoolSafeDevicePrivateIp($row['IP_ADDRESS']))throw new RuntimeException('COLLECTOR_ADAPTER_UNAVAILABLE');
    return $row;
}
function SchoolSafeCollectorCapture(int $deviceId,?callable $fetch=null,bool $ownTransaction=true): int
{
    $context=SchoolSafeCollectorContext($deviceId);$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));
    $from=new DateTimeImmutable($context['CURSOR_AT']);
    $end=$context['WINDOW_END']?new DateTimeImmutable($context['WINDOW_END']):min($from->modify('+1 day'),$now);
    if($from>$end)throw new RuntimeException('COLLECTOR_CLOCK_INVALID');
    $position=(int)$context['PAGE_POSITION'];
    if($fetch===null){
        [$username,$password]=SchoolSafeCollectorOpen($context['CREDENTIALS']);
        try{$page=SchoolSafeDeviceFetch($context,$username,$password,$from->format(DateTimeInterface::ATOM),$end->format(DateTimeInterface::ATOM),$position,true);}finally{sodium_memzero($password);}
    }else $page=$fetch($context,$from,$end,$position);
    if(!is_array($page))throw new RuntimeException('COLLECTOR_RESPONSE_INVALID');
    $rows=isset($page['events'])?$page['events']:$page;
    if(!is_array($rows))throw new RuntimeException('COLLECTOR_RESPONSE_INVALID');
    $more=isset($page['events'])?($page['more']??false):count($rows)===100;
    if(!is_bool($more)||($more&&!$rows))throw new RuntimeException('COLLECTOR_RESPONSE_INVALID');
    if(!is_array($rows)||count($rows)>100)throw new RuntimeException('COLLECTOR_RESPONSE_INVALID');
    $events=[];foreach($rows as $row){if(!is_array($row))throw new RuntimeException('COLLECTOR_RESPONSE_INVALID');$events[]=SchoolSafeDeviceEvent($row,'hikvision_isapi');}
    $q=static function($v){return "'".DBEscapeString((string)$v)."'";};
    if($ownTransaction)DBQuery('BEGIN');
    try{
        $fresh=SchoolSafeCollectorContext($deviceId,true);
        if((int)$fresh['REVISION']!==(int)$context['REVISION']||$fresh['CURSOR_AT']!==$context['CURSOR_AT']||(int)$fresh['PAGE_POSITION']!==$position)throw new RuntimeException('COLLECTOR_CONTEXT_CHANGED');
        foreach($events as $event){
            $json=json_encode($event,JSON_THROW_ON_ERROR);$hash=hash('sha256',$json);
            $old=DBGet("SELECT payload_hash FROM schoolsafe_device_queue WHERE device_id=$deviceId AND external_id=".$q($event['id']));
            if($old){if(reset($old)['PAYLOAD_HASH']!==$hash)throw new RuntimeException('COLLECTOR_EVENT_COLLISION');continue;}
            DBQuery("INSERT INTO schoolsafe_device_queue(device_id,external_id,payload_hash,event) VALUES($deviceId,".$q($event['id']).",".$q($hash).",".$q($json)."::jsonb)");
        }
        if($more){$cursor=$from;$window=$q($end->format(DateTimeInterface::ATOM));$next=$position+count($rows);}
        else{
            // A stable window is exhausted before advancing; overlap catches delayed events.
            $cursor=$end<$now->modify('-5 minutes')?$end:$end->modify('-5 minutes');
            if($cursor<$from)$cursor=$from;$window='NULL';$next=0;
        }
        DBQuery("UPDATE schoolsafe_device_collectors SET cursor_at=".$q($cursor->format(DateTimeInterface::ATOM)).",window_end=$window,page_position=$next WHERE device_id=$deviceId");
        if($ownTransaction)DBQuery('COMMIT');return count($events);
    }catch(Throwable $e){if($ownTransaction)DBQuery('ROLLBACK');throw $e;}
}
function SchoolSafeCollectorDeliver(int $deviceId,bool $ownTransaction=true,?callable $beforeAck=null): array
{
    if($ownTransaction)DBQuery('BEGIN');
    try{
        SchoolSafeCollectorContext($deviceId,true);
        $rows=DBGet("SELECT id,event FROM schoolsafe_device_queue WHERE device_id=$deviceId AND delivered_at IS NULL ORDER BY id LIMIT 100 FOR UPDATE");
        $events=[];$ids=[];foreach($rows as $row){$events[]=json_decode($row['EVENT'],true,8,JSON_THROW_ON_ERROR);$ids[]=(int)$row['ID'];}
        $counts=$events?SchoolSafeDeviceImport($deviceId,$events,false,true):['imported'=>0,'duplicate'=>0,'review'=>0];
        if($beforeAck!==null)$beforeAck();
        if($ids)DBQuery('UPDATE schoolsafe_device_queue SET delivered_at=now() WHERE id IN ('.implode(',',$ids).')');
        if($ownTransaction)DBQuery('COMMIT');return $counts;
    }catch(Throwable $e){if($ownTransaction)DBQuery('ROLLBACK');throw $e;}
}
function SchoolSafeCollectorConfigure(int $deviceId,array $input): void
{
    if(!SchoolSafeDeviceHubPermission(true))throw new RuntimeException('COLLECTOR_PERMISSION_REQUIRED');
    $school=(int)UserSchool();$year=(int)UserSyear();$actor=(int)User('STAFF_ID');
    $interval=filter_var($input['interval']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>30,'max_range'=>3600]]);
    $from=DateTimeImmutable::createFromFormat('!Y-m-d',(string)($input['start']??''),new DateTimeZone('Africa/Kinshasa'));
    if(!$interval||!$from||$from->format('Y-m-d')!==($input['start']??'')||$from>new DateTimeImmutable('today',new DateTimeZone('Africa/Kinshasa')))throw new RuntimeException('COLLECTOR_SETTINGS_INVALID');
    $device=DBGet("SELECT 1 FROM schoolsafe_devices WHERE id=$deviceId AND school_id=$school AND enabled=true AND adapter='hikvision_isapi'");
    if(!$device)throw new RuntimeException('COLLECTOR_CONTEXT_DENIED');
    $cipher=DBEscapeString(SchoolSafeCollectorSeal((string)($input['username']??''),(string)($input['password']??'')));
    $start=DBEscapeString($from->format(DateTimeInterface::ATOM));
    DBQuery("INSERT INTO schoolsafe_device_collectors(device_id,actor_id,syear,enabled,credentials,interval_seconds,cursor_at)
        VALUES($deviceId,$actor,$year,true,'$cipher',$interval,'$start') ON CONFLICT(device_id) DO UPDATE SET
        actor_id=$actor,syear=$year,enabled=true,credentials=EXCLUDED.credentials,interval_seconds=$interval,
        revision=schoolsafe_device_collectors.revision+1,failures=0,next_attempt_at=now(),last_error=NULL,updated_at=now()");
}
