<?php
// Pure synthetic regression against the native SIGNED ledger contract. No DB/send/login.
if (PHP_SAPI!=='cli') exit(2);
$root=getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT')?:dirname(__DIR__).'/app/modules/codex-ui-v1/extensions-test-runtime';
require_once $root.'/ProgramFunctions/SchoolSafeFeeScan.fnc.php';
$secret=str_repeat('0',64);
$signature=substr(strtr(base64_encode(hash_hmac('sha256','QA-LEDGER',hex2bin($secret),true)),'+/','-_'),0,32);
$qr='schoolsafe://card/QA-LEDGER/'.$signature;
$fees=[];$payments=[];$failedRead=false;$writes=[];$consults=[];$pass=0;$fail=0;
$db=['get'=>function($sql) use (&$fees,&$payments,&$failedRead,&$consults) {
    if (strpos($sql,'to_regclass')!==false)return [1=>['T'=>'public.schoolsafe_student_cards']];
    if (strpos($sql,'FROM schoolsafe_student_cards')!==false)return [1=>['STUDENT_ID'=>11,'SIGNATURE'=>$GLOBALS['signature'],'STATUS'=>'active','ENROLLED'=>'t','FIRST_NAME'=>'Synthetic','LAST_NAME'=>'Fixture','CLASS_NAME'=>'Synthetic class']];
    if (strpos($sql,'FROM students WHERE')!==false)return [1=>['STUDENT_ID'=>11]];
    if (strpos($sql,'FROM student_enrollment WHERE')!==false)return [1=>['OK'=>1]];
    if (strpos($sql,'FROM program_config')!==false)return [];
    if (strpos($sql,'FROM schools WHERE')!==false)return [1=>['ID'=>1,'TITLE'=>'Synthetic school']];
    if (strpos($sql,'FROM config WHERE')!==false)return [1=>['SCHOOL_ID'=>0,'CONFIG_VALUE'=>'SYN']];
    if (strpos($sql,'LEDGER_KIND')!==false){
        if($failedRead)return false;
        $rows=[];
        foreach($fees as $row){$row['LEDGER_KIND']='fee';$rows[count($rows)+1]=$row;}
        foreach($payments as $row){$row['LEDGER_KIND']='payment';$rows[count($rows)+1]=$row;}
        return $rows;
    }
    if (strpos($sql,'FROM billing_fees')!==false) {
        if($failedRead)return false;
        $waivers=strpos($sql,'WAIVED_FEE_ID IS NOT NULL')!==false;$rows=[];
        foreach($fees as $fee)if($waivers===isset($fee['WAIVED_FEE_ID']))$rows[count($rows)+1]=$fee;
        return $rows;
    }
    if (strpos($sql,'FROM billing_payments')!==false)return $payments;
    if (strpos($sql,'INSERT INTO schoolsafe_fee_consults')!==false){$consults[]=$sql;return [1=>['ID'=>1]];}
    throw new RuntimeException('UNEXPECTED_SQL');
},'exec'=>function($sql) use (&$writes){$writes[]=$sql;},'quote'=>fn($value)=>"'".str_replace("'","''",$value)."'"];
function ledgerCheck($name,$actual,$expected){global $pass,$fail;if($actual===$expected){$pass++;echo "PASS $name\n";}else{$fail++;echo "FAIL $name\n";}}
function ledgerScan(){global $qr,$secret,$db;return SchoolSafeFeeScan($qr,$secret,['user'=>9,'school'=>1,'year'=>2026,'detail'=>true],$db,'2026-10-23');}
foreach ([['100.00','-25.00','75.00'],['150.00','-100.00','50.00'],['100.00','-100.00','0.00'],['100.00','25.00','125.00']] as [$charge,$waiver,$net]) {
    $fees=[['ID'=>1,'AMOUNT'=>$charge],['ID'=>2,'AMOUNT'=>$waiver,'WAIVED_FEE_ID'=>1]];
    $payments=[];$result=ledgerScan();ledgerCheck('signed fee lines '.$charge.' '.$waiver,$result['fee_total'],$net);
}
$fees=[['ID'=>1,'AMOUNT'=>'100.00']];$payments=[1=>['ID'=>1,'AMOUNT'=>'100.00'],2=>['ID'=>2,'AMOUNT'=>'-25.00','REFUNDED_PAYMENT_ID'=>1]];
$result=ledgerScan();ledgerCheck('partial refund retains unrefunded payment amount',$result['paid_total'],'75.00');
ledgerCheck('partial refund leaves actual balance',$result['balance'],'25.00');
$fees=[['ID'=>1,'AMOUNT'=>'900000000000.01'],['ID'=>2,'AMOUNT'=>'0.02']];$payments=[];
ledgerCheck('native numeric amounts preserve exact cents',ledgerScan()['fee_total'],'900000000000.03');
$fees=[['ID'=>1,'AMOUNT'=>'100.00']];$payments=[1=>['ID'=>1,'AMOUNT'=>'125.00']];
$result=ledgerScan();ledgerCheck('overpayment preserves actual credit balance',$result['balance'],'-25.00');
ledgerCheck('overpayment is not an outstanding amount',$result['balance_status'],'paid');
$payments=[];$fees=[['ID'=>1,'AMOUNT'=>'not-money']];$before=count($consults);$error='NO_REFUSAL';
try{ledgerScan();}catch(RuntimeException $caught){$error=$caught->getMessage();}
ledgerCheck('invalid native money fails closed',$error,'FEE_READ_UNAVAILABLE');
ledgerCheck('invalid native money never logs a fictitious zero consultation',count($consults),$before);
$fees=[['ID'=>1,'AMOUNT'=>'100.00']];$payments=[1=>['ID'=>1,'AMOUNT'=>null]];$error='NO_REFUSAL';
try{ledgerScan();}catch(RuntimeException $caught){$error=$caught->getMessage();}
ledgerCheck('invalid native payment also fails closed',$error,'FEE_READ_UNAVAILABLE');
$failedRead=true;$before=count($consults);$error='NO_REFUSAL';
try{ledgerScan();}catch(RuntimeException $caught){$error=$caught->getMessage();}
ledgerCheck('failed native financial read is not no_data',$error,'FEE_READ_UNAVAILABLE');
ledgerCheck('failed read never logs a fabricated consultation',count($consults),$before);
echo json_encode(['pass'=>$pass,'fail'=>$fail,'scope'=>'Substituted rows; not PostgreSQL, policy or entry barrier'])."\n";exit($fail?1:0);
