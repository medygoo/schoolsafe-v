<?php
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
$allowed=User('STAFF_ID')&&User('PROFILE')==='admin'&&AllowEdit('Student_Billing/StudentFees.php');
while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(403);exit('Permission administrative et financière requise.');}
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Contrôle des frais | SchoolSafe</title>
<link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css"><link rel="stylesheet" href="schoolsafe-card-bridge/scanner-native.css"><script defer src="schoolsafe-card-bridge/fee-control.js"></script></head>
<body><header><strong>SchoolSafe</strong><a href="scanner-native.php">Scanner</a></header><main><h1>Contrôle des frais</h1>
<p>Paiements nets de l’année scolaire Rosario sélectionnée. Unité monétaire de la facturation Rosario.</p>
<form id="fee-model-form"><label>Nom du barème<input name="label" required maxlength="100"></label><label>Minimum payé<input name="threshold" type="number" min="0.01" step="0.01" required></label><label>Tolérance (%)<input name="tolerance" type="number" min="0" max="100" step="1" value="10" required></label><button type="submit" disabled id="fee-save">Enregistrer le modèle</button></form>
<h2>Barèmes de cette année</h2><div id="fee-models"></div><button id="fee-stop" type="button" disabled>Arrêter le contrôle</button><p id="fee-state" role="status">Chargement...</p></main></body></html>
