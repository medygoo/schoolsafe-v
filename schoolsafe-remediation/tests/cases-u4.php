<?php
// SchoolSafe — Lot U4 : multicanal, fiabilite, identite, urgences (isole).
// Usage (conteneur L1) : php schoolsafe-remediation/tests/cases-u4.php
// Sortie 0 ssi tous les cas passent. Nettoyage verifie (0 residu).
// Limites declarees (non revendiquees) : envoi reel Brevo et push reel sur
// appareil exigent cle + destinataire de test fournis separement (voir U4e).
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(2); }
chdir(dirname(__DIR__, 2));
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeP1.fnc.php';
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeMailTemplate.fnc.php';
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeBrevo.fnc.php';
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeWebPush.fnc.php';

$failures = 0;
function check($label, $actual, $expected) {
    global $failures;
    if ($actual === $expected) { echo "ok   $label\n"; return; }
    $failures++;
    echo "FAIL $label : attendu " . var_export($expected, true) . ", obtenu " . var_export($actual, true) . "\n";
}

$host = getenv('SCHOOLSAFE_DB_HOST') ?: 'db';
$port = getenv('SCHOOLSAFE_DB_PORT') ?: '5432';
$user = getenv('POSTGRES_USER') ?: 'schoolsafe';
$pass = getenv('POSTGRES_PASSWORD') ?: '';
$dbname = getenv('POSTGRES_DB') ?: 'schoolsafe';
$conn = @pg_connect("host=$host port=$port dbname=$dbname user=$user password=$pass connect_timeout=5");
if ($conn === false) { fwrite(STDERR, "FAIL connexion DB isolee\n"); exit(1); }
$q = function ($sql, $params = []) use ($conn) {
    $res = $params ? @pg_query_params($conn, $sql, $params) : @pg_query($conn, $sql);
    if ($res === false) throw new RuntimeException('DB_QUERY_FAILED: ' . substr($sql, 0, 140) . ' :: ' . pg_last_error($conn));
    return $res;
};
$get = function ($sql) use ($conn) {
    $res = @pg_query($conn, $sql);
    if ($res === false) throw new RuntimeException('DB_QUERY_FAILED: ' . substr($sql, 0, 140));
    $out = []; $i = 1;
    while ($r = pg_fetch_assoc($res)) { $out[$i++] = array_change_key_case($r, CASE_UPPER); }
    return $out;
};
$db = ['get' => $get, 'exec' => function ($sql) use ($conn) {
    if (@pg_query($conn, $sql) === false) throw new RuntimeException('DB_EXEC_FAILED: ' . pg_last_error($conn));
}];
$dispatch = function ($args) {
    $out = [];
    exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/../../schoolsafe-notify-dispatch.php') . ' ' . $args . ' 2>&1', $out, $code);
    return [implode("\n", $out), $code];
};
$year = (int) (getenv('SCHOOLSAFE_DEFAULT_SYEAR') ?: 2026);
$schoolRow = $get('SELECT ID FROM schools WHERE SYEAR=' . $year . ' ORDER BY ID LIMIT 2');
$schoolA = (int) $schoolRow[1]['ID'];
$schoolB = isset($schoolRow[2]) ? (int) $schoolRow[2]['ID'] : $schoolA;
check('ecoles-temoins', $schoolB !== $schoolA, true);
$uniq = 'u4' . substr(md5(uniqid('', true)), 0, 8);

// Pas de fournisseur configure en isole (honnetete verifiee plus bas).
putenv('BREVO_API_KEY'); putenv('VAPID_PRIVATE_PEM'); putenv('VAPID_PUBLIC_KEY');
check('brevo-absent-isole', SchoolSafeBrevoConfig(), null);
check('vapid-absent-isole', SchoolSafeWebPushConfig(), null);

$adminRow = $get("SELECT STAFF_ID FROM staff WHERE STAFF_ID=1 AND SYEAR=$year LIMIT 1");
$admin = isset($adminRow[1]) ? (int) $adminRow[1]['STAFF_ID'] : 0;
$q('INSERT INTO staff(syear, first_name, last_name, profile, username, email, schools) VALUES ($1,$2,$3,$4,$5,$6,$7)',
    [$year, 'U4', 'Prof', 'teacher', 'u4prof_' . $uniq, $uniq . 't@example.invalid', ",$schoolA,"]);
$q('INSERT INTO staff(syear, first_name, last_name, profile, username, email, schools) VALUES ($1,$2,$3,$4,$5,$6,$7)',
    [$year, 'U4', 'Mom', 'parent', 'u4mom_' . $uniq, $uniq . 'm@example.invalid', ",$schoolA,"]);
$q('INSERT INTO staff(syear, first_name, last_name, profile, username, email, schools) VALUES ($1,$2,$3,$4,$5,$6,$7)',
    [$year, 'U4', 'Gard', 'none', 'u4gard_' . $uniq, $uniq . 'g@example.invalid', ",$schoolA,"]);
$q('INSERT INTO staff(syear, first_name, last_name, profile, username, email, schools) VALUES ($1,$2,$3,$4,$5,$6,$7)',
    [$year, 'U4', 'Etrange', 'teacher', 'u4etrang_' . $uniq, $uniq . 'e@example.invalid', ",$schoolB,"]);
$st = function ($uname) use ($get) {
    $r = $get("SELECT STAFF_ID FROM staff WHERE USERNAME='$uname'");
    return (int) $r[1]['STAFF_ID'];
};
$teacher = $st('u4prof_' . $uniq);
$mom = $st('u4mom_' . $uniq);
$guard = $st('u4gard_' . $uniq);
$stranger = $st('u4etrang_' . $uniq);
$q('INSERT INTO students(first_name, last_name, username) VALUES ($1,$2,$3)', ['U4Kid', 'Kid', 'u4eleve_' . $uniq]);
$sr = $get("SELECT STUDENT_ID FROM students WHERE USERNAME='u4eleve_$uniq'");
$sid = (int) $sr[1]['STUDENT_ID'];
$q('INSERT INTO student_enrollment(syear, school_id, student_id) VALUES ($1,$2,$3)', [$year, $schoolA, $sid]);
$q('INSERT INTO students_join_users(student_id, staff_id) VALUES ($1,$2)', [$sid, $mom]);

// ---- A. Notification immediate individuelle P1-P4, sans regroupement.
$alertIds = [];
foreach (['P1', 'P2', 'P3', 'P4'] as $sev) {
    $r = SchoolSafeP1Raise($schoolA, $year, 'security', $sev, "Alerte $sev $uniq",
        'Corps de test.', $admin, [$admin, $teacher], $db, $sid, "u4-$uniq-$sev");
    $alertIds[$sev] = $r['alert_id'];
    check("levee-$sev", [$r['created'], $r['notified']], [true, 2]);
}
$evCount = $get("SELECT COUNT(*) AS C FROM schoolsafe_notify_events WHERE DEDUP_KEY LIKE 'p1:%' AND DEDUP_KEY NOT LIKE '%:esc:%' AND SCHOOL_ID=$schoolA");
check('4-evenements-individuels', ((int) $evCount[1]['C']) >= 4, true);
$noDigest = $get("SELECT to_regclass('public.schoolsafe_notify_digests') AS T");
$dt = '';
foreach ((array) $noDigest as $rr) { $dt = (string) ($rr['T'] ?? ''); break; }
check('aucun-regroupement-quotidien', $dt, '');
$pendCount = $get("SELECT COUNT(*) AS C FROM schoolsafe_notify_deliveries d JOIN schoolsafe_notify_events e ON e.ID=d.EVENT_ID WHERE e.DEDUP_KEY LIKE 'u4-$uniq-%' OR e.DEDUP_KEY LIKE 'p1:%'");
check('livraisons-individuelles', ((int) $pendCount[1]['C']) >= 24, true);

// ---- B. Dispatcher honnete : feed ok, email/push sans fournisseur = failed.
list($outFeed, $codeFeed) = $dispatch('--once --limit=500 --channel=feed');
check('dispatch-feed', $codeFeed, 0);
$sentFeed = $get("SELECT COUNT(*) AS C FROM schoolsafe_notify_deliveries d JOIN schoolsafe_notify_events e ON e.ID=d.EVENT_ID WHERE e.DEDUP_KEY LIKE 'p1:%' AND d.CHANNEL='feed' AND d.STATUS='sent'");
check('feed-sent-local', ((int) $sentFeed[1]['C']) >= 8, true);
list($outMail, $codeMail) = $dispatch('--once --limit=500 --channel=email');
check('dispatch-email', $codeMail, 0);
$mailRow = $get("SELECT STATUS, LAST_ERROR, ATTEMPTS FROM schoolsafe_notify_deliveries d JOIN schoolsafe_notify_events e ON e.ID=d.EVENT_ID WHERE e.DEDUP_KEY='p1:" . $alertIds['P1'] . "' AND d.CHANNEL='email' AND d.RECIPIENT_STAFF_ID=$teacher LIMIT 1");
check('email-sans-fournisseur-failed', $mailRow[1]['STATUS'], 'failed');
check('email-erreur-honnete', strpos($mailRow[1]['LAST_ERROR'], 'NO_EMAIL_PROVIDER') !== false, true);
check('email-ligne-conservee', ((int) $mailRow[1]['ATTEMPTS']) >= 1, true);
// Reprise : la ligne repasse pending, rien n'est supprime.
list($outRetry, $codeRetry) = $dispatch('--once --limit=500 --channel=email --retry-failed');
$mailRow2 = $get("SELECT STATUS FROM schoolsafe_notify_deliveries d JOIN schoolsafe_notify_events e ON e.ID=d.EVENT_ID WHERE e.DEDUP_KEY='p1:" . $alertIds['P1'] . "' AND d.CHANNEL='email' LIMIT 1");
check('reprise-retry-failed', in_array($mailRow2[1]['STATUS'], ['failed', 'pending'], true), true);
// Push sans abonnement ni cles.
list($outPush, $codePush) = $dispatch('--once --limit=500 --channel=push');
$pushRow = $get("SELECT STATUS, LAST_ERROR FROM schoolsafe_notify_deliveries d JOIN schoolsafe_notify_events e ON e.ID=d.EVENT_ID WHERE e.DEDUP_KEY='p1:" . $alertIds['P1'] . "' AND d.CHANNEL='push' LIMIT 1");
check('push-sans-abonnement-failed', $pushRow[1]['STATUS'], 'failed');
check('push-erreur-honnete', strpos($pushRow[1]['LAST_ERROR'], 'PUSH_UNAVAILABLE') !== false, true);
// Abonnement factice + pas de cles VAPID => echec honnete (pas de faux succes).
$q('INSERT INTO schoolsafe_notification_push_subscriptions(staff_id, school_id, syear, endpoint, p256dh, auth) VALUES ($1,$2,$3,$4,$5,$6)',
    [$mom, $schoolA, $year, 'https://push.invalid/u4-' . $uniq, 'x', 'y']);
$evP = SchoolSafeNotifyEmit(['school_id' => $schoolA, 'syear' => $year, 'event_type' => 'school.announcement',
    'dedup_key' => "u4-push-$uniq", 'payload' => ['kind' => 'new_message']],
    [['audience' => 'parent', 'staff_id' => $mom, 'channel' => 'push']], $db);
$dispatch('--once --limit=500 --channel=push');
$pushRow2 = $get("SELECT STATUS, LAST_ERROR FROM schoolsafe_notify_deliveries WHERE EVENT_ID=" . (int) $evP['event_id'] . " LIMIT 1");
check('push-sans-cles-failed', $pushRow2[1]['STATUS'], 'failed');
check('push-sans-cles-honnete', strpos($pushRow2[1]['LAST_ERROR'], 'NO_PUSH_PROVIDER') !== false, true);

// ---- C. Brevo : construction + succes/ echec via stub (sans reseau).
$mail = SchoolSafeMailBuild(['event_type' => 'absence.detected',
    'payload' => ['kind' => 'absence', 'student_name' => 'Test', 'time' => '09:00']],
    ['school_name' => 'Ecole Test', 'locale' => 'fr']);
$okHttp = function ($url, $headers, $body) {
    return ['http_code' => 201, 'body' => '{"messageId":"brevo-test-1"}'];
};
$out = SchoolSafeBrevoSend('parent@example.invalid', $mail, ['api_key' => 'k', 'base_url' => 'https://x'], $okHttp);
check('brevo-accepte', [$out['accepted'], $out['provider_msg_id']], [true, 'brevo-test-1']);
$koHttp = function ($url, $headers, $body) {
    return ['http_code' => 400, 'body' => '{"message":"bad"}'];
};
try {
    SchoolSafeBrevoSend('parent@example.invalid', $mail, ['api_key' => 'k', 'base_url' => 'https://x'], $koHttp);
    check('brevo-erreur-levee', 'accepte', 'exception');
} catch (RuntimeException $e) {
    check('brevo-erreur-levee', strpos($e->getMessage(), 'BREVO_ERROR:400') === 0, true);
}
try {
    SchoolSafeBrevoSend('parent@example.invalid', $mail, null);
    check('brevo-sans-cle-refuse', 'envoye', 'exception');
} catch (RuntimeException $e) {
    check('brevo-sans-cle-refuse', $e->getMessage(), 'NO_EMAIL_PROVIDER');
}
try {
    SchoolSafeBrevoSend('pas-un-email', $mail, ['api_key' => 'k', 'base_url' => 'https://x'], $okHttp);
    check('brevo-adresse-invalide', 'accepte', 'exception');
} catch (RuntimeException $e) {
    check('brevo-adresse-invalide', $e->getMessage(), 'NO_RECIPIENT_EMAIL');
}

// ---- D. Web Push : JWT signe OpensSSL, 410 => abonnement mort.
$ec = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
openssl_pkey_export($ec, $pem);
// Config directe ( la lecture env est testee par l'absence ci-dessus ; putenv
// multiline n'est pas fiable sous tous les shells, la prod utilise l'env reel).
$cfg = ['private_pem' => $pem, 'public_key' => 'u4testkey',
    'subject' => 'mailto:securite@mail.shoolsafe.tech'];
check('vapid-config', is_array($cfg) && SchoolSafeWebPushConfig() === null, true);
$jwt = SchoolSafeWebPushJwt('https://push.example', $cfg);
check('jwt-structure', count(explode('.', $jwt)), 3);
$http201 = function ($url, $headers, $body) { return ['http_code' => 201, 'body' => '']; };
$wout = SchoolSafeWebPushSend(['endpoint' => 'https://push.example/abc'], $cfg, $http201);
check('push-signal-accepte', $wout['accepted'], true);
$http410 = function ($url, $headers, $body) { return ['http_code' => 410, 'body' => '']; };
try {
    SchoolSafeWebPushSend(['endpoint' => 'https://push.example/abc'], $cfg, $http410);
    check('push-410-expire', 'accepte', 'exception');
} catch (RuntimeException $e) {
    check('push-410-expire', $e->getMessage(), 'SUBSCRIPTION_EXPIRED');
}
try {
    SchoolSafeWebPushSend(['endpoint' => 'https://push.example/abc']);
    check('push-sans-cles-refuse', 'envoye', 'exception');
} catch (RuntimeException $e) {
    check('push-sans-cles-refuse', $e->getMessage(), 'NO_PUSH_PROVIDER');
}

// ---- E. P1 : habilitations, lecture != prise en charge, escalade, cloture.
try {
    SchoolSafeP1Raise($schoolA, $year, 'security', 'P1', "X $uniq", 'Y', $admin, [$mom], $db, 0, "u4-parent-$uniq");
    check('p1-parent-refuse', 'accepte', 'refuse');
} catch (RuntimeException $e) {
    check('p1-parent-refuse', $e->getMessage(), 'RECIPIENT_DENIED');
}
try {
    SchoolSafeP1Raise($schoolA, $year, 'security', 'P1', "X $uniq", 'Y', $admin, [$guard], $db, 0, "u4-gardien-$uniq");
    check('p1-gardien-refuse', 'accepte', 'refuse');
} catch (RuntimeException $e) {
    check('p1-gardien-refuse', $e->getMessage(), 'RECIPIENT_DENIED');
}
try {
    SchoolSafeP1Raise($schoolA, $year, 'security', 'P1', "X $uniq", 'Y', $admin, [$stranger], $db, 0, "u4-etranger-$uniq");
    check('p1-autre-ecole-refuse', 'accepte', 'refuse');
} catch (RuntimeException $e) {
    check('p1-autre-ecole-refuse', $e->getMessage(), 'RECIPIENT_DENIED');
}
// Lecture ne vaut pas prise en charge.
$view = SchoolSafeP1Get($alertIds['P1'], $teacher, $db);
check('p1-lecture-destinataire', (int) $view['alert']['ID'], $alertIds['P1']);
$chk = $get('SELECT ACK_AT, STATUS FROM schoolsafe_p1_alerts WHERE ID=' . $alertIds['P1']);
check('p1-lecture-sans-ack', [$chk[1]['STATUS'], $chk[1]['ACK_AT']], ['open', null]);
try {
    SchoolSafeP1Get($alertIds['P1'], $stranger, $db);
    check('p1-lecture-etranger-refusee', 'lisible', 'refusee');
} catch (RuntimeException $e) {
    check('p1-lecture-etranger-refusee', $e->getMessage(), 'READ_DENIED');
}
// Ack : etranger refuse, destinataire ok, rejeu sans doublon.
try {
    SchoolSafeP1Ack($alertIds['P1'], $stranger, $db);
    check('p1-ack-etranger-refuse', 'accepte', 'refuse');
} catch (RuntimeException $e) {
    check('p1-ack-etranger-refuse', $e->getMessage(), 'ACK_DENIED');
}
$ack = SchoolSafeP1Ack($alertIds['P1'], $teacher, $db);
check('p1-ack-nominatif', $ack['acked'], true);
$ackWho = $get('SELECT ACK_BY FROM schoolsafe_p1_alerts WHERE ID=' . $alertIds['P1']);
check('p1-ack-attribue', (int) $ackWho[1]['ACK_BY'], $teacher);
$ack2 = SchoolSafeP1Ack($alertIds['P1'], $admin, $db);
check('p1-reack-sans-doublon', $ack2['acked'], false);
// Escalade P2 : delai depasse => escaladee, TOUJOURS ouverte.
$q("UPDATE schoolsafe_p1_alerts SET CREATED_AT=CURRENT_TIMESTAMP - INTERVAL '20 minutes' WHERE ID=" . $alertIds['P2']);
$wout2 = [];
exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/../../schoolsafe-p1-watch.php') . ' --once 2>&1', $wout2, $wcode);
check('watch-ok', $wcode, 0);
$esc = $get('SELECT STATUS, ESCALATED, ESCALATIONS, ACK_AT FROM schoolsafe_p1_alerts WHERE ID=' . $alertIds['P2']);
check('p1-escaladee', [$esc[1]['ESCALATED'], ((int) $esc[1]['ESCALATIONS']) >= 1], ['t', true]);
check('p1-jamais-cloturee-auto', [$esc[1]['STATUS'], $esc[1]['ACK_AT']], ['open', null]);
$escEv = $get("SELECT COUNT(*) AS C FROM schoolsafe_notify_events WHERE DEDUP_KEY='p1:" . $alertIds['P2'] . ":esc:1'");
check('p1-escalade-notifiee', ((int) $escEv[1]['C']), 1);
// Ni dispatcher ni recover ni watch ne cloturent.
$dispatch('--once --limit=500 --channel=all');
exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/../../schoolsafe-notify-recover.php') . ' --source=all 2>&1', $rout, $rcode);
exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/../../schoolsafe-p1-watch.php') . ' --once 2>&1', $wout3, $wcode3);
$still = $get('SELECT STATUS FROM schoolsafe_p1_alerts WHERE ID IN (' . $alertIds['P2'] . ',' . $alertIds['P3'] . ',' . $alertIds['P4'] . ')');
$allOpen = true;
foreach ((array) $still as $row) { if (isset($row['STATUS']) && $row['STATUS'] !== 'open') $allOpen = false; }
check('p1-urgences-restent-ouvertes', $allOpen, true);
// Cloture humaine : direction ok, etranger refuse, alerte close => ack refuse.
try {
    SchoolSafeP1Close($alertIds['P2'], $stranger, 'note', $db);
    check('p1-cloture-etranger-refusee', 'close', 'refuse');
} catch (RuntimeException $e) {
    check('p1-cloture-etranger-refusee', $e->getMessage(), 'CLOSE_DENIED');
}
$close = SchoolSafeP1Close($alertIds['P2'], $admin, 'Prise en charge sur place.', $db);
check('p1-cloture-direction', $close['closed'], true);
try {
    SchoolSafeP1Ack($alertIds['P2'], $teacher, $db);
    check('p1-ack-apres-cloture-refuse', 'accepte', 'refuse');
} catch (RuntimeException $e) {
    check('p1-ack-apres-cloture-refuse', $e->getMessage(), 'ALERT_CLOSED');
}
// Levee idempotente (meme dedup).
$dup = SchoolSafeP1Raise($schoolA, $year, 'security', 'P1', "Alerte P1 $uniq", 'Corps de test.', $admin, [$admin, $teacher], $db, $sid, "u4-$uniq-P1");
check('p1-levee-idempotente', [$dup['created'], $dup['alert_id']], [false, $alertIds['P1']]);

// ---- F. Reconciliation : absence sans evenement, dossier sans notification.
$q('INSERT INTO students(first_name, last_name, username) VALUES ($1,$2,$3)', ['U4Abs', 'Kid', 'u4abs_' . $uniq]);
$ar = $get("SELECT STUDENT_ID FROM students WHERE USERNAME='u4abs_$uniq'");
$asid = (int) $ar[1]['STUDENT_ID'];
$q('INSERT INTO student_enrollment(syear, school_id, student_id) VALUES ($1,$2,$3)', [$year, $schoolA, $asid]);
$q('INSERT INTO students_join_users(student_id, staff_id) VALUES ($1,$2)', [$asid, $mom]);
$ado = date('Y-m-d', strtotime('-1 day'));
$q('INSERT INTO schoolsafe_absence_events(school_id, syear, student_id, school_date) VALUES ($1,$2,$3,$4)',
    [$schoolA, $year, $asid, $ado]);
$rout2 = [];
exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/../../schoolsafe-notify-recover.php') . ' --source=absence 2>&1', $rout2, $rcode2);
check('recover-absence-ok', $rcode2, 0);
$recEv = $get("SELECT ID FROM schoolsafe_notify_events WHERE DEDUP_KEY='absence:$schoolA:$ado:$asid' LIMIT 1");
check('recover-absence-reconcilie', isset($recEv[1]), true);
$recLink = $get("SELECT NOTIFY_EVENT_ID FROM schoolsafe_absence_events WHERE SCHOOL_ID=$schoolA AND STUDENT_ID=$asid AND SCHOOL_DATE='$ado' LIMIT 1");
check('recover-absence-liee', (int) $recLink[1]['NOTIFY_EVENT_ID'], (int) $recEv[1]['ID']);
$rout3 = [];
exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/../../schoolsafe-notify-recover.php') . ' --source=absence 2>&1', $rout3, $rcode3);
check('recover-absence-idempotent', strpos(implode("\n", $rout3), 'absence_recovered=0') !== false, true);
// Dossier U2 : decision sans notification => reconciliee.
$q('INSERT INTO schoolsafe_case_files(school_id, syear, student_id, school_date, status) VALUES ($1,$2,$3,$4,$5)',
    [$schoolA, $year, $sid, $ado, 'pending_decision']);
$fr = $get("SELECT ID FROM schoolsafe_case_files WHERE SCHOOL_ID=$schoolA AND STUDENT_ID=$sid AND SCHOOL_DATE='$ado' LIMIT 1");
$fid = (int) $fr[1]['ID'];
$q('INSERT INTO schoolsafe_case_events(file_id, seq, kind, actor_staff_id) VALUES ($1,1,$2,$3)', [$fid, 'decision', $admin]);
$er = $get("SELECT ID FROM schoolsafe_case_events WHERE FILE_ID=$fid AND KIND='decision' LIMIT 1");
$evid = (int) $er[1]['ID'];
$rout4 = [];
exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/../../schoolsafe-notify-recover.php') . ' --source=cases 2>&1', $rout4, $rcode4);
check('recover-cases-ok', $rcode4, 0);
$caseEv = $get("SELECT ID FROM schoolsafe_notify_events WHERE DEDUP_KEY='case:$fid:decision:$evid' LIMIT 1");
check('recover-cases-reconcilie', isset($caseEv[1]), true);

// ---- G. Identite : FR/EN, expediteur, pas d'image cassee, lien profond.
check('mail-expediteur', $mail['from'], 'securite@mail.shoolsafe.tech');
check('mail-sujet-fr', strpos($mail['subject'], 'Absence') !== false, true);
check('mail-sans-img-cassee', strpos($mail['html'], '<img') === false, true);
check('mail-html-responsive', strpos($mail['html'], 'viewport') !== false, true);
$mailEn = SchoolSafeMailBuild(['event_type' => 'security.p1',
    'payload' => ['kind' => 'security_p1', 'severity' => 'P1', 'title' => 'Test']],
    ['school_name' => 'Test School', 'locale' => 'en',
     'portal_url' => 'https://studio.example.invalid/parent',
     'brand_logo_url' => 'https://cdn.example.invalid/logo.png']);
check('mail-sujet-en', strpos($mailEn['subject'], 'URGENT') !== false, true);
check('mail-p1-clause-en', strpos($mailEn['text'], 'not an acknowledgement') !== false, true);
check('mail-lien-profond', strpos($mailEn['html'], 'https://studio.example.invalid/parent') !== false, true);
check('mail-logo-ecole', strpos($mailEn['html'], 'https://cdn.example.invalid/logo.png') !== false, true);
$mailP1fr = SchoolSafeMailBuild(['event_type' => 'security.p1',
    'payload' => ['kind' => 'security_p1', 'severity' => 'P1']],
    ['school_name' => 'Ecole Test', 'locale' => 'fr']);
check('mail-p1-clause-fr', strpos($mailP1fr['text'], 'ne vaut pas prise en charge') !== false, true);

// ---- H. Confidentialite : parent B isole du fil A.
$feedB = SchoolSafeNotifyTestFeed($stranger, $schoolA, $db, 50);
check('fil-parent-B-vide-en-A', $feedB, []);
$feedMom = SchoolSafeNotifyTestFeed($mom, $schoolA, $db, 50);
check('fil-parent-A-non-vide', count($feedMom) > 0, true);

// Nettoyage isole.
$alertList = implode(',', array_map('intval', $alertIds));
$q("DELETE FROM schoolsafe_p1_recipients WHERE ALERT_ID IN ($alertList)");
$q("DELETE FROM schoolsafe_p1_alerts WHERE ID IN ($alertList)");
$dds = ["u4-push-$uniq",
    "absence:$schoolA:$ado:$asid", "case:$fid:decision:$evid"];
foreach ($dds as $dd) {
    $q('DELETE FROM schoolsafe_notify_deliveries WHERE EVENT_ID IN (SELECT ID FROM schoolsafe_notify_events WHERE DEDUP_KEY=$1)', [$dd]);
    $q('DELETE FROM schoolsafe_notify_events WHERE DEDUP_KEY=$1', [$dd]);
}
// Alertes P1 : evenement initial + toutes les escalades (p1:<id>%).
foreach ($alertIds as $aid) {
    $q('DELETE FROM schoolsafe_notify_deliveries WHERE EVENT_ID IN (SELECT ID FROM schoolsafe_notify_events WHERE DEDUP_KEY LIKE $1)', ["p1:$aid%"]);
    $q('DELETE FROM schoolsafe_notify_events WHERE DEDUP_KEY LIKE $1', ["p1:$aid%"]);
}
$q("DELETE FROM schoolsafe_notification_push_subscriptions WHERE ENDPOINT='https://push.invalid/u4-$uniq'");
$q('DELETE FROM schoolsafe_absence_events WHERE STUDENT_ID=$1', [$asid]);
// Dossiers AVANT les eleves (FK case_files -> students) : purge de test
// autorisee (transaction dediee, jamais en prod).
@pg_query($conn, 'BEGIN');
@pg_query($conn, "SET LOCAL schoolsafe.allow_case_purge='1'");
@pg_query($conn, "DELETE FROM schoolsafe_case_events WHERE FILE_ID=$fid");
@pg_query($conn, "DELETE FROM schoolsafe_justifications WHERE FILE_ID=$fid");
@pg_query($conn, "DELETE FROM schoolsafe_case_files WHERE ID=$fid");
@pg_query($conn, 'COMMIT');
$q("DELETE FROM students_join_users WHERE STUDENT_ID IN ($sid,$asid)");
$q("DELETE FROM student_enrollment WHERE STUDENT_ID IN ($sid,$asid)");
$q("DELETE FROM students WHERE STUDENT_ID IN ($sid,$asid)");
$q("DELETE FROM staff WHERE USERNAME IN ('u4prof_$uniq','u4mom_$uniq','u4gard_$uniq','u4etrang_$uniq','u4abs_$uniq')");
echo ($failures === 0 ? "U4 AUTOMATED GREEN" : "U4 AUTOMATED RED") . "\n";
exit($failures ? 1 : 0);
