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
if (SchoolSafeNativePermission('SchoolSafe/RemediationRead.php')) {
    $add('pedagogy', 'Accompagnement', 'Rattrapage', 'Modules.php?modname=SchoolSafe/RemediationRead.php');
}
// These are entry points, not grants. Each service independently checks its capabilities.
if (SchoolSafeNativePermission('SchoolSafe/FamilyRead.php')) {
    $add('school', 'Familles et acc\u00e8s', 'Personnes de r\u00e9cup\u00e9ration', 'Modules.php?modname=SchoolSafe/FamilyRead.php');
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
    $add('finance', 'Contr\u00f4les et bar\u00e8mes', 'Contr\u00f4le des frais', 'Modules.php?modname=SchoolSafe/FeeModelsRead.php');
}
if (SchoolSafeNativePermission('SchoolSafe/RemediationPaymentsRead.php')) {
    $add('finance', 'Paiements et encaissements', 'Paiements de rattrapage', 'Modules.php?modname=SchoolSafe/RemediationPaymentsRead.php');
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
$identity = DBGet('SELECT FIRST_NAME, LAST_NAME FROM staff WHERE STAFF_ID=' . (int) User('STAFF_ID'));
$photos = glob($UserPicturesPath . UserSyear() . '/' . (int) User('STAFF_ID') . '.*jpg') ?: [];
$photo = $photos ? end($photos) : '';
$profile = [
    'name' => trim(($identity[1]['FIRST_NAME'] ?? '') . ' ' . ($identity[1]['LAST_NAME'] ?? '')),
    'photo' => $photo,
    'url' => 'Modules.php?modname=Users/User.php&category_id=1&staff_id=' . (int) User('STAFF_ID'),
];
echo json_encode(['links' => $links, 'profile' => $profile], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
