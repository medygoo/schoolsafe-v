<?php
/**
 * SchoolSafe — SMS central partage (gestionnaire PRODELI, SMSGate).
 *
 * AUCUN moteur de notification reconstruit : ce fichier ajoute le transport
 * SMS sous le Studio U0-U5 (canal 'sms' pour OTP de recuperation et secours
 * d'alertes critiques — jamais le canal systematique des presences/notes).
 * PUSH reste prioritaire, EMAIL Brevo les communications officielles.
 *
 * - Lignes : 4 emplacements (vodacom|airtel|orange|africell), statuts
 *   (active|suspended|disabled). Une ligne n'est operationnelle que si
 *   l'appareil, la SIM, le forfait et la connexion sont verifies (statut
 *   pose par le hub PRODELI, jamais devine : un numero enregistre n'est
 *   PAS une SIM connectee).
 * - Secrets SMSGate (base URL, utilisateur, mot de passe / jeton) lus cote
 *   SERVEUR uniquement (env SMS_LINE_<id>_BASE|USER|PASS), jamais en base,
 *   jamais au navigateur, jamais en journal.
 * - Quotas : decompte documente (1 segment = 160 caracteres, segments =
 *   ceil(longueur/160)) ; seuil d'alerte ; blocage a 0 ; reprovisionnement
 *   explicite ; chaque mouvement est historise (quota_ledger).
 * - Anti-doublons : idempotency_key UNIQUE — le rejeu ne recree rien et ne
 *   re-decompte aucun quota.
 * - Statuts SMSGate : 202 = accepte (PAS envoye) ; le suivi se fait par
 *   polling (GET /3rdparty/v1/messages/{id}) : sent, delivered, failed.
 *   Un message 'processed' indetermine n'est JAMAIS renvoye a l'aveugle
 *   (risque de doublon cote operateur).
 * - Sans ligne operationnelle et hors simulation : echec explicite
 *   NO_SMS_LINE (jamais un faux succes).
 * - Simulation (recette isolee, config SMS_SIMULATE='Y') : outbox renseigne,
 *   statut simule, AUCUN appel reseau, AUCUNE ligne requise.
 */

/**
 * Table de routage initiale (prefixes nationaux, sans le 0) par operateur.
 * Sources : guides telephoniques publics, pas l'ARPTC — 086/096 EXCLUS
 * (attributions incertaines) et repli systematique sur une ligne
 * operationnelle quelconque (la portabilite peut fausser le prefixe).
 */
function SchoolSafeSmsPrefixMap()
{
    return [
        'vodacom' => ['081', '082', '083'],
        'airtel' => ['097', '098', '099'],
        'orange' => ['080', '084', '085', '089'],
        'africell' => ['090', '091'],
    ];
}

/**
 * Reseau suppose d'un numero national (10 chiffres commencant par 0),
 * faux si inconnu (le routeur applique alors le repli).
 */
function SchoolSafeSmsNetworkOf( $national )
{
    $digits = preg_replace( '/[^0-9]/', '', (string) $national );

    if ( ! preg_match( '/^0[0-9]{9}$/', $digits ) )
    {
        return false;
    }

    foreach ( SchoolSafeSmsPrefixMap() as $operator => $prefixes )
    {
        foreach ( $prefixes as $prefix )
        {
            if ( mb_strpos( $digits, $prefix ) === 0 )
            {
                return $operator;
            }
        }
    }

    return false;
}

/**
 * Normalise en +243XXXXXXXXX (9 chiffres apres l'indicatif), faux sinon.
 */
function SchoolSafeSmsNormalize( $phone )
{
    require_once 'ProgramFunctions/SchoolSafeIdentity.fnc.php';

    if ( function_exists( 'SchoolSafeIdentityNormalizePhoneCD' ) )
    {
        $normalized = SchoolSafeIdentityNormalizePhoneCD( (string) $phone );

        if ( preg_match( '/^\+243[0-9]{9}$/', $normalized ) )
        {
            return $normalized;
        }

        return false;
    }

    $digits = preg_replace( '/[^0-9]/', '', (string) $phone );

    if ( preg_match( '/^243([0-9]{9})$/', $digits, $m ) )
    {
        return '+243' . $m[1];
    }

    if ( preg_match( '/^0([0-9]{9})$/', $digits, $m ) )
    {
        return '+243' . $m[1];
    }

    return false;
}

/**
 * Regle de decompte documentee : 1 segment = 160 caracteres.
 */
function SchoolSafeSmsSegments( $text )
{
    $len = function_exists( 'mb_strlen' )
        ? mb_strlen( (string) $text, 'UTF-8' )
        : strlen( (string) $text );

    return max( 1, min( 10, (int) ceil( $len / 160 ) ) );
}

/**
 * Canal simule actif ? Config SMS_SIMULATE='Y' (recette isolee UNIQUEMENT).
 */
function SchoolSafeSmsSimulate()
{
    return Config( 'SMS_SIMULATE' ) === 'Y';
}

/**
 * Ligne operationnelle preferee : meme operateur que le destinataire si
 * possible, sinon n'importe quelle ligne active avec du quota et une
 * connexion non declaree hors ligne. Faux si aucune (avec le motif).
 */
function SchoolSafeSmsPickLine( $network, $segments, $line_id = null )
{
    $candidates = DBGet( "SELECT * FROM schoolsafe_sms_lines
        WHERE status='active' AND conn_state <> 'offline'
        " . ( $line_id === null ? '' : " AND id='" . (int) $line_id . "'" ) . "
        AND quota_used + '" . (int) $segments . "' <= quota_total
        ORDER BY CASE WHEN operator='" . DBEscapeString( (string) $network ) . "'
            THEN 0 ELSE 1 END, id" );

    if ( ! $candidates )
    {
        if ( $line_id !== null )
        {
            return [ 'line' => false, 'error' => 'SELECTED_LINE_UNAVAILABLE' ];
        }
        $any = DBGetOne( "SELECT COUNT(*) FROM schoolsafe_sms_lines
            WHERE status='active'" );

        return [ 'line' => false,
            'error' => (int) $any > 0 ? 'NO_QUOTA_OR_OFFLINE' : 'NO_SMS_LINE' ];
    }

    return [ 'line' => $candidates[1], 'error' => '' ];
}

/**
 * Journal d'audit SMS (acteur = USERNAME de session ou 'system').
 */
function SchoolSafeSmsAudit( $actor, $action, $line_id, $detail )
{
    DBQuery( "INSERT INTO schoolsafe_sms_audit(actor, action, line_id, detail)"
        . " VALUES('" . DBEscapeString( (string) $actor ) . "','"
        . DBEscapeString( (string) $action ) . "',"
        . ( $line_id === null ? 'NULL' : "'" . (int) $line_id . "'" ) . ",'"
        . DBEscapeString( mb_substr( (string) $detail, 0, 500 ) ) . "')" );
}

/**
 * Credentials SMSGate d'une ligne, env serveur uniquement (jamais logues).
 */
function SchoolSafeSmsLineCredentials( $line_id )
{
    $prefix = 'SMS_LINE_' . (int) $line_id . '_';

    $base = trim( (string) getenv( $prefix . 'BASE' ) );
    $user = trim( (string) getenv( $prefix . 'USER' ) );
    $pass = (string) getenv( $prefix . 'PASS' );

    if ( $base === '' || $user === '' || $pass === '' )
    {
        return null;
    }

    return [ 'base_url' => rtrim( $base, '/' ), 'user' => $user, 'pass' => $pass ];
}

/**
 * Envoi central : routage + quota atomique + idempotence + SMSGate/polling.
 *
 * $opts : school_id (int, 0 = plateforme), actor (string), dedup_key
 * (string, si absent = genere), purpose ('otp'|'alert'|'test'),
 * line_id (int, purpose=test uniquement : jamais de repli vers une autre ligne),
 * http callable($method,$url,$user,$pass,$jsonBody) => array (tests).
 *
 * @return array ['outbox_id'=>int,'status'=>string,'via'|'error'=>string]
 */
function SchoolSafeSmsSend( $to, $text, $opts = [] )
{
    $to = SchoolSafeSmsNormalize( $to );

    if ( $to === false )
    {
        return [ 'outbox_id' => 0, 'status' => 'failed', 'error' => 'BAD_RECIPIENT' ];
    }

    $text = mb_substr( trim( (string) $text ), 0, 1600 );

    if ( $text === '' )
    {
        return [ 'outbox_id' => 0, 'status' => 'failed', 'error' => 'EMPTY_TEXT' ];
    }

    $segments = SchoolSafeSmsSegments( $text );
    $school_id = (int) ( $opts['school_id'] ?? 0 );
    $actor = mb_substr( (string) ( $opts['actor'] ?? '' ), 0, 128 );
    $purpose = (string) ( $opts['purpose'] ?? 'alert' );

    if ( ! in_array( $purpose, [ 'otp', 'alert', 'test' ], true ) )
    {
        $purpose = 'alert';
    }

    $forced_line = null;
    if ( array_key_exists( 'line_id', $opts ) )
    {
        if ( $purpose !== 'test' || ! ctype_digit( (string) $opts['line_id'] )
            || (int) $opts['line_id'] < 1 )
        {
            return [ 'outbox_id' => 0, 'status' => 'blocked', 'error' => 'BAD_SMS_LINE' ];
        }
        $forced_line = (int) $opts['line_id'];
    }
    // Never persist recovery secrets in the operational SMS history.
    $stored_text = $purpose === 'otp' ? '[SchoolSafe: confidential recovery code]' : $text;
    $dedup = (string) ( $opts['dedup_key'] ?? '' );

    if ( $dedup === '' )
    {
        $dedup = 'sms:' . $school_id . ':' . $purpose . ':'
            . hash( 'sha256', $to . "\n" . $text );
    }

    // Idempotence d'abord : le rejeu rend le statut existant, sans ecrire.
    $existing = DBGet( "SELECT ID,STATUS,LINE_ID FROM schoolsafe_sms_outbox
        WHERE idempotency_key='" . DBEscapeString( $dedup ) . "'" );

    if ( $existing )
    {
        if ( $forced_line !== null && (int) $existing[1]['LINE_ID'] !== $forced_line )
        {
            return [ 'outbox_id' => 0, 'status' => 'blocked', 'error' => 'DEDUP_LINE_MISMATCH' ];
        }
        return [ 'outbox_id' => (int) $existing[1]['ID'],
            'status' => (string) $existing[1]['STATUS'], 'via' => 'dedup' ];
    }

    // Simulation (recette isolee) : tracee en outbox, SANS ligne reelle,
    // SANS quota, SANS appel reseau. Le hub affiche le mode simule.
    if ( SchoolSafeSmsSimulate() )
    {
        $sim_id = DBInsert( 'schoolsafe_sms_outbox', [
            'idempotency_key' => $dedup, 'school_id' => $school_id,
            'recipient_e164' => $to, 'message_text' => $stored_text,
            'line_id' => $forced_line,
            'segments' => $segments, 'status' => 'accepted',
            'error_code' => 'SIMULATED', 'created_by' => $actor,
        ], 'id' );

        return [ 'outbox_id' => $sim_id ? (int) $sim_id : 0,
            'status' => 'accepted', 'via' => 'simulated' ];
    }

    $national = '0' . mb_substr( $to, 4 );
    $network = SchoolSafeSmsNetworkOf( $national );
    $pick = SchoolSafeSmsPickLine( $network === false ? '' : $network, $segments, $forced_line );

    if ( $pick['line'] === false )
    {
        $blocked_id = DBInsert( 'schoolsafe_sms_outbox', [
            'idempotency_key' => $dedup, 'school_id' => $school_id,
            'recipient_e164' => $to, 'message_text' => $stored_text,
            'line_id' => $forced_line,
            'segments' => $segments, 'status' => 'blocked',
            'error_code' => $pick['error'], 'created_by' => $actor,
        ], 'id' );

        return [ 'outbox_id' => $blocked_id ? (int) $blocked_id : 0,
            'status' => 'blocked', 'error' => $pick['error'] ];
    }

    $line = $pick['line'];
    $line_id = (int) $line['ID'];

    // Decrement atomique : une seule requete, garde quota + statut.
    $claimed = DBQuery( "UPDATE schoolsafe_sms_lines
        SET quota_used=quota_used + '" . $segments . "',
            updated_at=CURRENT_TIMESTAMP
        WHERE id='" . $line_id . "' AND status='active' AND conn_state <> 'offline'
        AND quota_used + '" . $segments . "' <= quota_total" );

    if ( ! $claimed || pg_affected_rows( $claimed ) < 1 )
    {
        return [ 'outbox_id' => 0, 'status' => 'blocked', 'error' => 'QUOTA_RACE' ];
    }

    DBQuery( "INSERT INTO schoolsafe_sms_quota_ledger(line_id,delta,reason,actor)"
        . " VALUES('" . $line_id . "','-" . $segments . "','send','"
        . DBEscapeString( $actor ) . "')" );

    $outbox_id = DBInsert( 'schoolsafe_sms_outbox', [
        'idempotency_key' => $dedup, 'school_id' => $school_id,
        'recipient_e164' => $to, 'message_text' => $stored_text,
        'segments' => $segments, 'line_id' => $line_id,
        'status' => 'queued', 'created_by' => $actor,
    ], 'id' );

    $outbox_id = $outbox_id ? (int) $outbox_id : 0;

    SchoolSafeSmsAudit( $actor, 'send', $line_id,
        'outbox=' . $outbox_id . ' ecole=' . $school_id . ' vers=' . $to
        . ' segments=' . $segments . ' motif=' . $purpose );

    $creds = SchoolSafeSmsLineCredentials( $line_id );

    if ( $creds === null )
    {
        DBQuery( "UPDATE schoolsafe_sms_outbox SET status='failed',"
            . "error_code='NO_SMS_CREDENTIALS',updated_at=CURRENT_TIMESTAMP"
            . " WHERE id='" . $outbox_id . "'" );

        return [ 'outbox_id' => $outbox_id, 'status' => 'failed',
            'error' => 'NO_SMS_CREDENTIALS' ];
    }

    $payload = json_encode( [
        'phoneNumbers' => [ $to ],
        'simNumber' => (int) $line['SIM_NUMBER'],
        'withDeliveryReport' => true,
        'textMessage' => [ 'text' => $text ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

    $http = $opts['http'] ?? null;

    try
    {
        if ( is_callable( $http ) )
        {
            $res = $http( 'POST', $creds['base_url'] . '/3rdparty/v1/messages',
                $creds['user'], $creds['pass'], (string) $payload );
        }
        else
        {
            $res = SchoolSafeSmsHttp( 'POST',
                $creds['base_url'] . '/3rdparty/v1/messages',
                $creds['user'], $creds['pass'], (string) $payload );
        }
    }
    catch ( Exception $e )
    {
        $res = [ 'http_code' => 0, 'network_error' => $e->getMessage() ];
    }

    $code = (int) ( $res['http_code'] ?? 0 );

    if ( $code === 202 || $code === 200 )
    {
        $decoded = json_decode( (string) ( $res['body'] ?? '' ), true );
        $msg_id = '';

        if ( is_array( $decoded ) )
        {
            $msg_id = (string) ( $decoded['id'] ?? $decoded['messageId'] ?? '' );
        }

        DBQuery( "UPDATE schoolsafe_sms_outbox SET status='accepted',"
            . "provider_msg_id='" . DBEscapeString( mb_substr( $msg_id, 0, 128 ) ) . "',"
            . "updated_at=CURRENT_TIMESTAMP WHERE id='" . $outbox_id . "'" );

        return [ 'outbox_id' => $outbox_id, 'status' => 'accepted',
            'via' => 'smsgate' ];
    }

    $err = 'SMSGATE_ERROR:' . $code;

    if ( ! empty( $res['network_error'] ) )
    {
        $err = 'SMSGATE_NETWORK';
    }

    DBQuery( "UPDATE schoolsafe_sms_outbox SET status='failed',"
        . "error_code='" . DBEscapeString( $err ) . "',"
        . "updated_at=CURRENT_TIMESTAMP WHERE id='" . $outbox_id . "'" );

    return [ 'outbox_id' => $outbox_id, 'status' => 'failed', 'error' => $err ];
}

/**
 * Transport HTTP SMSGate (Basic Auth). Le reseau vit ICI et nulle part
 * ailleurs ; les secrets ne sont jamais journalises.
 */
function SchoolSafeSmsHttp( $method, $url, $user, $pass, $json_body )
{
    $ch = curl_init( $url );

    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
    curl_setopt( $ch, CURLOPT_HTTPHEADER,
        [ 'Content-Type: application/json', 'Accept: application/json' ] );
    curl_setopt( $ch, CURLOPT_USERPWD, $user . ':' . $pass );
    curl_setopt( $ch, CURLOPT_TIMEOUT, 15 );

    if ( $method === 'POST' )
    {
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $json_body );
    }

    $raw = curl_exec( $ch );
    $code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    $err = curl_error( $ch );

    curl_close( $ch );

    $res = [ 'http_code' => $code, 'body' => is_string( $raw ) ? $raw : '' ];

    if ( $err !== '' )
    {
        $res['network_error'] = $err;
    }

    return $res;
}

/**
 * Reconcilie le statut d'un envoi accepte (polling — jamais de renvoi
 * automatique : un 'processed' indetermine reste 'accepted').
 */
function SchoolSafeSmsPoll( $outbox_id, $http = null )
{
    $row_RET = DBGet( "SELECT o.*,l.device_id AS LINE_DEVICE
        FROM schoolsafe_sms_outbox o LEFT JOIN schoolsafe_sms_lines l
        ON l.id=o.line_id WHERE o.id='" . (int) $outbox_id . "'" );

    if ( ! $row_RET )
    {
        return [ 'status' => 'unknown', 'error' => 'NOT_FOUND' ];
    }

    $row = $row_RET[1];
    $status = (string) $row['STATUS'];

    if ( $status !== 'accepted' && $status !== 'queued' )
    {
        return [ 'status' => $status, 'via' => 'cached' ];
    }

    if ( (string) $row['ERROR_CODE'] === 'SIMULATED' )
    {
        return [ 'status' => $status, 'via' => 'simulated' ];
    }

    $creds = SchoolSafeSmsLineCredentials( (int) $row['LINE_ID'] );

    if ( $creds === null || (string) $row['PROVIDER_MSG_ID'] === '' )
    {
        return [ 'status' => $status, 'error' => 'NO_SMS_CREDENTIALS' ];
    }

    try
    {
        if ( is_callable( $http ) )
        {
            $res = $http( 'GET', $creds['base_url'] . '/3rdparty/v1/messages/'
                . rawurlencode( (string) $row['PROVIDER_MSG_ID'] ),
                $creds['user'], $creds['pass'], '' );
        }
        else
        {
            $res = SchoolSafeSmsHttp( 'GET', $creds['base_url']
                . '/3rdparty/v1/messages/'
                . rawurlencode( (string) $row['PROVIDER_MSG_ID'] ),
                $creds['user'], $creds['pass'], '' );
        }
    }
    catch ( Exception $e )
    {
        return [ 'status' => $status, 'error' => 'SMSGATE_NETWORK' ];
    }

    if ( (int) ( $res['http_code'] ?? 0 ) !== 200 )
    {
        return [ 'status' => $status, 'error' => 'POLL_FAILED' ];
    }

    $decoded = json_decode( (string) ( $res['body'] ?? '' ), true );
    $state = strtolower( (string) ( is_array( $decoded )
        ? ( $decoded['state'] ?? '' ) : '' ) );

    $map = [ 'pending' => 'accepted', 'processed' => 'accepted',
        'sent' => 'sent', 'delivered' => 'delivered',
        'failed' => 'failed', 'cancelled' => 'failed' ];

    if ( ! isset( $map[ $state ] ) )
    {
        return [ 'status' => $status, 'error' => 'UNKNOWN_STATE' ];
    }

    $new = $map[ $state ];

    if ( $new !== $status )
    {
        $err_sql = $new === 'failed'
            ? "error_code='SMSGATE_FAILED'," : '';

        DBQuery( "UPDATE schoolsafe_sms_outbox SET status='"
            . DBEscapeString( $new ) . "'," . $err_sql
            . "updated_at=CURRENT_TIMESTAMP WHERE id='"
            . (int) $outbox_id . "'" );
    }

    return [ 'status' => $new, 'via' => 'poll' ];
}

/**
 * Quota restant d'une ligne (faux si ligne inconnue).
 */
function SchoolSafeSmsQuotaLeft( $line_id )
{
    $row = DBGetOne( "SELECT quota_total - quota_used FROM schoolsafe_sms_lines
        WHERE id='" . (int) $line_id . "'" );

    return $row === false || $row === null ? false : (int) $row;
}
