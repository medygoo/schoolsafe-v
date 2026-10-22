<?php
declare(strict_types=1);

function sskcUiStart(string $title): void
{
    global $_ROSARIO, $RosarioModules;
    $route='/schoolsafe-administrators.php';
    if(isset($_GET['home']))$route.='?home=1';
    elseif(isset($_GET['wizard']))$route.='?wizard=1&step='.max(1,min(6,(int)($_GET['step']??1)));
    elseif(isset($_GET['opening']))$route.='?opening=1';
    elseif(($_GET['accounts']??'')==='central')$route.='?accounts=central';
    $_REQUEST['modname']='Users/User.php';$_ROSARIO['page']='modules';Warehouse('header');
    $jaspeLink = !empty($RosarioModules['SchoolSafe_Jaspe']) && AllowUse('SchoolSafe_Jaspe/Assistant.php')
        ? '<a id="sskc-jaspe-launch" href="/Modules.php?modname=SchoolSafe_Jaspe/Assistant.php&amp;ss_companion=1" target="_self" aria-haspopup="dialog" aria-controls="ss-jaspe-dialog" aria-expanded="false">Écrire à JASPE</a>' : '';
    echo '<link rel="stylesheet" href="/assets/themes/SchoolSafeCodex/menu-logo.css?v=shared-2"><link rel="stylesheet" href="/plugins/SchoolSafe_Keycloak/central-ui.css?v=16"><main class="sskc-shell" data-central-route="'.sskcEscape($route).'"><script src="/plugins/SchoolSafe_Keycloak/central-ui.js?v=16" defer></script><header class="sskc-top"><a class="sskc-brand" href="/schoolsafe-administrators.php?home=1" target="_self" aria-label="SchoolSafe — accueil central"><span class="sskc-emblem"><img class="ss-animated-brand" src="/schoolsafe-logo.png" alt="" width="66" height="72"></span><span><strong>SchoolSafe</strong><small>'.(SchoolSafeGlobalAdmin()?'ProDéli · Gestion centrale':'Votre école, en confiance.').'</small></span></a><div class="sskc-session-tools">'.(!isset($_GET['home'])?'<a href="/schoolsafe-administrators.php?home=1" target="_self">Retour au tableau de bord</a>':'').$jaspeLink.'<a href="/index.php?modfunc=logout" target="_self">Déconnexion</a></div></header><h1>'.sskcEscape($title).'</h1>';
}
function sskcUiEnd(): void {echo '</main>';Warehouse('footer');}
/** Validate dashboard intent; Side.php remains the native school-switch authority. */
function sskcDashboardSchoolTarget(): int
{
    sskcCsrf();
    $school=$_POST['school']??null;
    if(!sskcCanRead() || !is_string($school) || !preg_match('/^[1-9][0-9]{0,8}$/D',$school)
        || !SchoolSafeSchoolAllowed($school)
        || !DBGetOne('SELECT ID FROM schools WHERE ID='.(int)$school.' AND SYEAR='.(int)UserSyear())) {
        throw new RuntimeException('Cette école ne relève pas de vos autorisations pour cette année.');
    }
    return (int)$school;
}
/** Central navigation never opens the school dashboard implicitly. */
function sskcHomePage(): void
{
    if(!sskcCanRead()){SchoolSafeScopeDeny();exit;}
    $year=(int)UserSyear();$current=(int)UserSchool();$schools=[];
    foreach((array)DBGet('SELECT ID,TITLE FROM schools WHERE SYEAR='.$year.' ORDER BY TITLE') as $school)
        if(SchoolSafeSchoolAllowed((string)$school['ID']))$schools[(int)$school['ID']]=$school['TITLE'];
    $waiting=[];
    $accounts=DBGet("SELECT s.STAFF_ID,s.FIRST_NAME,s.LAST_NAME,s.USERNAME,s.PROFILE,s.SCHOOLS,s.LAST_LOGIN,l.STATE,l.SUBJECT,l.ENABLED AS IDENTITY_ENABLED FROM staff s LEFT JOIN schoolsafe_identity_links l ON l.STAFF_ID=s.STAFF_ID WHERE s.SYEAR=".$year." AND s.PROFILE='admin' ORDER BY s.LAST_NAME,s.FIRST_NAME");
    foreach((array)$accounts as $account) {
        if(!SchoolSafeStaffWithinScope((int)$account['STAFF_ID']) || !is_array(SchoolSafeScopeParse($account['SCHOOLS'])))continue;
        $presentation=sskcAccountPresentation($account);
        if($presentation['access']==='Actif' && $presentation['onboarding']!=='')$waiting[]=$account+['FOLLOWUP'=>$presentation['onboarding']];
    }
    $pending=[];
    if(sskcOpeningCentral())$pending=(array)DBGet("SELECT ID,SCHOOL_NAME,STATE FROM schoolsafe_opening_requests WHERE SYEAR=".$year." AND STATE IN ('requested','approved') ORDER BY ID LIMIT 5");
    $canCreate=sskcWizardAllowed();$draft=$_SESSION['sskc_school_draft']??null;$resume=false;$step=1;
    if($canCreate && is_array($draft) && ($draft['actor']??0)===(int)User('STAFF_ID') && ($draft['year']??0)===$year && ($draft['expires']??0)>time()) {
        $resume=!empty($draft['data']) || ($draft['status']??'draft')!=='draft';
        $step=($draft['status']??'draft')==='draft'?min(6,((int)($draft['done']??0))+1):6;
    }
    sskcUiStart(SchoolSafeGlobalAdmin()?'Tableau de bord ProDéli':'Accueil de gestion SchoolSafe');
    if(SchoolSafeGlobalAdmin())echo '<p class="sskc-dashboard-context">Gestion de la plateforme · Les espaces scolaires restent distincts.</p>';
    echo '<dl class="sskc-dashboard-summary" aria-label="Votre périmètre de gestion"><div><dt>Établissements accessibles</dt><dd data-summary="schools">'.count($schools).'</dd></div><div><dt>Accès à accompagner</dt><dd data-summary="onboarding">'.count($waiting).'</dd></div><div><dt>Année scolaire</dt><dd>'.$year.'–'.($year+1).'</dd></div></dl>';
    echo '<div class="sskc-dashboard-shortcuts" aria-label="Accès directs"><a href="#sskc-schools-title">Établissements</a><a href="#sskc-access-title">Administrateurs et accès</a>';
    if(SchoolSafeGlobalAdmin())echo '<a href="#sskc-communications-title">Communication</a>';
    echo '</div><div class="sskc-home"><section class="sskc-home-intro" aria-label="Ouverture des écoles"><div><p class="sskc-home-lead">Une école, son responsable, un accès prêt à remettre.</p><p>Préparez les établissements et accompagnez leurs administrateurs depuis cet espace. Les élèves, parents et enseignants se gèrent ensuite à l’intérieur de chaque école.</p></div><div class="sskc-home-start">';
    if($canCreate)echo '<a class="sskc-action button-primary" target="_self" href="/schoolsafe-administrators.php?wizard=1'.($resume?'&amp;step='.$step:'').'">'.($resume?'Reprendre le dossier en cours':'Créer une école').'</a><span>'.($resume?'Votre dossier est conservé pendant la durée de la session de préparation.':'6 étapes · école et administrateur principal').'</span>';
    else echo '<p>La création d’un établissement est réservée aux gestionnaires autorisés. Vos accès actuels restent disponibles ci-dessous.</p>';
    echo '</div></section><div class="sskc-home-columns"><div class="sskc-home-main"><section class="sskc-home-section" aria-labelledby="sskc-schools-title"><div class="sskc-section-heading"><h2 id="sskc-schools-title">Vos établissements</h2><span>Année scolaire '.$year.'–'.($year+1).'</span></div>';
    if($schools){
        echo '<label for="sskc-school-search">Rechercher un établissement<input type="search" id="sskc-school-search" autocomplete="off" placeholder="Nom de l’école" aria-controls="sskc-school-directory"></label><p id="sskc-school-search-status" class="sskc-muted" role="status">'.count($schools).' établissement(s) dans votre périmètre.</p><ul id="sskc-school-directory" class="sskc-school-directory">';
        foreach($schools as $id=>$title){
            echo '<li data-school-entry><div><strong data-school-title>'.sskcEscape((string)$title).'</strong><span>'.($id===$current?'École sélectionnée':'Dans votre périmètre autorisé').'</span></div><form target="_self" method="post" action="/schoolsafe-administrators.php?home=1">';
            foreach(['token'=>(string)($_SESSION['token']??''),'action'=>'dashboard_school','school'=>(string)$id] as $key=>$value)sskcWizardHidden($key,$value);
            echo '<button type="submit" aria-label="Ouvrir l’espace scolaire : '.sskcEscape((string)$title).'">Ouvrir l’école</button></form></li>';
        }
        echo '</ul><p id="sskc-school-search-empty" class="sskc-home-empty" hidden>Aucun établissement ne correspond à votre recherche.</p><p class="sskc-home-caption">L’ouverture utilise le changement d’école natif. Le serveur vérifie à nouveau vos autorisations ; aucun autre compte n’est créé.</p>';
    }else echo '<p class="sskc-home-empty">Aucun établissement accessible pour cette année. Un gestionnaire autorisé doit préparer votre école ou vérifier son attribution.</p>';
    echo '</section><section class="sskc-home-section" aria-labelledby="sskc-followup-title"><div class="sskc-section-heading"><h2 id="sskc-followup-title">Demandes à suivre</h2>';
    if(sskcOpeningCentral())echo '<a href="/schoolsafe-administrators.php?opening=1" target="_self">Voir les demandes</a>';
    echo '</div>';
    if(!sskcOpeningCentral())echo '<p>L’ouverture des nouveaux établissements est gérée par l’équipe centrale SchoolSafe.</p>';
    elseif(!$pending)echo '<p class="sskc-home-empty">Aucune demande en attente pour cette année. Vous pouvez préparer une école lorsque son dossier est prêt.</p>';
    else {
        echo '<ul class="sskc-home-followup">';foreach($pending as $request)echo '<li><strong>'.sskcEscape($request['SCHOOL_NAME']).'</strong><a class="sskc-row-action" target="_self" href="/schoolsafe-administrators.php?opening=1#sskc-request-'.(int)$request['ID'].'">Ouvrir le suivi</a><span>'.($request['STATE']==='requested'?'Demande à examiner':'Demande validée · rattachement à vérifier').'</span></li>';echo '</ul><p class="sskc-home-caption">Le suivi d’une demande ne confirme pas la première connexion de son responsable. Vérifiez le rattachement avant de recréer un établissement.</p>';
    }
    echo '</section></div><aside class="sskc-home-side" aria-label="Administrateurs et préparation"><section class="sskc-home-section"><h2 id="sskc-access-title">Administrateurs et accès</h2><p>Consultez les profils, les écoles attribuées et les actions autorisées pour chaque personne.</p><a class="sskc-action" href="/schoolsafe-administrators.php" target="_self">Gérer les administrateurs</a>';
    if(SchoolSafeGlobalAdmin())echo '<p><a href="/schoolsafe-administrators.php?accounts=central" target="_self">Gérer les comptes de l’équipe centrale</a></p>';
    if($waiting){echo '<h3>Accès à accompagner</h3><ul class="sskc-access-followup">';foreach(array_slice($waiting,0,3) as $account)echo '<li><strong>'.sskcEscape(trim($account['FIRST_NAME'].' '.$account['LAST_NAME'])?:$account['USERNAME']).'</strong><a class="sskc-row-action" target="_self" href="/schoolsafe-administrators.php?accounts=1#sskc-account-'.(int)$account['STAFF_ID'].'">Gérer cet accès</a><span>'.sskcEscape($account['FOLLOWUP']).'</span></li>';echo '</ul>';if(count($waiting)>3)echo '<p class="sskc-home-caption">Autres comptes à consulter dans la gestion des administrateurs.</p>';}
    else echo '<p class="sskc-home-caption">Aucune première connexion en attente parmi les administrateurs scolaires actifs.</p>';
    echo '</section><section class="sskc-home-section sskc-home-preparation"><h2>Préparer le dossier</h2><p>Les six étapes sont remplies par le gestionnaire autorisé.</p><details><summary>Informations à réunir</summary><ul><li>Nom officiel, coordonnées et directeur de l’établissement.</li><li>E-mail officiel, devise et informations des documents.</li><li>Nom, e-mail personnel, identifiant et profil du responsable principal.</li><li>Logo et portrait, si disponibles : zoom, recadrage et redimensionnement dans le parcours.</li><li>Modèle scolaire et base de notation.</li></ul></details><p class="sskc-home-caption">Après création, le client reçoit son mot de passe temporaire, complète ses informations et choisit son mot de passe personnel.</p></section></aside></div>';
    if(SchoolSafeGlobalAdmin())echo '<section class="sskc-home-section" aria-labelledby="sskc-communications-title"><h2 id="sskc-communications-title">Communication SchoolSafe</h2><p>Retrouvez les services existants et leurs réglages. Leur configuration reste réservée aux gestionnaires habilités ; ouvrir ces pages ne déclenche aucun envoi.</p><div class="sskc-dashboard-actions"><a class="sskc-action" target="_self" href="/plateforme/prodeli/sms">Configurer les quatre réseaux SMS</a><a class="sskc-action" target="_self" href="/plateforme/prodeli/whatsapp">Gérer WhatsApp</a><a class="sskc-action sskc-action-secondary" target="_self" href="/Modules.php?modname=School_Setup/Configuration.php">Configuration générale de l’école</a></div><details><summary>Opérations administratives existantes</summary><p>La console native conserve ses commandes de comptes et d’établissements. Leur utilisation reste soumise aux droits et confirmations existants.</p><a class="sskc-action sskc-action-secondary" target="_self" href="/admin-console.php">Ouvrir les opérations administratives</a></details></section>';
    if(isset($schools[$current]))echo '<section class="sskc-school-entry sskc-home-school-entry"><div><h2>Espace scolaire</h2><p>École sélectionnée : <strong>'.sskcEscape((string)$schools[$current]).'</strong>.</p><p>Retrouvez ici la gestion quotidienne des élèves, du personnel et de la scolarité.</p></div><a class="sskc-action" target="_self" href="/Modules.php?modname=misc/Portal.php">Ouvrir le tableau de bord de cette école</a></section>';
    echo '</div>';sskcUiEnd();exit;
}
function sskcWizardAllowed(): bool
{
    return sskcOpeningWrite() && SchoolSafeCanCreateSchool()
        && AllowUse('School_Setup/CopySchool.php') && AllowEdit('School_Setup/CopySchool.php')
        && AllowUse('School_Setup/Schools.php') && AllowEdit('School_Setup/Schools.php');
}
/** Review only: mutations remain in the existing, guarded controller. */
function sskcActionReview(string $action,int $id): void
{
    sskcCsrf();$row=sskcTarget($id,true);
    $labels=['native_temporary'=>'Renouveler l’accès initial natif','link'=>'Configurer la connexion gérée','temporary'=>'Préparer un mot de passe temporaire','suspend'=>'Suspendre l’accès','resume'=>'Réactiver l’accès'];
    if(!isset($labels[$action]))throw new RuntimeException('Action inconnue.');
    if($action==='link' && ($row['PROFILE']!=='admin' || !empty($row['SUBJECT'])))throw new RuntimeException('Ce compte possède déjà une liaison ou ne peut pas être activé.');
    if($action==='native_temporary' && (!SchoolSafeGlobalAdmin() || !empty($row['SUBJECT']) || $row['PROFILE']!=='admin'
        || $id===(int)User('STAFF_ID') || !SchoolSafeNativeOnboardingRow($id,(int)UserSyear())
        || !empty(SchoolSafeNativeOnboardingRow($id,(int)UserSyear())['COMPLETED_AT'])))throw new RuntimeException('Ce compte ne possède pas d’accès initial natif à renouveler.');
    if(!in_array($action,['link','native_temporary'],true) && (empty($row['SUBJECT']) || $id===(int)User('STAFF_ID')))throw new RuntimeException('Cette opération n’est pas disponible pour ce compte.');
    if($action==='native_temporary')$_SESSION['sskc_native_renew']=['id'=>bin2hex(random_bytes(24)),
        'staff'=>$id,'actor'=>(int)User('STAFF_ID'),'year'=>(int)UserSyear(),'expires'=>time()+600];
    $explanations=[
        'native_temporary'=>'Le mot de passe initial sera remplacé, les sessions de préparation précédentes seront refusées et un nouvel accès valable 24 heures sera à remettre confidentiellement. Aucun courriel ni SMS n’est envoyé.',
        'link'=>'Cette opération prépare la connexion du compte déjà présent. Elle ne recrée pas sa fiche scolaire. Si une identité de connexion existe déjà, la vérification native arrête la liaison automatique.',
        'temporary'=>'Le mot de passe précédent sera remplacé et les sessions de cette personne seront fermées. Le nouvel accès sera affiché une seule fois pour une remise confidentielle.',
        'suspend'=>'La personne ne pourra plus se connecter. Sa fiche et ses données seront conservées. Vous pourrez réactiver son accès si vos droits le permettent.',
        'resume'=>'La suspension sera levée. Le profil et les écoles attribués au compte restent contrôlés par SchoolSafe.'
    ];
    $return='/schoolsafe-administrators.php?accounts='.(SchoolSafeScopeParse($row['SCHOOLS'])===null?'central':'1');
    sskcUiStart($labels[$action]);
    echo '<section class="sskc-action-review"><p>Aucune modification n’a encore été effectuée. Vérifiez la personne et l’opération avant de confirmer.</p><dl><dt>Personne</dt><dd>'.sskcEscape(trim($row['FIRST_NAME'].' '.$row['LAST_NAME'])).'</dd><dt>Identifiant</dt><dd>'.sskcEscape($row['USERNAME']).'</dd><dt>Opération</dt><dd>'.sskcEscape($labels[$action]).'</dd></dl><p class="sskc-note">'.sskcEscape($explanations[$action]).'</p><form method="post" target="_self" action="'.sskcEscape($return).'">';
    foreach(['token'=>(string)$_SESSION['token'],'staff_id'=>(string)$id,'action'=>$action,'confirmed'=>'1'] as $key=>$value)sskcWizardHidden($key,$value);
    if($action==='native_temporary')sskcWizardHidden('native_renew_nonce',$_SESSION['sskc_native_renew']['id']);
    echo '<div class="sskc-review-buttons"><input type="submit" class="'.($action==='suspend'?'sskc-action-danger':'button-primary').'" value="Confirmer : '.sskcEscape($labels[$action]).'"><a class="sskc-action" href="'.sskcEscape($return).'#sskc-account-'.$id.'" target="_self">Annuler et revenir au compte</a></div></form><p>Les droits et l’état du compte sont revérifiés au moment de la confirmation.</p></section>';
    sskcUiEnd();exit;
}
function sskcWizardDraft(): array
{
    $d=$_SESSION['sskc_school_draft']??[];
    if(!$d || ($d['actor']??0)!==(int)User('STAFF_ID') || ($d['year']??0)!==(int)UserSyear() || ($d['expires']??0)<time()) {
        $d=['id'=>bin2hex(random_bytes(24)),'actor'=>(int)User('STAFF_ID'),'year'=>(int)UserSyear(),'expires'=>time()+7200,'data'=>[],'done'=>0,'status'=>'draft','source'=>(int)UserSchool()];
        $_SESSION['sskc_school_draft']=$d;
    }
    return $d;
}
function sskcWizardFields(): array
{
    return [1=>['TITLE'=>['Nom officiel',100,true],'SHORT_NAME'=>['Nom abrégé',25,false],'SCHOOL_NUMBER'=>['Numéro d’école',50,false],'ADDRESS'=>['Adresse',100,false],'CITY'=>['Ville',100,false],'STATE'=>['Code région / état',10,false],'ZIPCODE'=>['Code postal',10,false],'PHONE'=>['Téléphone officiel',30,false],'WWW_ADDRESS'=>['Site web',100,false],'PRINCIPAL'=>['Directeur / chef d’établissement',100,false]],
        2=>['school_email'=>['E-mail officiel',254,true],'school_slogan'=>['Devise / slogan',180,true],'extra1'=>['Expression complémentaire 2',180,false],'extra2'=>['Expression complémentaire 3',180,false],'extra3'=>['Expression complémentaire 4',180,false]],
        3=>['first_name'=>['Prénom du responsable',50,true],'last_name'=>['Nom du responsable',50,true],'middle_name'=>['Deuxième prénom',50,false],'admin_phone'=>['Téléphone personnel',30,false],'username'=>['Identifiant de connexion',100,true],'email'=>['E-mail personnel',254,true]],
        5=>[]];
}
function sskcWizardValidate(array $d,int $step): void
{
    $a=$d['data'];
    foreach(sskcWizardFields()[$step]??[] as $key=>[$label,$max,$required]) {
        $v=(string)($a[$key]??'');
        if(($required && $v==='') || !mb_check_encoding($v,'UTF-8') || mb_strlen($v)>$max || preg_match('/[\x00-\x1f\x7f]/',$v)) throw new RuntimeException('Vérifiez le champ « '.$label.' ».');
    }
    if($step===2) {
        require_once 'modules/School_Setup/includes/SchoolReceiptIdentity.fnc.php';
        if(!filter_var($a['school_email'],FILTER_VALIDATE_EMAIL) || !SchoolSafeSchoolSloganValid(sskcWizardSlogan($a))) throw new RuntimeException('E-mail officiel non valide ou devise totale supérieure à 180 caractères.');
    }
    if($step===3) {
        if(!filter_var($a['email'],FILTER_VALIDATE_EMAIL) || !preg_match('/^[A-Za-z0-9@._+-]+$/D',$a['username'])) throw new RuntimeException('Identifiant ou e-mail personnel non valide.');
        $profile=(int)($a['profile_id']??0);
        if(DBGetOne('SELECT PROFILE FROM user_profiles WHERE ID='.$profile)!=='admin' || !SchoolSafeProfileDelegable((string)$profile)) throw new RuntimeException('Choisissez un profil administrateur que vous êtes autorisé à attribuer.');
        $id=DBGetOne("SELECT STAFF_ID FROM staff WHERE SYEAR=".(int)UserSyear()." AND UPPER(USERNAME)=UPPER('".DBEscapeString($a['username'])."')");
        if($id)throw new RuntimeException('Ce compte existe déjà. Utilisez sa fiche dans la gestion des administrateurs ; ne le recréez pas.');
    }
    if($step===4)foreach(['logo','portrait'] as $key)if(!empty($a[$key]))sskcWizardImage($a[$key]);
    if($step===5) {
        if(!DBGetOne('SELECT ID FROM schools WHERE ID='.(int)$d['source'].' AND SYEAR='.(int)UserSyear()) || !SchoolSafeSchoolAllowed((string)$d['source'])) throw new RuntimeException('Modèle d’école indisponible dans votre périmètre.');
        // Le modèle doit avoir un calendrier cohérent (année, semestres,
        // trimestres) : une copie depuis un modèle cassé livrerait une école
        // dont les écrans échouent. Même contrôle que SchoolSafeSchoolCreate().
        $calendar=SchoolSafeSchoolCalendarStatuses([(int)$d['source']],(int)UserSyear());
        $calendarStatus=$calendar[(int)$d['source']]??null;
        if(!$calendarStatus || empty($calendarStatus['ok'])) {
            $calendarLabels=array_slice(array_column((array)($calendarStatus['issues']??[]),'label'),0,3);
            throw new RuntimeException("Le modèle choisi a un calendrier incohérent : aucune école ne sera créée."
                .($calendarLabels?' '.implode(' ',$calendarLabels):''));
        }
    }
}
function sskcWizardSlogan(array $a): string {return implode(' • ',array_filter(array_map(static fn($k)=>trim((string)($a[$k]??'')),['school_slogan','extra1','extra2','extra3']),static fn($v)=>$v!==''));}
function sskcWizardImage(string $data): void
{
    if(strlen($data)>2200000 || !str_starts_with($data,'data:image/jpeg;base64,')) throw new RuntimeException('Image cadrée trop grande ou format incorrect.');
    $raw=base64_decode(substr($data,23),true);$size=$raw===false?false:@getimagesizefromstring($raw);
    if(!$size || $size[2]!==IMAGETYPE_JPEG || $size[0]<1 || $size[1]<1 || $size[0]>1200 || $size[1]>1200) throw new RuntimeException('Image cadrée non valide. Reprenez son cadrage.');
}
function sskcWizardHidden(string $name,string $value): void {echo '<input type="hidden" name="'.sskcEscape($name).'" value="'.sskcEscape($value).'">';}
function sskcWizardRecoverableSchool(array $d): int
{
    if(($d['status']??'')!=='draft' || (int)($d['done']??0)<5)return 0;
    $a=$d['data'];$year=(int)UserSyear();$title=DBEscapeString((string)($a['TITLE']??''));
    $matches=(array)DBGet("SELECT ID FROM schools WHERE SYEAR=$year AND UPPER(TITLE)=UPPER('$title')");
    if(count($matches)!==1)return 0;
    $school=(int)$matches[0]['ID'];
    if(!$school || !SchoolSafeSchoolAllowed((string)$school) || !AllowEdit('School_Setup/Schools.php'))return 0;
    $email=DBGetOne("SELECT CONFIG_VALUE FROM config WHERE SCHOOL_ID=$school AND TITLE='SCHOOLSAFE_SCHOOL_EMAIL'");
    if(strcasecmp(trim((string)$email),trim((string)($a['school_email']??'')))!==0)return 0;
    if(DBGetOne("SELECT 1 FROM schoolsafe_opening_requests WHERE SCHOOL_ID=$school LIMIT 1"))return 0;
    if(DBGetOne("SELECT 1 FROM staff WHERE SYEAR=$year AND POSITION(',$school,' IN COALESCE(SCHOOLS,''))>0 LIMIT 1"))return 0;
    return $school;
}
function sskcWizardPage(): void
{
    if(!sskcWizardAllowed()){SchoolSafeScopeDeny();exit;}
    $d=sskcWizardDraft();$step=max(1,min(6,(int)($_GET['step']??1)));$error='';
    try {
        if(($_SERVER['REQUEST_METHOD']??'')==='POST') {
            sskcCsrf();
            if(!hash_equals($d['id'],(string)($_POST['draft_id']??'')))throw new RuntimeException('Brouillon expiré. Rechargez la page.');
            if(($_POST['wizard_action']??'')==='new' && !empty($d['staff'])){unset($_SESSION['sskc_school_draft']);header('Location: /schoolsafe-administrators.php?wizard=1');exit;}
            if(($_POST['wizard_action']??'')==='recover_school') {
                if($step!==6)throw new RuntimeException('Reprise indisponible.');
                for($i=1;$i<=5;$i++)sskcWizardValidate($d,$i);
                $school=sskcWizardRecoverableSchool($d);
                if(!$school || $school!==(int)($_POST['school_id']??0))throw new RuntimeException('École existante non vérifiée. Aucune nouvelle création effectuée.');
                $d['school']=$school;$d['status']='school_created';$d['expires']=time()+7200;
                $_SESSION['sskc_school_draft']=$d;
                header('Location: /schoolsafe-administrators.php?wizard=1&step=6');exit;
            }
            if($d['status']!=='draft')throw new RuntimeException('Ce dossier est déjà en cours d’ouverture. Consultez son récapitulatif.');
            foreach(sskcWizardFields()[$step]??[] as $key=>$field)$d['data'][$key]=trim((string)($_POST[$key]??''));
            if($step===3)$d['data']['profile_id']=(int)($_POST['profile_id']??0);
            if($step===4)foreach(['logo','portrait'] as $k)$d['data'][$k]=(string)($_POST[$k]??'');
            if($step===5)$d['source']=(int)($_POST['source']??0);
            sskcWizardValidate($d,$step);
            $d['done']=max((int)$d['done'],$step);$d['expires']=time()+7200;$_SESSION['sskc_school_draft']=$d;
            header('Location: /schoolsafe-administrators.php?wizard=1&step='.min(6,$step+1));exit;
        }
    }catch(RuntimeException $e){$error=$e->getMessage();$_SESSION['sskc_school_draft']=$d;}
    if($d['status']!=='draft')$step=6;
    elseif($step>$d['done']+1)$step=$d['done']+1;
    sskcUiStart('Ouverture d’une école');
    $titles=[1=>'École',2=>'Documents',3=>'Responsable',4=>'Images',5=>'Configuration',6=>'Vérification'];
    echo '<p>Une école et un administrateur principal. Le personnel, les enseignants, les parents et les élèves seront créés ensuite dans l’école.</p><ol class="sskc-progress" aria-label="Étapes de création">';
    foreach($titles as $i=>$t){echo '<li>';if($i<=$d['done']+1 && $d['status']==='draft')echo '<a target="_self" '.($i===$step?'aria-current="step" ':'').'href="/schoolsafe-administrators.php?wizard=1&amp;step='.$i.'">'.$i.'. '.$t.'</a>';else echo '<span '.($i===$step?'aria-current="step"':'').'>'.$i.'. '.$t.'</span>';echo '</li>';}
    echo '</ol><div class="sskc-workspace"><div class="sskc-workarea"><h2>'.$step.' / 6 — '.$titles[$step].'</h2>';
    if($error)echo '<p role="alert">'.sskcEscape($error).'</p>';
    echo '<p id="status" role="status" aria-live="polite"></p>';
    $a=$d['data'];
    if($step<6) {
        echo '<form method="post" target="_self" id="school-wizard" action="/schoolsafe-administrators.php?wizard=1&amp;step='.$step.'">';sskcWizardHidden('token',(string)$_SESSION['token']);sskcWizardHidden('draft_id',$d['id']);
        if($step===1)echo '<p class="sskc-note">Pour une école déjà créée, utilisez <a href="/schoolsafe-administrators.php?opening=1" target="_self">le suivi des ouvertures</a>. Ne recréez pas Le Sage ni son responsable.</p>';
        echo '<div class="grid">';
        foreach(sskcWizardFields()[$step]??[] as $key=>[$label,$max,$required]) {
            $type=in_array($key,['email','school_email'],true)?'email':($key==='REPORTING_GP_SCALE'?'number':'text');
            echo '<label>'.sskcEscape($label).($required?' *':'').'<input id="'.sskcEscape($key).'" name="'.sskcEscape($key).'" type="'.$type.'" maxlength="'.$max.'" '.($required?'required ':'').($type==='number'?'min="1" max="10000" ':'').'value="'.sskcEscape((string)($a[$key]??'')).'"></label>';
        }
        echo '</div>';
        if($step===2){echo '<p class="sskc-muted">La devise et ses expressions réunies sont limitées à 180 caractères. En-tête : nom, logo et devise. Pied de page : coordonnées officielles.</p>';sskcWizardPreview($a);}
        if($step===3){
            echo '<label>Profil scolaire *<select name="profile_id" required><option value="">Choisir un profil</option>';
            foreach((array)DBGet("SELECT ID,TITLE FROM user_profiles WHERE PROFILE='admin' ORDER BY ID") as $p)if(SchoolSafeProfileDelegable((string)$p['ID']))echo '<option value="'.(int)$p['ID'].'" '.((int)($a['profile_id']??0)===(int)$p['ID']?'selected':'').'>'.sskcEscape($p['TITLE']).'</option>';
            echo '</select></label><p>Accès à cette école uniquement. Le client reçoit un mot de passe temporaire, complète ses informations et choisit son mot de passe personnel. Aucun QR code ni second facteur obligatoire.</p>';
        }
        if($step===4){
            echo '<p>Choisissez une image, ajustez le zoom et le cadrage, puis validez. Le logo reste entier par défaut. Les images sont facultatives.</p><div class="editor" id="editors"></div>';
            foreach(['logo','portrait'] as $k)sskcWizardHidden($k,(string)($a[$k]??''));
            echo '<script src="/plugins/SchoolSafe_Keycloak/wizard-images.js?v=6"></script>';
        }
        if($step===5){
            echo '<p>Année active : '.(int)UserSyear().'–'.((int)UserSyear()+1).'. La configuration du modèle sera copiée ; aucune personne ni opération financière n’est copiée.</p><label>École servant de modèle *<select name="source" required>';
            foreach((array)DBGet('SELECT ID,TITLE FROM schools WHERE SYEAR='.(int)UserSyear().' ORDER BY TITLE') as $s)if(SchoolSafeSchoolAllowed((string)$s['ID']))echo '<option value="'.(int)$s['ID'].'" '.((int)$d['source']===(int)$s['ID']?'selected':'').'>'.sskcEscape($s['TITLE']).'</option>';
            echo '</select></label><p>Après ouverture, vérifiez les périodes, niveaux, horaires, barèmes et codes de présence dans les réglages de l’école.</p>';
        }
        echo '<div class="sskc-actions">';if($step>1)echo '<a class="sskc-action" target="_self" href="/schoolsafe-administrators.php?wizard=1&amp;step='.($step-1).'">Précédent</a>';
        echo '<input type="submit" class="button-primary" value="Enregistrer et continuer"></div></form><p class="sskc-muted">Le brouillon est conservé dans votre session pendant deux heures. Aucun compte n’est créé avant la confirmation finale.</p>';
    }else sskcWizardReview($d);
    echo '</div></div>';sskcUiEnd();exit;
}
function sskcWizardPreview(array $a): void
{
    echo '<div class="preview"><div class="paper-head">';if(!empty($a['logo']))echo '<img src="'.sskcEscape($a['logo']).'" alt="Logo cadré de l’école">';
    echo '<div><div class="school-title">'.sskcEscape((string)($a['TITLE']??'')).'</div>'.sskcEscape(sskcWizardSlogan($a)).'</div></div><div class="paper-content"><h3>Dossier de l’établissement</h3><p>'.(int)UserSyear().'–'.((int)UserSyear()+1).'</p></div><div class="paper-foot">'.sskcEscape(implode(' · ',array_filter([$a['ADDRESS']??'',$a['CITY']??'',$a['PHONE']??'',$a['school_email']??'',$a['WWW_ADDRESS']??'']))).'<br>SchoolSafe</div></div>';
}
function sskcWizardReview(array $d): void
{
    $a=$d['data'];echo '<dl>';
    foreach(['École'=>$a['TITLE']??'','Responsable'=>trim(($a['first_name']??'').' '.($a['last_name']??'')),'Identifiant'=>$a['username']??'','E-mail personnel'=>$a['email']??'','Accès'=>'Client — cette école uniquement',] as $k=>$v)echo '<dt>'.sskcEscape($k).'</dt><dd>'.sskcEscape((string)$v).'</dd>';
    echo '</dl>';sskcWizardPreview($a);
    if(!empty($d['staff'])) {
        echo '<h3>École et administrateur principal créés</h3><p>Le dossier est terminé. Affichez une seule fois l’accès temporaire, téléchargez la fiche confidentielle et remettez-la au responsable. Le parcours s’arrête à cette remise.</p>'
            .'<div class="sskc-actions"><a class="sskc-action button-primary" target="_self" href="/schoolsafe-administrators.php?credential='.(int)$d['staff'].'">Ouvrir la fiche à télécharger</a></div>'
            .'<p class="sskc-muted">Si la fiche a déjà été affichée ou a expiré, ne recréez ni l’école ni le compte. La gestion des administrateurs permet une nouvelle remise contrôlée.</p>';return;
    }
    if(!empty($d['school'])) {
        echo '<h3>École créée — compte principal à confirmer</h3><p>La création du compte conserve les contrôles de profil et d’écoles. Une seule école sera attribuée.</p>';
        try {
            $previous=$_POST;
            $_POST=['token'=>$_SESSION['token'],'first_name'=>$a['first_name'],'last_name'=>$a['last_name'],'username'=>$a['username'],'email'=>$a['email'],'profile_id'=>$a['profile_id'],'scope_mode'=>'schools','schools'=>[(string)$d['school']],'login_population'=>'client'];
            // PrepareIntent requires an explicit POST; never issue identity-service requests on GET.
            echo '<form method="post" target="_self" action="/schoolsafe-administrators.php">';
            foreach($_POST+['action'=>'prepare','sskc_wizard_id'=>$d['id']] as $k=>$v){if(is_array($v))foreach($v as $x)sskcWizardHidden($k.'[]',(string)$x);else sskcWizardHidden($k,(string)$v);}
            $_POST=$previous;
            echo '<input type="submit" class="button-primary" value="Vérifier le compte principal"></form>';
        }catch(Throwable $e){$_POST=$previous;echo '<p role="alert">Vérification indisponible. Reprenez depuis la gestion des administrateurs.</p>';}
        return;
    }
    if($d['status']!=='draft'){echo '<p role="alert">L’ouverture a déjà été lancée. Vérifiez le suivi et la liste des écoles avant toute nouvelle tentative.</p>';return;}
    try{for($i=1;$i<=5;$i++)sskcWizardValidate($d,$i);}catch(RuntimeException $e){echo '<p role="alert">'.sskcEscape($e->getMessage()).'</p>';return;}
    $existing=sskcWizardRecoverableSchool($d);
    if($existing) {
        echo '<p class="sskc-note">Cette école est déjà enregistrée, avec le même nom et le même e-mail officiel, mais sans responsable principal. Ne la recopiez pas. Reprenez ce dossier pour préparer uniquement le compte et sa fiche d’accès.</p>'
            .'<form method="post" target="_self" action="/schoolsafe-administrators.php?wizard=1&amp;step=6">';
        foreach(['token'=>(string)$_SESSION['token'],'draft_id'=>$d['id'],'wizard_action'=>'recover_school','school_id'=>(string)$existing] as $k=>$v)sskcWizardHidden($k,$v);
        echo '<input type="submit" class="button-primary" value="Reprendre cette école sans la recréer"></form>';return;
    }
    if(DBGetOne("SELECT 1 FROM schools WHERE SYEAR=".(int)UserSyear()." AND UPPER(TITLE)=UPPER('".DBEscapeString((string)$a['TITLE'])."')")) {
        echo '<p role="alert">Une école porte déjà ce nom, mais son rattachement ne peut pas être vérifié automatiquement. Aucune copie ne sera lancée.</p>';return;
    }
    echo '<p class="sskc-note">La confirmation crée d’abord l’école. Le compte principal et son accès temporaire seront confirmés ensuite. Aucun autre utilisateur n’est créé.</p><form method="post" target="_self" action="/Modules.php?modname=School_Setup/CopySchool.php&amp;delete_ok=1">';
    foreach(['token'=>$_SESSION['token'],'sskc_school_draft'=>$d['id'],'title'=>$a['TITLE'],'school_email'=>$a['school_email'],'school_slogan'=>sskcWizardSlogan($a)] as $k=>$v)sskcWizardHidden($k,(string)$v);
    foreach(['ADDRESS','CITY','PHONE','WWW_ADDRESS'] as $k)sskcWizardHidden('school_identity['.$k.']',(string)($a[$k]??''));
    foreach(['config','school_marking_periods','school_periods','school_gradelevels','report_card_grades','report_card_comments','eligibility_activities','attendance_codes'] as $t)sskcWizardHidden('tables['.$t.']','Y');
    echo '<div class="sskc-actions"><a target="_self" class="sskc-action" href="/schoolsafe-administrators.php?wizard=1&amp;step=1">Modifier le dossier</a><input type="submit" class="button-primary" value="Confirmer la création de l’école"></div></form>';
}

// Native controller hooks: no replacement of profile or school authorization functions.
function sskcWizardBeforeSchool(): void
{
    if(empty($_REQUEST['sskc_school_draft']))return;
    try {
        sskcCsrf();if(!sskcWizardAllowed())throw new RuntimeException('Ouverture non autorisée.');
        $d=sskcWizardDraft();
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST' || !hash_equals($d['id'],(string)$_POST['sskc_school_draft']) || $d['status']!=='draft' || $d['done']<5)throw new RuntimeException('Confirmation expirée ou déjà utilisée.');
        for($i=1;$i<=5;$i++)sskcWizardValidate($d,$i);
        if((int)UserSchool()!==(int)$d['source']){$_SESSION['UserSchool']=(int)$d['source'];UpdateSchoolArray((int)$d['source']);}
        if(DBGetOne("SELECT 1 FROM schools WHERE SYEAR=".(int)UserSyear()." AND UPPER(TITLE)=UPPER('".DBEscapeString($d['data']['TITLE'])."')"))throw new RuntimeException('Une école porte déjà ce nom. Reprenez sa fiche au lieu de la recréer.');
        $_POST['school_email']=$d['data']['school_email'];$_POST['school_slogan']=sskcWizardSlogan($d['data']);
        $_REQUEST['title']=DBEscapeString($d['data']['TITLE']);
        $_POST['school_slogan_extra']=[];
        foreach(['ADDRESS','CITY','PHONE','WWW_ADDRESS'] as $key)$_POST['school_identity'][$key]=DBEscapeString((string)($d['data'][$key]??''));
        $_REQUEST['tables']=array_fill_keys(['config','school_marking_periods','school_periods','school_gradelevels','report_card_grades','report_card_comments','eligibility_activities','attendance_codes'],'Y');
        // The SchoolSafe review is the confirmation. Do not display Rosario's second prompt.
        $_REQUEST['delete_ok']='1';
        $d['status']='creating';$_SESSION['sskc_school_draft']=$d;$GLOBALS['sskc_wizard_school']=$d;
    }catch(Throwable $e){echo '<p role="alert">'.sskcEscape($e instanceof RuntimeException?$e->getMessage():'Ouverture indisponible.').'</p><p><a href="/schoolsafe-administrators.php?wizard=1&amp;step=6">Revenir au dossier</a></p>';exit;}
}
function sskcWizardAfterSchool(): void
{
    if(empty($GLOBALS['sskc_wizard_school']))return;
    $d=$GLOBALS['sskc_wizard_school'];$school=(int)UserSchool();$a=$d['data'];
    // Record the created ID first: a later image error must never offer duplicate creation.
    $d['school']=$school;$d['status']='school_created';$_SESSION['sskc_school_draft']=$d;
    if(!SchoolSafeSchoolAllowed((string)$school) || !AllowEdit('School_Setup/Schools.php'))return;
    $values=[];foreach(['SHORT_NAME','SCHOOL_NUMBER','STATE','ZIPCODE','PRINCIPAL'] as $k)$values[$k]=DBEscapeString((string)($a[$k]??''));
    DBUpdate('schools',$values,['ID'=>$school,'SYEAR'=>(int)UserSyear()]);
    if(!empty($a['logo'])){
        require_once 'ProgramFunctions/FileUpload.fnc.php';
        $saved=ImageUpload($a['logo'],['width'=>1024,'height'=>1024],'assets/',[],'.jpg','school_logo_'.$school);
        if($saved)SchoolSafeUpdateReceiptBrand($school);
        else echo '<p role="alert">Le logo n’a pas pu être enregistré. Reprenez-le dans la fiche de l’école.</p>';
    }
    UpdateSchoolArray($school);
    echo '<p><a class="button-primary" target="_self" href="/schoolsafe-administrators.php?wizard=1&amp;step=6">Continuer : préparer le compte principal</a></p>';
}
function sskcWizardAfterStaff(): void
{
    global $UserPicturesPath;
    $d=$_SESSION['sskc_school_draft']??[];$intent=$GLOBALS['sskc_active_intent']??[];$id=(int)UserStaffID();
    if(empty($d['school']) || empty($intent['wizard_id']) || !hash_equals($d['id'],$intent['wizard_id']) || !$id || empty($_SESSION['sskc_handoff'][$id]))return;
    if(($intent['schools']??'')!==','.(int)$d['school'].',' || !SchoolSafeStaffWithinScope($id) || !sskcCanWrite())return;
    $d['staff']=$id;$d['status']='prepared';$_SESSION['sskc_school_draft']=$d;
    $request=DBInsert('schoolsafe_opening_requests',['SYEAR'=>(int)UserSyear(),'SCHOOL_NAME'=>DBEscapeString($d['data']['TITLE']),
        'PERSON_NAME'=>DBEscapeString(trim($d['data']['first_name'].' '.$d['data']['last_name'])),'EMAIL'=>DBEscapeString($d['data']['email']),
        'CREATED_BY'=>(int)User('STAFF_ID'),'UPDATED_BY'=>(int)User('STAFF_ID'),'SCHOOL_ID'=>(int)$d['school'],'RESPONSIBLE_ID'=>$id,'STATE'=>'prepared']);
    $_SESSION['sskc_school_draft']['request']=(int)$request;
    if(!empty($d['data']['portrait']) && AllowEdit('Users/User.php')) {
        require_once 'ProgramFunctions/FileUpload.fnc.php';
        $saved=ImageUpload($d['data']['portrait'],['width'=>150,'height'=>150],$UserPicturesPath.UserSyear().'/',[],'.jpg',$id.'.'.bin2hex(random_bytes(16)));
        if(!$saved)echo '<p role="alert">La photo n’a pas pu être enregistrée. Reprenez-la dans la fiche du responsable.</p>';
    }
    unset($_SESSION['sskc_school_draft']['data']['logo'],$_SESSION['sskc_school_draft']['data']['portrait']);
    echo '<p><a class="button-primary" target="_self" href="/schoolsafe-administrators.php?credential='.$id.'">Télécharger la fiche confidentielle et terminer</a></p>';
}
add_action('School_Setup/CopySchool.php|header','sskcWizardBeforeSchool');
add_action('School_Setup/CopySchool.php|copy_school','sskcWizardAfterSchool');
add_action('Users/User.php|create_user','sskcWizardAfterStaff');
