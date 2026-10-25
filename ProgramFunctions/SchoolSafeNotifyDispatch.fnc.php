<?php
// Existing native Notify deliveries: durable per-row ownership, no parallel
// outbox and no lease expiry that could resend an accepted-but-unacknowledged message.
require_once __DIR__.'/SchoolSafeNotifyRecipient.fnc.php';
require_once __DIR__.'/SchoolSafeSchoolScope.fnc.php';

function SchoolSafeNotifyDispatchGet($connection,string $sql): array
{
    $result=pg_query($connection,$sql);
    if($result===false)throw new RuntimeException('NOTIFY_DB_UNAVAILABLE');
    $rows=[];while($row=pg_fetch_assoc($result))$rows[count($rows)+1]=array_change_key_case($row,CASE_UPPER);
    return $rows;
}

function SchoolSafeNotifyDispatchClaim($connection,string $channel): ?array
{
    if(!in_array($channel,['all','feed','email','push','whatsapp'],true)
        ||pg_transaction_status($connection)!==PGSQL_TRANSACTION_IDLE)throw new RuntimeException('INVALID_DISPATCH_CONTEXT');
    $token=bin2hex(random_bytes(16));
    // One statement, autocommitted BEFORE any network/provider work.
    $sql="WITH candidate AS (SELECT id FROM schoolsafe_notify_deliveries WHERE status='pending'"
        ." AND dispatch_claim_token IS NULL AND ($1='all' OR channel=$1)"
        .' ORDER BY created_at,id FOR UPDATE SKIP LOCKED LIMIT 1), claimed AS ('
        ."UPDATE schoolsafe_notify_deliveries d SET dispatch_claim_token=$2,dispatch_claimed_at=CURRENT_TIMESTAMP,"
        ."attempts=attempts+1,updated_at=CURRENT_TIMESTAMP FROM candidate c WHERE d.id=c.id RETURNING d.*)"
        .' SELECT d.id,d.channel,d.audience,d.recipient_staff_id,d.event_id,d.dispatch_claim_token,'
        .' d.school_id AS delivery_school,d.syear AS delivery_year,e.school_id,e.syear,e.student_id,'
        .' e.event_type,e.payload,sch.title AS school_title FROM claimed d'
        .' JOIN schoolsafe_notify_events e ON e.id=d.event_id'
        .' LEFT JOIN schools sch ON sch.id=e.school_id AND sch.syear=e.syear';
    $result=pg_query_params($connection,$sql,[$channel,$token]);
    if($result===false)throw new RuntimeException('DISPATCH_CLAIM_UNAVAILABLE');
    if(pg_num_rows($result)>1)throw new RuntimeException('INVALID_DISPATCH_CONTEXT');
    return pg_fetch_assoc($result)?:null;
}

// Consume the original delivery, never resolve a new historical recipient.
// Both the event scope AND live native identity/link/account authority hold.
function SchoolSafeNotifyDispatchRecipient($connection,array $row): array
{
    $school=(int)($row['school_id']??0);$year=(int)($row['syear']??0);$parent=(int)($row['recipient_staff_id']??0);
    if(($row['school_title']??null)===null||min($school,$year,$parent)<1||$school!==(int)($row['delivery_school']??0)||$year!==(int)($row['delivery_year']??0))throw new RuntimeException('RECIPIENT_FORBIDDEN');
    $query=static fn($sql)=>SchoolSafeNotifyDispatchGet($connection,$sql);
    $db=['get'=>$query];$student=$row['student_id']===null?0:(int)$row['student_id'];
    if($row['student_id']!==null&&$student<1)throw new RuntimeException('RECIPIENT_FORBIDDEN');
    if(in_array($row['event_type'],['financial.control','payment.receipt'],true)&&(($row['audience']??null)!=='parent'||$student<1))throw new RuntimeException('RECIPIENT_FORBIDDEN');
    $payload=json_decode((string)($row['payload']??''),true);
    if(!is_array($payload))throw new RuntimeException('RECIPIENT_UNAVAILABLE');
    if(($payload['source']??null)==='native_scan'){
        if(($row['audience']??null)!=='parent'||$student<1||!in_array($row['event_type'],['security.entry','student.pickup','financial.control'],true))throw new RuntimeException('RECIPIENT_FORBIDDEN');
        if($row['event_type']==='financial.control'&&(($payload['observation']['school']['id']??null)!==$school
            ||($payload['observation']['syear']??null)!==$year||($payload['observation']['student']['id']??null)!==$student))throw new RuntimeException('RECIPIENT_FORBIDDEN');
    }
    if(($row['audience']??null)==='parent'&&!SchoolSafeNotifyParentCurrent($parent,$school,$year,$student,$db))throw new RuntimeException('RECIPIENT_FORBIDDEN');
    $staff=$query('SELECT STAFF_ID,PROFILE,SCHOOLS,USERNAME,EMAIL'.($row['channel']==='whatsapp'?',CUSTOM_200000001':'').' FROM staff WHERE STAFF_ID='.$parent.' AND SYEAR='.$year);
    if(count($staff)!==1)throw new RuntimeException('RECIPIENT_FORBIDDEN');
    $staff=$staff[1];
    if(($row['audience']??null)==='parent'&&($staff['PROFILE']??null)!=='parent')throw new RuntimeException('RECIPIENT_FORBIDDEN');
    if(($row['audience']??null)!=='parent'){
        $allowed=['teacher'=>['teacher'],'direction'=>['admin'],'staff'=>['admin','teacher']];
        if(!in_array($staff['PROFILE']??null,$allowed[$row['audience']??'']??[],true)
            ||!SchoolSafeScopeContains(SchoolSafeScopeParse($staff['SCHOOLS']),$school)
            ||!SchoolSafeNotifyAccountReadable($staff['USERNAME'],$school,$db))throw new RuntimeException('RECIPIENT_FORBIDDEN');
    }
    return $staff;
}

function SchoolSafeNotifyDispatchFinish($connection,array $row,string $status,string $error='',string $provider='',bool $uncertain=false): void
{
    if(!in_array($status,['sent','accepted','failed','pending'],true)||($uncertain&&$status!=='pending'))throw new RuntimeException('INVALID_DISPATCH_CONTEXT');
    $result=pg_query_params($connection,'UPDATE schoolsafe_notify_deliveries SET status=$3,last_error=$4,provider_msg_id=$5,updated_at=CURRENT_TIMESTAMP'
        .($uncertain?'':',dispatch_claim_token=NULL')
        ." WHERE id=$1 AND dispatch_claim_token=$2 AND status='pending' RETURNING id",
        [(int)$row['id'],$row['dispatch_claim_token'],$status,substr($error,0,500),substr($provider,0,128)]);
    if($result===false||pg_num_rows($result)!==1)throw new RuntimeException('DISPATCH_RESULT_UNAVAILABLE');
}
