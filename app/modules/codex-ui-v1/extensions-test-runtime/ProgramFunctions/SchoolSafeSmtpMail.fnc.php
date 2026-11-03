<?php
// Notifications e-mail par le SMTP Brevo déjà configuré (plugin Email_SMTP),
// quand aucune clé API Brevo n'est installée. Mêmes réglages que le plugin natif :
// hôte/port/identifiant/expéditeur dans la configuration Rosario de l'école,
// mot de passe uniquement dans l'environnement (SCHOOLSAFE_SMTP_PASSWORD).
// STARTTLS obligatoire, jamais de repli non chiffré.

function SchoolSafeSmtpConfig($conn, int $schoolId): ?array
{
    $password = (string) getenv('SCHOOLSAFE_SMTP_PASSWORD');
    if ($password === '') return null;
    $rows = pg_query_params($conn, "SELECT school_id, title, config_value FROM config
        WHERE title LIKE 'EMAIL_SMTP_%' AND school_id IN (0, $1) ORDER BY school_id", [$schoolId]);
    if ($rows === false) return null;
    $values = [];
    while ($row = pg_fetch_assoc($rows)) {
        // La valeur de l'école l'emporte sur la valeur générale (school_id 0).
        if ((string) $row['config_value'] !== '' || !isset($values[$row['title']])) $values[$row['title']] = (string) $row['config_value'];
    }
    $host = trim($values['EMAIL_SMTP_HOST'] ?? '');
    $from = trim($values['EMAIL_SMTP_FROM'] ?? '');
    if ($host === '' || !filter_var($from, FILTER_VALIDATE_EMAIL) || ($values['EMAIL_SMTP_PAUSE'] ?? '') === '1') return null;
    return ['host' => $host, 'port' => (int) ($values['EMAIL_SMTP_PORT'] ?? 587) ?: 587,
        'encryption' => ($values['EMAIL_SMTP_ENCRYPTION'] ?? '') === 'ssl' ? 'ssl' : 'tls',
        'username' => trim($values['EMAIL_SMTP_USERNAME'] ?? ''), 'password' => $password,
        'from' => $from, 'from_name' => trim($values['EMAIL_SMTP_FROM_NAME'] ?? '') ?: 'SchoolSafe'];
}

// Ouvre la connexion AVANT tout envoi : un serveur injoignable reste un échec sûr (rien n'est parti).
function SchoolSafeSmtpOpen(array $config)
{
    require_once __DIR__ . '/../classes/PHPMailer/PHPMailer/Exception.php';
    require_once __DIR__ . '/../classes/PHPMailer/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/../classes/PHPMailer/PHPMailer/SMTP.php';
    $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = $config['host'];
    $mailer->Port = $config['port'];
    $mailer->SMTPSecure = $config['encryption'];
    $mailer->SMTPAutoTLS = true;
    $mailer->SMTPAuth = $config['username'] !== '';
    $mailer->Username = $config['username'];
    $mailer->Password = $config['password'];
    $mailer->Timeout = 15;
    $mailer->CharSet = 'UTF-8';
    $mailer->SMTPDebug = 0;
    try {
        if (!$mailer->smtpConnect()) throw new RuntimeException('SMTP_UNAVAILABLE');
    } catch (Throwable $error) {
        throw new RuntimeException('SMTP_UNAVAILABLE');
    }
    return $mailer;
}

function SchoolSafeSmtpSend($mailer, string $to, array $mail, array $config): array
{
    $mailer->setFrom($config['from'], $config['from_name']);
    $mailer->addAddress($to);
    $mailer->Subject = (string) ($mail['subject'] ?? '');
    $mailer->isHTML(true);
    $mailer->Body = (string) ($mail['html'] ?? '');
    $mailer->AltBody = (string) ($mail['text'] ?? '');
    $mailer->send();
    $id = (string) $mailer->getLastMessageID();
    $mailer->smtpClose();
    if ($id === '') throw new RuntimeException('ACCEPTANCE_UNKNOWN');
    return ['accepted' => true, 'provider_msg_id' => 'smtp:' . $id];
}
