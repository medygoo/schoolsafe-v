<?php
// Observation of the existing native ledger, not an invoice or a payment receipt.
require_once __DIR__.'/SchoolSafeFeePolicy.fnc.php';

function SchoolSafeFeeControlSnapshot(array $identity,array $ctx,array $ledger,?array $model,array $financial,array $metadata,string $operation): array
{
    [$school,$year]=SchoolSafeFeePolicyScope($ctx);
    $student=(int)($identity['student_id']??0);$actor=(int)($ctx['user']??0);
    if($student<1||$actor<1||!in_array($operation,['consult','entry'],true))throw new RuntimeException('INVALID_CONTEXT');
    $expected=$model===null?['state'=>'inactive','color'=>null,'reason'=>'no_active_policy']:SchoolSafeFeeClassify($model,$ledger);
    if(!SchoolSafeFeeDecisionMatches($expected,$financial))throw new RuntimeException('FEE_READ_UNAVAILABLE');
    $available=($ledger['has_data']??false)===true;
    $money=function($key)use($ledger,$available){
        if(!$available)return null;
        if(!is_int($ledger[$key]??null))throw new RuntimeException('FEE_READ_UNAVAILABLE');
        return SchoolSafeFeeFormatCents($ledger[$key]);
    };
    $policy=null;
    if($model!==null){
        $bounds=$available?SchoolSafeFeePolicyBounds($model,$ledger):null;
        $policy=['id'=>$model['id'],'label'=>$model['label'],'minimum'=>SchoolSafeFeeFormatCents(SchoolSafeFeeCents($model['threshold'])),
            'effective_minimum'=>$bounds===null?null:SchoolSafeFeeFormatCents($bounds['minimum']),
            'tolerance_percent'=>$model['tolerance'],'tolerance_floor'=>$bounds===null?null:SchoolSafeFeeFormatCents($bounds['tolerance_floor'])];
    }
    $currency=$metadata['currency']??null;
    if($currency!==null&&(!is_string($currency)||preg_match('/^.{1,32}$/usD',$currency)!==1))throw new RuntimeException('FEE_CONTEXT_UNAVAILABLE');
    $balance=$money('balance_cents');
    $credit=$balance===null?null:($ledger['balance_cents']<0?substr($balance,1):'0.00');
    $due=$balance===null?null:($ledger['balance_cents']>0?$balance:'0.00');
    return ['version'=>1,'operation'=>$operation,'scope'=>'native_school_year_billing','syear'=>$year,'actor_staff_id'=>$actor,
        'school'=>['id'=>$school,'name'=>$metadata['school_name']??null],
        'student'=>['id'=>$student,'name'=>$identity['student_name']??null,'class_name'=>$identity['class_name']??null],
        'policy'=>$policy,'financial'=>$financial,
        'ledger'=>['available'=>$available,'status'=>$ledger['status'],'fee_count'=>$ledger['fee_count'],'overdue_count'=>$ledger['overdue_count'],'net_billed'=>$money('fee_cents'),
            'billed_positive'=>$money('billed_positive_cents'),'waiver_adjustments'=>$money('waiver_adjustment_cents'),
            'paid_positive'=>$money('payments_positive_cents'),'refund_adjustments'=>$money('refund_adjustment_cents'),
            'other_fee_adjustments'=>$money('fee_unlinked_adjustment_cents'),'other_payment_adjustments'=>$money('payment_unlinked_adjustment_cents'),
            'paid_net'=>$money('paid_cents'),'balance'=>$balance,'credit'=>$credit,'amount_due'=>$due,
            'currency'=>$currency,'currency_state'=>$metadata['currency_state']??'missing',
            'nominal_due_from'=>$ledger['nominal_due_from']??null,'nominal_due_to'=>$ledger['nominal_due_to']??null,
            'deadline_basis'=>'nominal_unallocated'],
        'action'=>'Consulter le dossier financier auprès du bureau de l’école. Ce contrôle n’est pas un reçu de paiement.'];
}

function SchoolSafeFeeDecisionMatches(array $expected,array $actual): bool
{
    if(count($expected)!==count($actual))return false;
    foreach($expected as $key=>$value)if(!array_key_exists($key,$actual)||$actual[$key]!==$value)return false;
    return true;
}

// An operation UUID is not a card/identity/permission. Legacy internal callers may omit it.
function SchoolSafeFeeRequestKey(array $ctx): ?string
{
    if(!array_key_exists('request_key',$ctx))return null;
    $key=$ctx['request_key'];
    if(!is_string($key)||preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/D',$key)!==1)throw new RuntimeException('INVALID_INPUT');
    return $key;
}

// Called under the native child transaction. Never recompute an old operation from today's ledger.
// Recheck card/permissions before this call, and reapply CURRENT detail rights to its response.
function SchoolSafeFeeConsultReplay(int $student,array $ctx,array $db): ?array
{
    $key=SchoolSafeFeeRequestKey($ctx);if($key===null)return null;
    [$school,$year]=SchoolSafeFeePolicyScope($ctx);
    $quote=$db['quote']??(function_exists('DBEscapeString')?function($value){return "'".DBEscapeString($value)."'";}:null);
    if(!is_callable($quote))throw new RuntimeException('FEE_CONTEXT_UNAVAILABLE');
    $rows=$db['get']('SELECT ID,STUDENT_ID,CONTEXT_SNAPSHOT FROM schoolsafe_fee_consults WHERE SCHOOL_ID='.$school
        .' AND SYEAR='.$year.' AND CONSULTED_BY='.(int)$ctx['user']." AND OPERATION='consult' AND REQUEST_KEY=".$quote($key).' FOR SHARE');
    if(!is_array($rows))throw new RuntimeException('FEE_READ_UNAVAILABLE');
    if(!$rows)return null;
    if(count($rows)!==1||!isset($rows[1]['ID'],$rows[1]['STUDENT_ID'],$rows[1]['CONTEXT_SNAPSHOT']))throw new RuntimeException('FEE_READ_UNAVAILABLE');
    if((int)$rows[1]['STUDENT_ID']!==$student)throw new RuntimeException('OPERATION_KEY_CONFLICT');
    $snapshot=is_string($rows[1]['CONTEXT_SNAPSHOT'])?json_decode($rows[1]['CONTEXT_SNAPSHOT'],true):$rows[1]['CONTEXT_SNAPSHOT'];
    if(!is_array($snapshot)||($snapshot['version']??null)!==1||($snapshot['school']['id']??null)!==$school
        ||($snapshot['syear']??null)!==$year||($snapshot['student']['id']??null)!==$student
        ||($snapshot['actor_staff_id']??null)!==(int)$ctx['user']||($snapshot['operation']??null)!=='consult')throw new RuntimeException('FEE_READ_UNAVAILABLE');
    $saved=$snapshot['ledger']??null;$financial=$snapshot['financial']??null;
    if(!is_array($saved)||!is_bool($saved['available']??null)||!is_array($financial)
        ||!in_array($saved['status']??null,['paid','partial','due','exempt','no_data'],true)
        ||!is_int($saved['fee_count']??null)||!is_int($saved['overdue_count']??null))throw new RuntimeException('FEE_READ_UNAVAILABLE');
    $ledger=['has_data'=>$saved['available'],'status'=>$saved['status'],'fee_count'=>$saved['fee_count'],'overdue_count'=>$saved['overdue_count']];
    foreach(['fee_cents'=>'net_billed','paid_cents'=>'paid_net','balance_cents'=>'balance'] as $target=>$source){
        $ledger[$target]=$saved['available']?SchoolSafeFeeCents($saved[$source]??null):0;
        if(!$saved['available']&&($saved[$source]??null)!==null)throw new RuntimeException('FEE_READ_UNAVAILABLE');
    }
    $policy=$snapshot['policy']??null;
    if($policy!==null&&!is_array($policy))throw new RuntimeException('FEE_READ_UNAVAILABLE');
    $expected=$policy===null?['state'=>'inactive','color'=>null,'reason'=>'no_active_policy']:SchoolSafeFeeClassify([
        'id'=>$policy['id']??null,'label'=>$policy['label']??null,'threshold'=>$policy['minimum']??null,
        'tolerance'=>$policy['tolerance_percent']??null,'active'=>true],$ledger);
    if(!SchoolSafeFeeDecisionMatches($expected,$financial))throw new RuntimeException('FEE_READ_UNAVAILABLE');
    return ['id'=>(int)$rows[1]['ID'],'ledger'=>$ledger,'financial'=>$financial];
}

// Audit association only. The native arrival transaction owns both writes and rollback.
function SchoolSafeFeeConsultEntry(int $consult,int $entry,int $student,array $ctx,array $db): void
{
    [$school,$year]=SchoolSafeFeePolicyScope($ctx);$actor=(int)($ctx['user']??0);
    if(min($consult,$entry,$student,$actor)<1)throw new RuntimeException('INVALID_CONTEXT');
    $rows=$db['get']('UPDATE schoolsafe_fee_consults SET ENTRY_RECORD_ID='.$entry.' WHERE ID='.$consult
        .' AND SCHOOL_ID='.$school.' AND SYEAR='.$year.' AND STUDENT_ID='.$student.' AND CONSULTED_BY='.$actor
        ." AND OPERATION='entry' AND ENTRY_RECORD_ID IS NULL AND context_snapshot->'financial'->>'entry_allowed'='true'"
        .' AND EXISTS(SELECT 1 FROM entry_exit_student_records r WHERE r.ID='.$entry.' AND r.STUDENT_ID='.$student
        .' AND r.SCHOOLSAFE_SCAN_SCHOOL_ID='.$school.' AND r.SCHOOLSAFE_SCAN_SYEAR='.$year
        ." AND r.TYPE='in' AND (r.UNAUTHORIZED IS NULL OR r.UNAUTHORIZED='N')) RETURNING ID");
    if(!is_array($rows)||!isset($rows[1]['ID']))throw new RuntimeException('FEE_WRITE_FAILED');
}

// Exact school/year owner and Config('CURRENCY') precedence: school override, then school 0.
// Missing currency stays missing; never assume USD, a conversion, or another school's label.
function SchoolSafeFeeControlMetadata(array $ctx,callable $get): array
{
    [$school,$year]=SchoolSafeFeePolicyScope($ctx);
    $rows=$get('SELECT TITLE FROM schools WHERE ID='.$school.' AND SYEAR='.$year);
    if(!is_array($rows)||!isset($rows[1]['TITLE'])||!is_string($rows[1]['TITLE']))throw new RuntimeException('FEE_CONTEXT_UNAVAILABLE');
    $currencyRows=$get("SELECT SCHOOL_ID,CONFIG_VALUE FROM config WHERE TITLE='CURRENCY' AND SCHOOL_ID IN (0,".$school.') ORDER BY SCHOOL_ID DESC LIMIT 2');
    $currency=null;$state='unavailable';
    if(is_array($currencyRows)){
        $state=$currencyRows?'unavailable':'missing';
        $first=$currencyRows[1]??null;$second=$currencyRows[2]??null;
        if(is_array($first)&&isset($first['SCHOOL_ID'])&&in_array((int)$first['SCHOOL_ID'],[0,$school],true)
            &&($second===null||(string)$second['SCHOOL_ID']!==(string)$first['SCHOOL_ID'])&&is_string($first['CONFIG_VALUE']??null)){
            $value=trim($first['CONFIG_VALUE']);
            if($value!==''&&preg_match('/^.{1,32}$/usD',$value)===1){$currency=$value;$state='known';}
            elseif($value==='')$state='missing';
        }
    }
    return ['school_name'=>$rows[1]['TITLE'],'currency'=>$currency,'currency_state'=>$state];
}
