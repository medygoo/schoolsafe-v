<?php


require_once 'ProgramFunctions/FileUpload.fnc.php';
require_once 'ProgramFunctions/Fields.fnc.php';
require_once 'modules/Users/includes/User.fnc.php';

$_REQUEST['staff_id'] = issetVal( $_REQUEST['staff_id'] );

if ( User( 'PROFILE' ) !== 'admin'
	&& User( 'PROFILE' ) !== 'teacher'
	&& $_REQUEST['staff_id']
	&& $_REQUEST['staff_id'] != User( 'STAFF_ID' )
	&& $_REQUEST['staff_id'] !== 'new' )
{
	if ( User( 'USERNAME' ) )
	{
		require_once 'ProgramFunctions/HackingLog.fnc.php';
		HackingLog();
	}

	exit;
}

// SchoolSafe — lecture : un administrateur délégué ne peut pas ouvrir la fiche
// d'un utilisateur hors de son périmètre. L'écriture était déjà contrôlée ; la
// consultation, elle, laissait voir nom, courriel et profil d'une autre école.
if ( User( 'PROFILE' ) === 'admin'
	&& function_exists( 'SchoolSafeGlobalAdmin' )
	&& ! SchoolSafeGlobalAdmin()
	&& ! empty( $_REQUEST['staff_id'] )
	&& $_REQUEST['staff_id'] !== 'new'
	&& (int) $_REQUEST['staff_id'] !== (int) User( 'STAFF_ID' )
	&& ! SchoolSafeStaffWithinScope( (int) $_REQUEST['staff_id'] ) )
{
	SchoolSafeScopeDeny();

	return;
}

$categories = [ '1' => 'General_Info', '2' => 'Schedule', 'Other_Info' => 'Other_Info' ];

if ( ! isset( $_REQUEST['category_id'] ) )
{
	$category_id = '1';
	$include = 'General_Info';
}
else
{
	$category_id = $_REQUEST['category_id'];

	if ( in_array( $_REQUEST['category_id'], array_keys( $categories ) ) )
	{
		$include = $categories[$category_id];
	}
	else
	{
		$category_include = DBGet( "SELECT INCLUDE
			FROM staff_field_categories
			WHERE ID='" . (int) $_REQUEST['category_id'] . "'" );

		if ( ! empty( $category_include ) )
		{
			$include = $category_include[1]['INCLUDE'];
			$category_id = (string) (int) $_REQUEST['category_id']; // Sanitise : meme valeur que le lookup (int) ci-dessus.

			if ( empty( $include ) )
			{
				$include = $categories['Other_Info'];
			}
		}

		//FJ Prevent $_REQUEST['category_id'] hacking
		else
		{
			$category_id = '1';
			$include = 'General_Info';
		}
	}
}

if ( empty( $_REQUEST['category_id'] )
	|| $_REQUEST['category_id'] !== $category_id )
{
	// Fix incoherence with AllowEdit() when category_id present or not in URL.
	$_REQUEST['category_id'] = $category_id;
}

// Allow update for Parents & Teachers if have Edit permissions.

if ( User( 'PROFILE' ) !== 'admin' )
{
	$can_edit_from_where = " FROM profile_exceptions WHERE PROFILE_ID='" . User( 'PROFILE_ID' ) . "'";

	if ( ! User( 'PROFILE_ID' ) )
	{
		$can_edit_from_where = " FROM staff_exceptions WHERE USER_ID='" . User( 'STAFF_ID' ) . "'";
	}

	$can_edit_RET = DBGet( "SELECT MODNAME " . $can_edit_from_where .
		" AND MODNAME='Users/User.php&category_id=" . $category_id . "'
		AND CAN_EDIT='Y'" );

	if ( $can_edit_RET )
	{
		$_ROSARIO['allow_edit'] = true;
	}
}


if ( $_REQUEST['modfunc'] === 'update'
	&& AllowEdit() )
{
	// Add eventual Dates to $_REQUEST['staff'].
	AddRequestedDates( 'staff', 'post' );
	if ( User( 'PROFILE' ) === 'admin' && ! SchoolSafeGlobalAdmin()
		&& ! isset( $_REQUEST['staff']['SCHOOLS'] )
		&& ( ! UserStaffID() || $_REQUEST['staff_id'] === 'new'
			|| isset( $_REQUEST['staff']['PROFILE'] ) && $_REQUEST['staff']['PROFILE'] !== 'parent' ) )
	{
		// A missing assignment must never turn a new/scoped account into all-schools.
		if ( ! UserStaffID() || $_REQUEST['staff_id'] === 'new' )
			$_REQUEST['staff']['SCHOOLS'] = [ UserSchool() => 'Y' ];
		else
		{
			$previous_profile = DBGetOne( "SELECT PROFILE FROM staff WHERE STAFF_ID='" . (int) UserStaffID() . "' AND SYEAR='" . (int) UserSyear() . "'" );
			if ( $previous_profile === 'parent' ) $_REQUEST['staff']['SCHOOLS'] = [ UserSchool() => 'Y' ];
		}
	}

	if ( isset( $_REQUEST['staff']['SCHOOLS'] ) )
	{
		$staff_schools = '';

		// FJ remove empty schools.

		foreach ( (array) $_REQUEST['staff']['SCHOOLS'] as $school_id => $yes )
		{
			if ( $yes )
			{
				$staff_schools .= $school_id . ',';
			}
		}

		// Build schools format: ,1,2,
		$_REQUEST['staff']['SCHOOLS'] = $staff_schools ? ',' . $staff_schools : '';
		foreach ( SchoolSafeScopeParse( $_REQUEST['staff']['SCHOOLS'] ) ?: [] as $assigned_school )
		{
			if ( ! DBGetOne( "SELECT ID FROM schools WHERE ID='" . (int) $assigned_school . "' AND SYEAR='" . (int) UserSyear() . "'" ) )
			{
				SchoolSafeScopeDeny(); return;
			}
		}

		// FJ remove Schools for Parents.

		if ( isset( $_REQUEST['staff']['PROFILE'] )
			&& $_REQUEST['staff']['PROFILE'] == 'parent' )
		{
			$_REQUEST['staff']['SCHOOLS'] = '';
		}

		// FJ reset current school if updating self schools.

		if ( User( 'STAFF_ID' ) == UserStaffID() )
		{
			unset( $_SESSION['UserSchool'] );
		}
	}

	// Admin Schools restriction.
	if ( User( 'PROFILE' ) === 'admin' && isset( $_REQUEST['staff']['SCHOOLS'] )
		&& AllowEdit( 'Users/User.php&category_id=1&schools' )
		&& ( $_REQUEST['staff']['PROFILE'] ?? '' ) !== 'parent'
		&& ! SchoolSafeScopeDelegates( SchoolSafeScopeParse( User( 'SCHOOLS' ) ), SchoolSafeScopeParse( $_REQUEST['staff']['SCHOOLS'] ) ) )
	{
		SchoolSafeScopeDeny(); return;
	}

	// SchoolSafe — garde-fou : un profil nomme a portee d'ecole (Directeur,
	// Comptable...) sans aucune ecole deviendrait proprietaire de la plateforme,
	// puisque SCHOOLS vide signifie « toutes les ecoles ». On refuse au lieu de
	// promouvoir en silence ; les profils natifs 1/2/3 restent inchanges.
	if ( User( 'PROFILE' ) === 'admin'
		&& function_exists( 'SchoolSafeSchoolScopedProfileRequiresSchool' ) )
	{
		$guard_profile_id = (int) ( $_REQUEST['staff']['PROFILE_ID'] ?? 0 );

		if ( $guard_profile_id < 1 && UserStaffID() )
		{
			$guard_profile_id = (int) DBGetOne( "SELECT PROFILE_ID FROM staff
				WHERE STAFF_ID='" . (int) UserStaffID() . "'" );
		}

		$guard_schools = isset( $_REQUEST['staff']['SCHOOLS'] )
			? (string) $_REQUEST['staff']['SCHOOLS']
			: ( UserStaffID()
				? (string) DBGetOne( "SELECT SCHOOLS FROM staff
					WHERE STAFF_ID='" . (int) UserStaffID() . "'" )
				: '' );

		if ( SchoolSafeSchoolScopedProfileRequiresSchool( $guard_profile_id )
			&& trim( $guard_schools, ',' ) === '' )
		{
			// SchoolSafe lot U2 (B7) : message en français.
			$error[] = SchoolSafeT(
				'A school-level profile (Director, Accountant...) must be assigned at least one school: without a school, the account would manage the whole platform.',
				'Un profil d’école (directeur, comptable…) doit être rattaché à au moins une école : sans école, ce compte gérerait toute la plateforme.'
			);
		}
	}

	if (  ( User( 'PROFILE' ) === 'admin'
		&& ! AllowEdit( 'Users/User.php&category_id=1&schools' ) )
		|| User( 'PROFILE' ) !== 'admin' )
	{
		if ( UserStaffID() )
		{
			// Restricted!
			unset( $_REQUEST['staff']['SCHOOLS'] );
		}
		elseif ( UserSchool() ) // No set if "Create User Account".
		{
			// Assign new user to current school only.
			$_REQUEST['staff']['SCHOOLS'] = ',' . UserSchool() . ',';
		}
	}

	// Admin Profile restriction.

	if (  ( User( 'PROFILE' ) === 'admin'
		&& ! AllowEdit( 'Users/User.php&category_id=1&user_profile' )
		&& isset( $_REQUEST['staff']['PROFILE'] ) )
		|| User( 'PROFILE' ) !== 'admin' )
	{
		if ( UserStaffID()
			&& $_REQUEST['staff_id'] )
		{
			// Restricted!
			unset( $_REQUEST['staff']['PROFILE'] );
			unset( $_REQUEST['staff']['PROFILE_ID'] );
		}

		// New User.
		elseif ( issetVal( $_REQUEST['staff']['PROFILE'] ) === 'admin' )
		{
			// Remove Administrator from profile options.
			$_REQUEST['staff']['PROFILE'] = 'teacher';
		}
	}

	if ( User( 'PROFILE' ) === 'admin'
		&& isset( $_REQUEST['staff']['PROFILE'] )
		&& ! in_array( $_REQUEST['staff']['PROFILE'], [ 'admin', 'teacher', 'parent', 'none' ] ) )
	{
		// Security fix: default to none when selected profile does not exist
		$_REQUEST['staff']['PROFILE'] = 'none';
	}

	if ( ! empty( $_POST['staff'] )
		|| ! empty( $_FILES ) )
	{
		if ( User( 'PROFILE' ) === 'admin' && ! SchoolSafeGlobalAdmin() )
		{
			$default_profiles = [ 'admin' => 1, 'teacher' => 2, 'parent' => 3 ];

			// A delegated administrator may only create/promote non-administrator
			// accounts: the target profile is checked once, after resolution.
			$submitted_kind = $_REQUEST['staff']['PROFILE'] ?? null;
			$submitted_profile = $_REQUEST['staff']['PROFILE_ID'] ?? null;

			if ( $submitted_profile === null
				|| $submitted_profile === ''
				|| $submitted_profile === '0' )
			{
				$submitted_profile = isset( $submitted_kind )
					? ( $default_profiles[(string) $submitted_kind] ?? null )
					: $submitted_profile;
			}

			if ( $submitted_profile !== null
				&& ! SchoolSafeProfileDelegable( $submitted_profile, $submitted_kind ) )
			{
				SchoolSafeScopeDeny(); return;
			}
		}

		// L3.1 — profils propres à une école (user_profiles.school_id, 034) :
		// attribution refusée hors de l'école propriétaire et aux comptes
		// multi-écoles (le moteur natif ne cloisonne pas les droits par
		// contexte) ; même exigence lors d'un changement ultérieur de SCHOOLS.
		// Vaut pour tout acteur admin, global compris (garde-fou explicite).
		if ( User( 'PROFILE' ) === 'admin'
			&& function_exists( 'SchoolSafeProfileSchoolAssignable' )
			&& is_array( $_REQUEST['staff'] ?? null ) )
		{
			$guard_has_schools = array_key_exists( 'SCHOOLS', $_REQUEST['staff'] );
			$guard_schools_raw = $guard_has_schools ? $_REQUEST['staff']['SCHOOLS'] : null;

			if ( is_array( $guard_schools_raw ) )
			{
				$guard_sids = [];

				foreach ( (array) $guard_schools_raw as $gsid => $gyes )
				{
					if ( $gyes && ctype_digit( (string) $gsid ) && (int) $gsid > 0 )
					{
						$guard_sids[] = (int) $gsid;
					}
				}

				$guard_target_schools = $guard_sids ? ',' . implode( ',', $guard_sids ) . ',' : '';
			}
			else $guard_target_schools = (string) ( $guard_schools_raw ?? '' );

			// Profil visé : soumis, sinon celui déjà détenu (cas d'un
			// changement de SCHOOLS sur un détenteur de profil local).
			$guard_assign_profile = $_REQUEST['staff']['PROFILE_ID'] ?? null;

			if ( ( $guard_assign_profile === null || $guard_assign_profile === '' )
				&& UserStaffID() )
			{
				$guard_assign_profile = DBGetOne( "SELECT PROFILE_ID FROM staff
					WHERE STAFF_ID='" . (int) UserStaffID() . "'" );
			}

			if ( ! $guard_has_schools && UserStaffID() )
			{
				// Écoles non soumises (fiche partielle) : périmètre actuel.
			$guard_target_schools = (string) DBGetOne( "SELECT SCHOOLS FROM staff
					WHERE STAFF_ID='" . (int) UserStaffID() . "'" );
			}

			$guard_owner = ( $guard_assign_profile !== null && $guard_assign_profile !== '' )
				? SchoolSafeProfileSchoolId( $guard_assign_profile ) : null;

			if ( $guard_owner !== null && $guard_owner !== false )
			{
				// Un profil d'école n'a pas de sens pour la famille parent
				// (jamais rattachée à une école) : refusé plutôt que promu.
				$guard_kind = $_REQUEST['staff']['PROFILE'] ?? null;

				if ( $guard_kind === null && UserStaffID() )
				{
					$guard_kind = DBGetOne( "SELECT PROFILE FROM staff
						WHERE STAFF_ID='" . (int) UserStaffID() . "'" );
				}

				if ( $guard_kind === 'parent'
					|| ! SchoolSafeProfileSchoolAssignable( $guard_assign_profile, $guard_target_schools ) )
				{
					SchoolSafeScopeDeny(); return;
				}
			}
		}
		if ( ( ! $_REQUEST['staff_id'] || $_REQUEST['staff_id'] === 'new' )
			&& UserStaffID() )
		{
			// Fix saving new user/student when current Staff ID set (in other browser tab).
			// An explicit creation must never update the browsed account instead:
			// the insert path below only runs when no Staff ID is in session.
			unset( $_SESSION['staff_id'] );
		}
		elseif ( $_REQUEST['staff_id'] !== 'new'
			&& $_REQUEST['staff_id'] != UserStaffID() )
		{
			// Fix SQL error on save when current Staff ID was lost (in other browser tab).
			SetUserStaffID( $_REQUEST['staff_id'] );
		}

		$required_error = false;

		if ( ( ( isset( $_REQUEST['staff']['FIRST_NAME'] ) || ! UserStaffID() )
				&& empty( $_REQUEST['staff']['FIRST_NAME'] ) )
			|| ( ( isset( $_REQUEST['staff']['LAST_NAME'] ) || ! UserStaffID() )
				&& empty( $_REQUEST['staff']['LAST_NAME'] ) ) )
		{
			// Check FIRST_NAME, LAST_NAME is not null.
			$required_error = true;
		}

		// FJ other fields required.
		$required_error = $required_error || CheckRequiredCustomFields( 'staff_fields', issetVal( $_REQUEST['staff'], [] ) );

		// FJ textarea fields MarkDown sanitize.
		$_REQUEST['staff'] = FilterCustomFieldsMarkdown( 'staff_fields', 'staff' );

		if ( basename( $_SERVER['PHP_SELF'] ) === 'index.php' )
		{
			// Create account.
			if ( ! CheckCaptcha() )
			{
				$error[] = _( 'Captcha' );
			}

			// Username & password required.

			if ( empty( $_REQUEST['staff']['USERNAME'] )
				|| empty( $_REQUEST['staff']['PASSWORD'] ) )
			{
				$required_error = true;
			}

			// Check if trying to hack profile (would result in an SQL error).

			if ( isset( $_REQUEST['staff']['PROFILE'] ) )
			{
				require_once 'ProgramFunctions/HackingLog.fnc.php';
				HackingLog();
			}
		}

		// SchoolSafe lot U1 (05/11/2026, ANALYSE_UTILISATEURS A1, A4, A6).
		require_once 'ProgramFunctions/SchoolSafePasswordPolicy.fnc.php';

		// A6 — mot de passe temporaire de l'assistant : gardé en session côté
		// serveur, jamais dans la page ; repris ici pour la même personne.
		if ( ! UserStaffID()
			&& ! empty( $_POST['schoolsafe_hub_password'] )
			&& ! empty( $_SESSION['SchoolSafeHubPassword']['password'] )
			&& (int) ( $_SESSION['SchoolSafeHubPassword']['expires'] ?? 0 ) >= time()
			&& isset( $_REQUEST['staff']['USERNAME'] )
			&& hash_equals(
				(string) $_SESSION['SchoolSafeHubPassword']['username'],
				(string) $_REQUEST['staff']['USERNAME']
			) )
		{
			$_POST['staff']['PASSWORD'] = $_REQUEST['staff']['PASSWORD']
				= (string) $_SESSION['SchoolSafeHubPassword']['password'];
		}

		// A1 — un nouveau compte a toujours un nom d'utilisateur et un mot de
		// passe, sauf le profil « Sans accès » (aucune connexion) et la création
		// Keycloak (sskc_intent : la connexion passe par Keycloak).
		if ( ! UserStaffID()
			&& basename( $_SERVER['PHP_SELF'] ) !== 'index.php'
			&& empty( $_POST['sskc_intent'] )
			&& ! ( ( $_REQUEST['staff']['PROFILE'] ?? '' ) === 'none'
				&& empty( $_REQUEST['staff']['PROFILE_ID'] ) )
			&& ( empty( $_REQUEST['staff']['USERNAME'] )
				|| empty( $_POST['staff']['PASSWORD'] ) ) )
		{
			$error[] = 'Un nouveau compte doit avoir un nom d’utilisateur et un mot de passe '
				. '(sauf le profil « Sans accès »).';
		}

		// A4 — robustesse vérifiée sur le serveur, à la création comme au changement.
		if ( isset( $_POST['staff']['PASSWORD'] ) && (string) $_POST['staff']['PASSWORD'] !== '' )
		{
			$schoolsafe_pw_username = ! empty( $_REQUEST['staff']['USERNAME'] )
				? (string) $_REQUEST['staff']['USERNAME']
				: ( UserStaffID() ? (string) DBGetOne( "SELECT USERNAME FROM staff
					WHERE STAFF_ID='" . (int) UserStaffID() . "'" ) : '' );

			$schoolsafe_pw_problem = SchoolSafePasswordProblem(
				(string) $_POST['staff']['PASSWORD'],
				$schoolsafe_pw_username
			);

			if ( $schoolsafe_pw_problem !== '' )
			{
				$error[] = $schoolsafe_pw_problem;
			}
		}

		if ( $required_error )
		{
			$error[] = _( 'Please fill in the required fields' );
		}

		if ( ! empty( $_REQUEST['staff']['USERNAME'] ) )
		{
			// Check username uniqueness (case-insensitive).
			$existing_username = DBGet( "SELECT 'exists'
				FROM staff
				WHERE UPPER(USERNAME)=UPPER('" . DBEscapeString( $_REQUEST['staff']['USERNAME'] ) . "')
				AND SYEAR='" . UserSyear() . "'
				AND STAFF_ID!='" . (int) UserStaffID() . "'
				UNION SELECT 'exists'
				FROM students
				WHERE UPPER(USERNAME)=UPPER('" . DBEscapeString( $_REQUEST['staff']['USERNAME'] ) . "')" );

			if ( ! empty( $existing_username ) )
			{
				$error[] = _( 'A user with that username already exists. Choose a different username and try again.' );
			}
		}

		if ( UserStaffID() && ! $error )
		{
			// Hook.
			do_action( 'Users/User.php|update_user_checks' );

			$profile_RET = DBGet( "SELECT PROFILE,PROFILE_ID,USERNAME
				FROM staff
				WHERE STAFF_ID='" . UserStaffID() . "'" );

			if ( isset( $_REQUEST['staff']['PROFILE'] )
				&& $_REQUEST['staff']['PROFILE'] != $profile_RET[1]['PROFILE']
				&& $_REQUEST['staff']['PROFILE'] != $profile_RET[1]['PROFILE_ID'] )
			{
				if ( $_REQUEST['staff']['PROFILE'] == 'admin' )
				{
					$_REQUEST['staff']['PROFILE_ID'] = '1';
				}
				elseif ( $_REQUEST['staff']['PROFILE'] == 'teacher' )
				{
					$_REQUEST['staff']['PROFILE_ID'] = '2';
				}
				elseif ( $_REQUEST['staff']['PROFILE'] == 'parent' )
				{
					$_REQUEST['staff']['PROFILE_ID'] = '3';
				}

				if ( $profile_RET[1]['PROFILE'] === 'none' )
				{
					// Old Profile was "No Access": Account Activation.
					$user_account_activated = true;
				}
			}

			if ( ! empty( $_REQUEST['staff']['PROFILE_ID'] ) )
			{
				DBQuery( "DELETE FROM staff_exceptions WHERE USER_ID='" . UserStaffID() . "'" );
			}
			elseif ( isset( $_REQUEST['staff']['PROFILE_ID'] ) && $profile_RET[1]['PROFILE_ID'] )
			{
				DBQuery( "DELETE FROM staff_exceptions WHERE USER_ID='" . UserStaffID() . "'" );
				DBQuery( "INSERT INTO staff_exceptions (USER_ID,MODNAME,CAN_USE,CAN_EDIT)
					SELECT s.STAFF_ID,e.MODNAME,e.CAN_USE,e.CAN_EDIT
					FROM staff s,profile_exceptions e
					WHERE s.STAFF_ID='" . UserStaffID() . "'
					AND s.PROFILE_ID=e.PROFILE_ID" );
			}

			if ( ! empty( $_REQUEST['staff'] ) && ! $error )
			{
				$fields_RET = DBGet( "SELECT ID,TYPE
					FROM staff_fields
					ORDER BY SORT_ORDER IS NULL,SORT_ORDER", [], [ 'ID' ] );

				$update_columns = [];

				foreach ( (array) $_REQUEST['staff'] as $column => $value )
				{
					if ( isset( $fields_RET[str_replace( 'CUSTOM_', '', $column )][1]['TYPE'] )
						&& $fields_RET[str_replace( 'CUSTOM_', '', $column )][1]['TYPE'] == 'numeric'
						&& $value != ''
						&& ! is_numeric( $value ) )
					{
						$error[] = _( 'Please enter valid Numeric data.' );
						continue;
					}

					if ( is_array( $value ) )
					{
						// Select Multiple from Options field type format.
						$value = implode( '||', $value ) ? '||' . implode( '||', $value ) : '';
					}
					elseif ( $column == 'PASSWORD' )
					{
						if ( empty( $value ) )
						{
							continue;
						}

						/**
						 * SQL update User password for all school years
						 *
						 * @since 12.0
						 *
						 * Handle case when a user changes password in a non default school year
						 *
						 * MySQL fix You can't specify target table 'staff' for update in FROM clause
						 * @link https://stackoverflow.com/questions/4429319/you-cant-specify-target-table-for-update-in-from-clause/14302701#14302701
						 */
						DBQuery( "UPDATE staff
							SET PASSWORD='" . encrypt_password( $_POST['staff']['PASSWORD'] ) . "'
							WHERE USERNAME=(SELECT st2.USERNAME
								FROM (SELECT * FROM staff) AS st2
								WHERE st2.STAFF_ID='" . UserStaffID() . "')" );

						// SchoolSafe L4 — mot de passe changé : les autres
						// sessions sont révoquées (époque L1, garde Warehouse).
						if ( function_exists( 'SchoolSafeAccountRevoke' ) )
						{
							$schoolsafe_pw_user = isset( $_POST['staff']['USERNAME'] )
								&& $_POST['staff']['USERNAME'] !== ''
								? (string) $_POST['staff']['USERNAME']
								: (string) DBGetOne( "SELECT USERNAME FROM staff
									WHERE STAFF_ID='" . UserStaffID() . "'" );

							if ( $schoolsafe_pw_user !== '' )
							{
								SchoolSafeAccountRevoke( $schoolsafe_pw_user, 0 );
							}
						}

						continue;
					}

					$update_columns[ $column ] = $value;
				}

				if ( $update_columns )
				{
					DBUpdate(
						'staff',
						$update_columns,
						[ 'STAFF_ID' => UserStaffID() ]
					);

					$note[] = button( 'check' ) . ' ' . _( 'Your changes were saved.' );

					// Hook.
					do_action( 'Users/User.php|update_user' );
				}
			}
			elseif ( ! $error
				&& ( ! empty( $_POST['values'] )
					|| ! empty( $_FILES ) ) )
			{
				$note[] = button( 'check' ) . ' ' . _( 'Your changes were saved.' );
			}
		}
		elseif ( ! $error ) // New user.
		{
			// Hook.
			do_action( 'Users/User.php|create_user_checks' );

			// SchoolSafe — nommer un profil métier dès la création (P2) : un
			// PROFILE_ID n'est retenu que s'il désigne un profil existant que
			// l'acteur peut attribuer ; le profil de base en découle, il ne peut
			// donc pas diverger du profil nommé. Sinon, comportement natif :
			// le profil par défaut de la famille native.
			$named_profile_id = $_REQUEST['staff']['PROFILE_ID'] ?? '';
			$named_profile_RET = ctype_digit( (string) $named_profile_id )
				&& (int) $named_profile_id > 0
				? DBGet( "SELECT PROFILE FROM user_profiles WHERE ID='" . (int) $named_profile_id . "'" )
				: [];

			if ( $named_profile_RET
				&& function_exists( 'SchoolSafeProfileDelegable' )
				&& SchoolSafeProfileDelegable( (string) $named_profile_id, $named_profile_RET[1]['PROFILE'] ) )
			{
				$_REQUEST['staff']['PROFILE'] = $named_profile_RET[1]['PROFILE'];

				// Un parent n'est jamais rattaché à une école : ses enfants le lient.
				if ( $_REQUEST['staff']['PROFILE'] === 'parent' )
				{
					$_REQUEST['staff']['SCHOOLS'] = '';
				}
			}
			else
			{
				$_REQUEST['staff']['PROFILE_ID'] = '';

				if ( $_REQUEST['staff']['PROFILE'] == 'admin' )
				{
					$_REQUEST['staff']['PROFILE_ID'] = '1';
				}
				elseif ( $_REQUEST['staff']['PROFILE'] == 'teacher' )
				{
					$_REQUEST['staff']['PROFILE_ID'] = '2';
				}
				elseif ( $_REQUEST['staff']['PROFILE'] == 'parent' )
				{
					$_REQUEST['staff']['PROFILE_ID'] = '3';
				}
			}

			if ( ! $error )
			{
				$insert_columns = [ 'SYEAR' => UserSyear() ];

				$fields_RET = DBGet( "SELECT ID,TYPE
					FROM staff_fields
					ORDER BY SORT_ORDER IS NULL,SORT_ORDER", [], [ 'ID' ] );

				foreach ( (array) $_REQUEST['staff'] as $column => $value )
				{
					if ( isset( $fields_RET[str_replace( 'CUSTOM_', '', $column )][1]['TYPE'] )
						&& $fields_RET[str_replace( 'CUSTOM_', '', $column )][1]['TYPE'] == 'numeric'
						&& $value != ''
						&& ! is_numeric( $value ) )
					{
						$error[] = _( 'Please enter valid Numeric data.' );
						break;
					}

					if ( is_array( $value ) )
					{
						// Select Multiple from Options field type format.
						$value = implode( '||', $value ) ? '||' . implode( '||', $value ) : '';
					}
					elseif ( $column == 'PASSWORD' )
					{
						$value = encrypt_password( $_POST['staff']['PASSWORD'] );
					}

					$insert_columns[ $column ] = $value;
				}

				if ( basename( $_SERVER['PHP_SELF'] ) == 'index.php' )
				{
					// Public Registration: force School Year (default) & Profile (No Access).
					$insert_columns['SYEAR'] = Config( 'SYEAR' );

					$insert_columns['PROFILE'] = 'none';
				}

				$staff_id = DBInsert(
					'staff',
					$insert_columns,
					'id'
				);

				SetUserStaffID( $_REQUEST['staff_id'] = $staff_id );

				// SchoolSafe lot U1 (A6) : compte créé, le mot de passe temporaire
				// de l'assistant ne reste pas en session.
				if ( ! empty( $_POST['schoolsafe_hub_password'] ) )
				{
					unset( $_SESSION['SchoolSafeHubPassword'] );
				}

				// Hook.
				do_action( 'Users/User.php|create_user' );
			}
		}

		if ( UserStaffID()
			&& ! empty( $_FILES ) )
		{
			$uploaded = FilesUploadUpdate(
				'staff',
				'staff',
				$FileUploadsPath . 'User/' . UserStaffID() . '/'
			);
		}

		if ( UserStaffID()
			&& ! empty( $_FILES['photo']['name'] ) )
		{
			$new_photo_file = ImageUpload(
				'photo',
				[ 'width' => 150, 'height' => 150 ],
				$UserPicturesPath . UserSyear() . '/',
				[],
				'.jpg',
				// @since 9.0 Fix Improper Access Control security issue: add random string to photo file name.
				// @since 11.0 Fix PHP fatal error if openssl PHP extension is missing
				UserStaffID() . '.' . bin2hex( function_exists( 'openssl_random_pseudo_bytes' ) ?
					openssl_random_pseudo_bytes( 16 ) :
					( function_exists( 'random_bytes' ) ? random_bytes( 16 ) :
						mb_substr( sha1( rand( 999999999, 9999999999 ), true ), 0, 16 ) ) )
			);

			if ( $new_photo_file )
			{
				// Remove old photos.
				$old_photo_files = glob( $UserPicturesPath . UserSyear() . '/' . UserStaffID() . '.*jpg' );

				foreach ( $old_photo_files as $old_photo_file )
				{
					if ( $old_photo_file !== $new_photo_file )
					{
						// Security: use FileDelete() instead of unlink().
						FileDelete( $old_photo_file, '.jpg' );
					}
				}
			}

			// Hook.
			// @since 9.0 Add $new_photo_file argument to action hook.
			do_action( 'Users/User.php|upload_user_photo', $new_photo_file );
		}

		if ( UserStaffID() )
		{
			require_once 'ProgramFunctions/SendNotification.fnc.php';

			if ( basename( $_SERVER['PHP_SELF'] ) === 'index.php' )
			{
				/**
				 * Send Create User Account email to System Notify address.
				 *
				 * @since 5.7
				 */
				SendNotificationCreateUserAccount( UserStaffID(), $RosarioNotifyAddress );
			}

			if ( ! empty( $staff_id ) ) // $staff_id is set on new.
			{
				// Send New Administrator Account email to System Notify address.
				SendNotificationNewAdministrator( UserStaffID(), $RosarioNotifyAddress );
			}

			if ( ! empty( $user_account_activated ) )
			{
				// @since 5.9 Send Account Activation email notification to User.
				SendNotificationActivateUserAccount( UserStaffID() );
			}
		}
	}

	if ( ! in_array( $include, $categories ) )
	{
		if ( ! mb_strpos( $include, '/' ) )
		{
			require 'modules/Users/includes/' . $include . '.inc.php';
		}
		else // ex.: Food Service, custom module or plugin.
		{
			if ( file_exists( 'plugins/' . $include . '.inc.php' ) )
			{
				// @since 4.5 Include Student/User Info tab from custom plugin.
				require 'plugins/' . $include . '.inc.php';
			}
			elseif ( file_exists( 'modules/' . $include . '.inc.php' ) )
			{
				require 'modules/' . $include . '.inc.php';
			}
		}
	}

	if ( $error && ! UserStaffID() )
	{
		$_REQUEST['staff_id'] = 'new';
	}

	// Unset modfunc & staff & redirect URL.
	RedirectURL( [ 'modfunc', 'staff' ] );

	if ( User( 'STAFF_ID' ) == $_REQUEST['staff_id'] )
	{
		unset( $_ROSARIO['User'] );
	}
}

if ( basename( $_SERVER['PHP_SELF'] ) !== 'index.php' )
{
	if ( isset( $_REQUEST['staff_id'] )
		&& $_REQUEST['staff_id'] === 'new' )
	{
		$_ROSARIO['HeaderIcon'] = 'Users';

		DrawHeader( _( 'Add a User' ) );
	}
	else
	{
		DrawHeader( ProgramTitle() );
	}
}
elseif ( ! UserStaffID() )
{
	// FJ create account.
	$_ROSARIO['HeaderIcon'] = 'Users';

	DrawHeader( _( 'Create User Account' ) );
}
else
{
	/**
	 * Account created, redirect to index. Redirection is done in HTML.
	 *
	 * @link https://stackoverflow.com/questions/42216700/how-can-i-redirect-after-oauth2-with-samesite-strict-and-still-get-my-cookies#answer-64216367
	 */
	$redirect_url = 'index.php?modfunc=logout&reason=account_created&token=' . $_SESSION['token'];
	?>
	<html>
	<head>
	<meta http-equiv="REFRESH" content="0;URL=<?php echo URLEscape( $redirect_url ); ?>" />
	</head>
	<body></body>
	</html>
	<?php
	exit;
}

if ( $_REQUEST['modfunc'] === 'delete'
	&& basename( $_SERVER['PHP_SELF'] ) !== 'index.php'
	&& UserStaffID() !== User( 'STAFF_ID' )
	&& User( 'PROFILE' ) === 'admin'
	&& AllowEdit()
	&& ( ! function_exists( 'SchoolSafeAccountBlocksWrite' ) || ! SchoolSafeAccountBlocksWrite() )
	&& ( ( function_exists( 'SchoolSafeGlobalAdmin' ) && SchoolSafeGlobalAdmin() ) || SchoolSafeStaffWithinScope( (int) $_REQUEST['staff_id'] ) ) )
{
	// SchoolSafe lot U1 (A7, 05/11/2026) : supprimer un parent efface ses liens
	// avec ses enfants. Avant confirmation, on montre ces enfants et on signale
	// ceux qui n'auraient plus aucun parent relié.
	$ss_delete_children = DBGet( "SELECT s.STUDENT_ID," . DisplayNameSQL( 's' ) . " AS FULL_NAME,
		(SELECT COUNT(*) FROM students_join_users j2
			WHERE j2.STUDENT_ID=s.STUDENT_ID) AS NB_PARENTS
		FROM students s
		JOIN students_join_users j ON j.STUDENT_ID=s.STUDENT_ID
		WHERE j.STAFF_ID='" . (int) $_REQUEST['staff_id'] . "'
		ORDER BY FULL_NAME" );

	if ( $ss_delete_children && empty( $_REQUEST['delete_ok'] ) )
	{
		$ss_children_list = [];
		$ss_orphans = [];

		foreach ( (array) $ss_delete_children as $ss_child )
		{
			$ss_children_list[] = AttrEscape( $ss_child['FULL_NAME'] );

			if ( (int) $ss_child['NB_PARENTS'] <= 1 )
			{
				$ss_orphans[] = AttrEscape( $ss_child['FULL_NAME'] );
			}
		}

		$ss_delete_note = 'Ce compte est relié à : <b>' . implode( ', ', $ss_children_list ) . '</b>. '
			. 'La suppression retire ces liens.';

		if ( $ss_orphans )
		{
			$ss_delete_note .= ' <b>Attention : ' . implode( ', ', $ss_orphans )
				. ' n’aura plus aucun parent relié.</b>';
		}

		echo ErrorMessage( [ $ss_delete_note ], 'warning' );
	}

	if ( DeletePrompt( _( 'User' ) ) )
	{
		$delete_sql = UserDeleteSQL( $_REQUEST['staff_id'] );

		DBQuery( $delete_sql );

		// Remove photos on delete.
		$old_photo_files = glob( $UserPicturesPath . UserSyear() . '/' . $_REQUEST['staff_id'] . '.*jpg' );

		foreach ( $old_photo_files as $old_photo_file )
		{
			// Security: use FileDelete() instead of unlink().
			FileDelete( $old_photo_file, '.jpg' );
		}

		// Hook.
		do_action( 'Users/User.php|delete_user' );

		unset( $_SESSION['staff_id'] );

		// Unset modfunc & staff_id & redirect URL.
		RedirectURL( [ 'modfunc', 'staff_id' ] );
	}
}

if ( $_REQUEST['modfunc'] === 'remove_file'
	&& basename( $_SERVER['PHP_SELF'] ) !== 'index.php'
	&& AllowEdit() )
{
	if ( DeletePrompt( _( 'File' ) ) )
	{
		$column = DBEscapeIdentifier( 'CUSTOM_' . $_REQUEST['id'] );

		$file = $FileUploadsPath . 'User/' . UserStaffID() . '/' . $_REQUEST['filename'];

		DBQuery( "UPDATE staff
			SET " . $column . "=REPLACE(" . $column . ", '" . DBEscapeString( $file ) . "||', '')
			WHERE STAFF_ID='" . UserStaffID() . "'" );

		// Security: use FileDelete() instead of unlink().
		FileDelete( $file );

		// Unset modfunc, id, filename & redirect URL.
		RedirectURL( [ 'modfunc', 'id', 'filename' ] );
	}
}

// SchoolSafe lot U1 (A6) : création lancée depuis l'assistant et refusée → retour à l'assistant
// (le mot de passe temporaire reste en session 15 minutes, rien n'est perdu).
if ( ! empty( $error ) && ! empty( $_POST['schoolsafe_hub_password'] ) && UserSchool() )
{
	$error[] = '<a href="/ecoles/' . (int) UserSchool() . '/utilisateurs/nouveau">'
		. 'Revenir à l’assistant de création pour corriger</a>';
}

echo ErrorMessage( $error );

echo ErrorMessage( $note, 'note' );

if ( basename( $_SERVER['PHP_SELF'] ) !== 'index.php' )
{
	if ( isset( $_REQUEST['staff_id'] )
		&& $_REQUEST['staff_id'] === 'new' )
	{
		SchoolSafeCard(
			'staff',
			'Staff',
			'Personnel',
			'Add a user',
			'Ajouter un utilisateur',
			'This page creates a user. Enter the identity, the profile and the username. After saving, the user file opens.',
			'Cette page crée un utilisateur. Indiquez son identité, son profil et son identifiant. Après l’enregistrement, sa fiche s’ouvre.'
		);
	}
	else
	{
		SchoolSafeCard(
			'folder',
			'File',
			'Dossier',
			'User file',
			'Fiche de l’utilisateur',
			'This page finds a user, then opens the file. Enter the name, the username or the profile. The file brings together the identity, the profile and the related information.',
			'Cette page sert à retrouver un utilisateur, puis à ouvrir sa fiche. Indiquez son nom, son identifiant ou son profil. La fiche réunit son identité, son profil et les informations qui lui sont liées.'
		);
	}
}

Search( 'staff_id', issetVal( $extra, [] ) );

if (  ( UserStaffID()
	|| ( isset( $_REQUEST['staff_id'] )
		&& $_REQUEST['staff_id'] === 'new' ) )
	&& $_REQUEST['modfunc'] !== 'delete'
	&& $_REQUEST['modfunc'] !== 'remove_file' )
{
	if ( User( 'PROFILE_ID' ) )
	{
		$can_use_RET = DBGet( "SELECT MODNAME
			FROM profile_exceptions
			WHERE PROFILE_ID='" . User( 'PROFILE_ID' ) . "'
			AND CAN_USE='Y'", [], [ 'MODNAME' ] );
	}
	else
	{
		$can_use_RET = DBGet( "SELECT MODNAME
			FROM staff_exceptions
			WHERE USER_ID='" . User( 'STAFF_ID' ) . "'
			AND CAN_USE='Y'", [], [ 'MODNAME' ] );
	}

	if ( basename( $_SERVER['PHP_SELF'] ) == 'index.php' )
	{
		$can_use_RET['Users/User.php&category_id=1'] = true;
	}

	if ( mb_strpos( $_REQUEST['modfunc'], 'delete_' ) !== 0
		|| ! empty( $_REQUEST['delete_ok'] ) )
	{
		if ( $_REQUEST['staff_id'] !== 'new' )
		{
			$sql = "SELECT s.STAFF_ID," . DisplayNameSQL( 's' ) . " AS FULL_NAME,
			s.TITLE,s.FIRST_NAME,s.LAST_NAME,s.MIDDLE_NAME,s.NAME_SUFFIX,
			s.USERNAME,s.PASSWORD,s.SCHOOLS,s.PROFILE,s.PROFILE_ID,s.EMAIL,
			s.LAST_LOGIN,s.SYEAR,s.ROLLOVER_ID
			FROM staff s
			WHERE s.STAFF_ID='" . UserStaffID() . "'";

			$staff = DBGet( $sql );

			$staff = $staff[1];
		}

		if ( basename( $_SERVER['PHP_SELF'] ) !== 'index.php' )
		{
			$form_action = 'Modules.php?modname=' . $_REQUEST['modname'] .
			'&category_id=' . $category_id . '&staff_id=' . UserStaffID() . '&modfunc=update';
		}
		else
		{
			// FJ create account.
			$form_action = 'index.php?create_account=user&staff_id=new&modfunc=update';
		}

		echo '<form name="staff" id="staff"	action="' . URLEscape( $form_action ) . '"
			method="POST" enctype="multipart/form-data">';

		$delete_button = '';

		if ( basename( $_SERVER['PHP_SELF'] ) !== 'index.php' )
		{
			if ( UserStaffID()
				&& UserStaffID() !== User( 'STAFF_ID' )
				&& User( 'PROFILE' ) === 'admin'
				&& AllowEdit() )
			{
				// @since 5.0 Cannot delete teacher if has course periods.
				$can_delete = DBTransDryRun( UserDeleteSQL( UserStaffID() ) );

				if ( $can_delete )
				{
					$delete_url = "Modules.php?modname=" . $_REQUEST['modname'] .
						'&staff_id=' . UserStaffID() . "&modfunc=delete";

					$delete_button = '<input type="button" value="' . AttrEscape( _( 'Delete' ) ) .
						// @since RosarioSIS 12.5 CSP remove unsafe-inline Javascript
						'" class="onclick-ajax-link" data-link="' . URLEscape( $delete_url ) . '" />';
				}
				else
				{
					// SchoolSafe lot U2 (B6) : dire pourquoi le bouton Supprimer manque.
					$delete_button = '<span class="legend-gray">'
						. 'Suppression impossible : ce compte est encore lié à des données de l’école '
						. '(cours, notes, paiements…). Retirez-le d’abord de ses cours, ou passez son profil '
						. 'à « Sans accès » pour bloquer la connexion en gardant l’historique.</span>';
				}
			}
		}

		$name = $_REQUEST['staff_id'] !== 'new' ? $staff['FULL_NAME'] . ' - ' . $staff['STAFF_ID'] : '';

		DrawHeader( $name, $delete_button . SubmitButton() );
		if (User('PROFILE') === 'parent' && (int)UserStaffID() === (int)User('STAFF_ID')) {
			require_once 'ProgramFunctions/SchoolSafePalmaresAwards.fnc.php';
			echo SchoolSafePalmaresParentBadges();
		}

		// Hook.
		do_action( 'Users/User.php|header' );

		$profile = '';

		if ( UserStaffID() )
		{
			$profile = DBGetOne( "SELECT PROFILE
				FROM staff WHERE
				STAFF_ID='" . UserStaffID() . "'" );
		}

		$categories_RET = DBGet( "SELECT ID,TITLE,INCLUDE
			FROM staff_field_categories
			WHERE " . ( $profile ? DBEscapeIdentifier( $profile ) . "='Y'" : "ID='1'" ) . "
			ORDER BY SORT_ORDER IS NULL,SORT_ORDER,TITLE" );

		$tabs = [];

		foreach ( (array) $categories_RET as $category )
		{
			if ( ! empty( $can_use_RET['Users/User.php&category_id=' . $category['ID']] ) )
			{
				$tabs[] = [
					'title' => $category['TITLE'],
					'link' => ( $_REQUEST['staff_id'] !== 'new' ?
						'Modules.php?modname=' . $_REQUEST['modname'] . '&category_id=' . $category['ID'] . '&staff_id=' . UserStaffID() :
						'' ),
				];
			}
		}

		$_ROSARIO['selected_tab'] = 'Modules.php?modname=' . $_REQUEST['modname'] . '&category_id=' . $category_id . '&staff_id=' . UserStaffID();

		echo '<br />';
		PopTable( 'header', $tabs, 'width="100%"' );
		$PopTable_opened = true;

		if ( ! empty( $can_use_RET['Users/User.php&category_id=' . $category_id] ) )
		{
			if ( ! mb_strpos( $include, '/' ) )
			{
				require 'modules/Users/includes/' . $include . '.inc.php';
			}
			else
			{
				if ( file_exists( 'plugins/' . $include . '.inc.php' ) )
				{
					// @since 4.5 Include Student/User Info tab from custom plugin.
					require 'plugins/' . $include . '.inc.php';
				}
				elseif ( file_exists( 'modules/' . $include . '.inc.php' ) )
				{
					require 'modules/' . $include . '.inc.php';
				}

				$separator = '<hr>';

				require_once 'modules/Users/includes/Other_Info.inc.php';
			}
		}

		PopTable( 'footer' );

		echo '<br /><div class="center">' . SubmitButton() . '</div>';
		echo '</form>';
	}
	elseif ( ! empty( $can_use_RET['Users/User.php&category_id=' . $category_id] ) )
	{
		// Is Deleting from Other tab.
		if ( ! mb_strpos( $include, '/' ) )
		{
			require 'modules/Users/includes/' . $include . '.inc.php';
		}
		else
		{
			if ( file_exists( 'plugins/' . $include . '.inc.php' ) )
			{
				// @since 4.5 Include Student/User Info tab from custom plugin.
				require 'plugins/' . $include . '.inc.php';
			}
			else
			{
				require 'modules/' . $include . '.inc.php';
			}
		}
	}
}
