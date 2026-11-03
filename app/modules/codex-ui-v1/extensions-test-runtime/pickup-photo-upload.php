<?php
// Photo d'une personne de récupération, prise au guichet famille.
// Même session, même jeton CSRF et même autorisation que l'action « photo-set »
// de scanner-relay.php ; stockage persistant (FileUploads, volume) puis
// SchoolSafePickupPhotoSet : la photo repart « à valider » (spec v1.1, R2/R3).
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function SchoolSafePhotoReply($status, $body) { http_response_code($status); echo json_encode($body, JSON_THROW_ON_ERROR); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $_GET) SchoolSafePhotoReply(400, ['error' => 'INVALID_REQUEST']);
$ss_photo_post = $_POST;
chdir(__DIR__);
$_REQUEST = ['modname' => 'misc/Portal.php', 'modfunc' => ''];
$level = ob_get_level();
ob_start();
try {
    require_once __DIR__ . '/Warehouse.php';
    require_once __DIR__ . '/modules/SchoolSafe/Gateway.php';
    require_once __DIR__ . '/ProgramFunctions/FileUpload.fnc.php';
    require_once __DIR__ . '/ProgramFunctions/SchoolSafePickupManage.fnc.php';
    if (!User('STAFF_ID')) { while (ob_get_level() > $level) ob_end_clean(); SchoolSafePhotoReply(401, ['error' => 'NATIVE_SESSION_REQUIRED']); }
    $user = (int) User('STAFF_ID'); $school = (int) UserSchool(); $year = (int) UserSyear();
    if ($user < 1 || $school < 1 || $year < 1) throw new RuntimeException('INVALID_CONTEXT');
    $csrf = $_SERVER['HTTP_X_SCHOOLSAFE_CSRF'] ?? '';
    if (empty($_SESSION['SchoolSafeScannerCsrf']) || !is_string($csrf) || !hash_equals($_SESSION['SchoolSafeScannerCsrf'], $csrf)) {
        throw new RuntimeException('CSRF_REQUIRED');
    }
    if (SchoolSafeEndpointGateway('scanner-relay.php', 'POST', 'management', ['action' => 'photo-set']) === 'deny') {
        throw new RuntimeException('FORBIDDEN');
    }
    $student = filter_var($ss_photo_post['nativeStudentId'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $guardian = filter_var($ss_photo_post['guardianId'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($student === false || $guardian === false) throw new RuntimeException('INVALID_INPUT');
    if (!DBGet("SELECT 1 FROM student_enrollment WHERE STUDENT_ID=" . (int) $student . " AND SCHOOL_ID=" . $school . " AND SYEAR=" . $year)) {
        throw new RuntimeException('NOT_FOUND');
    }
    if (!DBGet("SELECT 1 FROM schoolsafe_pickup_guardians WHERE ID=" . (int) $guardian . " AND STUDENT_ID=" . (int) $student
        . " AND SCHOOL_ID=" . $school . " AND IS_ACTIVE")) {
        throw new RuntimeException('NOT_FOUND');
    }
    if (empty($_FILES['photo']['tmp_name']) || ($_FILES['photo']['size'] ?? 0) > 8 * 1024 * 1024) throw new RuntimeException('INVALID_PHOTO');
    $relative = 'FileUploads/PickupPhotos/' . $school . '/';
    $stored = ImageUpload('photo', ['width' => 600, 'height' => 800], __DIR__ . '/' . $relative, ['.jpg', '.jpeg', '.png', '.webp'], '.jpg',
        'g' . (int) $guardian . '-' . bin2hex(random_bytes(8)));
    if (!$stored || !is_file($stored)) throw new RuntimeException('INVALID_PHOTO');
    $path = '/' . $relative . basename($stored);
    if (!is_file(__DIR__ . $path)) throw new RuntimeException('INVALID_PHOTO');
    $db = ['get' => function ($sql) { return DBGet(DBQuery($sql)); }, 'exec' => function ($sql) { DBQuery($sql); },
        'quote' => function ($value) { return "'" . DBEscapeString($value) . "'"; }];
    $ctx = ['user' => $user, 'school' => $school, 'year' => $year, 'profile' => User('PROFILE'), 'parent' => User('PROFILE') === 'parent', 'root' => '.'];
    $data = SchoolSafePickupPhotoSet((int) $student, (int) $guardian, $path, $ctx, $db);
} catch (Throwable $error) {
    while (ob_get_level() > $level) ob_end_clean();
    $code = ['CSRF_REQUIRED' => 403, 'FORBIDDEN' => 403, 'NOT_FOUND' => 404, 'INVALID_INPUT' => 400, 'INVALID_PHOTO' => 400,
        'INVALID_PERSON' => 400][$error->getMessage()] ?? 503;
    SchoolSafePhotoReply($code, ['error' => $code === 404 ? 'NOT_FOUND' : (string) $code]);
}
while (ob_get_level() > $level) ob_end_clean();
SchoolSafePhotoReply(200, ['data' => $data]);
