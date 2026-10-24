<?php
// Synthetic two-school fixture; no production data or real names.
$root=dirname(__DIR__).'/app/modules/codex-ui-v1/extensions-test-runtime/ProgramFunctions';
$queries=[];$permissions=['Students/Student.php'=>true,'Users/User.php'=>true];$profile='admin';$db=[];$checks=0;
function User($key){global $profile;return $key==='PROFILE'?$profile:($key==='STAFF_ID'?1:null);}
function UserSchool(){return 3;}function UserSyear(){return 2026;}function AllowUse($key){global $permissions;return $permissions[$key]??false;}
function Config($key){return 'N';}
function DBGet($sql){global $queries,$db;$queries[]=$sql;
 if(str_contains($sql,'FROM students s'))return $db['students']??[];
 if(str_contains($sql,'FROM staff_fields'))return [['ID'=>42]];
 if(str_contains($sql,'FROM staff WHERE'))return $db['staff']??[];
 return [];
}
function check($ok,$why){global $checks;$checks++;if(!$ok)throw new RuntimeException($why);}
require_once $root.'/SchoolSafeDashboardNative.fnc.php';
$today=new DateTimeImmutable('today',new DateTimeZone('Africa/Kinshasa'));
$valid=$today->modify('-10 years')->format('Y-m-d');$future=$today->modify('+1 year')->format('Y-m-d');
$db=['students'=>[
 ['PERSON_ID'=>11,'FIRST_NAME'=>'Eleve','LAST_NAME'=>'Valide','BIRTHDATE'=>$valid,'KIND'=>'Eleve'],
 ['PERSON_ID'=>12,'FIRST_NAME'=>'Eleve','LAST_NAME'=>'DateInvalide','BIRTHDATE'=>'2010-02-30','KIND'=>'Eleve'],
 ['PERSON_ID'=>13,'FIRST_NAME'=>'Eleve','LAST_NAME'=>'Futur','BIRTHDATE'=>$future,'KIND'=>'Eleve'],
 ],'staff'=>[['PERSON_ID'=>22,'FIRST_NAME'=>'Personnel','LAST_NAME'=>'Valide','BIRTHDATE'=>$valid,'KIND'=>'Personnel']]];
$rows=SchoolSafeSchoolBirthdayTopTen(3,2026);check(count($rows)===2,'Only valid, born people appear');
check(array_column($rows,'KIND')===['Eleve','Personnel'],'Students and staff both shown');
check(str_contains(implode(' ',$queries),'e.school_id=3 AND e.syear=2026'),'Student enrollment is tenant/year-scoped');
check(str_contains(implode(' ',$queries),'current_school_id=3'),'Staff school-scoped');
$permissions['Students/Student.php']=false;$queries=[];$rows=SchoolSafeSchoolBirthdayTopTen(3,2026);
check(count($rows)===1 && $rows[0]['KIND']==='Personnel','Staff-only right never reveals students');
$html=SchoolSafeDashboardNativeWidget(false);check(str_contains($html,'Personnel Valide'),'Admin with staff-only right still sees real staff birthdays');
check(!str_contains($html,'Eleve Valide'),'Staff-only right never renders student names');
$permissions['Users/User.php']=false;$html=SchoolSafeDashboardNativeWidget(false);check(!str_contains($html,'ss-birthday-visual'),'Without both rights, birthdays are hidden');
$profile='parent';check(SchoolSafeSchoolBirthdayTopTen(3,2026)===[],'Parent cannot read school-wide birthdays');
check(SchoolSafeSchoolBirthdayTopTen(0,2026)===[],'Missing school denies all');
echo json_encode(['status'=>'PASS','checks'=>$checks],JSON_UNESCAPED_UNICODE)."\n";
