<?php
// Internal native registry owner. Public callers must retain native bootstrap,
// school/student visibility, command permission and CSRF checks.
function SchoolSafeNativeCardRegistryPresent(array $db): bool
{
    $rows=$db['get']("SELECT to_regclass('public.schoolsafe_student_cards') AS T");
    if(!is_array($rows)||count($rows)!==1||!array_key_exists('T',$rows[1]))throw new RuntimeException('CARD_REGISTRY_UNAVAILABLE');
    if($rows[1]['T']===null)return false;
    if($rows[1]['T']!=='schoolsafe_student_cards')throw new RuntimeException('CARD_REGISTRY_UNAVAILABLE');
    return true;
}

function SchoolSafeNativeCardCredential(array $rows,string $secret): array
{
    $active=[];
    foreach($rows as$row){
        if(!in_array($row['STATUS']??null,['active','lost','replaced','revoked'],true))throw new RuntimeException('CARD_REGISTRY_UNAVAILABLE');
        if($row['STATUS']==='active')$active[]=$row;
    }
    if(count($active)>1)throw new RuntimeException('CARD_REGISTRY_AMBIGUOUS');
    if(!$active)return [];
    if(!preg_match('/^[a-f0-9]{64}$/D',$secret))throw new RuntimeException('CARD_KEY_UNAVAILABLE');
    $row=$active[0];$number=$row['CARD_NUMBER']??'';$signature=$row['SIGNATURE']??'';
    if(!is_string($number)||!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$number)||!is_string($signature))throw new RuntimeException('CARD_REGISTRY_UNAVAILABLE');
    $expected=substr(strtr(base64_encode(hash_hmac('sha256',$number,hex2bin($secret),true)),'+/','-_'),0,32);
    if(!hash_equals($expected,$signature))throw new RuntimeException('CARD_KEY_MISMATCH');
    return ['card_number'=>$number,'signature'=>$signature,'status'=>'active','qr_payload'=>'schoolsafe://card/'.$number.'/'.$signature];
}

function SchoolSafeNativeCardRegister(int $student,array $ctx,string $secret,array $db,callable $authorize): array
{
    $school=(int)($ctx['school']??0);$year=(int)($ctx['year']??0);$user=(int)($ctx['user']??0);
    if(min($student,$school,$year,$user)<1)throw new RuntimeException('INVALID_NATIVE_CONTEXT');
    if(!preg_match('/^[a-f0-9]{64}$/D',$secret))throw new RuntimeException('CARD_KEY_UNAVAILABLE');
    if(!SchoolSafeNativeCardRegistryPresent($db))throw new RuntimeException('CARD_REGISTRY_UNAVAILABLE');
    $db['exec']('BEGIN');
    try{
        if($authorize()!==true)throw new RuntimeException('NATIVE_ACCESS_DENIED');
        // Serialize issuance by the existing native child, not a second identity.
        $rows=$db['get']('SELECT s.STUDENT_ID FROM students s WHERE s.STUDENT_ID='.$student
            .' AND EXISTS(SELECT 1 FROM student_enrollment e JOIN schools sc ON sc.ID=e.SCHOOL_ID AND sc.SYEAR=e.SYEAR'
            .' WHERE e.STUDENT_ID=s.STUDENT_ID AND e.SCHOOL_ID='.$school.' AND e.SYEAR='.$year.') FOR UPDATE');
        if(!is_array($rows)||count($rows)!==1||(int)($rows[1]['STUDENT_ID']??0)!==$student)throw new RuntimeException('NATIVE_STUDENT_NOT_ACCESSIBLE');
        $rows=$db['get']('SELECT CARD_NUMBER,SIGNATURE,STATUS FROM schoolsafe_student_cards WHERE SCHOOL_ID='.$school.' AND STUDENT_ID='.$student.' FOR UPDATE');
        if(!is_array($rows))throw new RuntimeException('CARD_REGISTRY_UNAVAILABLE');
        $credential=SchoolSafeNativeCardCredential($rows,$secret);
        if($authorize()!==true)throw new RuntimeException('NATIVE_ACCESS_DENIED');
        if($credential){$db['exec']('COMMIT');return ['result'=>'ALREADY_ISSUED','operationPerformed'=>false,'data'=>['credential'=>$credential]];}
        if($rows)throw new RuntimeException('CARD_NOT_ACTIVE');
        $number=bin2hex(random_bytes(16));
        $signature=substr(strtr(base64_encode(hash_hmac('sha256',$number,hex2bin($secret),true)),'+/','-_'),0,32);
        $written=$db['get']("INSERT INTO schoolsafe_student_cards(school_id,student_id,card_number,signature) VALUES(".$school.','.$student.",'".$number."','".$signature."') RETURNING CARD_NUMBER,SIGNATURE,STATUS");
        if(!is_array($written)||count($written)!==1)throw new RuntimeException('CARD_REGISTRY_UNAVAILABLE');
        $credential=SchoolSafeNativeCardCredential($written,$secret);
        if(($credential['card_number']??null)!==$number)throw new RuntimeException('CARD_REGISTRY_UNAVAILABLE');
        $db['exec']('COMMIT');
        return ['result'=>'ISSUED','operationPerformed'=>true,'data'=>['credential'=>$credential]];
    }catch(Throwable$error){$db['exec']('ROLLBACK');throw $error;}
}

function SchoolSafeNativeCardMayIssue(array $db): bool
{
    if(!function_exists('SchoolSafeNativeCommandPermission')||User('PROFILE')!=='admin'||SchoolSafeGlobalAdmin())return false;
    $school=(int)UserSchool();$year=(int)UserSyear();$user=(int)User('STAFF_ID');
    if(min($school,$year,$user)<1)return false;
    $rows=$db['get']('SELECT STAFF_ID,USERNAME,PROFILE,PROFILE_ID,SCHOOLS FROM staff WHERE STAFF_ID='.$user.' AND SYEAR='.$year);
    if(!is_array($rows)||count($rows)!==1)return false;
    $row=$rows[1];$scope=SchoolSafeScopeParse($row['SCHOOLS']??null);
    if($scope===null||!SchoolSafeScopeContains($scope,$school)||($row['PROFILE']??null)!=='admin'
        ||(string)($row['PROFILE_ID']??'')!==(string)User('PROFILE_ID')||($row['USERNAME']??null)!==User('USERNAME'))return false;
    require_once __DIR__.'/SchoolSafeNotifyRecipient.fnc.php';
    if(!SchoolSafeNotifyAccountReadable($row['USERNAME'],$school,$db))return false;
    SchoolSafeAccountForget($row['USERNAME']);
    return !SchoolSafeAccountBlocksWrite()&&SchoolSafeNativeCommandPermission('SchoolSafe/IssueCard.php');
}
