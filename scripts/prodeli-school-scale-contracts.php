<?php
/** Focused authorization/input/controller contracts, with DB/auth substitutes. */
$root=getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT') ?: dirname(__DIR__).'/app/modules/codex-ui-v1/extensions-test-runtime';
$passed=0;$failed=0;
function check($name,$ok){global $passed,$failed;echo ($ok?'PASS ':'FAIL ').$name."\n";$ok?$passed++:$failed++;}
$wizard=file_get_contents($root.'/plugins/SchoolSafe_Keycloak/Wizard.php');
check('creation no longer asks for a grading base',!str_contains($wizard,"['REPORTING_GP_SCALE'=>['Base de notation'"));
check('creation preserves the native template scale',!str_contains($wizard,"'PRINCIPAL','REPORTING_GP_SCALE'"));
check('central review no longer asks for the school academic setting',!str_contains($wizard,"'Base de notation'=>"));
$helper=$root.'/modules/Grades/includes/SchoolScale.fnc.php';
check('school academic scale controller exists',is_file($helper));
if(!is_file($helper)){echo json_encode(['passed'=>$passed,'failed'=>$failed])."\n";exit(1);}
function User($key){return $GLOBALS['profile'];}
function UserSchool(){return 2;}
function UserSyear(){return $GLOBALS['year'];}
function Config($key){return 2026;}
function SchoolSafeSchoolAllowed($id){return $GLOBALS['scope'];}
function AllowUse($route){return $GLOBALS['use'] && $route==='Grades/ReportCardGrades.php';}
function AllowEdit($route=false){return $GLOBALS['edit'] && $route==='Grades/ReportCardGrades.php';}
function DBGetOne($q){return $GLOBALS['stored'];}
function DBUpdate($table,$values,$where){$GLOBALS['writes'][]=[$table,$values,$where];$GLOBALS['stored']=$values['REPORTING_GP_SCALE'];return true;}
function UpdateSchoolArray($id){$GLOBALS['refreshed']=$id;}
require $helper;
function resetCase(){$_SERVER['REQUEST_METHOD']='POST';$_SESSION=['token'=>'fixture-only-csrf'];$GLOBALS['profile']='admin';$GLOBALS['year']=2026;$GLOBALS['scope']=true;$GLOBALS['use']=true;$GLOBALS['edit']=true;$GLOBALS['stored']=100;$GLOBALS['writes']=[];$GLOBALS['refreshed']=null;}
function rejected($post){try{SchoolSafeSchoolScaleSave($post);return false;}catch(RuntimeException $e){return count($GLOBALS['writes'])===0;}}
$valid=['token'=>'fixture-only-csrf','school_scale'=>'20','school_scale_confirm'=>'1'];
resetCase();SchoolSafeSchoolScaleSave($valid+['school_id'=>'999','syear'=>'2025','TITLE'=>'forged']);
check('save changes only the authorized school/year and scale',$GLOBALS['writes']===[['schools',['REPORTING_GP_SCALE'=>20],['ID'=>2,'SYEAR'=>2026]]] && $GLOBALS['refreshed']===2);
foreach(['use','edit','scope'] as $flag){resetCase();$GLOBALS[$flag]=false;check($flag.' refusal writes nothing',rejected($valid));}
resetCase();$GLOBALS['profile']='teacher';check('teacher gradebook editing does not grant school-wide scale editing',rejected($valid));
resetCase();$GLOBALS['year']=2025;check('historical school year cannot be modified',rejected($valid));
resetCase();$_SERVER['REQUEST_METHOD']='GET';check('GET cannot change the base',rejected($valid));
resetCase();check('forged CSRF cannot change the base',rejected(array_replace($valid,['token'=>'wrong'])));
resetCase();check('confirmation required',rejected(array_replace($valid,['school_scale_confirm'=>'0'])));
foreach(['0','10001','20.5','-1','20 OR 1=1',[],null] as $bad){resetCase();check('invalid base rejected',rejected(array_replace($valid,['school_scale'=>$bad])));}
$menu=file_get_contents($root.'/modules/Grades/Menu.php');
check('native pedagogy menu identifies the setting',str_contains($menu,'Base de notation et échelles'));
$grades=file_get_contents($root.'/modules/Grades/ReportCardGrades.php');
check('existing grading program displays the school setting',str_contains($grades,'SchoolSafeSchoolScalePanel();'));
echo json_encode(['passed'=>$passed,'failed'=>$failed])."\n";exit($failed?1:0);
