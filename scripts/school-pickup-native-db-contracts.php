<?php
// Pure SQL-adapter contract, not HTTP, PostgreSQL or physical pickup evidence.
$root=getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT')?:dirname(__DIR__).'/app/modules/codex-ui-v1/extensions-test-runtime';
require_once $root.'/database.inc.php';
require_once $root.'/ProgramFunctions/SchoolSafePickupManage.fnc.php';
$passed=0;
foreach([null,'','   ','0000000000']as$phone){
    $insert=null;
    $db=['get'=>function($sql)use(&$insert){
        $filtered=db_sql_filter($sql);
        if(str_starts_with($sql,'INSERT INTO schoolsafe_pickup_guardians')){$insert=$filtered;return [1=>['ID'=>123]];}
        if(str_starts_with($sql,'SELECT COUNT(*)'))return [1=>['N'=>0]];
        if(str_starts_with($sql,'SELECT ID'))return [];
        return [1=>['STUDENT_ID'=>1]];
    }];
    $fields=['guardianType'=>'mere','fullName'=>'Synthetic collector','isPrimary'=>true];
    if($phone!==null)$fields['phone']=$phone;
    $result=SchoolSafePickupRegisterFamilyLocked(1,$fields,['school'=>1,'year'=>2026,'user'=>2,'profile'=>'admin'],$db);
    $expected=trim((string)$phone)===''?'DEFAULT':"'0000000000'";
    if(($result['guardian_id']??null)!=='123'||!is_string($insert)||!str_contains($insert,"'Synthetic collector',".$expected.',TRUE,TRUE,FALSE)')){
        fwrite(STDERR,"FAIL optional native phone must survive actual Rosario SQL normalization\n");exit(1);
    }
    $passed++;
}
echo json_encode(['passed'=>$passed,'failed'=>0,'scope'=>'pure SQL adapter; not HTTP/PG/camera'])."\n";
