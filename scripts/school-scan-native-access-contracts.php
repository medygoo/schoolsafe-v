<?php
// Real native rights catalog/resolver/gateway; SELECT-only synthetic grant store.
// No accounts, permission writes, HTTP calls, provider configuration or messages.
if (PHP_SAPI !== 'cli') exit(2);
$root=getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT') ?: dirname(__DIR__).'/app/modules/codex-ui-v1/extensions-test-runtime';
$user=['STAFF_ID'=>'9','PROFILE'=>'teacher','PROFILE_ID'=>'8'];$grants=[];$readOnly=false;$queries=[];
$RosarioModules=['SchoolSafe'=>true];
function User($key) { global $user; return $user[$key]??null; }
function SchoolSafeAccountBlocksWrite() { global $readOnly; return $readOnly; }
function DBGetOne($sql) {
    global $grants,$queries;
    $queries[]=$sql;
    if (!preg_match("/^SELECT 1 FROM (profile_exceptions|staff_exceptions) WHERE (PROFILE_ID|USER_ID)='([0-9]+)' AND MODNAME='([^']+)' AND CAN_USE='Y'( AND CAN_EDIT='Y')?$/D",$sql,$match)) throw new RuntimeException('UNEXPECTED_SQL');
    $grant=$grants[$match[1]][$match[3]][$match[4]]??[];
    return ($grant['use']??false) && (empty($match[5])||($grant['edit']??false)) ? 1 : false;
}
require_once $root.'/modules/SchoolSafe/Gateway.php';
require_once $root.'/modules/SchoolSafe/RightsCatalog.php';
$pass=0;$fail=0;
function accessCheck($name,$actual,$expected) {
    global $pass,$fail;
    if ($actual===$expected) {$pass++;echo "PASS $name\n";}
    else {$fail++;echo "FAIL $name\n";}
}
$native=['Users'=>['admin'=>['Users/User.php'=>'Users']]];
$published=SchoolSafeRightsEditorMenu($native,'Users/Profiles.php');
accessCheck('rights editor keeps native programs',$published['Users'],$native['Users']);
accessCheck('publishing rights does not mutate navigation',$native,['Users'=>['admin'=>['Users/User.php'=>'Users']]]);
foreach (SchoolSafeScanPermissionKeys() as $mode=>$key) {
    accessCheck($mode.' checkbox exists for eligible teacher profile',isset($published['SchoolSafe']['teacher'][$key]),true);
    $grants=[];
    accessCheck($mode.' route denied without native grant',SchoolSafeEndpointGateway('scanner-relay.php','POST','scan',['mode'=>$mode]),'deny');
    $grants=['profile_exceptions'=>['8'=>[$key=>['use'=>true,'edit'=>false]]]];
    accessCheck($mode.' route uses granted profile key',SchoolSafeEndpointGateway('scanner-relay.php','POST','scan',['mode'=>$mode]),'allow');
    foreach (SchoolSafeScanPermissionKeys() as $otherMode=>$otherKey) {
        if ($otherMode===$mode) continue;
        accessCheck($mode.' grant never authorizes '.$otherMode,SchoolSafeEndpointGateway('scanner-relay.php','POST','scan',['mode'=>$otherMode]),'deny');
    }
}
$key='SchoolSafe/ScanArrival.php';$grants=['staff_exceptions'=>['9'=>[$key=>['use'=>true,'edit'=>true]]]];
$user['PROFILE_ID']='8';
accessCheck('assigned profile never inherits individual override',SchoolSafeNativeCommandPermission($key),false);
$user['PROFILE_ID']=null;
accessCheck('custom permissions use native staff_exceptions',SchoolSafeNativeCommandPermission($key),true);
$user['PROFILE_ID']='0';
accessCheck('profile zero is assigned, not custom',SchoolSafeNativeCommandPermission($key),false);
$grants=['profile_exceptions'=>['0'=>[$key=>['use'=>true,'edit'=>false]]]];
accessCheck('profile zero grant is resolved explicitly',SchoolSafeNativeCommandPermission($key),true);
$user=['STAFF_ID'=>'9','PROFILE'=>'admin','PROFILE_ID'=>'8'];
$grants=['profile_exceptions'=>['8'=>[$key=>['use'=>true,'edit'=>false]]]];
accessCheck('admin mutation needs edit as well as use',SchoolSafeNativeCommandPermission($key),false);
$grants['profile_exceptions']['8'][$key]['edit']=true;
accessCheck('admin explicit use plus edit allows arrival',SchoolSafeNativeCommandPermission($key),true);
$readOnly=true;
accessCheck('read-only account cannot record an arrival',SchoolSafeNativeCommandPermission($key),false);
$verify='SchoolSafe/ScanVerify.php';$grants['profile_exceptions']['8'][$verify]=['use'=>true,'edit'=>false];
accessCheck('read-only verification remains independently granted',SchoolSafeNativeCommandPermission($verify),true);
$readOnly=false;$user['PROFILE']='parent';
accessCheck('parent gets no scan execution through forged family grant',SchoolSafeNativeCommandPermission($key),false);
$user['PROFILE']='teacher';$user['PROFILE_ID']='8';
$RosarioModules['SchoolSafe']=false;
accessCheck('rights catalog still publishes without activating module',isset(SchoolSafeRightsEditorMenu($native,'Users/Profiles.php')['SchoolSafe']['teacher'][$key]),true);
accessCheck('published checkbox is not module activation',SchoolSafeNativeCommandPermission($key),false);
accessCheck('gateway legacy branch is not unconditional native allowance',SchoolSafeEndpointGateway('scanner-relay.php','POST','scan',['mode'=>'entry']),'legacy');
$RosarioModules['SchoolSafe']=true;
accessCheck('unknown scan mode denied',SchoolSafeEndpointGateway('scanner-relay.php','POST','scan',['mode'=>'pre_exit']),'deny');
accessCheck('conflicting action never borrows scan permission',SchoolSafeEndpointGateway('scanner-relay.php','POST','scan',['mode'=>'entry','action'=>'delegate']),'deny');
$user['PROFILE']='admin';
$grants=['profile_exceptions'=>['8'=>['SchoolSafe/DelegationManage.php'=>['use'=>true,'edit'=>true]]]];
accessCheck('delegation management has an existing native program',SchoolSafeEndpointGateway('scanner-relay.php','POST','management',['action'=>'delegate']),'allow');
accessCheck('delegation manager does not automatically become scanner',SchoolSafeEndpointGateway('scanner-relay.php','POST','scan',['mode'=>'entry']),'deny');
$readKey='SchoolSafe/ScanLocationsRead.php';$saveKey='SchoolSafe/ScanLocationsManage.php';
$grants=['profile_exceptions'=>['8'=>[$readKey=>['use'=>true,'edit'=>false]]]];
accessCheck('location reader is separately authorized',SchoolSafeNativeCommandPermission($readKey),true);
accessCheck('location reader cannot configure',SchoolSafeNativeCommandPermission($saveKey),false);
$grants=['profile_exceptions'=>['8'=>[$saveKey=>['use'=>true,'edit'=>false]]]];
accessCheck('location mutation requires edit',SchoolSafeNativeCommandPermission($saveKey),false);
$grants['profile_exceptions']['8'][$saveKey]['edit']=true;
accessCheck('location manager uses native use plus edit',SchoolSafeNativeCommandPermission($saveKey),true);
accessCheck('location manager has no implicit read grant',SchoolSafeNativeCommandPermission($readKey),false);
$readOnly=true;
accessCheck('read-only account cannot configure locations',SchoolSafeNativeCommandPermission($saveKey),false);
$readOnly=false;$user['PROFILE']='teacher';
accessCheck('teacher cannot borrow admin location permission',SchoolSafeNativeCommandPermission($saveKey),false);
$user['PROFILE']='parent';
accessCheck('parent cannot configure checkpoints',SchoolSafeNativeCommandPermission($saveKey),false);
$user['PROFILE']='admin';$RosarioModules['SchoolSafe']=false;
accessCheck('inactive module cannot activate native location writes',SchoolSafeNativeCommandPermission($saveKey),false);
$RosarioModules['SchoolSafe']=true;
echo json_encode(['pass'=>$pass,'fail'=>$fail,'scope'=>'real native PHP rights functions with synthetic SELECT-only grant data'])."\n";
exit($fail?1:0);
