<?php
// Existing 037 session owner. Provider GETs only, no messages or credentials in
// results/audit. Unsupported/missing identity is unavailable, never operational.
function SchoolSafeWaSessionPhone($jid): ?string
{
    if(!is_string($jid)||preg_match('/^([0-9]+)(?::[0-9]+)?@(c\.us|s\.whatsapp\.net)$/D',$jid,$m)!==1)return null;
    $phone=SchoolSafeWaNormalize($m[1]);return $phone===false?null:$phone;
}

function SchoolSafeWaSessionProbe(array $row,?callable $http=null): array
{
    $engine=$row['ENGINE']??null;$name=$row['SESSION_NAME']??null;
    $phone=SchoolSafeWaNormalize($row['PHONE_E164']??'');
    $failure=['verified'=>false,'state'=>'error','reason'=>'SESSION_PROVIDER_UNAVAILABLE'];
    if(!in_array($engine,['waha','evolution'],true)||!is_string($name)
        ||preg_match('/^[a-z0-9][a-z0-9-]{2,127}$/D',$name)!==1||$phone===false)return $failure;
    $credentials=SchoolSafeWaCredentials($engine);if($credentials===null)return $failure;
    $get=function($path)use($credentials,$http){
        $res=$http?$http('GET',$credentials['base_url'].$path,$credentials['key'],null)
            :SchoolSafeWaHttp('GET',$credentials['base_url'].$path,$credentials['key'],null);
        if(!is_array($res)||($res['http_code']??null)!==200||!empty($res['network_error'])||!is_string($res['body']??null))throw new RuntimeException('SESSION_PROVIDER_UNAVAILABLE');
        $body=json_decode($res['body'],true,32,JSON_THROW_ON_ERROR);
        if(!is_array($body))throw new RuntimeException('SESSION_PROVIDER_UNAVAILABLE');return $body;
    };
    try{
        if($engine==='waha'){
            $body=$get('/api/sessions/'.rawurlencode($name));
            if(($body['name']??null)!==$name)return $failure;
            if(($body['status']??null)!=='WORKING')return ['verified'=>false,'state'=>'offline','reason'=>'SESSION_NOT_CONNECTED'];
            if(SchoolSafeWaSessionPhone($body['me']['id']??null)!==$phone)return ['verified'=>false,'state'=>'error','reason'=>'SESSION_ACCOUNT_MISMATCH'];
            if(($body['me']['reachoutTimelock']['active']??false)===true||($body['me']['messageCapping']['limited']??false)===true)return ['verified'=>false,'state'=>'error','reason'=>'SESSION_LIMITED'];
        }else{
            // Official Evolution API controller + monitor source fa09d378:
            // connectionState is live; fetchInstances returns Prisma rows
            // with name/ownerJid/connectionStatus. Never persist its token.
            $body=$get('/instance/connectionState/'.rawurlencode($name));
            if(($body['instance']['instanceName']??null)!==$name)return $failure;
            if(($body['instance']['state']??null)!=='open')return ['verified'=>false,'state'=>'offline','reason'=>'SESSION_NOT_CONNECTED'];
            $instances=$get('/instance/fetchInstances?instanceName='.rawurlencode($name));
            if(!array_is_list($instances)||count($instances)!==1||($instances[0]['name']??null)!==$name
                ||($instances[0]['connectionStatus']??null)!=='open')return $failure;
            if(SchoolSafeWaSessionPhone($instances[0]['ownerJid']??null)!==$phone)return ['verified'=>false,'state'=>'error','reason'=>'SESSION_ACCOUNT_MISMATCH'];
        }
        return ['verified'=>true,'state'=>'online','reason'=>null];
    }catch(Throwable $error){return $failure;}
}

// Compare original configuration/status/version so a concurrent edit or
// suspension cannot be overwritten by a slow provider response.
function SchoolSafeWaSessionStamp(array $row,string $state,?string $status=null): void
{
    if(!in_array($state,['online','offline','error','unknown'],true)||($status!==null&&!in_array($status,['active','disabled','suspended'],true))
        ||(int)($row['ID']??0)<1||!is_string($row['UPDATED_AT']??null))throw new RuntimeException('INVALID_SESSION_CONTEXT');
    $where='ID='.(int)$row['ID'];
    foreach(['ENGINE','SESSION_NAME','PHONE_E164','STATUS','UPDATED_AT']as$key){
        if(!is_string($row[$key]??null))throw new RuntimeException('INVALID_SESSION_CONTEXT');
        $where.=' AND '.$key."='".DBEscapeString($row[$key])."'";
    }
    $result=DBGet(DBQuery("UPDATE schoolsafe_wa_sessions SET conn_state='".$state."',conn_checked_at="
        .($state==='unknown'?'NULL':'CURRENT_TIMESTAMP').',updated_at=CURRENT_TIMESTAMP'
        .($status!==null?",status='".$status."'":'').' WHERE '.$where.' RETURNING ID'));
    if(!is_array($result)||count($result)!==1)throw new RuntimeException('SESSION_CONTEXT_CHANGED');
}

function SchoolSafeWaSessionSetStatus(int $id,string $status,?callable $http=null): array
{
    if($id<1||!in_array($status,['active','disabled','suspended'],true))throw new RuntimeException('INVALID_SESSION_CONTEXT');
    $rows=DBGet('SELECT * FROM schoolsafe_wa_sessions WHERE ID='.$id);
    if(!is_array($rows)||count($rows)!==1)throw new RuntimeException('INVALID_SESSION_CONTEXT');
    $row=$rows[1];
    if($status==='active'){
        $probe=SchoolSafeWaSessionProbe($row,$http);
        SchoolSafeWaSessionStamp($row,$probe['state'],$probe['verified']?'active':'disabled');
        return $probe;
    }
    SchoolSafeWaSessionStamp($row,'unknown',$status);
    return ['verified'=>false,'state'=>'unknown','reason'=>null];
}
