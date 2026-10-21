<?php
/**
 * Index
 *
 * Login screen
 *
 * @package RosarioSIS
 */

// FJ bugfix check accept cookies.
$default_session_name = session_name();

require_once 'Warehouse.php';
require_once 'ProgramFunctions/FirstLogin.fnc.php';

// Logout.
if ( isset( $_REQUEST['modfunc'] )
	&& $_REQUEST['modfunc'] === 'logout' )
{
	// Redirect to index.php with same locale as old session & eventual reason & redirect to URL.
	header( 'Location: ' . URLEscape( 'index.php?locale=' . $_SESSION['locale'] .
		( isset( $_REQUEST['reason'] ) ? '&reason=' . $_REQUEST['reason'] : '' ) .
		( isset( $_REQUEST['redirect_to'] ) ?
			'&redirect_to=' . urlencode( $_REQUEST['redirect_to'] ) :
			'' ) ) );

	if ( ! empty( $_REQUEST['token'] )
		&& $_SESSION['token'] === $_REQUEST['token'] )
	{
		session_unset();

		session_destroy();
	}

	exit;
}

// First login.
elseif ( isset( $_REQUEST['modfunc'] )
	&& $_REQUEST['modfunc'] === 'first-login' )
{
	// @since 7.3 Before First Login form action hook.
	// @example Parent Agreement plugin: Add a form before first login form without interfering with logic.
	do_action( 'index.php|before_first_login_form' );

	/**
	 * First Login Form
	 *
	 * Password Change & Poll after install.
	 *
	 * @since 5.3 Force password change on first login
	 */
	if ( HasFirstLoginForm() )
	{
		$first_login_done = false;

		if ( ! empty( $_POST['first_login'] ) )
		{
			// Save Password and set LAST_LOGIN.
			$first_login_done = DoFirstLoginForm( $_REQUEST['first_login'] );
		}

		if ( ! $first_login_done )
		{
			$_ROSARIO['page'] = 'first-login';

			Warehouse( 'header' );

			echo FirstLoginForm();

			Warehouse( 'footer' );

			exit;
		}
	}

	$_REQUEST['modfunc'] = false;
}

// Login.
elseif ( isset( $_POST['USERNAME'] )
	&& $_REQUEST['USERNAME'] !== ''
	&& isset( $_POST['PASSWORD'] )
	&& $_REQUEST['PASSWORD'] !== '' )
{
	// FJ check accept cookies.
	if ( ! isset( $_COOKIE['RosarioSIS'] )
		&& ! isset( $_COOKIE[ $default_session_name ] ) )
	{
		header( 'Location: index.php?modfunc=logout&reason=cookie&token=' . $_SESSION['token'] );

		exit;
	}

	// Only regenerate session ID if session.auto_start == 0.
	elseif ( isset( $_COOKIE['RosarioSIS'] ) )
	{
		session_regenerate_id( true ); // And invalidate old session.

		/**
		 * Add CSRF token to protect unauthenticated requests
		 *
		 * @since 9.0
		 * @since 11.0 Fix PHP fatal error if openssl PHP extension is missing
		 * @link https://stackoverflow.com/questions/5207160/what-is-a-csrf-token-what-is-its-importance-and-how-does-it-work
		 */
		$_SESSION['token'] = bin2hex( function_exists( 'openssl_random_pseudo_bytes' ) ?
			openssl_random_pseudo_bytes( 16 ) :
			( function_exists( 'random_bytes' ) ? random_bytes( 16 ) :
				mb_substr( sha1( rand( 999999999, 9999999999 ), true ), 0, 16 ) ) );
	}

	$username = (string) $_REQUEST['USERNAME'];

	unset( $_REQUEST['USERNAME'], $_POST['USERNAME'] );

	// Lookup for user $username in DB.
	$login_RET = DBGet( "SELECT USERNAME,PROFILE,STAFF_ID,LAST_LOGIN,FAILED_LOGIN,PASSWORD,SCHOOLS,CURRENT_SCHOOL_ID
	FROM staff
	WHERE SYEAR='" . Config( 'SYEAR' ) . "'
	AND UPPER(USERNAME)=UPPER('" . $username . "')" );

	if ( $login_RET
		&& match_password( $login_RET[1]['PASSWORD'], $_POST['PASSWORD'] ) )
	{
		unset( $_REQUEST['PASSWORD'], $_POST['PASSWORD'] );
	}
	else
		$login_RET = false;

	if ( ! $login_RET )
	{
		// Lookup for student $username in DB.
		$student_RET = DBGet( "SELECT s.USERNAME,s.STUDENT_ID,s.LAST_LOGIN,
			s.FAILED_LOGIN,s.PASSWORD,se.START_DATE
			FROM students s,student_enrollment se
			WHERE se.STUDENT_ID=s.STUDENT_ID
			AND se.SYEAR='" . Config( 'SYEAR' ) . "'
			AND CURRENT_DATE>=se.START_DATE
			AND (CURRENT_DATE<=se.END_DATE OR se.END_DATE IS NULL)
			AND UPPER(s.USERNAME)=UPPER('" . $username . "')" );

		if ( $student_RET
			&& match_password( $student_RET[1]['PASSWORD'], $_POST['PASSWORD'] ) )
		{
			unset( $_REQUEST['PASSWORD'], $_POST['PASSWORD'] );
		}
		else
		{
			// Student may be inactive or not verified, see below for corresponding errors.
			$student_RET = DBGet( "SELECT s.USERNAME,s.STUDENT_ID,
				s.LAST_LOGIN,s.FAILED_LOGIN,se.START_DATE,s.PASSWORD
			FROM students s,student_enrollment se
			WHERE se.STUDENT_ID=s.STUDENT_ID
			AND se.SYEAR='" . Config( 'SYEAR' ) . "'
			AND (CURRENT_DATE<=se.END_DATE OR se.END_DATE IS NULL)
			AND UPPER(s.USERNAME)=UPPER('" . $username . "')" );

			if ( ! $student_RET
				|| ! match_password( $student_RET[1]['PASSWORD'], $_POST['PASSWORD'] ) )
			{
				$student_RET = false;
			}
		}
	}

	$login_status = '';

	$is_banned = false;

	$ip = ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] )
		// Filter IP, HTTP_* headers can be forged.
		&& filter_var( $_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP ) ?
		$_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'] );

	if ( Config( 'FAILED_LOGIN_LIMIT' ) )
	{
		// Failed login ban if >= X failed attempts within 10 minutes.
		$failed_login_RET = DBGet( "SELECT
			COUNT(CASE WHEN STATUS IS NULL OR STATUS='B' THEN 1 END) AS FAILED_COUNT,
			COUNT(CASE WHEN STATUS='B' THEN 1 END) AS BANNED_COUNT
			FROM access_log
			WHERE CREATED_AT > (CURRENT_TIMESTAMP - INTERVAL " . ( $DatabaseType === 'mysql' ? '10 minute' : "'10 minute'" ) . ")
			AND USER_AGENT='" . DBEscapeString( $_SERVER['HTTP_USER_AGENT'] ) . "'
			AND IP_ADDRESS='" . $ip . "'" );

		if ( $failed_login_RET[1]['BANNED_COUNT']
			|| $failed_login_RET[1]['FAILED_COUNT'] >= Config( 'FAILED_LOGIN_LIMIT' ) )
		{
			// Ban in every case.
			$is_banned = true;

			$login_RET = $student_RET = false;

			// Banned status code: B.
			$login_status = 'B';
		}
	}

	// SchoolSafe — état de compte : suspension et expiration refusées ici,
	// avant qu'une session ne soit ouverte.
	$schoolsafe_state = 'active';

	$schoolsafe_blocked = false;

	// SchoolSafe — etat d'ETABLISSEMENT : une ecole coupee ferme l'acces a
	// tous ses utilisateurs. Une personne presente dans plusieurs ecoles ne
	// perd que l'ecole coupee : le blocage n'a lieu que si TOUTES sont coupees.
	$schoolsafe_school_state = '';

	if ( $login_RET )
	{
		$schoolsafe_schools = array_filter( array_map( 'intval',
			explode( ',', (string) issetVal( $login_RET[1]['SCHOOLS'] ) ) ) );

		// SchoolSafe — état le plus restrictif pour le message, mais décision
		// réelle sur les écoles encore utilisables : une suspension ciblée ne
		// bloque que son école.
		$schoolsafe_state = SchoolSafeAccountWorstState( $login_RET[1]['USERNAME'], $schoolsafe_schools );

		$schoolsafe_accessible = SchoolSafeAccountAccessibleSchools(
			$login_RET[1]['USERNAME'], $schoolsafe_schools );

		if ( ! $schoolsafe_accessible )
		{
			$schoolsafe_blocked = true;
		}

		if ( ! $schoolsafe_blocked )
		{
			// Un parent n'a aucune ecole assignee : ses ecoles sont celles de ses
			// enfants. Une personne de plusieurs ecoles ne perd que l'ecole coupee.
			$schoolsafe_school_state = SchoolSafeSchoolLoginBlock(
				SchoolSafePersonSchools( $login_RET[1]['PROFILE'], $login_RET[1]['SCHOOLS'],
					(int) $login_RET[1]['STAFF_ID'] ) );
		}
	}

	// SchoolSafe — etat d'etablissement pour un ELEVE : ecole d'inscription.
	if ( ! $schoolsafe_blocked
		&& $schoolsafe_school_state === ''
		&& $student_RET )
	{
		$schoolsafe_school_state = SchoolSafeSchoolLoginBlock(
			SchoolSafeStudentSchools( (int) $student_RET[1]['STUDENT_ID'] ) );
	}

	// Admin, teacher or parent: initiate session.
	if ( $login_RET
		&& ! $schoolsafe_blocked
		&& $schoolsafe_school_state === ''
		&& ( $login_RET[1]['PROFILE'] === 'admin'
			|| $login_RET[1]['PROFILE'] === 'teacher'
			|| $login_RET[1]['PROFILE'] === 'parent' ) )
	{
		$_SESSION['STAFF_ID'] = $login_RET[1]['STAFF_ID'];

		// Invalidate any active Student session.
		unset( $_SESSION['STUDENT_ID'] );

		// SchoolSafe — école d'entrée : celle du compte si elle reste ouverte,
		// sinon la première école utilisable. Le sélecteur ne doit pas ramener
		// la personne dans une école suspendue.
		$schoolsafe_entry = 0;

		foreach ( $schoolsafe_accessible as $schoolsafe_candidate )
		{
			if ( (int) $schoolsafe_candidate > 0 )
			{
				$schoolsafe_entry = (int) $schoolsafe_candidate;
				break;
			}
		}

		$schoolsafe_current = (int) ( $login_RET[1]['CURRENT_SCHOOL_ID'] ?? 0 );

		if ( $schoolsafe_current > 0
			&& in_array( $schoolsafe_current, $schoolsafe_accessible, true ) )
		{
			$schoolsafe_entry = $schoolsafe_current;
		}

		$_SESSION['UserSchool'] = $schoolsafe_entry;

		$_SESSION['LAST_LOGIN'] = $login_RET[1]['LAST_LOGIN'];
		SchoolSafeNativeOnboardingMarkLogin( $login_RET[1]['STAFF_ID'], UserSyear() );

		// SchoolSafe — état de compte : époque de session pour la révocation.
		$_SESSION['SchoolSafeEpoch'] = SchoolSafeAccountEpoch( $login_RET[1]['USERNAME'] );

		// Époque PAR ÉCOLE : une révocation ciblée ne coupe que son école.
		$_SESSION['SchoolSafeEpochs'] = [];

		foreach ( SchoolSafeAccountSchoolIds( $schoolsafe_schools ) as $schoolsafe_epoch_school )
		{
			$_SESSION['SchoolSafeEpochs'][ $schoolsafe_epoch_school ] = SchoolSafeAccountSchoolEpoch(
				$login_RET[1]['USERNAME'], $schoolsafe_epoch_school );
		}

		$_SESSION['SchoolSafeState'] = $schoolsafe_state;

		$failed_login = $login_RET[1]['FAILED_LOGIN'];

		$login_status = 'Y';
	}

	// SchoolSafe — compte suspendu ou expiré.
	elseif ( $login_RET
		&& $schoolsafe_blocked )
	{
		$error[] = SchoolSafeAccountLoginMessage( $schoolsafe_state );

		// Statut journalisé distinct d'un échec de mot de passe.
		$login_status = 'S';
	}

	// SchoolSafe — etablissement suspendu ou desactive.
	elseif ( $login_RET
		&& $schoolsafe_school_state !== '' )
	{
		$error[] = SchoolSafeSchoolLoginMessage( $schoolsafe_school_state );

		// Statut journalisé distinct d'un échec de mot de passe.
		$login_status = 'S';
	}

	// User with No access profile.
	elseif ( $login_RET
			&& $login_RET[1]['PROFILE'] == 'none' )
	{
		$error[] = _( 'Your account has not yet been activated.' ) . ' '
			. _( 'You will be notified when it has been verified by a school administrator.' );
	}

	// Student account inactive (today < Attendance start date).
	elseif ( $student_RET
			&& DBDate() < $student_RET[1]['START_DATE'] )
	{
		$error[] = _( 'Your account has not yet been activated.' );
	}

	// Student account not verified (enrollment school + start date + last login are NULL).
	elseif ( $student_RET
			&& ! $student_RET[1]['START_DATE']
			&& ! $student_RET[1]['LAST_LOGIN'] )
	{
		$error[] = _( 'Your account has not yet been activated.' ) . ' '
			. _( 'You will be notified when it has been verified by a school administrator.' );
	}

	// SchoolSafe — etablissement de l'eleve suspendu ou desactive.
	elseif ( $student_RET
		&& $schoolsafe_school_state !== '' )
	{
		$error[] = SchoolSafeSchoolLoginMessage( $schoolsafe_school_state );

		// Statut journalise distinct d'un echec de mot de passe.
		$login_status = 'S';
	}

	// Student: initiate session.
	elseif ( $student_RET
		&& $schoolsafe_school_state === '' )
	{
		$_SESSION['STUDENT_ID'] = $student_RET[1]['STUDENT_ID'];

		// Invalidate any active User session.
		unset( $_SESSION['STAFF_ID'] );

		unset( $_SESSION['UserSchool'] );

		$_SESSION['LAST_LOGIN'] = $student_RET[1]['LAST_LOGIN'];

		$failed_login = $student_RET[1]['FAILED_LOGIN'];

		$login_status = 'Y';
	}

	// Failed login.
	else
	{
		DBQuery( "UPDATE staff
			SET FAILED_LOGIN=" . db_case( [ 'FAILED_LOGIN', "''", '1', 'FAILED_LOGIN+1' ] ) . "
			WHERE UPPER(USERNAME)=UPPER('" . $username . "')
			AND SYEAR='" . Config( 'SYEAR' ) . "';
			UPDATE students
			SET FAILED_LOGIN=" . db_case( [ 'FAILED_LOGIN', "''", '1', 'FAILED_LOGIN+1' ] ) . "
			WHERE UPPER(USERNAME)=UPPER('" . $username . "')" );

		if ( $is_banned )
		{
			// Failed login ban if >= X failed attempts within 10 minutes.
			$error[] = _( 'Too many Failed Login Attempts.' ) . '&nbsp;'
				. _( 'Please try logging in later.' );
		}
		else
		{
			$error[] = _( 'Incorrect username or password.' ) . '&nbsp;'
				. _( 'Please try logging in again.' );
		}
	}

	// Access Log.
	if ( ! function_exists( 'AccessLogRecord' ) )
	{
		DBInsert(
			'access_log',
			[
				'SYEAR' => Config( 'SYEAR' ),
				'USERNAME' => mb_substr( $username, 0, 100 ),
				'PROFILE' => User( 'PROFILE' ),
				'IP_ADDRESS' => $ip,
				'USER_AGENT' => DBEscapeString( $_SERVER['HTTP_USER_AGENT'] ),
				'STATUS' => $login_status,
			]
		);
	}

	// Set current SchoolYear on login.
	if ( $login_status === 'Y'
		&& ! UserSyear() )
	{
		$_SESSION['UserSyear'] = Config( 'SYEAR' );
	}

	// @since 2.9.8 Login check action hook.
	do_action( 'index.php|login_check', $username );

	if ( HasFirstLoginForm() )
	{
		// First Login.
		header( 'Location: ' . URLEscape(
			'index.php?locale=' . $_SESSION['locale'] . '&modfunc=first-login&token=' . $_SESSION['token']
		) );

		exit;
	}

	// Set LAST_LOGIN, reset FAILED_LOGIN.
	if ( $login_status === 'Y'
		&& User( 'STAFF_ID' ) )
	{
		DBQuery( "UPDATE staff
			SET LAST_LOGIN=CURRENT_TIMESTAMP,FAILED_LOGIN=NULL
			WHERE STAFF_ID='" . User( 'STAFF_ID' ) . "'" );
	}
	elseif ( $login_status === 'Y' )
	{
		DBQuery( "UPDATE students
			SET LAST_LOGIN=CURRENT_TIMESTAMP,FAILED_LOGIN=NULL
			WHERE STUDENT_ID='" . (int) $_SESSION['STUDENT_ID'] . "'" );
	}
}

// FJ create account.
elseif ( isset( $_REQUEST['create_account'] ) )
{
	$include = false;

	unset( $_SESSION['STAFF_ID'], $_SESSION['STUDENT_ID'] );

	if ( $_REQUEST['create_account'] === 'user'
		&& Config( 'CREATE_USER_ACCOUNT' ) )
	{
		$include = 'Users/User.php';

		if ( UserStaffID() )
		{
			unset( $_SESSION['staff_id'] );
		}
	}

	elseif ( $_REQUEST['create_account'] === 'student'
		&& Config( 'CREATE_STUDENT_ACCOUNT' ) )
	{
		$include = 'Students/Student.php';

		// @since 6.0 Create Student Account: add school_id param to URL.
		if ( ! empty( $_REQUEST['school_id'] )
			&& ! Config( 'CREATE_STUDENT_ACCOUNT_DEFAULT_SCHOOL_FORCE' ) )
		{
			$sql_order_by = "ID='" . (int) $_REQUEST['school_id'] . "' DESC,ID";
		}
		else
		{
			// @since 6.3 Create Student Account Default School.
			// @link https://stackoverflow.com/questions/1250156/how-do-i-return-rows-with-a-specific-value-first#comment-67097263
			$sql_order_by = Config( 'CREATE_STUDENT_ACCOUNT_DEFAULT_SCHOOL' ) ?
				// Prevent SQL injection, cast to integer.
				"ID='" . (int) Config( 'CREATE_STUDENT_ACCOUNT_DEFAULT_SCHOOL' ) . "' DESC,ID" : "ID";
		}

		$_SESSION['UserSchool'] = DBGetOne( "SELECT ID FROM schools
			WHERE SYEAR='" . Config( 'SYEAR' ) . "'
			ORDER BY " . $sql_order_by );

		if ( UserStudentID() )
		{
			unset( $_SESSION['student_id'] );
		}
	}

	if ( ! $include )
	{
		// Do not use RedirectURL() here (no JS loaded).
		header( 'Location: index.php' );
	}
	else
	{
		if ( ! isset( $_REQUEST['modfunc'] ) )
		{
			$_REQUEST['modfunc'] = false;
		}

		$_REQUEST['modname'] = false;

		$_ROSARIO['page'] = 'create-account';

		Warehouse( 'header' );

		$_ROSARIO['allow_edit'] = true;

		// FJ security fix, cf http://www.securiteam.com/securitynews/6S02U1P6BI.html.
		if ( mb_substr( $include, -4, 4 ) !== '.php'
			|| mb_strpos( $include, '..' ) !== false
			|| ! is_file( 'modules/' . $include ) )
		{
			require_once 'ProgramFunctions/HackingLog.fnc.php';
			HackingLog();
		}
		else
			require_once 'modules/' . $include;

		Warehouse( 'footer' );

		if ( UserSchool() )
		{
			// Unset UserSchool() so we get correct Config values if next request changes school.
			unset( $_SESSION['UserSchool'] );
		}
	}
}


// Login screen.
if ( empty( $_SESSION['STAFF_ID'] )
	&& empty( $_SESSION['STUDENT_ID'] )
	&& ! isset( $_REQUEST['create_account'] ) )
{
	$_ROSARIO['page'] = 'login';

	Warehouse( 'header' );

	// SchoolSafe splash. Visual only, and only before an error so a failed login stays readable.
	if ( empty( $error ) )
	{
		echo '<div class="ss-splash" aria-hidden="true"><img src="assets/themes/' .
			URLEscape( Config( 'THEME' ) ) . '/logo.png" alt="" /></div>';
	}

	PopTable(
		'header',
		sprintf( _( '%s Login' ), SchoolSafeBrandText( Config( 'NAME' ) ) )
	);

	if ( isset( $_REQUEST['reason'] ) )
	{
		if ( $_REQUEST['reason'] == 'javascript' )
		{
			$note[] = sprintf(
				_( 'You must have javascript enabled to use %s.' ),
				Config( 'NAME' )
			);
		}

		// FJ check accept cookies.
		elseif ( $_REQUEST['reason'] == 'cookie' )
		{
			$note[] = sprintf(
				_( 'You must accept cookies to use %s.' ),
				Config( 'NAME' )
			);
		}

		// FJ create account.
		elseif ( $_REQUEST['reason'] == 'account_created' )
		{
			$note[] = _( 'Your account has been created.' ) . ' '
				. _( 'You will be notified when it has been verified by a school administrator.' ) . ' '
				. _( 'You will then be able to log in.' );
		}

		// @since 5.9 Automatic Student Account Activation.
		elseif ( $_REQUEST['reason'] == 'account_activated' )
		{
			$note[] = _( 'Your account has been created.' );
		}

		// Password recovery.
		elseif ( $_REQUEST['reason'] == 'password_reset' )
		{
			$note[] = _( 'If you supplied a correct email address then please check your email for the password reset instructions.' );
		}

		// SchoolSafe — état de compte : suspension ou expiration.
		elseif ( $_REQUEST['reason'] == 'account_suspended'
			|| $_REQUEST['reason'] == 'account_expired' )
		{
			$note[] = SchoolSafeAccountLoginMessage(
				$_REQUEST['reason'] == 'account_expired' ? 'expired' : 'suspended' );
		}

		// SchoolSafe — sessions revoquées par l'administration.
		elseif ( $_REQUEST['reason'] == 'account_revoked' )
		{
			$note[] = "Votre session a ete fermee par l'administration. Reconnectez-vous : si votre compte reste actif, l'acces est immediatement retabli.";
		}

		// SchoolSafe — etat d'etablissement : acces coupe pour toute l'ecole.
		elseif ( $_REQUEST['reason'] == 'school_suspended'
			|| $_REQUEST['reason'] == 'school_inactive' )
		{
			$note[] = SchoolSafeSchoolLoginMessage(
				$_REQUEST['reason'] == 'school_inactive' ? 'inactive' : 'suspended' );
		}
	}

	// Messages natifs de Rosario (erreur, note) : rendus DANS le panneau
	// de l'ecran bleu, jamais par-dessus les ecrans.
	$ss_blue_messages = ErrorMessage( $error ) . ErrorMessage( $note, 'note' );

?>

<?php

	// Valeurs par defaut du formulaire natif : recopiees telles quelles,
	// l'ouverture de session au premier acces reste inchangee.
	$default_username = $default_password = '';

	if ( Config( 'LOGIN' ) === 'No'
		&& DBGetOne( "SELECT 1 FROM staff
			WHERE USERNAME='admin'
			AND PASSWORD='$6\$dc51290a001671c6$97VSmw.Qu9sL6vpctFh62/YIbbR6b3DstJJxPXal2OndrtFszsxmVhdQaV2mJvb6Z38sPACXqDDQ7/uquwadd.'" ) )
	{
		// @since 12.9 First login, prefill form inputs with default "admin" username & password
		$default_username = $default_password = 'admin';
	}

	// Les deux ecrans bleus : accueil, puis connexion par nom d'utilisateur.
	// Le formulaire POST reste celui de Rosario (index.php).
	require 'modules/SchoolSafe/login-blue.inc.php';

?>
<?php PopTable( 'footer' );

	Warehouse( 'footer' );
}

// Successfully logged in, display Portal.
elseif ( ! isset( $_REQUEST['create_account'] ) )
{
	// Keep centrally authenticated SchoolSafe managers out of the school dashboard.
	// School administrators continue through Rosario's existing portal route.
	if ( empty( $_REQUEST['redirect_to'] )
		&& ! empty( $_SESSION['schoolsafe-keycloak-session'] )
		&& User( 'PROFILE' ) === 'admin'
		&& function_exists( 'SchoolSafeGlobalAdmin' )
		&& SchoolSafeGlobalAdmin() )
	{
		header( 'Location: /schoolsafe-administrators.php?home=1' );
		exit;
	}

	/**
	 * Redirect to Modules.php URL after login.
	 * Defaults to modname=misc/Portal.php.
	 * Sanitize redirect_to.
	 * Check profile ID, so we avoid HackingLog
	 * when login with a less privileged user.
	 *
	 * @since 3.8
	 */
	$redirect_to = empty( $_REQUEST['redirect_to'] )
		|| mb_strpos( $_REQUEST['redirect_to'], 'modname=misc/' ) === 0 ?
		'modname=misc/Portal.php' : // Fix #173 resend login form: redirect to Modules.php.
		str_replace(
			[ '&_ROSARIO_PDF=true', '&_ROSARIO_PDF', '&LO_save=1', '&bottomfunc=print', '&delete_ok=1' ],
			'',
			$_REQUEST['redirect_to']
		);

	header( 'Location: ' . URLEscape( 'Modules.php?' . $redirect_to ) );

	exit;
}
