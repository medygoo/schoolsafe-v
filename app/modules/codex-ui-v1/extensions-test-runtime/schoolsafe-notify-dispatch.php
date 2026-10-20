<?php
// SchoolSafe — Lot U0 : dispatcher independant des requetes de scan (CLI uniquement).
//
// Usage (conteneur L1) : php schoolsafe-notify-dispatch.php --once [--limit=50] [--channel=all|feed|email|push|whatsapp]
// WhatsApp (037) : WAHA principal + Evolution secours ; miroir automatique des
// evenements push/email eligibles (lignes whatsapp ajoutees, regles push/email
// inchangees) ; copies expurgees (jamais de mot de passe/lien secret/code).
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
require_once __DIR__ . '/ProgramFunctions/SchoolSafeSms.fnc.php';
require_once __DIR__ . '/ProgramFunctions/SchoolSafeWhatsapp.fnc.php';
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(2); }

$once = in_array('--once', $argv, true);
$limit = 50;
$channel = 'all';
$retryFailed = in_array('--retry-failed', $argv, true);
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) $limit = max(1, min(500, (int) substr($arg, 8)));
    if (str_starts_with($arg, '--channel=')) $channel = substr($arg, 10);
}
if (!$once || !in_array($channel, ['all', 'feed', 'email', 'push', 'whatsapp'], true)) {
    fwrite(STDERR, "Usage: php schoolsafe-notify-dispatch.php --once [--limit=50] [--channel=all|feed|email|push|whatsapp] [--retry-failed]\n");
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

// Miroir WhatsApp : pour chaque evenement eligible porte par une livraison
// push/email (pending/accepted/sent), ajoute la livraison whatsapp manquante
// (meme evenement/audience/destinataire). Les lignes push/email ne sont ni
// lues en ecriture ni modifiees ; le doublon est impossible (NOT EXISTS).
if ($channel === 'all' || $channel === 'whatsapp') {
    @pg_query($conn, "INSERT INTO schoolsafe_notify_deliveries"
        . '(event_id, school_id, syear, audience, recipient_staff_id, channel)'
        . ' SELECT d.event_id, d.school_id, d.syear, d.audience, d.recipient_staff_id, \'whatsapp\''
        . ' FROM schoolsafe_notify_deliveries d'
        . ' JOIN schoolsafe_notify_events e ON e.id=d.event_id'
        . " WHERE d.channel IN ('push','email') AND d.status IN ('pending','accepted','sent')"
        . " AND e.event_type IN ('security.entry','student.pickup','payment.receipt',"
        . "'school.announcement','remediation.update','attendance.closure',"
        . "'absence.detected','case.updated','security.p1')"
        . ' AND NOT EXISTS (SELECT 1 FROM schoolsafe_notify_deliveries x'
        . ' WHERE x.event_id=d.event_id AND x.audience=d.audience'
        . ' AND x.recipient_staff_id=d.recipient_staff_id AND x.channel=\'whatsapp\')'
        . ' ON CONFLICT (event_id, audience, recipient_staff_id, channel) DO NOTHING');
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
                // Identite visuelle : logo SchoolSafe + portrait JASPE officiels,
                // servis en HTTPS depuis l'application (surchageable en isole via
                // SCHOOLSAFE_ASSETS_BASE_URL) ; absents => simplement omis.
                $assetsBase = rtrim(trim((string) getenv('SCHOOLSAFE_ASSETS_BASE_URL')
                    ?: 'https://app.shoolsafe.tech'), '/');
                $mail = SchoolSafeMailBuild(
                    ['event_type' => (string) ($row['event_type'] ?? ''), 'payload' => $payload],
                    ['school_name' => (string) ($row['school_title'] ?? ''), 'locale' => 'fr',
                     'brand_logo_url' => $assetsBase . '/assets/themes/SchoolSafe/logo.png',
                     'jaspe_url' => $assetsBase . '/assets/jaspe2d/pack1/wave.webp']);
                $out = SchoolSafeBrevoSend($email, $mail);
                $ok = true; $status = 'accepted'; $providerId = $out['provider_msg_id'];
            } catch (Throwable $e) { $ok = false; $err = $e->getMessage(); }
        }
    } elseif ($ch === 'whatsapp') {
        // Copie courte + expurgee : jamais de secret. Meme table de decision
        // que SchoolSafeWaSend (simulation, consentement, WAHA puis secours
        // Evolution sur echec confirme uniquement), executee sur la connexion
        // $conn du dispatcher (Warehouse non charge en CLI) ; les branches
        // feed/email/push ci-dessus et ci-dessous sont inchangees.
        $ph = pg_query_params($conn, 'SELECT custom_200000001 FROM staff WHERE staff_id=$1 LIMIT 1',
            [(int) $row['recipient_staff_id']]);
        $rawPhone = ($ph !== false && ($prow = pg_fetch_assoc($ph))) ? trim((string) ($prow['custom_200000001'] ?? '')) : '';
        $copy = 'SchoolSafe — ' . (string) ($row['event_type'] ?? '') . ' — ' . (string) ($row['school_title'] ?? '');
        $toWa = SchoolSafeWaNormalize($rawPhone);
        if ($rawPhone === '' || $toWa === false) { $ok = false; $err = 'NO_RECIPIENT_PHONE'; }
        elseif (!SchoolSafeWaSanitize($copy)) { $ok = false; $err = 'SENSITIVE_BLOCKED'; }
        else {
            $simRow = pg_query_params($conn, "SELECT config_value FROM config WHERE title='WHATSAPP_SIMULATE' LIMIT 1", []);
            $simOn = ($simRow !== false && ($srow = pg_fetch_assoc($simRow))) && trim((string) ($srow['config_value'] ?? '')) === 'Y';
            if ($simOn) { $ok = true; $status = 'accepted'; $providerId = 'simulated'; }
            else {
                $cs = pg_query_params($conn, 'SELECT 1 FROM schoolsafe_wa_consents'
                    . ' WHERE school_id=$1 AND staff_id=$2 AND phone_e164=$3 AND consented=$4 LIMIT 1',
                    [(int) ($row['school_id'] ?? 0), (int) $row['recipient_staff_id'], $toWa, 'Y']);
                $consented = ($cs !== false && pg_fetch_assoc($cs) !== false);
                if (!$consented) { $ok = false; $err = 'CONSENT_REQUIRED'; }
                else {
                    $se = pg_query_params($conn, "SELECT id, session_name FROM schoolsafe_wa_sessions"
                        . " WHERE engine='waha' AND status='active' AND conn_state<>'offline' ORDER BY id LIMIT 1", []);
                    $waha = ($se !== false) ? pg_fetch_assoc($se) : false;
                    if ($waha === false) { $ok = false; $err = 'NO_WA_SESSION'; }
                    else {
                        $wb = trim((string) getenv('WAHA_BASE')); $wk = (string) getenv('WAHA_KEY');
                        if ($wb === '' || $wk === '') { $ok = false; $err = 'NO_WA_CREDENTIALS'; }
                        else {
                            try {
                                $chatId = preg_replace('/[^0-9]/', '', $toWa) . '@c.us';
                                $res = SchoolSafeWaHttp('POST', rtrim($wb, '/') . '/api/sendText', $wk,
                                    json_encode(['chatId' => $chatId, 'text' => $copy,
                                        'session' => (string) $waha['session_name']],
                                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                                $codeWa = (int) ($res['http_code'] ?? 0);
                            } catch (Throwable $e) { $res = ['http_code' => 0, 'network_error' => $e->getMessage()]; $codeWa = 0; }
                            if ($codeWa === 200 || $codeWa === 201 || $codeWa === 202) {
                                $ok = true; $status = 'accepted'; $providerId = 'waha';
                            } elseif (!SchoolSafeWaConfirmedFailure($res)) {
                                $ok = false; $err = 'WAHA_ERROR:' . $codeWa;
                            } else {
                                $se2 = pg_query_params($conn, "SELECT session_name FROM schoolsafe_wa_sessions"
                                    . " WHERE engine='evolution' AND status='active' AND conn_state<>'offline' ORDER BY id LIMIT 1", []);
                                $evo = ($se2 !== false) ? pg_fetch_assoc($se2) : false;
                                $eb = trim((string) getenv('EVOLUTION_BASE')); $ek = (string) getenv('EVOLUTION_KEY');
                                if ($evo === false) { $ok = false; $err = 'WAHA_FAILED_NO_FALLBACK'; }
                                elseif ($eb === '' || $ek === '') { $ok = false; $err = 'NO_EVO_CREDENTIALS'; }
                                else {
                                    try {
                                        $res2 = SchoolSafeWaHttp('POST', rtrim($eb, '/') . '/message/sendText/'
                                            . rawurlencode((string) $evo['session_name']), $ek,
                                            json_encode(['number' => preg_replace('/[^0-9]/', '', $toWa), 'text' => $copy],
                                                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                                        $codeEvo = (int) ($res2['http_code'] ?? 0);
                                    } catch (Throwable $e) { $codeEvo = 0; }
                                    if ($codeEvo === 200 || $codeEvo === 201 || $codeEvo === 202) {
                                        $ok = true; $status = 'accepted'; $providerId = 'evolution-fallback';
                                    } else { $ok = false; $err = 'EVO_ERROR:' . $codeEvo; }
                                }
                            }
                        }
                    }
                }
            }
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
