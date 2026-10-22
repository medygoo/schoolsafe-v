<?php
/** Landing controller + shared workspace rendering. No DB or external service. */
declare(strict_types=1);
$root = getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT') ?: dirname(__DIR__) . '/app/modules/codex-ui-v1/extensions-test-runtime';
$pass = 0; $fail = 0;
function check(string $name, bool $ok): void {global $pass,$fail; echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n"; $ok ? $pass++ : $fail++;}
$source = file_get_contents($root . '/index.php');
$marker = '// Successfully logged in, display Portal.';
$tail = substr($source, strpos($source, $marker));
$tail = preg_replace('/elseif\s*\(/', 'if (', $tail, 1);
$cases = [
    'native global defaults to ProDéli' => [true, 'admin', false, '', '/schoolsafe-administrators.php?home=1'],
    'managed global keeps central landing' => [true, 'admin', true, '', '/schoolsafe-administrators.php?home=1'],
    'scoped native administrator keeps school portal' => [false, 'admin', false, '', 'Modules.php?modname=misc/Portal.php'],
    'scoped managed administrator keeps school portal' => [false, 'admin', true, '', 'Modules.php?modname=misc/Portal.php'],
    'teacher cannot inherit central landing' => [false, 'teacher', false, '', 'Modules.php?modname=misc/Portal.php'],
    'non-admin global predicate alone grants no central landing' => [true, 'teacher', true, '', 'Modules.php?modname=misc/Portal.php'],
    'explicit authorized destination is retained for native global' => [true, 'admin', false, 'modname=School_Setup/Configuration.php', 'Modules.php?modname=School_Setup/Configuration.php'],
    'explicit school portal remains reachable by global' => [true, 'admin', false, 'modname=misc/Portal.php', 'Modules.php?modname=misc/Portal.php'],
    'explicit school destination is retained for school administrator' => [false, 'admin', false, 'modname=Users/User.php', 'Modules.php?modname=Users/User.php'],
    'print/save flags remain removed on redirect' => [true, 'admin', false, 'modname=Users/User.php&LO_save=1&_ROSARIO_PDF=true', 'Modules.php?modname=Users/User.php'],
];
foreach ($cases as $name => [$global,$profile,$managed,$redirect,$expected]) {
    $fixture = '<?php namespace WorkspaceLanding; function User($field){return ' . var_export($profile,true) . ';}'
        . 'function SchoolSafeGlobalAdmin(){return ' . ($global?'true':'false') . ';}'
        . 'function function_exists($name){return $name==="SchoolSafeGlobalAdmin";}'
        . 'function URLEscape($value){return $value;} function header($value){echo $value;}'
        . '$_SESSION=' . var_export($managed?['schoolsafe-keycloak-session'=>true]:[],true) . ';'
        . '$_REQUEST=' . var_export($redirect!==''?['redirect_to'=>$redirect]:[],true) . ';' . $tail;
    $pipes=[];
    $process=proc_open([PHP_BINARY], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    fwrite($pipes[0],$fixture);fclose($pipes[0]);$actual=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($process);
    check($name,$status===0 && $error==='' && $actual==='Location: '.$expected);
}
$login = file_get_contents($root.'/modules/SchoolSafe/login-blue.inc.php');
check('login field supports the existing 128-character first-access policy',preg_match('/<input[^>]+id="PASSWORD"[^>]+maxlength="128"/',$login)===1);
$global = true;
$RosarioLocales = ['en_US.utf8','fr_FR.utf8'];
function PreparePHP_SELF($remove = [], $removeKeys = [], $add = []) {return '/Modules.php?modname=School_Setup/Configuration.php&'.http_build_query($add);}
function SchoolSafeGlobalAdmin(){return $GLOBALS['global'];}
function SchoolInfo($key){return 'École <test> & contrôle';}
$helper = $root . '/ProgramFunctions/SchoolSafeWorkspace.fnc.php';
check('shared SchoolSafe workspace renderer exists',is_file($helper));
if(is_file($helper)) {
    require $helper;
    ob_start();SchoolSafeWorkspaceHeader('Paramètres <école>','school');$globalHtml=ob_get_clean();
    check('native workspace uses original brand',str_contains($globalHtml,'src="/schoolsafe-logo.png"'));
    check('global settings expose direct central return',str_contains($globalHtml,'/schoolsafe-administrators.php?home=1'));
    check('native settings identify the school context',str_contains($globalHtml,'École &lt;test&gt; &amp; contrôle'));
    check('workspace escapes untrusted titles',str_contains($globalHtml,'Paramètres &lt;école&gt;') && !str_contains($globalHtml,'Paramètres <école>'));
    check('workspace keeps all native forms outside its markup',!str_contains($globalHtml,'<form'));
    check('workspace retains native French and English language choices',str_contains($globalHtml,'Français') && str_contains($globalHtml,'English') && str_contains($globalHtml,'&amp;locale=fr_FR.utf8'));
    check('workspace uses the existing animated SchoolSafe brand',str_contains($globalHtml,'class="ss-animated-brand"'));
    $global=false;ob_start();SchoolSafeWorkspaceHeader('Paramètres','school');$schoolHtml=ob_get_clean();
    check('school workspace never suggests global management',!str_contains($schoolHtml,'schoolsafe-administrators.php'));
    check('school workspace returns to native school dashboard',str_contains($schoolHtml,'/Modules.php?modname=misc/Portal.php'));
}
$wizard=file_get_contents($root.'/plugins/SchoolSafe_Keycloak/Wizard.php');
check('global home identifies the distinct ProDéli dashboard',str_contains($wizard,'Tableau de bord ProDéli'));
check('home summarizes accessible establishments from existing filtered data',str_contains($wizard,'sskc-dashboard-summary') && str_contains($wizard,'count($schools)'));
check('native settings load shared SchoolSafe workspace',str_contains(file_get_contents($root.'/modules/School_Setup/Configuration.php'),'SchoolSafeWorkspaceHeader('));
check('native administration keeps the same shared return and brand',str_contains(file_get_contents($root.'/admin-console.php'),'SchoolSafeWorkspaceHeader('));
$portal=file_get_contents($root.'/modules/misc/Portal.php');
check('global school return does not depend on unrelated Users permission',!preg_match('/if\s*\(\s*SchoolSafeGlobalAdmin\(\)\s*&&\s*AllowUse\(\s*[\'"]Users\/User.php/',$portal));
$photo=file_get_contents($root.'/ProgramFunctions/SchoolSafePhotoDashboard.fnc.php');
check('school modules action is not mislabeled as global executive view',str_contains($photo,'Modules de l’école') && !str_contains($photo,'Vue exécutive'));
$js=file_get_contents($root.'/assets/js/schoolsafe-photo-dashboard.js');
check('global return is visible in school header, not hidden in account popover',str_contains($js,'if(central)left.appendChild(central);'));
check('school header adopts the actual return, not the legacy management breadcrumb',str_contains($js,"querySelector('#body > .ss-central-dashboard-return')"));
check('shared workspace stylesheet exists',is_file($root.'/assets/css/schoolsafe-native-workspace.css'));
check('updated cached assets receive distinct URLs',substr_count($photo,'v=workspace-20261022')===2 && str_contains($wizard,'central-ui.css?v=16'));
echo json_encode(['passed'=>$pass,'failed'=>$fail,'scope'=>'Controller and rendering with substituted authorization; browser/real SQL are separate gates.'])."\n";
exit($fail?1:0);
