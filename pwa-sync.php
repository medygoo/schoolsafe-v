<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
$level=ob_get_level();ob_start();$transaction=false;
function SchoolSafeSyncReply(int $status,array $data): void {
 global $level;while(ob_get_level()>$level)ob_end_clean();
 http_response_code($status);echo json_encode($data,JSON_THROW_ON_ERROR);exit;
}
try{
 $method=$_SERVER['REQUEST_METHOD']??'';
 if(!in_array($method,['GET','POST'],true)||$_GET||$_POST)SchoolSafeSyncReply(400,['error'=>'INVALID_REQUEST']);
 chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];require_once 'Warehouse.php';
 if(!User('STAFF_ID'))SchoolSafeSyncReply(401,['error'=>'SESSION_REQUIRED']);
 require_once 'modules/SchoolSafe/Gateway.php';
 if(User('PROFILE')!=='parent'||SchoolSafeEndpointGateway('parent-notifications.php','GET','',[])==='deny')SchoolSafeSyncReply(403,['error'=>'ACCESS_DENIED']);
 require_once 'ProgramFunctions/SchoolSafeReceipts.fnc.php';
 $user=(int)User('STAFF_ID');$school=(int)UserSchool();$year=(int)UserSyear();
 if($user<1||$school<1||$year<1||$DatabaseType!=='postgresql')SchoolSafeSyncReply(403,['error'=>'ACCESS_DENIED']);
 $actor=hash('sha256',"parent:$user:$school:$year");
 $_SESSION['schoolsafe_pwa_csrf']=$_SESSION['schoolsafe_pwa_csrf']??bin2hex(random_bytes(32));
 if($method==='GET'){
  $rows=DBGet("SELECT r.* FROM schoolsafe_pwa_receipt_reads rr JOIN schoolsafe_payment_receipts r ON r.id=rr.receipt_id
   WHERE rr.staff_id=$user AND rr.school_id=$school AND rr.syear=$year ORDER BY rr.read_at DESC LIMIT 1000");
  $read=[];foreach($rows as $row){if(SchoolSafeReceiptPermission($row))$read[]=(int)$row['ID'];}
  SchoolSafeSyncReply(200,['actor'=>$actor,'csrf'=>$_SESSION['schoolsafe_pwa_csrf'],'receiptReads'=>$read]);
 }
 $csrf=$_SERVER['HTTP_X_SCHOOLSAFE_CSRF']??'';
 if(!is_string($csrf)||!hash_equals($_SESSION['schoolsafe_pwa_csrf'],$csrf))SchoolSafeSyncReply(403,['error'=>'CSRF_REQUIRED']);
 if(strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json')SchoolSafeSyncReply(400,['error'=>'INVALID_CONTENT_TYPE']);
 $raw=file_get_contents('php://input',false,null,0,4097);
 if($raw===false||strlen($raw)>4096)SchoolSafeSyncReply(400,['error'=>'INVALID_BODY']);
 $input=json_decode($raw,true,8,JSON_THROW_ON_ERROR);
 if(!is_array($input)||count($input)!==4||array_diff(array_keys($input),['actor','id','kind','receiptId'])
  ||!is_string($input['actor']??null)||!hash_equals($actor,$input['actor'])
  ||!is_string($input['id']??null)||!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD',$input['id'])
  ||($input['kind']??'')!=='receipt.read'||!is_int($input['receiptId']??null)||$input['receiptId']<1)SchoolSafeSyncReply(400,['error'=>'INVALID_OPERATION']);
 $receipt=DBGet("SELECT * FROM schoolsafe_payment_receipts WHERE id=".$input['receiptId']);
 $receipt=$receipt[1]??null;
 if(!$receipt||!SchoolSafeReceiptPermission($receipt))SchoolSafeSyncReply(403,['error'=>'RECEIPT_DENIED']);
 $hash=hash('sha256','receipt.read:'.$input['receiptId']);
 $result=['confirmed'=>true,'id'=>$input['id'],'receiptId'=>$input['receiptId']];
 if(!pg_query($db_connection,'BEGIN'))throw new RuntimeException('DB');$transaction=true;
 $insert=pg_query_params($db_connection,'INSERT INTO schoolsafe_pwa_operations(staff_id,school_id,syear,operation_id,payload_hash,result)
 VALUES($1,$2,$3,$4,$5,$6) ON CONFLICT DO NOTHING',[$user,$school,$year,$input['id'],$hash,json_encode($result,JSON_THROW_ON_ERROR)]);
 if(!$insert)throw new RuntimeException('DB');
 $stored=pg_query_params($db_connection,'SELECT payload_hash,result FROM schoolsafe_pwa_operations
 WHERE staff_id=$1 AND school_id=$2 AND syear=$3 AND operation_id=$4 FOR UPDATE',[$user,$school,$year,$input['id']]);
 if(!$stored||!($row=pg_fetch_assoc($stored)))throw new RuntimeException('DB');
 if(!hash_equals($row['payload_hash'],$hash)){
  pg_query($db_connection,'ROLLBACK');$transaction=false;SchoolSafeSyncReply(409,['error'=>'OPERATION_CONFLICT']);
 }
 $save=pg_query_params($db_connection,'INSERT INTO schoolsafe_pwa_receipt_reads(staff_id,school_id,syear,receipt_id)
 VALUES($1,$2,$3,$4) ON CONFLICT DO NOTHING',[$user,$school,$year,$input['receiptId']]);
 if(!$save||!pg_query($db_connection,'COMMIT'))throw new RuntimeException('DB');$transaction=false;
 SchoolSafeSyncReply(200,json_decode($row['result'],true,8,JSON_THROW_ON_ERROR));
}catch(Throwable $error){
 if($transaction)pg_query($db_connection,'ROLLBACK');
 SchoolSafeSyncReply($error instanceof JsonException?400:503,['error'=>'SYNC_UNAVAILABLE']);
}
