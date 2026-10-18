<?php
require_once __DIR__.'/../modules/SchoolSafe/Permissions.php';

function SchoolSafeDeviceHubPermission(bool $write=false): bool
{
    global $RosarioModules;
    return User('PROFILE')==='admin' && !empty($RosarioModules['Entry_Exit'])
        && SchoolSafeNativeCommandPermission($write?'SchoolSafe/DevicesManage.php':'SchoolSafe/DevicesRead.php')
        && AllowUse('Entry_Exit/Records.php')
        && (!$write || (AllowEdit('Entry_Exit/AddRecords.php') && AllowEdit('School_Setup/Configuration.php')));
}

function SchoolSafeDevicePrivateIp(string $ip): bool
{
    if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4))return false;
    $parts=array_map('intval',explode('.',$ip));
    return $parts[0]===10 || ($parts[0]===172 && $parts[1]>=16 && $parts[1]<=31)
        || ($parts[0]===192 && $parts[1]===168);
}

function SchoolSafeDeviceEvent(array $row,string $adapter): array
{
    if($adapter==='hikvision_isapi'){
        $accepted=(int)($row['major']??0)===5 && (int)($row['minor']??0)===38;
        $row=['id'=>$row['serialNo']??null,'employee_id'=>$row['employeeNoString']??null,
            'time'=>$row['time']??null,'direction'=>$accepted?(['checkIn'=>'in','checkOut'=>'out'][$row['attendanceStatus']??'']??'unknown'):'unknown'];
    }elseif($adapter!=='normalized_json')throw new RuntimeException('Adaptateur inconnu.');
    if(array_diff(array_keys($row),['id','employee_id','time','direction']))throw new RuntimeException('Champs evenement non autorises.');
    foreach(['id'=>100,'employee_id'=>64] as $key=>$limit){
        if(!is_scalar($row[$key]??null)||!preg_match('/^[a-zA-Z0-9._:-]{1,'.$limit.'}$/D',(string)$row[$key]))throw new RuntimeException('Identifiant evenement invalide.');
    }
    if(!is_string($row['time']??null)||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D',$row['time']))throw new RuntimeException('Horodatage ISO avec fuseau requis.');
    $time=new DateTimeImmutable($row['time']);
    if(DateTimeImmutable::getLastErrors()!==false||$time>new DateTimeImmutable('+5 minutes'))throw new RuntimeException('Horodatage invalide ou futur.');
    $direction=$row['direction']??'unknown';
    if(!in_array($direction,['in','out','unknown'],true))throw new RuntimeException('Sens de pointage invalide.');
    return ['id'=>(string)$row['id'],'employee_id'=>(string)$row['employee_id'],
        'time'=>$time->format(DateTimeInterface::ATOM),'direction'=>$direction];
}

function SchoolSafeDeviceImport(int $deviceId,array $input,bool $ownTransaction=true,bool $normalized=false): array
{
    if(!SchoolSafeDeviceHubPermission(true))throw new RuntimeException('Permission refusee.');
    if(count($input)>200)throw new RuntimeException('Maximum 200 evenements par lot.');
    $school=(int)UserSchool();$year=(int)UserSyear();$actor=(int)User('STAFF_ID');
    $q=static function($v){return "'".DBEscapeString((string)$v)."'";};
    if($ownTransaction)DBQuery('BEGIN');
    try{
        $devices=DBGet("SELECT * FROM schoolsafe_devices WHERE id=$deviceId AND school_id=$school AND enabled=true FOR UPDATE");
        if(count($devices)!==1)throw new RuntimeException('Equipement absent ou suspendu.');
        $device=reset($devices);$counts=['imported'=>0,'duplicate'=>0,'review'=>0];
        require_once __DIR__.'/../modules/Entry_Exit/includes/common.fnc.php';
        foreach($input as $raw){
            if(!is_array($raw))throw new RuntimeException('Evenement invalide.');
            $event=SchoolSafeDeviceEvent($raw,$normalized?'normalized_json':$device['ADAPTER']);$hash=hash('sha256',json_encode($event,JSON_THROW_ON_ERROR));
            $existing=DBGet("SELECT payload_hash FROM schoolsafe_device_events WHERE device_id=$deviceId AND external_id=".$q($event['id']));
            if($existing){if(reset($existing)['PAYLOAD_HASH']!==$hash)throw new RuntimeException('Identifiant reutilise avec un autre evenement.');$counts['duplicate']++;continue;}
            $people=DBGet("SELECT s.staff_id FROM schoolsafe_device_staff m JOIN staff s ON s.staff_id=m.staff_id
                WHERE m.device_id=$deviceId AND m.employee_id=".$q($event['employee_id'])." AND m.syear=$year
                AND s.syear=$year AND s.profile IN ('admin','teacher')
                AND (s.current_school_id=$school OR position(',$school,' IN s.schools)>0)");
            $staff=$people?(int)reset($people)['STAFF_ID']:null;$status=$staff?'imported':'unmapped';$record=null;
            $local=(new DateTimeImmutable($event['time']))->setTimezone(new DateTimeZone('Africa/Kinshasa'));
            $academic=DBGet("SELECT 1 FROM school_marking_periods WHERE school_id=$school AND syear=$year
                AND start_date<=".$q($local->format('Y-m-d'))." AND end_date>=".$q($local->format('Y-m-d'))." LIMIT 1");
            if(!$academic)$status='outside_context';
            if($event['direction']==='unknown')$status='unknown_direction';
            $checkpoint=(int)$device['CHECKPOINT_ID'];
            $point=DBGet("SELECT type FROM entry_exit_checkpoints WHERE id=$checkpoint");
            if(!$point||(!empty(reset($point)['TYPE'])&&reset($point)['TYPE']!==$event['direction']))$status='checkpoint_mismatch';
            if($status==='imported'){
                $unauthorized=EntryExitIsUnauthorized($checkpoint,$local->format('H:i:s'))?'Y':'';
                $saved=DBGet(DBQuery("INSERT INTO entry_exit_staff_records(staff_id,checkpoint_id,type,unauthorized,comments,recorded_at)
                    VALUES($staff,$checkpoint,".$q($event['direction']).",".$q($unauthorized).",".$q('Device Hub #'.$deviceId).",".$q($local->format('Y-m-d H:i:s')).") RETURNING id"));
                if(!$saved)throw new RuntimeException('Pointage natif non enregistre.');
                $record=(int)reset($saved)['ID'];$counts['imported']++;
            }else $counts['review']++;
            DBQuery("INSERT INTO schoolsafe_device_events(device_id,external_id,payload_hash,employee_id,occurred_at,direction,status,staff_id,record_id,created_by)
                VALUES($deviceId,".$q($event['id']).",".$q($hash).",".$q($event['employee_id']).",".$q($event['time']).",".$q($event['direction']).",".$q($status).",".($staff??'NULL').",".($record??'NULL').",$actor)");
        }
        DBQuery("UPDATE schoolsafe_devices SET last_sync_at=now() WHERE id=$deviceId");if($ownTransaction)DBQuery('COMMIT');return $counts;
    }catch(Throwable $e){if($ownTransaction)DBQuery('ROLLBACK');throw $e;}
}

function SchoolSafeDeviceFetch(array $device,string $username,string $password,string $start,string $end,int $position=0,bool $withPage=false): array
{
    if($device['ADAPTER']!=='hikvision_isapi'||!SchoolSafeDevicePrivateIp($device['IP_ADDRESS'])||$username===''||$password==='')throw new RuntimeException('Connexion Hikvision incomplete.');
    $curl=curl_init($device['SCHEME'].'://'.$device['IP_ADDRESS'].':'.(int)$device['PORT'].'/ISAPI/AccessControl/AcsEvent?format=json');$response='';
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_HTTPAUTH=>CURLAUTH_DIGEST,CURLOPT_USERNAME=>$username,CURLOPT_PASSWORD=>$password,
        CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>15,
        CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>json_encode(['AcsEventCond'=>['searchID'=>'schoolsafe','searchResultPosition'=>$position,'maxResults'=>100,'major'=>$withPage?5:0,'minor'=>$withPage?38:0,'startTime'=>$start,'endTime'=>$end]],JSON_THROW_ON_ERROR),
        CURLOPT_WRITEFUNCTION=>static function($handle,$chunk)use(&$response){$response.=$chunk;return strlen($response)<=1000000?strlen($chunk):0;}]);
    try{
        if(curl_exec($curl)===false||curl_getinfo($curl,CURLINFO_RESPONSE_CODE)!==200)throw new RuntimeException('Connexion refusee ou appareil inaccessible.');
        $data=json_decode($response,true,32,JSON_THROW_ON_ERROR);$events=$data['AcsEvent']['InfoList']??null;
        if($events===null && isset($data['AcsEvent']['numOfMatches']) && (int)$data['AcsEvent']['numOfMatches']===0)$events=[];
        if(!is_array($events)||count($events)>100)throw new RuntimeException('Format Hikvision non reconnu.');
        $more=($data['AcsEvent']['responseStatusStrg']??'')==='MORE'
            || (isset($data['AcsEvent']['totalMatches'])&&$position+count($events)<(int)$data['AcsEvent']['totalMatches']);
        if($more&&!$events)throw new RuntimeException('Format Hikvision non reconnu.');
        return $withPage?['events'=>$events,'more'=>$more||count($events)===100]:$events;
    }finally{curl_close($curl);}
}
