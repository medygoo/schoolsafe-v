<?php
// Horaires d'arrivee par cycle (03/11) : l'administrateur de l'ecole regle seul l'heure d'arrivee,
// l'heure toleree et l'heure rouge de la Maternelle, du Primaire et du Secondaire/Humanites.
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)||$_GET){http_response_code(400);exit('Requête invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/modules/SchoolSafe/Gateway.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeArrivalHours.fnc.php';
$school=(int)UserSchool();$year=(int)UserSyear();
// Meme droit que les lieux de scan : administrateur de l'ecole avec la configuration de l'ecole.
$allowed=User('STAFF_ID')&&User('PROFILE')==='admin'&&$school>0&&$year>0&&AllowEdit('School_Setup/Configuration.php')
    &&SchoolSafeEndpointGateway('scan-locations.php','GET')!=='deny';
if(empty($_SESSION['SchoolSafeScannerCsrf']))$_SESSION['SchoolSafeScannerCsrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['SchoolSafeScannerCsrf'];
$get=function($sql){return DBGet(DBQuery($sql));};
$day=date('Y-m-d');$message='';$error='';
if($allowed&&$_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf']))throw new RuntimeException('Session expirée : rechargez la page.');
        $cycle=(string)($_POST['cycle']??'');
        // Enregistre apres l'heure d'arrivee : en vigueur le lendemain, jamais d'alerte d'absence retroactive le soir meme.
        $arrival=(string)($_POST['arrival']??'');
        $effective=strcmp(date('H:i'),$arrival)<0?$day:date('Y-m-d',strtotime('+1 day'));
        $count=SchoolSafeArrivalSave($school,$year,$cycle,$arrival,(string)($_POST['tolerated']??''),(string)($_POST['red']??''),$effective,$get);
        $message='Horaires enregistrés pour '.SchoolSafeArrivalCycles()[$cycle].' ('.$count.' niveau(x)), en vigueur '.($effective===$day?'dès aujourd’hui':'à partir de demain').'.';
    }catch(Throwable $failure){$error=$failure instanceof RuntimeException?$failure->getMessage():'Enregistrement impossible.';}
}
// Affiche aussi un horaire enregistre ce soir pour demain.
$cycles=$allowed?SchoolSafeArrivalRead($school,$year,date('Y-m-d',strtotime('+1 day')),$get):[];
while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(403);exit('Permission requise : administrateur de l’école.');}
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Horaires d’arrivée | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<style>.ss-hours{display:grid;gap:16px}.ss-hours form{border:1px solid #a9beb5;border-radius:8px;padding:14px;display:grid;gap:10px}
.ss-hours h2{margin:0;font-size:18px;color:#08766b}.ss-hours .grades{font-size:13px;color:#556777;margin:0}
.ss-hours .row{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px}.ss-hours label{display:grid;gap:4px;font-size:14px}
.ss-hours input[type=time]{min-height:40px;font-size:16px}.ss-hours button{min-height:44px;background:#08766b;color:#fff;border:0;border-radius:6px;font-size:15px}
.ss-legend{font-size:14px;line-height:1.6;background:#f4fbf8;border-left:4px solid #08766b;padding:10px 12px}.ss-ok{color:#08766b}.ss-ko{color:#a32d2d}</style></head>
<body><header><strong>SchoolSafe</strong><a href="scanner-native.php">Scans</a></header>
<main><h1>Horaires d’arrivée</h1>
<p class="ss-legend">🟢 <b>Heure d’arrivée</b> : jusqu’à cette heure, l’élève est à l’heure.<br>
🟡 <b>Heure tolérée</b> : jusqu’à cette heure, retard toléré.<br>
🟠 Entre l’heure tolérée et l’heure rouge : retard.<br>
🔴 <b>Heure rouge</b> : au-delà, retard grave (la direction est prévenue) ; c’est aussi l’heure de détection des absences.</p>
<?php if($message):?><p class="ss-ok" role="status"><?=$e($message)?></p><?php endif;?>
<?php if($error):?><p class="ss-ko" role="alert"><?=$e($error)?></p><?php endif;?>
<div class="ss-hours">
<?php foreach($cycles as $key=>$cycle): $h=$cycle['hours']??['07:30','07:45','08:30']; ?>
<form method="post"><input type="hidden" name="csrf" value="<?=$e($csrf)?>"><input type="hidden" name="cycle" value="<?=$e($key)?>">
<h2><?=$e($cycle['label'])?></h2>
<p class="grades"><?=$cycle['grades']?'Niveaux : '.$e(implode(', ',array_column($cycle['grades'],'title'))):'Aucun niveau de ce cycle dans l’école.'?>
<?=$cycle['hours']===null&&$cycle['grades']?' · <b>pas encore réglé</b> (valeurs proposées)':''?></p>
<div class="row"><label>🟢 Heure d’arrivée<input type="time" name="arrival" required value="<?=$e($h[0])?>"></label>
<label>🟡 Heure tolérée<input type="time" name="tolerated" required value="<?=$e($h[1])?>"></label>
<label>🔴 Heure rouge<input type="time" name="red" required value="<?=$e($h[2])?>"></label></div>
<button type="submit"<?=$cycle['grades']?'':' disabled'?>>Enregistrer <?=$e($cycle['label'])?></button></form>
<?php endforeach;?></div></main></body></html>
