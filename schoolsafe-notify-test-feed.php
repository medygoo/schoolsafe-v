<?php
// SchoolSafe — Lot U0 : fil de TEST des notifications (sans modifier le fil parent occupe).
// Endpoint JSON reserve aux parents : ?limit=50. Isolation ecole imposee.
// N'inclut JAMAIS SchoolSafeNotificationFeed.fnc.php (fichier occupe).
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function SchoolSafeNotifyTestReply($status, $data)
{
    http_response_code($status);
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}
try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || !empty($_GET['modname'] ?? null)) {
        // Endpoint direct, pas via Modules.php.
    }
    chdir(__DIR__);
    $_REQUEST = ['modname' => 'misc/Portal.php', 'modfunc' => ''];
    require_once __DIR__ . '/Warehouse.php';
    require_once __DIR__ . '/modules/SchoolSafe/Gateway.php';
    require_once __DIR__ . '/ProgramFunctions/SchoolSafeNotify.fnc.php';
    if (!User('STAFF_ID')) throw new RuntimeException('SESSION', 401);
    if (User('PROFILE') !== 'parent') throw new RuntimeException('DENIED', 403);
    if (SchoolSafeEndpointGateway('parent-notifications.php', 'GET', '', []) === 'deny') {
        throw new RuntimeException('DENIED', 403);
    }
    $user = (int) User('STAFF_ID');
    $school = (int) UserSchool();
    $limit = max(1, min(200, (int) ($_GET['limit'] ?? 50)));
    if ($user < 1 || $school < 1 || $DatabaseType !== 'postgresql') throw new RuntimeException('DENIED', 403);
    global $db_connection;
    $conn = $db_connection;
    $db = [
        'get' => function ($sql) use ($conn) {
            $res = @pg_query($conn, $sql);
            if ($res === false) throw new RuntimeException('DB');
            $out = [];
            $i = 1;
            while ($r = pg_fetch_assoc($res)) { $out[$i++] = array_change_key_case($r, CASE_UPPER); }
            return $out;
        },
        'exec' => function ($sql) use ($conn) {
            if (@pg_query($conn, $sql) === false) throw new RuntimeException('DB');
        },
    ];
    $items = SchoolSafeNotifyTestFeed($user, $school, $db, $limit);
    // Ordre stable : plus recent d'abord (meme regle que le Studio).
    SchoolSafeNotifyTestReply(200, ['items' => $items, 'count' => count($items)]);
} catch (Throwable $error) {
    $code = (int) $error->getCode();
    if ($code !== 401 && $code !== 403) $code = 503;
    SchoolSafeNotifyTestReply($code, ['error' => 'NOTIFY_TEST_UNAVAILABLE']);
}
