<?php
// Pure SELECT-only contracts for the existing native checkpoint list.
if (PHP_SAPI!=='cli') exit(2);
$root=getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT') ?: dirname(__DIR__).'/app/modules/codex-ui-v1/extensions-test-runtime';
require_once $root.'/ProgramFunctions/SchoolSafeScanPassage.fnc.php';
$pass=0;$fail=0;$queries=[];$ready=7;
function locationCheck($label,$actual,$expected) {
    global $pass,$fail;
    if ($actual===$expected) {$pass++;echo "PASS $label\n";} else {$fail++;echo "FAIL $label\n";}
}
function locationFailure($call) {
    try {$call();return 'NO_ERROR';} catch (RuntimeException $error) {return $error->getMessage();}
}
$db=['get'=>function($sql) use (&$queries,&$ready) {
    if (strpos($sql,'SELECT ')!==0) throw new RuntimeException('NO_WRITES_ALLOWED');
    $queries[]=$sql;
    if (strpos($sql,'information_schema.columns')!==false) return [1=>['N'=>$ready]];
    return [1=>['ID'=>7,'TITLE'=>'Synthetic native checkpoint']];
}];
locationCheck('ungranted modes have empty list',SchoolSafeScanLocations(1,2026,[],$db),[]);
locationCheck('ungranted modes make no query',$queries,[]);
locationCheck('invalid school refused',locationFailure(function() use ($db) {SchoolSafeScanLocations(0,2026,['entry'],$db);}), 'INVALID_CONTEXT');
locationCheck('invalid year refused',locationFailure(function() use ($db) {SchoolSafeScanLocations(1,0,['entry'],$db);}), 'INVALID_CONTEXT');
locationCheck('non-location scan permission cannot become location access',locationFailure(function() use ($db) {SchoolSafeScanLocations(1,2026,['fees'],$db);}), 'INVALID_SCAN_MODE');
locationCheck('native id label contract preserved',SchoolSafeScanLocations(1,2026,['entry'],$db),[['id'=>'7','label'=>'Synthetic native checkpoint']]);
$sql=end($queries);
locationCheck('query binds school year and enabled status',strpos($sql,'SCHOOLSAFE_SCAN_SCHOOL_ID=1 AND SCHOOLSAFE_SCAN_SYEAR=2026 AND SCHOOLSAFE_SCAN_ENABLED')!==false,true);
locationCheck('arrival grant never selects out-only checkpoints',strpos($sql,"TYPE='in'")!==false&&strpos($sql,"TYPE='out'")===false,true);
SchoolSafeScanLocations(1,2026,['pickup'],$db);$sql=end($queries);
locationCheck('pickup grant never selects in-only checkpoints',strpos($sql,"TYPE='out'")!==false&&strpos($sql,"TYPE='in'")===false,true);
$failedRead=['get'=>function($sql) {return strpos($sql,'information_schema.columns')!==false ? [1=>['N'=>7]] : false;}];
locationCheck('failed location read is not reported as an empty configuration',locationFailure(function() use ($failedRead) {SchoolSafeScanLocations(1,2026,['entry'],$failedRead);}), 'SCAN_LOCATIONS_UNAVAILABLE');
$ready=6;
locationCheck('incomplete scope migration fails closed',locationFailure(function() use ($db) {SchoolSafeScanLocations(1,2026,['entry'],$db);}), 'SCAN_SCOPE_UNAVAILABLE');
echo json_encode(['pass'=>$pass,'fail'=>$fail,'scope'=>'synthetic SELECT-only callbacks; no HTTP/session/configuration mutations'])."\n";
exit($fail?1:0);
