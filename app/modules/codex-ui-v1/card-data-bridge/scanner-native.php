<?php
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if($_SERVER['REQUEST_METHOD']!=='GET'||$_GET||$_POST){http_response_code(400);exit('Requete invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
$allowed=User('STAFF_ID');$canManageFees=User('PROFILE')==='admin'&&AllowEdit('Student_Billing/StudentFees.php');while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(401);exit('Connexion Rosario requise.');}
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Contrôle par carte | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<link rel="stylesheet" href="schoolsafe-card-bridge/scanner-native.css"><link rel="stylesheet" href="schoolsafe-card-bridge/fee-colors.css"><script defer src="schoolsafe-card-bridge/scanner-native.js"></script></head>
<body><header><strong>SchoolSafe</strong><a href="Modules.php?modname=misc/Portal.php">Tableau de bord</a></header>
<main><h1>Contrôle par carte</h1><p><a href="family-management.php">Familles, portraits et tâches de scan</a><?php if($canManageFees): ?> · <a href="fee-control.php">Barèmes de contrôle des frais</a><?php endif; ?></p><nav id="modes" aria-label="Type de scan"></nav>
<p id="scanner-state" role="status">Chargement des autorisations...</p>
<form id="scan-form" hidden><label id="location-label">Lieu<select id="scan-location" required></select></label>
<label>Code QR<input id="scan-qr" autocomplete="off" required maxlength="2000"></label>
<div class="scan-actions"><button type="submit">Scanner</button><button id="camera" type="button" aria-label="Ouvrir la camera">Caméra</button></div></form>
<section id="camera-area" hidden><video id="scan-video" autoplay playsinline muted></video><button id="camera-stop" type="button">Fermer la caméra</button></section>
<div id="scan-result" role="status"></div><section id="scan-people" aria-label="Personnes autorisees"></section>
<form id="pickup-confirm" hidden><label><input id="human-confirm" type="checkbox" required> Je confirme avoir comparé la personne présente à sa photo.</label><button type="submit">Confirmer la remise</button></form>
<button id="scanner-retry" type="button" hidden>Réessayer</button></main></body></html>
