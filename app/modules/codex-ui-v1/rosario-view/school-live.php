<?php
// Isolated presentation shell; native Rosario remains responsible for every operation.
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || array_diff(array_keys($_GET), ['view'])) {
    http_response_code(405);
    exit('Requete non prise en charge.');
}
$views = require __DIR__ . '/school-catalog.php';
$selected = $_GET['view'] ?? 'school';
if (!is_string($selected) || !isset($views[$selected])) {
    http_response_code(400);
    exit('Fonction inconnue.');
}
chdir('/var/www/html');
require_once 'Warehouse.php';
if (empty($_SESSION['STAFF_ID']) && empty($_SESSION['STUDENT_ID'])) {
    header('Location: /index.php');
    exit;
}
require_once 'Menu.php';
$visible = [];
foreach ($views as $key => [$module, $label]) {
    $route = 'School_Setup/' . $module . '.php';
    if (isset($_ROSARIO['Menu']['School_Setup'][$route]) && AllowUse($route)) {
        $visible[$key] = [$route, $label];
    }
}
if (!isset($visible[$selected])) {
    http_response_code(403);
    exit('Acces refuse par Rosario.');
}
header('Cache-Control: no-store');
header('Content-Security-Policy: frame-src \'self\'; object-src \'none\'; base-uri \'self\'; frame-ancestors \'self\'');
function schoolLiveEscape($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
[$route, $label] = $visible[$selected];
$nativeUrl = '/Modules.php?' . http_build_query(['modname' => $route]);
$sensitive = in_array($selected, ['copy', 'rollover', 'log'], true);
$groups = [
    'organisation' => ['Organisation', ['school', 'levels', 'periods', 'marking', 'calendar']],
    'communication' => ['Communication', ['notes', 'polls']],
    'parametres' => ['Parametres', ['fields', 'configuration']],
    'outils' => ['Outils et securite', ['copy', 'rollover', 'log']],
];
$currentGroup = '';
foreach ($groups as [$groupTitle, $keys]) {
    if (in_array($selected, $keys, true)) $currentGroup = $groupTitle;
}
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SchoolSafe - <?= schoolLiveEscape($label) ?></title><link rel="stylesheet" href="account-form.css?v=2"><link rel="stylesheet" href="school-workspace.css"><link rel="stylesheet" href="school-live.css"></head>
<body class="ss-account ss-school"><header class="account-bar"><div class="brand"><img src="schoolsafe-logo.png" alt="">SchoolSafe</div><a class="button" href="/schoolsafe-view/">Tableau de bord</a></header>
<a class="school-skip" href="#school-content">Aller au contenu</a>
<div class="school-layout"><aside><h2>Ecole</h2><details class="school-menu-disclosure" open><summary>Fonctions de l ecole</summary><nav aria-label="Fonctions Ecole autorisees">
<?php foreach ($groups as $group => [$groupTitle, $keys]):
    $allowedKeys = array_values(array_intersect($keys, array_keys($visible)));
    if (!$allowedKeys) continue;
?>
<section class="school-nav-group <?= schoolLiveEscape($group) ?>" aria-label="<?= schoolLiveEscape($groupTitle) ?>">
<h3><?= schoolLiveEscape($groupTitle) ?></h3><div>
<?php foreach ($allowedKeys as $key): [$native, $title] = $visible[$key]; ?>
<a href="?view=<?= schoolLiveEscape($key) ?>" <?= $key === $selected ? 'aria-current="page"' : '' ?>><?= schoolLiveEscape($title) ?></a>
<?php endforeach; ?></div></section>
<?php endforeach; ?></nav></details></aside>
<main id="school-content" tabindex="-1"><p class="school-location">Ecole / <?= schoolLiveEscape($currentGroup) ?> / <?= schoolLiveEscape($label) ?></p><h1><?= schoolLiveEscape($label) ?></h1><p>Session et droits Rosario · <?= schoolLiveEscape(User('PROFILE')) ?> · <?= schoolLiveEscape(UserSyear()) ?></p>
<?php if ($sensitive): ?>
<p class="caution"><?php if ($selected === 'log'): ?>Le journal natif peut purger automatiquement les entrees de plus d un an a son ouverture.<?php else: ?>Cette fonction peut copier ou remplacer des donnees. Les controles et confirmations restent dans Rosario.<?php endif; ?></p>
<a class="button primary" href="<?= schoolLiveEscape($nativeUrl) ?>">Ouvrir la fonction native Rosario</a>
<?php else: ?>
<a class="button" href="<?= schoolLiveEscape($nativeUrl) ?>" target="_blank" rel="noopener">Ouvrir dans un onglet separe</a>
<iframe class="native-school-frame" title="<?= schoolLiveEscape($label) ?> - formulaire natif Rosario" src="<?= schoolLiveEscape($nativeUrl) ?>"></iframe>
<?php endif; ?>
</main></div><script src="school-shell-navigation.js" defer></script><script src="school-native-presentation.js?v=3" defer></script><script src="school-existing-form.js?v=3" defer></script><script src="school-existing-lists.js?v=3" defer></script></body></html>
