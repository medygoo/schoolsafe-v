<?php
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if($_SERVER['REQUEST_METHOD']!=='GET'||$_GET||$_POST){http_response_code(400);exit('Requete invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/modules/SchoolSafe/Gateway.php';
$allowed=User('STAFF_ID');$parent=User('PROFILE')==='parent';while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(401);exit('Connexion Rosario requise.');}
if(SchoolSafeEndpointGateway('family-management.php','GET')==='deny'){http_response_code(403);exit('Permission requise.');}
require_once __DIR__.'/ProgramFunctions/SchoolSafeScanBackend.fnc.php';
$familyEngine=SchoolSafeScanBridgeAvailable()?'bridge':'native';
$actions=[];
foreach(SchoolSafePermissionCatalog() as $definition)foreach($definition['routes'] as $route){
 if($route[0]==='scanner-relay.php'&&$route[1]==='management'
   &&SchoolSafeEndpointGateway('scanner-relay.php','POST','management',['action'=>$route[2]])!=='deny')$actions[]=$route[2];
}
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Familles et cartes | SchoolSafe</title>
<link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css"><link rel="stylesheet" href="schoolsafe-card-bridge/scanner-native.css">
<script defer src="schoolsafe-card-bridge/family-management.js?v=native-family-contracts-20261023"></script></head>
<body data-actions="<?= htmlspecialchars(json_encode(array_values(array_unique($actions)),JSON_THROW_ON_ERROR),ENT_QUOTES,'UTF-8') ?>" data-parent="<?= $parent?'true':'false' ?>" data-engine="<?=$familyEngine?>"><header><strong>SchoolSafe</strong><a href="scanner-native.php">Contrôle par carte</a></header>
<main><h1>Familles et cartes</h1><form id="management-form"><label>Action<select id="management-action"></select></label><div id="management-fields"></div><button type="submit" id="management-submit" disabled>Valider</button></form>
<p id="management-state" role="status">Chargement de la session...</p><section id="management-result" aria-label="Resultat"></section></main></body></html>
