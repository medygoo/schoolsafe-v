<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/ArrivalTransferWorker.php';

function arrivalPdo(array $config, string $database, bool $readOnly): PDO
{
    if (($config['database'] ?? null) !== $database || !in_array($config['host'] ?? null,
        ['codex-card-target-db-v17', 'codex-addons-db', 'localhost', '127.0.0.1'], true)
        || !is_string($config['user'] ?? null) || !is_string($config['password'] ?? null)
        || !is_int($config['port'] ?? 5432) || ($config['port'] ?? 5432) < 1 || ($config['port'] ?? 5432) > 65535) {
        throw new RuntimeException('ISOLATED_DATABASE_CONFIG_REQUIRED');
    }
    $pdo = new PDO('pgsql:host=' . $config['host'] . ';port=' . ($config['port'] ?? 5432) . ';dbname=' . $database . ';connect_timeout=5',
        $config['user'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    if ($pdo->query('SELECT current_database()')->fetchColumn() !== $database) throw new RuntimeException('DATABASE_MISMATCH');
    $pdo->exec("SET statement_timeout='5s'; SET lock_timeout='3s'; SET TIME ZONE 'UTC'; SET search_path=pg_catalog,public");
    if ($readOnly) $pdo->exec('SET default_transaction_read_only=on');
    return $pdo;
}
function arrivalQuery(PDO $pdo): callable
{
    return static function (string $sql, array $params) use ($pdo): array {
        $statement = $pdo->prepare($sql); $statement->execute($params);
        return $statement->columnCount() ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    };
}
$lock = null;
try {
    if ($argc !== 1) throw new RuntimeException('NO_CLI_OVERRIDES');
    $settings = json_decode(file_get_contents('/var/lib/schoolsafe-attendance/worker.json'), true, 32, JSON_THROW_ON_ERROR);
    $bridge = json_decode(file_get_contents('/var/lib/schoolsafe-attendance/http-config.json'), true, 32, JSON_THROW_ON_ERROR);
    if (($settings['environment'] ?? null) !== 'local-isolated-test-only' || ($bridge['environment'] ?? null) !== 'local-isolated-test-only'
        || ($settings['sourceInstance'] ?? null) !== 'codex-addons-local') throw new RuntimeException('ISOLATED_ONLY');
    $activation = $settings['activatedAt'] ?? null;
    if (!is_string($activation) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $activation)) throw new RuntimeException('ACTIVATION_REQUIRED');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $activation, new DateTimeZone('UTC'));
    if (!$date || $date->format('Y-m-d\TH:i:s\Z') !== $activation) throw new RuntimeException('INVALID_ACTIVATION');
    $limit = $settings['batchSize'] ?? 100; $ticks = $settings['ticks'] ?? 1; $poll = $settings['pollSeconds'] ?? 15;
    if (!is_int($limit) || $limit < 1 || $limit > 500 || !is_int($ticks) || $ticks < 1 || $ticks > 100
        || !is_int($poll) || $poll < 1 || $poll > 60 || !is_array($settings['schoolTimezones'] ?? null)) throw new RuntimeException('INVALID_POLL_CONFIG');
    $lock = fopen('/var/lib/schoolsafe-attendance/worker.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('WORKER_ALREADY_RUNNING');
    $statePath = '/var/lib/schoolsafe-attendance/worker-state.json';
    $state = is_file($statePath) ? json_decode(file_get_contents($statePath), true, 64, JSON_THROW_ON_ERROR) : [];
    if (!is_array($state)) throw new RuntimeException('INVALID_WORKER_STATE');
    $target = arrivalQuery(arrivalPdo($bridge['target'], 'schoolsafe_cards_isolated', true));
    $mapping = arrivalQuery(arrivalPdo($bridge['mapping'], 'schoolsafe_card_bridge_test', true));
    $native = arrivalQuery(arrivalPdo($settings['native'], 'rosario_addons_test', false));
    $fetch = static function ($activation, $cursor, $limit) use ($target): array {
        $args = ['activation' => $activation];
        $where = '';
        if ($cursor) {
            $where = ' AND (occurred_at,id)>(CAST(:cursor_at AS timestamptz),CAST(:cursor_id AS uuid))';
            $args += ['cursor_at' => $cursor['occurredAt'], 'cursor_id' => $cursor['eventId']];
        }
        return $target('SELECT \'codex-addons-local\' AS "sourceInstance",id::text AS "eventId",school_id::text AS "schoolId",
            student_id::text AS "studentId",to_char(occurred_at AT TIME ZONE \'UTC\',\'YYYY-MM-DD"T"HH24:MI:SS.US\') || \'+00:00\' AS "occurredAt",
            event_type AS "eventType",decision,source FROM schoolsafe_arrival_source.arrivals
            WHERE occurred_at>=CAST(:activation AS timestamptz) AND event_type=\'entry\' AND decision=\'allowed\'
            AND source=\'scanner_entry\'' . $where . ' ORDER BY occurred_at,id LIMIT ' . $limit, $args);
    };
    $project = static function ($event) use ($mapping, $native, $settings) {
        $resolved = SchoolSafeArrivalTransferMapping($event, $mapping, $native, $settings['schoolTimezones']);
        return SchoolSafeArrivalProject($event, $resolved, $native);
    };
    for ($tick = 0; $tick < $ticks; ++$tick) {
        $result = SchoolSafeArrivalTransferTick($state, $activation, $limit, $fetch, $project);
        $temporary = $statePath . '.next';
        $encoded = json_encode($result['state'], JSON_THROW_ON_ERROR);
        $previousMask = umask(0077);
        try {
            if (file_put_contents($temporary, $encoded, LOCK_EX) !== strlen($encoded) || !rename($temporary, $statePath)) throw new RuntimeException('CHECKPOINT_FAILED');
        } finally { umask($previousMask); }
        $state = $result['state'];
        echo json_encode($result['counts'] + ['pending' => count($state['pending'])], JSON_THROW_ON_ERROR) . "\n";
        if ($tick + 1 < $ticks) sleep($poll);
    }
} catch (Throwable $error) {
    // PDO errors and configuration can contain credentials: never print exception details.
    fwrite(STDERR, "ARRIVAL_TRANSFER_UNAVAILABLE: retry required; checkpoint retained\n"); exit(1);
} finally {
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
}
