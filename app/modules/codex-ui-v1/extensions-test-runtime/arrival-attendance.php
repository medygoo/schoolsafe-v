<?php
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || $_POST
    || array_diff(array_keys($_GET), ['date', 'course_period_id', 'page'])) {
    http_response_code(400); exit('Requete invalide.');
}
chdir(__DIR__);
$_REQUEST = ['modname' => 'misc/Portal.php', 'modfunc' => ''];
$level = ob_get_level(); ob_start();
require_once __DIR__ . '/Warehouse.php';
while (ob_get_level() > $level) ob_end_clean();
require_once __DIR__ . '/schoolsafe-attendance/ArrivalRead.php';
require_once __DIR__ . '/schoolsafe-attendance/ArrivalView.php';
if (!User('STAFF_ID') || !SchoolSafeArrivalViewPermission()) {
    http_response_code(403); exit('Permission de consultation des arrivees requise.');
}
try {
    if ($DatabaseType === 'mysql') throw new RuntimeException('ARRIVAL_READ_UNAVAILABLE');
    $context = ['profile' => User('PROFILE'), 'staff' => User('STAFF_ID'), 'school' => UserSchool(), 'year' => UserSyear()];
    $query = static function (string $sql): array {
        ob_start();
        try { $rows = DBGet($sql); }
        finally { ob_end_clean(); }
        if (!is_array($rows)) throw new RuntimeException('ARRIVAL_READ_UNAVAILABLE');
        return $rows;
    };
    $classes = SchoolSafeArrivalAssignedClasses($context, $query);
    $filters = $_GET;
    $filters['date'] = $filters['date'] ?? DBDate();
    $period = null;
    if ($context['profile'] === 'teacher') {
        if (!$classes) { http_response_code(403); exit('Aucune classe attribuee dans cette ecole et cette annee.'); }
        $filters['course_period_id'] = $filters['course_period_id'] ?? (string) $classes[0]['course_period_id'];
        $period = SchoolSafeArrivalReadId($filters['course_period_id']);
    }
    $data = SchoolSafeArrivalRead($context, $filters, $query);
    $content = SchoolSafeArrivalRender($data, $classes, $context['profile'], $period);
} catch (InvalidArgumentException $error) {
    http_response_code(400); exit('Date ou filtre invalide.');
} catch (Throwable $error) {
    http_response_code($error->getMessage() === 'ARRIVAL_ACCESS_DENIED' ? 403 : 503);
    exit('Consultation des arrivees indisponible.');
}
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Arrivees | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-attendance/arrival-view.css"></head>
<body><header><strong>SchoolSafe</strong><a href="Modules.php?modname=misc/Portal.php">Tableau de bord</a></header><main><h1>Arrivees enregistrees</h1><?= $content ?></main></body></html>
