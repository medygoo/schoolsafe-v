<?php
// Contract tests: synthetic callbacks only, no account/database/network mutation.
if (PHP_SAPI!=='cli') exit(2);
$root=getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT') ?: dirname(__DIR__).'/app/modules/codex-ui-v1/extensions-test-runtime';
$pass=0;$fail=0;
function manageCheck($name,$ok) {global $pass,$fail;if($ok){$pass++;echo "PASS $name\n";}else{$fail++;echo "FAIL $name\n";}}
$relay=file_get_contents($root.'/scanner-relay.php');$page=file_get_contents($root.'/scan-locations.php');
$local=strpos($relay,'if(!SchoolSafeScanBridgeAvailable())');$call=strpos($relay,'SchoolSafeScanLocationManage(');
manageCheck('native management wired before bridge fallback',$call!==false&&$call>$local&&$call<strpos($relay,"throw new RuntimeException('CONFIG_REQUIRED');",$local));
manageCheck('page and relay share engine selection',strpos($page,'SchoolSafeScanBridgeAvailable()')!==false&&strpos($relay,"function SchoolSafeScanBridgeAvailable()")===false);
manageCheck('native form represents actual checkpoint direction',strpos($page,'name="direction"')!==false);
require_once $root.'/ProgramFunctions/SchoolSafeScanLocationManage.fnc.php';
$ctx=['user'=>9,'school'=>1,'year'=>2026,'profile'=>'admin','can_configure'=>true,'can_read'=>true,'can_save'=>true];
$queries=[];$quoted=[];$found=true;$ready=7;
$db=['get'=>function($sql) use (&$queries,&$found,&$ready) {
    $queries[]=$sql;
    if(strpos($sql,'information_schema.columns')!==false)return [1=>['N'=>$ready]];
    if(strpos($sql,'SELECT ')===0)return [1=>['ID'=>7,'TITLE'=>'Synthetic point','TYPE'=>'in','SCHOOLSAFE_SCAN_ENABLED'=>'f']];
    return $found?[1=>['ID'=>42]]:[];
},'quote'=>function($value) use (&$quoted) {$quoted[]=$value;return "'".str_replace("'","''",$value)."'";}];
$valid=['action'=>'save','label'=>" Portail d'école ",'direction'=>'both','is_active'=>false];
function manageFailure($input,$ctx,$db) {try {SchoolSafeScanLocationManage($input,$ctx,$db);return 'NO_ERROR';}catch(RuntimeException $error){return $error->getMessage();}}
foreach ([['profile'=>'teacher'],['profile'=>'parent'],['can_configure'=>false],['can_save'=>false],['can_save'=>'t'],['user'=>0],['school'=>0],['year'=>0]] as $override) {
    $queries=[];manageCheck('untrusted or missing authority refused '.json_encode($override),in_array(manageFailure($valid,array_replace($ctx,$override),$db),['FORBIDDEN','INVALID_CONTEXT'],true)&&$queries===[]);
}
$queries=[];
manageCheck('read permission does not grant save',manageFailure($valid,array_replace($ctx,['can_save'=>false]),$db)==='FORBIDDEN');
manageCheck('save permission does not grant list',manageFailure(['action'=>'list'],array_replace($ctx,['can_read'=>false]),$db)==='FORBIDDEN');
manageCheck('separate save permission works without read',SchoolSafeScanLocationManage($valid,array_replace($ctx,['can_read'=>false]),$db)===['id'=>'42']);
manageCheck('title quoted by database adapter after trim',end($quoted)==="Portail d'école");
manageCheck('create binds session scope and defaults are explicit',strpos(end($queries),",NULL,1,2026,false) RETURNING ID")!==false);
foreach ([['label'=>''],['label'=>str_repeat('x',101)],['label'=>"bad\0title"],['is_active'=>'false'],['direction'=>'classroom'],['id'=>null],['id'=>false],['id'=>'0'],['id'=>'-1'],['id'=>'2147483648'],['id'=>'uuid'],['code'=>'invented'],['kind'=>'gate'],['school'=>2],['year'=>2025],['can_save'=>true]] as $override) {
    $queries=[];manageCheck('invalid or client-owned context input refused '.json_encode(array_keys($override)),manageFailure(array_replace($valid,$override),$ctx,$db)==='INVALID_INPUT'&&$queries===[]);
}
$out=SchoolSafeScanLocationManage(['action'=>'list'],$ctx,$db);
manageCheck('PostgreSQL false stays inactive',$out['locations'][0]['is_active']===false);
manageCheck('native direction returned without invented code or kind',$out['locations'][0]['direction']==='in'&&!isset($out['locations'][0]['code'],$out['locations'][0]['kind']));
manageCheck('administrative list binds school year but includes inactive',strpos(end($queries),'SCHOOLSAFE_SCAN_SCHOOL_ID=1 AND SCHOOLSAFE_SCAN_SYEAR=2026')!==false&&strpos(end($queries),'AND SCHOOLSAFE_SCAN_ENABLED')===false);
$found=false;manageCheck('unknown scoped update is not a fictitious success',manageFailure($valid+['id'=>'8'],$ctx,$db)==='NOT_FOUND');
$ready=6;manageCheck('scope migration missing refuses management',manageFailure($valid,$ctx,$db)==='SCAN_SCOPE_UNAVAILABLE');
manageCheck('native escape adapter used at route',strpos($relay,'DBEscapeString($value)')!==false);
echo json_encode(['pass'=>$pass,'fail'=>$fail,'scope'=>'source wiring and synthetic callbacks; no authenticated HTTP or database writes'])."\n";exit($fail?1:0);
