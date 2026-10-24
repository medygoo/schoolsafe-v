<?php
/** Synthetic render of the real two navigation loops. No session, DB or provider. */
declare(strict_types=1);
$root = getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT') ?: '/repo/app/modules/codex-ui-v1/extensions-test-runtime';
function sskcEscape($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
function SchoolSafeGlobalAdmin() { return $GLOBALS['central']; }
function SchoolSafeStaffWithinScope($id) { return true; }
function SchoolSafeScopeParse($scope) {
    if ($scope === null || $scope === '') return null;
    if (!preg_match('/^,(?:[1-9][0-9]*,)+$/D', $scope)) return false;
    return array_map('intval', explode(',', trim($scope, ',')));
}
function sskcAccountPresentation($row) { return ['access'=>'Actif','connection'=>'Connexion native','onboarding'=>'']; }
function sskcAccountSchoolNames($scope, $names) { return 'École synthétique'; }
function sskcCanWrite() { return false; }
function UserSyear() { return 2026; }
function Config($key) { return 2026; }
function User($key) { return 900; }
function sskcWizardHidden($key,$value) { echo '<input type="hidden" name="'.sskcEscape($key).'" value="'.sskcEscape($value).'">'; }
function between(string $source,string $start,string $end): string {
    $a=strpos($source,$start); if($a===false)throw new RuntimeException('Missing render start');
    $b=strpos($source,$end,$a+strlen($start)); if($b===false)throw new RuntimeException('Missing render end');
    return substr($source,$a,$b-$a);
}
$manage=between(file_get_contents($root.'/plugins/SchoolSafe_Keycloak/manage.php'), 'foreach((array)$rows as $row) {', "if(!\$count)echo '<tr>");
$home=between(file_get_contents($root.'/plugins/SchoolSafe_Keycloak/Wizard.php'), 'foreach($schools as $id=>$title){', "        echo '</ul><p id=\"sskc-school-search-empty\"");
function renderAccount(array $changes=[],bool $central=true,bool $centralPopulation=false,array $names=[3=>'École synthétique']): string {
    global $manage; $GLOBALS['central']=$central;
    $rows=[array_replace(['STAFF_ID'=>17,'FIRST_NAME'=>'Responsable','LAST_NAME'=>'Synthétique','USERNAME'=>'fixture.admin','PROFILE'=>'admin','PROFILE_ID'=>42,'PROFILE_TITLE'=>'Responsable scolaire','SCHOOLS'=>',3,','SUBJECT'=>null],$changes)];
    $showCentral=$centralPopulation;$schoolNames=$names;$count=0;$csrf='synthetic-only';
    ob_start();eval($manage);return ob_get_clean();
}
function renderSchools(bool $central=true): string {
    global $home;$GLOBALS['central']=$central;$schools=[3=>'École <synthétique>'];$current=3;$_SESSION=['token'=>'synthetic-only'];
    ob_start();eval($home);return ob_get_clean();
}
function links(string $html): array {
    $doc=new DOMDocument();@$doc->loadHTML('<?xml encoding="UTF-8"><main>'.$html.'</main>',LIBXML_NONET);
    $found=[];foreach($doc->getElementsByTagName('a') as $a)if(strpos($a->getAttribute('href'),'/admin-console.php?access_school=')===0)$found[]=$a;
    return $found;
}
$passed=0;$failed=0;
function check(string $name,bool $ok): void {global $passed,$failed;if($ok){$passed++;echo "PASS $name\n";}else{$failed++;echo "FAIL $name\n";}}
$account=renderAccount();$accountLinks=links($account);$school=renderSchools();$schoolLinks=links($school);
check('central account row exposes an access button',count($accountLinks)===1);
check('account button names Scan and access',count($accountLinks)===1 && strpos($accountLinks[0]->textContent,'Gérer les droits — Scan et accès')!==false);
check('account link targets the existing school and selected account',count($accountLinks)===1 && $accountLinks[0]->getAttribute('href')==='/admin-console.php?access_school=3&access_staff=17#school-native-access');
check('school home exposes its access button',count($schoolLinks)===1);
check('school link opens existing owner, not the school dashboard',count($schoolLinks)===1 && $schoolLinks[0]->getAttribute('href')==='/admin-console.php?access_school=3#school-native-access');
check('school button has a contextual accessible name',count($schoolLinks)===1 && strpos($schoolLinks[0]->getAttribute('aria-label'),'École <synthétique>')!==false);
check('school title remains escaped',strpos($school,'<synthétique>')===false);
check('school staff never receive the central access link',count(links(renderAccount([],false)))===0);
check('school home does not expose central access to school staff',count(links(renderSchools(false)))===0);
check('school home retains native guarded school switch',strpos(renderSchools(false),'name="action" value="dashboard_school"')!==false);
check('central team account never receives school access link',count(links(renderAccount(['SCHOOLS'=>''],true,true)))===0);
check('multiple school account is not silently converted',count(links(renderAccount(['SCHOOLS'=>',3,4,'])))===0);
check('unknown school is not offered',count(links(renderAccount([],true,false,[])))===0);
check('disabled native account is not offered',count(links(renderAccount(['PROFILE'=>'none'])))===0);
check('self account is not offered',count(links(renderAccount(['STAFF_ID'=>900])))===0);
check('malformed scope is not offered',count(links(renderAccount(['SCHOOLS'=>'broken'])))===0);
if($out=getenv('SCHOOLSAFE_DISCOVERY_RENDER'))file_put_contents($out,json_encode(['account'=>'<table id="sskc-accounts"><tbody>'.$account.'</tbody></table>','home'=>'<div class="sskc-home"><ul class="sskc-school-directory">'.$school.'</ul></div>'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
echo json_encode(['passed'=>$passed,'failed'=>$failed,'evidence'=>'synthetic actual PHP render loops; no authenticated HTTP'])."\n";
exit($failed?1:0);
