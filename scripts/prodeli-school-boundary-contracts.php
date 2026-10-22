<?php
/** Synthetic policy tests: no database, network, session secrets or real accounts. */
$root = getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT') ?: __DIR__ . '/../app/modules/codex-ui-v1/extensions-test-runtime';
$GLOBALS['actor']=['PROFILE'=>'admin','SCHOOLS'=>'','STAFF_ID'=>7];
$GLOBALS['onboarding']=[];
function User($key){return $GLOBALS['actor'][$key]??'';}
function UserSyear(){return 2026;}
function SchoolSafeAccountReady(){return true;}
function SchoolSafeAccountStates($user,$scope){return [['school_id'=>0,'state'=>$GLOBALS['state']??'active']];}
function DBGetOne($sql){return '1';}
function DBGet($sql){return $GLOBALS['onboarding']?[1=>$GLOBALS['onboarding']]:[];}
function add_action(...$args){}
require $root.'/ProgramFunctions/SchoolSafeSchoolScope.fnc.php';
require $root.'/plugins/SchoolSafe_Keycloak/Management.php';
require $root.'/ProgramFunctions/SchoolSafeNativeOnboarding.fnc.php';
$passed=0;$failed=0;
function check($name,$fn){global $passed,$failed;try{if(!$fn())throw new RuntimeException('Unexpected result');$passed++;echo "PASS $name\n";}catch(Throwable $e){$failed++;echo "FAIL $name: ".$e->getMessage()."\n";}}
foreach(['/schoolsafe-administrators.php','/admin-console.php','/plateforme-prodeli.php','/plateforme-prodeli-sms.php','/plateforme-prodeli-whatsapp.php','/index.php','/PasswordReset.php','/compte-recuperation.php'] as $script)
 check('central route retained '.$script,fn()=>SchoolSafeCentralRequestAllowed($script,[]));
foreach(['misc/Portal.php','School_Setup/Schools.php','School_Setup/Configuration.php','Users/User.php','Users/Profiles.php','Users/Exceptions.php','Students/Student.php','Grades/ReportCardGrades.php','Accounting/Incomes.php'] as $mod)
 check('central refuses native school program '.$mod,fn()=>!SchoolSafeCentralRequestAllowed('/Modules.php',['modname'=>$mod]));
check('school preparation controller retained',fn()=>SchoolSafeCentralRequestAllowed('/Modules.php',['modname'=>'School_Setup/CopySchool.php']));
check('own preferences retained',fn()=>SchoolSafeCentralRequestAllowed('/Modules.php',['modname'=>'Users/Preferences.php']));
check('central embedded native print loader refused',fn()=>!SchoolSafeCentralRequestAllowed('/schoolsafe-administrators.php',['bottomfunc'=>'print','modname'=>'Users/User.php']));
check('central embedded native help loader refused',fn()=>!SchoolSafeCentralRequestAllowed('/admin-console.php',['bottomfunc'=>'help','modname'=>'Students/Student.php']));
check('array module fails closed',fn()=>!SchoolSafeCentralRequestAllowed('/Modules.php',['modname'=>['misc/Portal.php']]));
check('school hub refused',fn()=>!SchoolSafeCentralRequestAllowed('/school-users.php',['school'=>2]));
check('unknown direct endpoint refused',fn()=>!SchoolSafeCentralRequestAllowed('/schoolsafe-homework.php',['school'=>2]));
check('central Side display retained',fn()=>SchoolSafeCentralRequestAllowed('/Side.php',[]));
check('central native school switch refused',fn()=>!SchoolSafeCentralRequestAllowed('/Side.php',['sidefunc'=>'update','school'=>'2']));
check('central native selected staff refused',fn()=>!SchoolSafeCentralRequestAllowed('/Side.php',['sidefunc'=>'update','side_staff_id'=>'5']));
check('central native selected child refused',fn()=>!SchoolSafeCentralRequestAllowed('/Side.php',['side_student_id'=>'5']));
$_SERVER['SCRIPT_NAME']='/schoolsafe-administrators.php';$_GET=['wizard'=>'1','step'=>'6','token'=>'fixture-secret','credential'=>'11','modname'=>'Users/User.php'];
check('central locale stays in its route without native module or secret',fn()=>SchoolSafeCentralLocaleUrl('fr_FR.utf8')==='/schoolsafe-administrators.php?locale=fr_FR.utf8&wizard=1&step=6');
$_SERVER['SCRIPT_NAME']='/Modules.php';
check('locale on preparation result returns to central, never customer profile',fn()=>SchoolSafeCentralLocaleUrl('fr_FR.utf8')==='/schoolsafe-administrators.php?home=1&locale=fr_FR.utf8');
$GLOBALS['actor']['SCHOOLS']=',2,';
check('school user retains own native routing subject to existing permissions',fn()=>SchoolSafeCentralRequestAllowed('/Modules.php',['modname'=>'misc/Portal.php']));
$GLOBALS['actor']['SCHOOLS']='';
$row=['STAFF_ID'=>11,'USERNAME'=>'fixture','PROFILE'=>'admin','SCHOOLS'=>',2,','LAST_LOGIN'=>'2026-10-22'];
$GLOBALS['onboarding']=['COMPLETED_AT'=>null,'VALID'=>'t'];
check('temporary login does not label account active before personal password',fn()=>sskcAccountPresentation($row)['access']==='À activer');
check('pending password remains visible despite LAST_LOGIN',fn()=>sskcAccountPresentation($row)['onboarding']==='Mot de passe personnel à créer');
$GLOBALS['onboarding']['VALID']='f';
check('expired temporary access explicitly requests renewal',fn()=>sskcAccountPresentation($row)['onboarding']==='Accès initial expiré — à renouveler');
$GLOBALS['onboarding']['COMPLETED_AT']='2026-10-22';
check('personal completion activates the displayed native account',fn()=>sskcAccountPresentation($row)['access']==='Actif' && sskcAccountPresentation($row)['onboarding']==='');
check('legacy unused scoped administrator may explicitly prepare an initial access',fn()=>SchoolSafeNativeOnboardingCanPrepare(array_replace($row,['LAST_LOGIN'=>null]),[]));
check('legacy already connected administrator cannot be reset through initial access',fn()=>!SchoolSafeNativeOnboardingCanPrepare($row,[]));
check('completed access cannot be prepared again',fn()=>!SchoolSafeNativeOnboardingCanPrepare($row,$GLOBALS['onboarding']));
check('central team is never eligible for school initial access',fn()=>!SchoolSafeNativeOnboardingCanPrepare(array_replace($row,['SCHOOLS'=>'','LAST_LOGIN'=>null]),[]));
check('multiple-school legacy account is not silently repaired',fn()=>!SchoolSafeNativeOnboardingCanPrepare(array_replace($row,['SCHOOLS'=>',2,3,','LAST_LOGIN'=>null]),[]));
check('managed identity is never converted by native preparation',fn()=>!SchoolSafeNativeOnboardingCanPrepare(array_replace($row,['SUBJECT'=>'fixture-identity','LAST_LOGIN'=>null]),[]));
$GLOBALS['onboarding']['COMPLETED_AT']=null;$GLOBALS['state']='suspended';
check('pending activation never hides a suspension',fn()=>sskcAccountPresentation($row)['access']==='Suspendu');
echo json_encode(['passed'=>$passed,'failed'=>$failed])."\n";exit($failed?1:0);
