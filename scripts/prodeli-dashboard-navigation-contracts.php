<?php
/** Real home renderer with substituted actor, school directory and reads. No mutation. */
declare(strict_types=1);
$root=getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT')?:'/var/www/html';
if(($argv[1]??'')==='--render'){
    function SchoolSafeGlobalAdmin(){return $GLOBALS['argv'][2]==='global';}
    function sskcCanRead(){return true;}
    function sskcOpeningCentral(){return false;}
    function sskcOpeningWrite(){return false;}
    function sskcEscape($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
    function UserSyear(){return 2026;}
    function UserSchool(){return 1;}
    function User($key){return $key==='STAFF_ID'?1:'admin';}
    function AllowUse($path){return true;}
    function Warehouse($part){}
    function add_action(...$args){}
    function SchoolSafeSchoolAllowed($id){return (int)$id<=10;}
    function DBGet($q){if(str_contains($q,'FROM schools ')){
        if($GLOBALS['argv'][2]==='empty')return [];
        $rows=[];for($i=1;$i<=12;$i++)$rows[]=['ID'=>$i,'TITLE'=>$i===10?'École "<test>"':'École '.$i];return $rows;
    }return [];}
    function DBQuery($q){throw new RuntimeException('Home must not write');}
    $_SESSION=['token'=>'fixture-navigation-token'];$_GET=['home'=>'1'];$_ROSARIO=[];$RosarioModules=[];
    require $root.'/plugins/SchoolSafe_Keycloak/Wizard.php';sskcHomePage();exit;
}
$pass=0;$fail=0;
function check($name,$ok){global $pass,$fail;echo ($ok?'PASS ':'FAIL ').$name."\n";$ok?$pass++:$fail++;}
$command=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' --render ';
$html=shell_exec($command.'global')??'';
check('home original brand',str_contains($html,'src="/schoolsafe-logo.png"'));
check('tenth permitted school not truncated',str_contains($html,'École &quot;&lt;test&gt;&quot;'));
check('forbidden schools excluded',!str_contains($html,'École 11')&&!str_contains($html,'École 12'));
check('directory search control',str_contains($html,'id="sskc-school-search"'));
check('directory accessible result count',str_contains($html,'id="sskc-school-search-status"')&&str_contains($html,'role="status"'));
check('guarded school context POST',str_contains($html,'method="post"')&&str_contains($html,'action="/schoolsafe-administrators.php?home=1"'));
check('explicit dashboard school action',str_contains($html,'name="action" value="dashboard_school"'));
check('native school id and CSRF fields',str_contains($html,'name="school" value="10"')&&str_contains($html,'name="token" value="fixture-navigation-token"'));
check('global central accounts reachable',str_contains($html,'href="/schoolsafe-administrators.php?accounts=central"'));
check('school directory entries identifiable',substr_count($html,'data-school-entry')===10);
check('existing communication unchanged',str_contains($html,'/plateforme/prodeli/sms')&&str_contains($html,'/plateforme/prodeli/whatsapp'));
check('no new menu',!str_contains($html,'<nav'));
check('school title escaped',!str_contains($html,'École "<test>"'));
$limited=shell_exec($command.'limited')??'';
check('school actor does not see central accounts',!str_contains($limited,'href="/schoolsafe-administrators.php?accounts=central"'));
check('school actor does not see central communication',!str_contains($limited,'id="sskc-communications-title"'));
$empty=shell_exec($command.'empty')??'';
check('empty directory explained',str_contains($empty,'Aucun établissement accessible'));
check('empty directory has no search or school switch',!str_contains($empty,'id="sskc-school-search"')&&!str_contains($empty,'value="dashboard_school"'));
$js=file_get_contents($root.'/plugins/SchoolSafe_Keycloak/central-ui.js');
check('school search registered',str_contains($js,"'#sskc-school-search'"));
check('search retains notification anchor',str_contains($js,'communications-title'));
check('search does not create network requests',!str_contains($js,'fetch('));
echo json_encode(['passed'=>$pass,'failed'=>$fail])."\n";exit($fail?1:0);
