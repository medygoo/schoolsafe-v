<?php
require_once __DIR__.'/../../ProgramFunctions/SchoolSafeDeviceHub.fnc.php';
require_once __DIR__.'/../../ProgramFunctions/SchoolSafeDeviceCollector.fnc.php';
if(!SchoolSafeDeviceHubPermission()){http_response_code(403);echo 'Permission refusee.';return;}
$school=(int)UserSchool();$year=(int)UserSyear();$actor=(int)User('STAFF_ID');
$e=static function($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');};
$q=static function($v){return "'".DBEscapeString((string)$v)."'";};
if(empty($_SESSION['DeviceHubCsrf']))$_SESSION['DeviceHubCsrf']=bin2hex(random_bytes(32));
$write=SchoolSafeDeviceHubPermission(true);$notice='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!$write||!hash_equals($_SESSION['DeviceHubCsrf'],(string)($_POST['hub_csrf']??'')))throw new RuntimeException('Permission ou verification du formulaire refusee.');
        $action=$_POST['hub_action']??'';$deviceId=(int)($_POST['device_id']??0);
        if($action==='device'){
            $ip=(string)($_POST['ip']??'');$port=filter_var($_POST['port']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);
            $adapter=$_POST['adapter']??'';$scheme=$_POST['scheme']??'';$checkpoint=(int)($_POST['checkpoint']??0);
            if(!SchoolSafeDevicePrivateIp($ip)||!$port||!in_array($adapter,['hikvision_isapi','normalized_json'],true)||!in_array($scheme,['http','https'],true)||!DBGet("SELECT 1 FROM entry_exit_checkpoints WHERE id=$checkpoint"))throw new RuntimeException('Adresse IP locale, adaptateur ou point de passage invalide.');
            $values=[];foreach(['label'=>100,'manufacturer'=>60,'model'=>100,'serial'=>100] as $key=>$max){$value=trim((string)($_POST[$key]??''));if($value===''||strlen($value)>$max)throw new RuntimeException('Identification de l\'equipement incomplete.');$values[$key]=$q($value);}
            DBQuery("INSERT INTO schoolsafe_devices(school_id,label,manufacturer,model,serial,adapter,ip_address,port,scheme,checkpoint_id,created_by)
                VALUES($school,{$values['label']},{$values['manufacturer']},{$values['model']},{$values['serial']},".$q($adapter).",".$q($ip).",$port,".$q($scheme).",$checkpoint,$actor)");
            $notice='Equipement enregistre. Aucun identifiant biométrique conserve.';
        }else{
            $devices=DBGet("SELECT * FROM schoolsafe_devices WHERE id=$deviceId AND school_id=$school");
            if(count($devices)!==1)throw new RuntimeException('Equipement hors de votre ecole.');$device=reset($devices);
            if($action==='collector'){SchoolSafeCollectorConfigure($deviceId,$_POST);$notice='Collecteur automatique configure. La position de reprise existante est conservee.';}
            elseif($action==='stop_collector'){DBQuery("UPDATE schoolsafe_device_collectors SET enabled=false,revision=revision+1,updated_at=now() WHERE device_id=$deviceId");$notice='Collecteur suspendu. La file et la position sont conservees.';}
            elseif($action==='toggle'){DBQuery("UPDATE schoolsafe_devices SET enabled=NOT enabled WHERE id=$deviceId AND school_id=$school");$notice='Etat de l\'equipement modifie.';}
            elseif($action==='map'){
                $staff=(int)($_POST['staff_id']??0);$employee=trim((string)($_POST['employee_id']??''));
                if(!preg_match('/^[a-zA-Z0-9._:-]{1,64}$/D',$employee)||!DBGet("SELECT 1 FROM staff WHERE staff_id=$staff AND syear=$year AND profile IN ('admin','teacher') AND (current_school_id=$school OR position(',$school,' IN schools)>0)"))throw new RuntimeException('Identite professionnelle invalide. Les eleves et parents sont exclus.');
                DBQuery("INSERT INTO schoolsafe_device_staff(device_id,employee_id,syear,staff_id) VALUES($deviceId,".$q($employee).",$year,$staff)");
                $notice='Employee ID rattache au dossier Rosario existant pour cette annee.';
            }elseif(in_array($action,['import','sync'],true)){
                if($action==='import'){
                    $raw=(string)($_POST['events']??'');if(strlen($raw)>1000000)throw new RuntimeException('Lot trop volumineux.');
                    $input=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
                    if($device['ADAPTER']==='hikvision_isapi')$input=$input['AcsEvent']['InfoList']??$input;
                    if(!is_array($input)||!array_is_list($input))throw new RuntimeException('Une liste JSON est requise.');
                }else{
                    $from=DateTimeImmutable::createFromFormat('!Y-m-d',(string)($_POST['from']??''),new DateTimeZone('Africa/Kinshasa'));
                    $to=DateTimeImmutable::createFromFormat('!Y-m-d',(string)($_POST['to']??''),new DateTimeZone('Africa/Kinshasa'));
                    $position=filter_var($_POST['position']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>1000000]]);
                    if(!$from||!$to||$from->format('Y-m-d')!==($_POST['from']??'')||$to->format('Y-m-d')!==($_POST['to']??'')||$from>$to||$from->diff($to)->days>31||$position===false)throw new RuntimeException('Choisissez une periode de 32 jours maximum et une position valide.');
                    $input=SchoolSafeDeviceFetch($device,(string)($_POST['username']??''),(string)($_POST['password']??''),$from->format(DateTimeInterface::ATOM),$to->setTime(23,59,59)->format(DateTimeInterface::ATOM),$position);
                }
                $counts=SchoolSafeDeviceImport($deviceId,$input);
                $notice=$counts['imported'].' pointages natifs ; '.$counts['duplicate'].' doublons ignores ; '.$counts['review'].' evenements a verifier.';
            }else throw new RuntimeException('Action inconnue.');
        }
    }catch(Throwable $exception){$error=in_array($exception->getMessage(),['Permission refusee.','Equipement hors de votre ecole.'],true)?$exception->getMessage():'Operation non appliquee. Verifiez les champs, doublons d\'identifiants et correspondances de cet equipement.';}
}
DrawHeader('Equipements de presence du personnel');
echo '<link rel="stylesheet" href="assets/css/schoolsafe-device-hub.css"><div class="ss-device-hub"><p>Pointages du personnel uniquement. Les empreintes restent dans les appareils. Aucun calcul ni retrait automatique de salaire.</p>';
if($notice)echo '<p role="status">'.$e($notice).'</p>';if($error)echo '<p role="alert">'.$e($error).'</p>';
$devices=DBGet("SELECT d.*,c.title AS checkpoint FROM schoolsafe_devices d JOIN entry_exit_checkpoints c ON c.id=d.checkpoint_id WHERE d.school_id=$school ORDER BY d.label,d.id");
$token='<input type="hidden" name="hub_csrf" value="'.$e($_SESSION['DeviceHubCsrf']).'">';
$formUrl='Modules.php?modname='.rawurlencode($_REQUEST['modname']);
$open=static function($action)use($token,$formUrl){return '<form method="post" action="'.$formUrl.'">'.$token.'<input type="hidden" name="hub_action" value="'.$action.'">';};
$select='<label>Equipement<select name="device_id" required>';foreach($devices as $d)$select.='<option value="'.(int)$d['ID'].'">'.$e($d['LABEL']).'</option>';$select.='</select></label>';
echo '<h3>Equipements de votre ecole</h3><div class="ss-device-table"><table><thead><tr><th>Equipement</th><th>Adresse</th><th>Adaptateur</th><th>Passage</th><th>Etat</th><th>Synchronisation</th></tr></thead><tbody>';
foreach($devices as $d){echo '<tr><td>'.$e($d['LABEL'].' · '.$d['MODEL']).'</td><td>'.$e($d['IP_ADDRESS'].':'.$d['PORT']).'</td><td>'.$e($d['ADAPTER']).'</td><td>'.$e($d['CHECKPOINT']).'</td><td>'.(in_array($d['ENABLED'],[true,'t','1',1],true)?'Actif':'Suspendu');if($write)echo $open('toggle').'<input type="hidden" name="device_id" value="'.(int)$d['ID'].'"><button type="submit">Activer / suspendre</button></form>';echo '</td><td>'.$e($d['LAST_SYNC_AT']??'Jamais').'</td></tr>';}
if(!$devices)echo '<tr><td colspan="6">Aucun equipement configure.</td></tr>';echo '</tbody></table></div>';
if($write){
    echo '<details><summary>Configurer la collecte automatique</summary><p>Collecte en arriere-plan, avec reprise apres coupure. Les identifiants sont chiffres ; les droits du responsable sont reverifies a chaque cycle.</p>'.$open('collector').'<div class="ss-device-fields">'.$select.'<label>Utilisateur du terminal<input name="username" required autocomplete="off" maxlength="100"></label><label>Mot de passe du terminal<input name="password" type="password" required autocomplete="new-password" maxlength="500"></label><label>Premiere collecte a partir du<input name="start" type="date" required></label><label>Intervalle en secondes<input name="interval" type="number" min="30" max="3600" value="60" required></label></div><button type="submit">Activer le collecteur</button></form></details>';
    echo '<details><summary>Ajouter un equipement</summary>'.$open('device').'<div class="ss-device-fields">';
    foreach(['label'=>'Nom','manufacturer'=>'Fabricant','model'=>'Modele','serial'=>'Numero de serie','ip'=>'Adresse IP locale'] as $key=>$title)echo '<label>'.$title.'<input name="'.$key.'" required maxlength="'.($key==='ip'?15:($key==='manufacturer'?60:100)).'"'.($key==='ip'?' placeholder="192.168.1.20"':'').'></label>';
    echo '<label>Port<input name="port" type="number" min="1" max="65535" value="80" required></label><label>Connexion<select name="scheme"><option value="http">HTTP local</option><option value="https">HTTPS</option></select></label><label>Adaptateur<select name="adapter"><option value="hikvision_isapi">Hikvision ISAPI</option><option value="normalized_json">Import JSON normalise / autre fabricant</option></select></label><label>Point de passage<select name="checkpoint" required>';
    foreach(DBGet('SELECT id,title FROM entry_exit_checkpoints ORDER BY title') as $point)echo '<option value="'.(int)$point['ID'].'">'.$e($point['TITLE']).'</option>';
    echo '</select></label></div><button type="submit">Enregistrer l\'equipement</button></form></details>';
    echo '<details><summary>Associer un membre du personnel</summary><p>Le meme Employee ID peut etre rattache au dossier de la nouvelle annee apres verification. Aucune nouvelle personne n\'est creee.</p>'.$open('map').'<div class="ss-device-fields">'.$select.'<label>Employee ID<input name="employee_id" required maxlength="64"></label><label>Personnel Rosario<select name="staff_id" required>';
    foreach(DBGet("SELECT staff_id,first_name,last_name FROM staff WHERE syear=$year AND profile IN ('admin','teacher') AND (current_school_id=$school OR position(',$school,' IN schools)>0) ORDER BY last_name,first_name") as $person)echo '<option value="'.(int)$person['STAFF_ID'].'">'.$e($person['FIRST_NAME'].' '.$person['LAST_NAME']).'</option>';
    echo '</select></label></div><button type="submit">Associer pour '.$year.'</button></form></details>';
    echo '<details><summary>Synchroniser un Hikvision sur le reseau local</summary><p>Les identifiants sont utilises pour cette requete seulement, jamais enregistres. Lot de 100 evenements maximum ; augmentez la position pour poursuivre. La connexion physique reste a valider sur votre firmware.</p>'.$open('sync').'<div class="ss-device-fields">'.$select.'<label>Utilisateur du terminal<input name="username" required autocomplete="off"></label><label>Mot de passe du terminal<input name="password" type="password" required autocomplete="off"></label><label>Du<input name="from" type="date" required></label><label>Au<input name="to" type="date" required></label><label>Position dans les resultats<input name="position" type="number" min="0" value="0" required></label></div><button type="submit">Lire les evenements</button></form></details>';
    echo '<details><summary>Importer un lot / reprendre apres une coupure</summary>'.$open('import').$select.'<label>Evenements JSON<textarea name="events" required rows="6" maxlength="1000000"></textarea></label><button type="submit">Verifier et importer</button></form></details>';
}
$collectors=DBGet("SELECT c.device_id,c.enabled,c.page_position,c.cursor_at,c.last_success_at,c.last_error,c.failures,d.label,
    (SELECT COUNT(*) FROM schoolsafe_device_queue q WHERE q.device_id=c.device_id AND q.delivered_at IS NULL) AS pending
    FROM schoolsafe_device_collectors c JOIN schoolsafe_devices d ON d.id=c.device_id WHERE d.school_id=$school ORDER BY d.label");
echo '<h3>Collecte automatique et reprise</h3><div class="ss-device-table"><table><thead><tr><th>Equipement</th><th>Collecteur</th><th>Position</th><th>En attente</th><th>Derniere lecture</th><th>Diagnostic</th></tr></thead><tbody>';
foreach($collectors as $c){echo '<tr><td>'.$e($c['LABEL']).'</td><td>'.(in_array($c['ENABLED'],[true,'t',1,'1'],true)?'Active':'Suspendue');if($write)echo $open('stop_collector').'<input type="hidden" name="device_id" value="'.(int)$c['DEVICE_ID'].'"><button type="submit">Suspendre</button></form>';echo '</td><td>'.$e($c['CURSOR_AT']).' · '.(int)$c['PAGE_POSITION'].'</td><td>'.(int)$c['PENDING'].'</td><td>'.$e($c['LAST_SUCCESS_AT']??'Jamais').'</td><td>'.$e($c['LAST_ERROR']??'Aucune erreur').'</td></tr>';}
if(!$collectors)echo '<tr><td colspan="6">Aucun collecteur automatique configure.</td></tr>';echo '</tbody></table></div>';
$events=DBGet("SELECT e.*,d.label,s.first_name,s.last_name FROM schoolsafe_device_events e JOIN schoolsafe_devices d ON d.id=e.device_id LEFT JOIN staff s ON s.staff_id=e.staff_id WHERE d.school_id=$school ORDER BY e.occurred_at DESC,e.id DESC LIMIT 100");
echo '<h3>Derniers evenements et anomalies</h3><div class="ss-device-table"><table><thead><tr><th>Date</th><th>Equipement</th><th>Employee ID</th><th>Personnel</th><th>Sens</th><th>Traitement</th></tr></thead><tbody>';
foreach($events as $event)echo '<tr><td>'.$e($event['OCCURRED_AT']).'</td><td>'.$e($event['LABEL']).'</td><td>'.$e($event['EMPLOYEE_ID']).'</td><td>'.$e(($event['FIRST_NAME']??'').' '.($event['LAST_NAME']??'')).'</td><td>'.$e($event['DIRECTION']).'</td><td>'.$e($event['STATUS']).'</td></tr>';
if(!$events)echo '<tr><td colspan="6">Aucun evenement recu.</td></tr>';echo '</tbody></table></div></div>';
