<?php
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
chdir(__DIR__);
$_REQUEST = ['modname' => 'Student_ID_Card/StudentIDCard.php', 'modfunc' => ''];
ob_start(); require_once __DIR__ . '/Warehouse.php'; ob_end_clean();
if (!User('STAFF_ID') || !AllowUse('Student_ID_Card/StudentIDCard.php')) {
    http_response_code(403); exit('Connexion SchoolSafe et permission Cartes requises.');
}
require_once __DIR__ . '/modules/SchoolSafe/Gateway.php';
if (SchoolSafeEndpointGateway('studio-native.php', $_SERVER['REQUEST_METHOD'], '', $_GET) === 'deny') {
    http_response_code(403); exit('Acces SchoolSafe non autorise.');
}
$root = __DIR__ . '/schoolsafe-card-bridge/studio-validated-20261008/';
if (isset($_GET['asset'])) {
    $asset = $_GET['asset'];
    if (!in_array($asset, ['studio-card-options.js', 'studio-models.js', 'studio-layout.js'], true)) {
        http_response_code(400); exit('Ressource invalide');
    }
    header('Content-Type: application/javascript; charset=utf-8');
    echo str_replace('localStorage.', 'window.SchoolSafeStudioStorage.', file_get_contents($root . $asset));
    exit;
}
header('Content-Type: text/html; charset=utf-8');
if (empty($_SESSION['schoolsafe_card_issue_csrf'])) $_SESSION['schoolsafe_card_issue_csrf'] = bin2hex(random_bytes(32));
$doc = new DOMDocument(); libxml_use_internal_errors(true); $doc->loadHTMLFile($root . 'index.html');
$xpath = new DOMXPath($doc);
$base = $doc->createElement('base');
$base->setAttribute('href', '/schoolsafe-card-bridge/studio-validated-20261008/');
$head = $doc->getElementsByTagName('head')->item(0);
$head->insertBefore($base, $head->firstChild);
foreach ($xpath->query('//script[@src]') as $script) {
    $src = $script->getAttribute('src');
    if ($src === 'start.js') { $script->parentNode->removeChild($script); continue; }
    if (in_array($src, ['studio-card-options.js', 'studio-models.js', 'studio-layout.js'], true)) {
        $script->setAttribute('src', '/studio-native.php?asset=' . rawurlencode($src));
    }
    if ($src === 'host-adapter.js') {
        $init = $doc->createElement('script');
        $scope = json_encode('codex-addons-local:' . UserSchool() . ':' . UserSyear() . ':' . User('STAFF_ID'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $init->appendChild($doc->createTextNode("window.SchoolSafeNativeSchool=" . json_encode((string) UserSchool()) . ";window.SchoolSafeNativeYear=" . json_encode((string) UserSyear()) . ";window.DB={settings:{school:{},year:''},students:[],classes:[],users:[],aps:[]};window.SchoolSafeStudioStorage={getItem:k=>localStorage.getItem($scope+':'+k),setItem:(k,v)=>localStorage.setItem($scope+':'+k,v),removeItem:k=>localStorage.removeItem($scope+':'+k)};"));
        $script->parentNode->insertBefore($init, $script->nextSibling);
    }
}
foreach (['data-file', 'photo-file', 'export-png'] as $id) {
    $control = $xpath->query('//*[@id="' . $id . '"]')->item(0);
    if ($control) $control->setAttribute('disabled', 'disabled');
}
$form = $doc->createDocumentFragment();
$form->appendXML('<form id="native-lookup"><label for="native-student">Identifiant scolaire</label> <input id="native-student" required="required" inputmode="numeric" pattern="[1-9][0-9]{0,18}"/> <button>Consulter</button><button id="native-issue" type="button" disabled="disabled">Emettre la carte officielle</button><input id="native-issue-csrf" type="hidden" value="'.htmlspecialchars($_SESSION['schoolsafe_card_issue_csrf'], ENT_QUOTES, 'UTF-8').'"/><p>Informations scolaires. Reglages visuels locaux par ecole et utilisateur. Emission soumise aux controles serveur. Demande d impression soumise aux controles serveur. Recto et verso disponibles uniquement apres verification du dossier et des assets. Impression navigateur possible sous ces controles ; impression physique non certifiee.</p></form>');
$main = $doc->getElementsByTagName('main')->item(0); $main->insertBefore($form, $main->firstChild);
$print = $doc->createElement('button', 'Demander l impression officielle');
$print->setAttribute('id', 'native-print');
$print->setAttribute('type', 'button');
$print->setAttribute('disabled', 'disabled');
$xpath->query('//*[@id="native-lookup"]')->item(0)->appendChild($print);
$preview = $doc->createElement('button', 'Ouvrir le recto et le verso officiels');
$preview->setAttribute('id', 'native-print-preview');
$preview->setAttribute('type', 'button');
$preview->setAttribute('disabled', 'disabled');
$xpath->query('//*[@id="native-lookup"]')->item(0)->appendChild($preview);
foreach (['studio-original-adapter.js', 'studio-native-client.js', 'restored-studio-data.js', 'restored-studio-qr-guard.js', 'studio-official-print-render.js', 'restored-studio-native.js'] as $file) {
    $version = substr(hash_file('sha256', __DIR__ . '/schoolsafe-card-bridge/' . $file), 0, 16);
    $script = $doc->createElement('script'); $script->setAttribute('src', '/schoolsafe-card-bridge/' . $file . '?v=' . $version);
    $doc->getElementsByTagName('body')->item(0)->appendChild($script);
}
$presentation = $doc->createElement('link');
$presentation->setAttribute('rel', 'stylesheet');
$presentation->setAttribute('href', '/schoolsafe-card-bridge/studio-harmony.css?v=1');
$head->appendChild($presentation);
$presentationScript = $doc->createElement('script');
$presentationScript->setAttribute('src', '/schoolsafe-card-bridge/studio-harmony.js?v=1');
$doc->getElementsByTagName('body')->item(0)->appendChild($presentationScript);
echo $doc->saveHTML();
