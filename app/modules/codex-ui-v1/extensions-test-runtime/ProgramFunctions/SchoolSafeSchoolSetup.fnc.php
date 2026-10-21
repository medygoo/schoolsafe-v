<?php
/**
 * SchoolSafe — création d'école et direction d'école.
 *
 * Deux responsabilités, toutes deux additives (aucune modification du coeur
 * Rosario) :
 *
 *  - SchoolSafeSchoolCreate() : crée une école à partir d'un gabarit, comme
 *    modules/School_Setup/CopySchool.php (écoles, configuration, niveaux,
 *    périodes, périodes de notation), avec contrôle du calendrier AVANT et
 *    APRÈS la copie. En cas d'échec, rien de partiel ne reste en base.
 *    La console native et le pont de service appellent la MÊME fonction.
 *
 *  - SchoolSafeLeadership* : qui dirige l'école (directeur principal et
 *    adjoints, table schoolsafe_school_leadership, migration 018). Aucun
 *    droit n'est accordé ici : les droits restent ceux du profil nommé
 *    « Directeur » (migration 015).
 *
 * Les fonctions renvoient des tableaux d'erreur, jamais une sortie HTTP :
 * chaque appelant décide de sa réponse (page HTML ou JSON de service).
 */

/**
 * La table de direction existe-t-elle ? (mémoïsé par requête)
 */
function SchoolSafeLeadershipReady()
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
			WHERE table_name='schoolsafe_school_leadership'" );
	}
	catch ( Throwable $error )
	{
		$ready = false;
	}

	return $ready;
}

/**
 * Direction d'une école, principal d'abord puis adjoints.
 *
 * @param int $school_id École.
 * @param int $syear     Année scolaire.
 *
 * @return array Lignes STAFF_ID, ROLE, SINCE, USERNAME, FIRST_NAME, LAST_NAME.
 */
function SchoolSafeLeadershipStaff( $school_id, $syear )
{
	if ( ! SchoolSafeLeadershipReady() )
	{
		return [];
	}

	$syear = (int) $syear;

	$school_id = (int) $school_id;

	try
	{
		$rows = DBGet( DBQuery( "SELECT l.STAFF_ID,l.ROLE,l.SINCE,s.USERNAME,
			s.FIRST_NAME,s.LAST_NAME
			FROM schoolsafe_school_leadership l, staff s
			WHERE l.SCHOOL_ID='" . $school_id . "'
			AND s.STAFF_ID=l.STAFF_ID
			AND s.SYEAR='" . $syear . "'
			ORDER BY CASE WHEN l.ROLE='principal' THEN 0 ELSE 1 END,
				s.LAST_NAME,s.FIRST_NAME" ) );
	}
	catch ( Throwable $error )
	{
		return [];
	}

	return $rows ?: [];
}

/**
 * Directeur principal d'une école (0 si aucun).
 */
function SchoolSafeLeadershipPrincipal( $school_id )
{
	if ( ! SchoolSafeLeadershipReady() )
	{
		return 0;
	}

	try
	{
		return (int) DBGetOne( "SELECT STAFF_ID FROM schoolsafe_school_leadership
			WHERE SCHOOL_ID='" . (int) $school_id . "'
			AND ROLE='principal'" );
	}
	catch ( Throwable $error )
	{
		return 0;
	}
}

/**
 * Attribue un rôle de direction à une personne.
 *
 * Un seul principal par école : l'ancien est rétrogradé adjoint avant
 * l'écriture, l'index unique partiel ne peut donc jamais bloquer la console.
 *
 * @param int    $school_id École.
 * @param int    $staff_id  Personne.
 * @param string $role      'principal' ou 'adjoint'.
 * @param string $actor     Compte à l'origine du changement.
 *
 * @return bool
 */
function SchoolSafeLeadershipSet( $school_id, $staff_id, $role, $actor = '' )
{
	if ( ! SchoolSafeLeadershipReady() )
	{
		return false;
	}

	$school_id = (int) $school_id;

	$staff_id = (int) $staff_id;

	if ( $school_id < 1
		|| $staff_id < 1
		|| ! in_array( $role, [ 'principal', 'adjoint' ], true ) )
	{
		return false;
	}

	$actor = mb_substr( (string) $actor, 0, 100 );

	try
	{
		if ( $role === 'principal' )
		{
			DBQuery( "UPDATE schoolsafe_school_leadership
				SET ROLE='adjoint',CHANGED_BY='" . DBEscapeString( $actor ) . "',
				SINCE=CURRENT_TIMESTAMP
				WHERE SCHOOL_ID='" . $school_id . "'
				AND ROLE='principal'
				AND STAFF_ID<>'" . $staff_id . "'" );
		}

		DBQuery( "INSERT INTO schoolsafe_school_leadership
			(SCHOOL_ID,STAFF_ID,ROLE,SINCE,CHANGED_BY)
			VALUES ('" . $school_id . "','" . $staff_id . "','" . DBEscapeString( $role ) . "',
			CURRENT_TIMESTAMP,'" . DBEscapeString( $actor ) . "')
			ON CONFLICT (SCHOOL_ID,STAFF_ID)
			DO UPDATE SET ROLE=EXCLUDED.ROLE,CHANGED_BY=EXCLUDED.CHANGED_BY,
				SINCE=CURRENT_TIMESTAMP" );
	}
	catch ( Throwable $error )
	{
		return false;
	}

	return true;
}

/**
 * Retire une personne de la direction d'une école.
 */
function SchoolSafeLeadershipRemove( $school_id, $staff_id )
{
	if ( ! SchoolSafeLeadershipReady() )
	{
		return false;
	}

	try
	{
		DBQuery( "DELETE FROM schoolsafe_school_leadership
			WHERE SCHOOL_ID='" . (int) $school_id . "'
			AND STAFF_ID='" . (int) $staff_id . "'" );
	}
	catch ( Throwable $error )
	{
		return false;
	}

	return true;
}

/**
 * Prochain identifiant libre d'école pour l'année (même règle que Rosario).
 */
function SchoolSafeSchoolNextId()
{
	return (int) DBGetOne( "SELECT COALESCE(MAX(ID),0)+1 FROM schools" );
}

/**
 * Crée une école à partir d'un gabarit.
 *
 * @param array $input title, email, slogan, short_name, address, city, phone,
 *                     www_address, school_number, template_school_id,
 *                     assign_to_staff_id.
 * @param int   $syear Année scolaire.
 *
 * @return array ok, error, issues, school_id.
 */
function SchoolSafeSchoolCreate( $input, $syear )
{
	$syear = (int) $syear;

	$title = trim( (string) ( $input['title'] ?? '' ) );

	$email = trim( (string) ( $input['email'] ?? '' ) );

	$slogan = trim( (string) ( $input['slogan'] ?? '' ) );

	$short_name = mb_substr( trim( (string) ( $input['short_name'] ?? '' ) ), 0, 25 );

	if ( $title === ''
		|| mb_strlen( $title ) > 100
		|| preg_match( '/[\x00-\x1f\x7f]/', $title ) )
	{
		return [ 'ok' => false, 'error' => 'TITLE_INVALID' ];
	}

	if ( $email === ''
		|| ! filter_var( $email, FILTER_VALIDATE_EMAIL )
		|| $slogan === ''
		|| mb_strlen( $slogan ) > 180 )
	{
		return [ 'ok' => false, 'error' => 'IDENTITY_REQUIRED' ];
	}

	$template_id = (int) ( $input['template_school_id'] ?? 1 );

	$template = DBGet( DBQuery( "SELECT ID,REPORTING_GP_SCALE FROM schools
		WHERE ID='" . $template_id . "'
		AND SYEAR='" . $syear . "'" ) );

	if ( ! $template )
	{
		return [ 'ok' => false, 'error' => 'TEMPLATE_NOT_FOUND' ];
	}

	$template_id = (int) $template[1]['ID'];

	// Un gabarit au calendrier incohérent donnerait une école cassée :
	// mieux vaut refuser avant toute écriture que réparer une école livrée.
	$template_issues = function_exists( 'SchoolSafeSchoolCalendarIssues' )
		? SchoolSafeSchoolCalendarIssues( $template_id, $syear )
		: [];

	if ( $template_issues )
	{
		return [
			'ok' => false,
			'error' => 'TEMPLATE_CALENDAR_INVALID',
			'issues' => array_column( $template_issues, 'label' ),
		];
	}

	// 1) École : même INSERT que CopySchool.php, gabarit pour le barème.
	DBQuery( "INSERT INTO schools (SYEAR,TITLE,REPORTING_GP_SCALE,ADDRESS,CITY,PHONE,WWW_ADDRESS,SCHOOL_NUMBER,SHORT_NAME)
		SELECT SYEAR,'" . DBEscapeString( $title ) . "',REPORTING_GP_SCALE,
		'" . DBEscapeString( trim( (string) ( $input['address'] ?? '' ) ) ) . "',
		'" . DBEscapeString( trim( (string) ( $input['city'] ?? '' ) ) ) . "',
		'" . DBEscapeString( trim( (string) ( $input['phone'] ?? '' ) ) ) . "',
		'" . DBEscapeString( trim( (string) ( $input['www_address'] ?? '' ) ) ) . "',
		'" . DBEscapeString( trim( (string) ( $input['school_number'] ?? '' ) ) ) . "',
		'" . DBEscapeString( $short_name ) . "'
		FROM schools WHERE ID='" . $template_id . "' AND SYEAR='" . $syear . "'" );

	$new_school_id = (int) DBLastInsertID();

	if ( $new_school_id < 1 )
	{
		return [ 'ok' => false, 'error' => 'SCHOOL_NOT_CREATED' ];
	}

	// 2) Configuration héritée du gabarit, puis identité SchoolSafe.
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

	// 3) Données de base copiées du gabarit.
	DBQuery( "INSERT INTO school_gradelevels (SCHOOL_ID,TITLE,SHORT_NAME,SORT_ORDER)
		SELECT " . $new_school_id . ",TITLE,SHORT_NAME,SORT_ORDER
		FROM school_gradelevels WHERE SCHOOL_ID='" . $template_id . "'" );

	DBQuery( "INSERT INTO school_periods (SYEAR,SCHOOL_ID,TITLE,SHORT_NAME,SORT_ORDER,LENGTH,ATTENDANCE)
		SELECT SYEAR," . $new_school_id . ",TITLE,SHORT_NAME,SORT_ORDER,LENGTH,ATTENDANCE
		FROM school_periods WHERE SCHOOL_ID='" . $template_id . "' AND SYEAR='" . $syear . "'" );

	// PARENT_ID est recopié tel quel puis remappé vers les identifiants de la
	// nouvelle école (comme CopySchool.php) : sans ce remappage, les périodes
	// de notation naissent à plat.
	DBQuery( "INSERT INTO school_marking_periods (SYEAR,MP,SCHOOL_ID,TITLE,SHORT_NAME,SORT_ORDER,START_DATE,END_DATE,POST_START_DATE,POST_END_DATE,DOES_GRADES,DOES_COMMENTS,ROLLOVER_ID,PARENT_ID)
		SELECT SYEAR,MP," . $new_school_id . ",TITLE,SHORT_NAME,SORT_ORDER,START_DATE,END_DATE,POST_START_DATE,POST_END_DATE,DOES_GRADES,DOES_COMMENTS,MARKING_PERIOD_ID,PARENT_ID
		FROM school_marking_periods WHERE SCHOOL_ID='" . $template_id . "' AND SYEAR='" . $syear . "'" );

	DBQuery( "UPDATE school_marking_periods mp
		SET PARENT_ID=(SELECT mp2.MARKING_PERIOD_ID FROM school_marking_periods mp2
			WHERE mp2.SCHOOL_ID='" . $new_school_id . "' AND mp2.SYEAR='" . $syear . "'
			AND mp2.ROLLOVER_ID=mp.PARENT_ID)
		WHERE mp.SCHOOL_ID='" . $new_school_id . "' AND mp.SYEAR='" . $syear . "'
		AND mp.PARENT_ID IS NOT NULL" );

	// Garde-fou : la copie doit produire un calendrier cohérent, sinon rien de
	// partiel ne reste en base (aucune école à moitié créée).
	$copy_issues = function_exists( 'SchoolSafeSchoolCalendarIssues' )
		? SchoolSafeSchoolCalendarIssues( $new_school_id, $syear )
		: [];

	if ( $copy_issues )
	{
		SchoolSafeSchoolCreateCleanup( $new_school_id, $syear );

		return [
			'ok' => false,
			'error' => 'CALENDAR_COPY_FAILED',
			'issues' => array_column( $copy_issues, 'label' ),
		];
	}

	// 4) Attribution optionnelle au créateur (comme CopySchool).
	$assign_to = (int) ( $input['assign_to_staff_id'] ?? 0 );

	if ( $assign_to > 0 )
	{
		DBQuery( "UPDATE staff
			SET SCHOOLS=CONCAT(TRIM(TRAILING ',' FROM SCHOOLS),'," . $new_school_id . ",')
			WHERE STAFF_ID='" . $assign_to . "' AND SYEAR='" . $syear . "'
			AND SCHOOLS IS NOT NULL
			AND POSITION('," . $new_school_id . ",' IN SCHOOLS)=0" );
	}

	return [ 'ok' => true, 'error' => '', 'school_id' => $new_school_id ];
}

/**
 * Efface une école créée par erreur (copie incomplète), dans l'ordre des
 * dépendances. Ne touche à aucune donnée d'une école préexistante.
 */
function SchoolSafeSchoolCreateCleanup( $school_id, $syear )
{
	$school_id = (int) $school_id;

	$syear = (int) $syear;

	if ( $school_id < 1 )
	{
		return;
	}

	foreach ( [
		"DELETE FROM config WHERE SCHOOL_ID='" . $school_id . "'",
		"DELETE FROM school_gradelevels WHERE SCHOOL_ID='" . $school_id . "'",
		"DELETE FROM school_periods WHERE SCHOOL_ID='" . $school_id . "' AND SYEAR='" . $syear . "'",
		"DELETE FROM school_marking_periods WHERE SCHOOL_ID='" . $school_id . "' AND SYEAR='" . $syear . "'",
		"DELETE FROM schoolsafe_school_state WHERE SCHOOL_ID='" . $school_id . "'",
		"DELETE FROM schoolsafe_school_leadership WHERE SCHOOL_ID='" . $school_id . "'",
		"DELETE FROM schools WHERE ID='" . $school_id . "' AND SYEAR='" . $syear . "'",
	] as $statement )
	{
		try
		{
			DBQuery( $statement );
		}
		catch ( Throwable $error )
		{
			// La table facultative peut ne pas exister : la suppression continue.
		}
	}
}

/**
 * Mot de passe temporaire lisible : 3 groupes de 4, alphabet sans ambiguïté.
 */
function SchoolSafeSetupTempPassword()
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
 * Profil nommé « Directeur » (migration 015). 0 s'il n'existe pas.
 */
function SchoolSafeDirectorProfileId()
{
	try
	{
		return (int) DBGetOne( "SELECT ID FROM user_profiles
			WHERE TITLE='Directeur'" );
	}
	catch ( Throwable $error )
	{
		return 0;
	}
}

/**
 * Crée un administrateur d'école (profil « Directeur ») et le rattache à la
 * direction de son école.
 *
 * @param array $input first_name, last_name, username, email, school_id,
 *                     principal (bool).
 * @param int   $syear Année scolaire.
 * @param string $actor Compte à l'origine de la création.
 *
 * Owns a PostgreSQL transaction; refuses an already open caller transaction.
 * Account, leadership and success audit are committed together. An explicitly
 * requested principal never replaces an existing principal. Controller must
 * check actor permissions/CSRF before calling; no identity is linked here.
 *
 * @return array ok, error, staff_id, username, temp_password, role.
 */
function SchoolSafeCreateDirector( $input, $syear, $actor = '' )
{
	$syear = (int) $syear;

	$first_name = trim( (string) ( $input['first_name'] ?? '' ) );

	$last_name = trim( (string) ( $input['last_name'] ?? '' ) );

	$username = trim( (string) ( $input['username'] ?? '' ) );

	$email = trim( (string) ( $input['email'] ?? '' ) );

	$school_id = (int) ( $input['school_id'] ?? 0 );

	if ( $first_name === '' || mb_strlen( $first_name ) > 50
		|| $last_name === '' || mb_strlen( $last_name ) > 50 )
	{
		return [ 'ok' => false, 'error' => 'NAME_REQUIRED' ];
	}

	if ( $username === '' || mb_strlen( $username ) > 100
		|| preg_match( '/[\x00-\x1f\x7f]/', $username ) )
	{
		return [ 'ok' => false, 'error' => 'USERNAME_INVALID' ];
	}

	if ( mb_strlen( $email ) > 255
		|| ( $email !== '' && ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) )
	{
		return [ 'ok' => false, 'error' => 'EMAIL_INVALID' ];
	}

	// This operation owns its transaction. Never commit a caller's work or
	// report success when the optional leadership/audit schema is unavailable.
	// Authorization and CSRF remain the responsibility of the native controller.
	global $db_connection, $DatabaseType;

	if ( $DatabaseType !== 'postgresql' || ! isset( $db_connection )
		|| pg_transaction_status( $db_connection ) !== PGSQL_TRANSACTION_IDLE )
	{
		return [ 'ok' => false, 'error' => 'TRANSACTION_UNAVAILABLE' ];
	}

	// db_query(false) avoids Rosario's terminating SQL error page, allowing
	// rollback and a non-sensitive error result even after an INSERT fails.
	$query = static function ( $sql ) {
		$result = @db_query( $sql, false );

		if ( $result === false )
		{
			throw new RuntimeException( 'ACCOUNT_NOT_CREATED' );
		}

		return $result;
	};

	$started = false;

	try
	{
		$temp_password = SchoolSafeSetupTempPassword();
		$password_hash = encrypt_password( $temp_password );

		if ( ! is_string( $password_hash ) || $password_hash === '' )
		{
			throw new RuntimeException( 'ACCOUNT_NOT_CREATED' );
		}

		$query( 'BEGIN' );
		$started = true;
		$query( "SET LOCAL lock_timeout='5s'" );

		// Real table locks also serialize native writers that do not participate
		// in an advisory-lock convention (case-insensitive usernames included).
		$query( 'LOCK TABLE staff, students, schoolsafe_school_leadership IN SHARE ROW EXCLUSIVE MODE' );
		$schools = DBGet( $query( "SELECT ID FROM schools WHERE ID=" . $school_id
			. " AND SYEAR=" . $syear . " FOR SHARE" ) );

		if ( $school_id < 1 || ! $schools )
		{
			throw new RuntimeException( 'SCHOOL_NOT_FOUND' );
		}

		$profiles = DBGet( $query( "SELECT ID FROM user_profiles
			WHERE TITLE='Directeur' AND PROFILE='admin' FOR SHARE" ) );

		if ( count( $profiles ) !== 1 )
		{
			throw new RuntimeException( 'DIRECTOR_PROFILE_MISSING' );
		}

		$profile_id = (int) $profiles[1]['ID'];
		$taken = DBGet( $query( "SELECT 1 FROM staff
			WHERE LOWER(USERNAME)=LOWER('" . DBEscapeString( $username ) . "') AND SYEAR=" . $syear . "
			UNION SELECT 1 FROM students WHERE LOWER(USERNAME)=LOWER('" . DBEscapeString( $username ) . "')" ) );

		if ( $taken )
		{
			throw new RuntimeException( 'USERNAME_TAKEN' );
		}

		$existing = DBGet( $query( "SELECT STAFF_ID FROM schoolsafe_school_leadership
			WHERE SCHOOL_ID=" . $school_id . " AND ROLE='principal'" ) );

		if ( ! empty( $input['principal'] ) && $existing )
		{
			throw new RuntimeException( 'PRINCIPAL_EXISTS' );
		}

		$role = $existing ? 'adjoint' : 'principal';
		$values = [
			'SYEAR' => $syear, 'FIRST_NAME' => $first_name, 'LAST_NAME' => $last_name,
			'USERNAME' => $username, 'PASSWORD' => $password_hash, 'EMAIL' => $email,
			'PROFILE' => 'admin', 'PROFILE_ID' => $profile_id,
			'SCHOOLS' => ',' . $school_id . ',', 'CURRENT_SCHOOL_ID' => $school_id,
		];
		// DBInsertSQL expects escaped values, not raw form data.
		$values = array_map( 'DBEscapeString', $values );
		$insert = rtrim( DBInsertSQL( 'staff', $values ), ';' ) . ' RETURNING STAFF_ID';
		$created = DBGet( $query( $insert ) );
		$staff_id = (int) ( $created[1]['STAFF_ID'] ?? 0 );

		if ( $staff_id < 1 )
		{
			throw new RuntimeException( 'ACCOUNT_NOT_CREATED' );
		}

		// Do NOT call LeadershipSet: its replacement semantics demote a principal.
		$query( "INSERT INTO schoolsafe_school_leadership (SCHOOL_ID,STAFF_ID,ROLE,CHANGED_BY)
			VALUES (" . $school_id . "," . $staff_id . ",'" . $role . "','"
			. DBEscapeString( mb_substr( (string) $actor, 0, 100 ) ) . "')" );
		$query( "INSERT INTO schoolsafe_identity_audit (actor_staff_id,target_staff_id,action,result)
			VALUES (" . (int) User( 'STAFF_ID' ) . "," . $staff_id . ",'create','success')" );
		$query( 'COMMIT' );
		$started = false;
	}
	catch ( Throwable $error )
	{
		if ( $started )
		{
			@db_query( 'ROLLBACK', false );
		}

		$code = $error->getMessage();
		$allowed = [ 'SCHOOL_NOT_FOUND', 'DIRECTOR_PROFILE_MISSING', 'USERNAME_TAKEN', 'PRINCIPAL_EXISTS' ];

		return [ 'ok' => false, 'error' => in_array( $code, $allowed, true ) ? $code : 'ACCOUNT_NOT_CREATED' ];
	}

	return [
		'ok' => true,
		'error' => '',
		'staff_id' => $staff_id,
		'username' => $username,
		'temp_password' => $temp_password,
		'role' => $role,
		'school_id' => $school_id,
	];
}
