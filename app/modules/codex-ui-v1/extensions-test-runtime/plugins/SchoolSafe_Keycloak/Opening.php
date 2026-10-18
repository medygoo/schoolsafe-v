<?php
declare(strict_types=1);

function sskcOpeningCentral(): bool
{
    return sskcCanRead() && SchoolSafeGlobalAdmin();
}
function sskcOpeningWrite(): bool
{
    return sskcOpeningCentral() && sskcCanWrite(true) && (int)UserSyear()===(int)Config('SYEAR');
}
function sskcCentralSchoolGuard(): void
{
    if (!SchoolSafeGlobalAdmin()) { SchoolSafeScopeDeny(); exit; }
}
// This hook runs before the native CopySchool mutations, including direct requests.
add_action('School_Setup/CopySchool.php|header','sskcCentralSchoolGuard');

function sskcOpeningMutation(): void
{
    sskcCsrf();
    if (!sskcOpeningWrite()) throw new RuntimeException('Gestion centrale et droits de création requis.');
    $action=(string)($_POST['opening_action']??'');
    if ($action==='record') {
        $values=[];
        foreach(['school_name'=>150,'person_name'=>150,'email'=>254] as $field=>$limit) {
            $v=trim((string)($_POST[$field]??''));
            if($v==='' || mb_strlen($v)>$limit) throw new RuntimeException('Renseignez les informations demandées.');
            $values[$field]=$v;
        }
        if(!filter_var($values['email'],FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Adresse e-mail non valide.');
        DBQuery("INSERT INTO schoolsafe_opening_requests(syear,school_name,person_name,email,created_by) VALUES("
            .(int)UserSyear().",'".DBEscapeString($values['school_name'])."','".DBEscapeString($values['person_name'])
            ."','".DBEscapeString($values['email'])."',".(int)User('STAFF_ID').')');
        return;
    }
    $id=(int)($_POST['request_id']??0);
    DBQuery('BEGIN');
    try {
        $rows=DBGet('SELECT * FROM schoolsafe_opening_requests WHERE id='.$id.' AND syear='.(int)UserSyear().' FOR UPDATE');
        $row=$rows[1]??null;
        if(!$row) throw new RuntimeException('Demande introuvable pour cette année.');
        if($action==='approve' && $row['STATE']==='requested') $newstate='approved';
        elseif($action==='close' && in_array($row['STATE'],['requested','approved'],true)) $newstate='closed';
        elseif($action==='attach' && $row['STATE']==='approved') {
            $school=(int)($_POST['school_id']??0);$staff=(int)($_POST['responsible_id']??0);
            if(!DBGetOne('SELECT id FROM schools WHERE id='.$school.' AND syear='.(int)UserSyear())) throw new RuntimeException('École non valide.');
            $account=sskcTarget($staff,true);
            $scope=SchoolSafeScopeParse($account['SCHOOLS']);
            if($account['PROFILE']!=='admin' || !is_array($scope) || !in_array($school,$scope,true)
                || !$account['SUBJECT'] || $account['IDENTITY_ENABLED']!=='Y'
                || !in_array($account['STATE'],['active','onboarding'],true)) {
                throw new RuntimeException('Choisissez un responsable limité aux écoles attribuées et avec un accès SchoolSafe activé.');
            }
            DBUpdate('schoolsafe_opening_requests',['SCHOOL_ID'=>$school,'RESPONSIBLE_ID'=>$staff],['ID'=>$id]);
            $newstate='prepared';
        } else throw new RuntimeException('Transition non autorisée.');
        DBUpdate('schoolsafe_opening_requests',['STATE'=>$newstate,'UPDATED_BY'=>(int)User('STAFF_ID')],['ID'=>$id]);
        DBQuery("INSERT INTO schoolsafe_opening_events(request_id,actor_staff_id,action) VALUES(".$id.','.(int)User('STAFF_ID').",'".DBEscapeString($action)."')");
        DBQuery('COMMIT');
    } catch(Throwable $e) { DBQuery('ROLLBACK');throw $e; }
}

function sskcOpeningPage(): void
{
    global $_ROSARIO;
    if(!sskcCanRead()) {SchoolSafeScopeDeny();exit;}
    $message='';$error='';
    if(($_SERVER['REQUEST_METHOD']??'')==='POST') {
        try {sskcOpeningMutation();$message='Opération enregistrée. Aucun accès ni mot de passe transmis automatiquement.';}
        catch(Throwable $e) {$error=$e instanceof RuntimeException?$e->getMessage():'Opération indisponible. Aucun succès confirmé.';}
    }
    
    $central=sskcOpeningCentral();
    sskcUiStart($central?'Écoles et demandes d’ouverture':'Gestion de votre école');
    if(sskcWizardAllowed())echo '<p><a target="_self" class="sskc-action button-primary" href="/schoolsafe-administrators.php?wizard=1">Ouvrir une école — six étapes</a></p>';
    echo '<p><a href="/schoolsafe-administrators.php">Administrateurs et accès temporaires</a></p>';
    if($message)echo '<p role="status">'.sskcEscape($message).'</p>';
    if($error)echo '<p role="alert">'.sskcEscape($error).'</p>';
    echo '<p>'.($central?'Validez la demande, préparez l’école dans SchoolSafe, créez son responsable et remettez son accès confidentiellement.'
        :'Vous gérez uniquement les établissements et fonctions qui vous sont attribués. L’ouverture d’une nouvelle école relève de SchoolSafe.').'</p>';
    if(!$central) {sskcUiEnd();exit;}
    $token=sskcEscape((string)$_SESSION['token']);$write=sskcOpeningWrite();
    if($write) {
        echo '<h2>Enregistrer une demande reçue</h2><p>Après vérification de la personne et de son établissement, validez la demande dans la liste. Ce formulaire ne crée aucun compte.</p><form target="_self" method="post">'
            .'<input type="hidden" name="token" value="'.$token.'"><input type="hidden" name="opening_action" value="record">';
        foreach(['school_name'=>'Nom de l’établissement','person_name'=>'Nom du futur responsable','email'=>'E-mail de contact'] as $field=>$label)
            echo '<p><label>'.sskcEscape($label).' <input required maxlength="'.($field==='email'?254:150).'" type="'.($field==='email'?'email':'text').'" name="'.$field.'"></label></p>';
        echo '<input type="submit" class="button-primary" value="Enregistrer la demande"></form>';
    }
    $rows=DBGet('SELECT r.*,s.TITLE AS SCHOOL_TITLE,u.USERNAME AS RESPONSIBLE_USERNAME FROM schoolsafe_opening_requests r LEFT JOIN schools s ON s.ID=r.SCHOOL_ID AND s.SYEAR=r.SYEAR LEFT JOIN staff u ON u.STAFF_ID=r.RESPONSIBLE_ID AND u.SYEAR=r.SYEAR WHERE r.SYEAR='.(int)UserSyear().' ORDER BY r.ID DESC');
    $schools=DBGet('SELECT ID,TITLE FROM schools WHERE syear='.(int)UserSyear().' ORDER BY TITLE');
    $people=DBGet("SELECT s.STAFF_ID,s.USERNAME FROM staff s JOIN schoolsafe_identity_links l ON l.STAFF_ID=s.STAFF_ID WHERE s.PROFILE='admin' AND s.SYEAR=".(int)UserSyear()." AND l.ENABLED='Y' AND l.STATE IN ('active','onboarding') ORDER BY s.USERNAME");
    $labels=['requested'=>'À examiner','approved'=>'Validée — école et responsable à préparer','prepared'=>'Préparée — liaison enregistrée','closed'=>'Demande close'];
    echo '<h2>Demandes d’ouverture</h2><p>« Préparée » confirme le rattachement ; ce statut ne prouve ni la remise du secret ni une première connexion réussie.</p>';
    foreach((array)$rows as $r) {
        echo '<section id="sskc-request-'.(int)$r['ID'].'" tabindex="-1"><h3>Demande n°'.(int)$r['ID'].' — '.sskcEscape($r['SCHOOL_NAME']).'</h3><p>'.sskcEscape($r['PERSON_NAME']).' — '.sskcEscape($r['EMAIL']).'</p><p>'.sskcEscape($labels[$r['STATE']]??'État inconnu').'</p>';
        if($r['SCHOOL_ID'])echo '<p>École : '.sskcEscape($r['SCHOOL_TITLE']??'À vérifier').' ; responsable : '.sskcEscape($r['RESPONSIBLE_USERNAME']??'À vérifier').'</p>';
        if($write && in_array($r['STATE'],['requested','approved'],true)) {
            echo '<form target="_self" method="post"><input type="hidden" name="token" value="'.$token.'"><input type="hidden" name="request_id" value="'.(int)$r['ID'].'">';
            if($r['STATE']==='requested')echo '<input type="hidden" name="opening_action" value="approve"><input type="submit" class="button-primary" value="Valider après vérification"> ';
            else {
                echo '<p>Après préparation de l’école et création de son responsable dans les fonctions ci-dessus :</p><label>École <select required name="school_id"><option value="">Choisir</option>';
                foreach((array)$schools as $s)echo '<option value="'.(int)$s['ID'].'">'.sskcEscape($s['TITLE']).'</option>';
                echo '</select></label> <label>Responsable lié <select required name="responsible_id"><option value="">Choisir</option>';
                foreach((array)$people as $p)echo '<option value="'.(int)$p['STAFF_ID'].'">'.sskcEscape($p['USERNAME']).'</option>';
                echo '</select></label><input type="hidden" name="opening_action" value="attach"><input type="submit" class="button-primary" value="Enregistrer le rattachement"> ';
            }
            echo '</form><form target="_self" method="post"><input type="hidden" name="token" value="'.$token.'"><input type="hidden" name="request_id" value="'.(int)$r['ID'].'"><input type="hidden" name="opening_action" value="close"><input type="submit" class="button" value="Clore la demande sans supprimer de compte"></form>';
        }
        echo '</section>';
    }
    if(!$rows)echo '<p>Aucune demande enregistrée pour cette année.</p>';
    echo '<h2>Remise de l’accès et accompagnement</h2><ol><li>Créez le responsable avec un profil délégable, les écoles explicites et le parcours Client.</li><li>Affichez une seule fois son accès temporaire et remettez-le confidentiellement.</li><li>Le client complète ses informations personnelles, choisit son mot de passe et entre automatiquement dans SchoolSafe, sans QR code ni second facteur obligatoire.</li></ol><p>L’équipe SchoolSafe conserve son authentification renforcée. L’accès temporaire n’est affiché que pendant dix minutes. Ce délai d’affichage ne correspond pas à sa durée de validité.</p>';
    sskcUiEnd();exit;
}

require_once __DIR__.'/Wizard.php';
