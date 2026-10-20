<?php
/**
 * SchoolSafe — WhatsApp central (WAHA principal, Evolution secours).
 *
 * AUCUN moteur de notification reconstruit : ce fichier ajoute le transport
 * WhatsApp sous le Studio U0-U5 (canal 'whatsapp', en sur-ensemble de
 * feed/email/push — les regles de ces canaux restent inchangees).
 *
 * - WAHA = moteur principal ; Evolution API = secours, tente UNIQUEMENT
 *   apres echec CONFIRME du primaire (erreur reseau, timeout, HTTP 0/429/5xx).
 *   Une 4xx definitive ne bascule jamais (rejet identique garanti).
 *   Jamais d'envoi parallele, jamais de double envoi volontaire.
 * - Sessions : une par moteur, noms DISTINCTS (UNIQUE en base + garde
 *   applicative) — la meme session n'est JAMAIS partagee entre WAHA et
 *   Evolution. Une session n'est operationnelle que si le hub PRODELI l'a
 *   activee APRES verification du compte (statut pose, jamais devine).
 * - Secrets (bases URL, cles) lus cote SERVEUR uniquement
 *   (env WAHA_BASE|WAHA_KEY, EVOLUTION_BASE|EVOLUTION_KEY), jamais en base,
 *   jamais au navigateur, jamais en journal.
 * - Consentement explicite : sans ligne schoolsafe_wa_consents a 'Y' pour
 *   (ecole, destinataire, numero), envoi refuse (CONSENT_REQUIRED).
 * - Confidentialite : les copies WhatsApp ne contiennent JAMAIS mot de passe,
 *   lien secret ni code de recuperation (SENSITIVE_BLOCKED, rien n'est envoye).
 * - Anti-doublons : idempotency_key UNIQUE — le rejeu rend le statut existant
 *   sans re-emettre. Statuts : 202/200/201 = accepte (PAS envoye) ; le suivi
 *   se fait par polling ; un etat indetermine reste 'accepted' (jamais de
 *   renvoi a l'aveugle).
 * - Simulation (recette isolee, config WHATSAPP_SIMULATE='Y') : outbox
 *   renseigne, statut simule, AUCUN appel reseau, AUCUNE session requise.
 */

/**
 * Canal simule actif ? Config WHATSAPP_SIMULATE='Y' (recette isolee UNIQUEMENT).
 */
function SchoolSafeWaSimulate()
{
    return Config( 'WHATSAPP_SIMULATE' ) === 'Y';
}

/**
 * Types d'evenements Studio eligibles au miroir WhatsApp.
 * Sur-ensemble strict : tous les types U0-U5, sans toucher leurs regles.
 * Les copies restent expurgees (SENSITIVE_BLOCKED si secret detecte).
 */
function SchoolSafeWaEligible( $event_type )
{
    return in_array( (string) $event_type,
        [ 'security.entry', 'student.pickup', 'payment.receipt',
            'school.announcement', 'remediation.update',
            'attendance.closure', 'absence.detected', 'case.updated',
            'security.p1' ], true );
}

/**
 * Normalise en +243XXXXXXXXX (9 chiffres apres l'indicatif), faux sinon.
 * Reutilise le normaliseur SMS quand il existe (meme regle, zero duplication).
 */
function SchoolSafeWaNormalize( $phone )
{
    if ( function_exists( 'SchoolSafeSmsNormalize' ) )
    {
        return SchoolSafeSmsNormalize( $phone );
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
 * Garde de confidentialite : refuse toute copie contenant un mot de passe,
 * un lien secret ou un code de recuperation. Vrai = propre, faux = bloque.
 */
function SchoolSafeWaSanitize( $text )
{
    $t = (string) $text;

    // Modificateur 'u' (UTF-8) : sans lui, les classes [eé] comparent des
    // octets et « récupération » passerait au travers (octet C3 puis A9).
    $patterns = [
        '/mot\s+de\s+passe/iu',
        '/\bpassword\b/iu',
        '/code\s+(de\s+)?(r[eé]cup[eé]ration|secret|otp)/iu',
        '/lien\s+secret/iu',
        '/https?:\/\/\S*(token|reset|recover|secret|code|password)/iu',
    ];

    foreach ( $patterns as $p )
    {
        if ( preg_match( $p, $t ) )
        {
            return false;
        }
    }

    return true;
}

/**
 * Journal d'audit WhatsApp (acteur = USERNAME de session ou 'system').
 */
function SchoolSafeWaAudit( $actor, $action, $session_id, $detail )
{
    DBQuery( "INSERT INTO schoolsafe_wa_audit(actor, action, session_id, detail)"
        . " VALUES('" . DBEscapeString( (string) $actor ) . "','"
        . DBEscapeString( (string) $action ) . "',"
        . ( $session_id === null ? 'NULL' : "'" . (int) $session_id . "'" ) . ",'"
        . DBEscapeString( mb_substr( (string) $detail, 0, 500 ) ) . "')" );
}

/**
 * Identifiants d'un moteur, env serveur uniquement (jamais logues).
 */
function SchoolSafeWaCredentials( $engine )
{
    if ( $engine === 'waha' )
    {
        $base = trim( (string) getenv( 'WAHA_BASE' ) );
        $key = (string) getenv( 'WAHA_KEY' );
    }
    elseif ( $engine === 'evolution' )
    {
        $base = trim( (string) getenv( 'EVOLUTION_BASE' ) );
        $key = (string) getenv( 'EVOLUTION_KEY' );
    }
    else
    {
        return null;
    }

    if ( $base === '' || $key === '' )
    {
        return null;
    }

    return [ 'base_url' => rtrim( $base, '/' ), 'key' => $key ];
}

/**
 * Session operationnelle d'un moteur (active + non declaree hors ligne).
 * Faux si aucune.
 */
function SchoolSafeWaActiveSession( $engine )
{
    $rows = DBGet( "SELECT * FROM schoolsafe_wa_sessions"
        . " WHERE engine='" . DBEscapeString( $engine ) . "'"
        . " AND status='active' AND conn_state <> 'offline'"
        . " ORDER BY id LIMIT 1" );

    return $rows ? $rows[1] : false;
}

/**
 * Consentement explicite du destinataire (opt-in ecole). Vrai si une ligne
 * (ecole, staff, numero) est a 'Y'.
 */
function SchoolSafeWaConsented( $school_id, $staff_id, $phone_e164 )
{
    $rows = DBGet( "SELECT ID FROM schoolsafe_wa_consents"
        . " WHERE school_id='" . (int) $school_id . "'"
        . " AND staff_id='" . (int) $staff_id . "'"
        . " AND phone_e164='" . DBEscapeString( (string) $phone_e164 ) . "'"
        . " AND consented='Y' LIMIT 1" );

    return (bool) $rows;
}

/**
 * Echec confirme (basculement autorise) : reseau, timeout, HTTP 0/429/5xx.
 * Une 4xx metier (sauf 429) est definitive : jamais de secours.
 */
function SchoolSafeWaConfirmedFailure( $res )
{
    if ( ! empty( $res['network_error'] ) )
    {
        return true;
    }

    $code = (int) ( $res['http_code'] ?? 0 );

    if ( $code === 0 || $code === 429 )
    {
        return true;
    }

    return $code >= 500 && $code <= 599;
}

/**
 * Transport HTTP generique WAHA/Evolution (cles en en-tetes, jamais en URL
 * ni en journal). Le reseau vit ICI et nulle part ailleurs.
 */
function SchoolSafeWaHttp( $method, $url, $key, $json_body )
{
    $ch = curl_init( $url );

    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
    curl_setopt( $ch, CURLOPT_HTTPHEADER,
        [ 'Content-Type: application/json', 'Accept: application/json',
            'X-Api-Key: ' . $key, 'apikey: ' . $key ] );
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
 * Envoi central : consentement + confidentialite + idempotence + WAHA puis
 * secours Evolution sur echec confirme uniquement.
 *
 * $opts : school_id (int), staff_id (int, 0 = test hub), actor (string),
 * dedup_key (string, si absent = genere), purpose ('notify'|'test'),
 * http_waha / http_evo callables($method,$url,$key,$jsonBody) => array.
 *
 * @return array ['outbox_id'=>int,'status'=>string,'via'|'error'=>string]
 */
function SchoolSafeWaSend( $to, $text, $opts = [] )
{
    $to = SchoolSafeWaNormalize( $to );

    if ( $to === false )
    {
        return [ 'outbox_id' => 0, 'status' => 'failed', 'error' => 'BAD_RECIPIENT' ];
    }

    $text = mb_substr( trim( (string) $text ), 0, 2000 );

    if ( $text === '' )
    {
        return [ 'outbox_id' => 0, 'status' => 'failed', 'error' => 'EMPTY_TEXT' ];
    }

    if ( ! SchoolSafeWaSanitize( $text ) )
    {
        return [ 'outbox_id' => 0, 'status' => 'failed', 'error' => 'SENSITIVE_BLOCKED' ];
    }

    $school_id = (int) ( $opts['school_id'] ?? 0 );
    $staff_id = (int) ( $opts['staff_id'] ?? 0 );
    $actor = mb_substr( (string) ( $opts['actor'] ?? '' ), 0, 128 );
    $purpose = (string) ( $opts['purpose'] ?? 'notify' );

    if ( ! in_array( $purpose, [ 'notify', 'test' ], true ) )
    {
        $purpose = 'notify';
    }

    $dedup = (string) ( $opts['dedup_key'] ?? '' );

    if ( $dedup === '' )
    {
        $dedup = 'wa:' . $school_id . ':' . $staff_id . ':' . $purpose . ':'
            . hash( 'sha256', $to . "\n" . $text );
    }

    // Idempotence d'abord : le rejeu rend le statut existant, sans re-emettre.
    $existing = DBGet( "SELECT ID,STATUS FROM schoolsafe_wa_outbox"
        . " WHERE idempotency_key='" . DBEscapeString( $dedup ) . "'" );

    if ( $existing )
    {
        return [ 'outbox_id' => (int) $existing[1]['ID'],
            'status' => (string) $existing[1]['STATUS'], 'via' => 'dedup' ];
    }

    // Simulation (recette isolee) : tracee en outbox, SANS session reelle,
    // SANS consentement exige, SANS appel reseau. Le hub affiche le mode simule.
    if ( SchoolSafeWaSimulate() )
    {
        $sim_id = DBInsert( 'schoolsafe_wa_outbox', [
            'idempotency_key' => $dedup, 'school_id' => $school_id,
            'recipient_e164' => $to, 'message_text' => $text,
            'engine' => 'simulated', 'status' => 'accepted',
            'error_code' => 'SIMULATED', 'created_by' => $actor,
        ], 'id' );

        return [ 'outbox_id' => $sim_id ? (int) $sim_id : 0,
            'status' => 'accepted', 'via' => 'simulated' ];
    }

    // Consentement (hors test hub explicite staff_id=0 + purpose=test).
    if ( ! ( $purpose === 'test' && $staff_id === 0 )
        && ! SchoolSafeWaConsented( $school_id, $staff_id, $to ) )
    {
        $blocked_id = DBInsert( 'schoolsafe_wa_outbox', [
            'idempotency_key' => $dedup, 'school_id' => $school_id,
            'recipient_e164' => $to, 'message_text' => $text,
            'engine' => 'waha', 'status' => 'blocked',
            'error_code' => 'CONSENT_REQUIRED', 'created_by' => $actor,
        ], 'id' );

        return [ 'outbox_id' => $blocked_id ? (int) $blocked_id : 0,
            'status' => 'blocked', 'error' => 'CONSENT_REQUIRED' ];
    }

    $waha_session = SchoolSafeWaActiveSession( 'waha' );

    if ( $waha_session === false )
    {
        $blocked_id = DBInsert( 'schoolsafe_wa_outbox', [
            'idempotency_key' => $dedup, 'school_id' => $school_id,
            'recipient_e164' => $to, 'message_text' => $text,
            'engine' => 'waha', 'status' => 'blocked',
            'error_code' => 'NO_WA_SESSION', 'created_by' => $actor,
        ], 'id' );

        return [ 'outbox_id' => $blocked_id ? (int) $blocked_id : 0,
            'status' => 'blocked', 'error' => 'NO_WA_SESSION' ];
    }

    // Garde anti-partage : les deux moteurs ne partagent jamais un nom.
    $evo_session = SchoolSafeWaActiveSession( 'evolution' );

    if ( is_array( $evo_session )
        && (string) $evo_session['SESSION_NAME'] !== ''
        && (string) $evo_session['SESSION_NAME'] === (string) $waha_session['SESSION_NAME'] )
    {
        return [ 'outbox_id' => 0, 'status' => 'failed', 'error' => 'SESSION_SHARED' ];
    }

    $outbox_id = DBInsert( 'schoolsafe_wa_outbox', [
        'idempotency_key' => $dedup, 'school_id' => $school_id,
        'recipient_e164' => $to, 'message_text' => $text,
        'engine' => 'waha', 'status' => 'queued', 'created_by' => $actor,
    ], 'id' );

    $outbox_id = $outbox_id ? (int) $outbox_id : 0;

    SchoolSafeWaAudit( $actor, 'send', (int) $waha_session['ID'],
        'outbox=' . $outbox_id . ' ecole=' . $school_id . ' vers=' . $to
        . ' moteur=waha motif=' . $purpose );

    $creds = SchoolSafeWaCredentials( 'waha' );

    if ( $creds === null )
    {
        DBQuery( "UPDATE schoolsafe_wa_outbox SET status='failed',"
            . "error_code='NO_WA_CREDENTIALS',updated_at=CURRENT_TIMESTAMP"
            . " WHERE id='" . $outbox_id . "'" );

        return [ 'outbox_id' => $outbox_id, 'status' => 'failed',
            'error' => 'NO_WA_CREDENTIALS' ];
    }

    $chat_id = preg_replace( '/[^0-9]/', '', $to ) . '@c.us';

    $waha_payload = json_encode( [
        'chatId' => $chat_id,
        'text' => $text,
        'session' => (string) $waha_session['SESSION_NAME'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

    $http_waha = $opts['http_waha'] ?? null;

    try
    {
        if ( is_callable( $http_waha ) )
        {
            $res = $http_waha( 'POST', $creds['base_url'] . '/api/sendText',
                $creds['key'], (string) $waha_payload );
        }
        else
        {
            $res = SchoolSafeWaHttp( 'POST', $creds['base_url'] . '/api/sendText',
                $creds['key'], (string) $waha_payload );
        }
    }
    catch ( Exception $e )
    {
        $res = [ 'http_code' => 0, 'network_error' => $e->getMessage() ];
    }

    $code = (int) ( $res['http_code'] ?? 0 );

    if ( $code === 200 || $code === 201 || $code === 202 )
    {
        $decoded = json_decode( (string) ( $res['body'] ?? '' ), true );
        $msg_id = '';

        if ( is_array( $decoded ) )
        {
            $msg_id = (string) ( $decoded['id'] ?? $decoded['key']['id']
                ?? $decoded['messageId'] ?? '' );
        }

        DBQuery( "UPDATE schoolsafe_wa_outbox SET status='accepted',"
            . "provider_msg_id='" . DBEscapeString( mb_substr( $msg_id, 0, 128 ) ) . "',"
            . "updated_at=CURRENT_TIMESTAMP WHERE id='" . $outbox_id . "'" );

        return [ 'outbox_id' => $outbox_id, 'status' => 'accepted', 'via' => 'waha' ];
    }

    // Echec non confirme (4xx metier) : pas de secours, echec explicite.
    if ( ! SchoolSafeWaConfirmedFailure( $res ) )
    {
        $err = 'WAHA_ERROR:' . $code;

        DBQuery( "UPDATE schoolsafe_wa_outbox SET status='failed',"
            . "error_code='" . DBEscapeString( $err ) . "',"
            . "updated_at=CURRENT_TIMESTAMP WHERE id='" . $outbox_id . "'" );

        return [ 'outbox_id' => $outbox_id, 'status' => 'failed', 'error' => $err ];
    }

    // Echec confirme : secours Evolution, UNE fois (jamais de parallele).
    $evo_session = SchoolSafeWaActiveSession( 'evolution' );

    if ( $evo_session === false )
    {
        DBQuery( "UPDATE schoolsafe_wa_outbox SET status='failed',"
            . "error_code='WAHA_FAILED_NO_FALLBACK',updated_at=CURRENT_TIMESTAMP"
            . " WHERE id='" . $outbox_id . "'" );

        return [ 'outbox_id' => $outbox_id, 'status' => 'failed',
            'error' => 'WAHA_FAILED_NO_FALLBACK' ];
    }

    $evo_creds = SchoolSafeWaCredentials( 'evolution' );

    if ( $evo_creds === null )
    {
        DBQuery( "UPDATE schoolsafe_wa_outbox SET status='failed',"
            . "error_code='NO_EVO_CREDENTIALS',updated_at=CURRENT_TIMESTAMP"
            . " WHERE id='" . $outbox_id . "'" );

        return [ 'outbox_id' => $outbox_id, 'status' => 'failed',
            'error' => 'NO_EVO_CREDENTIALS' ];
    }

    SchoolSafeWaAudit( $actor, 'fallback', (int) $evo_session['ID'],
        'outbox=' . $outbox_id . ' waha=' . $code . ' secours=evolution' );

    $evo_payload = json_encode( [
        'number' => preg_replace( '/[^0-9]/', '', $to ),
        'text' => $text,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

    $http_evo = $opts['http_evo'] ?? null;

    try
    {
        if ( is_callable( $http_evo ) )
        {
            $res2 = $http_evo( 'POST', $evo_creds['base_url'] . '/message/sendText/'
                . rawurlencode( (string) $evo_session['SESSION_NAME'] ),
                $evo_creds['key'], (string) $evo_payload );
        }
        else
        {
            $res2 = SchoolSafeWaHttp( 'POST', $evo_creds['base_url'] . '/message/sendText/'
                . rawurlencode( (string) $evo_session['SESSION_NAME'] ),
                $evo_creds['key'], (string) $evo_payload );
        }
    }
    catch ( Exception $e )
    {
        $res2 = [ 'http_code' => 0, 'network_error' => $e->getMessage() ];
    }

    $code2 = (int) ( $res2['http_code'] ?? 0 );

    if ( $code2 === 200 || $code2 === 201 || $code2 === 202 )
    {
        $decoded = json_decode( (string) ( $res2['body'] ?? '' ), true );
        $msg_id = is_array( $decoded )
            ? (string) ( $decoded['id'] ?? $decoded['key']['id'] ?? '' ) : '';

        DBQuery( "UPDATE schoolsafe_wa_outbox SET status='accepted',engine='evolution',"
            . "provider_msg_id='" . DBEscapeString( mb_substr( $msg_id, 0, 128 ) ) . "',"
            . "updated_at=CURRENT_TIMESTAMP WHERE id='" . $outbox_id . "'" );

        return [ 'outbox_id' => $outbox_id, 'status' => 'accepted',
            'via' => 'evolution-fallback' ];
    }

    $err = 'EVO_ERROR:' . $code2;

    if ( ! empty( $res2['network_error'] ) )
    {
        $err = 'EVO_NETWORK';
    }

    DBQuery( "UPDATE schoolsafe_wa_outbox SET status='failed',"
        . "error_code='" . DBEscapeString( $err ) . "',"
        . "updated_at=CURRENT_TIMESTAMP WHERE id='" . $outbox_id . "'" );

    return [ 'outbox_id' => $outbox_id, 'status' => 'failed', 'error' => $err ];
}

/**
 * Reconcilie le statut d'un envoi accepte (polling — jamais de renvoi
 * automatique : un etat indetermine reste 'accepted').
 */
function SchoolSafeWaPoll( $outbox_id, $http = null )
{
    $row_RET = DBGet( "SELECT o.*,s.session_name AS WA_SESSION
        FROM schoolsafe_wa_outbox o LEFT JOIN schoolsafe_wa_sessions s
        ON s.engine=o.engine WHERE o.id='" . (int) $outbox_id . "'" );

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

    $engine = (string) $row['ENGINE'];

    if ( $engine !== 'waha' && $engine !== 'evolution' )
    {
        return [ 'status' => $status, 'error' => 'NO_POLL_ENGINE' ];
    }

    $creds = SchoolSafeWaCredentials( $engine );

    if ( $creds === null || (string) $row['PROVIDER_MSG_ID'] === '' )
    {
        return [ 'status' => $status, 'error' => 'NO_WA_CREDENTIALS' ];
    }

    try
    {
        if ( is_callable( $http ) )
        {
            $res = $http( 'GET', $creds['base_url'] . '/status/'
                . rawurlencode( (string) $row['PROVIDER_MSG_ID'] ),
                $creds['key'], '' );
        }
        else
        {
            $res = SchoolSafeWaHttp( 'GET', $creds['base_url'] . '/status/'
                . rawurlencode( (string) $row['PROVIDER_MSG_ID'] ),
                $creds['key'], '' );
        }
    }
    catch ( Exception $e )
    {
        return [ 'status' => $status, 'error' => 'WA_NETWORK' ];
    }

    if ( (int) ( $res['http_code'] ?? 0 ) !== 200 )
    {
        return [ 'status' => $status, 'error' => 'POLL_FAILED' ];
    }

    $decoded = json_decode( (string) ( $res['body'] ?? '' ), true );
    $state = strtolower( (string) ( is_array( $decoded )
        ? ( $decoded['state'] ?? $decoded['status'] ?? '' ) : '' ) );

    $map = [ 'pending' => 'accepted', 'processed' => 'accepted',
        'unknown' => 'accepted', 'sent' => 'sent', 'delivered' => 'delivered',
        'read' => 'delivered', 'failed' => 'failed', 'error' => 'failed' ];

    if ( ! isset( $map[ $state ] ) )
    {
        return [ 'status' => $status, 'error' => 'UNKNOWN_STATE' ];
    }

    $new = $map[ $state ];

    if ( $new !== $status )
    {
        $err_sql = $new === 'failed' ? "error_code='WA_FAILED'," : '';

        DBQuery( "UPDATE schoolsafe_wa_outbox SET status='"
            . DBEscapeString( $new ) . "'," . $err_sql
            . "updated_at=CURRENT_TIMESTAMP WHERE id='"
            . (int) $outbox_id . "'" );
    }

    return [ 'status' => $new, 'via' => 'poll' ];
}
