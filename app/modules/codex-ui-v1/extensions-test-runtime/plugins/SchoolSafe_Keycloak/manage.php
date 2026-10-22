<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
chdir(dirname(__DIR__,2));
require_once 'Warehouse.php';
require_once __DIR__.'/functions.php';
if (empty($_SESSION['STAFF_ID'])) {header('Location: /index.php');exit;}
if (!sskcCanRead()) {SchoolSafeScopeDeny();exit;}
$showCentral=($_GET['accounts']??'')==='central';
if($showCentral && !SchoolSafeGlobalAdmin()){SchoolSafeScopeDeny();exit;}
if(($_SERVER['REQUEST_METHOD']??'')==='POST' && ($_POST['action']??'')==='dashboard_school') {
    $level=ob_get_level();
    try {
        $dashboardSchool=sskcDashboardSchoolTarget();
        // Only the validated school reaches the existing native controller.
        // Never forward a client-supplied year, student, staff or module context.
        $_REQUEST=['sidefunc'=>'update','school'=>(string)$dashboardSchool];
        ob_start();require dirname(__DIR__,2).'/Side.php';ob_end_clean();
        if((int)UserSchool()!==$dashboardSchool)throw new RuntimeException('Le changement d’école n’a pas été confirmé.');
        header('Location: /Modules.php?modname=misc/Portal.php',true,303);exit;
    } catch(Throwable $e) {
        while(ob_get_level()>$level)ob_end_clean();
        http_response_code(403);sskcUiStart('Ouverture de l’espace scolaire');
        echo '<p role="alert">L’école n’a pas pu être ouverte. Rechargez le tableau de bord et vérifiez votre accès.</p><p><a href="/schoolsafe-administrators.php?home=1">Retour au tableau de bord</a></p>';
        sskcUiEnd();exit;
    }
}
if(isset($_GET['home'])) sskcHomePage();
if(isset($_GET['wizard'])) sskcWizardPage();
if(isset($_GET['opening'])) sskcOpeningPage();
$message='';$failure='';$intent=null;
try {
    if(($_SERVER['REQUEST_METHOD']??'')==='POST') {
        $action=(string)($_POST['action']??'');
        if($action==='prepare') $intent=sskcPrepareIntent();
        elseif($action==='native_create') {
            $created=sskcConfirmNativeDirector();
            header('Location: /schoolsafe-administrators.php?credential='.$created,true,303);exit;
        }
        else {
            $target=(int)($_POST['staff_id']??0);
            if(($_POST['confirmed']??'')!=='1')sskcActionReview($action,$target);
            if($action==='native_temporary') {
                $renewed=sskcRenewNativeDirector();
                header('Location: /schoolsafe-administrators.php?credential='.$renewed,true,303);exit;
            }
            sskcExistingAction($action,$target);
            if(in_array($action,['temporary','link'],true)) {header('Location: /schoolsafe-administrators.php?credential='.$target);exit;}
            $message=$action==='suspend'?'Compte suspendu dans SchoolSafe.':'Compte réactivé dans SchoolSafe.';
        }
    }
} catch(Throwable $e) {$failure=$e->getMessage();}
if(isset($_GET['credential'])) {
    // Keep the temporary credential away from the application's global scripts.
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; font-src 'self'; base-uri 'none'; frame-ancestors 'none'");
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Accès temporaire SchoolSafe</title><link rel="stylesheet" href="/assets/css/schoolsafe-access.css?v=1"></head><body><main class="ss-access-shell ss-access-handoff"><header class="ss-access-header"><span class="ss-access-brand"><span class="ss-access-emblem"><img src="/schoolsafe-logo.png" alt="" width="48" height="52"></span><span><strong>SchoolSafe</strong><small>Remise confidentielle</small></span></span><nav aria-label="Retour à la gestion"><a href="/schoolsafe-administrators.php">Administrateurs</a></nav></header><section class="ss-access-card">';
    try {
        $id=(int)$_GET['credential'];$row=sskcTarget($id,true);
        $handoff=$_SESSION['sskc_handoff'][$id]??null;
        if(!$handoff || $handoff['expires']<time() || $handoff['actor']!==(int)User('STAFF_ID')) {
            throw new RuntimeException('Cet accès temporaire a déjà été affiché ou a expiré. Vous pouvez en générer un nouveau depuis la liste.');
        }
        unset($_SESSION['sskc_handoff'][$id]);
        sskcAudit('temporary_password_display',$id,'success');
        echo '<h1>Accès à remettre confidentiellement</h1><p>Ce mot de passe est affiché une seule fois. Remettez-le directement à la personne concernée.</p>';
        echo '<dl><dt>Identifiant</dt><dd>'.sskcEscape($row['USERNAME']).'</dd><dt>Mot de passe temporaire</dt><dd><code>'
            .sskcEscape($handoff['password']).'</code></dd></dl>';
        $client=!empty($handoff['client']) || is_array(SchoolSafeScopeParse($row['SCHOOLS']));
        $native=!empty($handoff['native']);
        $login=$native?'https://app.shoolsafe.tech/index.php':'https://schoolsafe.179-198-195-15.sslip.io/plugins/SchoolSafe_Keycloak/endpoint.php';
        if($native)echo '<p>Connexion native SchoolSafe. Mot de passe initial valable 24 heures à compter de la création ; choix d’un mot de passe personnel obligatoire avant tout accès scolaire.</p>';
        if(!empty($handoff['photo_warning']))echo '<p role="alert">Le portrait n’a pas pu être enregistré. Reprenez-le dans la fiche du responsable ; le compte n’est pas à recréer.</p>';
        $steps=$native?'Connectez-vous à SchoolSafe et choisissez un mot de passe personnel de 12 à 128 caractères, différent du temporaire. Votre école s’ouvrira ensuite. Aucun QR code ni second facteur obligatoire.':($client?'Complétez vos informations, choisissez un mot de passe personnel, puis ouvrez votre école. Aucun QR code ni Windows Hello obligatoire.':'Choisissez un mot de passe personnel et suivez l’authentification renforcée de l’équipe SchoolSafe.');
        $document="SchoolSafe — fiche confidentielle\nÀ remettre uniquement à la personne concernée.\n\nIdentifiant : ".$row['USERNAME']."\nMot de passe temporaire : ".$handoff['password']."\nConnexion : ".$login."\n\n".$steps."\nLe mot de passe personnel doit respecter les exigences affichées. Utilisez votre navigateur personnel ; si un autre compte est connecté, ouvrez une fenêtre privée.\nAprès la première connexion, détruisez cette fiche.\n";
        echo '<p><a class="sskc-action button-primary" download="SchoolSafe-acces-'.$id.'.txt" href="data:text/plain;charset=utf-8;base64,'.base64_encode($document).'">Télécharger la fiche d’accès</a></p><p>Enregistrez le fichier sur votre téléphone ou ordinateur, puis remettez-le confidentiellement à cette personne. Aucun e-mail n’est envoyé automatiquement. Le fichier téléchargé contient un secret et reste présent sur votre appareil jusqu’à sa suppression.</p>';
        echo '<p>Connexion : <a href="/index.php">SchoolSafe — Connexion administrateur</a>. '
            .($client?'Responsable d’école : compléter vos informations personnelles et choisir un mot de passe personnel. Aucun QR code ni Windows Hello obligatoire pour le parcours client.'
            :'Mot de passe personnel à choisir. L’authentification renforcée reste applicable aux comptes de l’équipe SchoolSafe.').'</p>';
        echo '<p><a href="/schoolsafe-administrators.php">Revenir à la gestion des administrateurs</a></p>';
    }catch(Throwable $e){echo '<p role="alert">'.sskcEscape($e->getMessage()).'</p>';}
    echo '<p class="ss-access-return"><a href="/schoolsafe-administrators.php">Retour aux administrateurs</a></p></section></main></body></html>';exit;
}
$_REQUEST['modname']='Users/User.php';$_ROSARIO['page']='modules';
sskcUiStart($showCentral?'Comptes de l’équipe centrale':'Administrateurs scolaires');
echo '<p><a href="/schoolsafe-administrators.php?opening=1">'.(SchoolSafeGlobalAdmin()?'Gestion centrale — ouvertures d’écoles':'Gestion de votre école').'</a></p>';
echo '<p>Créez un compte, attribuez son profil SchoolSafe et ses écoles, puis remettez son accès temporaire. Les droits existants restent applicables.</p>';
if($message!=='')echo '<p role="status">'.sskcEscape($message).'</p>';
if($failure!=='')echo '<p role="alert">'.sskcEscape($failure).'</p>';
$csrf=sskcEscape((string)$_SESSION['token']);
if($intent!==null) {
    $title=DBGetOne('SELECT TITLE FROM user_profiles WHERE id='.(int)$intent['profile']);
    $reviewSchools=[];
    foreach((array)DBGet('SELECT ID,TITLE FROM schools WHERE SYEAR='.(int)UserSyear()) as $reviewSchool)
        if(SchoolSafeSchoolAllowed((string)$reviewSchool['ID']))$reviewSchools[(int)$reviewSchool['ID']]=$reviewSchool['TITLE'];
    echo '<h2>Confirmer la création</h2><dl><dt>Personne</dt><dd>'.sskcEscape($intent['first'].' '.$intent['last'])
        .'</dd><dt>Identifiant</dt><dd>'.sskcEscape($intent['username']).'</dd><dt>Profil</dt><dd>'.sskcEscape($title)
        .'</dd><dt>Écoles</dt><dd>'.sskcEscape(sskcAccountSchoolNames($intent['schools'],$reviewSchools)).'</dd></dl>';
    echo '<p>SchoolSafe vérifiera les champs et les permissions avant la création. Aucun accès SchoolSafe ne sera remis si la création complète échoue.</p>';
    if(!empty($intent['native_director'])) {
        echo '<p>Connexion native, une seule école. Rôle : '.(!empty($intent['principal'])?'principal (aucun remplacement automatique)':'adjoint si un principal existe, sinon principal').'.</p>';
        echo '<form target="_self" method="post" action="/schoolsafe-administrators.php">';
        foreach(['action'=>'native_create','token'=>$_SESSION['token'],'confirmed'=>'1','sskc_intent'=>$intent['id']] as $name=>$value)
            echo '<input type="hidden" name="'.sskcEscape($name).'" value="'.sskcEscape((string)$value).'">';
        echo '<input type="submit" class="button-primary" value="Créer le compte et préparer l’accès"></form><p><a href="/schoolsafe-administrators.php">Annuler</a></p>';
        sskcUiEnd();exit;
    }
    echo '<form target="_self" method="post" action="/Modules.php?modname=Users/User.php&amp;modfunc=update&amp;staff_id=new">';
    $fields=['token'=>$_SESSION['token'],'sskc_intent'=>$intent['id'],'staff[FIRST_NAME]'=>$intent['first'],
        'staff[LAST_NAME]'=>$intent['last'],'staff[USERNAME]'=>$intent['username'],'staff[EMAIL]'=>$intent['email'],'staff[PROFILE]'=>'admin'];
    if(isset($intent['middle']))$fields['staff[MIDDLE_NAME]']=$intent['middle'];
    if(isset($intent['phone']))$fields['staff[CUSTOM_200000001]']=$intent['phone'];
    $scope=SchoolSafeScopeParse($intent['schools']);
    if($scope===null)$fields['staff[SCHOOLS][]']='';
    else foreach($scope as $id)$fields['staff[SCHOOLS]['.$id.']']='Y';
    foreach($fields as $name=>$value)echo '<input type="hidden" name="'.sskcEscape($name).'" value="'.sskcEscape((string)$value).'">';
    echo '<input type="submit" class="button-primary" value="Créer le compte et préparer l’accès"></form><p><a href="/schoolsafe-administrators.php">Annuler</a></p>';
    sskcUiEnd();exit;
}
if(sskcWizardAllowed())echo '<p><a class="sskc-action button-primary" target="_self" href="/schoolsafe-administrators.php?wizard=1">Ouvrir une école — parcours en six étapes</a></p>';
if(SchoolSafeGlobalAdmin())echo '<nav aria-label="Population des comptes"><a href="/schoolsafe-administrators.php" target="_self">Administrateurs scolaires</a> · <a href="/schoolsafe-administrators.php?accounts=central" target="_self">Comptes centraux — gestion protégée</a></nav>';
echo '<div class="sskc-list-tools"><h2>'.($showCentral?'Équipe centrale':'Administrateurs scolaires dans votre périmètre').'</h2><label>Rechercher un administrateur<input type="search" id="sskc-account-search" placeholder="Nom, identifiant, profil ou école" autocomplete="off" aria-controls="sskc-accounts"></label><nav aria-label="Actions de gestion">';
if(sskcCanWrite(true) && (int)UserSyear()===(int)Config('SYEAR'))echo '<a class="sskc-action" href="#sskc-add-admin" data-central-section="sskc-add-admin">Ajouter un administrateur</a>';
echo '<a class="sskc-action" href="#sskc-history" data-central-section="sskc-history">Voir l’historique</a></nav></div>';
$rows=DBGet('SELECT s.STAFF_ID,s.USERNAME,s.FIRST_NAME,s.LAST_NAME,s.PROFILE,s.PROFILE_ID,s.SCHOOLS,s.LAST_LOGIN,p.TITLE AS PROFILE_TITLE,'
    . 'l.SUBJECT,l.STATE,l.ENABLED AS IDENTITY_ENABLED FROM staff s LEFT JOIN user_profiles p ON p.ID=s.PROFILE_ID'
    . ' LEFT JOIN schoolsafe_identity_links l ON l.STAFF_ID=s.STAFF_ID WHERE s.SYEAR='.(int)UserSyear()
    . " AND (s.PROFILE='admin' OR (s.PROFILE='none' AND (p.PROFILE='admin' OR l.STATE='suspended'))) ORDER BY s.USERNAME");
$schoolNames=[];
foreach((array)DBGet('SELECT ID,TITLE FROM schools WHERE SYEAR='.(int)UserSyear()) as $school)
    if(SchoolSafeSchoolAllowed((string)$school['ID']))$schoolNames[(int)$school['ID']]=$school['TITLE'];
echo '<div class="sskc-table"><table id="sskc-accounts" class="list width-100p"><thead><tr><th>Personne / identifiant</th><th>Profil et écoles</th><th>État du compte</th><th>Actions autorisées</th></tr></thead><tbody>';
$count=0;
foreach((array)$rows as $row) {
    $id=(int)$row['STAFF_ID'];if(!SchoolSafeStaffWithinScope($id))continue;
    $scope=SchoolSafeScopeParse($row['SCHOOLS']);
    if(($scope===null)!==$showCentral)continue;
    $count++;$presentation=sskcAccountPresentation($row);
    echo '<tr id="sskc-account-'.$id.'" tabindex="-1"><td>'.sskcEscape(trim($row['FIRST_NAME'].' '.$row['LAST_NAME'])).'<br>'.sskcEscape($row['USERNAME'])
        .'</td><td>'.sskcEscape($row['PROFILE_TITLE']?:'Permissions personnalisées').'<br>'
        .sskcEscape(sskcAccountSchoolNames($row['SCHOOLS'],$schoolNames))
        .'</td><td>'.sskcEscape($presentation['access']).'<br><small>'.sskcEscape($presentation['connection']).'</small>'
        .($presentation['onboarding']!==''?'<br><small>'.sskcEscape($presentation['onboarding']).'</small>':'').'</td><td><div class="sskc-account-actions">';
    if(!SchoolSafeGlobalAdmin())echo '<a class="sskc-action sskc-action-secondary" target="_self" href="/Modules.php?modname=Users/User.php&amp;staff_id='.$id.'">Consulter la fiche</a>';
    $write=sskcCanWrite() && SchoolSafeProfileDelegable((string)$row['PROFILE_ID']) && (int)UserSyear()===(int)Config('SYEAR');
    if($write && $row['SUBJECT'] && $id!==(int)User('STAFF_ID')) {
        $actions=$row['STATE']==='suspended'?['resume'=>'Réactiver l’accès']:['temporary'=>'Mot de passe temporaire','suspend'=>'Suspendre l’accès'];
        foreach($actions as $action=>$label)echo '<form target="_self" method="post" action="/schoolsafe-administrators.php"><input type="hidden" name="token" value="'.$csrf
            .'"><input type="hidden" name="staff_id" value="'.$id.'"><input type="hidden" name="action" value="'.$action
            .'"><input type="submit" class="'.($action==='suspend'?'sskc-action-danger':'sskc-action-secondary').'" value="'.sskcEscape($label).'"></form>';
    }
    if($write && !$row['SUBJECT'] && $row['PROFILE']==='admin') {
        $initial=SchoolSafeNativeOnboardingRow($id,(int)UserSyear());
        if(SchoolSafeGlobalAdmin() && $id!==(int)User('STAFF_ID') && SchoolSafeNativeOnboardingCanPrepare($row,$initial))
            echo '<form target="_self" method="post" action="/schoolsafe-administrators.php"><input type="hidden" name="token" value="'.$csrf.'"><input type="hidden" name="staff_id" value="'.$id.'"><input type="hidden" name="action" value="native_temporary"><input type="submit" class="sskc-action-secondary" value="'.($initial?'Renouveler l’accès initial natif':'Préparer l’accès initial natif').'"></form>';
        // Native school access is prepared above; do not offer a paused provider
        // as if it could generate the native temporary credential.
    }
    echo '</div><p class="sskc-action-help">';
    if($id===(int)User('STAFF_ID'))echo 'Votre compte connecté. Les opérations de suspension et de renouvellement de votre propre accès sont protégées.';
    elseif(!$write)echo 'Consultation disponible. Les modifications dépendent de votre profil et de l’année scolaire courante.';
    elseif(!$row['SUBJECT']) {
        if(SchoolSafeGlobalAdmin() && SchoolSafeNativeOnboardingCanPrepare($row,SchoolSafeNativeOnboardingRow($id,(int)UserSyear())))
            echo 'Accès natif : générez ou renouvelez l’accès initial ci-dessus, puis remettez la fiche confidentiellement. L’administrateur scolaire choisit lui-même son mot de passe personnel avant de pouvoir entrer dans son école.';
        else echo 'Aucun accès initial à préparer pour ce compte. Un compte déjà activé conserve son mot de passe personnel ; en cas d’oubli, utilisez le parcours de récupération, sans réinitialisation depuis ProDéli.';
    }
    elseif($row['STATE']==='suspended')echo 'Réactivez l’accès avant de préparer un nouveau mot de passe.';
    else echo 'Chaque opération affiche un récapitulatif avant confirmation.';
    echo '</p></td></tr>';
}
if(!$count)echo '<tr><td colspan="4">Aucun administrateur accessible dans votre périmètre.</td></tr>';
echo '</tbody></table></div><p class="sskc-muted">L’état du compte et la première connexion sont distincts. L’état de l’école peut aussi limiter l’accès.</p>';
if(sskcCanWrite(true) && (int)UserSyear()===(int)Config('SYEAR')) {
    $profiles=DBGet("SELECT ID,TITLE FROM user_profiles WHERE PROFILE='admin' ORDER BY ID");
    $schools=DBGet('SELECT ID,TITLE FROM schools WHERE SYEAR='.(int)UserSyear().' ORDER BY TITLE');
    echo '<details id="sskc-add-admin"><summary>Ajouter un administrateur à une école existante ou à l’équipe SchoolSafe</summary><h2>Créer un administrateur</h2><form target="_self" method="post"><input type="hidden" name="action" value="prepare"><input type="hidden" name="token" value="'.$csrf.'">';
    echo '<p><label>Parcours de connexion <select name="login_population"><option value="client">Client — responsable d’école, connexion simple</option><option value="team">Équipe SchoolSafe — authentification renforcée</option></select></label></p><p>Un administrateur global conserve toujours l’authentification renforcée.</p>';
    foreach(['first_name'=>'Prénom','last_name'=>'Nom','username'=>'Identifiant de connexion','email'=>'Adresse e-mail'] as $key=>$label) {
        echo '<p><label>'.sskcEscape($label).' <input name="'.$key.'" type="'.($key==='email'?'email':'text').'" maxlength="'
            .(in_array($key,['first_name','last_name'],true)?50:($key==='username'?100:254)).'" required></label></p>';
    }
    echo '<p><label>Profil SchoolSafe <select name="profile_id" required><option value="">Choisir un profil</option>';
    foreach((array)$profiles as $profile)if(SchoolSafeProfileDelegable((string)$profile['ID']))echo '<option value="'.(int)$profile['ID'].'">'.sskcEscape($profile['TITLE']).'</option>';
    echo '</select></label></p><fieldset><legend>Périmètre scolaire</legend>';
    echo '<p><label><input type="radio" name="scope_mode" value="schools" checked> Écoles sélectionnées</label></p>';
    echo '<p>Création scolaire native : sélectionnez une seule école. Les comptes existants ne sont jamais convertis.</p><label>Responsabilité scolaire <select name="leadership_role"><option value="principal">Administrateur principal — sans remplacer le principal existant</option><option value="adjoint">Administrateur adjoint</option></select></label>';
    foreach((array)$schools as $school)if(SchoolSafeSchoolAllowed((string)$school['ID']))echo '<p><label><input type="checkbox" name="schools[]" value="'.(int)$school['ID'].'">'.sskcEscape($school['TITLE']).'</label></p>';
    if(SchoolSafeGlobalAdmin())echo '<p><label><input type="radio" name="scope_mode" value="global"> Toutes les écoles — administrateur global</label></p>';
    echo '</fieldset><p>Un mot de passe temporaire sera généré. Responsable d’école : informations personnelles et nouveau mot de passe, sans second facteur obligatoire. Administrateur global : authentification renforcée. Aucun droit de gestion globale de la plateforme n’est attribué automatiquement.</p>';
    echo '<input type="submit" class="button-primary" value="Vérifier et continuer"></form></details>';
}else echo '<p>Création indisponible : droits de création, de profil et d’écoles requis dans l’année courante.</p>';
echo '<h2 id="sskc-history" tabindex="-1">Historique récent</h2><div class="sskc-table"><table class="list width-100p"><thead><tr><th>Date</th><th>Gestionnaire</th><th>Compte</th><th>Opération</th><th>Résultat</th></tr></thead><tbody>';
$events=DBGet('SELECT a.*,s.USERNAME AS TARGET_USERNAME FROM schoolsafe_identity_audit a LEFT JOIN staff s ON s.STAFF_ID=a.TARGET_STAFF_ID ORDER BY a.ID DESC LIMIT 50');
$eventLabels=['create'=>'Création','link_existing'=>'Liaison existante','temporary'=>'Accès temporaire','temporary_password_display'=>'Remise de l’accès','suspend'=>'Suspension','resume'=>'Réactivation','first_login'=>'Première connexion'];
$seen=0;
foreach((array)$events as $event) {
    if(!SchoolSafeGlobalAdmin() && !SchoolSafeStaffWithinScope((int)$event['TARGET_STAFF_ID']))continue;
    $seen++;echo '<tr><td>'.sskcEscape($event['OCCURRED_AT']).'</td><td>'.(int)$event['ACTOR_STAFF_ID'].'</td><td>'
        .sskcEscape($event['TARGET_USERNAME']??('ID '.(int)$event['TARGET_STAFF_ID'])).'</td><td>'
        .sskcEscape($eventLabels[$event['ACTION']]??$event['ACTION']).'</td><td>'.sskcEscape($event['RESULT']==='success'?'Réussi':'Échec').'</td></tr>';
}
if(!$seen)echo '<tr><td colspan="5">Aucune opération récente dans votre périmètre.</td></tr>';
echo '</tbody></table></div>';
sskcUiEnd();
