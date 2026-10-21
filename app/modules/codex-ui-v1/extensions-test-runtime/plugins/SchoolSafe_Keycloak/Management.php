<?php
declare(strict_types=1);

/** Read-only presentation; never provisions an identity or changes permissions. */
function sskcAccountSchoolNames($scope,array $schools): string
{
    $ids=SchoolSafeScopeParse($scope);
    if($ids===null)return 'Équipe centrale — toutes les écoles';
    if($ids===false)return 'Périmètre à vérifier';
    return implode(' · ',array_map(static fn($id)=>(string)($schools[$id]??('École #'.$id.' — à vérifier')),$ids));
}
function sskcAccountPresentation(array $row): array
{
    $managed=!empty($row['SUBJECT']);$scope=SchoolSafeScopeParse($row['SCHOOLS']??null);
    $result=['connection'=>$managed?'Connexion gérée':'Connexion native','access'=>'État à vérifier','onboarding'=>''];
    if($scope===false){$result['access']='Périmètre à vérifier';return $result;}
    if($managed && ($row['STATE']??'')==='suspended'){$result['access']='Suspendu';return $result;}
    if(($row['PROFILE']??'')!=='admin'){$result['access']='Sans accès — profil désactivé';return $result;}
    if($managed && ($row['IDENTITY_ENABLED']??'')!=='Y'){$result['access']='Accès désactivé';return $result;}
    if($managed && !in_array($row['STATE']??'',['active','onboarding'],true))return $result;
    try {
        if(!SchoolSafeAccountReady())return $result;
        $entries=SchoolSafeAccountStates((string)$row['USERNAME'],$scope??[]);
        $global='active';$states=[];
        foreach($entries as $entry){if((int)$entry['school_id']===0)$global=$entry['state'];else $states[]=$entry['state'];}
        if(in_array($global,['suspended','expired'],true))$states=[$global];
        elseif($global==='readonly')$states=array_map(static fn($s)=>$s==='active'?'readonly':$s,$states?:[$global]);
        $states=array_values(array_unique($states?:[$global]));
        $labels=['active'=>'Actif','readonly'=>'Consultation seule','suspended'=>'Suspendu','expired'=>'Expiré'];
        foreach($states as $state)if(!isset($labels[$state]))return $result;
        $result['access']=count($states)>1?'Accès restreint selon l’école':$labels[$states[0]];
    }catch(Throwable $e){return $result;}
    if($managed && $row['STATE']==='onboarding')$result['onboarding']='Première connexion à terminer';
    elseif(!$managed && empty($row['LAST_LOGIN']))$result['onboarding']='Première connexion à effectuer';
    return $result;
}

function sskcCanRead(): bool
{
    return User('PROFILE') === 'admin' && AllowUse('Users/User.php')
        && SchoolSafeScopeParse(User('SCHOOLS')) !== false;
}
function sskcCanWrite(bool $create = false): bool
{
    return sskcCanRead() && AllowEdit($create ? 'Users/User.php&staff_id=new' : 'Users/User.php')
        && (!$create || AllowUse('Users/User.php&staff_id=new'))
        && (!$create || SchoolSafeProfileDelegable('1'))
        && AllowEdit('Users/User.php&category_id=1&user_profile')
        && AllowEdit('Users/User.php&category_id=1&schools');
}
function sskcCsrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        || !is_string($_POST['token'] ?? null)
        || !hash_equals((string)($_SESSION['token'] ?? ''), $_POST['token'])) {
        throw new RuntimeException('Requête expirée. Rechargez la page.');
    }
}
function sskcEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
function sskcAudit(string $action, int $staff, string $result): void
{
    DBQuery("INSERT INTO schoolsafe_identity_audit(actor_staff_id,target_staff_id,action,result) VALUES("
        . (int)User('STAFF_ID') . ',' . $staff . ",'" . DBEscapeString($action) . "','" . DBEscapeString($result) . "')");
}
function sskcManagement(string $method, string $path, ?array $data = null): array
{
    static $token = null;
    $config = schoolSafeKeycloakConfig();
    if (!preg_match('~^users(?:\?username=[A-Za-z0-9%._-]+&exact=true|/[a-f0-9-]+(?:/reset-password|/logout|/role-mappings/realm)?)?$~D', $path)) {
        throw new RuntimeException('Opération non autorisée.');
    }
    if ($token === null) {
        $reply = schoolSafeKeycloakRequest($config['issuer'].'/protocol/openid-connect/token', [
            'grant_type'=>'client_credentials','client_id'=>$config['management_client_id'],
            'client_secret'=>$config['management_client_secret']]);
        $token = $reply['access_token'] ?? null;
        if (!is_string($token)) throw new RuntimeException('Gestion des identités indisponible.');
    }
    $policy=schoolSafeIdentityEnvironment($config['environment']??'production',
        ($config['environment']??'production')==='local' && schoolSafeIdentityLocalProof($config));
    $curl = curl_init($policy['admin_base'].$path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,
        CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_PROTOCOLS=>$policy['https_only']?CURLPROTO_HTTPS:CURLPROTO_HTTP,
        CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Content-Type: application/json','Accept: application/json']]);
    if ($data !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data, JSON_THROW_ON_ERROR));
    $body = curl_exec($curl);$status = curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
    if (!is_string($body) || !in_array($status,[200,201,204],true)) {
        throw new RuntimeException('Service de connexion indisponible ou opération non autorisée.');
    }
    return $body === '' ? [] : json_decode($body,true,64,JSON_THROW_ON_ERROR);
}
function sskcProvisionLoginPolicy(string $subject, bool $client): void
{
    $role=schoolSafeKeycloakConfig()['client_password_role']??null;
    if(!is_array($role) || ($role['name']??'')!=='schoolsafe-client-password') throw new RuntimeException('Politique de connexion non disponible.');
    if($client) sskcManagement('POST','users/'.$subject.'/role-mappings/realm',[$role]);
    $actions=$client?['UPDATE_PROFILE','UPDATE_PASSWORD']:['UPDATE_PASSWORD','webauthn-register'];
    sskcManagement('PUT','users/'.$subject,['requiredActions'=>$actions,'enabled'=>true]);
}
function sskcTarget(int $id, bool $write = false): array
{
    if (!sskcCanRead() || ($write && !sskcCanWrite()) || !SchoolSafeStaffWithinScope($id)) {
        throw new RuntimeException('Ce compte ne relève pas de vos autorisations.');
    }
    $rows = DBGet('SELECT s.*,l.subject,l.state,l.enabled AS IDENTITY_ENABLED FROM staff s'
        . ' LEFT JOIN schoolsafe_identity_links l ON l.staff_id=s.staff_id'
        . ' WHERE s.staff_id='.$id.' AND s.syear='.(int)UserSyear());
    $row=$rows[1]??[];
    if (!$row || !in_array($row['PROFILE'],['admin','none'],true)
        || ($write && (!SchoolSafeProfileDelegable((string)$row['PROFILE_ID']) || (int)UserSyear()!==(int)Config('SYEAR')))) {
        throw new RuntimeException('Compte ou profil non déléguable pour cette année.');
    }
    return $row;
}
function sskcValidateIntent(array $intent): void
{
    if (!sskcCanWrite(true) || (int)UserSyear()!==(int)Config('SYEAR')
        || (int)$intent['actor']!==(int)User('STAFF_ID') || (int)$intent['year']!==(int)UserSyear()
        || $intent['expires']<time()) throw new RuntimeException('Création non autorisée ou confirmation expirée.');
    $profile=DBGetOne('SELECT PROFILE FROM user_profiles WHERE id='.(int)$intent['profile']);
    if ($profile!=='admin' || !SchoolSafeProfileDelegable((string)$intent['profile'])) {
        throw new RuntimeException('Ce profil ne peut pas être attribué.');
    }
    $scope=SchoolSafeScopeParse($intent['schools']);
    if (!SchoolSafeScopeDelegates(SchoolSafeScopeParse(User('SCHOOLS')),$scope)
        || ($scope===null && !SchoolSafeGlobalAdmin())) throw new RuntimeException('Périmètre scolaire non autorisé.');
    foreach ($scope??[] as $school) {
        if (!DBGetOne('SELECT id FROM schools WHERE id='.(int)$school.' AND syear='.(int)UserSyear())) {
            throw new RuntimeException('École inconnue pour cette année.');
        }
    }
}
function sskcPrepareIntent(): array
{
    sskcCsrf();
    $get=static function(string $key,int $max): string {
        $value=trim((string)($_POST[$key]??''));
        if ($value==='' || mb_strlen($value)>$max) throw new RuntimeException('Identité incomplète ou champ trop long.');
        return $value;
    };
    $username=$get('username',100);$email=$get('email',254);
    if (!preg_match('/^[A-Za-z0-9@._+-]+$/D',$username) || !filter_var($email,FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Identifiant ou adresse e-mail non valide.');
    }
    $mode=$_POST['scope_mode']??'';$schools='';
    if ($mode==='schools') {
        $chosen=$_POST['schools']??[];
        if (!is_array($chosen) || !$chosen) throw new RuntimeException('Sélectionnez au moins une école.');
        $ids=[];
        foreach($chosen as $id) {
            if (!is_string($id) || !ctype_digit($id) || (int)$id<1) throw new RuntimeException('École non valide.');
            $ids[]=(int)$id;
        }
        $ids=array_values(array_unique($ids));sort($ids);$schools=','.implode(',',$ids).',';
    } elseif ($mode!=='global') throw new RuntimeException('Choisissez explicitement le périmètre.');
    $intent=['id'=>bin2hex(random_bytes(24)),'actor'=>(int)User('STAFF_ID'),'year'=>(int)UserSyear(),
        'expires'=>time()+600,'first'=>$get('first_name',50),'last'=>$get('last_name',50),
        'username'=>$username,'email'=>$email,'profile'=>(int)($_POST['profile_id']??0),'schools'=>$schools,
        'client_login'=>$mode!=='global' && ($_POST['login_population']??'client')==='client'];
    if(!empty($_POST['sskc_wizard_id'])) {
        $d=sskcWizardDraft();$a=$d['data'];
        if(!sskcWizardAllowed() || !hash_equals($d['id'],(string)$_POST['sskc_wizard_id']) || empty($d['school']) || !empty($d['staff'])
            || $intent['schools']!==','.(int)$d['school'].',' || !$intent['client_login']
            || $intent['username']!==$a['username'] || $intent['email']!==$a['email']
            || $intent['first']!==$a['first_name'] || $intent['last']!==$a['last_name'] || $intent['profile']!==(int)$a['profile_id']) {
            throw new RuntimeException('Le compte doit correspondre au responsable confirmé dans le dossier d’école.');
        }
        $intent['wizard_id']=$d['id'];
        $intent['middle']=(string)($a['middle_name']??'');$intent['phone']=(string)($a['admin_phone']??'');
    }
    sskcValidateIntent($intent);
    $native=DBGetOne("SELECT 1 FROM staff WHERE syear=".(int)UserSyear()." AND UPPER(username)=UPPER('".DBEscapeString($username)."')");
    if ($native) throw new RuntimeException('Cet identifiant existe déjà dans SchoolSafe.');
    if (sskcManagement('GET','users?username='.rawurlencode($username).'&exact=true')) {
        throw new RuntimeException('Cet identifiant existe déjà dans le service de connexion. Aucune liaison automatique effectuée.');
    }
    $_SESSION['sskc_intent']=$intent;
    return $intent;
}

function sskcBeforeNativeCreate(): void
{
    global $error;
    if (empty($_REQUEST['sskc_intent'])) return;
    try {
        $intent=$_SESSION['sskc_intent']??null;
        if (!is_array($intent) || !hash_equals($intent['id'],(string)$_REQUEST['sskc_intent'])) {
            throw new RuntimeException('Confirmation de création invalide.');
        }
        sskcValidateIntent($intent);
        if (($_REQUEST['staff']['USERNAME']??'')!==$intent['username']
            || ($_REQUEST['staff']['EMAIL']??'')!==$intent['email']
            || ($_REQUEST['staff']['PROFILE']??'')!=='admin'
            || ($_REQUEST['staff']['SCHOOLS']??'')!==$intent['schools']) {
            throw new RuntimeException('Les paramètres confirmés ont été modifiés.');
        }
        unset($_SESSION['sskc_intent']);
        $GLOBALS['sskc_active_intent']=$intent;
        DBQuery('BEGIN');
        DBQuery('SELECT pg_advisory_xact_lock(hashtext(\'schoolsafe-provision\'))');
        $_REQUEST['staff']['PASSWORD']=$_POST['staff']['PASSWORD']=bin2hex(random_bytes(32));
        $GLOBALS['sskc_transaction_open']=true;
    } catch(Throwable $e) {$error[]=$e->getMessage();}
}
function sskcAfterNativeCreate(): void
{
    if (empty($GLOBALS['sskc_active_intent'])) return;
    $intent=$GLOBALS['sskc_active_intent'];$id=(int)UserStaffID();$subject=null;
    try {
        sskcValidateIntent($intent);
        $row=sskcTarget($id);
        if ($row['USERNAME']!==$intent['username'] || (string)$row['SCHOOLS']!==$intent['schools']) {
            throw new RuntimeException('Le compte créé ne correspond pas à la confirmation.');
        }
        // Native creation deliberately assigns the default profile first.
        // Apply the explicitly validated custom profile only after native creation.
        DBUpdate('staff',['PROFILE_ID'=>(int)$intent['profile']],['STAFF_ID'=>$id,'SYEAR'=>(int)UserSyear()]);
        if (sskcManagement('GET','users?username='.rawurlencode($intent['username']).'&exact=true')) {
            throw new RuntimeException('Identifiant de connexion déjà utilisé.');
        }
        sskcManagement('POST','users',['username'=>$intent['username'],'email'=>$intent['email'],
            'firstName'=>$intent['first'],'lastName'=>$intent['last'],'enabled'=>false,'emailVerified'=>false]);
        $users=sskcManagement('GET','users?username='.rawurlencode($intent['username']).'&exact=true');
        if(count($users)!==1) throw new RuntimeException('Identité créée non retrouvée.');
        $subject=$users[0]['id'];$temporary='Aa9!'.bin2hex(random_bytes(14));
        sskcManagement('PUT','users/'.$subject.'/reset-password',['type'=>'password','temporary'=>true,'value'=>$temporary]);
        sskcProvisionLoginPolicy($subject,!empty($intent['client_login']) && SchoolSafeScopeParse($intent['schools'])!==null);
        DBInsert('schoolsafe_identity_links',['STAFF_ID'=>$id,'ISSUER'=>DBEscapeString(schoolSafeKeycloakConfig()['issuer']),
            'SUBJECT'=>DBEscapeString($subject),'STATE'=>'onboarding','ENABLED'=>'Y','CREATED_BY'=>(int)User('STAFF_ID')]);
        sskcAudit('create',$id,'success');
        DBQuery('COMMIT');$GLOBALS['sskc_transaction_open']=false;
        $_SESSION['sskc_handoff'][$id]=['password'=>$temporary,'actor'=>(int)User('STAFF_ID'),'expires'=>time()+600,'client'=>!empty($intent['client_login'])];
        echo '<p class="success">Compte SchoolSafe et identité de connexion créés. '
            .'<a href="/schoolsafe-administrators.php?credential='.$id.'">Afficher une fois le mot de passe temporaire</a></p>';
    } catch(Throwable $e) {
        if($subject!==null) {try {sskcManagement('PUT','users/'.$subject,['enabled'=>false]);}catch(Throwable $ignored){}}
        DBQuery('ROLLBACK');$GLOBALS['sskc_transaction_open']=false;
        sskcAudit('create',$id,'failed');
        SetUserStaffID('');
        echo ErrorMessage(['La création complète n’a pas abouti. Le compte SchoolSafe a été annulé ; aucun accès n’a été remis. Une éventuelle identité de connexion non liée doit être vérifiée dans la gestion des identités.']);
        exit;
    }
}

function sskcExistingAction(string $action,int $id): void
{
    sskcCsrf();$row=sskcTarget($id,true);
    if($action==='link') {
        if($row['PROFILE']!=='admin' || !empty($row['SUBJECT'])) throw new RuntimeException('Le compte doit être un administrateur non lié.');
        if(sskcManagement('GET','users?username='.rawurlencode($row['USERNAME']).'&exact=true')) {
            throw new RuntimeException('Identité déjà présente dans le service de connexion : une liaison existante doit être vérifiée explicitement.');
        }
        $subject=null;
        try {
            sskcManagement('POST','users',['username'=>$row['USERNAME'],'email'=>$row['EMAIL'],
                'firstName'=>$row['FIRST_NAME'],'lastName'=>$row['LAST_NAME'],'enabled'=>false,'emailVerified'=>false]);
            $users=sskcManagement('GET','users?username='.rawurlencode($row['USERNAME']).'&exact=true');
            if(count($users)!==1)throw new RuntimeException('Identité non retrouvée après création.');
            $subject=$users[0]['id'];$password='Aa9!'.bin2hex(random_bytes(14));
            sskcManagement('PUT','users/'.$subject.'/reset-password',['type'=>'password','temporary'=>true,'value'=>$password]);
            sskcProvisionLoginPolicy($subject,SchoolSafeScopeParse($row['SCHOOLS'])!==null);
            DBInsert('schoolsafe_identity_links',['STAFF_ID'=>$id,'ISSUER'=>DBEscapeString(schoolSafeKeycloakConfig()['issuer']),
                'SUBJECT'=>DBEscapeString($subject),'STATE'=>'onboarding','ENABLED'=>'Y','CREATED_BY'=>(int)User('STAFF_ID')]);
            $_SESSION['sskc_handoff'][$id]=['password'=>$password,'actor'=>(int)User('STAFF_ID'),'expires'=>time()+600,'client'=>SchoolSafeScopeParse($row['SCHOOLS'])!==null];
            sskcAudit('link_existing',$id,'success');
            return;
        }catch(Throwable $e){
            if($subject!==null){try{sskcManagement('PUT','users/'.$subject,['enabled'=>false]);}catch(Throwable $ignored){}}
            sskcAudit('link_existing',$id,'failed');throw $e;
        }
    }
    if (empty($row['SUBJECT']) || !preg_match('/^[a-f0-9-]+$/D',$row['SUBJECT'])) throw new RuntimeException('Ce compte n’a pas de liaison de connexion.');
    if($id===(int)User('STAFF_ID')) throw new RuntimeException('Utilisez votre espace personnel pour votre propre compte.');
    $path='users/'.$row['SUBJECT'];
    if($action==='temporary') {
        if($row['PROFILE']!=='admin' || $row['IDENTITY_ENABLED']!=='Y') throw new RuntimeException('Réactivez le compte avant de remettre un nouvel accès.');
        $password='Aa9!'.bin2hex(random_bytes(14));
        sskcManagement('PUT',$path.'/reset-password',['type'=>'password','temporary'=>true,'value'=>$password]);
        sskcManagement('POST',$path.'/logout');
        DBUpdate('schoolsafe_identity_links',['STATE'=>'onboarding'],['STAFF_ID'=>$id]);
        $_SESSION['sskc_handoff'][$id]=['password'=>$password,'actor'=>(int)User('STAFF_ID'),'expires'=>time()+600];
    } elseif($action==='suspend') {
        $global=SchoolSafeScopeParse($row['SCHOOLS'])===null;
        if($global && (int)DBGetOne("SELECT count(*) FROM staff WHERE profile='admin' AND (schools IS NULL OR schools='') AND syear=".(int)UserSyear())<=1) {
            throw new RuntimeException('Le dernier administrateur global ne peut pas être suspendu.');
        }
        // Revoke external access first. A remote failure leaves native state unchanged.
        sskcManagement('PUT',$path,['enabled'=>false]);sskcManagement('POST',$path.'/logout');
        DBQuery('BEGIN');
        DBUpdate('schoolsafe_identity_links',['ENABLED'=>'N','STATE'=>'suspended'],['STAFF_ID'=>$id]);
        DBUpdate('staff',['PROFILE'=>'none'],['STAFF_ID'=>$id,'SYEAR'=>(int)UserSyear()]);
        DBQuery('COMMIT');
    } elseif($action==='resume') {
        if($row['STATE']!=='suspended' || $row['PROFILE']!=='none') throw new RuntimeException('État de compte incompatible avec une réactivation.');
        if(DBGetOne('SELECT PROFILE FROM user_profiles WHERE id='.(int)$row['PROFILE_ID'])!=='admin') throw new RuntimeException('Profil administrateur requis.');
        $remote=sskcManagement('GET',$path);
        $newstate=empty($remote['requiredActions'])?'active':'onboarding';
        // Re-enable native scope only for the explicitly checked profile and year.
        DBQuery('BEGIN');
        DBUpdate('staff',['PROFILE'=>'admin'],['STAFF_ID'=>$id,'SYEAR'=>(int)UserSyear()]);
        DBUpdate('schoolsafe_identity_links',['ENABLED'=>'Y','STATE'=>$newstate],['STAFF_ID'=>$id]);
        try {sskcManagement('PUT',$path,['enabled'=>true]);DBQuery('COMMIT');}
        catch(Throwable $e){DBQuery('ROLLBACK');throw $e;}
    } else throw new RuntimeException('Action inconnue.');
    sskcAudit($action,$id,'success');
}

function sskcManagerNavigation(): void
{
    if(basename((string)($_SERVER['SCRIPT_NAME']??''))==='schoolsafe-administrators.php')return;
    if(sskcCanRead()) echo '<p><a href="/schoolsafe-administrators.php?opening=1">'.(SchoolSafeGlobalAdmin()?'Gestion centrale SchoolSafe':'Gestion de votre école').'</a> · <a href="/schoolsafe-administrators.php">Administrateurs et accès</a></p>';
}

add_action('Users/User.php|create_user_checks','sskcBeforeNativeCreate');
add_action('Users/User.php|create_user','sskcAfterNativeCreate');
add_action('Warehouse.php|header','sskcManagerNavigation');
function sskcNativeLoginComplete($tag,$attempted): void
{
    $auth=$_SESSION['schoolsafe-keycloak-session']??null;
    if(!is_array($auth))return;
    if(empty($_SESSION['STAFF_ID'])) {unset($_SESSION['schoolsafe-keycloak-session']);return;}
    $rows=DBGet('SELECT STAFF_ID,STATE FROM schoolsafe_identity_links WHERE SUBJECT=\''
        .DBEscapeString($auth['subject']).'\' AND ENABLED=\'Y\'');
    if($rows && (int)$rows[1]['STAFF_ID']===(int)$_SESSION['STAFF_ID'] && $rows[1]['STATE']==='onboarding') {
        // Keycloak has already completed its mandatory credential actions.
        // Suppress only Rosario's duplicate first-password prompt for this
        // successfully authenticated identity; index.php records LAST_LOGIN itself.
        if(empty($_SESSION['LAST_LOGIN'])) $_SESSION['LAST_LOGIN']=date('Y-m-d H:i:s');
        if(!empty($auth['client_password_only'])) {
            $profile=$auth['profile']??[];
            if(!empty($profile['first']) && !empty($profile['last']) && mb_strlen($profile['first'])<=50
                && mb_strlen($profile['last'])<=50 && filter_var($profile['email']??'',FILTER_VALIDATE_EMAIL)) {
                DBUpdate('staff',['FIRST_NAME'=>DBEscapeString($profile['first']),'LAST_NAME'=>DBEscapeString($profile['last']),
                    'EMAIL'=>DBEscapeString($profile['email'])],['STAFF_ID'=>(int)$_SESSION['STAFF_ID'],'SYEAR'=>(int)Config('SYEAR')]);
            }
        }
        DBUpdate('schoolsafe_identity_links',['STATE'=>'active'],['STAFF_ID'=>(int)$_SESSION['STAFF_ID']]);
        sskcAudit('first_login',(int)$_SESSION['STAFF_ID'],'success');
    }
}
add_action('index.php|login_check','sskcNativeLoginComplete',2);
function sskcManagedNativePassword($tag,&$crypted,$plain): void
{
    global $username;
    if(!is_string($username??null)) return;
    if(!empty($GLOBALS['sskc_accepted_native_ticket'])
        && hash_equals($GLOBALS['sskc_accepted_native_ticket'],hash('sha256',(string)$plain))) return;
    // LOT L2 : service non configure (ni secret, ni liaison) -> ne jamais
    // bloquer l'authentification native (constat isole : 500 a chaque login).
    // Quand le fournisseur est configure, la neutralisation ci-dessous reste
    // integrale pour les comptes geres.
    try { $config=schoolSafeKeycloakConfig(); }
    catch(Throwable $e) { return; }
    $managed=DBGet('SELECT s.STAFF_ID FROM staff s JOIN schoolsafe_identity_links l ON l.STAFF_ID=s.STAFF_ID'
        . ' WHERE s.SYEAR='.(int)Config('SYEAR')." AND UPPER(s.USERNAME)=UPPER('".DBEscapeString($username)."')");
    if($managed && !in_array((int)$managed[1]['STAFF_ID'],$config['native_fallback_staff_ids']??[],true)) {
        // An independently reset native password must not bypass the managed MFA.
        $crypted=encrypt_password(bin2hex(random_bytes(32)));
    }
}
add_action('functions/Password.php|match_password','sskcManagedNativePassword',3);
register_shutdown_function(static function(): void {
    if(!empty($GLOBALS['sskc_transaction_open'])) {DBQuery('ROLLBACK');}
});
