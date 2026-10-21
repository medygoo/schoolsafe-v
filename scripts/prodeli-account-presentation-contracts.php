<?php
/** Presentation contracts only, isolated from live DB and identity services. */
declare(strict_types=1);
function add_action(...$args){}
function SchoolSafeScopeParse($s){if($s===null||$s==='')return null;if(!preg_match('/^,(?:[1-9][0-9]*,)+$/D',$s))return false;return array_map('intval',explode(',',trim($s,',')));}
function SchoolSafeAccountReady(){return $GLOBALS['ready'];}
function SchoolSafeAccountStates($u,$ids){if($GLOBALS['throws'])throw new RuntimeException('Unavailable');return $GLOBALS['states'];}
require (getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT') ?: '/work/php').'/plugins/SchoolSafe_Keycloak/Management.php';
$pass=0;$fail=0;
function check($name,$fn){global $pass,$fail;try{$fn();$pass++;echo "PASS $name\n";}catch(Throwable $e){$fail++;echo "FAIL $name: ".$e->getMessage()."\n";}}
function same($actual,$expected){if($actual!==$expected)throw new RuntimeException('Unexpected presentation');}
$row=['USERNAME'=>'fixture.school','PROFILE'=>'admin','SCHOOLS'=>',2,','SUBJECT'=>null,'STATE'=>null,'IDENTITY_ENABLED'=>null,'LAST_LOGIN'=>'2026-10-21'];
$GLOBALS['ready']=true;$GLOBALS['throws']=false;$GLOBALS['states']=[['school_id'=>2,'state'=>'active']];
check('native connected account is active, not awaiting identity link',fn()=>same(sskcAccountPresentation($row)['access'],'Actif'));
check('native connection identified without provisioning',fn()=>same(sskcAccountPresentation($row)['connection'],'Connexion native'));
check('native first login remains separate from activation',fn()=>same(sskcAccountPresentation(array_replace($row,['LAST_LOGIN'=>null]))['onboarding'],'Première connexion à effectuer'));
check('managed onboarding distinct from active account',fn()=>same(sskcAccountPresentation(array_replace($row,['SUBJECT'=>'fixture','STATE'=>'onboarding','IDENTITY_ENABLED'=>'Y']))['onboarding'],'Première connexion à terminer'));
check('disabled managed identity not presented as active',fn()=>same(sskcAccountPresentation(array_replace($row,['SUBJECT'=>'fixture','STATE'=>'active','IDENTITY_ENABLED'=>'N']))['access'],'Accès désactivé'));
check('managed suspension remains explicit with disabled native profile',fn()=>same(sskcAccountPresentation(array_replace($row,['SUBJECT'=>'fixture','STATE'=>'suspended','IDENTITY_ENABLED'=>'N','PROFILE'=>'none']))['access'],'Suspendu'));
check('global suspension takes precedence over a local active row',function()use($row){$GLOBALS['states']=[['school_id'=>0,'state'=>'suspended'],['school_id'=>2,'state'=>'active']];same(sskcAccountPresentation($row)['access'],'Suspendu');$GLOBALS['states']=[['school_id'=>2,'state'=>'active']];});
check('native no-access profile retained',fn()=>same(sskcAccountPresentation(array_replace($row,['PROFILE'=>'none']))['access'],'Sans accès — profil désactivé'));
check('mixed per-school states do not claim full suspension',function()use($row){$GLOBALS['states']=[['school_id'=>2,'state'=>'suspended'],['school_id'=>3,'state'=>'active']];same(sskcAccountPresentation(array_replace($row,['SCHOOLS'=>',2,3,']))['access'],'Accès restreint selon l’école');});
check('uniform suspension visible',function()use($row){$GLOBALS['states']=[['school_id'=>2,'state'=>'suspended']];same(sskcAccountPresentation($row)['access'],'Suspendu');});
check('missing account-state support is not advertised active',function()use($row){$GLOBALS['ready']=false;same(sskcAccountPresentation($row)['access'],'État à vérifier');$GLOBALS['ready']=true;});
check('query failure is not advertised active',function()use($row){$GLOBALS['throws']=true;same(sskcAccountPresentation($row)['access'],'État à vérifier');$GLOBALS['throws']=false;});
check('invalid scope is visible as a problem',fn()=>same(sskcAccountPresentation(array_replace($row,['SCHOOLS'=>'invalid']))['access'],'Périmètre à vérifier'));
check('school names replace numeric-only scope',fn()=>same(sskcAccountSchoolNames(',2,',[2=>'École fictive']),'École fictive'));
check('missing school name never implies a known school',fn()=>same(sskcAccountSchoolNames(',3,',[]),'École #3 — à vérifier'));
check('global account remains explicitly central',fn()=>same(sskcAccountSchoolNames('',[]),'Équipe centrale — toutes les écoles'));
echo json_encode(['passed'=>$pass,'failed'=>$fail])."\n";exit($fail?1:0);
