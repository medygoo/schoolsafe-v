<?php
// Pure contracts: no DB, account mutation, provider or fixture secret.
$root = getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT') ?: dirname(__DIR__) . '/app/modules/codex-ui-v1/extensions-test-runtime';
$file = $root . '/ProgramFunctions/SchoolSafeSchoolAccess.fnc.php';
if (!is_file($file)) { echo "FAIL native school access owner missing\n"; exit(1); }
require $file;
$n=0;$failed=0;
function check($ok,$name){global $n,$failed;$n++;if(!$ok)$failed++;echo ($ok?'PASS ':'FAIL ').$name."\n";}
$a=['SchoolSafe/ScanArrival.php'=>[true,true],'SchoolSafe/ScanVerify.php'=>[true,false]];
check(SchoolSafeAccessRightsCovered($a,$a),'full equal school envelope is delegable');
check(SchoolSafeAccessRightsCovered($a,['SchoolSafe/ScanArrival.php'=>[true,false]]),'use-only is covered by use and edit');
check(!SchoolSafeAccessRightsCovered($a,['SchoolSafe/ScanVerify.php'=>[true,true]]),'edit cannot be borrowed from use');
check(!SchoolSafeAccessRightsCovered($a,['SchoolSafe/ScanFees.php'=>[true,true]]),'missing permission denied');
check(SchoolSafeAccessRightsCovered($a,[]),'empty selection supports revocation');
$catalog=SchoolSafeAccessCatalog();
check(count(array_intersect(array_keys($catalog),['SchoolSafe/ScanArrival.php','SchoolSafe/ScanPickup.php','SchoolSafe/ScanFees.php','SchoolSafe/ScanCanteen.php','SchoolSafe/ScanVerify.php']))===5,'five independent native Scan keys');
check(!isset($catalog['SchoolSafe/ParentToggle.php']),'parent-only authority is not school delegated');
check(!isset($catalog['Users/Profiles.php'])&&!isset($catalog['Users/Exceptions.php']),'central native editors never delegated');
check(isset($catalog['Student_Billing/StudentFees.php'])&&isset($catalog['Food_Service/Accounts.php']),'monetary detail stays explicit and separate');
check(SchoolSafeAccessInput(['SchoolSafe/ScanArrival.php'=>['use'=>'Y','edit'=>'Y']],'teacher')===['SchoolSafe/ScanArrival.php'=>[true,true]],'native flags decoded');
foreach ([['SchoolSafe/ScanArrival.php'=>['edit'=>'Y']],['SchoolSafe/ScanArrival.php'=>['use'=>'false']],['Users/Profiles.php'=>['use'=>'Y']],['SchoolSafe/DelegationManage.php'=>['use'=>'Y','edit'=>'Y']]] as $i=>$bad) {
 try{SchoolSafeAccessInput($bad,'teacher');$ok=false;}catch(InvalidArgumentException $e){$ok=true;}
 check($ok,'malformed, reserved or incompatible selection refused '.$i);
}
check(SchoolSafeAccessInput([],'teacher')===[],'explicit empty revocation accepted');
echo json_encode(['passed'=>$n,'failed'=>$failed])."\n";exit($failed?1:0);
