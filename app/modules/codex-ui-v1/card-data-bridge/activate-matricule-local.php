<?php
// Explicit operator command for the isolated local instance, never an HTTP endpoint.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require '/var/www/html/config.inc.php';
if ($DatabaseName !== 'rosario_addons_test') throw new RuntimeException('Wrong local instance');
$pdo = new PDO('pgsql:host=' . $DatabaseServer . ';port=' . ($DatabasePort ?: '5432') . ';dbname=' . $DatabaseName, $DatabaseUsername, $DatabasePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->beginTransaction();
$row = $pdo->query("SELECT school_id, config_value FROM config WHERE title='PLUGINS' AND school_id=0 FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
if (!$row) throw new RuntimeException('Native plugin configuration missing');
$plugins = unserialize($row['config_value'], ['allowed_classes' => false]);
if (!is_array($plugins)) throw new RuntimeException('Invalid plugin configuration');
$plugins['SchoolSafe_Matricule'] = true;
$statement = $pdo->prepare("UPDATE config SET config_value=:value WHERE title='PLUGINS' AND school_id=0");
$statement->execute(['value' => serialize($plugins)]);
$pdo->commit();
echo "PASS: native plugin activation; existing plugin entries preserved\n";
