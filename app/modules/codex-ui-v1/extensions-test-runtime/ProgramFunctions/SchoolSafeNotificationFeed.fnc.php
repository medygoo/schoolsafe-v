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
        'remediation'=>static fn()=>SchoolSafeRemediationParentInbox($principal,$ids,$user,$school,$year),
        'birthdays'=>static fn()=>SchoolSafeBirthdayParentInbox($principal,$ids,$school,$year),
        'palmares'=>static fn()=>SchoolSafePalmaresParentInbox(),
        'receipts'=>static function()use($user,$school,$year){
            require_once __DIR__.'/SchoolSafeReceipts.fnc.php';
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
