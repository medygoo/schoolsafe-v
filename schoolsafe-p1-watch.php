<?php
// SchoolSafe — Lot U4 : chien de garde des alertes P1-P4 (CLI uniquement).
//
// Usage (conteneur L1) : php schoolsafe-p1-watch.php --once [--limit=50]
// - Relance les alertes ouvertes sans prise en charge dont le delai par
//   severite est depasse (P1 5min, P2 15min, P3 60min, P4 240min).
// - Escalade = renotifie la direction + marque escalated, JAMAIS de cloture :
//   une alerte ouverte le reste jusqu'a cloture humaine autorisee.
// - Idempotent : delai non atteint, deja prise en charge ou cloturee => rien.
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(2); }

$once = in_array('--once', $argv, true);
$limit = 50;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) $limit = max(1, min(500, (int) substr($arg, 8)));
}
if (!$once) {
    fwrite(STDERR, "Usage: php schoolsafe-p1-watch.php --once [--limit=50]\n");
    exit(2);
}
chdir(__DIR__);
require_once __DIR__ . '/ProgramFunctions/SchoolSafeP1.fnc.php';

$host = getenv('SCHOOLSAFE_DB_HOST') ?: 'db';
$port = getenv('SCHOOLSAFE_DB_PORT') ?: '5432';
$user = getenv('POSTGRES_USER') ?: 'schoolsafe';
$pass = getenv('POSTGRES_PASSWORD') ?: '';
$dbname = getenv('POSTGRES_DB') ?: 'schoolsafe';
$conn = @pg_connect("host=$host port=$port dbname=$dbname user=$user password=$pass connect_timeout=5");
if ($conn === false) { fwrite(STDERR, "DB_UNAVAILABLE\n"); exit(1); }
$db = [
    'get' => function ($sql) use ($conn) {
        $res = @pg_query($conn, $sql);
        if ($res === false) throw new RuntimeException('DB_QUERY_FAILED');
        $out = [];
        $i = 1;
        while ($r = pg_fetch_assoc($res)) { $out[$i++] = array_change_key_case($r, CASE_UPPER); }
        return $out;
    },
    'exec' => function ($sql) use ($conn) {
        $res = @pg_query($conn, $sql);
        if ($res === false) throw new RuntimeException('DB_EXEC_FAILED');
    },
];

$open = $db['get']("SELECT ID FROM schoolsafe_p1_alerts WHERE STATUS='open' AND ACK_AT IS NULL"
    . ' ORDER BY CREATED_AT, ID LIMIT ' . $limit);
$escalated = 0; $left = 0;
if (is_array($open)) foreach ($open as $row) {
    if (!isset($row['ID'])) continue;
    try {
        $out = SchoolSafeP1Escalate((int) $row['ID'], $db);
        if (!empty($out['escalated'])) $escalated++;
        else $left++;
    } catch (Throwable $e) { $left++; }
}
echo "P1WATCH escalated=$escalated open_left=$left\n";
exit(0);
