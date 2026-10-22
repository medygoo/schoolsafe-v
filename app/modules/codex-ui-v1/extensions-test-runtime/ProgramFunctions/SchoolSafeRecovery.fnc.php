<?php
/**
 * SchoolSafe L4 — récupération des comptes : moteur partagé.
 *
 * AUCUNE logique d'authentification reconstruite : ce fichier factorise ce
 * que PasswordReset.php natif faisait en ligne (émission du lien, envoi du
 * courriel) pour le réutiliser depuis le parcours /compte/recuperation, et
 * ajoute l'invalidation des anciennes sessions (époque) + les défis OTP
 * remis au routeur SMS existant (simulation uniquement en recette).
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
        // Recovery must not bypass managed identity or the initial-access guard.
        if ( ! SchoolSafeRecoveryStaffEligible( (int) $user_id ) ) return $out;
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
 * Consume a previously matched native staff reset atomically (PostgreSQL).
 * The caller must have matched the native link. Its password/deadline snapshot
 * is checked again under the staff row lock: only one concurrent submission
 * can change all school-year passwords and revoke sessions. No new reset token.
 * $revoke is an optional internal test callback; never supplied by the request.
 */
function SchoolSafeRecoveryCompleteStaffReset( $staff_id, $expected_password, $expected_deadline, $new_password, $revoke = null )
{
    global $db_connection;
    if ( (int) $staff_id < 1 || ! is_string( $expected_password )
        || ! is_string( $expected_deadline ) || ! is_string( $new_password ) || $new_password === ''
        || pg_transaction_status( $db_connection ) !== PGSQL_TRANSACTION_IDLE ) return false;
    try
    {
        $password = encrypt_password( $new_password );
        SchoolSafeRecoveryQuery( 'BEGIN' );
        SchoolSafeRecoveryQuery( "SET LOCAL lock_timeout='5s'" );
        $rows = DBGet( SchoolSafeRecoveryQuery( "SELECT USERNAME,PASSWORD,LAST_LOGIN FROM staff
            WHERE STAFF_ID='" . (int) $staff_id . "'
            AND SYEAR='" . (int) Config( 'SYEAR' ) . "' FOR UPDATE" ) );
        if ( ! $rows || ! hash_equals( $expected_password, (string) $rows[1]['PASSWORD'] )
            || ! hash_equals( $expected_deadline, (string) $rows[1]['LAST_LOGIN'] )
            || ! SchoolSafeRecoveryStaffEligible( (int) $staff_id ) )
        {
            SchoolSafeRecoveryQuery( 'ROLLBACK' );
            return false;
        }
        // Read the clock after acquiring the lock, not at transaction start.
        $valid = DBGet( SchoolSafeRecoveryQuery( "SELECT 1 FROM staff
            WHERE STAFF_ID='" . (int) $staff_id . "' AND LAST_LOGIN>clock_timestamp()" ) );
        if ( ! $valid )
        {
            SchoolSafeRecoveryQuery( 'ROLLBACK' );
            return false;
        }
        $username = (string) $rows[1]['USERNAME'];
        SchoolSafeRecoveryQuery( "UPDATE staff SET PASSWORD='" . DBEscapeString( $password ) . "',
            LAST_LOGIN=CURRENT_TIMESTAMP WHERE USERNAME='" . DBEscapeString( $username ) . "'" );
        $revoked = is_callable( $revoke ) ? $revoke( $username )
            : ( function_exists( 'SchoolSafeAccountRevoke' ) && SchoolSafeAccountRevoke( $username, 0 ) );
        if ( ! $revoked ) throw new RuntimeException( 'Recovery revocation unavailable' );
        SchoolSafeRecoveryQuery( 'COMMIT' );
        return true;
    }
    catch ( Throwable $error )
    {
        @db_query( 'ROLLBACK', false );
        if ( function_exists( 'SchoolSafeAccountForget' ) ) SchoolSafeAccountForget();
        return false;
    }
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
    global $db_connection;
    require_once 'ProgramFunctions/SchoolSafeIdentity.fnc.php';
    $username = trim( (string) $username );
    $code = is_callable( $rng ) ? (string) $rng()
        : str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
    if ( $username === '' || ! preg_match( '/^[0-9]{6}$/D', $code )
        || pg_transaction_status( $db_connection ) !== PGSQL_TRANSACTION_IDLE ) return false;
    try
    {
        SchoolSafeRecoveryQuery( 'BEGIN' );
        SchoolSafeRecoveryQuery( "SET LOCAL lock_timeout='5s'" );
        $rows = DBGet( SchoolSafeRecoveryQuery( "SELECT STAFF_ID,CUSTOM_200000001 AS PHONE
            FROM staff WHERE USERNAME='" . DBEscapeString( $username ) . "'
            AND SYEAR='" . (int) Config( 'SYEAR' ) . "' FOR UPDATE" ) );
        $registered = $rows ? SchoolSafeIdentityNormalizePhoneCD( (string) $rows[1]['PHONE'] ) : '';
        $given = SchoolSafeIdentityNormalizePhoneCD( (string) $phone );
        $user_id = $rows ? (int) $rows[1]['STAFF_ID'] : 0;
        if ( count( $rows ) !== 1 || $registered === '' || $given === ''
            || ! hash_equals( $registered, $given ) || ! SchoolSafeRecoveryStaffEligible( $user_id ) )
        {
            SchoolSafeRecoveryQuery( 'ROLLBACK' );
            return false;
        }
        SchoolSafeRecoveryQuery( "UPDATE schoolsafe_recovery_otp SET consumed_at=CURRENT_TIMESTAMP
            WHERE user_type='staff' AND user_id='" . $user_id . "'
            AND channel='sms' AND consumed_at IS NULL" );
        $plain = SchoolSafeRecoveryOtpSimulate() ? "'" . $code . "'" : 'NULL';
        $row = DBGet( SchoolSafeRecoveryQuery( "INSERT INTO schoolsafe_recovery_otp
            (user_type,user_id,channel,code_hash,code_plain,expires_at,created_ip)
            VALUES('staff','" . $user_id . "','sms','" . hash( 'sha256', $code ) . "',"
            . $plain . ",CURRENT_TIMESTAMP+INTERVAL '10 minute','"
            . DBEscapeString( substr( (string) $ip, 0, 64 ) ) . "') RETURNING id" ) );
        SchoolSafeRecoveryQuery( 'COMMIT' );
        return (int) $row[1]['ID'];
    }
    catch ( Throwable $error )
    {
        @db_query( 'ROLLBACK', false );
        return false;
    }
}

/** Sanitized SQL failure: never render a public database/secret error page. */
function SchoolSafeRecoveryQuery( $sql )
{
    $result = @db_query( $sql, false );
    if ( ! $result ) throw new RuntimeException( 'Recovery operation unavailable' );
    return $result;
}

/** Native completed accounts only; first access and managed identities stay separate. */
function SchoolSafeRecoveryStaffEligible( $staff_id )
{
    $username = DBGetOne( "SELECT USERNAME FROM staff WHERE STAFF_ID='" . (int) $staff_id
        . "' AND SYEAR='" . (int) Config( 'SYEAR' ) . "'" );
    if ( ! $username ) return false;
    if ( DBGetOne( "SELECT to_regclass('schoolsafe_identity_links')" )
        && DBGetOne( "SELECT 1 FROM schoolsafe_identity_links l JOIN staff s ON s.staff_id=l.staff_id
            WHERE LOWER(s.username)=LOWER('" . DBEscapeString( $username ) . "') LIMIT 1" ) ) return false;
    if ( DBGetOne( "SELECT to_regclass('schoolsafe_native_onboarding')" )
        && DBGetOne( "SELECT 1 FROM schoolsafe_native_onboarding WHERE staff_id='" . (int) $staff_id
            . "' AND syear='" . (int) Config( 'SYEAR' ) . "' AND completed_at IS NULL" ) ) return false;
    return true;
}

/** Issue first, send the same code to the verified recipient, invalidate on failure. */
function SchoolSafeRecoveryOtpRequest( $username, $phone, $ip = '', $sender = null, $rng = null )
{
    $id = false;
    try
    {
        $code = is_callable( $rng ) ? (string) $rng()
            : str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
        $id = SchoolSafeRecoveryOtpIssue( $username, $phone, static function() use ( $code ) { return $code; }, $ip );
        if ( ! $id ) return false;
        $recipient = SchoolSafeIdentityNormalizePhoneCD( (string) $phone );
        $text = 'SchoolSafe : code de recuperation ' . $code . '. Valable 10 minutes. Ne le partagez jamais.';
        $options = [ 'dedup_key' => 'recovery-otp:' . (int) $id ];
        $sent = is_callable( $sender ) ? $sender( $recipient, $text, $options )
            : SchoolSafeRecoverySmsSend( $recipient, $text, $options );
        if ( ! empty( $sent['sent'] ) ) return (int) $id;
    }
    catch ( Throwable $error )
    {
        // Generic failure only; never log the OTP, transport credentials or payload.
    }
    if ( $id )
    {
        try { SchoolSafeRecoveryQuery( "UPDATE schoolsafe_recovery_otp SET consumed_at=CURRENT_TIMESTAMP
            WHERE id='" . (int) $id . "' AND consumed_at IS NULL" ); }
        catch ( Throwable $error ) { /* Fail closed at the caller: no challenge handed off. */ }
    }
    return false;
}

/** One atomic update: expiry, attempts and consumption also hold under concurrency. */
function SchoolSafeRecoveryOtpVerify( $otp_id, $code )
{
    if ( (int) $otp_id < 1 || ! is_string( $code ) || ! preg_match( '/^[0-9]{6}$/D', $code ) ) return false;
    $hash = hash( 'sha256', $code );
    try
    {
        $rows = DBGet( SchoolSafeRecoveryQuery( "UPDATE schoolsafe_recovery_otp
            SET attempts=attempts+1,
                consumed_at=CASE WHEN code_hash='" . $hash . "' OR attempts+1>=max_attempts
                    THEN CURRENT_TIMESTAMP ELSE consumed_at END
            WHERE id='" . (int) $otp_id . "' AND consumed_at IS NULL
                AND expires_at>CURRENT_TIMESTAMP AND attempts<max_attempts
            RETURNING CASE WHEN code_hash='" . $hash . "' THEN 1 ELSE 0 END AS valid" ) );
        return $rows && (int) $rows[1]['VALID'] === 1;
    }
    catch ( Throwable $error ) { return false; }
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
 * - simulation (recette isolée, config RECOVERY_SMS_SIMULATE='Y') : le défi
 *   est déjà en base, rien à transmettre — succès simulé ;
 * - sinon : routeur SMS central existant ; échec explicite NO_SMS_PROVIDER
 *   si aucun transport n'accepte le message. Accepté ne signifie pas livré.
 *
 * @return array ['sent'=>bool,'via'=>string]
 */
function SchoolSafeRecoverySmsSend( $phone_e164, $text, $options = [] )
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
            [ 'purpose' => 'otp', 'actor' => 'recovery-otp',
                'dedup_key' => (string) ( $options['dedup_key'] ?? '' ) ] );
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
