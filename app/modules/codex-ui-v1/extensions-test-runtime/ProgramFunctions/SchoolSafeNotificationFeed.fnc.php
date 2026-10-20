<?php
function SchoolSafeNotificationPriority(array $item): int
{
    $type=strtolower((string)($item['event_type']??''));
    if(preg_match('/security|sos|arrival|departure|entry|exit|pickup/', $type)) return 0;
    if(preg_match('/payment|receipt|billing/', $type)) return 1;
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
function SchoolSafeNotificationFeed(array $scans,array $principal,array $ids,int $user,int $school,int $year,array &$degraded): array
{
    require_once __DIR__.'/../schoolsafe-remediation/ParentInbox.fnc.php';
    require_once __DIR__.'/SchoolSafeDashboardNative.fnc.php';
    $sources=[
        'arrivals'=>static function()use($ids,$school,$year){
            // Lot ENTRY : arrivées scannées des enfants, sans le bridge.
            if(!$ids)return[];
            $in=implode(',',array_map('intval',$ids));
            $rows=DBGet('SELECT r.ID, r.STUDENT_ID, s.FIRST_NAME, s.LAST_NAME, r.RECORDED_AT, c.TITLE'
                . ' FROM entry_exit_student_records r JOIN students s ON s.STUDENT_ID=r.STUDENT_ID'
                . ' LEFT JOIN entry_exit_checkpoints c ON c.ID=r.CHECKPOINT_ID'
                . ' WHERE r.STUDENT_ID IN ('.$in.") AND r.TYPE='in' AND r.UNAUTHORIZED IS NULL"
                . ' AND r.RECORDED_AT>=CURRENT_TIMESTAMP - INTERVAL \'7 days\''
                . ' ORDER BY r.RECORDED_AT DESC LIMIT 20');
            $items=[];
            if(is_array($rows))foreach($rows as $row){
                if(!isset($row['ID'],$row['STUDENT_ID'],$row['RECORDED_AT']))continue;
                $at=substr($row['RECORDED_AT'],0,16);
                $items[]=['id'=>'arrival:'.$row['ID'],'student_id'=>(string)$row['STUDENT_ID'],
                    'student_name'=>trim($row['FIRST_NAME'].' '.$row['LAST_NAME']),
                    'event_type'=>'security.entry','created_at'=>$row['RECORDED_AT'],
                    'payload'=>['message'=>'Arrivée enregistrée à '.$at
                        .(!empty($row['TITLE'])?' — '.$row['TITLE']:''),'record_id'=>(int)$row['ID']]];
            }
            return $items;
        },
        'departures'=>static function()use($ids){
            // Lot PICKUP : sorties confirmées des enfants, sans le bridge.
            if(!$ids)return[];
            $in=implode(',',array_map('intval',$ids));
            $rows=DBGet('SELECT r.ID, r.STUDENT_ID, s.FIRST_NAME, s.LAST_NAME, r.RECORDED_AT, r.COMMENTS'
                . ' FROM entry_exit_student_records r JOIN students s ON s.STUDENT_ID=r.STUDENT_ID'
                . ' WHERE r.STUDENT_ID IN ('.$in.") AND r.TYPE='out' AND r.UNAUTHORIZED IS NULL"
                . ' AND r.RECORDED_AT>=CURRENT_TIMESTAMP - INTERVAL \'7 days\''
                . ' ORDER BY r.RECORDED_AT DESC LIMIT 20');
            $items=[];
            if(is_array($rows))foreach($rows as $row){
                if(!isset($row['ID'],$row['STUDENT_ID'],$row['RECORDED_AT']))continue;
                $at=substr($row['RECORDED_AT'],0,16);
                $items[]=['id'=>'departure:'.$row['ID'],'student_id'=>(string)$row['STUDENT_ID'],
                    'student_name'=>trim($row['FIRST_NAME'].' '.$row['LAST_NAME']),
                    'event_type'=>'student.pickup','created_at'=>$row['RECORDED_AT'],
                    'payload'=>['message'=>'Sortie confirmée à '.$at,'record_id'=>(int)$row['ID']]];
            }
            return $items;
        },
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
