<?php
function SchoolSafeMatriculeRead($student_id)
{
    require_once dirname(__DIR__, 3) . '/schoolsafe-card-bridge/RosarioCardRead.fnc.php';
    return SchoolSafeReadNativeCardInput($student_id);
}

function SchoolSafeMatriculeQueue()
{
    $directory = '/var/lib/schoolsafe-card-bridge';
    if (!is_dir($directory) || !is_writable($directory)) throw new RuntimeException('PRIVATE_QUEUE_REQUIRED');
    $queue = new PDO('sqlite:' . $directory . '/matricule-queue.sqlite');
    $queue->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $queue->exec('PRAGMA busy_timeout=5000');
    $queue->exec("CREATE TABLE IF NOT EXISTS pending (student_id TEXT NOT NULL, school_id TEXT NOT NULL, year TEXT NOT NULL, actor_id TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'pending', matricule TEXT, attempts INTEGER NOT NULL DEFAULT 0, last_error TEXT, PRIMARY KEY(student_id, school_id, year))");
    chmod($directory . '/matricule-queue.sqlite', 0600);
    return $queue;
}

function SchoolSafeMatriculeAllocate(array $input)
{
    global $DatabaseServer, $DatabasePort, $DatabaseUsername, $DatabasePassword;
    if (($input['authorized'] ?? false) !== true) throw new RuntimeException('NATIVE_ACCESS_REQUIRED');
    $student = (string) ($input['student']['STUDENT_ID'] ?? '');
    $school = (string) ($input['enrollment']['SCHOOL_ID'] ?? '');
    if (!preg_match('/^[1-9][0-9]*$/D', $student) || !preg_match('/^[1-9][0-9]*$/D', $school)) throw new RuntimeException('NATIVE_IDENTITY_REQUIRED');
    // This plugin is restricted to the approved isolated local runtime.
    $config = require '/var/lib/schoolsafe-card-bridge/config.php';
    if (($config['source'] ?? '') !== 'codex-addons-local' || ($config['database'] ?? '') !== 'schoolsafe_card_bridge_test') throw new RuntimeException('ISOLATED_CONFIG_REQUIRED');
    $pdo = new PDO('pgsql:host=' . $DatabaseServer . ';port=' . ($DatabasePort ?: '5432') . ';dbname=' . $config['database'], $DatabaseUsername, $DatabasePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $statement = $pdo->prepare('SELECT card_bridge.ensure_student_matricule(:source, :school, :student, :existing)');
    $statement->execute(['source' => $config['source'], 'school' => $school, 'student' => $student, 'existing' => $input['verified']['matricule'] ?? null]);
    return $statement->fetchColumn();
}

// Called only after the native provider has authorized this child.
function SchoolSafeMatriculeLookup($student_id)
{
    global $DatabaseServer, $DatabasePort, $DatabaseUsername, $DatabasePassword;
    $config = require '/var/lib/schoolsafe-card-bridge/config.php';
    if (($config['source'] ?? '') !== 'codex-addons-local' || ($config['database'] ?? '') !== 'schoolsafe_card_bridge_test') throw new RuntimeException('ISOLATED_CONFIG_REQUIRED');
    $pdo = new PDO('pgsql:host=' . $DatabaseServer . ';port=' . ($DatabasePort ?: '5432') . ';dbname=' . $config['database'], $DatabaseUsername, $DatabasePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $statement = $pdo->prepare('SELECT matricule FROM card_bridge.student_matricule WHERE source_instance=:source AND native_student_id=:student');
    $statement->execute(['source' => $config['source'], 'student' => (string) $student_id]);
    $result = $statement->fetchColumn();
    return $result === false ? null : $result;
}
