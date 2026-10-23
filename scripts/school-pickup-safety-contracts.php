<?php
// Pure synthetic contracts: no database, file, identity or business mutation.
if (PHP_SAPI !== 'cli') exit(2);
$root = getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT') ?: dirname(__DIR__) . '/app/modules/codex-ui-v1/extensions-test-runtime';
require_once $root . '/ProgramFunctions/SchoolSafePickupManage.fnc.php';
$pass = 0; $fail = 0;
function expectPickup($label, $actual, $expected) {
    global $pass, $fail;
    if ($actual === $expected) { $pass++; echo "PASS $label\n"; }
    else { $fail++; echo "FAIL $label\n"; }
}
function pickupFailure(callable $call) {
    try { $call(); return 'NO_REFUSAL'; } catch (RuntimeException $error) { return $error->getMessage(); }
}
$day = '2026-10-23';
$family = ['ID'=>21, 'GUARDIAN_TYPE'=>'mere', 'IS_ACTIVE'=>'t', 'IS_PRIMARY'=>'t',
    'IS_AUTHORIZED_PICKUP'=>'t', 'PHOTO_ACCEPTED'=>'t', 'PHOTO_PATH'=>'/fixture-missing.jpg'];
expectPickup('valid family remains eligible', SchoolSafePickupReady($family, null, $day), true);
foreach (['IS_ACTIVE', 'PHOTO_ACCEPTED', 'IS_AUTHORIZED_PICKUP'] as $key) {
    foreach (['f', false, '0', 0, null, 'unexpected'] as $value) {
        expectPickup('false/unknown '.$key.' cannot authorize handover',
            SchoolSafePickupReady(array_replace($family, [$key=>$value]), null, $day), false);
    }
}
expectPickup('approved father need not be principal', SchoolSafePickupReady(array_replace($family,
    ['IS_PRIMARY'=>'f', 'GUARDIAN_TYPE'=>'pere']), null, $day), true);
$external = array_replace($family, ['IS_PRIMARY'=>'f', 'GUARDIAN_TYPE'=>'externe']);
$authorization = ['STATUS'=>'active', 'PHOTO_VERIFIED'=>'t', 'STARTS_ON'=>null, 'ENDS_ON'=>null];
expectPickup('external needs authorization, not inherited family flag', SchoolSafePickupReady($external, null, $day), false);
expectPickup('authorized external ready', SchoolSafePickupReady($external, $authorization, $day), true);
expectPickup('rejected external photo never authorizes', SchoolSafePickupReady($external,
    array_replace($authorization, ['PHOTO_VERIFIED'=>'f']), $day), false);
foreach (['pending','suspended','revoked'] as $status) expectPickup('inactive authorization '.$status,
    SchoolSafePickupReady($external, array_replace($authorization, ['STATUS'=>$status]), $day), false);
expectPickup('future authorization denied', SchoolSafePickupReady($external,
    array_replace($authorization, ['STARTS_ON'=>'2026-10-24']), $day), false);
expectPickup('expired authorization denied', SchoolSafePickupReady($external,
    array_replace($authorization, ['ENDS_ON'=>'2026-10-22']), $day), false);
$writes = [];
$ctx = ['school'=>1,'year'=>2026,'user'=>9,'profile'=>'admin'];
$guardian = $family;
$db = ['get'=>function($sql) use (&$guardian) {
    if (strpos($sql,'FROM student_enrollment') !== false) return [1=>['OK'=>1]];
    if (strpos($sql,'FROM schoolsafe_pickup_guardians WHERE ID=') !== false) return [1=>$guardian];
    if (strpos($sql,'FROM schoolsafe_pickup_guardians') !== false) return [1=>array_replace($guardian,
        ['FULL_NAME'=>'Fixture principal']), 2=>array_replace($guardian,
        ['ID'=>22,'FULL_NAME'=>'Fixture father','GUARDIAN_TYPE'=>'pere','IS_PRIMARY'=>'f','PHOTO_ACCEPTED'=>'f'])];
    if (strpos($sql,'FROM schoolsafe_pickup_authorizations') !== false) return [];
    throw new RuntimeException('UNEXPECTED_SQL');
}, 'exec'=>function($sql) use (&$writes) { $writes[]=$sql; }];
$listing = SchoolSafePickupList(11, $ctx, $db, $day);
expectPickup('two familial people stay in family group', count($listing['family']), 2);
expectPickup('father is not an external accredited slot', count($listing['accredited']), 0);
$father = $listing['family'][1] ?? [];
expectPickup('database f is not primary', $father['is_primary'] ?? null, false);
expectPickup('rejected photo not exposed in list', $father['photo_path'] ?? null, '');
$guardian['PHOTO_ACCEPTED']='f';
expectPickup('school cannot approve rejected photo', pickupFailure(function() use ($ctx,$db) {
    SchoolSafePickupApproveFamily(11,21,'Fixture reason','Fixture consent',$ctx,$db);
}), 'PHOTO_NOT_VALIDATED');
expectPickup('rejected approval made no write', $writes, []);
$guardian['IS_ACTIVE']='f';
expectPickup('inactive guardian cannot receive photo review', pickupFailure(function() use ($ctx,$db) {
    SchoolSafePickupPhotoReview(11,21,'accepted','Fixture reason',$ctx,$db);
}), 'INVALID_PERSON');
expectPickup('inactive review made no write', $writes, []);
// A parent may reactivate only an owned suspended authorization, not a pending request.
$parent = ['school'=>1,'year'=>2026,'user'=>42,'profile'=>'parent','parent'=>true];
$auth = ['ID'=>31,'GUARDIAN_ID'=>23,'STATUS'=>'suspended','REQUESTED_BY'=>42,
    'PHOTO_VERIFIED'=>'t','STARTS_ON'=>null,'ENDS_ON'=>null];
$external['IS_ACTIVE']='t';
$changeOwnerOnLock = false;
$parentDb = ['get'=>function($sql) use (&$auth,$external,&$changeOwnerOnLock) {
    if (strpos($sql,'FROM students WHERE') !== false) return [1=>['STUDENT_ID'=>11]];
    if (strpos($sql,'FROM student_enrollment') !== false) return [1=>['OK'=>1]];
    if (strpos($sql,'FROM schoolsafe_pickup_guardians') !== false) return [1=>$external];
    if (strpos($sql,'COUNT(*)') !== false) return [1=>['N'=>0]];
    if (strpos($sql,'MIN(SLOT_NO)') !== false) return [1=>['TAKEN1'=>0]];
    if (strpos($sql,'SELECT SLOT_NO') !== false) return [];
    if (strpos($sql,'FROM schoolsafe_pickup_authorizations') !== false) return [1=>
        $changeOwnerOnLock && strpos($sql,'FOR UPDATE') !== false ? array_replace($auth,['REQUESTED_BY'=>99]) : $auth];
    throw new RuntimeException('UNEXPECTED_SQL');
}, 'exec'=>function($sql) use (&$writes) { $writes[]=$sql; }];
try {
    $reactivation = SchoolSafePickupParentActivate(11,31,'Fixture reactivation',$parent,$parentDb,$day);
    expectPickup('owned suspended authorization reactivates after same checks', $reactivation['status'], 'active');
} catch (RuntimeException $error) { expectPickup('owned suspended authorization reactivates after same checks', $error->getMessage(), 'active'); }
expectPickup('reactivation never rewrites ownership after transaction', count(array_filter($writes, function($sql) {
    return strpos($sql,'SET REQUESTED_BY=') !== false;
})), 0);
$writes=[]; $auth['REQUESTED_BY']=99;
expectPickup('another parent cannot reactivate', pickupFailure(function() use ($parent,$parentDb,$day) {
    SchoolSafePickupParentActivate(11,31,'Fixture',$parent,$parentDb,$day);
}), 'FORBIDDEN');
expectPickup('other-parent refusal made no write', $writes, []);
$writes=[]; $auth['REQUESTED_BY']=42; $changeOwnerOnLock=true;
expectPickup('ownership changed during reactivation is rechecked under lock', pickupFailure(function() use ($parent,$parentDb,$day) {
    SchoolSafePickupParentActivate(11,31,'Fixture',$parent,$parentDb,$day);
}), 'FORBIDDEN');
expectPickup('changed ownership never mutates authorization', array_values(array_filter($writes, function($sql) {
    return !in_array($sql,['BEGIN','COMMIT','ROLLBACK'],true);
})), []);
expectPickup('changed ownership rolls transaction back', end($writes), 'ROLLBACK');
echo json_encode(['pass'=>$pass,'fail'=>$fail]) . "\n";
exit($fail ? 1 : 0);
