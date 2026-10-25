<?php
// Read the EXISTING Food_Service shared account. This is NOT a meal service,
// tuition ledger, school allocation, payment receipt or admission permission.
require_once __DIR__.'/SchoolSafeFeeSnapshot.fnc.php';

function SchoolSafeCanteenAccountResult(array $rows,string $name,bool $detail=false,?string $currency=null): array
{
    $base=['result'=>'CANTEEN_STATUS','student_name'=>$name,'read_only'=>true,'balance_scope'=>'native_food_service_account'];
    if(!$rows)return $base+['status'=>'no_account','label'=>'Aucun compte cantine renseigné.'];
    if(count($rows)!==1)throw new RuntimeException('NATIVE_CANTEEN_UNAVAILABLE');
    $row=reset($rows);
    if(!isset($row['ACCOUNT_ID'],$row['BALANCE'])||!ctype_digit((string)$row['ACCOUNT_ID'])||(int)$row['ACCOUNT_ID']<1)
        return $base+['status'=>'no_data','label'=>'Données du compte cantine indisponibles.'];
    if(!is_string($row['BALANCE'])||!preg_match('/^-?[0-9]+(?:\.[0-9]{1,2})?$/D',$row['BALANCE']))throw new RuntimeException('NATIVE_CANTEEN_UNAVAILABLE');
    // Native Food_Service treats NULL/blank status as active (Students/Accounts).
    $active=['','Active'];if(function_exists('_'))$active[]=_( 'Active' );
    if(!in_array((string)($row['STATUS']??''),$active,true))return $base+['status'=>'inactive','label'=>'Compte cantine inactif.'];
    if($currency===null||trim($currency)===''||preg_match('/^.{1,32}$/usD',$currency)!==1)
        return $base+['status'=>'currency_unavailable','label'=>'Devise du compte cantine indisponible. Vérification requise ; aucun repas débité.'];
    $cents=SchoolSafeFeeCents($row['BALANCE']);$status=$cents>0?'credit_available':($cents<0?'negative_balance':'zero_balance');
    $labels=['credit_available'=>'Crédit cantine disponible','negative_balance'=>'Solde cantine négatif','zero_balance'=>'Aucun crédit cantine disponible'];
    $result=$base+['status'=>$status,'label'=>$labels[$status].'. Vérification seulement ; aucun repas débité.'];
    // Financial detail is a separate EXISTING native account-read permission.
    if($detail){
        $result['balance']=SchoolSafeFeeFormatCents($cents);$result['currency']=$currency;
        $result['label']=$labels[$status].' : '.$result['balance'].' '.$currency.'. Solde du compte natif, non ventilé par école ; aucun repas débité.';
    }
    return $result;
}

function SchoolSafeCanteenScanRead(int $student,int $school,int $year,array $db=[],bool $detail=false): array
{
    global $RosarioModules;
    if(empty($RosarioModules['Food_Service'])||!SchoolSafeNativeCommandPermission('SchoolSafe/ScanCanteen.php'))throw new RuntimeException('NATIVE_PERMISSION_REQUIRED');
    if($student<1||$school<1||$year<1)throw new RuntimeException('INVALID_INPUT');
    $get=$db['get']??static fn($sql)=>DBGet($sql);
    $today=(new DateTimeImmutable('today',new DateTimeZone('Africa/Kinshasa')))->format('Y-m-d');
    $people=$get("SELECT s.first_name,s.last_name FROM students s WHERE s.student_id=$student
        AND EXISTS(SELECT 1 FROM student_enrollment e WHERE e.student_id=s.student_id
        AND e.school_id=$school AND e.syear=$year AND e.start_date<='$today'
        AND (e.end_date IS NULL OR e.end_date>='$today'))");
    if(!is_array($people))throw new RuntimeException('NATIVE_CANTEEN_UNAVAILABLE');
    if(count($people)!==1)throw new RuntimeException('NATIVE_STUDENT_NOT_ACCESSIBLE');
    $person=reset($people);
    $rows=$get("SELECT a.account_id,a.status,b.balance FROM food_service_student_accounts a
        LEFT JOIN food_service_accounts b ON b.account_id=a.account_id WHERE a.student_id=$student");
    if(!is_array($rows))throw new RuntimeException('NATIVE_CANTEEN_UNAVAILABLE');
    $metadata=SchoolSafeFeeControlMetadata(['school'=>$school,'year'=>$year],$get);
    return SchoolSafeCanteenAccountResult(array_values($rows),$person['FIRST_NAME'].' '.$person['LAST_NAME'],$detail,$metadata['currency']);
}

function SchoolSafeCanteenScan($qr,$secretHex,array $ctx,array $db): ?array
{
    global $RosarioModules;
    if(empty($RosarioModules['Food_Service'])||!SchoolSafeNativeCommandPermission('SchoolSafe/ScanCanteen.php'))throw new RuntimeException('NATIVE_PERMISSION_REQUIRED');
    $school=(int)($ctx['school']??0);$year=(int)($ctx['year']??0);
    if(min($school,$year,(int)($ctx['user']??0))<1)throw new RuntimeException('INVALID_CONTEXT');
    require_once __DIR__.'/SchoolSafeCardVerify.fnc.php';
    $query=static fn($number,$scope)=>SchoolSafeCardVerifyQuery($number,$scope,$year,$db['get']);
    $verified=SchoolSafeCardVerifyQr($qr,$secretHex,$school,$year,$query);
    if($verified===null||($verified['result']??null)!=='VALID')return $verified;
    $account=SchoolSafeCanteenScanRead((int)$verified['student_id'],$school,$year,$db,($ctx['detail']??false)===true);
    return array_replace($verified,$account);
}
