<?php
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
$level=ob_get_level();ob_start();
function SchoolSafePaymentContextReply(int $status,array $data): void{
 global $level;while(ob_get_level()>$level)ob_end_clean();http_response_code($status);echo json_encode($data,JSON_THROW_ON_ERROR);exit;
}
try{
 $method=$_SERVER['REQUEST_METHOD']??'';
 if(!in_array($method,['GET','POST'],true)||$_GET||$_POST)SchoolSafePaymentContextReply(400,['error'=>'INVALID_REQUEST']);
 chdir(__DIR__);$_REQUEST=['modname'=>'Student_Billing/StudentPayments.php','modfunc'=>''];require_once 'Warehouse.php';
 if(!User('STAFF_ID'))SchoolSafePaymentContextReply(401,['error'=>'SESSION_REQUIRED']);
 if(User('PROFILE')!=='admin'||!AllowUse('Student_Billing/StudentPayments.php')||!AllowEdit('Student_Billing/StudentPayments.php'))SchoolSafePaymentContextReply(403,['error'=>'PAYMENT_EDIT_REQUIRED']);
 $student=(int)UserStudentID();$school=(int)UserSchool();$year=(int)UserSyear();$user=(int)User('STAFF_ID');
 $scope=['WHERE'=>' AND s.STUDENT_ID='.$student];
 if($student<1||$school<1||$year<1||!GetStuList($scope))SchoolSafePaymentContextReply(403,['error'=>'STUDENT_SCOPE_REQUIRED']);
 $enrolled=DBGetOne("SELECT 1 FROM student_enrollment WHERE student_id=$student AND school_id=$school AND syear=$year LIMIT 1");
 if(!$enrolled)SchoolSafePaymentContextReply(403,['error'=>'STUDENT_SCOPE_REQUIRED']);
 $actor=hash('sha256',"payment:$user:$school:$year");
 $_SESSION['schoolsafe_payment_draft_csrf']=$_SESSION['schoolsafe_payment_draft_csrf']??bin2hex(random_bytes(32));
 if($method==='POST'){
  $csrf=$_SERVER['HTTP_X_SCHOOLSAFE_CSRF']??'';
  if(!is_string($csrf)||!hash_equals($_SESSION['schoolsafe_payment_draft_csrf'],$csrf))SchoolSafePaymentContextReply(403,['error'=>'CSRF_REQUIRED']);
  if(strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json')SchoolSafePaymentContextReply(400,['error'=>'INVALID_REQUEST']);
  $raw=file_get_contents('php://input',false,null,0,4097);
  if($raw===false||strlen($raw)>4096)SchoolSafePaymentContextReply(400,['error'=>'INVALID_REQUEST']);
  $value=json_decode($raw,true,8,JSON_THROW_ON_ERROR);
  if(!is_array($value)||count($value)!==7||array_diff(array_keys($value),['actor','studentId','amountMinor','date','comment','currency','draftId'])
    ||($value['actor']??null)!==$actor||($value['studentId']??null)!==$student||($value['currency']??null)!==(string)Config('CURRENCY')
    ||!is_int($value['amountMinor']??null)||$value['amountMinor']<1||$value['amountMinor']>999999999
    ||!is_string($value['date']??null)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value['date'])
    ||!is_string($value['comment']??null)||mb_strlen($value['comment'])>250||trim($value['comment'])===''||preg_match('/[\x00-\x1f\x7f]/',$value['comment']))SchoolSafePaymentContextReply(400,['error'=>'INVALID_DRAFT']);
  $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value['date'],new DateTimeZone('Africa/Kinshasa'));
  if(!$date||$date->format('Y-m-d')!==$value['date']||$date->format('Y')<'1900'||$date>new DateTimeImmutable('today',new DateTimeZone('Africa/Kinshasa')))SchoolSafePaymentContextReply(400,['error'=>'INVALID_DATE']);
  require_once 'ProgramFunctions/SchoolSafePaymentIntent.fnc.php';
  $draftId=SchoolSafePaymentDraftId($value['draftId']??null);
  $draftHash=hash('sha256',json_encode([$value['studentId'],$value['amountMinor'],$value['date'],$value['comment'],$value['currency']],JSON_THROW_ON_ERROR));
  SchoolSafePaymentIntentQuery('INSERT INTO schoolsafe_pwa_payment_intents(staff_id,school_id,syear,draft_id,student_id,currency,draft_hash) VALUES($1,$2,$3,$4,$5,$6,$7) ON CONFLICT DO NOTHING',[$user,$school,$year,$draftId,$student,$value['currency'],$draftHash]);
  $intent=pg_fetch_assoc(SchoolSafePaymentIntentQuery('SELECT * FROM schoolsafe_pwa_payment_intents WHERE staff_id=$1 AND school_id=$2 AND syear=$3 AND draft_id=$4',[$user,$school,$year,$draftId]));
  if(!$intent||!hash_equals($intent['draft_hash'],$draftHash))SchoolSafePaymentContextReply(409,['error'=>'DRAFT_CONFLICT']);
  if($intent['payment_id'])SchoolSafePaymentContextReply(200,SchoolSafePaymentIntentStatus($intent));
  $_SESSION['schoolsafe_payment_draft']=['staff'=>$user,'school'=>$school,'year'=>$year,'student'=>$student,
   'draft_id'=>$draftId,
   'amount'=>intdiv($value['amountMinor'],100).'.'.str_pad((string)($value['amountMinor']%100),2,'0',STR_PAD_LEFT),
   'date'=>$value['date'],'comment'=>$value['comment'],'expires'=>time()+600];
  SchoolSafePaymentContextReply(200,['prepared'=>true,'nativeUrl'=>'/Modules.php?modname=Student_Billing/StudentPayments.php&student_id='.$student]);
 }
 SchoolSafePaymentContextReply(200,['actor'=>$actor,'studentId'=>$student,'csrf'=>$_SESSION['schoolsafe_payment_draft_csrf'],
  'currency'=>(string)Config('CURRENCY'),'date'=>(new DateTimeImmutable('now',new DateTimeZone('Africa/Kinshasa')))->format('Y-m-d'),
  'nativeUrl'=>'/Modules.php?modname=Student_Billing/StudentPayments.php&student_id='.$student]);
}catch(Throwable $error){error_log('SchoolSafe payment context: '.get_class($error).' at '.basename($error->getFile()).':'.$error->getLine());SchoolSafePaymentContextReply($error instanceof JsonException?400:(in_array($error->getCode(),[400,403,409],true)?$error->getCode():503),['error'=>'CONTEXT_UNAVAILABLE']);}
