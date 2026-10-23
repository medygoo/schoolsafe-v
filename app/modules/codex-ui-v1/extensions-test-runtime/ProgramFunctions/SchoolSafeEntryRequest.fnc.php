<?php
// HTTP operation receipts, NOT another passage/attendance/finance source.
// Called only after current permission/card/enrollment/checkpoint checks, under
// the existing native child transaction. No dispatcher or network calls here.
require_once __DIR__.'/SchoolSafeFeeSnapshot.fnc.php';

function SchoolSafeEntryRequest(int $student,int $checkpoint,array $ctx,array $db,callable $operation): array
{
    $key=SchoolSafeFeeRequestKey($ctx);
    if($key===null)return $operation(); // Backward-compatible internal callers.
    [$school,$year]=SchoolSafeFeePolicyScope($ctx);$actor=(int)($ctx['user']??0);
    if(min($student,$checkpoint,$actor)<1)throw new RuntimeException('INVALID_CONTEXT');
    $quote=$db['quote']??(function_exists('DBEscapeString')?function($value){return "'".DBEscapeString($value)."'";}:null);
    if(!is_callable($quote))throw new RuntimeException('SCAN_OPERATION_UNAVAILABLE');
    $where='SCHOOL_ID='.$school.' AND SYEAR='.$year.' AND ACTOR_STAFF_ID='.$actor.' AND REQUEST_KEY='.$quote($key).'::uuid';
    $rows=$db['get']('SELECT STUDENT_ID,CHECKPOINT_ID,RESULT,REASON,ENTRY_RECORD_ID,FINANCIAL_CONSULT_ID,FINANCIAL_STATE'
        .' FROM schoolsafe_entry_requests WHERE '.$where.' FOR SHARE');
    if(!is_array($rows))throw new RuntimeException('SCAN_OPERATION_UNAVAILABLE');
    if($rows){
        if(count($rows)!==1||!isset($rows[1]['STUDENT_ID'],$rows[1]['CHECKPOINT_ID'],$rows[1]['RESULT']))throw new RuntimeException('SCAN_OPERATION_UNAVAILABLE');
        $saved=$rows[1];
        if((int)$saved['STUDENT_ID']!==$student||(int)$saved['CHECKPOINT_ID']!==$checkpoint)throw new RuntimeException('OPERATION_KEY_CONFLICT');
        if(in_array($saved['RESULT'],['PRESENT','ALREADY_PRESENT'],true)){
            if((int)($saved['ENTRY_RECORD_ID']??0)<1)throw new RuntimeException('SCAN_OPERATION_UNAVAILABLE');
            // Historical success, never a claim about current physical presence
            // or a new financial authorization. No old blue/yellow reused here.
            return ['result'=>'ALREADY_RECORDED','decision'=>'recorded','entry_allowed'=>false,'idempotent'=>true,
                'original_result'=>$saved['RESULT'],'record_id'=>(int)$saved['ENTRY_RECORD_ID']];
        }
        if($saved['RESULT']!=='DENIED'||!in_array($saved['REASON']??null,['FEE_CONTROL_DENIED','FEE_CONTROL_UNAVAILABLE'],true)
            ||(int)($saved['FINANCIAL_CONSULT_ID']??0)<1)throw new RuntimeException('SCAN_OPERATION_UNAVAILABLE');
        $consult=(int)$saved['FINANCIAL_CONSULT_ID'];
        $observations=$db['get']('SELECT ID,STUDENT_ID,CONTEXT_SNAPSHOT FROM schoolsafe_fee_consults WHERE ID='.$consult
            .' AND SCHOOL_ID='.$school.' AND SYEAR='.$year.' AND CONSULTED_BY='.$actor.' AND STUDENT_ID='.$student
            ." AND OPERATION='entry' AND REQUEST_KEY=".$quote($key).' FOR SHARE');
        if(!is_array($observations)||count($observations)!==1||!isset($observations[1]))throw new RuntimeException('SCAN_OPERATION_UNAVAILABLE');
        $observation=SchoolSafeFeeObservationDecode($observations[1],$student,$ctx,'entry');
        $financial=$observation['financial'];
        $expectedReason=($financial['state']??null)==='unavailable'?'FEE_CONTROL_UNAVAILABLE':'FEE_CONTROL_DENIED';
        if(($financial['entry_allowed']??null)!==false||($financial['state']??null)!==($saved['FINANCIAL_STATE']??null)
            ||$saved['REASON']!==$expectedReason)throw new RuntimeException('SCAN_OPERATION_UNAVAILABLE');
        return ['result'=>'DENIED','decision'=>'denied','reason'=>$saved['REASON'],'idempotent'=>true,
            'financial'=>$financial,'financial_consult_id'=>$consult];
    }
    $result=$operation();
    $code=$result['result']??null;$reason=$result['reason']??null;
    $record=$result['record_id']??null;$consult=$result['financial_consult_id']??null;
    $state=$result['financial']['state']??null;
    if(!in_array($code,['PRESENT','ALREADY_PRESENT','DENIED'],true))throw new RuntimeException('SCAN_OPERATION_WRITE_FAILED');
    $inserted=$db['get']('INSERT INTO schoolsafe_entry_requests(school_id,syear,actor_staff_id,request_key,student_id,checkpoint_id,result,reason,entry_record_id,financial_consult_id,financial_state) VALUES('
        .$school.','.$year.','.$actor.','.$quote($key).'::uuid,'.$student.','.$checkpoint.','.$quote($code).','
        .($reason===null?'NULL':$quote($reason)).','.($record===null?'NULL':(int)$record).','
        .($consult===null?'NULL':(int)$consult).','.($state===null?'NULL':$quote($state)).') RETURNING REQUEST_KEY');
    if(!is_array($inserted)||!isset($inserted[1]['REQUEST_KEY']))throw new RuntimeException('SCAN_OPERATION_WRITE_FAILED');
    return $result;
}
