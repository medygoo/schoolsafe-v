<?php
/**
 * SchoolSafe L4 — parcours officiel de récupération des comptes.
 *
 * Route officielle (rewrite .htaccess) : /compte/recuperation
 *
 * AUCUN moteur parallèle : le courriel passe par PasswordReset.php natif
 * (formulaire posté tel quel, même moteur, Brevo-d'abord depuis L4) ; le
 * téléphone passe par un défi OTP (simulation uniquement en recette,
 * routeur SMS existant sinon) puis est REMIS au formulaire natif via un lien
 * à usage unique émis par le moteur partagé — la pose du mot de passe,
 * l'expiration (+2 h), l'usage unique et la révocation des sessions restent
 * 100 % natifs. Cette page ne voit jamais un mot de passe en clair.
 */

header( 'Cache-Control: no-store' );
header( 'X-Content-Type-Options: nosniff' );

chdir( __DIR__ );

require_once __DIR__ . '/Warehouse.php';
require_once __DIR__ . '/ProgramFunctions/SchoolSafeRecovery.fnc.php';

$message = '';
$error = '';
$step_otp_code = false;

$token = (string) ( $_SESSION['token'] ?? '' );

function SchoolSafeRecoveryHubTokenOk()
{
    return is_string( $_POST['token'] ?? null )
        && $_POST['token'] !== ''
        && hash_equals( (string) ( $_SESSION['token'] ?? '' ), $_POST['token'] );
}

if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) === 'POST' )
{
    if ( ! SchoolSafeRecoveryHubTokenOk() )
    {
        $error = 'Requête expirée. Rechargez la page.';
    }
    elseif ( ( $_POST['l4_action'] ?? '' ) === 'otp_demande' )
    {
        $ip = SchoolSafeRecoveryClientIp();
        if ( SchoolSafeRecoveryOtpAttempts( $ip ) >= 10 )
        {
            $error = 'Trop de demandes. Réessayez dans quelques minutes.';
        }
        else
        {
            SchoolSafeRecoveryOtpLog( $ip );
            unset( $_SESSION['SS_OTP'] );
            $otp_id = SchoolSafeRecoveryOtpRequest(
                (string) ( $_POST['username'] ?? '' ),
                (string) ( $_POST['phone'] ?? '' ),
                $ip
            );
            if ( $otp_id ) $_SESSION['SS_OTP'] = (int) $otp_id;
            // Same message and form for unknown accounts, wrong phones and
            // unavailable providers. No challenge/secret is exposed to the browser.
            $step_otp_code = true;
            $message = 'Si ces informations correspondent à un compte et que le canal est disponible, un code à 6 chiffres a été demandé. Saisissez le code reçu ci-dessous (10 minutes, 5 essais). Sinon, utilisez le courriel ou contactez votre établissement.';
        }
    }
    elseif ( ( $_POST['l4_action'] ?? '' ) === 'otp_verifie' )
    {
        $otp_id = (int) ( $_SESSION['SS_OTP'] ?? 0 );
        $step_otp_code = true;
        $code = trim( (string) ( $_POST['code'] ?? '' ) );
        if ( $otp_id < 1 || ! preg_match( '/^[0-9]{6}$/', $code ) )
        {
            $error = 'Code incorrect ou expiré. Recommencez la demande.';
        }
        elseif ( ! SchoolSafeRecoveryOtpVerify( $otp_id, $code ) )
        {
            $error = 'Code incorrect ou expiré. Recommencez la demande.';
        }
        else
        {
            unset( $_SESSION['SS_OTP'] );
            // UserSyear() est vide hors session authentifiée : l'année
            // courante canonique est Config('SYEAR') (même source que
            // l'émission du défi, sinon la remise échoue toujours ici).
            $row = DBGet( "SELECT o.user_id,s.USERNAME,s.EMAIL
                FROM schoolsafe_recovery_otp o JOIN staff s ON s.STAFF_ID=o.user_id
                WHERE o.id='" . $otp_id . "' AND s.SYEAR='" . (int) Config( 'SYEAR' ) . "'" );
            $mint = [ 'sent' => false, 'link' => '' ];
            if ( $row )
            {
                $mint = SchoolSafeRecoveryIssueReset(
                    (int) $row[1]['USER_ID'], 'staff', (string) $row[1]['EMAIL'],
                    null, false );
                error_log( 'SchoolSafe OTP handoff minted for staff_id=' . (int) $row[1]['USER_ID'] );
            }
            if ( $mint['sent'] && $mint['link'] !== '' )
            {
                header( 'Location: ' . $mint['link'] );
                exit;
            }
            $error = 'Ce compte n’a aucun courriel associé : contactez l’administration de votre établissement.';
        }
    }
}

$simulate = SchoolSafeRecoveryOtpSimulate();
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Récupération du compte | SchoolSafe</title>
<link rel="stylesheet" href="/assets/css/schoolsafe-recovery.css?v=1">
</head>
<body>
<main class="ss-recovery-shell">
<header class="ss-recovery-header">
<a class="ss-recovery-brand" href="/index.php" aria-label="SchoolSafe — connexion"><span class="ss-recovery-emblem"><img src="/schoolsafe-logo.png" alt="" width="48" height="52"></span><span><strong>SchoolSafe</strong><small>Votre école, en confiance.</small></span></a>
<a class="ss-recovery-return" href="/index.php">← Retour à la connexion</a>
</header>
<h1>Récupération du compte</h1>
<p class="ss-recovery-intro">Retrouvez l’accès à votre espace avec le courriel ou le téléphone associé à votre compte.</p>
<?php if ( $simulate ) : ?>
<p class="sim" role="note">Canal SMS simulé (recette isolée) : aucun SMS réel ne part.</p>
<?php endif; ?>
<?php if ( $message !== '' ) : ?>
<p class="ok" role="status"><?php echo htmlspecialchars( $message, ENT_QUOTES, 'UTF-8' ); ?></p>
<?php endif; ?>
<?php if ( $error !== '' ) : ?>
<p class="ko" role="alert"><?php echo htmlspecialchars( $error, ENT_QUOTES, 'UTF-8' ); ?></p>
<?php endif; ?>
<div class="ss-recovery-methods">
<section class="card" aria-labelledby="t-mail">
<h2 id="t-mail">Par courriel</h2>
<p><small>Le lien expire en 2 heures, usage unique, sans énumération de comptes.</small></p>
<form method="post" action="/PasswordReset.php">
<label>Courriel <input type="email" name="email" maxlength="255" autocomplete="email" required></label>
<p><button type="submit">Recevoir le lien</button></p>
</form>
</section>
<section class="card" aria-labelledby="t-sms">
<h2 id="t-sms">Par SMS<?php echo $simulate ? ' (canal simulé)' : ''; ?></h2>
<p><small>Utilisez l’identifiant et le téléphone associés à votre compte.</small></p>
<?php if ( ! $step_otp_code ) : ?>
<form method="post" action="/compte/recuperation">
<input type="hidden" name="token" value="<?php echo htmlspecialchars( $token, ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="l4_action" value="otp_demande">
<label>Identifiant <input name="username" maxlength="100" autocomplete="username" required></label>
<label>Téléphone <input name="phone" placeholder="+243…" autocomplete="off" required></label>
<p><button type="submit">Préparer un code</button></p>
</form>
<?php else : ?>
<form method="post" action="/compte/recuperation">
<input type="hidden" name="token" value="<?php echo htmlspecialchars( $token, ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="l4_action" value="otp_verifie">
<label>Code à 6 chiffres <input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label>
<p><button type="submit">Vérifier et continuer</button></p>
</form>
<?php endif; ?>
</section>
</div>
</main>
</body>
</html>
