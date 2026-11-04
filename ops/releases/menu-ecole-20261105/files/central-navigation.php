<?php
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || $_GET || $_POST) {
    http_response_code(400); exit('{}');
}
chdir(__DIR__);
$_REQUEST = ['modname' => 'misc/Portal.php', 'modfunc' => ''];
$level = ob_get_level(); ob_start(); require_once __DIR__ . '/Warehouse.php';
while (ob_get_level() > $level) ob_end_clean();
if (!User('STAFF_ID')) { http_response_code(401); exit('{}'); }
require_once __DIR__ . '/modules/SchoolSafe/Permissions.php';
$links = [];
$add = static function ($branch, $group, $title, $url) use (&$links) {
    $links[] = compact('branch', 'group', 'title', 'url');
};
if (SchoolSafeNativePermission('SchoolSafe/Studio.php')) {
    $add('school', 'Cartes SchoolSafe', 'Studio de cartes', 'Modules.php?modname=SchoolSafe/Studio.php');
}
// Pedagogie (04/11, harmonisation demandee par le proprietaire) : un parcours par profil, dans l'ordre d'usage.
// Ce sont des entrees, pas des droits : chaque page verifie son propre acces.
require_once __DIR__ . '/ProgramFunctions/SchoolSafePedagogyNav.fnc.php';
if (User('PROFILE') === 'admin') {
    $ssConfig = AllowEdit('School_Setup/Configuration.php');
    $ssReports = $ssConfig || AllowUse('Grades/ReportCards.php');
    if ($ssConfig) {
        $add('pedagogy', '1 · Organiser l’année', 'Préparer l’année', 'preparer-annee.php');
        $add('pedagogy', '1 · Organiser l’année', 'Branches et maxima', 'branches-classe.php');
        $add('pedagogy', '1 · Organiser l’année', 'Papier de l’école', 'papier-ecole.php');
    }
    if ($ssReports) {
        $add('pedagogy', '2 · Suivre et évaluer', 'Suivi pédagogique', 'suivi-pedagogique.php');
        $add('pedagogy', '2 · Suivre et évaluer', 'Synthèse du bulletin', 'synthese-bulletin.php');
    }
    if (AllowUse('Students/Student.php')) $add('pedagogy', '3 · Communiquer et archiver', 'Convocations et courriers', 'courriers.php');
    if ($ssConfig) $add('pedagogy', '3 · Communiquer et archiver', 'Archives de l’école', 'archives.php');
    if ($ssConfig || $ssReports) $add('pedagogy', 'Aide', 'Mode d’emploi Pédagogie', 'guide-pedagogie.php#direction');
}
if (User('PROFILE') === 'teacher') {
    $add('pedagogy', 'Ma classe', 'Ma journée de classe', 'ma-journee.php');
    $add('pedagogy', 'Ma classe', 'Devoirs et travaux', 'devoirs-travaux.php');
    $add('pedagogy', 'Ma classe', 'Notes du bulletin', 'notes-bulletin.php');
    if (SchoolSafePedagogyIsTitulaire()) $add('pedagogy', 'Ma classe', 'Synthèse du bulletin (titulaire)', 'synthese-bulletin.php');
    $add('pedagogy', 'Aide', 'Mode d’emploi Pédagogie', 'guide-pedagogie.php#enseignant');
}
if (SchoolSafeNativePermission('SchoolSafe/RemediationRead.php')) {
    $add('pedagogy', 'Accompagnement', 'Rattrapage', 'Modules.php?modname=SchoolSafe/RemediationRead.php');
}
// These are entry points, not grants. Each service independently checks its capabilities.
if (SchoolSafeNativePermission('SchoolSafe/FamilyRead.php')) {
    // Onglet « Famille et récupération » de la fiche élève (catégorie native) ;
    // repli sur l'ancien formulaire si la catégorie n'existe pas.
    $familyCategory = (int) DBGetOne("SELECT ID FROM student_field_categories WHERE INCLUDE='SchoolSafe/FamilyTab'");
    $add('school', 'Familles et acc\u00e8s', 'Personnes de r\u00e9cup\u00e9ration', $familyCategory > 0
        ? 'Modules.php?modname=Students/Student.php&category_id=' . $familyCategory
        : 'Modules.php?modname=SchoolSafe/FamilyRead.php');
}
foreach (['SchoolSafe/ScanVerify.php', 'SchoolSafe/ScanArrival.php',
    'SchoolSafe/ScanPickup.php', 'SchoolSafe/ScanFees.php'] as $scanProgram) {
    if (!SchoolSafeNativeCommandPermission($scanProgram)) continue;
    $add('security', 'Contr\u00f4les SchoolSafe', 'Scanner les cartes', 'Modules.php?modname=' . $scanProgram);
    break;
}
require_once __DIR__ . '/schoolsafe-attendance/ArrivalRead.php';
if (SchoolSafeArrivalViewPermission()) {
    $add('pedagogy', 'Pr\u00e9sences', 'Arriv\u00e9es \u00e0 l\u2019\u00e9cole', 'Modules.php?modname=SchoolSafe/ArrivalAttendance.php');
}
if (SchoolSafeNativePermission('SchoolSafe/FeeModelsRead.php')) {
    $add('finance', 'Frais et mod\u00e8les', 'Contr\u00f4le des frais', 'Modules.php?modname=SchoolSafe/FeeModelsRead.php');
}
if (SchoolSafeNativePermission('SchoolSafe/RemediationPaymentsRead.php')) {
    $add('finance', 'Rattrapage', 'Paiements de rattrapage', 'Modules.php?modname=SchoolSafe/RemediationPaymentsRead.php');
}
// Travail de mon enfant (04/11) : devoirs, notes, presences, cahier de textes sur une seule page.
// Harmonisation (04/11) : le parcours du parent et de l'eleve est range dans Pedagogie, bloc « Mon enfant ».
if (in_array(User('PROFILE'), ['parent', 'student'], true)) {
    $ssChild = User('PROFILE') === 'student' ? 'Mon travail' : 'Travail de mon enfant';
    $add('pedagogy', 'Mon enfant', $ssChild, 'travail-enfant.php');
    // Dossier de l'annee (04/11) : tous les travaux de l'annee, bilan et PDF.
    $add('pedagogy', 'Mon enfant', 'Dossier de l’année', 'dossier-annee.php');
    $add('pedagogy', 'Mon enfant', 'Bulletin', 'bulletin.php');
    $add('pedagogy', 'Mon enfant', 'Mode d’emploi', 'guide-pedagogie.php#' . (User('PROFILE') === 'student' ? 'eleve' : 'parent'));
}
if (User('PROFILE') === 'parent') {
    $add('communication', 'Suivi de mon enfant', 'Signaler une absence ou un retard', 'signaler-absence.php');
}
if (SchoolSafeNativePermission('SchoolSafe/Notifications.php')) {
    $add('communication', 'Suivi de mon enfant', 'Notifications', 'Modules.php?modname=SchoolSafe/Notifications.php');
}
// Decode the ASCII source labels before JSON serialization.
foreach ($links as &$link) {
    foreach (['group', 'title'] as $key) {
        $link[$key] = json_decode('"' . $link[$key] . '"');
    }
}
unset($link);
$identity = DBGet('SELECT FIRST_NAME, LAST_NAME, ROLLOVER_ID FROM staff WHERE STAFF_ID=' . (int) User('STAFF_ID'));
require_once __DIR__ . '/ProgramFunctions/SchoolSafeStaffPhoto.fnc.php';
$photo = SchoolSafeStaffPhotoUrl($UserPicturesPath, (int) UserSyear(), (int) User('STAFF_ID'),
    (int) ($identity[1]['ROLLOVER_ID'] ?? 0));
$profile = [
    'name' => trim(($identity[1]['FIRST_NAME'] ?? '') . ' ' . ($identity[1]['LAST_NAME'] ?? '')),
    'photo' => $photo,
    'url' => 'Modules.php?modname=Users/User.php&category_id=1&staff_id=' . (int) User('STAFF_ID'),
];
// Visible school navigation for every authenticated profile, NOT a capability.
// ProDéli keeps its separate workspace; execution still uses the native gateway.
$scanAction = function_exists('SchoolSafeGlobalAdmin') && !SchoolSafeGlobalAdmin()
    && (int) UserSchool() > 0 ? 'scanner-native.php' : null;
// Profil connecte (04/11) : sert seulement a ranger le menu par profil, jamais a donner un droit.
$role = in_array(User('PROFILE'), ['admin', 'teacher', 'parent', 'student'], true) ? User('PROFILE') : '';
echo json_encode(['links' => $links, 'profile' => $profile, 'role' => $role, 'scanAction' => $scanAction], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
