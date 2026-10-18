<?php
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || $_GET || $_POST) { http_response_code(400); exit('Requete invalide.'); }
chdir(__DIR__);
$_REQUEST = ['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level(); ob_start();
require_once __DIR__.'/Warehouse.php';
$allowed=User('STAFF_ID') && User('PROFILE')==='parent';
while(ob_get_level()>$level) { ob_end_clean(); }
if (!$allowed) { http_response_code(403); exit('Connexion avec un compte parent Rosario requise.'); }
?>
<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Notifications | SchoolSafe</title>
<link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<script defer src="schoolsafe-card-bridge/parent-notification-data.js"></script>
<script defer src="schoolsafe-card-bridge/parent-notifications-push.js"></script>
<script defer src="schoolsafe-card-bridge/parent-notifications-view.js"></script></head>
<body><header><strong>SchoolSafe</strong><a href="Modules.php?modname=misc/Portal.php">Tableau de bord</a></header>
<main><h1>Notifications</h1><nav aria-label="Categories de notifications">
<button type="button" data-filter="all" aria-pressed="true">Toutes</button>
<button type="button" data-filter="student.arrival" aria-pressed="false">Arrivées</button>
<button type="button" data-filter="student.pickup" aria-pressed="false">Récupérations</button>
<button type="button" data-filter="student.fee_control" aria-pressed="false">Contrôle des frais</button>
</nav><p id="state" role="status">Chargement des notifications...</p>
<ul id="notifications" aria-label="Notifications des enfants"></ul>
<button id="retry" type="button" hidden>Réessayer</button></main></body></html>
