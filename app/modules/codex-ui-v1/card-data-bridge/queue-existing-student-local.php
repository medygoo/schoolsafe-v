<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require '/var/www/html/config.inc.php';
if ($DatabaseName !== 'rosario_addons_test') throw new RuntimeException('Wrong local instance');
require '/var/www/html/plugins/SchoolSafe_Matricule/includes/bridge.php';
$native = new PDO('pgsql:host=' . $DatabaseServer . ';port=' . ($DatabasePort ?: '5432') . ';dbname=' . $DatabaseName, $DatabaseUsername, $DatabasePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$exists = $native->query('SELECT 1 FROM students s JOIN student_enrollment e ON e.student_id=s.student_id WHERE s.student_id=1 AND e.school_id=1 AND e.syear=2026')->fetchColumn();
if (!$exists) throw new RuntimeException('Known local student enrollment missing');
$queue = SchoolSafeMatriculeQueue();
$statement = $queue->prepare('INSERT OR IGNORE INTO pending (student_id, school_id, year, actor_id) VALUES (?, ?, ?, ?)');
$statement->execute(['1', '1', '2026', '1']);
echo "PASS: existing local student queued; native permission will be rechecked on retry\n";
