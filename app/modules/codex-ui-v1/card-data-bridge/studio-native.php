<?php
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
chdir(__DIR__);
$_REQUEST = ['modname' => 'Student_ID_Card/StudentIDCard.php', 'modfunc' => ''];
ob_start(); require_once __DIR__ . '/Warehouse.php'; ob_end_clean();
if (!User('STAFF_ID') || !AllowUse('Student_ID_Card/StudentIDCard.php')) {
    http_response_code(403); exit('Connexion Rosario et permission Cartes requises.');
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
        $init->appendChild($doc->createTextNode("window.SchoolSafeNativeSchool=" . json_encode((string) UserSchool()) . ";window.DB={settings:{school:{},year:''},students:[],classes:[],users:[],aps:[]};window.SchoolSafeStudioStorage={getItem:k=>localStorage.getItem($scope+':'+k),setItem:(k,v)=>localStorage.setItem($scope+':'+k,v),removeItem:k=>localStorage.removeItem($scope+':'+k)};"));
        $script->parentNode->insertBefore($init, $script->nextSibling);
    }
}
foreach (['data-file', 'photo-file', 'export-png'] as $id) {
    $control = $xpath->query('//*[@id="' . $id . '"]')->item(0);
    if ($control) $control->setAttribute('disabled', 'disabled');
}
$form = $doc->createDocumentFragment();
$form->appendXML('<form id="native-lookup"><label for="native-student">Identifiant Rosario</label> <input id="native-student" required="required" inputmode="numeric" pattern="[1-9][0-9]{0,18}"/> <button>Consulter</button><button id="native-issue" type="button" disabled="disabled">Emettre la carte officielle</button><input id="native-issue-csrf" type="hidden" value="'.htmlspecialchars($_SESSION['schoolsafe_card_issue_csrf'], ENT_QUOTES, 'UTF-8').'"/><p>Donnees Rosario. Reglages visuels locaux par ecole et utilisateur. Emission soumise aux controles serveur. Impression non raccordee.</p></form>');
$main = $doc->getElementsByTagName('main')->item(0); $main->insertBefore($form, $main->firstChild);
foreach (['studio-original-adapter.js', 'studio-native-client.js', 'restored-studio-data.js', 'restored-studio-qr-guard.js', 'restored-studio-native.js'] as $file) {
    $script = $doc->createElement('script'); $script->setAttribute('src', '/schoolsafe-card-bridge/' . $file);
    $doc->getElementsByTagName('body')->item(0)->appendChild($script);
}
echo $doc->saveHTML();
