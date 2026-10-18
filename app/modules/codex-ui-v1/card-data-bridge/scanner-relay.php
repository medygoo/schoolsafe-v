<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function SchoolSafeScanReply($status,$body) { http_response_code($status); echo json_encode($body,JSON_THROW_ON_ERROR); exit; }
$method=$_SERVER['REQUEST_METHOD'];
$operation=$_GET['operation']??'capabilities';
if(array_diff(array_keys($_GET),['operation']) || !in_array($operation,['capabilities','locations','scan','management','fees'],true)
    || (in_array($operation,['scan','management','fees'],true)?$method!=='POST':$method!=='GET')) SchoolSafeScanReply(400,['error'=>'INVALID_REQUEST']);
chdir(__DIR__); $_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level(); ob_start();
try {
 require_once __DIR__.'/Warehouse.php';
 if(!User('STAFF_ID')) {
   while(ob_get_level()>$level)ob_end_clean();
   SchoolSafeScanReply(401,['error'=>'NATIVE_SESSION_REQUIRED']);
 }
 $user=(int)User('STAFF_ID');$school=(int)UserSchool();$year=(int)UserSyear();
 if($user<1||$school<1||$year<1) throw new RuntimeException('INVALID_CONTEXT');
 if(empty($_SESSION['SchoolSafeScannerCsrf'])) $_SESSION['SchoolSafeScannerCsrf']=bin2hex(random_bytes(32));
 $input=new stdClass();
 if(in_array($operation,['scan','management','fees'],true)) {
   $csrf=$_SERVER['HTTP_X_SCHOOLSAFE_CSRF']??'';
   if(!is_string($csrf)||!hash_equals($_SESSION['SchoolSafeScannerCsrf'],$csrf)) throw new RuntimeException('CSRF_REQUIRED');
   if(stripos($_SERVER['CONTENT_TYPE']??'','application/json')!==0) throw new RuntimeException('INVALID_INPUT');
   $raw=file_get_contents('php://input',false,null,0,20001);
   if(strlen($raw)>20000) throw new RuntimeException('INVALID_INPUT');
   $input=json_decode($raw,false,16,JSON_THROW_ON_ERROR);
   if(!is_object($input)) throw new RuntimeException('INVALID_INPUT');
   if($operation==='fees' && (User('PROFILE')!=='admin'||!AllowEdit('Student_Billing/StudentFees.php')||!in_array($input->action??'',['list','save','activate','stop'],true))) throw new RuntimeException('NATIVE_STUDENT_NOT_ACCESSIBLE');
   if($operation==='management' && isset($input->nativeStudentId)) {
     if(($input->action??'')==='print-request'&&!AllowUse('Student_ID_Card/StudentIDCard.php')) throw new RuntimeException('NATIVE_STUDENT_NOT_ACCESSIBLE');
     $student=filter_var($input->nativeStudentId,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
     if($student===false) throw new RuntimeException('INVALID_INPUT');
     $sql="SELECT 1 FROM student_enrollment e WHERE e.STUDENT_ID=".(int)$student." AND e.SCHOOL_ID=".$school." AND e.SYEAR=".$year;
     if(User('PROFILE')==='parent') $sql.=" AND EXISTS(SELECT 1 FROM students_join_users u WHERE u.STUDENT_ID=e.STUDENT_ID AND u.STAFF_ID=".$user.")";
     if(!DBGet($sql)) throw new RuntimeException('NATIVE_STUDENT_NOT_ACCESSIBLE');
     $input->nativeStudentId=(string)$student;
   }
 }
 $config=json_decode(file_get_contents('/var/lib/schoolsafe-card-bridge/relay-config.json'),true,16,JSON_THROW_ON_ERROR);
 if(($config['environment']??'')!=='local-isolated-test-only'||($config['sourceInstance']??'')!=='codex-addons-local'
   ||!preg_match('/^[a-f0-9]{64}$/D',$config['secretHex']??'')) throw new RuntimeException('CONFIG_REQUIRED');
 $relay=function($operation,$input)use($config,$user,$school,$year){
 $now=time();
 $body=json_encode(['version'=>1,'audience'=>'schoolsafe-scan-bridge','sourceInstance'=>'codex-addons-local',
   'nativeUserId'=>(string)$user,'nativeSchoolId'=>(string)$school,'nativeYear'=>(string)$year,
   'operation'=>$operation,'input'=>$input,'issuedAt'=>$now,'expiresAt'=>$now+30,'nonce'=>bin2hex(random_bytes(32))],JSON_THROW_ON_ERROR);
 $curl=curl_init('http://codex-card-bridge-http:8080/bridge/scanner/'.$operation);
 $response='';
 curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,
   CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>15,CURLOPT_PROTOCOLS=>CURLPROTO_HTTP,
   CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
   CURLOPT_POSTFIELDS=>json_encode(['body'=>$body,'signature'=>hash_hmac('sha256',$body,hex2bin($config['secretHex']))],JSON_THROW_ON_ERROR),
   CURLOPT_WRITEFUNCTION=>function($handle,$chunk)use(&$response){$response.=$chunk;return strlen($response)<=300000?strlen($chunk):0;}]);
 try {
   $ok=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
   if($ok===false||!in_array($status,[200,400,401,403,409,503],true)) throw new RuntimeException('RELAY_UNAVAILABLE');
   $result=json_decode($response,true,32,JSON_THROW_ON_ERROR);
   return [$status,$result];
 } finally {curl_close($curl);}
 };
 if($operation==='scan'&&($input->mode??'')==='fees'){
   if(array_diff(array_keys((array)$input),['mode','qr_payload'])||!is_string($input->qr_payload??null))throw new RuntimeException('INVALID_INPUT');
   [$status,$result]=$relay('fees',(object)['action'=>'resolve','qr_payload'=>$input->qr_payload]);
   if($status===200&&isset($result['data']['nativeStudentId'])){
     $student=filter_var($result['data']['nativeStudentId'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
     if($student===false||!DBGet("SELECT 1 FROM student_enrollment WHERE STUDENT_ID=".$student." AND SCHOOL_ID=".$school." AND SYEAR=".$year))throw new RuntimeException('NATIVE_STUDENT_NOT_ACCESSIBLE');
     $where=" WHERE STUDENT_ID=".$student." AND SCHOOL_ID=".$school." AND SYEAR=".$year;
     $billing=DBGet("SELECT (SELECT COUNT(*) FROM billing_fees".$where.") AS FEE_COUNT,(SELECT COALESCE(SUM(AMOUNT),0) FROM billing_fees".$where.") AS FEE_TOTAL,(SELECT COALESCE(SUM(AMOUNT),0)::numeric(14,2) FROM billing_payments".$where.") AS PAID");
     if(!isset($billing[1]['FEE_COUNT'],$billing[1]['FEE_TOTAL'],$billing[1]['PAID']))throw new RuntimeException('NATIVE_BILLING_UNAVAILABLE');
     [$status,$result]=$relay('fees',(object)['action'=>'result','qr_payload'=>$input->qr_payload,'modelId'=>$result['data']['modelId'],'paid'=>(string)$billing[1]['PAID'],'hasData'=>(int)$billing[1]['FEE_COUNT']>0&&(float)$billing[1]['FEE_TOTAL']>0]);
   }
 }else{[$status,$result]=$relay($operation,$input);}
 if($status===200&&$operation==='capabilities')$result['csrfToken']=$_SESSION['SchoolSafeScannerCsrf'];
 while(ob_get_level()>$level)ob_end_clean();
 SchoolSafeScanReply($status,$result);
} catch(Throwable $error) {
 while(ob_get_level()>$level)ob_end_clean();
 $status=in_array($error->getMessage(),['CSRF_REQUIRED','NATIVE_STUDENT_NOT_ACCESSIBLE'],true)?403:($error->getMessage()==='INVALID_INPUT'?400:503);
 SchoolSafeScanReply($status,['error'=>'NATIVE_SCANNER_UNAVAILABLE']);
}
