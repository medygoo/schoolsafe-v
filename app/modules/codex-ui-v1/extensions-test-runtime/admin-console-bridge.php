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

		if ( $action === 'suspend' )
		{
			if ( $admin['PROFILE'] === 'none' )
			{
				SchoolSafeConsoleReply( 409, [ 'ok' => false, 'error' => 'ALREADY_SUSPENDED' ] );
			}

			// Mecanisme natif No Access ; PROFILE_ID conserve pour la reactivation.
			DBUpdate( 'staff', [ 'PROFILE' => 'none' ],
				[ 'STAFF_ID' => (int) $admin['STAFF_ID'], 'SYEAR' => $syear ] );

			SchoolSafeConsoleReply( 200, [ 'ok' => true, 'data' => [ 'staff_id' => (int) $admin['STAFF_ID'], 'status' => 'suspended' ] ] );
		}

		if ( $admin['PROFILE'] !== 'none' )
		{
			SchoolSafeConsoleReply( 409, [ 'ok' => false, 'error' => 'ALREADY_ACTIVE' ] );
		}

		DBUpdate( 'staff', [ 'PROFILE' => 'admin' ],
			[ 'STAFF_ID' => (int) $admin['STAFF_ID'], 'SYEAR' => $syear ] );

		SchoolSafeConsoleReply( 200, [ 'ok' => true, 'data' => [ 'staff_id' => (int) $admin['STAFF_ID'], 'status' => 'active' ] ] );

	// Creation d'ecole : replique de CopySchool.php (gabarit + identite).
	case 'create-school':
		if ( $_SERVER['REQUEST_METHOD'] !== 'POST' )
		{
			SchoolSafeConsoleReply( 405, [ 'ok' => false, 'error' => 'METHOD_NOT_ALLOWED' ] );
		}

		$title = trim( (string) ( $request['title'] ?? '' ) );
		$email = trim( (string) ( $request['email'] ?? '' ) );
		$slogan = trim( (string) ( $request['slogan'] ?? '' ) );

		if ( $title === ''
			|| mb_strlen( $title ) > 100
			|| preg_match( '/[\x00-\x1f\x7f]/', $title ) )
		{
			SchoolSafeConsoleReply( 400, [ 'ok' => false, 'error' => 'TITLE_INVALID' ] );
		}

		if ( $email === ''
			|| ! filter_var( $email, FILTER_VALIDATE_EMAIL )
			|| $slogan === ''
			|| mb_strlen( $slogan ) > 180 )
		{
			SchoolSafeConsoleReply( 400, [ 'ok' => false, 'error' => 'IDENTITY_REQUIRED' ] );
		}

		$template_id = (int) ( $request['template_school_id'] ?? 1 );

		$template = DBGet( DBQuery( "SELECT ID,REPORTING_GP_SCALE FROM schools
			WHERE ID='" . $template_id . "'
			AND SYEAR='" . (int) $syear . "'" ) );

		if ( ! $template )
		{
			SchoolSafeConsoleReply( 404, [ 'ok' => false, 'error' => 'TEMPLATE_NOT_FOUND' ] );
		}

		$template_id = (int) $template[1]['ID'];

		// Le gabarit doit avoir un calendrier coherent : sans cela, l'ecole
		// recopiee naitrait cassee (periodes sans parent) et tous ses ecrans
		// echoueraient. Mieux vaut refuser avant toute ecriture que reparer
		// apres coup une ecole deja livree.
		$template_issues = SchoolSafeSchoolCalendarIssues( $template_id, $syear );

		if ( $template_issues )
		{
			SchoolSafeConsoleReply( 409, [
				'ok' => false,
				'error' => 'TEMPLATE_CALENDAR_INVALID',
				'data' => [
					'template_school_id' => $template_id,
					'issues' => array_column( $template_issues, 'label' ),
				],
			] );
		}

		// 1) Ecole : meme INSERT que CopySchool.php, gabarit pour le scale.
		DBQuery( "INSERT INTO schools (SYEAR,TITLE,REPORTING_GP_SCALE,ADDRESS,CITY,PHONE,WWW_ADDRESS,SCHOOL_NUMBER,SHORT_NAME)
			SELECT SYEAR,'" . DBEscapeString( $title ) . "',REPORTING_GP_SCALE,
			'" . DBEscapeString( trim( (string) ( $request['address'] ?? '' ) ) ) . "',
			'" . DBEscapeString( trim( (string) ( $request['city'] ?? '' ) ) ) . "',
			'" . DBEscapeString( trim( (string) ( $request['phone'] ?? '' ) ) ) . "',
			'" . DBEscapeString( trim( (string) ( $request['www_address'] ?? '' ) ) ) . "',
			'" . DBEscapeString( trim( (string) ( $request['school_number'] ?? '' ) ) ) . "',
			'" . DBEscapeString( mb_substr( trim( (string) ( $request['short_name'] ?? '' ) ), 0, 25 ) ) . "'
			FROM schools WHERE ID='" . $template_id . "' AND SYEAR='" . (int) $syear . "'" );

		$new_school_id = (int) DBLastInsertID();

		// 2) Configuration heritee du gabarit (comme CopySchool), puis identite SchoolSafe.
		DBQuery( "INSERT INTO config (SCHOOL_ID,TITLE,CONFIG_VALUE,CREATED_AT,UPDATED_AT)
			SELECT " . $new_school_id . ",TITLE,CONFIG_VALUE,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP
			FROM config WHERE SCHOOL_ID='" . $template_id . "'" );

		foreach ( [
			'SCHOOLSAFE_SCHOOL_EMAIL' => $email,
			'SCHOOLSAFE_SCHOOL_SLOGAN' => $slogan,
		] as $config_title => $config_value )
		{
			DBUpdate( 'config', [ 'CONFIG_VALUE' => $config_value ], [
				'SCHOOL_ID' => $new_school_id,
				'TITLE' => $config_title,
			] );

			DBQuery( "INSERT INTO config (SCHOOL_ID,TITLE,CONFIG_VALUE)
				SELECT " . $new_school_id . ",'" . $config_title . "','" . DBEscapeString( $config_value ) . "'
				WHERE NOT EXISTS (SELECT 1 FROM config
					WHERE SCHOOL_ID=" . $new_school_id . " AND TITLE='" . $config_title . "')" );
		}

		// 3) Donnees de base copiees du gabarit : niveaux, periodes, periodes de notation.
		DBQuery( "INSERT INTO school_gradelevels (SCHOOL_ID,TITLE,SHORT_NAME,SORT_ORDER)
			SELECT " . $new_school_id . ",TITLE,SHORT_NAME,SORT_ORDER
			FROM school_gradelevels WHERE SCHOOL_ID='" . $template_id . "'" );

		DBQuery( "INSERT INTO school_periods (SYEAR,SCHOOL_ID,TITLE,SHORT_NAME,SORT_ORDER,LENGTH,ATTENDANCE)
			SELECT SYEAR," . $new_school_id . ",TITLE,SHORT_NAME,SORT_ORDER,LENGTH,ATTENDANCE
			FROM school_periods WHERE SCHOOL_ID='" . $template_id . "' AND SYEAR='" . (int) $syear . "'" );

		// PARENT_ID est recopie tel quel, puis remappe vers les identifiants de la
		// nouvelle ecole : c'est ce que fait CopySchool.php. L'omettre laissait
		// PARENT_ID a NULL et le remappage ne trouvait jamais rien — d'ou des
		// periodes a plat a chaque creation. Les dates de saisie sont recopiees
		// pour la meme raison (calendrier du gabarit a l'identique).
		DBQuery( "INSERT INTO school_marking_periods (SYEAR,MP,SCHOOL_ID,TITLE,SHORT_NAME,SORT_ORDER,START_DATE,END_DATE,POST_START_DATE,POST_END_DATE,DOES_GRADES,DOES_COMMENTS,ROLLOVER_ID,PARENT_ID)
			SELECT SYEAR,MP," . $new_school_id . ",TITLE,SHORT_NAME,SORT_ORDER,START_DATE,END_DATE,POST_START_DATE,POST_END_DATE,DOES_GRADES,DOES_COMMENTS,MARKING_PERIOD_ID,PARENT_ID
			FROM school_marking_periods WHERE SCHOOL_ID='" . $template_id . "' AND SYEAR='" . (int) $syear . "'" );

		DBQuery( "UPDATE school_marking_periods mp
			SET PARENT_ID=(SELECT mp2.MARKING_PERIOD_ID FROM school_marking_periods mp2
				WHERE mp2.SCHOOL_ID='" . $new_school_id . "' AND mp2.SYEAR='" . (int) $syear . "'
				AND mp2.ROLLOVER_ID=mp.PARENT_ID)
			WHERE mp.SCHOOL_ID='" . $new_school_id . "' AND mp.SYEAR='" . (int) $syear . "'
			AND mp.PARENT_ID IS NOT NULL" );

		// Garde-fou : la copie doit produire un calendrier coherent. Sinon, rien
		// de partiel ne reste en base (aucune ecole a moitie creee).
		$copy_issues = SchoolSafeSchoolCalendarIssues( $new_school_id, $syear );

		if ( $copy_issues )
		{
			DBQuery( "DELETE FROM config WHERE SCHOOL_ID='" . $new_school_id . "'" );
			DBQuery( "DELETE FROM school_gradelevels WHERE SCHOOL_ID='" . $new_school_id . "'" );
			DBQuery( "DELETE FROM school_periods WHERE SCHOOL_ID='" . $new_school_id . "'" );
			DBQuery( "DELETE FROM school_marking_periods WHERE SCHOOL_ID='" . $new_school_id . "'" );
			DBQuery( "DELETE FROM schools WHERE ID='" . $new_school_id . "' AND SYEAR='" . (int) $syear . "'" );

			SchoolSafeConsoleReply( 500, [
				'ok' => false,
				'error' => 'CALENDAR_COPY_FAILED',
				'data' => [ 'issues' => array_column( $copy_issues, 'label' ) ],
			] );
		}

		// 4) Auto-attribution optionnelle (comme CopySchool pour l'admin createur).
		$assign_to = (int) ( $request['assign_to_staff_id'] ?? 0 );

		if ( $assign_to > 0 )
		{
			DBQuery( "UPDATE staff
				SET SCHOOLS=CONCAT(TRIM(TRAILING ',' FROM SCHOOLS),'," . $new_school_id . ",')
				WHERE STAFF_ID='" . $assign_to . "' AND SYEAR='" . (int) $syear . "'
				AND SCHOOLS IS NOT NULL
				AND POSITION('," . $new_school_id . ",' IN SCHOOLS)=0" );
		}

		SchoolSafeConsoleReply( 201, [
			'ok' => true,
			'data' => [
				'school_id' => $new_school_id,
				'title' => $title,
				'syear' => $syear,
				'note' => 'Calendriers de presence, cours et eleves restent a creer dans SchoolSafe.',
			],
		] );

	default:
		SchoolSafeConsoleReply( 400, [ 'ok' => false, 'error' => 'ACTION_UNKNOWN' ] );
}
