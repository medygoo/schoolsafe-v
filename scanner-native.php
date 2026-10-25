<?php
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if($_SERVER['REQUEST_METHOD']!=='GET'||$_GET||$_POST){http_response_code(400);exit('Requete invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/modules/SchoolSafe/Gateway.php';
$allowed=User('STAFF_ID');$canManageFees=User('PROFILE')==='admin'&&AllowEdit('Student_Billing/StudentFees.php');while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(401);exit('Connexion School Safe requise.');}
if(SchoolSafeEndpointGateway('scanner-native.php','GET')==='deny'){http_response_code(403);exit('Permission requise.');}
$canManageFees=$canManageFees&&SchoolSafeEndpointGateway('fee-control.php','GET')!=='deny';
$canManageLocations=User('PROFILE')==='admin'&&AllowEdit('School_Setup/Configuration.php')&&SchoolSafeEndpointGateway('scan-locations.php','GET')!=='deny';
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Présence &amp; Scan QR | SchoolSafe</title><script src="schoolsafe-card-bridge/scanner-theme.js?v=1"></script><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<link rel="stylesheet" href="schoolsafe-card-bridge/fee-colors.css"><link rel="stylesheet" href="schoolsafe-card-bridge/scanner-native.css?v=scan-theme-1"><script defer src="assets/themes/SchoolSafeCodex/brand-presentation.js?v=scan-1"></script><script defer src="schoolsafe-card-bridge/scanner-native.js?v=native-entry-operation-4"></script></head>
<body><a class="skip-link" href="#scanner">Aller au scanner</a><header class="scan-header"><a class="scan-back" href="Modules.php?modname=misc/Portal.php" aria-label="Retour au tableau de bord">←</a><strong><img src="/schoolsafe-logo.png" alt="" width="56" height="56">SchoolSafe</strong><span class="scan-header-space" aria-hidden="true"></span></header>
<main id="scanner"><div class="scan-heading"><p class="scan-eyebrow">Contrôle par carte · SchoolSafe</p><h1>Présence &amp; Scan QR</h1><p>Choisissez une tâche, puis présentez le QR de la carte devant la caméra.</p></div>
<nav id="modes" aria-label="Type de scan"></nav>
<p id="scanner-state" role="status">Chargement des autorisations...</p>
<section class="scan-workspace" aria-label="Lecteur de QR"><div class="scan-viewfinder"><div class="scan-viewfinder-corners" aria-hidden="true"></div><div class="scan-viewfinder-idle"><span class="scan-viewfinder-symbol" aria-hidden="true">▣</span><strong>Lecteur de carte</strong><span>Le QR reste sur la carte : présentez-le devant l’objectif.</span><button id="camera" type="button" aria-label="Ouvrir la caméra" disabled>Ouvrir la caméra</button></div>
<section id="camera-area" hidden aria-label="Aperçu de la caméra"><video id="scan-video" autoplay playsinline muted></video><button id="camera-stop" type="button">Fermer la caméra</button></section></div>
<form id="scan-form" hidden><label id="location-label">Lieu de scan<select id="scan-location" required></select></label>
<label>Code QR <span class="scan-hint">ou lecteur externe</span><input id="scan-qr" autocomplete="off" required maxlength="2000" placeholder="Présentez la carte ou saisissez son code"></label>
<div class="scan-actions"><button type="submit">Vérifier le code</button></div></form></section>
<section class="scan-feedback" aria-label="Résultat du scan"><h2>Résultat</h2><div id="scan-result" role="status" aria-live="polite"></div><section id="scan-people" aria-label="Personnes autorisées"></section>
<form id="pickup-confirm" hidden><label><input id="human-confirm" type="checkbox" required> Je confirme avoir comparé la personne présente à sa photo.</label><button type="submit">Confirmer la remise</button></form></section>
<button id="scanner-retry" type="button" hidden>Réessayer</button>
<footer class="scan-links"><a href="Modules.php?modname=misc/Portal.php">Tableau de bord</a><a href="family-management.php">Familles et portraits</a><?php if($canManageFees): ?><a href="fee-control.php">Barèmes des frais</a><?php endif; ?><?php if($canManageLocations): ?><a href="scan-locations.php">Lieux de scan</a><?php endif; ?></footer></main></body></html>
