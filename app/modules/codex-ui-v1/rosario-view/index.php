<?php
// Presentation only: use Rosario's session, permissions and native readers.
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || $_GET) {
    http_response_code(405);
    exit('Lecture uniquement.');
}
chdir('/var/www/html');
require_once 'Warehouse.php';
if (empty($_SESSION['STAFF_ID']) && empty($_SESSION['STUDENT_ID'])) {
    header('Location: /index.php');
    exit;
}
if (!AllowUse('misc/Portal.php')) {
    http_response_code(403);
    exit('Acces refuse.');
}
header('Cache-Control: no-store');
function ssviewEscape($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function ssviewText($value) { return html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8'); }
require_once 'Menu.php';
$schoolCatalog = require __DIR__ . '/school-catalog.php';
function ssviewDestination($route) {
    global $schoolCatalog;
    foreach ($schoolCatalog as $key => [$module, $label]) {
        if ($route === 'School_Setup/' . $module . '.php') {
            return '/schoolsafe-view/school-live.php?' . http_build_query(['view' => $key]);
        }
    }
    return '/Modules.php?modname=' . $route;
}
require_once 'modules/misc/includes/SchoolSafeDashboard.inc.php';
$screenBindings = json_decode(file_get_contents(__DIR__ . '/screen-bindings.json'), true, 512, JSON_THROW_ON_ERROR);
$readers = [
    ['Connexions enregistrees', 'School_Setup/AccessLog.php', 'School_Setup', 'DashboardSchoolSetupAdmin'],
    ['Eleves inscrits', 'Students/Student.php', 'Students', 'DashboardStudentsAdmin'],
    ['Utilisateurs', 'Users/User.php', 'Users', 'DashboardUsersAdmin'],
    ['Cours', 'Scheduling/Courses.php', 'Scheduling', 'DashboardSchedulingAdmin'],
    ['Notes et moyennes', 'Grades/GPARankList.php', 'Grades', 'DashboardGradesAdmin'],
    ['Absences des eleves', 'Attendance/Administration.php', 'Attendance', 'DashboardAttendanceAdmin'],
    ['Activites', 'Eligibility/Activities.php', 'Eligibility', 'DashboardEligibilityAdmin'],
    ['Discipline', 'Discipline/Referrals.php', 'Discipline', 'DashboardDisciplineAdmin'],
    ['Comptabilite', 'Accounting/DailyTransactions.php', 'Accounting', 'DashboardAccountingAdmin'],
    ['Frais scolaires', 'Student_Billing/DailyTransactions.php', 'Student_Billing', 'DashboardStudentBillingAdmin'],
    ['Restauration', 'Food_Service/Accounts.php', 'Food_Service', 'DashboardFoodServiceAdmin'],
];
$links = [
    'Eleves' => 'Students/Student.php',
    'Cours' => 'Scheduling/Courses.php',
    'Presences' => 'Attendance/TakeAttendance.php',
    'Profils' => 'Users/Profiles.php',
];
$accountLinks = [
    'Consulter les utilisateurs' => 'Users/User.php',
    'Ajouter un utilisateur' => 'Users/User.php&staff_id=new',
    'Profils utilisateurs' => 'Users/Profiles.php',
    'Droits d acces' => 'Users/Exceptions.php',
    'Associer les eleves aux parents' => 'Users/AddStudents.php',
    'Mes preferences' => 'Users/Preferences.php',
];
$allowedAccountLinks = array_filter($accountLinks, function ($route) {
    return SchoolSafeMenuProgramTitle($route) !== '' && AllowUse($route);
});
$studentLinks = [
    'Consulter les dossiers' => 'Students/Student.php',
    'Inscrire un eleve' => 'Students/Student.php&include=General_Info&student_id=new',
    'Parents associes' => 'Students/AddUsers.php',
    'Rapport avance' => 'Students/AdvancedReport.php',
    'Repartition des eleves' => 'Students/StudentBreakdown.php',
    'Imprimer les dossiers' => 'Students/PrintStudentInfo.php',
];
$allowedStudentLinks = array_filter($studentLinks, function ($route) {
    return SchoolSafeMenuProgramTitle($route) !== '' && AllowUse($route);
});
?>
<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>SchoolSafe - Vue generale Rosario</title>
<link rel="stylesheet" href="/schoolsafe-view/codex-ui-v1.css">
<link rel="stylesheet" href="/schoolsafe-view/view.css"></head>
<body class="sscx-app"><header class="sscx-topbar"><div><strong>SchoolSafe</strong><span class="sscx-eyebrow">Rosario local · <?= ssviewEscape(User('PROFILE')) ?> · <?= ssviewEscape(UserSyear()) ?></span></div><a href="/Modules.php?modname=misc/Portal.php">Portail Rosario</a></header>
<main class="sscx-content"><h1>Vue generale</h1><nav class="ssview-links" aria-label="Acces autorises">
<?php foreach ($links as $label => $route): if (SchoolSafeMenuProgramTitle($route) === '' || !AllowUse($route)) continue; ?>
<a href="/Modules.php?<?= ssviewEscape(http_build_query(['modname' => $route])) ?>"><?= ssviewEscape($label) ?></a>
<?php endforeach; ?></nav>
<div class="ssview-grid">
<?php if (User('PROFILE') === 'admin'): foreach ($readers as [$label, $route, $module, $reader]):
    if (empty($RosarioModules[$module]) || !AllowUse($route)) continue;
    require_once "modules/$module/includes/Dashboard.inc.php";
    $data = $reader();
?>
<section class="ssview-metric"><h2><?= ssviewEscape($label) ?></h2>
<?php if (!$data): ?><p>Aucune valeur disponible dans le tableau de bord natif.</p>
<?php else: $totalLabel = array_key_first($data); $total = array_shift($data); ?><strong class="ssview-total"><?= ssviewEscape(ssviewText($total)) ?></strong><p><?= ssviewEscape(ssviewText($totalLabel)) ?></p>
<dl><?php foreach ($data as $key => $value): if ($value === null) continue; ?><div><dt><?= ssviewEscape(ssviewText($key)) ?></dt><dd><?= ssviewEscape(ssviewText($value)) ?></dd></div><?php endforeach; ?></dl>
<?php endif; ?></section>
<?php endforeach; else: ?><p>Les totaux administratifs ne sont pas disponibles pour ce profil. Consultez vos fonctions autorisees.</p><?php endif; ?>
</div>
<?php if ($allowedAccountLinks): ?>
<section class="ssview-status"><h2>Comptes et droits</h2><nav class="ssview-links" aria-label="Comptes et droits Rosario">
<?php foreach ($allowedAccountLinks as $label => $route): ?>
<a href="<?= ssviewEscape('/Modules.php?modname=' . $route) ?>"><?= ssviewEscape($label) ?></a>
<?php endforeach; ?></nav></section>
<?php endif; ?>
<?php if ($allowedStudentLinks): ?>
<section class="ssview-status"><h2>Eleves et inscriptions</h2><nav class="ssview-links" aria-label="Eleves Rosario">
<?php foreach ($allowedStudentLinks as $label => $route): ?>
<a href="<?= ssviewEscape('/Modules.php?modname=' . $route) ?>"><?= ssviewEscape($label) ?></a>
<?php endforeach; ?></nav></section>
<?php endif; ?>
<?php if (User('PROFILE') === 'admin'): ?>
<section class="ssview-status"><h2>Previsualisations du design</h2><p>Donnees fictives. Aucun enregistrement metier.</p><nav class="ssview-links" aria-label="Previsualisations SchoolSafe">
<a href="/schoolsafe-view/account-form.html">Formulaire utilisateur</a>
<a href="/schoolsafe-view/personnel-list.html">Liste des utilisateurs et personnel</a>
<a href="/schoolsafe-view/permissions.html">Profils et droits</a>
<a href="/schoolsafe-view/school-workspace.html">Ecole : toutes les fonctions</a>
<a href="/schoolsafe-view/school-form.html">Informations de l ecole</a>
<a href="/schoolsafe-view/grade-levels.html">Niveaux scolaires</a>
<a href="/schoolsafe-view/marking-periods.html">Periodes scolaires</a>
<a href="/schoolsafe-view/student-form.html">Inscription eleve</a>
<a href="/schoolsafe-view/student-list.html">Liste des eleves</a>
<a href="/schoolsafe-view/family-association.html">Association parents et eleves</a>
</nav></section>
<?php endif; ?>
<section class="ssview-status"><h2>Fonctions Rosario disponibles</h2><div class="ssview-native-menu">
<?php foreach ($_ROSARIO['Menu'] as $module => $entries): ?>
<details><summary><?= ssviewEscape(ssviewText($entries['title'] ?? $module)) ?></summary><ul>
<?php foreach ($entries as $route => $label):
    if (is_int($route) || in_array($route, ['title', 'default'], true) || !AllowUse($route)) continue;
?>
<li><a href="<?= ssviewEscape(ssviewDestination($route)) ?>"><?= ssviewEscape(ssviewText($label)) ?></a></li>
<?php endforeach; ?></ul></details>
<?php endforeach; ?></div></section>
<section class="ssview-status"><h2>Acces depuis les destinations SchoolSafe</h2><ul>
<?php foreach ($screenBindings as $binding):
    $route = $binding['nativeRoute'];
    if (!$route) continue;
    $module = explode('/', $route)[0];
    if (empty($RosarioModules[$module]) || SchoolSafeMenuProgramTitle($route) === '' || !AllowUse($route)) continue;
?>
<li><a href="<?= ssviewEscape(ssviewDestination($route)) ?>"><?= ssviewEscape($binding['label']) ?></a></li>
<?php endforeach; ?></ul></section>
<section class="ssview-status"><h2>Informations en attente de raccordement</h2><ul><li>Detail filles et garcons par classe</li><li>Presences journalieres des enseignants</li><li>Palmares des dix meilleurs eleves</li></ul></section>
</main></body></html>
