<?php
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if($_SERVER['REQUEST_METHOD']!=='GET'||$_GET||$_POST){http_response_code(400);exit('Requete invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
$allowed=User('STAFF_ID');$parent=User('PROFILE')==='parent';while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(401);exit('Connexion Rosario requise.');}
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Familles et cartes | SchoolSafe</title>
<link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css"><link rel="stylesheet" href="schoolsafe-card-bridge/scanner-native.css">
<script defer src="schoolsafe-card-bridge/family-management.js"></script></head>
<body data-parent="<?= $parent?'true':'false' ?>"><header><strong>SchoolSafe</strong><a href="scanner-native.php">Contrôle par carte</a></header>
<main><h1>Familles et cartes</h1><form id="management-form"><label>Action<select id="management-action"></select></label><div id="management-fields"></div><button type="submit" id="management-submit" disabled>Valider</button></form>
<p id="management-state" role="status">Chargement de la session...</p><section id="management-result" aria-label="Resultat"></section></main></body></html>
