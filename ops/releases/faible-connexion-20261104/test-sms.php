<?php
// Lot 11 (04/11) : SMS de secours, sur la base reelle dans une transaction ANNULEE (simulation SMSGate, aucun envoi).
$root = $argv[1] ?? '/var/www/html';
chdir($root);
require_once $root . '/schoolsafe-notify-cli.php';
$conn = pg_connect('host=' . (getenv('SCHOOLSAFE_DB_HOST') ?: 'db') . ' port=' . (getenv('SCHOOLSAFE_DB_PORT') ?: '5432') . ' dbname=' . getenv('POSTGRES_DB') . ' user=' . getenv('POSTGRES_USER') . ' password=' . getenv('POSTGRES_PASSWORD') . ' connect_timeout=5');
SchoolSafeNotifyCliInit($conn);
if(!function_exists('DBGetOne')){function DBGetOne($sql){$rows=DBGet($sql);if(!$rows)return false;$row=reset($rows);return reset($row);}}
if(!function_exists('DBInsert')){function DBInsert($table,$columns,$return='true'){$f=[];$v=[];foreach((array)$columns as $c=>$x){if(!is_string($c)||(string)$x==='')continue;$f[]=$c;$v[]="'".pg_escape_string($GLOBALS['schoolsafe_notify_connection'],(string)$x)."'";}
 $rows=DBGet('INSERT INTO '.$table.'('.implode(',',$f).') VALUES('.implode(',',$v).')'.($return==='id'?' RETURNING id AS ID':''));return $return==='id'?(int)($rows[1]['ID']??0):true;}}
require_once $root . '/ProgramFunctions/SchoolSafeSms.fnc.php';
require_once $root . '/ProgramFunctions/SchoolSafeNotifyDispatch.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }
foreach (['schoolsafe-notify-dispatch.php', 'ma-journee.php', 'ProgramFunctions/SchoolSafeNotifyDispatch.fnc.php'] as $f) { $out = []; exec('php -l ' . escapeshellarg($root . '/' . $f) . ' 2>&1', $out, $code); check('syntaxe ' . $f, $code, 0); }
$t = SchoolSafeNotifySmsText(['title' => 'Absence signalée', 'message' => "⚠️ Josué est absent ce matin. Voir : https://app.shoolsafe.tech/x\n— Le sage · SchoolSafe · Un enfant protégé"], 'Le sage');
check('texte SMS court, sans lien ni emoji', [strpos($t, 'http') === false, strpos($t, '⚠') === false, mb_strlen($t) <= 300, strpos($t, 'Josué est absent') !== false], [true, true, true, true]);
echo "      SMS : $t\n";
check('canal sms autorise par la base', (bool) DBGet("SELECT 1 AS X FROM pg_constraint WHERE conname='schoolsafe_notify_deliveries_channel_allowed' AND pg_get_constraintdef(oid) LIKE '%sms%'"), true);
DBQuery('BEGIN');
try {
    $parent = (int) (DBGet("SELECT su.STAFF_ID FROM students_join_users su JOIN staff s ON s.STAFF_ID=su.STAFF_ID AND s.SYEAR=2026 WHERE su.STUDENT_ID=4 LIMIT 1")[1]['STAFF_ID'] ?? 0);
    check('Josue a un parent lie', $parent > 0, true);
    $ev = (int) DBGet("INSERT INTO schoolsafe_notify_events(school_id,syear,student_id,event_type,dedup_key,payload) VALUES(3,2026,4,'absence.detected','essai-sms-lot11','{\"title\":\"Absence\",\"message\":\"Essai\"}'::jsonb) RETURNING id")[1]['ID'];
    DBQuery("INSERT INTO schoolsafe_notify_deliveries(event_id,school_id,syear,audience,recipient_staff_id,channel,status,last_error) VALUES(" . $ev . ",3,2026,'parent'," . $parent . ",'email','failed','PRE_SEND:NO_RECIPIENT_EMAIL')");
    DBQuery("INSERT INTO schoolsafe_notify_deliveries(event_id,school_id,syear,audience,recipient_staff_id,channel,status,last_error) VALUES(" . $ev . ",3,2026,'parent'," . $parent . ",'whatsapp','failed','PRE_SEND:CONSENT_REQUIRED')");
    pg_query($conn, SchoolSafeNotifySmsMirrorSql());
    check('sans ligne SMS active : aucun SMS cree', (int) DBGet("SELECT count(*) AS N FROM schoolsafe_notify_deliveries WHERE event_id=" . $ev . " AND channel='sms'")[1]['N'], 0);
    DBQuery("INSERT INTO config(school_id,title,config_value) VALUES(0,'SMS_SIMULATE','Y')");
    pg_query($conn, SchoolSafeNotifySmsMirrorSql());
    check('ligne disponible : un SMS de secours cree', (int) DBGet("SELECT count(*) AS N FROM schoolsafe_notify_deliveries WHERE event_id=" . $ev . " AND channel='sms'")[1]['N'], 1);
    pg_query($conn, SchoolSafeNotifySmsMirrorSql());
    check('pas de doublon', (int) DBGet("SELECT count(*) AS N FROM schoolsafe_notify_deliveries WHERE event_id=" . $ev . " AND channel='sms'")[1]['N'], 1);
    $ev2 = (int) DBGet("INSERT INTO schoolsafe_notify_events(school_id,syear,student_id,event_type,dedup_key,payload) VALUES(3,2026,4,'homework.published','essai-sms-lot11-b','{}'::jsonb) RETURNING id")[1]['ID'];
    DBQuery("INSERT INTO schoolsafe_notify_deliveries(event_id,school_id,syear,audience,recipient_staff_id,channel,status,last_error) VALUES(" . $ev2 . ",3,2026,'parent'," . $parent . ",'email','failed','PRE_SEND:NO_RECIPIENT_EMAIL')");
    pg_query($conn, SchoolSafeNotifySmsMirrorSql());
    check('un devoir ne part jamais par SMS', (int) DBGet("SELECT count(*) AS N FROM schoolsafe_notify_deliveries WHERE event_id=" . $ev2 . " AND channel='sms'")[1]['N'], 0);
    $out = SchoolSafeSmsSend('+243810000000', $t . " (l'ecole)", ['school_id' => 3, 'actor' => 'test', 'dedup_key' => 'notify:delivery:essai', 'purpose' => 'alert']);
    check('envoi simule accepte (aucun reseau)', [$out['status'] ?? '', $out['via'] ?? ''], ['accepted', 'simulated']);
} catch (Throwable $x) { $ko++; echo 'KO   exception : ' . $x->getMessage() . "\n"; }
finally { DBQuery('ROLLBACK'); }
check('rien n est reste en base', (int) DBGet("SELECT count(*) AS N FROM schoolsafe_notify_events WHERE dedup_key LIKE 'essai-sms-lot11%'")[1]['N'], 0);
echo "\n$ok OK, $ko KO\n";
exit($ko ? 1 : 0);
