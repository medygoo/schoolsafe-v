<?php
if(PHP_SAPI!=='cli')exit(2);
$root=getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT');if(!$root)exit(2);
require $root.'/ProgramFunctions/SchoolSafeCanteenScan.fnc.php';
function Currency($amount){return $amount.' USD';} // Catch accidental legacy default denomination.
function checkCanteen($name,$ok){if(!$ok)throw new RuntimeException($name);echo 'PASS '.$name."\n";}
$row=[['ACCOUNT_ID'=>'7','STATUS'=>'Active','BALANCE'=>'12.50']];
$guard=SchoolSafeCanteenAccountResult($row,'Synthetic student',false,'SYN');
checkCanteen('canteen guard receives no monetary field or numeric label',!isset($guard['balance'])&&!str_contains($guard['label'],'12.50'));
$detail=SchoolSafeCanteenAccountResult($row,'Synthetic student',true,'SYN');
checkCanteen('authorized canteen detail preserves real balance and actual configured denomination',($detail['balance']??null)==='12.50'&&($detail['currency']??null)==='SYN'&&str_contains($detail['label'],'12.50 SYN'));
checkCanteen('canteen verification is explicitly read-only with no meal debit',($detail['read_only']??null)===true&&str_contains($detail['label'],'aucun repas'));
$unknown=SchoolSafeCanteenAccountResult($row,'Synthetic student',true,null);
checkCanteen('missing denomination is unavailable not invented USD or usable credit',($unknown['status']??null)==='currency_unavailable'&&!isset($unknown['balance'])&&!str_contains($unknown['label'],'USD'));
$empty=SchoolSafeCanteenAccountResult([],'Synthetic student',true,'SYN');
checkCanteen('no account is never zero balance',($empty['status']??null)==='no_account'&&!isset($empty['balance']));
$missing=SchoolSafeCanteenAccountResult([['ACCOUNT_ID'=>'7','STATUS'=>'Active','BALANCE'=>null]],'Synthetic student',true,'SYN');
checkCanteen('unknown balance is not zero',($missing['status']??null)==='no_data'&&!isset($missing['balance']));
foreach(['0.00'=>'zero_balance','-0.01'=>'negative_balance','0.01'=>'credit_available'] as$amount=>$status){
 $value=SchoolSafeCanteenAccountResult([['ACCOUNT_ID'=>'7','STATUS'=>null,'BALANCE'=>$amount]],'Synthetic student',false,'SYN');
 checkCanteen('native nullable-active status and signed exact cents '.$amount,($value['status']??null)===$status&&!isset($value['balance']));
}
$inactive=SchoolSafeCanteenAccountResult([['ACCOUNT_ID'=>'7','STATUS'=>'Inactive','BALANCE'=>'12.50']],'Synthetic student',true,'SYN');
checkCanteen('inactive account never advertises available credit',($inactive['status']??null)==='inactive'&&!isset($inactive['balance']));
try{SchoolSafeCanteenAccountResult([$row[0],$row[0]],'Synthetic student',true,'SYN');checkCanteen('duplicate account must fail closed',false);}catch(RuntimeException $e){checkCanteen('duplicate account fails closed',$e->getMessage()==='NATIVE_CANTEEN_UNAVAILABLE');}
checkCanteen('canteen command has native signed-card execution without isolated bridge',function_exists('SchoolSafeCanteenScan'));
