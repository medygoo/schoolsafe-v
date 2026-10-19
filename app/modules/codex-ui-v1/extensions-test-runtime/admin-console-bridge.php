<?php
/**
 * SchoolSafe — Console bridge (AppSmith)
 *
 * Endpoint de service pour la console de gestion SchoolSafe (AppSmith).
 * Toutes les operations reutilisent STRICTEMENT les mecanismes natifs :
 *  - encrypt_password() (functions/Password.php) pour le hachage $6$ ;
 *  - meme INSERT staff que modules/Users/User.php (PROFILE_ID=1 => droits
 *    du role Administrator via profile_exceptions, aucune ligne de droits) ;
 *  - LAST_LOGIN laisse a NULL + FORCE_PASSWORD_CHANGE_ON_FIRST_LOGIN (config,
 *    ligne globale 0) => changement de mot de passe FORCE a la premiere
 *    connexion via l'ecran natif (ProgramFunctions/FirstLogin.fnc.php) ;
 *  - suspension = PROFILE 'none' (mecanisme natif No Access), reactivation =
 *    retour a 'admin', PROFILE_ID conserve ;
 *  - creation d'ecole = replique de modules/School_Setup/CopySchool.php
 *    (gabarit + identite SchoolSafe + auto-attribution optionnelle).
 *
 * Authentification : en-tete X-SchoolSafe-Console-Token egal a l'variable
 * d'environnement SCHOOLSAFE_CONSOLE_TOKEN. Jeton absent du serveur => 503
 * (echec ferme). Aucune session, aucun cookie : appel serveur a serveur.
 * Aucun secret n'est journalise. Le mot de passe temporaire est retourne
 * UNE SEULE FOIS dans la reponse.
 *
 * Lot additif : aucune modification du coeur Rosario.
 */

if ( $_SERVER['REQUEST_METHOD'] !== 'GET'
	&& $_SERVER['REQUEST_METHOD'] !== 'POST' )
{
	http_response_code( 405 );
	exit( '{"ok":false,"error":"METHOD_NOT_ALLOWED"}' );
}

header( 'Content-Type: application/json; charset=utf-8' );
header( 'X-Content-Type-Options: nosniff' );
header( 'Cache-Control: no-store' );

require_once __DIR__ . '/Warehouse.php';

/**
 * Reponse JSON et arret.
 */
function SchoolSafeConsoleReply( $status, $data )
{
	http_response_code( $status );

	exit( json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR ) );
}

// --- Authentification de service (echec ferme) ---
$console_token = getenv( 'SCHOOLSAFE_CONSOLE_TOKEN' );

if ( ! $console_token )
{
	SchoolSafeConsoleReply( 503, [ 'ok' => false, 'error' => 'DEPENDENCY_UNAVAILABLE' ] );
}

$provided_token = $_SERVER['HTTP_X_SCHOOLSAFE_CONSOLE_TOKEN'] ?? '';

if ( ! is_string( $provided_token )
	|| $provided_token === ''
	|| ! hash_equals( $console_token, $provided_token ) )
{
	SchoolSafeConsoleReply( 401, [ 'ok' => false, 'error' => 'TOKEN_REQUIRED' ] );
}

// --- Entree ---
$action = '';

$request = [];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' )
{
	$raw = file_get_contents( 'php://input' );

	try
	{
		$request = json_decode( $raw ?: '', true, 32, JSON_THROW_ON_ERROR );
	}
	catch ( JsonException $e )
	{
		$request = null;
	}

	if ( ! is_array( $request )
		|| empty( $request['action'] )
		|| ! is_string( $request['action'] ) )
	{
		SchoolSafeConsoleReply( 400, [ 'ok' => false, 'error' => 'INVALID_JSON' ] );
	}

	$action = $request['action'];
}
else
{
	$action = $_GET['action'] ?? '';
}

$syear = (int) Config( 'SYEAR' );

/**
 * Mot de passe temporaire lisible : 3 groupes de 4, alphabet sans ambiguite.
 */
function SchoolSafeConsoleTempPassword()
{
	$alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';

	$groups = [];

	for ( $g = 0; $g < 3; $g++ )
	{
		$group = '';

		for ( $i = 0; $i < 4; $i++ )
		{
			$group .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
		}

		$groups[] = $group;
	}

	return implode( '-', $groups );
}

/**
 * Unicite du USERNAME, meme controle que l'UI : staff (meme annee) + students,
 * insensible a la casse.
 */
function SchoolSafeConsoleUsernameTaken( $username, $syear )
{
	$escaped = DBEscapeString( $username );

	$result = DBQuery( "SELECT USERNAME FROM staff
		WHERE LOWER(USERNAME)='" . mb_strtolower( $escaped ) . "'
		AND SYEAR='" . (int) $syear . "'
		UNION
		SELECT USERNAME FROM students
		WHERE LOWER(USERNAME)='" . mb_strtolower( $escaped ) . "'" );

	return count( DBGet( $result ) ) > 0;
}

/**
 * Liste d'ecoles validees au format ',1,2,' (format natif staff.SCHOOLS).
 * Refuse toute ecole inexistante pour l'annee courante (echec ferme).
 */
function SchoolSafeConsoleSchoolsList( $school_ids, $syear )
{
	if ( ! is_array( $school_ids )
		|| count( $school_ids ) === 0
		|| count( $school_ids ) > 20 )
	{
		SchoolSafeConsoleReply( 400, [ 'ok' => false, 'error' => 'SCHOOLS_REQUIRED' ] );
	}

	$clean = [];

	foreach ( $school_ids as $school_id )
	{
		if ( ! is_numeric( $school_id )
			|| (int) $school_id < 1 )
		{
			SchoolSafeConsoleReply( 400, [ 'ok' => false, 'error' => 'SCHOOL_ID_INVALID' ] );
		}

		$clean[ (int) $school_id ] = true;
	}

	$exists = DBGet( DBQuery( "SELECT ID FROM schools
		WHERE SYEAR='" . (int) $syear . "'
		AND ID IN (" . implode( ',', array_keys( $clean ) ) . ")" ) );

	if ( count( $exists ) !== count( $clean ) )
	{
		SchoolSafeConsoleReply( 400, [ 'ok' => false, 'error' => 'SCHOOL_NOT_FOUND' ] );
	}

	return ',' . implode( ',', array_keys( $clean ) ) . ',';
}

/**
 * Compte cible par staff_id, profile admin attendu.
 */
function SchoolSafeConsoleFindAdmin( $staff_id, $syear )
{
	if ( ! is_numeric( $staff_id )
		|| (int) $staff_id < 1 )
	{
		SchoolSafeConsoleReply( 400, [ 'ok' => false, 'error' => 'STAFF_ID_INVALID' ] );
	}

	$admin = DBGet( DBQuery( "SELECT STAFF_ID,USERNAME,FIRST_NAME,LAST_NAME,EMAIL,PROFILE,PROFILE_ID,SCHOOLS,LAST_LOGIN
		FROM staff
		WHERE STAFF_ID='" . (int) $staff_id . "'
		AND SYEAR='" . (int) $syear . "'" ) );

	if ( ! $admin )
	{
		SchoolSafeConsoleReply( 404, [ 'ok' => false, 'error' => 'ACCOUNT_NOT_FOUND' ] );
	}

	return $admin[1];
}

switch ( $action )
{
	// Liste des comptes administrateurs de l'annee courante.
	case 'list-admins':
		$admins = DBGet( DBQuery( "SELECT STAFF_ID,USERNAME,FIRST_NAME,LAST_NAME,EMAIL,
			PROFILE,PROFILE_ID,SCHOOLS,LAST_LOGIN,FAILED_LOGIN
			FROM staff
			WHERE SYEAR='" . (int) $syear . "'
			AND PROFILE IN ('admin','none')
			AND (PROFILE_ID=1 OR PROFILE='none')
			ORDER BY LAST_NAME,FIRST_NAME" ) );

		// Etat reel de chaque compte (personne + ecoles) : une suspension par
		// etat et une suspension par profil No Access doivent se lire pareil.
		$state_ready = function_exists( 'SchoolSafeAccountReady' )
			&& SchoolSafeAccountReady();

		foreach ( $admins as $admin_key => $admin_row )
		{
			$admin_schools = array_values( array_filter( array_map( 'intval',
				explode( ',', (string) $admin_row['SCHOOLS'] ) ) ) );

			if ( ! $state_ready )
			{
				// Repli sans migration : le profil natif est la seule source.
				$admins[$admin_key]['STATE'] = $admin_row['PROFILE'] === 'none'
					? 'suspended' : 'active';
				$admins[$admin_key]['STATE_SOURCE'] = 'profile';
				$admins[$admin_key]['SCHOOL_STATES'] = [];
				$admins[$admin_key]['STATE_DETAIL'] = $admins[$admin_key]['STATE'];

				continue;
			}

			// État le plus restrictif de la personne : une école suspendue se
			// voit même si l'école listée en premier est active.
			$admin_states = SchoolSafeAccountStates( $admin_row['USERNAME'], $admin_schools );

			$admin_detail = [];

			foreach ( $admin_states as $state_entry )
			{
				$admin_detail[] = ( $state_entry['school_id'] > 0
					? 'ecole ' . $state_entry['school_id']
					: 'toutes les ecoles' ) . ' : ' . $state_entry['state'];
			}

			$admins[$admin_key]['STATE'] = SchoolSafeAccountWorstState( $admin_row['USERNAME'], $admin_schools );

			$admins[$admin_key]['STATE_SOURCE'] = 'account_state';

			// Détail école par école : la console affiche les deux colonnes.
			$admins[$admin_key]['SCHOOL_STATES'] = $admin_states;

			$admins[$admin_key]['STATE_DETAIL'] = $admin_detail
				? implode( ' ; ', $admin_detail ) : 'actif';
		}

		SchoolSafeConsoleReply( 200, [ 'ok' => true, 'data' => $admins ] );

	case 'create-admin':
		if ( $_SERVER['REQUEST_METHOD'] !== 'POST' )
		{
			SchoolSafeConsoleReply( 405, [ 'ok' => false, 'error' => 'METHOD_NOT_ALLOWED' ] );
		}

		$first_name = trim( (string) ( $request['first_name'] ?? '' ) );
		$last_name = trim( (string) ( $request['last_name'] ?? '' ) );
		$username = trim( (string) ( $request['username'] ?? '' ) );
		$email = trim( (string) ( $request['email'] ?? '' ) );

		if ( $first_name === ''
			|| mb_strlen( $first_name ) > 50
			|| $last_name === ''
			|| mb_strlen( $last_name ) > 50 )
		{
			SchoolSafeConsoleReply( 400, [ 'ok' => false, 'error' => 'NAME_REQUIRED' ] );
		}

		if ( $username === ''
			|| mb_strlen( $username ) > 100
			|| preg_match( '/[\x00-\x1f\x7f]/', $username ) )
		{
			SchoolSafeConsoleReply( 400, [ 'ok' => false, 'error' => 'USERNAME_INVALID' ] );
		}

		if ( $email !== ''
			&& ! filter_var( $email, FILTER_VALIDATE_EMAIL ) )
		{
			SchoolSafeConsoleReply( 400, [ 'ok' => false, 'error' => 'EMAIL_INVALID' ] );
		}

		if ( SchoolSafeConsoleUsernameTaken( $username, $syear ) )
		{
			SchoolSafeConsoleReply( 409, [ 'ok' => false, 'error' => 'USERNAME_TAKEN' ] );
		}

		$schools = SchoolSafeConsoleSchoolsList( $request['school_ids'] ?? null, $syear );

		$temp_password = SchoolSafeConsoleTempPassword();

		// Meme INSERT que modules/Users/User.php : PROFILE admin, PROFILE_ID 1
		// (droits du role via profile_exceptions), LAST_LOGIN NULL (premiere connexion).
		$staff_id = DBInsert( 'staff', [
			'SYEAR' => $syear,
			'FIRST_NAME' => $first_name,
			'LAST_NAME' => $last_name,
			'USERNAME' => $username,
			'PASSWORD' => encrypt_password( $temp_password ),
			'EMAIL' => $email,
			'PROFILE' => 'admin',
			'PROFILE_ID' => 1,
			'SCHOOLS' => $schools,
		], 'id' );

		SchoolSafeConsoleReply( 201, [
			'ok' => true,
			'data' => [
				'staff_id' => (int) $staff_id,
				'username' => $username,
				'temp_password' => $temp_password,
				'first_login_force' => true,
			],
		] );

	case 'reset-password':
		if ( $_SERVER['REQUEST_METHOD'] !== 'POST' )
		{
			SchoolSafeConsoleReply( 405, [ 'ok' => false, 'error' => 'METHOD_NOT_ALLOWED' ] );
		}

		$admin = SchoolSafeConsoleFindAdmin( $request['staff_id'] ?? 0, $syear );

		if ( $admin['PROFILE'] === 'none' )
		{
			SchoolSafeConsoleReply( 409, [ 'ok' => false, 'error' => 'ACCOUNT_SUSPENDED' ] );
		}

		$temp_password = SchoolSafeConsoleTempPassword();

		// Remise a NULL de LAST_LOGIN : le compte repasse par l'ecran natif
		// de premiere connexion (changement de mot de passe force).
		// DBQuery explicite : DBUpdate ignorerait la valeur NULL.
		DBQuery( "UPDATE staff
			SET PASSWORD='" . DBEscapeString( encrypt_password( $temp_password ) ) . "',
			LAST_LOGIN=NULL,FAILED_LOGIN=0
			WHERE STAFF_ID='" . (int) $admin['STAFF_ID'] . "'
			AND SYEAR='" . (int) $syear . "'" );

		SchoolSafeConsoleReply( 200, [
			'ok' => true,
			'data' => [
				'staff_id' => (int) $admin['STAFF_ID'],
				'username' => $admin['USERNAME'],
				'temp_password' => $temp_password,
				'first_login_force' => true,
			],
		] );

	case 'suspend':
	case 'activate':
		if ( $_SERVER['REQUEST_METHOD'] !== 'POST' )
		{
			SchoolSafeConsoleReply( 405, [ 'ok' => false, 'error' => 'METHOD_NOT_ALLOWED' ] );
		}

		$admin = SchoolSafeConsoleFindAdmin( $request['staff_id'] ?? 0, $syear );

		$school_id = (int) ( $request['school_id'] ?? 0 );

		if ( $school_id < 0 )
		{
			SchoolSafeConsoleReply( 400, [ 'ok' => false, 'error' => 'SCHOOL_ID_INVALID' ] );
		}

		if ( $school_id > 0
			&& ! DBGetOne( "SELECT ID FROM schools
				WHERE ID='" . $school_id . "' AND SYEAR='" . (int) $syear . "'" ) )
		{
			SchoolSafeConsoleReply( 400, [ 'ok' => false, 'error' => 'SCHOOL_NOT_FOUND' ] );
		}

		/**
		 * Etat de compte (migration 014) = source de verite quand elle existe.
		 *
		 * Il porte sur la personne ET l'ecole et incremente l'epoque de session,
		 * donc une suspension coupe les sessions deja ouvertes. Le profil natif
		 * `none` n'est plus utilise que si cette couche est absente : avant, il
		 * etait pose seul, ce qui laissait la session ouverte et bloquait la
		 * reactivation par etat (le profil restait « No Access »).
		 */
		$state_ready = function_exists( 'SchoolSafeAccountReady' )
			&& SchoolSafeAccountReady();

		$reason = trim( (string) ( $request['reason'] ?? '' ) );

		$until = trim( (string) ( $request['until'] ?? '' ) );

		if ( $until !== ''
			&& ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}( [0-9]{2}:[0-9]{2}(:[0-9]{2})?)?$/', $until ) )
		{
			SchoolSafeConsoleReply( 400, [ 'ok' => false, 'error' => 'UNTIL_INVALID' ] );
		}

		if ( $action === 'suspend' )
		{
			$current = $state_ready
				? SchoolSafeAccountEffective( $admin['USERNAME'], [ $school_id ] )
				: ( $admin['PROFILE'] === 'none' ? 'suspended' : 'active' );

			if ( $current === 'suspended' )
			{
				SchoolSafeConsoleReply( 409, [ 'ok' => false, 'error' => 'ALREADY_SUSPENDED' ] );
			}

			if ( $state_ready )
			{
				if ( ! SchoolSafeAccountSet( $admin['USERNAME'], $school_id, 'suspended',
					$reason ?: 'Suspension PRODELI', $until ?: null, true ) )
				{
					SchoolSafeConsoleReply( 500, [ 'ok' => false, 'error' => 'STATE_NOT_SAVED' ] );
				}
			}
			else
			{
				// Repli : mecanisme natif No Access, PROFILE_ID conserve.
				DBUpdate( 'staff', [ 'PROFILE' => 'none' ],
					[ 'STAFF_ID' => (int) $admin['STAFF_ID'], 'SYEAR' => $syear ] );
			}

			SchoolSafeConsoleReply( 200, [
				'ok' => true,
				'data' => [
					'staff_id' => (int) $admin['STAFF_ID'],
					'username' => $admin['USERNAME'],
					'status' => 'suspended',
					'state' => 'suspended',
					'school_id' => $school_id,
					'until' => $until ?: null,
					'session_revoked' => $state_ready,
					'session_epoch' => $state_ready ? SchoolSafeAccountEpoch( $admin['USERNAME'] ) : null,
					'state_source' => $state_ready ? 'account_state' : 'profile',
				],
			] );
		}

		// Reactivation.
		$current = $state_ready
			? SchoolSafeAccountEffective( $admin['USERNAME'], [ $school_id ] )
			: ( $admin['PROFILE'] === 'none' ? 'suspended' : 'active' );

		if ( $current === 'active' && $admin['PROFILE'] !== 'none' )
		{
			SchoolSafeConsoleReply( 409, [ 'ok' => false, 'error' => 'ALREADY_ACTIVE' ] );
		}

		if ( $state_ready )
		{
			SchoolSafeAccountSet( $admin['USERNAME'], $school_id, 'active',
				$reason ?: 'Reactivation PRODELI', null, true );
		}

		// Compte ferme par l'ancien mecanisme (profil No Access) : il resterait
		// bloque malgre un etat actif, on retablit donc le profil admin.
		if ( $admin['PROFILE'] === 'none' )
		{
			DBUpdate( 'staff', [ 'PROFILE' => 'admin' ],
				[ 'STAFF_ID' => (int) $admin['STAFF_ID'], 'SYEAR' => $syear ] );
		}

		SchoolSafeConsoleReply( 200, [
			'ok' => true,
			'data' => [
				'staff_id' => (int) $admin['STAFF_ID'],
				'username' => $admin['USERNAME'],
				'status' => 'active',
				'state' => 'active',
				'school_id' => $school_id,
				'session_revoked' => $state_ready,
				'session_epoch' => $state_ready ? SchoolSafeAccountEpoch( $admin['USERNAME'] ) : null,
				'state_source' => $state_ready ? 'account_state' : 'profile',
			],
		] );

	// Creation d'ecole : logique partagee avec la console native
	// (ProgramFunctions/SchoolSafeSchoolSetup.fnc.php), pour que les deux
	// chemins creent exactement la meme ecole.
	case 'create-school':
		if ( $_SERVER['REQUEST_METHOD'] !== 'POST' )
		{
			SchoolSafeConsoleReply( 405, [ 'ok' => false, 'error' => 'METHOD_NOT_ALLOWED' ] );
		}

		$created = SchoolSafeSchoolCreate( $request, $syear );

		if ( empty( $created['ok'] ) )
		{
			$status = [
				'TITLE_INVALID' => 400,
				'IDENTITY_REQUIRED' => 400,
				'TEMPLATE_NOT_FOUND' => 404,
				'TEMPLATE_CALENDAR_INVALID' => 409,
				'CALENDAR_COPY_FAILED' => 500,
				'SCHOOL_NOT_CREATED' => 500,
			][ $created['error'] ?? '' ] ?? 500;

			SchoolSafeConsoleReply( $status, [
				'ok' => false,
				'error' => $created['error'] ?? 'SCHOOL_NOT_CREATED',
				'data' => [
					'template_school_id' => (int) ( $request['template_school_id'] ?? 1 ),
					'issues' => $created['issues'] ?? [],
				],
			] );
		}

		SchoolSafeConsoleReply( 201, [
			'ok' => true,
			'data' => [
				'school_id' => (int) $created['school_id'],
				'title' => trim( (string) ( $request['title'] ?? '' ) ),
				'syear' => $syear,
				'note' => 'Calendriers de presence, cours et eleves restent a creer dans SchoolSafe.',
			],
		] );

	default:
		SchoolSafeConsoleReply( 400, [ 'ok' => false, 'error' => 'ACTION_UNKNOWN' ] );
}
