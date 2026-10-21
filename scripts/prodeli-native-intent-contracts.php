<?php
/** Controller contracts with substituted DB/auth/session; NOT HTTP proof. */
function add_action(...$args) {}
function User($key) {return ['PROFILE'=>'admin','SCHOOLS'=>'','STAFF_ID'=>7,'USERNAME'=>'qa.manager'][$key]??'';}
function UserSyear() {return $GLOBALS['year'];}
function Config($key) {return 2026;}
function AllowUse($key) {return $GLOBALS['allowed'];}
function AllowEdit($key) {return $GLOBALS['allowed'];}
function SchoolSafeProfileDelegable($id) {return $GLOBALS['delegable'];}
function SchoolSafeGlobalAdmin() {return $GLOBALS['global'];}
function SchoolSafeScopeParse($scope) {return $scope===''?null:array_map('intval',explode(',',trim($scope,',')));}
function SchoolSafeScopeDelegates($actor,$target) {return true;}
function DBGetOne($sql) {
    if(str_contains($sql,'SELECT PROFILE FROM user_profiles'))return 'admin';
    if(str_contains($sql,'SELECT 1 FROM staff'))return $GLOBALS['duplicate'];
    return 1;
}
function DBEscapeString($s) {return str_replace("'","''",$s);}
function SchoolSafeCreateDirector($input,$year,$actor) {
    $GLOBALS['calls'][]=$input;
    return $GLOBALS['create_failure']?['ok'=>false,'error'=>'PRINCIPAL_EXISTS']:
        ['ok'=>true,'staff_id'=>123,'temp_password'=>'synthetic-temporary-not-a-real-account'];
}
function schoolSafeKeycloakConfig() {$GLOBALS['provider_calls']++;throw new RuntimeException('Provider must not be called');}
require (getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT')?:'/candidate').'/plugins/SchoolSafe_Keycloak/Management.php';
$passed=0;$failed=0;
function check($name,$fn) {global $passed,$failed;try{$fn();$passed++;echo "PASS $name\n";}catch(Throwable $e){$failed++;echo "FAIL $name\n";}}
function mustReject($fn) {try{$fn();}catch(RuntimeException $e){return;}throw new Exception('Expected rejection');}
function expect($v) {if(!$v)throw new Exception('Unexpected outcome');}
function resetCase() {
    $GLOBALS['allowed']=$GLOBALS['delegable']=$GLOBALS['global']=true;$GLOBALS['duplicate']=false;
    $GLOBALS['year']=2026;$GLOBALS['create_failure']=false;$GLOBALS['calls']=[];$GLOBALS['provider_calls']=0;
    $_SESSION=['token'=>'fixture-token'];$_SERVER['REQUEST_METHOD']='POST';
    $_POST=['action'=>'prepare','token'=>'fixture-token','first_name'=>'Anne','last_name'=>'Test',
        'username'=>'qa.native','email'=>'qa@example.invalid','profile_id'=>'3','scope_mode'=>'schools','schools'=>['2']];
}
function confirmation($intent) {$_POST=['action'=>'native_create','token'=>'fixture-token','confirmed'=>'1','sskc_intent'=>$intent['id']];}
resetCase();
check('native preparation never calls paused provider',function(){resetCase();$i=sskcPrepareIntent();expect($i['native_director'] && $GLOBALS['provider_calls']===0);});
check('multiple schools rejected',function(){resetCase();$_POST['schools']=['2','3'];mustReject(fn()=>sskcPrepareIntent());});
check('school-scoped manager cannot provision principal',function(){resetCase();$GLOBALS['global']=false;mustReject(fn()=>sskcPrepareIntent());});
check('missing write permission rejected',function(){resetCase();$GLOBALS['allowed']=false;mustReject(fn()=>sskcPrepareIntent());});
check('undelegable profile rejected',function(){resetCase();$GLOBALS['delegable']=false;mustReject(fn()=>sskcPrepareIntent());});
check('wrong school year rejected',function(){resetCase();$GLOBALS['year']=2025;mustReject(fn()=>sskcPrepareIntent());});
check('native duplicate rejected without provider',function(){resetCase();$GLOBALS['duplicate']=true;mustReject(fn()=>sskcPrepareIntent());expect($GLOBALS['provider_calls']===0);});
check('confirmation requires CSRF',function(){resetCase();$i=sskcPrepareIntent();confirmation($i);$_POST['token']='wrong';mustReject(fn()=>sskcConfirmNativeDirector());expect(!$GLOBALS['calls']);});
check('confirmation required explicitly',function(){resetCase();$i=sskcPrepareIntent();confirmation($i);unset($_POST['confirmed']);mustReject(fn()=>sskcConfirmNativeDirector());});
check('confirmation expiry rechecked',function(){resetCase();$i=sskcPrepareIntent();confirmation($i);$_SESSION['sskc_intent']['expires']=time()-1;mustReject(fn()=>sskcConfirmNativeDirector());});
check('actor binding rechecked',function(){resetCase();$i=sskcPrepareIntent();confirmation($i);$_SESSION['sskc_intent']['actor']=8;mustReject(fn()=>sskcConfirmNativeDirector());});
check('rights revoked after preparation rejected',function(){resetCase();$i=sskcPrepareIntent();confirmation($i);$GLOBALS['allowed']=false;mustReject(fn()=>sskcConfirmNativeDirector());});
check('raw fields cannot override confirmed identity school profile',function(){resetCase();$i=sskcPrepareIntent();confirmation($i);$_POST['staff']=['USERNAME'=>'forged','SCHOOLS'=>',99,','PROFILE_ID'=>1];expect(sskcConfirmNativeDirector()===123);$c=$GLOBALS['calls'][0];expect($c['username']==='qa.native' && $c['school_id']===2 && $c['profile_id']===3);});
check('confirmation replay rejected',function(){resetCase();$i=sskcPrepareIntent();confirmation($i);sskcConfirmNativeDirector();mustReject(fn()=>sskcConfirmNativeDirector());expect(count($GLOBALS['calls'])===1);});
check('failure consumes intent without releasing credential',function(){resetCase();$i=sskcPrepareIntent();confirmation($i);$GLOBALS['create_failure']=true;mustReject(fn()=>sskcConfirmNativeDirector());expect(empty($_SESSION['sskc_handoff']) && empty($_SESSION['sskc_intent']));});
check('handoff bound to actor short-lived and native',function(){resetCase();$i=sskcPrepareIntent();confirmation($i);sskcConfirmNativeDirector();$h=$_SESSION['sskc_handoff'][123];expect($h['actor']===7 && $h['native'] && $h['expires']<=time()+600);});
echo json_encode(['passed'=>$passed,'failed'=>$failed])."\n";exit($failed?1:0);
