<?php
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || $_GET || $_POST) { http_response_code(400); exit('Requete invalide.'); }
chdir(__DIR__);
$_REQUEST = ['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level(); ob_start();
require_once __DIR__.'/Warehouse.php';
require_once __DIR__ . '/modules/SchoolSafe/Gateway.php';
$allowed=User('STAFF_ID') && User('PROFILE')==='parent'
    && SchoolSafeEndpointGateway('parent-notifications-view.php', 'GET', '', $_GET) !== 'deny';
while(ob_get_level()>$level) { ob_end_clean(); }
if (!$allowed) { http_response_code(403); exit('Connexion avec un compte parent Rosario requise.'); }
?>
<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Notifications | SchoolSafe</title>
<link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<script defer src="schoolsafe-card-bridge/parent-notification-data.js?v=native-scan-notices-1"></script>
<script defer src="schoolsafe-card-bridge/notification-push-subscribe.js"></script>
<script defer src="/pwa-outbox.js"></script>
<script defer src="schoolsafe-card-bridge/parent-notifications-view.js?v=native-scan-notices-1"></script></head>
<body><header><strong>SchoolSafe</strong><a href="Modules.php?modname=misc/Portal.php">Tableau de bord</a></header>
<main><h1>Notifications</h1><button id="enable-background-notifications" type="button">Activer les notifications en arriere-plan</button><nav aria-label="Categories de notifications">
<button type="button" data-filter="all" aria-pressed="true">Toutes</button>
<button type="button" data-filter="student.arrival" aria-pressed="false">Arrivées</button>
<button type="button" data-filter="student.pickup" aria-pressed="false">Récupérations</button>
<button type="button" data-filter="student.fee_control" aria-pressed="false">Contrôle des frais</button>
<button type="button" data-filter="remediation" aria-pressed="false">Rattrapage</button>
<button type="button" data-filter="palmares" aria-pressed="false">Top 10</button>
</nav><p id="state" role="status">Chargement des notifications...</p>
<button id="pwa-sync" type="button" title="Synchroniser" aria-label="Synchroniser">&#8635;</button><p id="pwa-sync-state" role="status"></p>
<ul id="notifications" aria-label="Notifications des enfants"></ul>
<button id="retry" type="button" hidden>Réessayer</button></main></body></html>
