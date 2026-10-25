<?php
// Native Notify CLI only. Claims are durable BEFORE provider work. Never
// expire claims or retry uncertain acceptance: a crashed worker may have sent.
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once __DIR__.'/ProgramFunctions/SchoolSafeMailTemplate.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeBrevo.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeWebPush.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeSms.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeWhatsapp.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeNotifyDispatch.fnc.php';
require_once __DIR__.'/schoolsafe-notify-cli.php';
$once=in_array('--once',$argv,true);$limit=50;$channel='all';$retryFailed=in_array('--retry-failed',$argv,true);
foreach($argv as$arg){
    if(str_starts_with($arg,'--limit='))$limit=max(1,min(500,(int)substr($arg,8)));
    if(str_starts_with($arg,'--channel='))$channel=substr($arg,10);
}
if(!$once||!in_array($channel,['all','feed','email','push','whatsapp'],true)){
    fwrite(STDERR,"Usage: php schoolsafe-notify-dispatch.php --once [--limit=50] [--channel=all|feed|email|push|whatsapp] [--retry-failed]\n");exit(2);
}
$host=getenv('SCHOOLSAFE_DB_HOST')?:'db';$port=getenv('SCHOOLSAFE_DB_PORT')?:'5432';
$user=getenv('POSTGRES_USER')?:'schoolsafe';$pass=getenv('POSTGRES_PASSWORD')?:'';$dbname=getenv('POSTGRES_DB')?:'schoolsafe';
$conn=@pg_connect("host=$host port=$port dbname=$dbname user=$user password=$pass connect_timeout=5");
if($conn===false){fwrite(STDERR,"DB_UNAVAILABLE\n");exit(1);}
SchoolSafeNotifyCliInit($conn);
// Missing migration is unavailable, never permission to run the old unclaimed path.
$ready=pg_query($conn,"SELECT dispatch_claim_token,dispatch_claimed_at FROM schoolsafe_notify_deliveries LIMIT 0");
if($ready===false){fwrite(STDERR,"DISPATCH_CLAIM_UNAVAILABLE\n");exit(1);}
if($retryFailed){
    $retry=pg_query_params($conn,"UPDATE schoolsafe_notify_deliveries SET status='pending',updated_at=CURRENT_TIMESTAMP"
        ." WHERE status='failed' AND dispatch_claim_token IS NULL AND last_error LIKE 'PRE_SEND:%' AND ($1='all' OR channel=$1)",[$channel]);
    if($retry===false){fwrite(STDERR,"RETRY_UNAVAILABLE\n");exit(1);}
}
// Original deliveries only; no newly linked parent is resolved by this mirror.
if($channel==='all'||$channel==='whatsapp'){
    $mirror=pg_query($conn,"INSERT INTO schoolsafe_notify_deliveries(event_id,school_id,syear,audience,recipient_staff_id,channel)"
        ." SELECT DISTINCT d.event_id,d.school_id,d.syear,d.audience,d.recipient_staff_id,'whatsapp'"
        .' FROM schoolsafe_notify_deliveries d JOIN schoolsafe_notify_events e ON e.id=d.event_id'
        .' AND e.school_id=d.school_id AND e.syear=d.syear'
        ." WHERE d.channel IN ('email','push') AND d.status IN ('pending','accepted','sent')"
        ." AND e.event_type IN ('security.entry','student.pickup','financial.control','payment.receipt','school.announcement','remediation.update','attendance.closure','absence.detected','case.updated','security.p1')"
        .' ON CONFLICT(event_id,audience,recipient_staff_id,channel) DO NOTHING');
    if($mirror===false){fwrite(STDERR,"WA_MIRROR_UNAVAILABLE\n");exit(1);}
}
$transports=$GLOBALS['schoolsafe_notify_transports']??[];
if(!is_array($transports)||array_diff(array_keys($transports),['email','push','waha','evolution','status']))throw new RuntimeException('INVALID_DISPATCH_CONTEXT');
foreach($transports as$transport)if(!is_callable($transport))throw new RuntimeException('INVALID_DISPATCH_CONTEXT');
$forceFail=getenv('SCHOOLSAFE_NOTIFY_FORCE_FAIL')==='1';$sent=0;$failed=0;$held=0;
for($i=0;$i<$limit;$i++){
    try{$row=SchoolSafeNotifyDispatchClaim($conn,$channel);}catch(Throwable $error){fwrite(STDERR,"DISPATCH_CLAIM_UNAVAILABLE\n");exit(1);}
    if($row===null)break;
    // Server-side injection only for the guarded disposable CLI harness.
    // No HTTP/env input selects hooks; ordinary workers have none.
    $afterClaim=$GLOBALS['schoolsafe_notify_after_claim']??null;
    if(is_callable($afterClaim))$afterClaim($row);
    $attempted=false;$status='failed';$provider='';$error='';$uncertain=false;
    try{
        $staff=SchoolSafeNotifyDispatchRecipient($conn,$row);
        if($forceFail)throw new RuntimeException('FORCED_FAILURE');
        $payload=json_decode($row['payload'],true,32,JSON_THROW_ON_ERROR);
        if($row['channel']==='feed'){
            $status='sent';
        }elseif($row['channel']==='email'){
            $email=trim((string)$staff['EMAIL']);
            if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('NO_RECIPIENT_EMAIL');
            $config=SchoolSafeBrevoConfig();if($config===null)throw new RuntimeException('NO_EMAIL_PROVIDER');
            $assets=rtrim(trim((string)getenv('SCHOOLSAFE_ASSETS_BASE_URL'))?:'https://app.shoolsafe.tech','/');
            $mail=SchoolSafeMailBuild(['event_type'=>$row['event_type'],'payload'=>$payload],
                ['school_name'=>$row['school_title'],'locale'=>'fr','brand_logo_url'=>$assets.'/assets/themes/SchoolSafe/logo.png','jaspe_url'=>$assets.'/assets/jaspe2d/pack1/wave.webp']);
            $current=SchoolSafeNotifyDispatchRecipient($conn,$row);
            if(trim((string)$current['EMAIL'])!==$email)throw new RuntimeException('RECIPIENT_CHANGED');
            $attempted=true;$out=SchoolSafeBrevoSend($email,$mail,$config,$transports['email']??null);
            if(!is_string($out['provider_msg_id']??null)||$out['provider_msg_id']==='')throw new RuntimeException('ACCEPTANCE_UNKNOWN');
            $provider=$out['provider_msg_id'];$status='accepted';
        }elseif($row['channel']==='whatsapp'){
            $to=SchoolSafeWaNormalize($staff['CUSTOM_200000001']??'');
            if($to===false)throw new RuntimeException('NO_RECIPIENT_PHONE');
            if(SchoolSafeWaSimulate())throw new RuntimeException('SIMULATION_ONLY');
            if(!SchoolSafeWaConsented((int)$row['school_id'],(int)$row['recipient_staff_id'],$to))throw new RuntimeException('CONSENT_REQUIRED');
            if(!SchoolSafeWaActiveSession('waha',$transports['status']??null))throw new RuntimeException('NO_WA_SESSION');
            $copy=$payload['message']??('SchoolSafe — '.$row['event_type'].' — '.$row['school_title']);
            if(!is_string($copy)||mb_strlen(trim($copy))>2000||!SchoolSafeWaSanitize($copy))throw new RuntimeException('WA_COPY_UNAVAILABLE');
            $authorize=function()use($conn,$row,$to){
                $live=SchoolSafeNotifyDispatchRecipient($conn,$row);
                return SchoolSafeWaNormalize($live['CUSTOM_200000001']??'')===$to;
            };
            $attempted=true;
            $out=SchoolSafeWaSend($to,$copy,['school_id'=>(int)$row['school_id'],'staff_id'=>(int)$row['recipient_staff_id'],
                'purpose'=>'notify','actor'=>'native-notify-dispatch','dedup_key'=>'notify:delivery:'.$row['id'],'authorize'=>$authorize,'retry_blocked'=>true,
                'http_waha'=>$transports['waha']??null,'http_evo'=>$transports['evolution']??null,'http_status'=>$transports['status']??null]);
            if(($out['status']??'')==='blocked'){$attempted=false;throw new RuntimeException('WA_BLOCKED');}
            if(($out['status']??'')==='accepted'){
                $receipt=pg_query_params($conn,'SELECT engine,provider_msg_id FROM schoolsafe_wa_outbox WHERE id=$1',[(int)($out['outbox_id']??0)]);
                $proof=$receipt===false?false:pg_fetch_assoc($receipt);
                if(!$proof||$proof['engine']==='simulated'||trim($proof['provider_msg_id'])==='')throw new RuntimeException('ACCEPTANCE_UNKNOWN');
                $provider=$proof['provider_msg_id'];$status='accepted';
            }else{throw new RuntimeException('ACCEPTANCE_UNKNOWN');}
        }elseif($row['channel']==='push'){
            $sub=pg_query_params($conn,'SELECT endpoint FROM schoolsafe_notification_push_subscriptions WHERE staff_id=$1 ORDER BY created_at DESC LIMIT 1',[(int)$row['recipient_staff_id']]);
            if($sub===false)throw new RuntimeException('PUSH_UNAVAILABLE');
            $subscription=pg_fetch_assoc($sub);$endpoint=$subscription['endpoint']??'';
            if($endpoint===''||!preg_match('#^https://#i',$endpoint))throw new RuntimeException('PUSH_UNAVAILABLE');
            $config=SchoolSafeWebPushConfig();if($config===null)throw new RuntimeException('NO_PUSH_PROVIDER');
            $parts=parse_url($endpoint);SchoolSafeWebPushJwt('https://'.($parts['host']??''),$config);
            SchoolSafeNotifyDispatchRecipient($conn,$row);
            $attempted=true;SchoolSafeWebPushSend(['endpoint'=>$endpoint],$config,$transports['push']??null);$status='accepted';
        }
    }catch(Throwable $failure){
        // Never store raw provider/DB errors or presume an ambiguous request
        // was rejected. A durable unresolved claim requires reconciliation.
        if($attempted&&$row['channel']==='push'&&$failure->getMessage()==='SUBSCRIPTION_EXPIRED'){
            pg_query_params($conn,'DELETE FROM schoolsafe_notification_push_subscriptions WHERE staff_id=$1 AND endpoint=$2',[(int)$row['recipient_staff_id'],$endpoint]);
            $attempted=false;
        }
        if($attempted){$status='pending';$uncertain=true;$error='ACCEPTANCE_UNKNOWN';}
        else{
            $known=['FORCED_FAILURE','RECIPIENT_FORBIDDEN','RECIPIENT_UNAVAILABLE','NO_RECIPIENT_EMAIL','NO_EMAIL_PROVIDER',
                'RECIPIENT_CHANGED','NO_RECIPIENT_PHONE','SIMULATION_ONLY','CONSENT_REQUIRED','NO_WA_SESSION','WA_COPY_UNAVAILABLE','WA_BLOCKED','PUSH_UNAVAILABLE','NO_PUSH_PROVIDER','SUBSCRIPTION_EXPIRED'];
            $code=in_array($failure->getMessage(),$known,true)?$failure->getMessage():'CONTEXT_UNAVAILABLE';
            $error='PRE_SEND:'.$code;$status='failed';
        }
    }
    try{SchoolSafeNotifyDispatchFinish($conn,$row,$status,$error,$provider,$uncertain);}
    catch(Throwable $failure){fwrite(STDERR,"DISPATCH_RESULT_UNAVAILABLE\n");exit(1);}
    if($uncertain)$held++;elseif(in_array($status,['sent','accepted'],true))$sent++;else$failed++;
}
echo "DISPATCH sent=$sent failed=$failed held=$held channel=$channel\n";
