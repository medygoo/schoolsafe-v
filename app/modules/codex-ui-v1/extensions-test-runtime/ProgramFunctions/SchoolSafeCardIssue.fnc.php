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
    return ['card_id'=>$number,'card_number'=>$number,'signature'=>$signature,'status'=>'active','qr_payload'=>'schoolsafe://card/'.$number.'/'.$signature];
}

// Duplicata et annulation (03/11). L'ancienne carte change d'etat (refusee au portail) et, sauf annulation,
// une nouvelle carte active est emise dans la meme transaction. reissue = nouvelle carte apres une annulation.
function SchoolSafeNativeCardChange(int $student,string $reason,array $ctx,string $secret,array $db,callable $authorize): array
{
    $states=['lost'=>'lost','stolen'=>'revoked','damaged'=>'replaced','cancel'=>'revoked','reissue'=>null];
    if(!array_key_exists($reason,$states))throw new RuntimeException('INVALID_CARD_CHANGE');
    $school=(int)($ctx['school']??0);$year=(int)($ctx['year']??0);$user=(int)($ctx['user']??0);
    if(min($student,$school,$year,$user)<1)throw new RuntimeException('INVALID_NATIVE_CONTEXT');
    if(!preg_match('/^[a-f0-9]{64}$/D',$secret))throw new RuntimeException('CARD_KEY_UNAVAILABLE');
    $db['exec']('BEGIN');
    try{
        if($authorize()!==true)throw new RuntimeException('NATIVE_ACCESS_DENIED');
        $rows=$db['get']('SELECT s.STUDENT_ID FROM students s WHERE s.STUDENT_ID='.$student
            .' AND EXISTS(SELECT 1 FROM student_enrollment e WHERE e.STUDENT_ID=s.STUDENT_ID AND e.SCHOOL_ID='.$school.' AND e.SYEAR='.$year.') FOR UPDATE');
        if(!is_array($rows)||count($rows)!==1)throw new RuntimeException('NATIVE_STUDENT_NOT_ACCESSIBLE');
        $cards=$db['get']('SELECT CARD_NUMBER,SIGNATURE,STATUS FROM schoolsafe_student_cards WHERE SCHOOL_ID='.$school.' AND STUDENT_ID='.$student.' FOR UPDATE');
        if(!is_array($cards))throw new RuntimeException('CARD_REGISTRY_UNAVAILABLE');
        $active=SchoolSafeNativeCardCredential($cards,$secret);
        if($reason==='reissue'){if($active||!$cards)throw new RuntimeException('CARD_NOT_ACTIVE');}
        else{
            if(!$active)throw new RuntimeException('CARD_NOT_ACTIVE');
            $changed=$db['get']("UPDATE schoolsafe_student_cards SET STATUS='".$states[$reason]."',STATUS_REASON='".$reason."',STATUS_CHANGED_BY=".$user
                .",STATUS_CHANGED_AT=CURRENT_TIMESTAMP,UPDATED_AT=CURRENT_TIMESTAMP WHERE SCHOOL_ID=".$school.' AND STUDENT_ID='.$student
                ." AND STATUS='active' AND CARD_NUMBER='".$active['card_number']."' RETURNING CARD_NUMBER");
            if(!is_array($changed)||count($changed)!==1)throw new RuntimeException('CARD_REGISTRY_UNAVAILABLE');
        }
        $credential=[];
        if($reason!=='cancel'){
            $number=bin2hex(random_bytes(16));
            $signature=substr(strtr(base64_encode(hash_hmac('sha256',$number,hex2bin($secret),true)),'+/','-_'),0,32);
            $written=$db['get']("INSERT INTO schoolsafe_student_cards(school_id,student_id,card_number,signature) VALUES(".$school.','.$student.",'".$number."','".$signature."') RETURNING CARD_NUMBER,SIGNATURE,STATUS");
            $credential=is_array($written)?SchoolSafeNativeCardCredential($written,$secret):[];
            if(($credential['card_number']??null)!==$number)throw new RuntimeException('CARD_REGISTRY_UNAVAILABLE');
        }
        if($authorize()!==true)throw new RuntimeException('NATIVE_ACCESS_DENIED');
        $db['exec']('COMMIT');
        return ['result'=>$reason==='cancel'?'CANCELLED':'REPLACED','operationPerformed'=>true,'data'=>['credential'=>$credential]];
    }catch(Throwable$error){$db['exec']('ROLLBACK');throw $error;}
}

// Design du Studio enregistre pour l'ecole et l'annee (table native program_config), avec verrou.
function SchoolSafeCardDesignRead(int $school,int $year,array $db): array
{
    $rows=$db['get']("SELECT TITLE,VALUE FROM program_config WHERE PROGRAM='schoolsafe_card_studio' AND SCHOOL_ID=".$school.' AND SYEAR='.$year);
    $design=[];$locked=false;
    foreach((array)$rows as$row){
        if($row['TITLE']==='DESIGN'){$value=json_decode((string)$row['VALUE'],true);if(is_array($value))$design=$value;}
        if($row['TITLE']==='LOCKED')$locked=$row['VALUE']==='Y';
    }
    return ['design'=>$design,'locked'=>$locked];
}

function SchoolSafeCardDesignWrite(int $school,int $year,?array $design,?bool $lock,array $db): array
{
    $current=SchoolSafeCardDesignRead($school,$year,$db);
    if($design!==null){
        if($current['locked'])throw new RuntimeException('CARD_DESIGN_LOCKED');
        foreach($design as$key=>$value)if(!is_string($key)||!preg_match('/^[a-z0-9:_-]{1,120}$/iD',$key)||!is_string($value))throw new RuntimeException('INVALID_CARD_DESIGN');
        $json=json_encode($design,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if(!is_string($json)||strlen($json)>200000)throw new RuntimeException('INVALID_CARD_DESIGN');
        SchoolSafeCardDesignPut($school,$year,'DESIGN',$json,$db);
    }
    if($lock!==null)SchoolSafeCardDesignPut($school,$year,'LOCKED',$lock?'Y':'',$db);
    return SchoolSafeCardDesignRead($school,$year,$db);
}

function SchoolSafeCardDesignPut(int $school,int $year,string $title,string $value,array $db): void
{
    $where=" WHERE PROGRAM='schoolsafe_card_studio' AND TITLE='".$title."' AND SCHOOL_ID=".$school.' AND SYEAR='.$year;
    $sql="'".str_replace("'","''",$value)."'";
    $updated=$db['get']('UPDATE program_config SET VALUE='.$sql.',UPDATED_AT=CURRENT_TIMESTAMP'.$where.' RETURNING TITLE');
    if(is_array($updated)&&count($updated)===1)return;
    $db['get']("INSERT INTO program_config(SYEAR,SCHOOL_ID,PROGRAM,TITLE,VALUE) VALUES(".$year.','.$school.",'schoolsafe_card_studio','".$title."',".$sql.') RETURNING TITLE');
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
