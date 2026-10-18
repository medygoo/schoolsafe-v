<?php
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
$level=ob_get_level();ob_start();
try{
 if(($_SERVER['REQUEST_METHOD']??'')!=='GET'||count($_GET)!==1||!isset($_GET['draft_id'])||$_POST)throw new RuntimeException('INVALID_REQUEST',400);
 $id=$_GET['draft_id'];chdir(__DIR__);$_REQUEST=['modname'=>'Student_Billing/StudentPayments.php','modfunc'=>''];require_once 'Warehouse.php';
 require_once 'ProgramFunctions/SchoolSafePaymentIntent.fnc.php';$id=SchoolSafePaymentDraftId($id);
 if(!User('STAFF_ID'))throw new RuntimeException('SESSION_REQUIRED',401);
 $intent=pg_fetch_assoc(SchoolSafePaymentIntentQuery('SELECT * FROM schoolsafe_pwa_payment_intents WHERE staff_id=$1 AND school_id=$2 AND syear=$3 AND draft_id=$4',[(int)User('STAFF_ID'),(int)UserSchool(),(int)UserSyear(),$id]));
 if(!$intent){SchoolSafePaymentIntentScope((int)UserStudentID());$data=['state'=>'not_prepared'];}
 else{SchoolSafePaymentIntentScope((int)$intent['student_id']);$data=SchoolSafePaymentIntentStatus($intent);}
 $status=200;
}catch(Throwable $error){$status=in_array($error->getCode(),[400,401,403,409],true)?$error->getCode():503;$data=['error'=>'RESULT_UNAVAILABLE'];}
while(ob_get_level()>$level)ob_end_clean();http_response_code($status);echo json_encode($data,JSON_THROW_ON_ERROR);
