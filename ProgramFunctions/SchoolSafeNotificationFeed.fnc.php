<?php
function SchoolSafeNotificationPriority(array $item): int
{
    $type=strtolower((string)($item['event_type']??''));
    if(preg_match('/security|sos|arrival|departure|entry|exit|pickup/', $type)) return 0;
    if(preg_match('/payment|receipt|billing|financial/', $type)) return 1;
    return 2;
}
function SchoolSafeNotificationOrder(array $items): array
{
    $unique=[];
    foreach($items as $item){
        if(!is_array($item)||!is_string($item['id']??null))continue;
        $item['priority']=SchoolSafeNotificationPriority($item);
        $unique[$item['id']]=$item;
    }
    $items=array_values($unique);
    usort($items,static fn($a,$b)=>($a['priority']<=>$b['priority'])
        ?:((strtotime($b['created_at']??'')?:0)<=>(strtotime($a['created_at']??'')?:0))
        ?:strcmp($a['id'],$b['id']));
    return array_slice($items,0,200);
}
// Read only committed official passages in the requested tenant/year, and
// recheck the live native parent link even if the caller supplied a stale list.
function SchoolSafeNotificationPassageRows(array $ids,int $user,int $school,int $year,string $direction,callable $query): array
{
    require_once __DIR__.'/../schoolsafe-attendance/ArrivalRead.php';
    SchoolSafeArrivalReadId($user);SchoolSafeArrivalReadId($school);SchoolSafeArrivalReadId($year);
    if (!in_array($direction,['in','out'],true)) throw new RuntimeException('INVALID_PASSAGE_DIRECTION');
    if (!$ids) return [];
    $ids=array_map(static fn($id)=>SchoolSafeArrivalReadId($id),$ids);
    if (!SchoolSafeArrivalNativeRecordsReady($query)) throw new RuntimeException('SCAN_SCOPE_UNAVAILABLE');
    require_once __DIR__.'/SchoolSafeNotifyRecipient.fnc.php';
    if(!SchoolSafeNotifyParentCurrent($user,$school,$year,0,['get'=>$query]))return [];
    $meta=$query("SELECT to_regclass('public.schoolsafe_notify_events') AS EVENTS_TABLE,to_regclass('public.schoolsafe_notify_deliveries') AS DELIVERIES_TABLE");
    if(!is_array($meta)||count($meta)!==1||!array_key_exists('EVENTS_TABLE',$meta[1])||!array_key_exists('DELIVERIES_TABLE',$meta[1]))throw new RuntimeException('SCAN_NOTIFICATIONS_UNAVAILABLE');
    $noticeSelect='';$noticeJoin='';
    if(!empty($meta[1]['EVENTS_TABLE'])&&!empty($meta[1]['DELIVERIES_TABLE'])){
        $type=$direction==='in'?'security.entry':'student.pickup';$prefix=$direction==='in'?'entry:record:':'pickup:record:';
        $noticeSelect=',CASE WHEN nd.ID IS NOT NULL THEN n.PAYLOAD ELSE NULL END AS NOTICE_PAYLOAD';
        $noticeJoin=' LEFT JOIN schoolsafe_notify_events n ON n.SCHOOL_ID=r.SCHOOLSAFE_SCAN_SCHOOL_ID AND n.SYEAR=r.SCHOOLSAFE_SCAN_SYEAR'
            ." AND n.STUDENT_ID=r.STUDENT_ID AND n.EVENT_TYPE='".$type."' AND n.DEDUP_KEY='".$prefix."'||r.ID"
            ." AND n.PAYLOAD->>'source'='native_scan' AND n.PAYLOAD->>'record_id'=r.ID::text"
            .' LEFT JOIN schoolsafe_notify_deliveries nd ON nd.EVENT_ID=n.ID AND nd.SCHOOL_ID=n.SCHOOL_ID AND nd.SYEAR=n.SYEAR'
            ." AND nd.CHANNEL='feed' AND nd.AUDIENCE='parent' AND nd.RECIPIENT_STAFF_ID=".$user;
    }
    $rows=$query('SELECT r.ID,r.STUDENT_ID,s.FIRST_NAME,s.LAST_NAME,r.RECORDED_AT,c.TITLE'.$noticeSelect
        .' FROM entry_exit_student_records r JOIN students s ON s.STUDENT_ID=r.STUDENT_ID'
        .' LEFT JOIN entry_exit_checkpoints c ON c.ID=r.CHECKPOINT_ID'
        .' AND c.SCHOOLSAFE_SCAN_SCHOOL_ID=r.SCHOOLSAFE_SCAN_SCHOOL_ID AND c.SCHOOLSAFE_SCAN_SYEAR=r.SCHOOLSAFE_SCAN_SYEAR'.$noticeJoin
        .' WHERE r.STUDENT_ID IN ('.implode(',',$ids).") AND r.TYPE='".$direction."' AND r.UNAUTHORIZED IS NULL"
        .' AND r.SCHOOLSAFE_SCAN_SCHOOL_ID='.$school.' AND r.SCHOOLSAFE_SCAN_SYEAR='.$year
        .' AND EXISTS(SELECT 1 FROM student_enrollment e WHERE e.STUDENT_ID=r.STUDENT_ID'
        .' AND e.SCHOOL_ID=r.SCHOOLSAFE_SCAN_SCHOOL_ID AND e.SYEAR=r.SCHOOLSAFE_SCAN_SYEAR)'
        .' AND EXISTS(SELECT 1 FROM students_join_users su JOIN staff st ON st.STAFF_ID=su.STAFF_ID'
        ." WHERE su.STUDENT_ID=r.STUDENT_ID AND su.STAFF_ID=".$user." AND st.PROFILE='parent' AND st.SYEAR=".$year.')'
        ." AND r.RECORDED_AT>=CURRENT_TIMESTAMP - INTERVAL '7 days' ORDER BY r.RECORDED_AT DESC,r.ID DESC LIMIT 20");
    if (!is_array($rows)) throw new RuntimeException('SCAN_PASSAGES_UNAVAILABLE');
    return $rows;
}
function SchoolSafeNotificationPassageMessage(array $row,string $fallback): string
{
    $notice=is_string($row['NOTICE_PAYLOAD']??null)?json_decode($row['NOTICE_PAYLOAD'],true):($row['NOTICE_PAYLOAD']??null);
    return is_array($notice)&&is_string($notice['message']??null)&&trim($notice['message'])!==''?$notice['message']:$fallback;
}
// Read the EXISTING Notify feed deliveries, not a reconstructed current ledger.
// Original recipients AND live parent/year/account authority must both hold.
function SchoolSafeNotificationFinancialRows(array $ids,int $user,int $school,int $year,callable $query): array
{
    require_once __DIR__.'/../schoolsafe-attendance/ArrivalRead.php';
    require_once __DIR__.'/SchoolSafeNotifyRecipient.fnc.php';
    SchoolSafeArrivalReadId($user);SchoolSafeArrivalReadId($school);SchoolSafeArrivalReadId($year);
    if(!$ids)return [];
    $ids=array_map(static fn($id)=>SchoolSafeArrivalReadId($id),$ids);
    $meta=$query("SELECT to_regclass('public.schoolsafe_notify_events') AS EVENTS_TABLE,to_regclass('public.schoolsafe_notify_deliveries') AS DELIVERIES_TABLE");
    if(!is_array($meta)||count($meta)!==1||empty($meta[1]['EVENTS_TABLE'])||empty($meta[1]['DELIVERIES_TABLE']))throw new RuntimeException('FINANCIAL_NOTIFICATIONS_UNAVAILABLE');
    if(!SchoolSafeNotifyParentCurrent($user,$school,$year,0,['get'=>$query]))return [];
    $rows=$query('SELECT e.ID,e.STUDENT_ID,e.CREATED_AT,e.PAYLOAD,d.STATUS FROM schoolsafe_notify_events e'
        .' JOIN schoolsafe_notify_deliveries d ON d.EVENT_ID=e.ID AND d.SCHOOL_ID=e.SCHOOL_ID AND d.SYEAR=e.SYEAR'
        .' WHERE e.SCHOOL_ID='.$school.' AND e.SYEAR='.$year.' AND e.STUDENT_ID IN ('.implode(',',$ids).')'
        ." AND e.EVENT_TYPE='financial.control' AND d.CHANNEL='feed' AND d.AUDIENCE='parent' AND d.RECIPIENT_STAFF_ID=".$user
        ." AND e.PAYLOAD->>'source'='native_scan' AND e.PAYLOAD->'observation'->'school'->>'id'=e.SCHOOL_ID::text"
        ." AND e.PAYLOAD->'observation'->>'syear'=e.SYEAR::text AND e.PAYLOAD->'observation'->'student'->>'id'=e.STUDENT_ID::text"
        .' AND EXISTS(SELECT 1 FROM students_join_users su JOIN staff st ON st.STAFF_ID=su.STAFF_ID'
        ." WHERE su.STUDENT_ID=e.STUDENT_ID AND su.STAFF_ID=".$user." AND st.PROFILE='parent' AND st.SYEAR=e.SYEAR)"
        .' AND EXISTS(SELECT 1 FROM student_enrollment se WHERE se.STUDENT_ID=e.STUDENT_ID AND se.SCHOOL_ID=e.SCHOOL_ID AND se.SYEAR=e.SYEAR)'
        .' ORDER BY e.CREATED_AT DESC,e.ID DESC LIMIT 50');
    if(!is_array($rows))throw new RuntimeException('FINANCIAL_NOTIFICATIONS_UNAVAILABLE');
    $items=[];
    foreach($rows as$row){
        $payload=is_string($row['PAYLOAD']??null)?json_decode($row['PAYLOAD'],true):($row['PAYLOAD']??null);
        if(!is_array($payload)||!is_string($payload['message']??null)||$payload['message']===''||!is_int($payload['consult_id']??null))throw new RuntimeException('FINANCIAL_NOTIFICATIONS_UNAVAILABLE');
        $name=$payload['observation']['student']['name']??null;
        $items[]=['id'=>'financial-control:'.$row['ID'],'student_id'=>(string)$row['STUDENT_ID'],
            'student_name'=>is_string($name)?$name:'','event_type'=>'financial.control','created_at'=>$row['CREATED_AT'],
            'payload'=>['message'=>$payload['message'],'consult_id'=>$payload['consult_id'],'delivery_status'=>$row['STATUS']]];
    }
    return $items;
}
function SchoolSafeNotificationFeed(array $scans,array $principal,array $ids,int $user,int $school,int $year,array &$degraded): array
{
    require_once __DIR__.'/../schoolsafe-remediation/ParentInbox.fnc.php';
    require_once __DIR__.'/SchoolSafeDashboardNative.fnc.php';
    $sources=[
        'arrivals'=>static function()use($ids,$user,$school,$year){
            // Lot ENTRY : arrivées scannées des enfants, sans le bridge.
            if(!$ids)return[];
            $rows=SchoolSafeNotificationPassageRows($ids,$user,$school,$year,'in',static fn($sql)=>DBGet($sql));
            $items=[];
            if(is_array($rows))foreach($rows as $row){
                if(!isset($row['ID'],$row['STUDENT_ID'],$row['RECORDED_AT']))continue;
                $at=substr($row['RECORDED_AT'],0,16);
                $items[]=['id'=>'arrival:'.$row['ID'],'student_id'=>(string)$row['STUDENT_ID'],
                    'student_name'=>trim($row['FIRST_NAME'].' '.$row['LAST_NAME']),
                    'event_type'=>'security.entry','created_at'=>$row['RECORDED_AT'],
                    'payload'=>['message'=>SchoolSafeNotificationPassageMessage($row,'Arrivée enregistrée à '.$at
                        .(!empty($row['TITLE'])?' — '.$row['TITLE']:'')),'record_id'=>(int)$row['ID']]];
            }
            return $items;
        },
        'departures'=>static function()use($ids,$user,$school,$year){
            // Lot PICKUP : sorties confirmées des enfants, sans le bridge.
            if(!$ids)return[];
            $rows=SchoolSafeNotificationPassageRows($ids,$user,$school,$year,'out',static fn($sql)=>DBGet($sql));
            $items=[];
            if(is_array($rows))foreach($rows as $row){
                if(!isset($row['ID'],$row['STUDENT_ID'],$row['RECORDED_AT']))continue;
                $at=substr($row['RECORDED_AT'],0,16);
                $items[]=['id'=>'departure:'.$row['ID'],'student_id'=>(string)$row['STUDENT_ID'],
                    'student_name'=>trim($row['FIRST_NAME'].' '.$row['LAST_NAME']),
                    'event_type'=>'student.pickup','created_at'=>$row['RECORDED_AT'],
                    'payload'=>['message'=>SchoolSafeNotificationPassageMessage($row,'Sortie confirmée à '.$at),'record_id'=>(int)$row['ID']]];
            }
            return $items;
        },
        'financial_controls'=>static fn()=>SchoolSafeNotificationFinancialRows($ids,$user,$school,$year,static fn($sql)=>DBGet($sql)),
        'remediation'=>static fn()=>SchoolSafeRemediationParentInbox($principal,$ids,$user,$school,$year),
        'birthdays'=>static fn()=>SchoolSafeBirthdayParentInbox($principal,$ids,$school,$year),
        'palmares'=>static fn()=>SchoolSafePalmaresParentInbox(),
        'receipts'=>static function()use($user,$school,$year){
            require_once __DIR__.'/SchoolSafeReceipts.fnc.php';
            // Garde anti-die Rosario : db_show_error() affiche du HTML puis die()
            // (non rattrapable) quand la table FEES est absente. On vérifie
            // d'abord via to_regclass et on dégrade proprement si besoin.
            $reg=DBGet("SELECT to_regclass('public.schoolsafe_payment_receipts') AS t");
            $found='';
            if(is_array($reg))foreach($reg as $rr){$found=(string)($rr['T']??'');break;}
            if($found==='')throw new RuntimeException('RECEIPTS_UNAVAILABLE');
            $rows=DBGet("SELECT r.* FROM schoolsafe_payment_receipts r WHERE r.school_id=$school AND r.syear=$year
                AND r.status<>'corrected' AND EXISTS(SELECT 1 FROM students_join_users u
                WHERE u.student_id=r.student_id AND u.staff_id=$user) ORDER BY r.id DESC LIMIT 100");
            $items=[];
            foreach($rows as $row){
                if(!SchoolSafeReceiptPermission($row))continue;
                $snapshot=json_decode($row['SNAPSHOT'],true,32,JSON_THROW_ON_ERROR);
                $items[]=['id'=>'receipt:'.$row['ID'],'student_id'=>(string)$row['STUDENT_ID'],
                    'student_name'=>(string)($snapshot['student']??''),
                    'event_type'=>'payment.receipt','created_at'=>$row['CREATED_AT'],
                    'payload'=>['message'=>'Recu de paiement disponible','receipt_id'=>(int)$row['ID'],
                        'status'=>$row['STATUS'],'url'=>'/schoolsafe-receipt.php?id='.(int)$row['ID']]];
            }
            return $items;
        }
    ];
    foreach($sources as $name=>$source){
        try{$scans=array_merge($scans,$source());}
        catch(Throwable $error){$degraded[]=$name;}
    }
    // An unavailable principal-parent mapping must never broaden recipient scope.
    return SchoolSafeNotificationOrder($scans);
}
