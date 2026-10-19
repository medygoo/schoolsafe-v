<?php
// SchoolSafe — Lot U0 : dispatcher independant des requetes de scan (CLI uniquement).
//
// Usage (conteneur L1) : php schoolsafe-notify-dispatch.php --once [--limit=50] [--channel=all|feed|email|push]
// - Ne bloque jamais le scanner : l'emetteur ecrit l'outbox en transaction,
//   ce script traite les 'pending' en arriere-plan.
// - Panne conservee : un echec passe en 'failed' avec attempts+1 et last_error,
//   la ligne est conservee (reprise possible) ; --retry-failed les repasse en
//   'pending' pour rejouer apres reparation.
// - Redemarrage sur : les 'pending' restent en base, rien n'est perdu.
// - Zero reseau scanner : le scan n'appelle jamais ce script ; ici, feed = local
//   immediat, email = Brevo API (cle serveur BREVO_API_KEY) avec gabarits
//   SchoolSafe FR/EN, push = signal VAPID (cles serveur) vers l'abonnement.
// - Honnetete des etats : feed local => 'sent' (relu par TestFeed) ; email/push
//   acceptes par le fournisseur => 'accepted' (+ provider_msg_id) ; sans
//   fournisseur ou echec => 'failed' conserve (reprise --retry-failed).
//   Jamais de 'sent'/'accepted' sans preuve d'envoi.
require_once __DIR__ . '/ProgramFunctions/SchoolSafeMailTemplate.fnc.php';
require_once __DIR__ . '/ProgramFunctions/SchoolSafeBrevo.fnc.php';
require_once __DIR__ . '/ProgramFunctions/SchoolSafeWebPush.fnc.php';
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(2); }

$once = in_array('--once', $argv, true);
$limit = 50;
$channel = 'all';
$retryFailed = in_array('--retry-failed', $argv, true);
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) $limit = max(1, min(500, (int) substr($arg, 8)));
    if (str_starts_with($arg, '--channel=')) $channel = substr($arg, 10);
}
if (!$once || !in_array($channel, ['all', 'feed', 'email', 'push'], true)) {
    fwrite(STDERR, "Usage: php schoolsafe-notify-dispatch.php --once [--limit=50] [--channel=all|feed|email|push] [--retry-failed]\n");
    exit(2);
}

$host = getenv('SCHOOLSAFE_DB_HOST') ?: 'db';
$port = getenv('SCHOOLSAFE_DB_PORT') ?: '5432';
$user = getenv('POSTGRES_USER') ?: 'schoolsafe';
$pass = getenv('POSTGRES_PASSWORD') ?: '';
$dbname = getenv('POSTGRES_DB') ?: 'schoolsafe';
$conn = @pg_connect("host=$host port=$port dbname=$dbname user=$user password=$pass connect_timeout=5");
if ($conn === false) { fwrite(STDERR, "DB_UNAVAILABLE\n"); exit(1); }

if ($retryFailed) {
    pg_query_params($conn, "UPDATE schoolsafe_notify_deliveries SET status='pending', updated_at=now()"
        . " WHERE status='failed' AND ($1='all' OR channel=$1)", [$channel]);
}

$forceFail = getenv('SCHOOLSAFE_NOTIFY_FORCE_FAIL') === '1';
$res = pg_query_params($conn, "SELECT d.id, d.channel, d.recipient_staff_id, d.event_id,"
    . " s.email, e.event_type, e.payload, e.school_id, sch.title AS school_title"
    . ' FROM schoolsafe_notify_deliveries d'
    . ' JOIN schoolsafe_notify_events e ON e.id=d.event_id'
    . ' LEFT JOIN staff s ON s.staff_id=d.recipient_staff_id'
    . ' LEFT JOIN schools sch ON sch.id=d.school_id AND sch.syear=d.syear'
    . " WHERE d.status='pending' AND ($1='all' OR d.channel=$1)"
    . " ORDER BY d.created_at, d.id LIMIT $limit", [$channel]);
if ($res === false) { fwrite(STDERR, "QUERY_FAILED\n"); exit(1); }

$sent = 0; $failed = 0;
while ($row = pg_fetch_assoc($res)) {
    $id = (int) $row['id'];
    $ch = $row['channel'];
    $ok = false; $err = '';
    $status = 'sent'; $providerId = '';
    if ($forceFail) { $ok = false; $err = 'FORCED_FAILURE'; }
    elseif ($ch === 'feed') { $ok = true; $status = 'sent'; }
    elseif ($ch === 'email') {
        $email = trim((string) ($row['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $ok = false; $err = 'NO_RECIPIENT_EMAIL'; }
        else {
            try {
                $payload = json_decode((string) ($row['payload'] ?? '{}'), true);
                if (!is_array($payload)) $payload = [];
                $mail = SchoolSafeMailBuild(
                    ['event_type' => (string) ($row['event_type'] ?? ''), 'payload' => $payload],
                    ['school_name' => (string) ($row['school_title'] ?? ''), 'locale' => 'fr']);
                $out = SchoolSafeBrevoSend($email, $mail);
                $ok = true; $status = 'accepted'; $providerId = $out['provider_msg_id'];
            } catch (Throwable $e) { $ok = false; $err = $e->getMessage(); }
        }
    } elseif ($ch === 'push') {
        $sub = pg_query_params($conn, 'SELECT endpoint FROM schoolsafe_notification_push_subscriptions'
            . ' WHERE staff_id=$1 ORDER BY created_at DESC LIMIT 1', [(int) $row['recipient_staff_id']]);
        $endpoint = ($sub !== false && ($srow = pg_fetch_assoc($sub))) ? (string) $srow['endpoint'] : '';
        if ($endpoint === '') { $ok = false; $err = 'PUSH_UNAVAILABLE'; }
        else {
            try {
                SchoolSafeWebPushSend(['endpoint' => $endpoint]);
                $ok = true; $status = 'accepted';
            } catch (Throwable $e) {
                $ok = false; $err = $e->getMessage();
                if ($e->getMessage() === 'SUBSCRIPTION_EXPIRED') {
                    pg_query_params($conn, 'DELETE FROM schoolsafe_notification_push_subscriptions'
                        . ' WHERE staff_id=$1 AND endpoint=$2',
                        [(int) $row['recipient_staff_id'], $endpoint]);
                }
            }
        }
    }
    if ($ok) {
        pg_query_params($conn, 'UPDATE schoolsafe_notify_deliveries SET status=$2, attempts=attempts+1,'
            . " last_error='', provider_msg_id=$3, updated_at=now() WHERE id=$1",
            [$id, $status, substr($providerId, 0, 128)]);
        $sent++;
    } else {
        pg_query_params($conn, "UPDATE schoolsafe_notify_deliveries SET status='failed', attempts=attempts+1,"
            . " last_error=$2, updated_at=now() WHERE id=$1", [$id, substr($err, 0, 500)]);
        $failed++;
    }
}
echo "DISPATCH sent=$sent failed=$failed channel=$channel\n";
exit(0);
