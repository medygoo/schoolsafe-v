<?php
require_once 'ProgramFunctions/Theme.fnc.php';
require_once 'ProgramFunctions/FileUpload.fnc.php';
require_once 'ProgramFunctions/SchoolSafeSchoolLogo.fnc.php';
require_once 'modules/School_Setup/includes/SchoolReceiptIdentity.fnc.php';
require_once 'ProgramFunctions/SchoolSafeWorkspace.fnc.php';

$schoolsafe_configuration_school = (int) UserSchool();
if ( $schoolsafe_configuration_school < 1 || ! SchoolSafeSchoolAllowed( $schoolsafe_configuration_school ) )
{
	SchoolSafeScopeDeny();
	exit;
}

if ( empty( $_REQUEST['_ROSARIO_PDF'] ) )
{
	SchoolSafeWorkspaceHeader( 'Paramètres de l’école', 'school' );
}

//FJ add School Configuration
// move the Modules config.inc.php to the database table
// 'config' if the value is needed in multiple modules
// 'program_config' if the value is needed in one module

// Keep native heading initialization and printed output, without duplicate UI titles.
if ( empty( $_REQUEST['_ROSARIO_PDF'] ) ) echo '<div hidden aria-hidden="true">';
DrawHeader( ProgramTitle() );

if ( empty( $_REQUEST['_ROSARIO_PDF'] ) )
{
	echo '</div><header class="ss-f10 ss-rise">';
	echo '<p>' . SchoolSafeT( 'This page sets how the school works. Some settings affect several parts of the application. Change security and access settings with care.', 'Cette page règle le fonctionnement général de l’école. Certains réglages touchent plusieurs parties de l’application. Les réglages de sécurité et d’accès se modifient avec prudence.' ) . '</p>';
	echo '</header>';
}

if ( empty( $_REQUEST['tab'] ) )
{
	$_REQUEST['tab'] = 'school';
}


$configuration_link = '<a href="' . URLEscape( 'Modules.php?modname=' . $_REQUEST['modname'] ) . '">' .
	( $_REQUEST['tab'] !== 'modules' && $_REQUEST['tab'] !== 'plugins' ?
	'<b>' . _( 'Configuration' ) . '</b>' : _( 'Configuration' ) ) . '</a>';

$multiple_schools_admin_has_1_school = SchoolInfo( 'SCHOOLS_NB' ) > 1
	&& DBGetOne( "SELECT SCHOOLS
		FROM staff
		WHERE STAFF_ID='" . User( 'STAFF_ID' ) . "'
		AND SYEAR='" . UserSyear() . "'
		AND SCHOOLS='," . UserSchool() . ",';" );

$modules_link = ' | <a href="' . URLEscape( 'Modules.php?modname=' . $_REQUEST['modname'] . '&tab=modules' ) . '">' .
	( $_REQUEST['tab'] === 'modules' ?
	'<b>' . _( 'Modules' ) . '</b>' : _( 'Modules' ) ) . '</a>';

$plugins_link = ' | <a href="' . URLEscape( 'Modules.php?modname=' . $_REQUEST['modname'] . '&tab=plugins' ) . '">' .
	( $_REQUEST['tab'] === 'plugins' ?
	'<b>' . _( 'Plugins' ) . '</b>' : _( 'Plugins' ) ) . '</a>';

// Hide Modules & Plugins tabs if Administrator of 1 school only.
if ( AllowEdit() && ! $multiple_schools_admin_has_1_school )
{
	DrawHeader( $configuration_link . $modules_link . $plugins_link );
}

if ( $_REQUEST['tab'] === 'modules' )
{
	require_once 'modules/School_Setup/includes/Modules.inc.php';
}
elseif ( $_REQUEST['tab'] === 'plugins' )
{
	require_once 'modules/School_Setup/includes/Plugins.inc.php';
}
else
{
	if ( $_REQUEST['modfunc'] === 'update' )
	{
		if ( ! empty( $_FILES['LOGO_FILE'] )
			&& AllowEdit() )
		{
			// Upload school logo.
			$saved = ImageUpload(
				'LOGO_FILE',
				[],
				SchoolSafeSchoolLogoUploadDirectory(),
				[],
				'.jpg',
				'school_logo_' . UserSchool()
			);
			if ( $saved && is_file( $saved ) ) SchoolSafeUpdateReceiptBrand( UserSchool() );
		}

		if ( ! empty( $_REQUEST['values'] )
			&& $_POST['values']
			&& AllowEdit() )
		{
			$updated = $numeric_error = false;

			$old_theme = Config( 'THEME' );

			$_REQUEST['values']['config'] = issetVal( $_REQUEST['values']['config'] );

			foreach (['SCHOOLSAFE_BIRTHDAYS', 'SCHOOLSAFE_PALMARES'] as $schoolSafeSetting) {
				if (isset($_REQUEST['values']['config'][$schoolSafeSetting])
					&& (User('PROFILE') !== 'admin' || !in_array($_REQUEST['values']['config'][$schoolSafeSetting], ['', 'N', 'Y'], true))) {
					unset($_REQUEST['values']['config'][$schoolSafeSetting]);
				}
			}

			if ( ! empty( $_REQUEST['values']['CONFIG'] ) )
			{
				// Compatibility with add-ons version < 10.0, gather CONFIG (uppercase table name) values too.
				$_REQUEST['values']['config'] = array_merge(
					(array) $_REQUEST['values']['config'],
					$_REQUEST['values']['CONFIG']
				);
			}

			foreach ( (array) $_REQUEST['values']['config'] as $column => $value )
			{
				// Keep stored theme settings untouched while SchoolSafe selection
				// is suspended. Also covers legacy CONFIG and forged case variants.
				if ( in_array( strtoupper( (string) $column ), [ 'THEME', 'THEME_FORCE' ], true ) )
				{
					continue;
				}

				$numeric_columns = [
					'ATTENDANCE_FULL_DAY_MINUTES',
					'CREATE_STUDENT_ACCOUNT_DEFAULT_SCHOOL',
					'FAILED_LOGIN_LIMIT',
					'PASSWORD_STRENGTH',
				];

				if ( in_array( $column, $numeric_columns )
					&& $value != ''
					&& ! is_numeric( $value ) )
				{
					$numeric_error = true;

					continue;
				}

				// This is the current school's configuration, not the platform's
				// inherited defaults. Config() otherwise reuses SCHOOL_ID=0 when
				// no local override exists and can change every school's default.
				$config_mode = DBGetOne( "SELECT 1 FROM config WHERE TITLE='" . DBEscapeString( $column )
					. "' AND SCHOOL_ID=" . $schoolsafe_configuration_school ) ? 'update' : 'insert';
				DBUpsert( 'config', [ 'CONFIG_VALUE' => $value ],
					[ 'TITLE' => $column, 'SCHOOL_ID' => $schoolsafe_configuration_school ], $config_mode );
				unset( $_ROSARIO['Config'] );

				$updated = true;
			}

			$_REQUEST['values']['program_config'] = issetVal( $_REQUEST['values']['program_config'] );

			if ( ! empty( $_REQUEST['values']['PROGRAM_CONFIG'] ) )
			{
				// Compatibility with add-ons version < 10.0, gather PROGRAM_CONFIG (uppercase table name) values too.
				$_REQUEST['values']['program_config'] = array_merge(
					(array) $_REQUEST['values']['program_config'],
					$_REQUEST['values']['PROGRAM_CONFIG']
				);
			}

			foreach ( (array) $_REQUEST['values']['program_config'] as $program => $columns )
			{
				foreach ( (array) $columns as $column => $value )
				{
					$numeric_columns = [
						'ATTENDANCE_EDIT_DAYS_BEFORE',
						'ATTENDANCE_EDIT_DAYS_AFTER',
						'FOOD_SERVICE_BALANCE_WARNING',
						'FOOD_SERVICE_BALANCE_MINIMUM',
						'FOOD_SERVICE_BALANCE_TARGET',
					];

					if ( in_array( $column, $numeric_columns )
						&& $value != ''
						&& ! is_numeric( $value ) )
					{
						$numeric_error = true;

						continue;
					}

					ProgramConfig( $program, $column, $value );

					$updated = true;
				}
			}

			$_REQUEST['values']['schools'] = issetVal( $_REQUEST['values']['schools'] );

			foreach ( (array) $_REQUEST['values']['schools'] as $column => $value )
			{
				$numeric_columns = [
					'NUMBER_DAYS_ROTATION',
				];

				if ( in_array( $column, $numeric_columns )
					&& $value != ''
					&& ! is_numeric( $value ) )
				{
					$numeric_error = true;

					continue;
				}

				$updated = DBUpdate(
					'schools',
					[ $column => $value ],
					[ 'ID' => UserSchool(), 'SYEAR' => UserSyear() ]
				);

				UpdateSchoolArray( UserSchool() );
			}

			if ( $updated )
			{
				$note[] = button( 'check' ) . '&nbsp;' .
				_( 'The school configuration has been modified.' );
			}

			if ( $numeric_error )
			{
				$error[] = _( 'Please enter valid Numeric data.' );
			}

			// Theme changed? Update it live!
			ThemeLiveUpdate( Config( 'THEME' ), $old_theme );
		}

		// Unset modfunc & values & redirect URL.
		RedirectURL( [ 'modfunc', 'values' ] );
	}

	if ( ! $_REQUEST['modfunc'] )
	{
		echo '<form action="' . URLEscape( 'Modules.php?modname=' . $_REQUEST['modname'] . '&tab=' . $_REQUEST['tab'] . '&modfunc=update' ) . '" method="POST" enctype="multipart/form-data">';

		if ( AllowEdit() )
		{
			DrawHeader( '', SubmitButton() );
		}

		echo ErrorMessage( $note, 'note' );

		echo ErrorMessage( $error, 'error' );

		echo '<br />';

		$tabs = [];

		if ( ! $multiple_schools_admin_has_1_school )
		{
			// Hide System config options if Administrator of 1 school only.
			$tabs[] = [
				'title' => Config( 'NAME' ),
				'link' => 'Modules.php?modname=' . $_REQUEST['modname'] . '&tab=system',
			];
		}

		$tabs[] = [
			'title' => _( 'School' ),
			'link' => 'Modules.php?modname=' . $_REQUEST['modname'] . '&tab=school',
		];

		if ( $RosarioModules['Students'] )
		{
			$tabs[] = [
				'title' => _( 'Students' ),
				'link' => 'Modules.php?modname=' . $_REQUEST['modname'] . '&tab=students',
			];
		}

		if ( $RosarioModules['Grades'] )
		{
			$tabs[] = [
				'title' => _( 'Grades' ),
				'link' => 'Modules.php?modname=' . $_REQUEST['modname'] . '&tab=grades',
			];
		}

		if ( $RosarioModules['Attendance'] )
		{
			$tabs[] = [
				'title' => _( 'Attendance' ),
				'link' => 'Modules.php?modname=' . $_REQUEST['modname'] . '&tab=attendance',
			];
		}

		if ( $RosarioModules['Food_Service'] )
		{
			$tabs[] = [
				'title' => _( 'Food Service' ),
				'link' => 'Modules.php?modname=' . $_REQUEST['modname'] . '&tab=food_service',
			];
		}

		/**
		 * Configuration School Tabs action Hook.
		 * Plugins or modules can add their own tabs.
		 *
		 * @since 6.2
		 */
		do_action( 'School_Setup/Configuration.php|school_tabs', [ &$tabs ] );

		$_ROSARIO['selected_tab'] = 'Modules.php?modname=' . $_REQUEST['modname'] . '&tab=' . $_REQUEST['tab'];

		PopTable( 'header', $tabs );

		if ( $_REQUEST['tab'] === 'system'
			&& ! $multiple_schools_admin_has_1_school )
		{
			echo '<p class="ss-f10-note">' . SchoolSafeT( 'These settings apply to the whole application. The theme, public registration and security need particular care.', 'Ces réglages s’appliquent à toute l’application. Le thème, l’inscription publique et la sécurité demandent une attention particulière.' ) . '</p>';

			echo '<table class="cellpadding-5 width-100p"><tr><td>' .
				TextInput( Config( 'NAME' ), 'values[config][NAME]', _( 'Program Name' ), 'required maxlength=20' ) .
			'</td></tr>';

			echo '<tr><td>' .
				MLTextInput( Config( 'TITLE' ), 'values[config][TITLE]', _( 'Program Title' ) ) .
			'</td></tr>';

			echo '<tr><td><p class="ss-theme-locked"><strong>SchoolSafe</strong> — '
				. SchoolSafeT( 'Single presentation. Alternative theme selection is suspended.', 'Présentation unique. La sélection des autres thèmes est suspendue.' )
				. '</p></td></tr>';

			echo '<tr><td><fieldset class="ss-f10-caution"><legend>' . _( 'Public Registration' ) . '</legend><table>';

			echo '<tr><td colspan="2">' . CheckboxInput(
				Config( 'CREATE_USER_ACCOUNT' ),
				'values[config][CREATE_USER_ACCOUNT]',
				_( 'Create User Account' ) .
				'<div class="tooltip"><i>' .
				_( 'New users will be added with the No Access profile' ) .
				'</i></div>',
				'',
				false,
				button( 'check' ),
				button( 'x' )
			) . '</td></tr>';

			$create_student_account_tooltip = '';

			if ( ! Config( 'CREATE_STUDENT_ACCOUNT_AUTOMATIC_ACTIVATION' ) ) // Do NOT use empty() here!
			{
				$create_student_account_tooltip = '<div class="tooltip"><i>' .
					_( 'New students will be added as Inactive students' ) . '</i></div>';
			}

			echo '<tr><td colspan="2">' . CheckboxInput(
				Config( 'CREATE_STUDENT_ACCOUNT' ),
				'values[config][CREATE_STUDENT_ACCOUNT]',
				_( 'Create Student Account' ) . $create_student_account_tooltip,
				'',
				false,
				button( 'check' ),
				button( 'x' )
			) . '</td></tr>';

			// @since 5.9 Automatic Student Account Activation.
			// HTML add arrow to indicate sub-option.
			echo '<tr><td class="valign-middle">&#10551; </td><td>' . CheckboxInput(
				Config( 'CREATE_STUDENT_ACCOUNT_AUTOMATIC_ACTIVATION' ),
				'values[config][CREATE_STUDENT_ACCOUNT_AUTOMATIC_ACTIVATION]',
				_( 'Automatic Student Account Activation' ),
				'',
				false,
				button( 'check' ),
				button( 'x' )
			) . '</td></tr>';

			$school_options = [];

			$schools_RET = DBGet( "SELECT ID,TITLE
				FROM schools
				WHERE SYEAR='" . UserSyear() . "'
				ORDER BY ID" );

			foreach ( $schools_RET as $school )
			{
				$school_options[ $school['ID'] ] = $school['TITLE'];
			}

			// @since 6.3 Create Student Account Default School.
			// HTML add arrow to indicate sub-option.
			echo '<tr><td class="valign-middle">&#10551; </td><td>' . SelectInput(
				Config( 'CREATE_STUDENT_ACCOUNT_DEFAULT_SCHOOL' ),
				'values[config][CREATE_STUDENT_ACCOUNT_DEFAULT_SCHOOL]',
				'',
				$school_options
			);

			echo FormatInputTitle(
				_( 'Default School' ),
				GetInputID( 'values[config][CREATE_STUDENT_ACCOUNT_DEFAULT_SCHOOL]' ),
				false,
				( Config( 'CREATE_STUDENT_ACCOUNT_DEFAULT_SCHOOL' ) ? '' : '<br />' )
			) . ' ';

			// @since 12.6 Force Default School.
			echo CheckboxInput(
				Config( 'CREATE_STUDENT_ACCOUNT_DEFAULT_SCHOOL_FORCE' ),
				'values[config][CREATE_STUDENT_ACCOUNT_DEFAULT_SCHOOL_FORCE]',
				_( 'Force' ),
				'',
				true
			) . '</td></tr>';

			$students_email_field_RET = DBGet( "SELECT ID,TITLE
				FROM custom_fields
				WHERE TYPE='text'
				AND CATEGORY_ID=1" );

			$students_email_field_options = [ 'USERNAME' => _( 'Username' ) ];

			foreach ( (array) $students_email_field_RET as $field )
			{
				$students_email_field_options[ $field['ID'] ] = ParseMLField( $field['TITLE'] );
			}

			echo '<tr><td colspan="2">' . SelectInput(
				Config( 'STUDENTS_EMAIL_FIELD' ),
				'values[config][STUDENTS_EMAIL_FIELD]',
				_( 'Student email field' ),
				$students_email_field_options,
				'N/A'
			) . '</td></tr>';

			echo '</table></fieldset></td></tr>';

			// FJ add Security to Configuration.
			echo '<tr><td><fieldset class="ss-f10-caution"><legend>' . _( 'Security' ) . '</legend><table>';

			// Failed login ban if >= X failed attempts within 10 minutes.
			echo '<tr><td>' . TextInput(
				Config( 'FAILED_LOGIN_LIMIT' ),
				'values[config][FAILED_LOGIN_LIMIT]',
				_( 'Failed Login Attempts Limit' ) .
				'<div class="tooltip"><i>' .
				_( 'Leave the field blank to always allow' ) .
				'</i></div>',
				'type=number maxlength=2 size=2 min=2 max=99'
			) . '</td></tr>';

			// Password Strength.
			// @since 4.4.
			echo '<tr><td>' . TextInput(
				Config( 'PASSWORD_STRENGTH' ),
				'values[config][PASSWORD_STRENGTH]',
				'',
				'type=number maxlength=1 min=0 max=4 style="width:196px;"',
				false
			);

			$password_strength_input_id = GetInputID( 'values[config][PASSWORD_STRENGTH]' );

			// Password strength bars, hang tight.
			// @since 12.5 CSP remove unsafe-inline Javascript
			?>
			<div class="password-strength-bars" style="width:200px;">
				<span class="score0"></span>
				<span class="score1"></span>
				<span class="score2"></span>
				<span class="score3"></span>
				<span class="score4"></span>
			</div>
			<script src="assets/js/csp/modules/schoolSetup/Configuration.js?v=12.5"></script>
			<?php

			echo FormatInputTitle(
				_( 'Password Strength' ) .
				'<div class="tooltip"><i>' .
				_( 'Minimum password strength required.' ) . ' ' .
				_( 'Set to 0 to disable.' ) .
				'</i></div>',
				$password_strength_input_id,
				false,
				''
			) .	'</td></tr>';

			// @since 5.3 Force password change on first login.
			echo '<tr><td>' . CheckboxInput(
				Config( 'FORCE_PASSWORD_CHANGE_ON_FIRST_LOGIN' ),
				'values[config][FORCE_PASSWORD_CHANGE_ON_FIRST_LOGIN]',
				_( 'Force Password Change on First Login' ),
				'',
				false,
				button( 'check' ),
				button( 'x' )
			) . '</td></tr>';

			echo '</table></fieldset></td></tr>';

			/**
			 * Display Name.
			 *
			 * @since 9.3 SQL use CONCAT() instead of pipes || for MySQL compatibility
			 * @link https://www.w3.org/International/questions/qa-personal-names
			 */
			$display_name_options = [
				"CONCAT(FIRST_NAME,' ',LAST_NAME)" => _( 'First Name' ) . ' ' . _( 'Last Name' ),
				"CONCAT(FIRST_NAME,' ',LAST_NAME,coalesce(NULLIF(CONCAT(' ',NAME_SUFFIX),' '),''))" => _( 'First Name' ) . ' ' . _( 'Last Name' ) . ' ' . _( 'Suffix' ),
				"CONCAT(FIRST_NAME,coalesce(NULLIF(CONCAT(' ',MIDDLE_NAME,' '),'  '),' '),LAST_NAME)" => _( 'First Name' ) . ' ' . _( 'Middle Name' ) . ' ' . _( 'Last Name' ),
				"CONCAT(FIRST_NAME,', ',LAST_NAME,coalesce(NULLIF(CONCAT(' ',MIDDLE_NAME),' '),''))" => _( 'First Name' ) . ', ' . _( 'Last Name' ) . ' ' . _( 'Middle Name' ),
				"CONCAT(LAST_NAME,' ',FIRST_NAME)" => _( 'Last Name' ) . ' ' . _( 'First Name' ),
				"CONCAT(LAST_NAME,', ',FIRST_NAME)" => _( 'Last Name' ) . ', ' . _( 'First Name' ),
				"CONCAT(LAST_NAME,', ',FIRST_NAME,coalesce(NULLIF(CONCAT(' ',MIDDLE_NAME),' '),''))" => _( 'Last Name' ) . ', ' . _( 'First Name' ) . ' ' . _( 'Middle Name' ),
				"CONCAT(LAST_NAME,coalesce(NULLIF(CONCAT(' ',MIDDLE_NAME,' '),'  '),' '),FIRST_NAME)" => _( 'Last Name' ) . ' ' . _( 'Middle Name' ) . ' ' . _( 'First Name' ),
			];

			echo '<tr><td>' . SelectInput(
				Config( 'DISPLAY_NAME' ),
				'values[config][DISPLAY_NAME]',
				_( 'Display Name' ),
				$display_name_options,
				false
			) . '</td></tr></table>';
		}

		if ( $_REQUEST['tab'] === 'school' )
		{
			echo '<p class="ss-f10-note">' . SchoolSafeT( 'These settings concern how this school is shown: the year, the logo, the currency and the student search.', 'Ces réglages concernent l’affichage de cette école : l’année, le logo, la monnaie et la recherche d’un élève.' ) . '</p>';

			// School year over one/two calendar years format.
			echo '<table class="cellpadding-5 width-100p"><tr><td>' . CheckboxInput(
				Config( 'SCHOOL_SYEAR_OVER_2_YEARS' ),
				'values[config][SCHOOL_SYEAR_OVER_2_YEARS]',
				_( 'School year over two calendar years' ),
				'',
				false,
				button( 'check' ),
				button( 'x' )
			) . '</td></tr>';

			// Number of Days for the Rotation.
			// @since 11.8 Move "Number of Days for the Rotation" option from School Information to Configuration
			echo '<tr><td>' . TextInput(
				SchoolInfo( 'NUMBER_DAYS_ROTATION' ),
				'values[schools][NUMBER_DAYS_ROTATION]',
				_( 'Number of Days for the Rotation' ) .
				'<div class="tooltip"><i>' .
				_( 'Leave the field blank if the school does not use a Rotation of Numbered Days' ) .
				'</i></div>',
				'type=number size=1 min=2 max=7'
			) . '</td></tr>';

			// Upload school logo.
			echo '<tr><td>' . ( SchoolSafeSchoolLogoPath( (int) UserSchool() ) !== '' ?
				'<br /><img src="' . SchoolSafeSchoolLogoUrl( (int) UserSchool() ) .
				'" style="max-width:225px; max-height:225px;" /><br />' : '' ) .
			FileInput(
				'LOGO_FILE',
				_( 'School logo' ) . ' (.jpg, .png, .gif, .webp)',
				'accept="image/*"'
			) . '</td></tr>';

			// Currency.
			echo '<tr><td><table><tr class="st"><td>' . TextInput(
				Config( 'CURRENCY' ),
				'values[config][CURRENCY]',
				_( 'Currency Symbol' ),
				'maxlength=8 size=3'
			) . '</td>';

			// @since 9.1 Add decimal & thousands separator configuration.
			// @link https://en.wikipedia.org/wiki/Decimal_separator
			$thousands_separator_options = [
				',' => ',',
				'.' => '.',
				'&amp;nbsp;' => '&nbsp;',
			];

			$thousands_separator_value = Config( 'THOUSANDS_SEPARATOR' );

			if ( $thousands_separator_value === '&nbsp;' )
			{
				$thousands_separator_value = '&amp;nbsp;';
			}

			echo '<td>' . SelectInput(
				$thousands_separator_value,
				'values[config][THOUSANDS_SEPARATOR]',
				_( 'Thousands separator' ),
				$thousands_separator_options,
				false
			) . '</td>';

			$decimal_separator_options = [
				'.' => '.',
				',' => ',',
			];

			echo '<td>' . SelectInput(
				Config( 'DECIMAL_SEPARATOR' ),
				'values[config][DECIMAL_SEPARATOR]',
				_( 'Decimal separator' ),
				$decimal_separator_options,
				false
			) . '</td>';

			echo '<td>' . Currency( 1250.00 ) . '</td></tr></table></td></tr>';

			// Add "Find a Student" fieldset.
			echo '<tr><td><fieldset><legend>' . _( 'Find a Student' ) . '</legend><table>';

			// @since 7.4 Add Course Widget configuration option: Popup window or Pull-Down.
			$course_widget_options = [
				'' => _( 'Popup window' ),
				'select' => _( 'Pull-Down' ),
			];

			echo '<tr><td>' . SelectInput(
				Config( 'COURSE_WIDGET_METHOD' ),
				'values[config][COURSE_WIDGET_METHOD]',
				_( 'Course Widget' ),
				$course_widget_options,
				false
			) . '</td></tr>';

			echo '</table></fieldset></td></tr>';

			echo '</table>';
		}

		if ( $_REQUEST['tab'] === 'students' )
		{
			$addresses_contacts_cat_title = DBGetOne( "SELECT TITLE
				FROM student_field_categories
				WHERE ID='3'" );

			echo '<p class="ss-f10-note">' . SchoolSafeT( 'These boxes set student addresses, contacts and comments.', 'Ces cases règlent les adresses, les contacts et les commentaires des élèves.' ) . '</p>';

			echo '<table class="cellpadding-5 width-100p"><tr><td><fieldset><legend>' .
				ParseMLField( $addresses_contacts_cat_title ) . '</legend><table>';

			echo '<tr><td>' . CheckboxInput( Config( 'STUDENTS_USE_MAILING' ), 'values[config][STUDENTS_USE_MAILING]', _( 'Display Mailing Address' ), '', false, button( 'check' ), button( 'x' ) ) . '</td></tr>';

			echo '<tr><td>' . CheckboxInput(
				ProgramConfig( 'students', 'STUDENTS_USE_BUS' ),
				'values[program_config][students][STUDENTS_USE_BUS]',
				_( 'Check Bus Pickup / Dropoff by default' ),
				'',
				false,
				button( 'check' ),
				button( 'x' )
			) . '</td></tr>';

			echo '<tr><td>' . CheckboxInput(
				ProgramConfig( 'students', 'STUDENTS_USE_CONTACT' ),
				'values[program_config][students][STUDENTS_USE_CONTACT]',
				_( 'Enable Legacy Contact Information' ),
				'',
				false,
				button( 'check' ),
				button( 'x' )
			) . '</td></tr>';

			if ( ! $multiple_schools_admin_has_1_school )
			{
				// Hide All schools config options if Administrator of 1 school only.
				echo '<tr><td>' . CheckboxInput(
					Config( 'LIMIT_EXISTING_CONTACTS_ADDRESSES' ),
					'values[config][LIMIT_EXISTING_CONTACTS_ADDRESSES]',
					_( 'Limit Existing Contacts & Addresses to current school' ),
					'',
					false,
					button( 'check' ),
					button( 'x' )
				) . '</td></tr>';
			}

			echo '</table></fieldset></td></tr>';

			echo '<tr><td>' . CheckboxInput(
				ProgramConfig( 'students', 'STUDENTS_SEMESTER_COMMENTS' ),
				'values[program_config][students][STUDENTS_SEMESTER_COMMENTS]',
				_( 'Use Semester Comments instead of Quarter Comments' ),
				'',
				false,
				button( 'check' ),
				button( 'x' )
			) . '</td></tr>';

			echo '</table>';
		}

		if ( $_REQUEST['tab'] === 'grades' )
		{
			$grades_options = [
				'-1' => _( 'Use letter grades only' ),
				'0' => _( 'Use letter and percent grades' ),
				'1' => _( 'Use percent grades only' ),
			];

			echo '<p class="ss-f10-note">' . SchoolSafeT( 'These settings change grade entry and what families can see.', 'Ces réglages changent la saisie des notes et ce que les familles peuvent voir.' ) . '</p>';

			echo '<table class="cellpadding-5 width-100p"><tr><td>' . SelectInput(
				ProgramConfig( 'grades', 'GRADES_DOES_LETTER_PERCENT' ),
				'values[program_config][grades][GRADES_DOES_LETTER_PERCENT]',
				_( 'Grades' ),
				$grades_options,
				false
			) . '</td></tr>';

			echo '<tr><td><fieldset><legend>' .
				_( 'Input Final Grades' ) . '</legend><table>';

			echo '<tr><td>' . CheckboxInput(
				ProgramConfig( 'grades', 'GRADES_HIDE_NON_ATTENDANCE_COMMENT' ),
				'values[program_config][grades][GRADES_HIDE_NON_ATTENDANCE_COMMENT]',
				_( 'Hide grade comment except for attendance period courses' ),
				'',
				false,
				button( 'check' ),
				button( 'x' )
			) . '</td></tr>';

			echo '<tr><td>' . CheckboxInput(
				ProgramConfig( 'grades', 'GRADES_TEACHER_ALLOW_EDIT' ),
				'values[program_config][grades][GRADES_TEACHER_ALLOW_EDIT]',
				_( 'Allow Teachers to edit grades after grade posting period' ),
				'',
				false,
				button( 'check' ),
				button( 'x' )
			) . '</td></tr>';

			echo '</table></fieldset></td></tr>';

			echo '<tr><td><fieldset><legend>' .
				_( 'Gradebook' ) . ' - ' . _( 'Grades' ) . '</legend><table>';

			echo '<tr><td>' . CheckboxInput(
				ProgramConfig( 'grades', 'GRADES_GRADEBOOK_TEACHER_ALLOW_EDIT' ),
				'values[program_config][grades][GRADES_GRADEBOOK_TEACHER_ALLOW_EDIT]',
				_( 'Allow Teachers to edit gradebook grades for past quarters' ),
				'',
				false,
				button( 'check' ),
				button( 'x' )
			) . '</td></tr>';

			echo '</table></fieldset></td></tr>';

			echo '<tr><td><fieldset><legend>' .
				_( 'Student Grades' ) . '</legend><table>';

			echo '<tr><td>' . CheckboxInput(
				ProgramConfig( 'grades', 'GRADES_DO_STATS_STUDENTS_PARENTS' ),
				'values[program_config][grades][GRADES_DO_STATS_STUDENTS_PARENTS]',
				_( 'Enable Anonymous Grade Statistics for Parents and Students' ),
				'',
				false,
				button( 'check' ),
				button( 'x' )
			) . '</td></tr>';

			echo '<tr><td>' . CheckboxInput(
				ProgramConfig( 'grades', 'GRADES_DO_STATS_ADMIN_TEACHERS' ),
				'values[program_config][grades][GRADES_DO_STATS_ADMIN_TEACHERS]',
				_( 'Enable Anonymous Grade Statistics for Administrators and Teachers' ),
				'',
				false,
				button( 'check' ),
				button( 'x' )
			) . '</td></tr>';

			echo '</table></fieldset></td></tr></table>';
		}

		if ( $_REQUEST['tab'] === 'attendance' )
		{
			// @since 11.2 Dynamic Daily Attendance calculation based on total course period minutes
			// @since 12.1 Fix tooltip display at bottom
			$tooltip = '<div class="tooltip bottom"><i>' .
				_( 'Set to 0 for dynamic Daily Attendance calculation based on total course period minutes.' ) .
				'</i></div>';

			echo '<p class="ss-f10-note">' . SchoolSafeT( 'These numbers set the daily calculation and the time teachers have to change attendance.', 'Ces nombres règlent le calcul de la journée et le délai laissé aux enseignants pour modifier la présence.' ) . '</p>';

			echo '<table class="cellpadding-5"><tr><td>' . TextInput(
				Config( 'ATTENDANCE_FULL_DAY_MINUTES' ),
				'values[config][ATTENDANCE_FULL_DAY_MINUTES]',
				_( 'Minutes in a Full School Day' ) . $tooltip,
				' type="number" min="0" max="999"'
			) . '</td></tr>';

			echo '<tr><td>' . TextInput(
				ProgramConfig( 'attendance', 'ATTENDANCE_EDIT_DAYS_BEFORE' ),
				'values[program_config][attendance][ATTENDANCE_EDIT_DAYS_BEFORE]',
				_( 'Number of days before the school date teachers can edit attendance' ) .
				'<div class="tooltip"><i>' .
				_( 'Leave the field blank to always allow' ) .
				'</i></div>',
				' type="number" min="1" max="99"'
			) . '</td></tr>';

			echo '<tr><td>' . TextInput(
				ProgramConfig( 'attendance', 'ATTENDANCE_EDIT_DAYS_AFTER' ),
				'values[program_config][attendance][ATTENDANCE_EDIT_DAYS_AFTER]',
				_( 'Number of days after the school date teachers can edit attendance' ) .
				'<div class="tooltip"><i>' .
				_( 'Leave the field blank to always allow' ) .
				'</i></div>',
				' type="number" min="1" max="99"'
			) . '</td></tr></table>';
		}

		if ( $_REQUEST['tab'] === 'food_service' )
		{
			echo '<p class="ss-f10-note">' . SchoolSafeT( 'These amounts set the food service balance alerts.', 'Ces montants règlent les alertes du solde de restauration.' ) . '</p>';

			echo '<table class="cellpadding-5"><tr><td>' . TextInput(
				ProgramConfig( 'food_service', 'FOOD_SERVICE_BALANCE_WARNING' ),
				'values[program_config][food_service][FOOD_SERVICE_BALANCE_WARNING]',
				_( 'Food Service Balance minimum amount for warning' ),
				' type="number" step="0.01" max="999999999999" min="-999999999999" required'
			) . '</td></tr>';

			echo '<tr><td>' . TextInput(
				ProgramConfig( 'food_service', 'FOOD_SERVICE_BALANCE_MINIMUM' ),
				'values[program_config][food_service][FOOD_SERVICE_BALANCE_MINIMUM]',
				_( 'Food Service Balance minimum amount' ),
				' type="number" step="0.01" max="999999999999" min="-999999999999" required'
			) . '</td></tr>';

			echo '<tr><td>' . TextInput(
				ProgramConfig( 'food_service', 'FOOD_SERVICE_BALANCE_TARGET' ),
				'values[program_config][food_service][FOOD_SERVICE_BALANCE_TARGET]',
				_( 'Food Service Balance target amount' ),
				' type="number" step="0.01" max="999999999999" min="-999999999999" required'
			) . '</td></tr></table>';
		}

		/**
		 * Configuration School Table action Hook.
		 * Plugins or modules can add their own Config options to the table (per school).
		 *
		 * @since 5.8
		 */
		do_action( 'School_Setup/Configuration.php|school_table' );

		echo '<section><h3>SchoolSafe : anniversaires et palmares</h3>';
		echo CheckboxInput(Config('SCHOOLSAFE_BIRTHDAYS'), 'values[config][SCHOOLSAFE_BIRTHDAYS]', 'Anniversaires : tableau de bord et notifications au parent principal', '', false, 'Y', 'N', false);
		echo '<br>' . CheckboxInput(Config('SCHOOLSAFE_PALMARES'), 'values[config][SCHOOLSAFE_PALMARES]', 'Palmares : publier les dix meilleurs eleves dans cette ecole', '', false, 'Y', 'N', false);
		echo '</section>';
		PopTable( 'footer' );

		if ( AllowEdit() )
		{
			echo '<br /><div class="center">' . SubmitButton() . '</div>';
		}

		echo '</form>';
	}
}
