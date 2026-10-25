<?php
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if($_SERVER['REQUEST_METHOD']!=='GET'||$_GET||$_POST){http_response_code(400);exit('Requete invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/modules/SchoolSafe/Gateway.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeScanBackend.fnc.php';
$locationEngine=SchoolSafeScanBridgeAvailable()?'bridge':'native';
$allowed=User('STAFF_ID')&&User('PROFILE')==='admin'&&(int)UserSchool()>0&&(int)UserSyear()>0&&AllowEdit('School_Setup/Configuration.php');
$allowed=$allowed&&SchoolSafeEndpointGateway('scan-locations.php','GET')!=='deny';
$canRead=$allowed&&($locationEngine==='native'?SchoolSafeNativeCommandPermission('SchoolSafe/ScanLocationsRead.php'):SchoolSafeEndpointGateway('scanner-relay.php','POST','location-management',['action'=>'list'])!=='deny');
$canSave=$allowed&&($locationEngine==='native'?SchoolSafeNativeCommandPermission('SchoolSafe/ScanLocationsManage.php'):SchoolSafeEndpointGateway('scanner-relay.php','POST','location-management',['action'=>'save'])!=='deny');
if(empty($_SESSION['SchoolSafeScannerCsrf']))$_SESSION['SchoolSafeScannerCsrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['SchoolSafeScannerCsrf'];
while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(403);exit('Permission requise.');}
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Lieux de scan | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<script defer src="schoolsafe-card-bridge/scan-locations.js?v=native-locations-20261023"></script></head>
<body><header><strong>SchoolSafe</strong><a href="scanner-native.php">Scans</a></header>
<main id="location-config" data-csrf="<?=htmlspecialchars($csrf,ENT_QUOTES,'UTF-8')?>" data-read="<?=$canRead?'1':'0'?>" data-save="<?=$canSave?'1':'0'?>" data-engine="<?=$locationEngine?>">
<h1>Lieux de scan</h1><p id="location-status" role="status"></p><section id="location-list" aria-label="Lieux enregistres"></section>
<?php if($canSave): ?><form id="location-form">
<input name="id" type="hidden">
<label data-bridge-location>Code<input name="code" required maxlength="100" autocomplete="off"></label>
<label>Nom du lieu<input name="label" required maxlength="200" autocomplete="off"></label>
<label data-bridge-location>Type<select name="kind" required><option value="">Choisir</option><option value="gate">Portail</option><option value="door">Porte</option><option value="classroom">Salle de classe</option><option value="office">Bureau</option><option value="control_point">Point de controle</option></select></label>
<label data-native-location hidden>Sens<select name="direction"><option value="both">Arrivée et retrait</option><option value="in">Arrivée uniquement</option><option value="out">Retrait uniquement</option></select></label>
<p data-native-location hidden>Point natif de cette école et de cette année. Aucune arrivée ne vaut présence en classe.</p>
<label><input name="is_active" type="checkbox">Actif</label>
<button type="submit">Enregistrer</button><button type="reset">Nouveau lieu</button>
</form><?php endif; ?></main></body></html>
