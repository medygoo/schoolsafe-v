<?php
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
chdir(__DIR__);
$_REQUEST = ['modname' => 'Student_ID_Card/StudentIDCard.php', 'modfunc' => ''];
ob_start();
require_once __DIR__ . '/Warehouse.php';
ob_end_clean();
if (!User('STAFF_ID') || !AllowUse('Student_ID_Card/StudentIDCard.php')) {
    http_response_code(403);
    exit('Connexion Rosario et permission Cartes requises.');
}
$document = new DOMDocument();
libxml_use_internal_errors(true);
$document->loadHTMLFile(__DIR__ . '/schoolsafe-card-bridge/studio-cursor-reference/application-reference.html');
$studio = (new DOMXPath($document))->query('//*[@id="cardsStudio"]')->item(0);
if (!$studio) { http_response_code(503); exit('Studio de reference indisponible.'); }
$studio->removeAttribute('hidden');
foreach ((new DOMXPath($document))->query('.//button | .//input | .//select', $studio) as $control) {
    $control->setAttribute('disabled', 'disabled');
}
?>
<!doctype html>
<html lang="fr" data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SchoolSafe - Studio complet</title>
<link rel="stylesheet" href="/schoolsafe-card-bridge/studio-cursor-reference/styles/design-tokens.css">
<link rel="stylesheet" href="/schoolsafe-card-bridge/studio-cursor-reference/styles/design-system.css">
<link rel="stylesheet" href="/schoolsafe-card-bridge/studio-cursor-reference/cards/assets/cards.css">
<style>body{margin:0;font-family:Segoe UI,sans-serif;background:white}.cards-studio{position:relative;min-height:100dvh;height:auto;overflow:visible}.cards-studio-layout{min-height:70vh}.native-lookup{padding:16px;border-bottom:1px solid #dce4ef;display:flex;flex-wrap:wrap;gap:12px;align-items:center}.native-lookup input{width:100px;min-height:40px}.native-lookup button{min-height:40px}.cards-preview-wrap{background:#fff}.cards-studio-panel{box-sizing:border-box}.cards-studio-header p{white-space:normal}.ss-card-studio{max-width:100%;overflow:auto}@media(max-width:700px){.cards-studio-layout{display:block}.cards-studio-panel{width:100%;max-height:none}.cards-preview-wrap{display:block}}</style>
</head><body>
<form class="native-lookup" id="nativeLookup"><label for="nativeStudent">Identifiant Rosario</label><input id="nativeStudent" required inputmode="numeric" pattern="[1-9][0-9]{0,18}"><button>Consulter</button><a href="/Modules.php?modname=misc/Portal.php">Tableau de bord</a><span>Consultation native. Emission et impression non raccordees.</span></form>
<?php echo $document->saveHTML($studio); ?>
<script src="/schoolsafe-card-bridge/studio-cursor-reference/cards/card-adapter.js"></script>
<script src="/schoolsafe-card-bridge/studio-native-client.js"></script>
<script type="module" src="/schoolsafe-card-bridge/studio-complete-client.js"></script>
</body></html>
