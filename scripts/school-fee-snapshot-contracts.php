<?php
// Pure synthetic contracts: no database, account, HTTP transport or real child.
$root=getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT');
if(!$root)exit(2);
require_once $root.'/ProgramFunctions/SchoolSafeFeePolicy.fnc.php';
$passed=0;$failed=0;
function checkSnapshot($condition,$label){global $passed,$failed;if($condition){$passed++;echo "PASS $label\n";}else{$failed++;echo "FAIL $label\n";}}
$rows=[
 ['LEDGER_KIND'=>'fee','ID'=>1,'AMOUNT'=>'100.00','WAIVED_FEE_ID'=>null,'DUE_DATE'=>'2026-10-31'],
 ['LEDGER_KIND'=>'fee','ID'=>2,'AMOUNT'=>'-25.00','WAIVED_FEE_ID'=>1,'DUE_DATE'=>null],
 ['LEDGER_KIND'=>'payment','ID'=>3,'AMOUNT'=>'80.00','REFUNDED_PAYMENT_ID'=>null],
 ['LEDGER_KIND'=>'payment','ID'=>4,'AMOUNT'=>'-10.00','REFUNDED_PAYMENT_ID'=>3],
];
$ledger=SchoolSafeFeeLedgerRead(42,3,2026,fn($sql)=>$rows,'2026-10-23');
checkSnapshot(($ledger['billed_positive_cents']??null)===10000,'positive billed rows retained separately');
checkSnapshot(($ledger['waiver_adjustment_cents']??null)===-2500,'signed native exemption adjustments retained');
checkSnapshot(($ledger['payments_positive_cents']??null)===8000,'gross actual payments retained');
checkSnapshot(($ledger['refund_adjustment_cents']??null)===-1000,'signed actual refund retained');
checkSnapshot(($ledger['nominal_due_from']??null)==='2026-10-31','actual nominal deadline retained without allocation');
$file=$root.'/ProgramFunctions/SchoolSafeFeeSnapshot.fnc.php';
checkSnapshot(is_file($file),'native financial control snapshot owner exists');
if(is_file($file)){
 require_once $file;
 $model=['id'=>'11111111-1111-4111-8111-111111111111','label'=>'Annual synthetic control','threshold'=>'80','tolerance'=>10,'active'=>true];
 $ctx=['school'=>3,'year'=>2026,'user'=>9];
 $identity=['student_id'=>42,'student_name'=>'Synthetic Student','class_name'=>'Synthetic Class'];
 $financial=SchoolSafeFeeClassify($model,$ledger);
 $metadata=['school_name'=>'Synthetic School','currency'=>'SYN','currency_state'=>'known'];
 $snapshot=SchoolSafeFeeControlSnapshot($identity,$ctx,$ledger,$model,$financial,$metadata,'entry');
 checkSnapshot($snapshot['syear']===2026&&$snapshot['school']['id']===3&&$snapshot['student']['id']===42,'snapshot explicitly scoped to actual year school child');
 checkSnapshot($snapshot['policy']['minimum']==='80.00'&&$snapshot['policy']['effective_minimum']==='75.00'&&$snapshot['policy']['tolerance_floor']==='67.50','approved net cap and exact tolerance snapshot');
 checkSnapshot($snapshot['ledger']['net_billed']==='75.00'&&$snapshot['ledger']['paid_net']==='70.00'&&$snapshot['ledger']['balance']==='5.00','snapshot is signed financial observation not receipt');
 checkSnapshot($snapshot['financial']['color']==='yellow'&&$snapshot['operation']==='entry','actual decision and operation retained');
 checkSnapshot($snapshot['ledger']['currency']==='SYN'&&$snapshot['ledger']['deadline_basis']==='nominal_unallocated','real configured currency and honest deadline basis');
 $rows[0]['AMOUNT']='999.00';$model['threshold']='999';
 checkSnapshot($snapshot['policy']['minimum']==='80.00'&&$snapshot['ledger']['net_billed']==='75.00','snapshot is not a later live ledger reference');
 $empty=SchoolSafeFeeLedgerRead(42,3,2026,fn($sql)=>[],'2026-10-23');
 $unknown=SchoolSafeFeeControlSnapshot($identity,$ctx,$empty,$model,SchoolSafeFeeClassify($model,$empty),['school_name'=>'Synthetic School','currency'=>null,'currency_state'=>'missing'],'consult');
 checkSnapshot($unknown['ledger']['net_billed']===null&&$unknown['ledger']['paid_net']===null&&$unknown['ledger']['balance']===null,'no-data observation never manufactures zero monetary facts');
 checkSnapshot($unknown['ledger']['currency']===null&&$unknown['financial']['color']===null,'unknown currency and no-data color remain unknown');
 $creditLedger=SchoolSafeFeeLedgerRead(42,3,2026,fn($sql)=>[
  ['LEDGER_KIND'=>'fee','ID'=>1,'AMOUNT'=>'100.00'],['LEDGER_KIND'=>'payment','ID'=>2,'AMOUNT'=>'125.00']],'2026-10-23');
 $credit=SchoolSafeFeeControlSnapshot($identity,$ctx,$creditLedger,$model,SchoolSafeFeeClassify($model,$creditLedger),$metadata,'consult');
 checkSnapshot($credit['ledger']['credit']==='25.00'&&$credit['ledger']['amount_due']==='0.00'&&$credit['ledger']['balance']==='-25.00','actual overpayment retained as credit rather than fabricated debt');
 $small=$ledger;$small['fee_cents']=7501;$small['balance_cents']=501;
 $cent=SchoolSafeFeeControlSnapshot($identity,$ctx,$small,$model,SchoolSafeFeeClassify($model,$small),$metadata,'consult');
 checkSnapshot($cent['policy']['tolerance_floor']==='67.51','fractional-cent tolerance rounds only to first actually payable cent');
 $currencyRows=[1=>['SCHOOL_ID'=>3,'CONFIG_VALUE'=>'LOCAL'],2=>['SCHOOL_ID'=>0,'CONFIG_VALUE'=>'SYN']];
 $queries=[];$get=function($sql)use(&$queries,&$currencyRows){$queries[]=$sql;return strpos($sql,'FROM schools WHERE')!==false?[1=>['TITLE'=>'Synthetic school']]:$currencyRows;};
 checkSnapshot(SchoolSafeFeeControlMetadata($ctx,$get)['currency']==='LOCAL'&&strpos($queries[1],'SCHOOL_ID IN (0,3)')!==false,'currency uses native local then general precedence and excludes foreign school');
 $currencyRows=[1=>['SCHOOL_ID'=>0,'CONFIG_VALUE'=>'SYN']];
 checkSnapshot(SchoolSafeFeeControlMetadata($ctx,$get)['currency']==='SYN','declared general native currency fallback is allowed');
 $currencyRows=[1=>['SCHOOL_ID'=>3,'CONFIG_VALUE'=>'LOCAL'],2=>['SCHOOL_ID'=>3,'CONFIG_VALUE'=>'OTHER']];
 checkSnapshot(SchoolSafeFeeControlMetadata($ctx,$get)['currency']===null,'ambiguous school currency never silently picks a value');
 $currencyRows=false;$unavailable=SchoolSafeFeeControlMetadata($ctx,$get);
 checkSnapshot($unavailable['currency']===null&&$unavailable['currency_state']==='unavailable','failed currency read never invents a default');
 $currencyRows=[1=>['SCHOOL_ID'=>3,'CONFIG_VALUE'=>''],2=>['SCHOOL_ID'=>0,'CONFIG_VALUE'=>'SYN']];
 checkSnapshot(SchoolSafeFeeControlMetadata($ctx,$get)['currency']===null,'empty school override does not manufacture a general currency');
 $saved=$snapshot;$saved['operation']='consult';ksort($saved['financial']);
 $replayCtx=$ctx+['request_key'=>'33333333-3333-4333-8333-333333333333'];
 $replayDb=['quote'=>fn($value)=>"'".str_replace("'","''",$value)."'",'get'=>fn($sql)=>[1=>['ID'=>901,'STUDENT_ID'=>42,'CONTEXT_SNAPSHOT'=>json_encode($saved)]]];
 try{$replay=SchoolSafeFeeConsultReplay(42,$replayCtx,$replayDb);checkSnapshot($replay['id']===901&&$replay['ledger']['fee_cents']===7500,'JSONB key order does not invalidate the original financial operation');}
 catch(RuntimeException $error){checkSnapshot(false,'JSONB key order does not invalidate the original financial operation');$replay=null;}
 if($replay!==null){$restricted=SchoolSafeFeeConsultResult($identity,$ctx,$replay['ledger'],$replay['financial'],901,true);checkSnapshot($restricted['idempotent']===true&&!array_intersect(['fee_total','paid_total','balance'],array_keys($restricted)),'replayed financial response rechecks current detail rights');}
 try{SchoolSafeFeeConsultReplay(43,$replayCtx,$replayDb);checkSnapshot(false,'same operation key cannot reveal another child');}catch(RuntimeException $error){checkSnapshot($error->getMessage()==='OPERATION_KEY_CONFLICT','same operation key cannot reveal another child');}
 foreach([null,true,'not-uuid'] as $badKey){try{SchoolSafeFeeRequestKey(['request_key'=>$badKey]);checkSnapshot(false,'invalid request key refused');}catch(RuntimeException $error){checkSnapshot($error->getMessage()==='INVALID_INPUT','invalid request key refused');}}
 try{SchoolSafeFeeControlSnapshot($identity,$ctx,$ledger,$model,$financial,$metadata,'payment');checkSnapshot(false,'payment operation refused');}catch(RuntimeException $error){checkSnapshot($error->getMessage()==='INVALID_CONTEXT','control cannot masquerade as a payment');}
}
echo "SNAPSHOT passed=$passed failed=$failed\n";exit($failed?1:0);
