<?php
// Native signed billing reader. Never creates a payment, attendance or passage.
// numeric(14,2) is calculated in exact cents. A failed read is not a zero balance.
function SchoolSafeFeeScan($qr,$secretHex,array $ctx,array $db,$day=null)
{
    require_once __DIR__.'/SchoolSafeCardVerify.fnc.php';
    require_once __DIR__.'/SchoolSafeFeePolicy.fnc.php';
    $school=(int)($ctx['school']??0);$year=(int)($ctx['year']??0);$user=(int)($ctx['user']??0);
    if($school<1||$year<1||$user<1)throw new RuntimeException('INVALID_CONTEXT');
    $day=$day??date('Y-m-d');
    if(!is_string($day)||preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$day)!==1)throw new RuntimeException('INVALID_CONTEXT');
    $cardQuery=function($number,$scope)use($db,$year){return SchoolSafeCardVerifyQuery($number,$scope,$year,$db['get']);};
    $verify=SchoolSafeCardVerifyQr($qr,$secretHex,$school,$year,$cardQuery);
    if($verify===null||($verify['result']??'')!=='VALID')return $verify;
    $db['exec']('BEGIN');
    try{
        $model=SchoolSafeFeePolicyActive($ctx,$db,true);
        $ledger=SchoolSafeFeeLedgerRead((int)$verify['student_id'],$school,$year,$db['get'],$day);
        $financial=$model===null?['state'=>'inactive','color'=>null,'reason'=>'no_active_policy']:SchoolSafeFeeClassify($model,$ledger);
        $id=SchoolSafeFeeConsultRecord((int)$verify['student_id'],$ctx,$ledger,$db['get']);
        $db['exec']('COMMIT');
    }catch(Throwable $error){try{$db['exec']('ROLLBACK');}catch(Throwable $ignored){}throw $error;}
    $result=['result'=>'FEE_STATUS','decision'=>'allowed','balance_status'=>$ledger['status'],'consult_id'=>$id,'financial'=>$financial]+$verify;
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
    foreach($rows as $row){
        if(!is_array($row)||!isset($row['ID'],$row['AMOUNT'],$row['LEDGER_KIND']))throw new RuntimeException('FEE_READ_UNAVAILABLE');
        $amount=SchoolSafeFeeCents($row['AMOUNT']);
        if($row['LEDGER_KIND']==='payment'){$paid=SchoolSafeFeeAddCents($paid,$amount);continue;}
        if($row['LEDGER_KIND']!=='fee')throw new RuntimeException('FEE_READ_UNAVAILABLE');
        $total=SchoolSafeFeeAddCents($total,$amount);
        if(isset($row['WAIVED_FEE_ID'])){
            $id=(int)$row['WAIVED_FEE_ID'];$waiverTotal=SchoolSafeFeeAddCents($waiverTotal,$amount);
            $adjustments[$id]=SchoolSafeFeeAddCents($adjustments[$id]??0,$amount);
        }else $fees[(int)$row['ID']]=['cents'=>$amount,'due'=>$row['DUE_DATE']??null];
    }
    if(($paid>0&&$total<PHP_INT_MIN+$paid)||($paid<0&&$total>PHP_INT_MAX+$paid))throw new RuntimeException('FEE_READ_UNAVAILABLE');
    $balance=$total-$paid;$status='due';
    if(!$rows)$status='no_data';
    elseif($total<=0&&$paid<=0&&$waiverTotal<0)$status='exempt';
    elseif($balance<=0)$status='paid';
    elseif($paid>0)$status='partial';
    $overdue=0;
    if($balance>0)foreach($fees as $id=>$fee){
        if($fee['due']===null||$fee['due']==='')continue;
        if(!is_string($fee['due'])||preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$fee['due'])!==1)throw new RuntimeException('FEE_READ_UNAVAILABLE');
        // Nominal deadlines only: Rosario payments have no per-fee allocation.
        if($fee['due']<$day&&SchoolSafeFeeAddCents($fee['cents'],$adjustments[$id]??0)>0)$overdue++;
    }
    return ['fee_cents'=>$total,'paid_cents'=>$paid,'balance_cents'=>$balance,'status'=>$status,
        'has_data'=>(bool)$rows,'fee_count'=>count($fees),'overdue_count'=>$overdue];
}

// Caller owns the transaction; a failure must roll back the enclosing passage too.
function SchoolSafeFeeConsultRecord(int $student,array $ctx,array $ledger,callable $get): int
{
    $row=$get('INSERT INTO schoolsafe_fee_consults(school_id,student_id,consulted_by,balance_status,fee_total,paid_total,balance) VALUES('
        .(int)$ctx['school'].','.$student.','.(int)$ctx['user'].",'".$ledger['status']."',".SchoolSafeFeeFormatCents($ledger['fee_cents']).','
        .SchoolSafeFeeFormatCents($ledger['paid_cents']).','.SchoolSafeFeeFormatCents($ledger['balance_cents']).') RETURNING ID');
    if(!is_array($row)||!isset($row[1]['ID']))throw new RuntimeException('FEE_WRITE_FAILED');
    return (int)$row[1]['ID'];
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
