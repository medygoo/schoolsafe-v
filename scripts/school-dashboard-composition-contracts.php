<?php
/** Real PHP renderers, substituted SELECT-only data and permissions. No database/session. */
declare(strict_types=1);
error_reporting(E_ALL);
set_error_handler(static function($level,$message,$file,$line){throw new ErrorException($message,0,$level,$file,$line);});
$root=getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT')?:'/work/php';
$profile='admin';$limited=false;$pass=0;$fail=0;
function check(string $name,bool $ok):void{global $pass,$fail;if($ok){$pass++;echo "PASS $name\n";}else{$fail++;echo "FAIL $name\n";}}
function User($key){return $key==='PROFILE'?$GLOBALS['profile']:0;}
function UserSchool(){return 81;}
function UserSyear(){return 2026;}
function AllowUse($route){return !$GLOBALS['limited']&&in_array($route,['Students/Student.php','Users/User.php','Scheduling/Courses.php','Attendance/DailySummary.php','Student_Billing/DailyTransactions.php','Accounting/DailyTransactions.php','School_Setup/PortalNotes.php','School_Setup/Configuration.php','Grades/GPARankList.php','School_Setup/Calendar.php'],true);}
function AllowEdit($route){return false;}
function ParseMLField($text){return $text;}
function SchoolInfo($key){return 'École synthétique de composition';}
function Currency($amount){return number_format((float)$amount,2,',',' ').' FC';}
function SchoolSafeDashboardNativeWidget($unused){return '<section class="ss-school-life"><h3>Vie scolaire synthétique</h3></section><section class="ss-school-life ss-birthday-visual"><details><summary>Anniversaires synthétiques</summary><ul><li>Aucune personne réelle</li></ul></details></section>';}
function SchoolSafeNativeDashboardSources(){return '<p>Sources synthétiques autorisées uniquement.</p>';}
function DBGet($sql){
 if(!preg_match('/^\s*SELECT\b/i',$sql))throw new RuntimeException('Only SELECT is allowed in this renderer test');
 if(str_contains($sql,'AS daily'))return [['DAILY'=>120,'ANNUAL'=>960]];
 if(str_contains($sql,'AS total')&&str_contains($sql,'SUM(amount)')&&!str_contains($sql,'GROUP BY'))return [['TOTAL'=>240]];
 if(str_contains($sql,'COUNT(*) AS total'))return [['TOTAL'=>4]];
 if(str_contains($sql,'AS month'))return [['MONTH'=>'2026-10','TOTAL'=>240]];
 if(str_contains($sql,'AS title,SUM(p.amount)'))return [['TITLE'=>'Fournitures synthétiques','TOTAL'=>80],['TITLE'=>'Entretien synthétique','TOTAL'=>160]];
 if(str_contains($sql,'AS attendance_month'))return [['DAY'=>'2026-10-01','KNOWN'=>20,'PRESENT'=>18],['DAY'=>'2026-10-02','KNOWN'=>20,'PRESENT'=>19]];
 return [];
}
require $root.'/ProgramFunctions/SchoolSafePhotoDashboard.fnc.php';
$_GET=['details_tab'=>'activities'];$RosarioModules=[];
$data=['total'=>42,'present'=>18,'absent'=>2,'partial'=>0,'classes'=>[[],[],[]],'gender'=>[['IS_TOTAL'=>1,'GIRLS'=>22,'BOYS'=>20,'TOTAL'=>42]]];
$feed=['activities'=>[['TITLE'=>'Annonce synthétique','CREATED_AT'=>'2026-10-01 08:00:00']],'events'=>[['TITLE'=>'Réunion synthétique','SCHOOL_DATE'=>'2026-10-02']]];
function renderComposition($data,$feed){ob_start();SchoolSafePhotoDashboard($data,$feed,'');return ob_get_clean();}
$html=renderComposition($data,$feed);
check('real renderer outputs one dashboard',substr_count($html,'id="ss-photo-dashboard"')===1);
check('welcome and authorized configuration retained',str_contains($html,'École synthétique de composition')&&str_contains($html,'href="/Modules.php?modname=School_Setup/Configuration.php"'));
check('school values, not reference illustration figures',str_contains($html,'>42</strong>')&&str_contains($html,'22 filles · 20 garçons')&&!str_contains($html,'2 547'));
check('all six authorized indicators retained',substr_count($html,'<a class="ss-photo-metric"')===6);
check('all eight authorized module branches retained',substr_count($html,'data-open-native-branch=')===8);
check('attendance SVG is actual renderer output',str_contains($html,'18:')===false&&str_contains($html,'Présences enregistrées du mois')&&str_contains($html,'95%'));
check('financial categories use the existing chart renderer',str_contains($html,'Fournitures synthétiques')&&str_contains($html,'ss-expense-breakdown'));
check('cash summary follows modules and charts',strpos($html,'class="ss-closing-finance"')>strpos($html,'class="ss-photo-charts"'));
check('all four cash summary periods retained',str_contains($html,'Encaissements du jour')&&str_contains($html,'Encaissements du mois')&&str_contains($html,'Depenses')&&str_contains($html,'Recettes'));
check('school life and alerts retained',str_contains($html,'ss-photo-school-life')&&str_contains($html,'id="ss-photo-alerts"')&&str_contains($html,'Absences enregistrées'));
check('complete server detail body remains available',str_contains($html,'ss-detail-panel')&&str_contains($html,'Réunion synthétique')&&str_contains($html,'details_tab=finance'));
check('no female companion stage or embedded engine',!str_contains($html,'ss-photo-jaspe-stage')&&!str_contains($html,'live-companion'));
check('birthdays appear once, open, above modules',substr_count($html,'class="ss-school-life ss-birthday-visual"')===1&&strpos($html,'id="ss-photo-birthdays"')<strpos($html,'id="ss-photo-rubriques"')&&str_contains($html,'<details open="">'));
check('no server pager or hidden content parking',!str_contains($html,'ss-fixed-pager')&&!str_contains($html,'ss-fixed-park'));
check('GET state restored after real nested render',$_GET===['details_tab'=>'activities']);
if(getenv('SCHOOLSAFE_COMPOSITION_HTML'))file_put_contents(getenv('SCHOOLSAFE_COMPOSITION_HTML'),$html);
$limited=true;$restricted=renderComposition($data,$feed);
check('restricted account has no finance or personnel module',!str_contains($restricted,'data-open-native-branch="finance"')&&!str_contains($restricted,'data-open-native-branch="people"'));
check('restricted account gains no configuration action',!str_contains($restricted,'href="/Modules.php?modname=School_Setup/Configuration.php"'));
foreach(['teacher','parent','student'] as $role){$profile=$role;check($role.' dashboard presentation untouched',renderComposition($data,$feed)==='');}
echo json_encode(['passed'=>$pass,'failed'=>$fail])."\n";exit($fail?1:0);
