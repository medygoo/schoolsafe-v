<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if($_SERVER['REQUEST_METHOD']!=='GET'||$_GET||$_POST){http_response_code(400);exit('{}');}
chdir(__DIR__); $_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();
try {
    require_once 'Warehouse.php';
    if(!User('STAFF_ID')||User('PROFILE')!=='parent'||!AllowUse('Grades/GPARankList.php')) {
        http_response_code(403);$result=['error'=>'PARENT_ACCESS_REQUIRED'];
    } else {
        require_once 'ProgramFunctions/SchoolSafePalmaresAwards.fnc.php';
        $badges=[];
        foreach(SchoolSafePalmaresParentAwards() as $row) $badges[]=[
            'id'=>(string)$row['ID'],'student_name'=>SchoolSafePalmaresFullName($row),
            'scope'=>(int)$row['SCOPE_ID']===0?'ecole':'classe','class'=>ParseMLField($row['CLASS_TITLE']??''),
            'period'=>ParseMLField($row['PERIOD_TITLE'])];
        $result=['badges'=>$badges,'notifications'=>SchoolSafePalmaresParentInbox()];
    }
} catch(Throwable $error){http_response_code(503);$result=['error'=>'PALMARES_UNAVAILABLE'];}
while(ob_get_level()>$level)ob_end_clean();
echo json_encode($result,JSON_THROW_ON_ERROR);
