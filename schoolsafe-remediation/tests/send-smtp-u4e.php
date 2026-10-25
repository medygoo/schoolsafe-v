<?php
// SchoolSafe — U4e REEL voie SMTP : relais Brevo en TLS via sockets PHP.
// Secret lu depuis env BREVO_SMTP_PASSWORD uniquement. Sortie EXPURGEE :
// codes SMTP seuls, jamais de secret, login, ni contenu sensible.
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(2); }
chdir(dirname(__DIR__, 2));
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeMailTemplate.fnc.php';

$pass = trim((string) getenv('BREVO_SMTP_PASSWORD'));
if ($pass === '') { echo "BLOCK=NO_SMTP_SECRET\n"; exit(2); }
$from = 'securite@mail.shoolsafe.tech';
$smtpLogin = trim((string) getenv('BREVO_SMTP_LOGIN'));
if ($smtpLogin === '') $smtpLogin = 'b48a08001@smtp-brevo.com'; // login de compte, distinct de l'expediteur
$to = 'lomsmedy@gmail.com';

$event = ['event_type' => 'security.entry', 'school_id' => 1,
    'payload' => ['title' => 'Test recette reelle U4e', 'student_name' => 'Eleve Temoin',
        'time' => '08:00', 'message' => 'Arrivee enregistree (test isole autorise).']];
$identity = ['school_name' => 'Ecole Temoin', 'locale' => 'fr', 'motto' => 'Savoir - Securite',
    'phone' => '+000 00 00 00', 'email' => 'contact@ecole-temoin.test'];
$mail = SchoolSafeMailBuild($event, $identity);

function smtp_expect($fp, $want) {
    $line = '';
    while (($l = fgets($fp, 1024)) !== false) {
        $line = $l;
        if (preg_match('/^\\d{3} /', $l)) break;
    }
    $code = (int) substr(trim($line), 0, 3);
    foreach ((array) $want as $w) { if ($code === $w) return $code; }
    throw new RuntimeException('SMTP_UNEXPECTED_' . $code);
}
function smtp_cmd($fp, $cmd) { fwrite($fp, $cmd . "\r\n"); }

try {
    $fp = @stream_socket_client('tcp://smtp-relay.brevo.com:587', $errno, $errstr, 20);
    if (!$fp) throw new RuntimeException('SMTP_CONNECT_FAIL');
    $greet = smtp_expect($fp, 220);
    smtp_cmd($fp, 'EHLO schoolsafe-test');
    smtp_expect($fp, 250);
    smtp_cmd($fp, 'STARTTLS');
    smtp_expect($fp, 220);
    if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        throw new RuntimeException('SMTP_TLS_FAIL');
    }
    smtp_cmd($fp, 'EHLO schoolsafe-test');
    smtp_expect($fp, 250);
    smtp_cmd($fp, 'AUTH LOGIN');
    smtp_expect($fp, 334);
    smtp_cmd($fp, base64_encode($smtpLogin));
    smtp_expect($fp, 334);
    smtp_cmd($fp, base64_encode($pass));
    $auth = smtp_expect($fp, 235);
    smtp_cmd($fp, 'MAIL FROM:<' . $from . '>');
    smtp_expect($fp, 250);
    smtp_cmd($fp, 'RCPT TO:<' . $to . '>');
    $rcpt = smtp_expect($fp, [250, 251]);
    smtp_cmd($fp, 'DATA');
    smtp_expect($fp, 354);
    $headers = 'From: SchoolSafe <' . $from . ">\r\n"
        . 'To: <' . $to . ">\r\n"
        . 'Subject: =?UTF-8?B?' . base64_encode($mail['subject']) . "?=\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n";
    fwrite($fp, $headers . "\r\n" . str_replace("\n.", "\n..", $mail['html']) . "\r\n.\r\n");
    $dataLine = '';
    while (($l = fgets($fp, 1024)) !== false) { $dataLine = $l; if (preg_match('/^\\d{3} /', $l)) break; }
    $data = (int) substr(trim($dataLine), 0, 3);
    if ($data !== 250) throw new RuntimeException('SMTP_UNEXPECTED_' . $data);
    $dataReply = preg_replace('/[A-Za-z0-9_\\-\\.]{20,}/', '***', trim($dataLine));
    smtp_cmd($fp, 'QUIT');
    fclose($fp);
    echo "LOGIN=$smtpLogin\n";
    echo "GREET=$greet AUTH=$auth RCPT=$rcpt DATA=$data\n";
    echo 'DATA_REPLY=' . $dataReply . "\n";
    echo "SMTP_ACCEPTED=oui DESTINATAIRE=$to\n";
} catch (RuntimeException $e) {
    echo 'SMTP_FAIL=' . $e->getMessage() . "\n";
    exit(1);
}
