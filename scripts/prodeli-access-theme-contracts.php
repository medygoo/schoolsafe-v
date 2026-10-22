<?php
/** Isolated executable theme resolution checks, no DB/service. */
$root=getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT')?:'/candidate';
$pass=0;$fail=0;
function check($name,$ok){global $pass,$fail;echo ($ok?'PASS ':'FAIL ').$name."\n";$ok?$pass++:$fail++;}
function Config($key){return $key==='THEME'?'WPadmin':'';}
function DBGet(...$args){return ['THEME'=>[1=>['VALUE'=>'FlatSIS']]];}
function UserSyear(){return '2026';}
function URLEscape($v){return $v;}
function issetVal($v){return $v??'';}
require $root.'/functions/User.fnc.php';
foreach([[],['STAFF_ID'=>1,'User'=>['STAFF_ID'=>'1','PROFILE'=>'admin']],['STUDENT_ID'=>1],['STAFF_ID'=>2,'User'=>['STAFF_ID'=>'2','PROFILE'=>'teacher']],['STAFF_ID'=>3,'User'=>['STAFF_ID'=>'3','PROFILE'=>'parent']]] as $i=>$session){
 $_SESSION=$session+['locale'=>'fr_FR.utf8'];$_ROSARIO=['User'=>[1=>['SYEAR'=>'2026','STAFF_ID'=>(string)($session['STAFF_ID']??0)]],'Preferences'=>['Preferences'=>['THEME'=>[1=>['VALUE'=>'FlatSIS']]]]];
 check('SchoolSafe effective theme for session kind '.$i,Preferences('THEME')==='SchoolSafeCodex');
 check('Stored historical preference remains intact '.$i,$_ROSARIO['Preferences']['Preferences']['THEME'][1]['VALUE']==='FlatSIS');
}
check('Invalid preference arguments retain empty result',Preferences('THEME',false)===''&&Preferences('')==='');
check('Other preference defaults preserved',Preferences('SORT')==='Name');
$writes=[];
function DBUpsert($table,$values,$keys,$mode){$GLOBALS['writes'][]=[$table,$values,$keys];}
function DBEscapeString($v){return $v;}
function DBUnescapeString($v){return $v;}
$source=file_get_contents($root.'/functions/Config.fnc.php');
eval(substr($source,strpos($source,'function ProgramUserConfig(')));
foreach(['THEME','theme','ThEmE'] as $title){
 ProgramUserConfig('Preferences',17,[$title=>'FlatSIS','DATE'=>'%d/%m/%Y']);
}
check('Forged theme writes skipped at shared preference writer',count(array_filter($writes,fn($w)=>strcasecmp($w[2]['TITLE'],'THEME')===0))===0);
check('Other preferences remain writable',count(array_filter($writes,fn($w)=>$w[2]['TITLE']==='DATE'))===1);
$historical=ProgramUserConfig('Preferences',17);
check('Shared writer leaves historical stored theme unchanged',($historical['THEME']??'')==='FlatSIS');
require $root.'/ProgramFunctions/Theme.fnc.php';
check('Theme live update cannot reload or mutate under SchoolSafe',ThemeLiveUpdate('WPadmin','FlatSIS')===false);
$login=file_get_contents($root.'/modules/SchoolSafe/login-blue.inc.php');
check('Public login loads one access presentation',str_contains($login,'/assets/css/schoolsafe-access.css?v=1')&&!str_contains($login,"'dashboard-liquid-glass'"));
$first=file_get_contents($root.'/ProgramFunctions/FirstLogin.fnc.php');
check('First access uses the common presentation',str_contains($first,'ss-access-shell')&&str_contains($first,'schoolsafe-access.css?v=1'));
$handoff=file_get_contents($root.'/plugins/SchoolSafe_Keycloak/manage.php');
check('Confidential page uses isolated presentation',str_contains($handoff,'ss-access-shell ss-access-handoff')&&str_contains($handoff,'schoolsafe-access.css?v=1'));
check('Confidential CSP remains deny-by-default with local logo only',str_contains($handoff,"default-src 'none'; style-src 'self'; img-src 'self'; font-src 'self'; base-uri 'none'; frame-ancestors 'none'"));
$prefs=file_get_contents($root.'/modules/Users/Preferences.php');$config=file_get_contents($root.'/modules/School_Setup/Configuration.php');
check('No directory-enumerating theme selectors',!str_contains($prefs,"glob( 'assets/themes/*'")&&!str_contains($config,"glob( 'assets/themes/*'"));
foreach(['FlatSIS','SchoolSafe','SchoolSafeCodex','WPadmin'] as $theme)check('Theme foundations preserved '.$theme,is_dir($root.'/assets/themes/'.$theme));
echo json_encode(['passed'=>$pass,'failed'=>$fail])."\n";exit($fail?1:0);
