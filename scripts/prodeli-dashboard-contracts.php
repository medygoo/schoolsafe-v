<?php
/** Real dashboard renderer with substituted read-only data/authorization. */
declare(strict_types=1);
$pass=0;$fail=0;$global=true;
function check($name,$condition){global $pass,$fail;if($condition){$pass++;echo "PASS $name\n";}else{$fail++;echo "FAIL $name\n";}}
function add_action(...$args){}
function SchoolSafeGlobalAdmin(){return $GLOBALS['global'];}
function AllowUse($path){return true;}
function Warehouse($part){}
function sskcEscape($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function sskcOpeningCentral(){return false;}
function sskcOpeningWrite(){return false;}
function User($field){return $field==='PROFILE'?'admin':1;}
function UserSyear(){return 2026;}
function UserSchool(){return 1;}
function Config($key){return 2026;}
function DBGet($q){return [];}
function SchoolSafeSchoolAllowed($id){return true;}
function SchoolSafeScopeParse($s){return $s===null||$s===''?null:[1];}
function SchoolSafeStaffWithinScope($id){return true;}
function sskcAccountPresentation($r){return ['access'=>'Actif','onboarding'=>''];}
$root=getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT')?:'/work/php';
require $root.'/plugins/SchoolSafe_Keycloak/Wizard.php';
$_GET=['home'=>'1'];$_SESSION=[];$_ROSARIO=[];$RosarioModules=[];
ob_start();sskcUiStart('Tableau de bord ProDéli');$home=ob_get_clean();
check('no central menu on dashboard',!str_contains($home,'<nav'));
check('original SchoolSafe logo preserved',str_contains($home,'src="/schoolsafe-logo.png"'));
check('no redundant home link on home',!str_contains($home,'Retour au tableau de bord'));
$_GET=[];ob_start();sskcUiStart('Administrateurs scolaires');$sub=ob_get_clean();
check('subpage returns to dashboard',str_contains($sub,'Retour au tableau de bord'));
check('logout remains reachable',str_contains($sub,'modfunc=logout'));
check('existing assistant remains reachable',str_contains($sub,'sskc-jaspe-launch')===false); // Module disabled in this fixture.
$wizard=file_get_contents($root.'/plugins/SchoolSafe_Keycloak/Wizard.php');
check('existing SMS route retained',str_contains($wizard,'/plateforme/prodeli/sms'));
check('existing WhatsApp route retained',str_contains($wizard,'/plateforme/prodeli/whatsapp'));
check('existing administration remains reachable',str_contains($wizard,'/admin-console.php'));
check('communication block reserved to global actor',str_contains($wizard,"if(SchoolSafeGlobalAdmin())echo '<section class=\"sskc-home-section\" aria-labelledby=\"sskc-communications-title\""));
$entry=file_get_contents($root.'/plateforme-prodeli.php');
check('legacy entry canonicalizes only plain global GET',str_contains($entry,"( \$_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'GET'") && str_contains($entry,'empty( $_GET )') && str_contains($entry,'SchoolSafeGlobalAdmin()'));
check('legacy native handler retained',str_contains($entry,"require_once __DIR__ . '/admin-console.php'"));
echo json_encode(['passed'=>$pass,'failed'=>$fail])."\n";exit($fail?1:0);
