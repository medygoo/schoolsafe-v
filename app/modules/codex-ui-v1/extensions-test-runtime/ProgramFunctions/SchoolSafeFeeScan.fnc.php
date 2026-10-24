<?php
// Native signed billing reader. Never creates a payment, attendance or passage.
// numeric(14,2) is calculated in exact cents. A failed read is not a zero balance.
function SchoolSafeFeeScan($qr,$secretHex,array $ctx,array $db,$day=null)
{
    require_once __DIR__.'/SchoolSafeCardVerify.fnc.php';
    require_once __DIR__.'/SchoolSafeFeeSnapshot.fnc.php';
    require_once __DIR__.'/SchoolSafePickupManage.fnc.php';
    SchoolSafeFeeRequestKey($ctx);
    $school=(int)($ctx['school']??0);$year=(int)($ctx['year']??0);$user=(int)($ctx['user']??0);
    if($school<1||$year<1||$user<1)throw new RuntimeException('INVALID_CONTEXT');
    $day=$day??date('Y-m-d');
    if(!is_string($day)||preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$day)!==1)throw new RuntimeException('INVALID_CONTEXT');
    $cardQuery=function($number,$scope)use($db,$year){return SchoolSafeCardVerifyQuery($number,$scope,$year,$db['get']);};
    $verify=SchoolSafeCardVerifyQr($qr,$secretHex,$school,$year,$cardQuery);
    if($verify===null||($verify['result']??'')!=='VALID')return $verify;
    return SchoolSafePickupStudentTransaction((int)$verify['student_id'],$ctx,$db,function()use($verify,$ctx,$db,$school,$year,$day){
        $replay=SchoolSafeFeeConsultReplay((int)$verify['student_id'],$ctx,$db);
        if($replay!==null)return SchoolSafeFeeConsultResult($verify,$ctx,$replay['ledger'],$replay['financial'],$replay['id'],true);
        $model=SchoolSafeFeePolicyActive($ctx,$db,true);
        $ledger=SchoolSafeFeeLedgerRead((int)$verify['student_id'],$school,$year,$db['get'],$day);
        $financial=$model===null?['state'=>'inactive','color'=>null,'reason'=>'no_active_policy']:SchoolSafeFeeClassify($model,$ledger);
        $snapshot=SchoolSafeFeeControlSnapshot($verify,$ctx,$ledger,$model,$financial,SchoolSafeFeeControlMetadata($ctx,$db['get']),'consult');
        $id=SchoolSafeFeeConsultRecord((int)$verify['student_id'],$ctx,$ledger,$db,$snapshot);
        return SchoolSafeFeeConsultResult($verify,$ctx,$ledger,$financial,$id);
    });
}

function SchoolSafeFeeConsultResult(array $verify,array $ctx,array $ledger,array $financial,int $id,bool $replay=false): array
{
    $result=['result'=>'FEE_STATUS','decision'=>'allowed','balance_status'=>$ledger['status'],'consult_id'=>$id,'financial'=>$financial]+$verify;
    if($replay)$result['idempotent']=true;
    // Consultation success is not permission to enter. Unknown data exposes no invented amounts.
    if(($ctx['detail']??false)===true&&$ledger['has_data']){
        $result['fee_total']=SchoolSafeFeeFormatCents($ledger['fee_cents']);
        $result['paid_total']=SchoolSafeFeeFormatCents($ledger['paid_cents']);
        $result['balance']=SchoolSafeFeeFormatCents($ledger['balance_cents']);
        $result['fee_count']=$ledger['fee_count'];$result['overdue_count']=$ledger['overdue_count'];
        $result['overdue_basis']='nominal_unallocated';
    }
    return $result;
}

// One native SQL statement supplies fees AND payments from the same snapshot.
// Reused inside the arrival transaction, with no nested BEGIN or audit write.
function SchoolSafeFeeLedgerRead(int $student,int $school,int $year,callable $get,string $day): array
{
    if($student<1||$school<1||$year<1||preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$day)!==1)throw new RuntimeException('INVALID_CONTEXT');
    $where='STUDENT_ID='.$student.' AND SCHOOL_ID='.$school.' AND SYEAR='.$year;
    $rows=$get("SELECT 'fee' AS LEDGER_KIND,ID,AMOUNT,WAIVED_FEE_ID,NULL AS REFUNDED_PAYMENT_ID,DUE_DATE FROM billing_fees WHERE ".$where
        ." UNION ALL SELECT 'payment' AS LEDGER_KIND,ID,AMOUNT,NULL AS WAIVED_FEE_ID,REFUNDED_PAYMENT_ID,NULL AS DUE_DATE FROM billing_payments WHERE ".$where);
    if(!is_array($rows))throw new RuntimeException('FEE_READ_UNAVAILABLE');
    $total=0;$waiverTotal=0;$paid=0;$fees=[];$adjustments=[];
    $billedPositive=0;$paymentsPositive=0;$refundAdjustments=0;$feeAdjustments=0;$paymentAdjustments=0;
    foreach($rows as $row){
        if(!is_array($row)||!isset($row['ID'],$row['AMOUNT'],$row['LEDGER_KIND']))throw new RuntimeException('FEE_READ_UNAVAILABLE');
        $amount=SchoolSafeFeeCents($row['AMOUNT']);
        if($row['LEDGER_KIND']==='payment'){
            $paid=SchoolSafeFeeAddCents($paid,$amount);
            if(isset($row['REFUNDED_PAYMENT_ID']))$refundAdjustments=SchoolSafeFeeAddCents($refundAdjustments,$amount);
            elseif($amount>0)$paymentsPositive=SchoolSafeFeeAddCents($paymentsPositive,$amount);
            else $paymentAdjustments=SchoolSafeFeeAddCents($paymentAdjustments,$amount);
            continue;
        }
        if($row['LEDGER_KIND']!=='fee')throw new RuntimeException('FEE_READ_UNAVAILABLE');
        $total=SchoolSafeFeeAddCents($total,$amount);
        if(isset($row['WAIVED_FEE_ID'])){
            $id=(int)$row['WAIVED_FEE_ID'];$waiverTotal=SchoolSafeFeeAddCents($waiverTotal,$amount);
            $adjustments[$id]=SchoolSafeFeeAddCents($adjustments[$id]??0,$amount);
        }else {
            $fees[(int)$row['ID']]=['cents'=>$amount,'due'=>$row['DUE_DATE']??null];
            if($amount>0)$billedPositive=SchoolSafeFeeAddCents($billedPositive,$amount);
            else $feeAdjustments=SchoolSafeFeeAddCents($feeAdjustments,$amount);
        }
    }
    if(($paid>0&&$total<PHP_INT_MIN+$paid)||($paid<0&&$total>PHP_INT_MAX+$paid))throw new RuntimeException('FEE_READ_UNAVAILABLE');
    $balance=$total-$paid;$status='due';
    if(!$rows)$status='no_data';
    elseif($total<=0&&$paid<=0&&$waiverTotal<0)$status='exempt';
    elseif($balance<=0)$status='paid';
    elseif($paid>0)$status='partial';
    $overdue=0;$dueFrom=null;$dueTo=null;
    foreach($fees as $id=>$fee){
        if($fee['due']===null||$fee['due']==='')continue;
        if(!is_string($fee['due'])||preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$fee['due'])!==1)throw new RuntimeException('FEE_READ_UNAVAILABLE');
        // Nominal deadlines only: Rosario payments have no per-fee allocation.
        if(SchoolSafeFeeAddCents($fee['cents'],$adjustments[$id]??0)<=0)continue;
        $dueFrom=$dueFrom===null||$fee['due']<$dueFrom?$fee['due']:$dueFrom;
        $dueTo=$dueTo===null||$fee['due']>$dueTo?$fee['due']:$dueTo;
        if($balance>0&&$fee['due']<$day)$overdue++;
    }
    return ['fee_cents'=>$total,'paid_cents'=>$paid,'balance_cents'=>$balance,'status'=>$status,
        'has_data'=>(bool)$rows,'fee_count'=>count($fees),'overdue_count'=>$overdue,
        'billed_positive_cents'=>$billedPositive,'waiver_adjustment_cents'=>$waiverTotal,
        'payments_positive_cents'=>$paymentsPositive,'refund_adjustment_cents'=>$refundAdjustments,
        'fee_unlinked_adjustment_cents'=>$feeAdjustments,'payment_unlinked_adjustment_cents'=>$paymentAdjustments,
        'nominal_due_from'=>$dueFrom,'nominal_due_to'=>$dueTo];
}

// Caller owns the transaction; a failure must roll back the enclosing passage too.
function SchoolSafeFeeConsultRecord(int $student,array $ctx,array $ledger,array $db,array $snapshot): int
{
    $quote=$db['quote']??(function_exists('DBEscapeString')?function($value){return "'".DBEscapeString($value)."'";}:null);
    if(!is_callable($quote))throw new RuntimeException('FEE_CONTEXT_UNAVAILABLE');
    $json=json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    if(strlen($json)>8000)throw new RuntimeException('FEE_CONTEXT_UNAVAILABLE');
    $key=SchoolSafeFeeRequestKey($ctx);
    $row=$db['get']('INSERT INTO schoolsafe_fee_consults(school_id,student_id,consulted_by,balance_status,fee_total,paid_total,balance,syear,operation,request_key,context_snapshot) VALUES('
        .(int)$ctx['school'].','.$student.','.(int)$ctx['user'].",'".$ledger['status']."',".SchoolSafeFeeFormatCents($ledger['fee_cents']).','
        .SchoolSafeFeeFormatCents($ledger['paid_cents']).','.SchoolSafeFeeFormatCents($ledger['balance_cents']).','.(int)$ctx['year'].','.$quote($snapshot['operation']).','.($key===null?'NULL':$quote($key)).','
        .'('.$quote($json)."::jsonb || jsonb_build_object('recorded_at',clock_timestamp()))) RETURNING ID");
    if(!is_array($row)||!isset($row[1]['ID']))throw new RuntimeException('FEE_WRITE_FAILED');
    $id=(int)$row[1]['ID'];
    if(array_key_exists('event',$db)){
        if(!is_callable($db['event']))throw new RuntimeException('NOTIFY_UNAVAILABLE');
        $db['event']('financial.control',$id);
    }
    return $id;
}

function SchoolSafeFeeCents($value): int
{
    if((!is_string($value)&&!is_int($value))||preg_match('/^(-?)([0-9]{1,12})(?:\.([0-9]{1,2}))?$/D',(string)$value,$parts)!==1)throw new RuntimeException('FEE_READ_UNAVAILABLE');
    $cents=(int)$parts[2]*100+(int)str_pad($parts[3]??'',2,'0',STR_PAD_RIGHT);
    return $parts[1]==='-'?-$cents:$cents;
}

function SchoolSafeFeeAddCents(int $total,int $amount): int
{
    if(($amount>0&&$total>PHP_INT_MAX-$amount)||($amount<0&&$total<PHP_INT_MIN-$amount))throw new RuntimeException('FEE_READ_UNAVAILABLE');
    return $total+$amount;
}

function SchoolSafeFeeFormatCents(int $cents): string
{
    return ($cents<0?'-':'').abs(intdiv($cents,100)).'.'.str_pad((string)abs($cents%100),2,'0',STR_PAD_LEFT);
}

function SchoolSafeFeeStatusLabel($status)
{
    return ['paid'=>'À jour','partial'=>'Partiellement payé','due'=>'Paiement exigible','exempt'=>'Exempté','no_data'=>'Aucune donnée'][$status]??'Statut indisponible';
}
