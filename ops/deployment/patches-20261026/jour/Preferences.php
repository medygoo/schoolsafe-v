<?php
require_once 'ProgramFunctions/Theme.fnc.php';

DrawHeader( ProgramTitle() );

if ( empty( $_REQUEST['tab'] )
	|| User( 'PROFILE' ) === 'student' )
{
	$_REQUEST['tab'] = 'password';
}

// SchoolSafe : explication contextuelle concrete PAR onglet (demande du
// propriétaire) — le texte du haut decrit l'onglet choisi, avec repli sur
// le texte general. Motif existant : bandeau theme verrouille (onglet Affichage).
$ss_tab_explanations = [
	'password' => [
		'Changes your account password. It is updated for every school year of your username: your next sign-in, here and in every other school where you are registered, will use the new password. The current password is required to confirm your identity.',
		'Change le mot de passe de votre compte. Il est mis à jour pour toutes les années scolaires de votre nom d’utilisateur : votre prochaine connexion, ici comme dans les autres écoles où vous êtes inscrit, utilisera le nouveau mot de passe. L’ancien mot de passe est demandé pour confirmer votre identité.',
	],
	'display_options' => [
		'Sets what YOU see on screen, with no effect on other users: the highlight color of list rows, the format of every displayed date, and the option to hide the last sign-in reminder. The theme is locked by SchoolSafe: the presentation is unique and identical for everyone.',
		'Règle ce que VOUS voyez à l’écran, sans effet pour les autres utilisateurs : la couleur de surbrillance des lignes des listes, le format de toutes les dates affichées, et la possibilité de masquer le rappel de dernière connexion. Le thème est verrouillé par SchoolSafe : la présentation est unique et identique pour tous.',
	],
	'print_options' => [
		'Sets the PDF documents you print: paper size, the header band color of printed lists, and the envelope window side for mailing labels. These settings apply to you only.',
		'Règle les documents PDF que vous imprimez : format du papier, couleur de la bande d’en-tête des listes, et côté de la fenêtre des enveloppes pour les étiquettes postales. Ces réglages ne concernent que vous.',
	],
	'student_listing' => [
		'Sets your student lists: the default sort order, the downloaded file format (and its dates), and whether the search screen shows before each list.',
		'Règle vos listes d’élèves : l’ordre de tri par défaut, le format du fichier exporté (et de ses dates), et l’affichage de l’écran de recherche avant chaque liste.',
	],
	'student_fields' => [
		'Choose the student information YOU see: Search column = criteria offered in the search screen; Detail View = blocks shown on the student page. Unchecking hides the field for you only — other users are not affected. The Addresses category chooses the displayed address: none, residence, mailing, bus pickup or bus drop-off.',
		'Choisissez les informations d’élève que VOUS voyez : colonne Recherche = critères proposés dans l’écran de recherche ; Vue détaillée = blocs affichés sur la fiche élève. Décocher vous masque le champ à vous seul — les autres utilisateurs ne sont pas affectés. La catégorie Adresses choisit l’adresse affichée : aucune, résidence, courrier, montée ou descente du bus.',
	],
	'widgets' => [
		'Each checked widget adds ONE criteria line to your student search screen: calendar, next year, course, request, days absent, GPA, rank, letter grade, eligibility, activity, cafeteria balance/status/barcode, discipline (reporter, date, fields), billing balance. No other effect: check only what you use.',
		'Chaque widget coché ajoute UNE ligne de critère dans votre écran de recherche d’élèves : calendrier, année suivante, cours, demande, jours d’absence, moyenne, rang, note, éligibilité, activité, solde/statut/code-barres cafeteria, discipline (rapporteur, date, champs), solde de facturation. Aucun autre effet : ne cochez que ce que vous utilisez.',
	],
	'staff_fields' => [
		'Same as student fields, but for USER records (staff, parents): Search = criteria of the user search screen; Detail View = blocks of the user page.',
		'Identique aux champs élève, mais pour les fiches UTILISATEURS (personnel, parents) : Recherche = critères de l’écran de recherche des utilisateurs ; Vue détaillée = blocs de la fiche utilisateur.',
	],
	'staff_widgets' => [
		'Each checked widget adds a criteria to your user search: Permissions (rights filter), staff cafeteria balance/status/barcode, payroll balance.',
		'Chaque widget coché ajoute un critère dans votre recherche d’utilisateurs : Permissions (filtre par droits), solde/statut/code-barres cafeteria du personnel, solde de paie.',
	],
];

$ss_generic_explanation = [
	'This page sets the display, the printing, the password and the list columns for the signed-in user.',
	'Cette page règle l’affichage, l’impression, le mot de passe et les colonnes des listes pour l’utilisateur connecté.',
];

$ss_explanation = isset( $ss_tab_explanations[ $_REQUEST['tab'] ] ) ?
	$ss_tab_explanations[ $_REQUEST['tab'] ] :
	$ss_generic_explanation;

SchoolSafeCard(
	'settings',
	'Settings',
	'Réglages',
	'My preferences',
	'Mes préférences',
	$ss_explanation[0],
	$ss_explanation[1]
);

if ( $_REQUEST['tab'] === 'print_options' )
{
	// Set page size options for use by SelectInput() and to check if page size is valid before save
	$page_size_options = [ 'A4' => 'A4', 'LETTER' => _( 'US Letter' ) ];
}

if ( $_REQUEST['modfunc'] === 'save'
	&& ! empty( $_REQUEST['values'] )
	&& ! empty( $_POST['values'] ) )
{
	if ( $_REQUEST['tab'] == 'password' )
	{
		$current_password = $_POST['values']['current'];
		$new_password = $_POST['values']['new'];

		//hook
		do_action( 'Users/Preferences.php|update_password_checks' );

		if ( ! $error )
		{
			//FJ enable password change for students

			if ( User( 'PROFILE' ) === 'student' )
			{
				$password = DBGetOne( "SELECT PASSWORD
					FROM students
					WHERE STUDENT_ID='" . UserStudentID() . "'" );
			}
			else
			{
				$password = DBGetOne( "SELECT PASSWORD
					FROM staff
					WHERE STAFF_ID='" . User( 'STAFF_ID' ) . "'
					AND SYEAR='" . UserSyear() . "'" );
			}

			if ( ! match_password( $password, $current_password ) )
			{
				$error[] = _( 'Your current password was incorrect.' );
			}
			else
			{
				if ( User( 'PROFILE' ) === 'student' )
				{
					DBUpdate(
						'students',
						[ 'PASSWORD' => encrypt_password( $new_password ) ],
						[ 'STUDENT_ID' => UserStudentID() ]
					);
				}
				else
				{
					/**
					 * SQL update User password for all school years
					 *
					 * @since 12.0
					 *
					 * Handle case when a user changes password in a non default school year
					 */
					DBUpdate(
						'staff',
						[ 'PASSWORD' => encrypt_password( $new_password ) ],
						[ 'USERNAME' => DBEscapeString( User( 'USERNAME' ) ) ]
					);
				}

				$note[] = _( 'Your new password was saved.' );

				//hook
				do_action( 'Users/Preferences.php|update_password' );
			}
		}
	}
	else
	{
		if ( $_REQUEST['tab'] == 'student_listing' && $_REQUEST['values']['Preferences']['SEARCH'] != 'Y' )
		{
			$_REQUEST['values']['Preferences']['SEARCH'] = 'N';
		}

		if ( $_REQUEST['tab'] == 'student_listing' && User( 'PROFILE' ) === 'admin' && $_REQUEST['values']['Preferences']['DEFAULT_FAMILIES'] != 'Y' )
		{
			$_REQUEST['values']['Preferences']['DEFAULT_FAMILIES'] = 'N';
		}

		if ( $_REQUEST['tab'] == 'student_listing'
			&& User( 'PROFILE' ) === 'admin'
			&& ( empty( $_REQUEST['values']['Preferences']['DEFAULT_ALL_SCHOOLS'] )
				|| $_REQUEST['values']['Preferences']['DEFAULT_ALL_SCHOOLS'] != 'Y' ) )
		{
			$_REQUEST['values']['Preferences']['DEFAULT_ALL_SCHOOLS'] = 'N';
		}

		if ( $_REQUEST['tab'] == 'display_options' && $_REQUEST['values']['Preferences']['HIDE_ALERTS'] != 'Y' )
		{
			$_REQUEST['values']['Preferences']['HIDE_ALERTS'] = 'N';
		}

		// ProgramUserConfig() ignores theme writes for every tab and preserves
		// historical values; other display and printing preferences still save.

		if ( $_REQUEST['tab'] == 'print_options'
			&& ! in_array( $_REQUEST['values']['Preferences']['PAGE_SIZE'], array_keys( $page_size_options ) ) )
		{
			// Security fix selected page size does not exist
			unset( $_REQUEST['values']['Preferences']['PAGE_SIZE'] );
		}

		// SchoolSafe B2 : tout decocher doit pouvoir tout effacer. Les cases
		// decochees ne postent rien et ProgramUserConfig n'efface jamais :
		// sans cles array pour un programme de l'onglet courant, on purge
		// ses lignes (celles de l'utilisateur connecte uniquement).
		$ss_tab_programs = [
			'student_fields' => [ 'StudentFieldsSearch', 'StudentFieldsView' ],
			'widgets' => [ 'WidgetsSearch' ],
			'staff_fields' => [ 'StaffFieldsSearch', 'StaffFieldsView' ],
			'staff_widgets' => [ 'StaffWidgetsSearch' ],
		];

		if ( isset( $ss_tab_programs[ $_REQUEST['tab'] ] ) )
		{
			foreach ( $ss_tab_programs[ $_REQUEST['tab'] ] as $ss_program )
			{
				if ( ! isset( $_REQUEST['values'][ $ss_program ] )
					|| ! is_array( $_REQUEST['values'][ $ss_program ] ) )
				{
					DBQuery( "DELETE FROM program_user_config
						WHERE PROGRAM='" . $ss_program . "'
						AND USER_ID='" . (int) User( 'STAFF_ID' ) . "'" );
				}
			}
		}

		// SchoolSafe B3 : valeurs de preferences validees — couleurs en hex
		// (#rrggbb) et longueur plafonnee, comme le controle PAGE_SIZE ci-dessus.
		if ( isset( $_REQUEST['values']['Preferences'] ) && is_array( $_REQUEST['values']['Preferences'] ) )
		{
			foreach ( [ 'HIGHLIGHT', 'HEADER' ] as $ss_color_key )
			{
				if ( isset( $_REQUEST['values']['Preferences'][ $ss_color_key ] )
					&& ! preg_match( '/^#[0-9a-fA-F]{6}$/', (string) $_REQUEST['values']['Preferences'][ $ss_color_key ] ) )
				{
					unset( $_REQUEST['values']['Preferences'][ $ss_color_key ] );
				}
			}

			foreach ( $_REQUEST['values']['Preferences'] as $ss_key => $ss_value )
			{
				if ( ! is_string( $ss_value ) || mb_strlen( $ss_value ) > 100 )
				{
					unset( $_REQUEST['values']['Preferences'][ $ss_key ] );
				}
			}
		}

		foreach ( (array) $_REQUEST['values'] as $program => $values )
		{
			ProgramUserConfig( $program, 0, $values );
		}

		$note[] = button( 'check' ) . '&nbsp;' . _( 'Your preferences were saved.' );

		$old_theme = Preferences( 'THEME' );

		// So Preferences() will get the new values.
		unset( $_ROSARIO['Preferences'] );

		// Theme changed? Update it live!
		ThemeLiveUpdate( Preferences( 'THEME' ), $old_theme, false );
	}

	// Unset modfunc, values & redirect URL.
	RedirectURL( [ 'modfunc', 'values' ] );
}

// Unset search modfunc & redirect URL.
RedirectURL( 'search_modfunc' );

echo ErrorMessage( $error );

echo ErrorMessage( $note, 'note' );

if ( ! $_REQUEST['modfunc'] )
{
	$current_RET = DBGet( "SELECT TITLE,VALUE,PROGRAM
		FROM program_user_config
		WHERE USER_ID='" . User( 'STAFF_ID' ) . "'
		AND PROGRAM IN ('Preferences','StudentFieldsSearch','StudentFieldsView',
			'WidgetsSearch','StaffFieldsSearch','StaffFieldsView','StaffWidgetsSearch')",
		[], [ 'PROGRAM', 'TITLE' ] );

	echo '<form action="' . URLEscape(
		'Modules.php?modname=' . $_REQUEST['modname'] . '&modfunc=save&tab=' . $_REQUEST['tab']
	) . '" method="POST">';

	DrawHeader( '', Buttons( _( 'Save' ) ) );

	echo '<br />';

	if ( User( 'PROFILE' ) === 'admin'
		|| User( 'PROFILE' ) === 'teacher' )
	{
		$_ROSARIO['allow_edit'] = true;

		$tabs = [
			[
				'title' => _( 'Display Options' ),
				'link' => 'Modules.php?modname=' . $_REQUEST['modname'] . '&tab=display_options',
			],
			[
				'title' => _( 'Print Options' ),
				'link' => 'Modules.php?modname=' . $_REQUEST['modname'] . '&tab=print_options',
			],
			[
				'title' => _( 'Student Listing' ),
				'link' => 'Modules.php?modname=' . $_REQUEST['modname'] . '&tab=student_listing',
			],
			[
				'title' => _( 'Password' ),
				'link' => 'Modules.php?modname=' . $_REQUEST['modname'] . '&tab=password',
			],
			[
				'title' => _( 'Student Fields' ),
				'link' => 'Modules.php?modname=' . $_REQUEST['modname'] . '&tab=student_fields',
			],
			[
				'title' => _( 'Widgets' ),
				'link' => 'Modules.php?modname=' . $_REQUEST['modname'] . '&tab=widgets',
			],
		];

		if ( User( 'PROFILE' ) === 'admin' )
		{
			$tabs[] = [
				'title' => _( 'User Fields' ),
				'link' => 'Modules.php?modname=' . $_REQUEST['modname'] . '&tab=staff_fields',
			];

			$tabs[] = [
				'title' => _( 'User Widgets' ),
				'link' => 'Modules.php?modname=' . $_REQUEST['modname'] . '&tab=staff_widgets',
			];
		}
	}
	elseif ( User( 'PROFILE' ) === 'parent' )
	{
		$_ROSARIO['allow_edit'] = true;

		$tabs = [
			[
				'title' => _( 'Display Options' ),
				'link' => 'Modules.php?modname=' . $_REQUEST['modname'] . '&tab=display_options',
			],
			[
				'title' => _( 'Print Options' ),
				'link' => 'Modules.php?modname=' . $_REQUEST['modname'] . '&tab=print_options',
			],
			[
				'title' => _( 'Password' ),
				'link' => 'Modules.php?modname=' . $_REQUEST['modname'] . '&tab=password',
			],
			[
				'title' => _( 'Student Fields' ),
				'link' => 'Modules.php?modname=' . $_REQUEST['modname'] . '&tab=student_fields',
			],
		];
	}

	// FJ enable password change for students.
	else
	{
		$tabs = [
			[
				'title' => _( 'Password' ),
				'link' => 'Modules.php?modname=' . $_REQUEST['modname'] . '&tab=password',
			],
		];
	}

	$_ROSARIO['selected_tab'] = 'Modules.php?modname=' . $_REQUEST['modname'] . '&tab=' . $_REQUEST['tab'];

	$LO_options = [
		'responsive' => false,
		'search' => false,
		'save' => false,
		'count' => false,
		'valign-middle' => true,
	];

	if ( ! in_array( $_REQUEST['tab'], [ 'student_fields', 'staff_fields' ] ) )
	{
		PopTable( 'header', $tabs );
	}
	else
	{
		// FJ Responsive student/staff fields preferences.
		$LO_options['header'] = WrapTabs( $tabs, $_ROSARIO['selected_tab'] );
	}

	// Inputs param defaults.
	$allow_na = $div = false;

	$new = true;

	$extra = '';

	// Student Listing tab

	if ( $_REQUEST['tab'] === 'student_listing' )
	{
		echo '<table class="cellpadding-5"><tr><td>';

		// Student Sorting.
		echo SelectInput(
			Preferences( 'SORT' ),
			'values[Preferences][SORT]',
			_( 'Student Sorting' ),
			[ 'Name' => _( 'Name' ), 'Grade' => _( 'Grade Level' ) . ', ' . _( 'Name' ) ],
			$allow_na,
			$extra,
			$div
		);

		echo '</td></tr><tr><td>';

		// File Export Type.
		echo SelectInput(
			Preferences( 'DELIMITER' ),
			'values[Preferences][DELIMITER]',
			_( 'File Export Type' ),
			[
				'Tab' => _( 'Excel' ),
				'CSV' => 'CSV (OpenOffice / LibreOffice) (UTF-8)', // Do not Translate
				'XML' => 'XML', // Do not Translate
			],
			$allow_na,
			$extra,
			$div
		);

		echo '</td></tr><tr><td>';

		// Date Export Format.
		echo SelectInput(
			Preferences( 'E_DATE' ),
			'values[Preferences][E_DATE]',
			_( 'Date Export Format' ),
			[
				'' => _( 'Display Options Format' ),
				'MM/DD/YYYY' => 'MM/DD/YYYY', // Do not Translate
				// @since 7.1 Export (Excel) date to YYYY-MM-DD format (ISO).
				'YYYY-MM-DD' => 'YYYY-MM-DD', // Do not Translate
			],
			$allow_na,
			$extra,
			$div
		);

		echo '</td></tr>';

		if ( User( 'PROFILE' ) === 'admin'
			|| User( 'PROFILE' ) === 'teacher' )
		{
			// Display student search screen.
			echo '<tr><td>' . CheckboxInput(
				Preferences( 'SEARCH' ),
				'values[Preferences][SEARCH]',
				_( 'Display student search screen' ),
				'',
				$new
			) . '</td></tr>';
		}

		if ( User( 'PROFILE' ) === 'admin' )
		{
			// Group by family by default.
			echo '<tr><td>' . CheckboxInput(
				Preferences( 'DEFAULT_FAMILIES' ),
				'values[Preferences][DEFAULT_FAMILIES]',
				_( 'Group by family by default' ),
				'',
				$new
			) . '</td></tr>';

			// FJ if only one school, no Search All Schools option.
			// Restrict Search All Schools to user schools.

			if ( SchoolInfo( 'SCHOOLS_NB' ) > 1
				&& ( ! trim( User( 'SCHOOLS' ), ',' )
					|| mb_substr_count( User( 'SCHOOLS' ), ',' ) > 2 ) )
			{
				// Search all schools by default.
				echo '<tr><td>' . CheckboxInput(
					Preferences( 'DEFAULT_ALL_SCHOOLS' ),
					'values[Preferences][DEFAULT_ALL_SCHOOLS]',
					_( 'Search all schools by default' ),
					'',
					$new
				) . '</td></tr>';
			}
		}

		echo '</table>';
	}

	// Display Options tab.

	if ( $_REQUEST['tab'] === 'display_options' )
	{
		echo '<table class="cellpadding-5"><tr><td>';

		echo '<p class="ss-theme-locked"><strong>SchoolSafe</strong> — '
			. SchoolSafeT( 'Single presentation. Alternative theme selection is suspended.', 'Présentation unique. La sélection des autres thèmes est suspendue.' )
			. '</p>';

		$extra = '';

		echo '</td></tr><tr><td>';

		echo ColorInput(
			Preferences( 'HIGHLIGHT' ),
			'values[Preferences][HIGHLIGHT]',
			_( 'Highlight Color' ),
			$extra,
			$div
		);

		echo '</td></tr><tr><td>';

		// @since 7.1 Select Date Format: Add Preferences( 'DATE' ).
		// @link https://en.wikipedia.org/wiki/Date_format_by_country
		// @link https://www.php.net/strftime
		// @since 9.0 Fix PHP8.1 deprecated strftime() use strftime_compat() instead
		$date_options = [
			'%B %d %Y' => ucfirst( strftime_compat( '%B %d %Y' ) ), // August 18 2020.
			'%b %d %Y' => ucfirst( strftime_compat( '%b %d %y' ) ), // Aug 18 20.
			'%d %B %Y' => strftime_compat( '%d %B %Y' ), // 18 August 2020.
			'%d %b %Y' => strftime_compat( '%d %b %y' ), // 18 Aug 20.
			'%m/%d/%Y' => strftime_compat( '%m/%d/%Y' ), // 08/18/2020.
			'%d/%m/%Y' => strftime_compat( '%d/%m/%Y' ), // 18/08/2020.
			'%Y/%m/%d' => strftime_compat( '%Y/%m/%d' ), // 2020/08/18.
			'%d.%m.%Y' => strftime_compat( '%d.%m.%Y' ), // 18.08.2020.
			'%d-%m-%Y' => strftime_compat( '%d-%m-%Y' ), // 18-08-2020.
			'%Y-%m-%d' => strftime_compat( '%Y-%m-%d' ), // 2020-08-18.
		];

		echo SelectInput(
			Preferences( 'DATE' ),
			'values[Preferences][DATE]',
			_( 'Date Format' ),
			$date_options,
			$allow_na,
			$extra,
			$div
		);

		echo '</td></tr><tr><td>';

		// Disable login alerts
		echo CheckboxInput(
			Preferences( 'HIDE_ALERTS' ),
			'values[Preferences][HIDE_ALERTS]',
			_( 'Disable login alerts' ),
			'',
			$new
		);

		echo '</td></tr></table>';
	}

	// Pint Options tab

	if ( $_REQUEST['tab'] === 'print_options' )
	{
		echo '<table class="cellpadding-5"><tr><td>';

		// Page Size
		echo SelectInput(
			Preferences( 'PAGE_SIZE' ),
			'values[Preferences][PAGE_SIZE]',
			_( 'Page Size' ),
			$page_size_options,
			$allow_na,
			$extra,
			$div
		);

		echo '</td></tr><tr><td>';

		echo ColorInput(
			Preferences( 'HEADER' ),
			'values[Preferences][HEADER]',
			_( 'PDF List Header Color' ),
			$extra,
			$div
		);

		echo '</td></tr><tr><td>';

		/**
		 * Add Mailing Label Position
		 * Position or side for a windowed envelope
		 * Defaults to right for French speaking countries
		 *
		 * @since 11.6
		 */
		echo SelectInput(
			Preferences( 'MAILING_LABEL_POSITION' ),
			'values[Preferences][MAILING_LABEL_POSITION]',
			_( 'Mailing Label Position' ),
			[ 'left' => _( 'Left' ), 'right' => _( 'Right' ) ],
			$allow_na,
			$extra,
			$div
		);

		echo '</td></tr><tr><td></table>';
	}

	if ( $_REQUEST['tab'] === 'password' )
	{
		AllowEditTemporary( 'start' );

		//FJ password fields are required
		echo '<table class="cellpadding-5"><tr><td>';

		// Current Password
		echo PasswordInput( '', 'values[current]', _( 'Current Password' ), 'required' );

		echo '</td></tr><tr><td>';

		// @since 11.1 Prevent using App name, username, or email in the password
		$_ROSARIO['PasswordInput']['user_inputs'] = [
			User( 'USERNAME' ),
			User( 'EMAIL' ),
		];

		// New Password.
		echo PasswordInput( '', 'values[new]', _( 'New Password' ), 'required strength' );

		echo '</td></tr></table>';

		AllowEditTemporary( 'stop' );
	}

	// Student Fields tab.

	if ( $_REQUEST['tab'] === 'student_fields' )
	{
		// SchoolSafe B2 : hidden indispensable — si toutes les cases Recherche sont
		// decochees, aucune cle values[StudentFieldsSearch] n'est postee sinon.
		echo '<input type="hidden" name="values[StudentFieldsSearch]" />';

		$custom_fields_sql = "SELECT sfc.TITLE AS CATEGORY,cf.ID,cf.TITLE,cf.TYPE,
				'' AS SEARCH,'' AS DISPLAY
			FROM custom_fields cf,student_field_categories sfc
			WHERE sfc.ID=cf.CATEGORY_ID
			AND (SELECT CAN_USE FROM " .
			( User( 'PROFILE_ID' ) ?
			"profile_exceptions WHERE PROFILE_ID='" . User( 'PROFILE_ID' ) . "'" :
			"staff_exceptions WHERE USER_ID='" . User( 'STAFF_ID' ) . "'" ) .
			" AND MODNAME=CONCAT('Students/Student.php&category_id=', cf.CATEGORY_ID)
			LIMIT 1)='Y'
			AND cf.TYPE<>'files'
			ORDER BY sfc.SORT_ORDER IS NULL,sfc.SORT_ORDER,sfc.TITLE,cf.SORT_ORDER IS NULL,cf.SORT_ORDER,cf.TITLE";

		$custom_fields_RET = DBGet(
			$custom_fields_sql,
			[ 'SEARCH' => '_make', 'DISPLAY' => '_make' ],
			[ 'CATEGORY' ]
		);

		foreach ( $custom_fields_RET as &$category_RET )
		{
			foreach ( $category_RET as &$field )
			{
				$field['CATEGORY'] = '<b>' . ParseMLField( $field['CATEGORY'] ) . '</b>';
				$field['TITLE'] = ParseMLField( $field['TITLE'] );
			}
		}

		// Student Fields: search Username.
		$general_info_category_title = DBGetOne( "SELECT sfc.TITLE
			FROM student_field_categories sfc
			WHERE sfc.ID=1" );

		if ( ! isset( $custom_fields_RET[$general_info_category_title] ) )
		{
			// Empty General Info category.
			$custom_fields_RET[$general_info_category_title] = [];
		}

		$THIS_RET['ID'] = 'USERNAME';
		$username_field = [
			'CATEGORY' => '<b>' . ParseMLField( $general_info_category_title ) . '</b>',
			'ID' => 'USERNAME',
			'TITLE' => _( 'Username' ),
			'SEARCH' => _make( 'USERNAME', 'SEARCH' ),
			'DISPLAY' => _make( 'USERNAME', 'DISPLAY' ),
		];

		// Add Username to General Info fields.
		$custom_fields_RET[$general_info_category_title] = array_merge(
			[ $username_field ],
			$custom_fields_RET[$general_info_category_title]
		);

		$THIS_RET['ID'] = 'CONTACT_INFO';
		$custom_fields_RET[-1][1] = [
			'CATEGORY' => '<b>' . _( 'Contact Information' ) . '</b>',
			'ID' => 'CONTACT_INFO',
			'TITLE' => button( 'down_phone', '', '', 'bigger' ) . ' ' . _( 'Contact Information' ),
			'DISPLAY' => _make( '', 'DISPLAY' ),
		];

		$THIS_RET['ID'] = 'HOME_PHONE';
		$custom_fields_RET[-1][] = [
			'CATEGORY' => '<b>' . _( 'Contact Information' ) . '</b>',
			'ID' => 'HOME_PHONE',
			'TITLE' => _( 'Home Phone Number' ),
			'DISPLAY' => _make( '', 'DISPLAY' ),
		];

		$THIS_RET['ID'] = 'GUARDIANS';
		$custom_fields_RET[-1][] = [
			'CATEGORY' => '<b>' . _( 'Contact Information' ) . '</b>',
			'ID' => 'GUARDIANS',
			'TITLE' => _( 'Guardians' ),
			'DISPLAY' => _make( '', 'DISPLAY' ),
		];

		$THIS_RET['ID'] = 'ALL_CONTACTS';
		$custom_fields_RET[-1][] = [
			'CATEGORY' => '<b>' . _( 'Contact Information' ) . '</b>',
			'ID' => 'ALL_CONTACTS',
			'TITLE' => _( 'All Contacts' ),
			'DISPLAY' => _make( '', 'DISPLAY' ),
		];

		$custom_fields_RET[0][1] = [
			'CATEGORY' => '<b>' . _( 'Addresses' ) . '</b>',
			'ID' => 'ADDRESS',
			'TITLE' => _( 'None' ),
			'DISPLAY' => _makeAddress( '' ),
		];

		$custom_fields_RET[0][] = [
			'CATEGORY' => '<b>' . _( 'Addresses' ) . '</b>',
			'ID' => 'ADDRESS',
			'TITLE' => button( 'house', '', '', 'bigger' ) . ' ' . _( 'Residence' ),
			'DISPLAY' => _makeAddress( 'RESIDENCE' ),
		];

		//FJ disable mailing address display

		if ( Config( 'STUDENTS_USE_MAILING' ) )
		{
			$custom_fields_RET[0][] = [
				'CATEGORY' => '<b>' . _( 'Addresses' ) . '</b>',
				'ID' => 'ADDRESS',
				'TITLE' => button( 'mailbox', '', '', 'bigger' ) . ' ' . _( 'Mailing' ),
				'DISPLAY' => _makeAddress( 'MAILING' ),
			];
		}

		$custom_fields_RET[0][] = [
			'CATEGORY' => '<b>' . _( 'Addresses' ) . '</b>',
			'ID' => 'ADDRESS',
			'TITLE' => button( 'bus', '', '', 'bigger' ) . ' ' . _( 'Bus Pickup' ),
			'DISPLAY' => _makeAddress( 'BUS_PICKUP' ),
		];

		$custom_fields_RET[0][] = [
			'CATEGORY' => '<b>' . _( 'Addresses' ) . '</b>',
			'ID' => 'ADDRESS',
			'TITLE' => button( 'bus', '', '', 'bigger' ) . ' ' . _( 'Bus Dropoff' ),
			'DISPLAY' => _makeAddress( 'BUS_DROPOFF' ),
		];

		if ( User( 'PROFILE' ) === 'admin'
			|| User( 'PROFILE' ) === 'teacher' )
		{
			$columns = [
				'CATEGORY' => '<span class="a11y-hidden">' . _( 'Category' ) . '</span>',
				'TITLE' => _( 'Field' ),
				'SEARCH' => _( 'Search' ),
				'DISPLAY' => _( 'Expanded View' ),
			];
		}
		else
		{
			$columns = [
				'CATEGORY' => '<span class="a11y-hidden">' . _( 'Category' ) . '</span>',
				'TITLE' => _( 'Field' ),
				'DISPLAY' => _( 'Expanded View' ),
			];
		}

		ListOutput(
			$custom_fields_RET,
			$columns,
			'.',
			'.',
			[],
			[ [ 'CATEGORY' ] ],
			$LO_options
		);
	}

	// Widgets tab.

	if ( $_REQUEST['tab'] === 'widgets'
		&& in_array( User( 'PROFILE' ), [ 'admin', 'teacher' ], true ) )
	{
		$widgets = [];

		if ( $RosarioModules['Students'] )
		{
			$widgets += [
				'calendar' => _( 'Calendar' ),
				'next_year' => _( 'Next School Year' ),
			];
		}

		if ( $RosarioModules['Scheduling'] && User( 'PROFILE' ) === 'admin' )
		{
			$widgets += [ 'course' => _( 'Course' ), 'request' => _( 'Request' ) ];
		}

		if ( $RosarioModules['Attendance'] )
		{
			$widgets += [ 'absences' => _( 'Days Absent' ) ];
		}

		if ( $RosarioModules['Grades'] )
		{
			$widgets += [
				'gpa' => _( 'GPA' ),
				'class_rank' => _( 'Class Rank' ),
				'letter_grade' => _( 'Grade' ),
			];
		}

		if ( $RosarioModules['Eligibility'] )
		{
			$widgets += [ 'eligibility' => _( 'Eligibility' ), 'activity' => _( 'Activity' ) ];
		}

		if ( $RosarioModules['Food_Service'] )
		{
			$widgets += [
				'fsa_balance' => _( 'Food Service Balance' ),
				'fsa_discount' => _( 'Food Service Discount' ),
				'fsa_status' => _( 'Food Service Status' ),
				'fsa_barcode' => _( 'Food Service Barcode' ),
			];
		}

		if ( $RosarioModules['Discipline'] )
		{
			$widgets += [
				'reporter' => _( 'Discipline Reporter' ),
				'incident_date' => _( 'Discipline Incident Date' ),
				'discipline_fields' => _( 'Discipline Fields' ),
			];
		}

		if ( $RosarioModules['Student_Billing'] )
		{
			$widgets += [ 'balance' => _( 'Student Billing Balance' ) ];
		}

		$widgets_RET[0] = [];

		foreach ( (array) $widgets as $widget => $title )
		{
			$THIS_RET['ID'] = $widget;
			$widgets_RET[] = [ 'ID' => $widget, 'TITLE' => $title, 'WIDGET' => _make( '', 'WIDGET' ) ];
		}

		unset( $widgets_RET[0] );

		echo '<input type="hidden" name="values[WidgetsSearch]" />';

		$columns = [ 'TITLE' => _( 'Widget' ), 'WIDGET' => _( 'Search' ) ];

		ListOutput(
			$widgets_RET,
			$columns,
			'.',
			'.',
			[],
			[],
			$LO_options
		);
	}

	// Staff Fields tab.

	if ( $_REQUEST['tab'] === 'staff_fields'
		&& User( 'PROFILE' ) === 'admin' )
	{
		$custom_fields_sql = "SELECT sfc.TITLE AS CATEGORY,cf.ID,cf.TITLE,cf.TYPE,
				'' AS STAFF_SEARCH,'' AS STAFF_DISPLAY
			FROM staff_fields cf,staff_field_categories sfc
			WHERE sfc.ID=cf.CATEGORY_ID
			AND (SELECT CAN_USE
				FROM " .
			( User( 'PROFILE_ID' ) ?
			"profile_exceptions WHERE PROFILE_ID='" . User( 'PROFILE_ID' ) . "'" :
			"staff_exceptions WHERE USER_ID='" . User( 'STAFF_ID' ) . "'" ) .
			" AND MODNAME=CONCAT('Users/User.php&category_id=', cf.CATEGORY_ID)
			LIMIT 1)='Y'
			AND cf.TYPE<>'files'
			ORDER BY sfc.SORT_ORDER IS NULL,sfc.SORT_ORDER,sfc.TITLE,cf.SORT_ORDER IS NULL,cf.SORT_ORDER,cf.TITLE";

		$custom_fields_RET = DBGet(
			$custom_fields_sql,
			[ 'STAFF_SEARCH' => '_make', 'STAFF_DISPLAY' => '_make' ],
			[ 'CATEGORY' ]
		);

		foreach ( $custom_fields_RET as &$category_RET )
		{
			foreach ( $category_RET as &$field )
			{
				$field['CATEGORY'] = '<b>' . ParseMLField( $field['CATEGORY'] ) . '</b>';
				$field['TITLE'] = ParseMLField( $field['TITLE'] );
			}
		}

		// User Fields: search Email Address & Phone.
		$general_info_category_title = DBGetOne( "SELECT sfc.TITLE
			FROM staff_field_categories sfc
			WHERE sfc.ID=1" );

		if ( isset( $custom_fields_RET[$general_info_category_title] ) )
		{
			$i = count( $custom_fields_RET[$general_info_category_title] );
		}
		else
		{
			$i = 1;

			// Empty General Info category.
			$custom_fields_RET[$general_info_category_title] = [];
		}

		echo '<input type="hidden" name="values[StaffFieldsSearch]" />
			<input type="hidden" name="values[StaffFieldsView]" />';

		$columns = [
			'CATEGORY' => '<span class="a11y-hidden">' . _( 'Category' ) . '</span>',
			'TITLE' => _( 'Field' ),
			'STAFF_SEARCH' => _( 'Search' ),
			'STAFF_DISPLAY' => _( 'Expanded View' ),
		];

		//FJ no responsive table
		ListOutput(
			$custom_fields_RET,
			$columns,
			'.',
			'.',
			[],
			[ [ 'CATEGORY' ] ],
			$LO_options
		);
	}

	// Staff Widgets tab

	if ( $_REQUEST['tab'] === 'staff_widgets'
		&& User( 'PROFILE' ) === 'admin' )
	{
		$widgets = [];

		if ( $RosarioModules['Users'] )
		{
			$widgets += [ 'permissions' => _( 'Permissions' ) ];
		}

		if ( $RosarioModules['Food_Service'] )
		{
			$widgets += [
				'fsa_balance' => _( 'Food Service Balance' ),
				'fsa_status' => _( 'Food Service Status' ),
				'fsa_barcode' => _( 'Food Service Barcode' ),
			];
		}

		if ( $RosarioModules['Accounting'] )
		{
			$widgets += [ 'staff_balance' => _( 'Staff Payroll Balance' ) ];
		}

		$widgets_RET[0] = [];

		foreach ( (array) $widgets as $widget => $title )
		{
			$THIS_RET['ID'] = $widget;
			$widgets_RET[] = [ 'ID' => $widget, 'TITLE' => $title, 'STAFF_WIDGET' => _make( '', 'STAFF_WIDGET' ) ];
		}

		unset( $widgets_RET[0] );

		echo '<input type="hidden" name="values[StaffWidgetsSearch]" />';
		$columns = [ 'TITLE' => _( 'Widget' ), 'STAFF_WIDGET' => _( 'Search' ) ];

		ListOutput(
			$widgets_RET,
			$columns,
			'.',
			'.',
			[],
			[],
			$LO_options
		);
	}

	if ( ! in_array( $_REQUEST['tab'], [ 'student_fields', 'staff_fields' ] ) )
	{
		PopTable( 'footer' );
	}

	echo '<br /><div class="center">' . Buttons( _( 'Save' ) ) . '</div>';
	echo '</form>';
}

/**
 * Make Checkbox
 *
 * @since 5.3.2 & 4.9.12 Fix regression since 4.4 save unchecked config option: use CheckboxInput()
 *
 * @param string $value
 * @param string $name Column.
 *
 * @return string Checkbox HTML.
 */
function _make( $value, $name )
{
	global $THIS_RET,
		$current_RET,
		$_ROSARIO;

	// No Search checkbox for textarea fields.

	if ( isset( $THIS_RET['TYPE'] )
		&& $THIS_RET['TYPE'] === 'textarea'
		&& mb_strpos( $name, 'SEARCH' ) !== false )
	{
		return '';
	}

	switch ( $name )
	{
		case 'SEARCH':
			$program = 'StudentFieldsSearch';

			break;

		case 'DISPLAY':
			$program = 'StudentFieldsView';

			break;

		case 'WIDGET':
			$program = 'WidgetsSearch';

			break;

		case 'STAFF_SEARCH':
			$program = 'StaffFieldsSearch';

			break;

		case 'STAFF_DISPLAY':
			$program = 'StaffFieldsView';

			break;

		case 'STAFF_WIDGET':
			$program = 'StaffWidgetsSearch';

			break;
	}

	$name = 'values[' . $program . '][' . $THIS_RET['ID'] . ']';

	$_ROSARIO['allow_edit'] = true;

	$checkbox = CheckboxInput(
		issetVal( $current_RET[ $program ][$THIS_RET['ID']][1]['VALUE'], '' ),
		$name,
		'',
		'',
		true
	);

	$_ROSARIO['allow_edit'] = false;

	return $checkbox;
}

/**
 * @param $value
 */
function _makeAddress( $value )
{
	global $current_RET;

	$checked = ( empty( $current_RET['StudentFieldsView']['ADDRESS'][1]['VALUE'] ) && $value == '' )
	|| ( ! empty( $current_RET['StudentFieldsView']['ADDRESS'][1]['VALUE'] ) == $value
		&& $current_RET['StudentFieldsView']['ADDRESS'][1]['VALUE'] == $value ) ? ' checked' : '';

	return '<input type="radio" name="values[StudentFieldsView][ADDRESS]" value="' . AttrEscape( $value ) . '"' . $checked . ' />';
}
