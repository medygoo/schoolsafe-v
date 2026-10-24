<?php
// Native scan -> existing Notify/Studio transactional outbox. No network,
// secondary ledger, inferred historical school/year, or fabricated receipt.
require_once __DIR__.'/SchoolSafeNotify.fnc.php';
require_once __DIR__.'/SchoolSafeFeeSnapshot.fnc.php';
require_once __DIR__.'/SchoolSafeScanNotice.fnc.php';
require_once __DIR__.'/SchoolSafePickupManage.fnc.php';

// Installed by every real native scanner caller, not supplied by HTTP input.
// Lower-level legacy callbacks remain testable without a messaging dependency.
function SchoolSafeScanNotifyAdapter(array $db,array $ctx): array
{
    SchoolSafePickupManageContext($ctx);
    $db['event']=function(string $type,int $id,array $identity=[])use($db,$ctx){
        return SchoolSafeScanNotifyRecord($type,$id,$identity,$ctx,$db,false);
    };
    return $db;
}

function SchoolSafeScanNotifyRecord(string $type,int $id,array $identity,array $ctx,array $db,bool $ownTransaction=false): array
{
    [$school,$year]=SchoolSafeFeePolicyScope($ctx);
    if($id<1||!in_array($type,['financial.control','security.entry','student.pickup'],true))throw new RuntimeException('INVALID_CONTEXT');
    if($type==='financial.control'){
        $rows=$db['get']('SELECT ID,STUDENT_ID,CONSULTED_BY,CONTEXT_SNAPSHOT FROM schoolsafe_fee_consults WHERE ID='.$id
            .' AND SCHOOL_ID='.$school.' AND SYEAR='.$year.' AND CONTEXT_SNAPSHOT IS NOT NULL');
        if(!is_array($rows)||count($rows)!==1||!isset($rows[1]['CONSULTED_BY'],$rows[1]['STUDENT_ID']))throw new RuntimeException('NOTIFY_CONTEXT_UNAVAILABLE');
        $row=$rows[1];$student=(int)$row['STUDENT_ID'];$actor=(int)$row['CONSULTED_BY'];
        if(min($student,$actor)<1)throw new RuntimeException('NOTIFY_CONTEXT_UNAVAILABLE');
        if(($ctx['user']??0)>0&&(int)$ctx['user']!==$actor)throw new RuntimeException('NOTIFY_CONTEXT_UNAVAILABLE');
        $snapshot=is_string($row['CONTEXT_SNAPSHOT']??null)?json_decode($row['CONTEXT_SNAPSHOT'],true):($row['CONTEXT_SNAPSHOT']??null);
        if(!is_array($snapshot)||!in_array($snapshot['operation']??null,['consult','entry'],true)
            ||!is_string($snapshot['recorded_at']??null)||$snapshot['recorded_at']==='')throw new RuntimeException('NOTIFY_CONTEXT_UNAVAILABLE');
        SchoolSafeFeeObservationDecode($row,$student,['school'=>$school,'year'=>$year,'user'=>$actor],$snapshot['operation']);
        // Explicit field lists: never forward unknown JSON properties (QR, phone,
        // credential, portrait, etc.) even if an audit row was imported manually.
        $safe=array_intersect_key($snapshot,array_flip(['version','operation','scope','syear','actor_staff_id','recorded_at','action']));
        foreach($safe as$value)if(is_array($value)||is_object($value))throw new RuntimeException('NOTIFY_CONTEXT_UNAVAILABLE');
        foreach(['school'=>['id','name'],'student'=>['id','name','class_name'],
            'policy'=>['id','label','minimum','effective_minimum','tolerance_percent','tolerance_floor'],
            'financial'=>['state','color','entry_allowed','reason','policy_id'],
            'ledger'=>['available','status','fee_count','overdue_count','net_billed','billed_positive','waiver_adjustments','paid_positive',
                'refund_adjustments','other_fee_adjustments','other_payment_adjustments','paid_net','balance','credit','amount_due',
                'currency','currency_state','nominal_due_from','nominal_due_to','deadline_basis']]as$key=>$fields){
            $safe[$key]=is_array($snapshot[$key]??null)?array_intersect_key($snapshot[$key],array_flip($fields)):null;
            foreach($safe[$key]??[]as$value)if(is_array($value)||is_object($value))throw new RuntimeException('NOTIFY_CONTEXT_UNAVAILABLE');
        }
        $payload=['version'=>1,'source'=>'native_scan','consult_id'=>$id,'observation'=>$safe,
            'title'=>$safe['policy']['label']??'Consultation financière','message'=>SchoolSafeScanFinancialNotice($safe)];
        $dedup='fee:consult:'.$id;
    }else{
        $rows=$db['get']('SELECT r.ID,r.STUDENT_ID,r.CHECKPOINT_ID,r.TYPE,r.RECORDED_AT,c.TITLE AS CHECKPOINT_TITLE,s.TITLE AS SCHOOL_TITLE'
            .' FROM entry_exit_student_records r JOIN entry_exit_checkpoints c ON c.ID=r.CHECKPOINT_ID'
            .' JOIN schools s ON s.ID=r.SCHOOLSAFE_SCAN_SCHOOL_ID AND s.SYEAR=r.SCHOOLSAFE_SCAN_SYEAR'
            .' WHERE r.ID='.$id.' AND r.SCHOOLSAFE_SCAN_SCHOOL_ID='.$school.' AND r.SCHOOLSAFE_SCAN_SYEAR='.$year
            ." AND r.TYPE='".($type==='security.entry'?'in':'out')."' AND (r.UNAUTHORIZED IS NULL OR r.UNAUTHORIZED='N')");
        if(!is_array($rows)||count($rows)!==1||!isset($rows[1]['STUDENT_ID'],$rows[1]['RECORDED_AT'],$rows[1]['SCHOOL_TITLE']))throw new RuntimeException('NOTIFY_CONTEXT_UNAVAILABLE');
        $row=$rows[1];$student=(int)$row['STUDENT_ID'];$actor=(int)($ctx['user']??0);
        if((int)($identity['student_id']??0)!==$student||!is_string($identity['student_name']??null)||trim($identity['student_name'])==='')throw new RuntimeException('NOTIFY_CONTEXT_UNAVAILABLE');
        $message=($type==='security.entry'?'Arrivée physique enregistrée':'Sortie et remise confirmées')."\nÉcole : ".$row['SCHOOL_TITLE']
            ."\nÉlève : ".$identity['student_name']."\nClasse : ".($identity['class_name']??'non renseignée')
            ."\nAnnée scolaire : ".$year."\nDate/heure du passage : ".$row['RECORDED_AT']
            ."\nPoint de passage : ".$row['CHECKPOINT_TITLE'];
        if($type==='student.pickup'){
            $proof=$db['get']('SELECT ID FROM schoolsafe_pickup_proofs WHERE RECORD_ID='.$id.' AND SCHOOL_ID='.$school
                .' AND SYEAR='.$year.' AND STUDENT_ID='.$student.' AND USED_AT IS NOT NULL');
            if(!is_array($proof)||count($proof)!==1||!is_string($identity['person_name']??null)||trim($identity['person_name'])==='')throw new RuntimeException('NOTIFY_CONTEXT_UNAVAILABLE');
            $message.="\nPersonne venue : ".$identity['person_name']."\nQualité : ".($identity['person_role']??'non renseignée');
        }
        $message.="\nCe passage ne vaut pas présence pédagogique en classe. Aucun paiement n’est enregistré par cette notification.";
        if(($identity['recovered']??false)===true)$message.="\nReprise du passage conservé, pas de nouvelle arrivée ou remise. Identité du dossier relue au moment de cette reprise.";
        $payload=['version'=>1,'source'=>'native_scan','record_id'=>$id,'message'=>$message];
        $dedup=($type==='security.entry'?'entry:record:':'pickup:record:').$id;
    }
    $recipients=SchoolSafeNotifyResolveRecipients($type,$school,$year,$student,$db);
    $out=SchoolSafeNotifyEmit(['school_id'=>$school,'syear'=>$year,'student_id'=>$student,'actor_staff_id'=>$actor?:null,
        'event_type'=>$type,'dedup_key'=>$dedup,'payload'=>$payload],$recipients,$db,$ownTransaction);
    if(!is_array($out))throw new RuntimeException('NOTIFY_UNAVAILABLE');
    return $out;
}

// Reconciliation reads only explicit native scope/frozen observations. Never
// reassign legacy events from current enrollment or rebuild old money.
function SchoolSafeScanNotifyRecover(string $source,array $db,int $limit=200): array
{
    $limit=max(1,min(500,$limit));$counts=['entry'=>0,'pickup'=>0,'fees'=>0,'skipped'=>0];
    $types=[];
    if(in_array($source,['entry','scan','all'],true))$types['entry']='security.entry';
    if(in_array($source,['pickup','scan','all'],true))$types['pickup']='student.pickup';
    if(in_array($source,['fees','scan','all'],true))$types['fees']='financial.control';
    foreach($types as$kind=>$type){
        if($kind==='fees'){
            $sql='SELECT c.ID,c.STUDENT_ID,c.SCHOOL_ID,c.SYEAR FROM schoolsafe_fee_consults c'
                .' WHERE c.SCHOOL_ID>0 AND c.SYEAR>0 AND c.CONTEXT_SNAPSHOT IS NOT NULL'
                ." AND c.OPERATION IN ('entry','consult') AND NOT EXISTS(SELECT 1 FROM schoolsafe_notify_events e"
                ." WHERE e.SCHOOL_ID=c.SCHOOL_ID AND e.SYEAR=c.SYEAR AND e.DEDUP_KEY='fee:consult:'||c.ID) ORDER BY c.ID LIMIT ".$limit;
        }else{
            $prefix=$kind==='entry'?'entry:record:':'pickup:record:';
            $sql='SELECT r.ID,r.STUDENT_ID,r.SCHOOLSAFE_SCAN_SCHOOL_ID AS SCHOOL_ID,r.SCHOOLSAFE_SCAN_SYEAR AS SYEAR'
                .' FROM entry_exit_student_records r WHERE r.SCHOOLSAFE_SCAN_SCHOOL_ID>0 AND r.SCHOOLSAFE_SCAN_SYEAR>0'
                ." AND r.TYPE='".($kind==='entry'?'in':'out')."' AND (r.UNAUTHORIZED IS NULL OR r.UNAUTHORIZED='N')"
                ." AND r.RECORDED_AT>=CURRENT_TIMESTAMP - INTERVAL '7 days'" // Preserve existing native passage recovery window.
                .' AND NOT EXISTS(SELECT 1 FROM schoolsafe_notify_events e WHERE e.SCHOOL_ID=r.SCHOOLSAFE_SCAN_SCHOOL_ID'
                ." AND e.SYEAR=r.SCHOOLSAFE_SCAN_SYEAR AND e.DEDUP_KEY='".$prefix."'||r.ID) ORDER BY r.ID LIMIT ".$limit;
        }
        $rows=$db['get']($sql);if(!is_array($rows))throw new RuntimeException('NOTIFY_CONTEXT_UNAVAILABLE');
        foreach($rows as$row){
            // Modern controls commit their original recipients with the outbox
            // atomically. An older audit without that event preserves money,
            // NOT who was entitled at the time. Today's SJU cannot reconstruct
            // historical recipients; leave it explicitly skipped, never send
            // private old observations to a newly linked parent.
            if($kind==='fees'){$counts['skipped']++;continue;}
            try{
                $id=(int)$row['ID'];$student=(int)$row['STUDENT_ID'];$school=(int)$row['SCHOOL_ID'];$year=(int)$row['SYEAR'];
                $identity=$kind==='fees'?[]:SchoolSafeScanNotifyRecoveryIdentity($student,$school,$year,$db);
                $actor=0;
                if($kind==='pickup'){
                    $person=$db['get']('SELECT g.FULL_NAME,g.GUARDIAN_TYPE,p.PREPARED_BY FROM schoolsafe_pickup_proofs p'
                        .' JOIN schoolsafe_pickup_guardians g ON g.ID=p.GUARDIAN_ID AND g.SCHOOL_ID=p.SCHOOL_ID AND g.STUDENT_ID=p.STUDENT_ID'
                        .' WHERE p.RECORD_ID='.$id.' AND p.STUDENT_ID='.$student.' AND p.SCHOOL_ID='.$school.' AND p.SYEAR='.$year.' AND p.USED_AT IS NOT NULL');
                    if(!is_array($person)||count($person)!==1)throw new RuntimeException('NOTIFY_CONTEXT_UNAVAILABLE');
                    $identity['person_name']=$person[1]['FULL_NAME'];$identity['person_role']=$person[1]['GUARDIAN_TYPE'];$actor=(int)$person[1]['PREPARED_BY'];
                }
                $out=SchoolSafeScanNotifyRecord($type,$id,$identity,['school'=>$school,'year'=>$year,'user'=>$actor],$db,true);
                if(($out['created']??false)===true)$counts[$kind]++;
            }catch(Throwable $error){$counts['skipped']++;}
        }
    }
    return $counts;
}

// Recovery only: explicitly label identity as re-read, never infer the event's
// original school/year from today's enrollment or reconstruct financial facts.
function SchoolSafeScanNotifyRecoveryIdentity(int $student,int $school,int $year,array $db): array
{
    if(min($student,$school,$year)<1)throw new RuntimeException('INVALID_CONTEXT');
    $rows=$db['get']("SELECT s.STUDENT_ID,trim(concat_ws(' ',s.LAST_NAME,s.FIRST_NAME)) AS STUDENT_NAME,"
        .' CASE WHEN COUNT(DISTINCT g.TITLE)=1 THEN MAX(g.TITLE) ELSE NULL END AS CLASS_NAME'
        .' FROM students s JOIN student_enrollment se ON se.STUDENT_ID=s.STUDENT_ID'
        .' LEFT JOIN school_gradelevels g ON g.ID=se.GRADE_ID WHERE s.STUDENT_ID='.$student
        .' AND se.SCHOOL_ID='.$school.' AND se.SYEAR='.$year.' GROUP BY s.STUDENT_ID,s.LAST_NAME,s.FIRST_NAME');
    if(!is_array($rows)||count($rows)!==1||!is_string($rows[1]['STUDENT_NAME']??null)||trim($rows[1]['STUDENT_NAME'])==='')throw new RuntimeException('NOTIFY_CONTEXT_UNAVAILABLE');
    return ['student_id'=>$student,'student_name'=>$rows[1]['STUDENT_NAME'],'class_name'=>$rows[1]['CLASS_NAME']??null,'recovered'=>true];
}
