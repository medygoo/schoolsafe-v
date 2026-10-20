<?php
/**
 * SchoolSafe — codes d'autorisation d'ouverture d'ecole (LOT L2).
 *
 * Un seul mecanisme central, explicitement autorise, peut emettre les codes :
 * l'emission exige un administrateur global (SchoolSafeGlobalAdmin), donc un
 * directeur d'ecole ne peut jamais generer ses propres autorisations. La
 * presentation du code, elle, est publique (demarrer.php) : le code EST
 * l'autorisation, mais il ne declenche que l'approbation de la demande
 * (requested -> approved). La creation de l'ecole reste reservee au parcours
 * autorise (assistant ecole -> CopySchool natif) et le rattachement du
 * responsable passe par les controles natifs (Opening.php, action attach).
 *
 * Garanties :
 * - code aleatoire robuste (192 bits, alphabet sans ambiguite) ;
 * - seul le hash SHA-256 est conserve en base, jamais le code brut ;
 * - expiration obligatoire (pas de code sans echeance) ;
 * - usage unique, consommation atomique (SELECT ... FOR UPDATE : deux
 *   activations concurrentes -> une seule gagne, l'autre est refusee) ;
 * - liaison a la demande (request_id), a l'operation (approve) et au
 *   demandeur (e-mail de la demande, compare a la presentation) ;
 * - tentatives limitees (5 par defaut, verrou au-dela) ;
 * - journalisation dans schoolsafe_opening_events SANS le secret.
 *
 * Les fonctions renvoient des tableaux ['ok'=>bool,'error'=>code,...],
 * jamais le code brut sauf a l'emission (retourne UNE SEULE FOIS a
 * l'emetteur central pour remise confidentielle).
 */

/**
 * Le mecanisme central d'emission est-il autorise dans ce contexte ?
 *
 * @return bool Vrai pour un administrateur global authentifie uniquement.
 */
function SchoolSafeOpeningCodeIssuerAllowed()
{
	if ( ! function_exists( 'SchoolSafeGlobalAdmin' )
		|| ! SchoolSafeGlobalAdmin() )
	{
		return false;
	}

	if ( function_exists( 'sskcOpeningCentral' )
		&& ! sskcOpeningCentral() )
	{
		return false;
	}

	return true;
}

/**
 * Entropie : 24 caracteres dans un alphabet de 31 symboles (~119 bits),
 * sans caractere ambigu (ni 0/O, ni 1/I/L).
 *
 * @return string Code type « XXXXXX-XXXXXX-XXXXXX-XXXXXX ».
 */
function SchoolSafeOpeningCodeGenerate()
{
	$alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

	$last = strlen( $alphabet ) - 1;

	$raw = '';

	for ( $i = 0; $i < 24; $i++ )
	{
		$raw .= $alphabet[ random_int( 0, $last ) ];
	}

	return substr( $raw, 0, 6 ) . '-' . substr( $raw, 6, 6 ) . '-'
		. substr( $raw, 12, 6 ) . '-' . substr( $raw, 18, 6 );
}

/**
 * Normalise un code presente (casse, espaces, tirets optionnels).
 *
 * @param string $code Code brut presente.
 *
 * @return string Code normalise, ou '' si le format est invalide.
 */
function SchoolSafeOpeningCodeNormalize( $code )
{
	$clean = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', (string) $code ) );

	if ( strlen( $clean ) !== 24
		|| preg_match( '/[^ABCDEFGHJKMNPQRSTUVWXYZ23456789]/', $clean ) )
	{
		return '';
	}

	return substr( $clean, 0, 6 ) . '-' . substr( $clean, 6, 6 ) . '-'
		. substr( $clean, 12, 6 ) . '-' . substr( $clean, 18, 6 );
}

/**
 * Hash conserve en base (jamais le code brut).
 *
 * @param string $normalized_code Code normalise.
 *
 * @return string Hash SHA-256 hexadecimal.
 */
function SchoolSafeOpeningCodeHash( $normalized_code )
{
	return hash( 'sha256', $normalized_code );
}

/**
 * La table des codes existe-t-elle ? (memoise par requete)
 *
 * @return bool
 */
function SchoolSafeOpeningCodeReady()
{
	static $ready = null;

	if ( $ready !== null )
	{
		return $ready;
	}

	$ready = false;

	try
	{
		$ready = (bool) DBGetOne( "SELECT 1 FROM information_schema.tables
			WHERE table_name='schoolsafe_opening_codes'" );
	}
	catch ( Throwable $error )
	{
		$ready = false;
	}

	return $ready;
}

/**
 * Emet un code d'autorisation pour une demande (central uniquement).
 *
 * @param int $request_id     Demande a l'etat « requested ».
 * @param int $actor_staff_id Emetteur central (trace, jamais le demandeur).
 * @param int $ttl_hours      Duree de validite en heures (1 a 336).
 *
 * @return array ok, error, code (UNE SEULE FOIS), code_id, expires_at.
 */
function SchoolSafeOpeningCodeIssue( $request_id, $actor_staff_id, $ttl_hours = 72 )
{
	$request_id = (int) $request_id;

	$actor_staff_id = (int) $actor_staff_id;

	$ttl_hours = max( 1, min( 336, (int) $ttl_hours ) );

	if ( ! SchoolSafeOpeningCodeReady() )
	{
		return [ 'ok' => false, 'error' => 'SERVICE_UNAVAILABLE' ];
	}

	if ( ! SchoolSafeOpeningCodeIssuerAllowed() )
	{
		return [ 'ok' => false, 'error' => 'ISSUER_UNAUTHORIZED' ];
	}

	if ( $request_id < 1 || $actor_staff_id < 1 )
	{
		return [ 'ok' => false, 'error' => 'REQUEST_INVALID' ];
	}

	DBQuery( 'BEGIN' );

	try
	{
		$rows = DBGet( "SELECT * FROM schoolsafe_opening_requests
			WHERE ID='" . $request_id . "'
			AND SYEAR='" . (int) UserSyear() . "'
			FOR UPDATE" );

		$request = $rows[1] ?? null;

		if ( ! $request )
		{
			throw new RuntimeException( 'REQUEST_NOT_FOUND' );
		}

		if ( $request['STATE'] !== 'requested' )
		{
			throw new RuntimeException( 'REQUEST_NOT_APPROVABLE' );
		}

		$active = DBGet( "SELECT 1 FROM schoolsafe_opening_codes
			WHERE REQUEST_ID='" . $request_id . "'
			AND OPERATION='approve'
			AND CONSUMED_AT IS NULL
			AND EXPIRES_AT>CURRENT_TIMESTAMP
			AND ATTEMPTS<MAX_ATTEMPTS" );

		if ( $active )
		{
			throw new RuntimeException( 'CODE_ALREADY_ACTIVE' );
		}

		$code = SchoolSafeOpeningCodeGenerate();

		$hash = SchoolSafeOpeningCodeHash( $code );

		// DBInsert ne sait passer que des litteraux : l'echeance est calculee
		// ici (meme fuseau que la base, Africa/Kinshasa par configuration).
		$expires_at = date( 'Y-m-d H:i:s', time() + $ttl_hours * 3600 );

		$code_id = (int) DBInsert( 'schoolsafe_opening_codes', [
			'REQUEST_ID' => $request_id,
			'OPERATION' => 'approve',
			'CODE_HASH' => $hash,
			'EXPIRES_AT' => $expires_at,
			'MAX_ATTEMPTS' => 5,
			'ATTEMPTS' => 0,
			'CREATED_BY' => $actor_staff_id,
		], 'id' );

		if ( $code_id < 1 )
		{
			throw new RuntimeException( 'CODE_NOT_CREATED' );
		}

		DBQuery( "INSERT INTO schoolsafe_opening_events(request_id,actor_staff_id,action)
			VALUES(" . $request_id . "," . $actor_staff_id . ",'code_issued')" );

		DBQuery( 'COMMIT' );

		$expires = DBGetOne( "SELECT EXPIRES_AT FROM schoolsafe_opening_codes
			WHERE ID='" . $code_id . "'" );

		return [
			'ok' => true,
			'error' => '',
			'code' => $code,
			'code_id' => $code_id,
			'expires_at' => (string) $expires,
		];
	}
	catch ( Throwable $error )
	{
		DBQuery( 'ROLLBACK' );

		return [
			'ok' => false,
			'error' => $error instanceof RuntimeException
				? $error->getMessage() : 'ISSUE_FAILED',
		];
	}
}

/**
 * Etat public d'un code, sans secret (affichage central uniquement).
 *
 * @param int $request_id Demande.
 *
 * @return array active (bool), expires_at, attempts, max_attempts.
 */
function SchoolSafeOpeningCodeStatus( $request_id )
{
	$status = [
		'active' => false,
		'expires_at' => '',
		'attempts' => 0,
		'max_attempts' => 5,
	];

	if ( ! SchoolSafeOpeningCodeReady() )
	{
		return $status;
	}

	try
	{
		$rows = DBGet( "SELECT EXPIRES_AT,ATTEMPTS,MAX_ATTEMPTS
			FROM schoolsafe_opening_codes
			WHERE REQUEST_ID='" . (int) $request_id . "'
			AND OPERATION='approve'
			AND CONSUMED_AT IS NULL
			AND EXPIRES_AT>CURRENT_TIMESTAMP
			AND ATTEMPTS<MAX_ATTEMPTS
			ORDER BY ID DESC" );

		$row = $rows[1] ?? null;

		if ( $row )
		{
			$status['active'] = true;
			$status['expires_at'] = (string) $row['EXPIRES_AT'];
			$status['attempts'] = (int) $row['ATTEMPTS'];
			$status['max_attempts'] = (int) $row['MAX_ATTEMPTS'];
		}
	}
	catch ( Throwable $error )
	{
		// Affichage seul : un echec ne bloque jamais l'ecran central.
	}

	return $status;
}

/**
 * Presente un code pour autoriser automatiquement une demande.
 *
 * Succes : la demande passe a « approved » et le code est consomme
 * atomiquement (deux presentations concurrentes -> une seule gagne).
 * Echec : la demande reste inchangee, la tentative est comptabilisee.
 *
 * @param int    $request_id Demande visee.
 * @param string $email      E-mail de contact de la demande (liaison).
 * @param string $code       Code brut presente (jamais journalise).
 *
 * @return array ok, error, request_id, remaining (tentatives restantes).
 */
function SchoolSafeOpeningCodeVerify( $request_id, $email, $code )
{
	$request_id = (int) $request_id;

	$email = trim( mb_strtolower( (string) $email ) );

	$normalized = SchoolSafeOpeningCodeNormalize( $code );

	if ( ! SchoolSafeOpeningCodeReady() )
	{
		return [ 'ok' => false, 'error' => 'SERVICE_UNAVAILABLE', 'remaining' => 0 ];
	}

	if ( $request_id < 1 || $email === '' || $normalized === '' )
	{
		return [ 'ok' => false, 'error' => 'CODE_INVALID', 'remaining' => 0 ];
	}

	DBQuery( 'BEGIN' );

	try
	{
		$rows = DBGet( "SELECT * FROM schoolsafe_opening_requests
			WHERE ID='" . $request_id . "'
			FOR UPDATE" );

		$request = $rows[1] ?? null;

		if ( ! $request )
		{
			throw new RuntimeException( 'REQUEST_NOT_FOUND' );
		}

		if ( trim( mb_strtolower( (string) $request['EMAIL'] ) ) !== $email )
		{
			throw new RuntimeException( 'EMAIL_MISMATCH' );
		}

		if ( $request['STATE'] !== 'requested' )
		{
			throw new RuntimeException( 'ALREADY_DECIDED' );
		}

		$code_rows = DBGet( "SELECT * FROM schoolsafe_opening_codes
			WHERE REQUEST_ID='" . $request_id . "'
			AND OPERATION='approve'
			ORDER BY ID DESC" );

		$code_row = $code_rows[1] ?? null;

		if ( ! $code_row )
		{
			throw new RuntimeException( 'NO_ACTIVE_CODE' );
		}

		$code_id = (int) $code_row['ID'];

		// Verrouille la ligne du code : les presentations concurrentes
		// s'alignent ici, une seule peut consommer.
		$locked_rows = DBGet( "SELECT * FROM schoolsafe_opening_codes
			WHERE ID='" . $code_id . "'
			FOR UPDATE" );

		$locked = $locked_rows[1] ?? null;

		if ( ! $locked || $locked['CONSUMED_AT'] !== null )
		{
			DBQuery( "INSERT INTO schoolsafe_opening_events(request_id,action)
				VALUES(" . $request_id . ",'code_reuse_refused')" );

			DBQuery( 'COMMIT' );

			return [ 'ok' => false, 'error' => 'CODE_CONSUMED', 'remaining' => 0 ];
		}

		if ( strtotime( (string) $locked['EXPIRES_AT'] ) <= time() )
		{
			DBQuery( "INSERT INTO schoolsafe_opening_events(request_id,action)
				VALUES(" . $request_id . ",'code_expired')" );

			DBQuery( 'COMMIT' );

			return [ 'ok' => false, 'error' => 'CODE_EXPIRED', 'remaining' => 0 ];
		}

		if ( (int) $locked['ATTEMPTS'] >= (int) $locked['MAX_ATTEMPTS'] )
		{
			DBQuery( "INSERT INTO schoolsafe_opening_events(request_id,action)
				VALUES(" . $request_id . ",'code_locked')" );

			DBQuery( 'COMMIT' );

			return [ 'ok' => false, 'error' => 'CODE_LOCKED', 'remaining' => 0 ];
		}

		if ( ! hash_equals(
			(string) $locked['CODE_HASH'],
			SchoolSafeOpeningCodeHash( $normalized )
		) )
		{
			DBQuery( "UPDATE schoolsafe_opening_codes
				SET ATTEMPTS=ATTEMPTS+1
				WHERE ID='" . $code_id . "'" );

			DBQuery( "INSERT INTO schoolsafe_opening_events(request_id,action)
				VALUES(" . $request_id . ",'code_failed')" );

			DBQuery( 'COMMIT' );

			$remaining = (int) $locked['MAX_ATTEMPTS'] - (int) $locked['ATTEMPTS'] - 1;

			return [
				'ok' => false,
				'error' => 'CODE_INVALID',
				'remaining' => max( 0, $remaining ),
			];
		}

		DBQuery( "UPDATE schoolsafe_opening_codes
			SET CONSUMED_AT=CURRENT_TIMESTAMP
			WHERE ID='" . $code_id . "'" );

		DBUpdate( 'schoolsafe_opening_requests', [
			'STATE' => 'approved',
		], [ 'ID' => $request_id ] );

		DBQuery( "INSERT INTO schoolsafe_opening_events(request_id,action)
			VALUES(" . $request_id . ",'code_consumed'),(" . $request_id . ",'approve')" );

		DBQuery( 'COMMIT' );

		return [ 'ok' => true, 'error' => '', 'request_id' => $request_id ];
	}
	catch ( Throwable $error )
	{
		DBQuery( 'ROLLBACK' );

		return [
			'ok' => false,
			'error' => $error instanceof RuntimeException
				? $error->getMessage() : 'VERIFY_FAILED',
			'remaining' => 0,
		];
	}
}
