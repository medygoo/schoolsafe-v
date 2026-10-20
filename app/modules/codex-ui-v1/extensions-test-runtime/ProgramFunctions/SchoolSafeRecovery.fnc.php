<?php
/**
 * SchoolSafe L4 — récupération des comptes : moteur partagé.
 *
 * AUCUNE logique d'authentification reconstruite : ce fichier factorise ce
 * que PasswordReset.php natif faisait en ligne (émission du lien, envoi du
 * courriel) pour le réutiliser depuis le parcours /compte/recuperation, et
 * ajoute l'invalidation des anciennes sessions (époque) + les défis OTP du
 * canal simulé (sans SMSGate, sans SIM réelle).
 *
 * - Invalidation des sessions : AUCUN doublon — on réutilise l'époque L1
 *   (schoolsafe_account_state.SESSION_EPOCH) via SchoolSafeAccountRevoke(),
 *   déjà évincée par SchoolSafeAccountSessionGuard() (Warehouse).
 * - Courriel : Brevo d'abord (si configuré), repli SendEmail natif ; panne
 *   tracée serveur, message utilisateur toujours générique.
 * - OTP : code à 6 chiffres, hash SHA-256 seul en base, 10 min, 5 essais,
 *   usage unique. code_plain n'est écrit QUE si config
 *   RECOVERY_SMS_SIMULATE='Y' (piles isolées — JAMAIS en production).
 */

/**
 * Envoyer le courriel de récupération : Brevo d'abord (si configuré),
 * repli SendEmail natif. Panne tracée serveur ; l'appelant affiche
 * toujours le message générique (anti-énumération).
 *
 * @return array ['sent'=>bool,'via'=>string]
 */
function SchoolSafeRecoverySendMail( $to, $subject, $html_message )
{
    require_once 'ProgramFunctions/SendEmail.fnc.php';
    $via = 'mail';
    $sent = false;
    if ( function_exists( 'SchoolSafeBrevoSend' ) )
    {
        try
        {
            require_once 'ProgramFunctions/SchoolSafeBrevo.fnc.php';
            $out = SchoolSafeBrevoSend( $to, [
                'from' => 'ne-pas-repondre@schoolsafe.local',
                'subject' => $subject,
                'html' => $html_message,
                'text' => strip_tags( str_replace( [ '<br />', '<br>' ], "\n", $html_message ) ),
            ] );
            if ( ! empty( $out['accepted'] ) )
            {
                $sent = true;
                $via = 'brevo';
            }
        }
        catch ( Exception $e )
        {
            error_log( 'SchoolSafe recovery brevo FAILED: ' .
                substr( $e->getMessage(), 0, 120 ) );
        }
    }
    if ( ! $sent )
    {
        $sent = (bool) SendEmail( $to, $subject, $html_message );
    }
    if ( ! $sent )
    {
        error_log( 'SchoolSafe recovery email FAILED to=' . $to );
    }
    return [ 'sent' => $sent, 'via' => $via ];
}

/**
 * Limitation des tentatives sur les LIENS (vérification du hash h) :
 * 30 vérifications / 10 min / IP (le hash est indevinable ; ce garde ne
 * vise que le balayage automatisé). Journal access_log, STATUS 'R'.
 */
function SchoolSafeRecoveryLinkAttempts( $ip )
{
    $n = DBGetOne( "SELECT COUNT(*) FROM access_log
        WHERE CREATED_AT > (CURRENT_TIMESTAMP - INTERVAL '10 minute')
        AND IP_ADDRESS='" . DBEscapeString( $ip ) . "'
        AND STATUS='R'" );
    return (int) $n;
}

function SchoolSafeRecoveryLinkLog( $ip )
{
    DBQuery( "INSERT INTO access_log(SYEAR, USERNAME, PROFILE, IP_ADDRESS, USER_AGENT, STATUS)"
        . " VALUES('" . Config( 'SYEAR' ) . "','(reset-link)','none','"
        . DBEscapeString( $ip ) . "','"
        . DBEscapeString( $_SERVER['HTTP_USER_AGENT'] ) . "','R')" );
}

function SchoolSafeRecoveryClientIp()
{
    return ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] )
        && filter_var( $_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP ) ?
        $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'] );
}

/**
 * Moteur d'émission du lien natif (extrait de PasswordReset.php, inchangé
 * dans sa cryptographie) : hash = crypt(id.nom complet.mdp.email.échéance),
 * échéance +2 h mémorisée dans LAST_LOGIN, mise à jour SEULEMENT si le
 * courriel est parti. $mailer callable($to,$subject,$html) => bool ;
 * défaut = Brevo-d'abord ci-dessus. Retourne le lien (usage serveur
 * uniquement : redirection post-OTP vers le formulaire natif).
 *
 * @param bool $deliver Vrai = envoyer le courriel ; faux = remise deja
 *   prouvee par un OTP verifie (pas d'envoi, echeance posee quand meme).
 * @return array ['sent'=>bool,'link'=>string]
 */
function SchoolSafeRecoveryIssueReset( $user_id, $user_type, $email, $mailer = null, $deliver = true )
{
    global $DatabaseType;
    $out = [ 'sent' => false, 'link' => '' ];
    if ( (int) $user_id < 1 || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) )
    {
        return $out;
    }
    if ( $user_type === 'staff' )
    {
        $row_RET = DBGet( "SELECT USERNAME, PASSWORD,"
            . DisplayNameSQL() . " AS FULL_NAME
            FROM staff
            WHERE STAFF_ID='" . (int) $user_id . "'
            AND SYEAR='" . Config( 'SYEAR' ) . "'" );
        $username = $row_RET[1]['USERNAME'];
        $name = $row_RET[1]['FULL_NAME'];
        $password = $row_RET[1]['PASSWORD'];
    }
    else
    {
        $row_RET = DBGet( "SELECT USERNAME,PASSWORD,"
            . DisplayNameSQL( 's' ) . " AS FULL_NAME
            FROM students s,student_enrollment ssm
            WHERE s.STUDENT_ID='" . (int) $user_id . "'
            AND s.STUDENT_ID=ssm.STUDENT_ID
            AND ssm.SYEAR='" . Config( 'SYEAR' ) . "'
            AND ('" . DBDate() . "'>=ssm.START_DATE
            AND (ssm.END_DATE IS NULL
                OR '" . DBDate() . "'<=ssm.END_DATE ) )" );
        $username = $row_RET[1]['USERNAME'];
        $name = $row_RET[1]['FULL_NAME'];
        $password = $row_RET[1]['PASSWORD'];
    }
    if ( ! $username ) return $out;
    $last_login = DBGetOne( "SELECT
        CAST(CURRENT_TIMESTAMP + INTERVAL " . ( $DatabaseType === 'mysql' ? '2 hour' : "'2 hour'" ) . " AS char(19)) AS LAST_LOGIN" );
    $hash = encrypt_password( $user_id . $username . $name . $password . $email . $last_login );
    // Remise au formulaire NATIF : le hash h n'est compris que par
    // PasswordReset.php (jamais le script appelant, ex. le hub).
    $link = RosarioURL( 'dir' ) . 'PasswordReset.php?h=' . $hash;
    $message = _( 'Please visit the following link to reset your password' ) . ':<br />
        <a href="' . URLEscape( $link ) . '">' . $link . '</a>
        <br />' . _( 'Username' ) . ': ' . $username . '
        <br /><br />' .
        _( 'Please permanently delete this email once you are done.' );
    $sent = false;
    if ( ! $deliver )
    {
        // Remise déjà prouvée (OTP vérifié) : pas d'envoi, l'échéance est
        // quand même posée et le lien reste à usage unique via le natif.
        $sent = true;
    }
    elseif ( is_callable( $mailer ) )
    {
        $sent = (bool) $mailer( $email, _( 'Password Reset' ), $message );
    }
    else
    {
        $sent = SchoolSafeRecoverySendMail( $email, _( 'Password Reset' ), $message )['sent'];
    }
    if ( ! $sent ) return $out;
    if ( $user_type === 'staff' )
    {
        DBQuery( "UPDATE staff SET LAST_LOGIN='" . $last_login . "'
            WHERE STAFF_ID='" . (int) $user_id . "'" );
    }
    else
    {
        DBQuery( "UPDATE students SET LAST_LOGIN='" . $last_login . "'
            WHERE STUDENT_ID='" . (int) $user_id . "'" );
    }
    $out['sent'] = true;
    $out['link'] = $link;
    return $out;
}

/**
 * Canal simulé actif ? Config RECOVERY_SMS_SIMULATE='Y' (recette isolée
 * UNIQUEMENT — jamais en production : aucun SMS ne part sans SMSGate).
 */
function SchoolSafeRecoveryOtpSimulate()
{
    return Config( 'RECOVERY_SMS_SIMULATE' ) === 'Y';
}

/**
 * Émettre un défi OTP (6 chiffres) pour un compte personnel identifié par
 * son identifiant + téléphone vérifié. Les défis actifs précédents sont
 * consommés (un seul défi vivant). $rng callable() => string, tests.
 *
 * @return int|false Identifiant du défi, faux si compte introuvable.
 */
function SchoolSafeRecoveryOtpIssue( $username, $phone, $rng = null, $ip = '' )
{
    require_once 'ProgramFunctions/SchoolSafeIdentity.fnc.php';
    $username = trim( (string) $username );
    if ( $username === '' ) return false;
    $row_RET = DBGet( "SELECT STAFF_ID,CUSTOM_200000001 AS PHONE
        FROM staff
        WHERE USERNAME='" . DBEscapeString( $username ) . "'
        AND SYEAR='" . Config( 'SYEAR' ) . "'" );
    if ( ! $row_RET ) return false;
    $registered = SchoolSafeIdentityNormalizePhoneCD( (string) $row_RET[1]['PHONE'] );
    $given = SchoolSafeIdentityNormalizePhoneCD( (string) $phone );
    if ( $registered === '' || $given === '' || ! hash_equals( $registered, $given ) )
    {
        return false;
    }
    $user_id = (int) $row_RET[1]['STAFF_ID'];
    DBQuery( "UPDATE schoolsafe_recovery_otp SET consumed_at=CURRENT_TIMESTAMP
        WHERE user_type='staff' AND user_id='" . $user_id . "'
        AND channel='sms' AND consumed_at IS NULL" );
    $code = is_callable( $rng )
        ? (string) $rng()
        : str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
    if ( ! preg_match( '/^[0-9]{6}$/', $code ) ) return false;
    $simulate = SchoolSafeRecoveryOtpSimulate();
    $id = DBInsert( 'schoolsafe_recovery_otp', [
        'user_type' => 'staff',
        'user_id' => $user_id,
        'channel' => 'sms',
        'code_hash' => hash( 'sha256', $code ),
        'code_plain' => $simulate ? $code : null,
        'expires_at' => date( 'Y-m-d H:i:s', time() + 600 ),
        'created_ip' => substr( (string) $ip, 0, 64 ),
    ], 'id' );
    return $id ? (int) $id : false;
}

/**
 * Vérifier un code OTP : compare en temps constant, comptabilise l'essai,
 * verrouille à max_attempts (défi consommé), usage unique.
 */
function SchoolSafeRecoveryOtpVerify( $otp_id, $code )
{
    if ( (int) $otp_id < 1 ) return false;
    $row_RET = DBGet( "SELECT code_hash,attempts,max_attempts,expires_at,consumed_at
        FROM schoolsafe_recovery_otp WHERE id='" . (int) $otp_id . "'" );
    if ( ! $row_RET || $row_RET[1]['CONSUMED_AT'] !== null ) return false;
    if ( strtotime( (string) $row_RET[1]['EXPIRES_AT'] ) < time() ) return false;
    if ( (int) $row_RET[1]['ATTEMPTS'] >= (int) $row_RET[1]['MAX_ATTEMPTS'] )
    {
        DBQuery( "UPDATE schoolsafe_recovery_otp SET consumed_at=CURRENT_TIMESTAMP
            WHERE id='" . (int) $otp_id . "'" );
        return false;
    }
    DBQuery( "UPDATE schoolsafe_recovery_otp SET attempts=attempts+1
        WHERE id='" . (int) $otp_id . "'" );
    if ( ! hash_equals( (string) $row_RET[1]['CODE_HASH'], hash( 'sha256', (string) $code ) ) )
    {
        return false;
    }
    DBQuery( "UPDATE schoolsafe_recovery_otp SET consumed_at=CURRENT_TIMESTAMP
        WHERE id='" . (int) $otp_id . "'" );
    return true;
}

/**
 * Relire le code simulé (recette isolée UNIQUEMENT : config + colonne
 * renseignée seulement en simulation). Jamais affiché à l'écran.
 */
function SchoolSafeRecoveryOtpReadSimulated( $otp_id )
{
    if ( ! SchoolSafeRecoveryOtpSimulate() ) return null;
    return DBGetOne( "SELECT code_plain FROM schoolsafe_recovery_otp
        WHERE id='" . (int) $otp_id . "'" );
}

/**
 * Contrat d-envoi SMS : parcours L4 simule, sinon routeur central
 * (lignes PRODELI, quotas, idempotence — voir SchoolSafeSms.fnc.php).
 * - simulation (recette isolée, config RECOVERY_SMS_SIMULATE='Y') : le code
 *   est déjà en base (outbox), rien à transmettre — succès simulé ;
 * - sinon : échec explicite NO_SMS_PROVIDER (jamais un faux succès, jamais
 *   de SMS sans SMSGate ni SIM réelle).
 *
 * @return array ['sent'=>bool,'via'=>string]
 */
function SchoolSafeRecoverySmsSend( $phone_e164, $text )
{
    // Parcours L4 : defi OTP deja en base, rien a transmettre.
    if ( SchoolSafeRecoveryOtpSimulate() )
    {
        return [ 'sent' => true, 'via' => 'simulated' ];
    }

    // Lignes SMSGate : routeur central (quotas, idempotence, statuts).
    require_once 'ProgramFunctions/SchoolSafeSms.fnc.php';

    try
    {
        $out = SchoolSafeSmsSend( $phone_e164, $text,
            [ 'purpose' => 'otp', 'actor' => 'recovery-otp' ] );
    }
    catch ( Exception $e )
    {
        throw new RuntimeException( 'NO_SMS_PROVIDER' );
    }

    if ( in_array( $out['status'], [ 'accepted', 'sent', 'delivered' ], true ) )
    {
        return [ 'sent' => true, 'via' => (string) ( $out['via'] ?? 'sms' ) ];
    }

    error_log( 'SchoolSafe OTP sms status=' . (string) ( $out['status'] ?? '?' )
        . ' error=' . (string) ( $out['error'] ?? '?' ) );

    throw new RuntimeException( 'NO_SMS_PROVIDER' );
}

/**
 * Limitation du canal OTP : 10 émissions / 10 min / IP (journal access_log,
 * STATUS 'O'). Les essais de codes sont bornés par le schéma (5 max).
 */
function SchoolSafeRecoveryOtpAttempts( $ip )
{
    $n = DBGetOne( "SELECT COUNT(*) FROM access_log
        WHERE CREATED_AT > (CURRENT_TIMESTAMP - INTERVAL '10 minute')
        AND IP_ADDRESS='" . DBEscapeString( $ip ) . "'
        AND STATUS='O'" );
    return (int) $n;
}

function SchoolSafeRecoveryOtpLog( $ip )
{
    DBQuery( "INSERT INTO access_log(SYEAR, USERNAME, PROFILE, IP_ADDRESS, USER_AGENT, STATUS)"
        . " VALUES('" . Config( 'SYEAR' ) . "','(recovery-otp)','none','"
        . DBEscapeString( $ip ) . "','"
        . DBEscapeString( $_SERVER['HTTP_USER_AGENT'] ) . "','O')" );
}

/**
 * Limitation des DEMANDES par courriel : 5 / 10 min / IP (journal
 * access_log, STATUS 'P'). Au-delà : refus silencieux avec le message
 * générique (aucune énumération de comptes, même en cas de limitation).
 */
function SchoolSafeRecoveryRequestAttempts( $ip )
{
    $n = DBGetOne( "SELECT COUNT(*) FROM access_log
        WHERE CREATED_AT > (CURRENT_TIMESTAMP - INTERVAL '10 minute')
        AND IP_ADDRESS='" . DBEscapeString( $ip ) . "'
        AND STATUS='P'" );
    return (int) $n;
}

function SchoolSafeRecoveryRequestLog( $ip )
{
    DBQuery( "INSERT INTO access_log(SYEAR, USERNAME, PROFILE, IP_ADDRESS, USER_AGENT, STATUS)"
        . " VALUES('" . Config( 'SYEAR' ) . "','(reset)','none','"
        . DBEscapeString( $ip ) . "','"
        . DBEscapeString( $_SERVER['HTTP_USER_AGENT'] ) . "','P')" );
}
