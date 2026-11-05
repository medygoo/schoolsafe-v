<?php
/**
 * Remove Access for selected Students
 * and Associated Parents.
 * Grant Access back.
 *
 * @since 4.8
 *
 * @package RosarioSIS
 */

// Remove or grant access for students.
$accessfunc = empty( $_REQUEST['accessfunc'] ) ? '' : 'grant';

if ( $_REQUEST['modfunc'] === 'save'
	&& AllowEdit()
	// SchoolSafe lot E1 (A12, 05/11/2026) : confirmation avant d'agir.
	&& ( empty( $_REQUEST['st_arr'] ) || Prompt(
		$accessfunc === 'grant' ? 'Rendre l’accès' : 'Bloquer l’accès',
		$accessfunc === 'grant'
			? 'Rendre l’accès aux élèves sélectionnés (et à leurs parents si la case est cochée) ?'
			: 'Bloquer l’accès des élèves sélectionnés (et de leurs parents si la case est cochée) ?'
	) ) )
{
	if ( ! empty( $_REQUEST['st_arr'] ) )
	{
		// @since 5.9 Move REMOVE_ACCESS_USERNAME_PREFIX_ADD from program_config (per school) to config (all schools, 0).
		$username_prefix_add = Config(
			'REMOVE_ACCESS_USERNAME_PREFIX_ADD',
			issetVal( $_REQUEST['username_prefix_add'] )
		);

		$st_list = implode( ',', array_map( 'intval', $_REQUEST['st_arr'] ) );

		// SchoolSafe lot E1 (A12) : sans préfixe, le blocage ne faisait rien mais affichait « Done. ».
		if ( (string) $username_prefix_add === '' && $accessfunc !== 'grant' )
		{
			$error[] = 'Indiquez d’abord le préfixe ajouté au nom d’utilisateur pour bloquer l’accès.';

			RedirectURL( [ 'modfunc', 'st_arr' ] );
		}

		// SchoolSafe lot E1 (A12) : jamais deux fois le préfixe sur un même élève.
		$update_sql = "UPDATE students
			SET USERNAME=CONCAT('" . DBEscapeString( $username_prefix_add ) . "', USERNAME)
			WHERE STUDENT_ID IN (" . $st_list . ")
			AND LEFT(USERNAME, LENGTH('" . DBEscapeString( $username_prefix_add ) . "'))<>'"
				. DBEscapeString( $username_prefix_add ) . "'";

		if ( $accessfunc === 'grant' )
		{
			// @since 11.0 SQL Fix replacement in case the "username" contains the prefix
			$update_sql = "UPDATE students
				SET USERNAME=CONCAT(
					REPLACE(
						SUBSTRING(USERNAME, 1, LENGTH('" . DBEscapeString( $username_prefix_add ) . "')),
						'" . DBEscapeString( $username_prefix_add ) . "',
						''),
					SUBSTR(USERNAME, LENGTH('" . DBEscapeString( $username_prefix_add ) . "')+1, 100))
				WHERE STUDENT_ID IN (" . $st_list . ")";
		}

		DBQuery( $update_sql );

		if ( ! empty( $_REQUEST['parents_no_access'] ) )
		{
			// SchoolSafe lot E1 (A12) : on mémorise les parents passés en « Sans accès » ici,
			// et « Rendre l'accès » ne rétablit que ceux-là (pas un parent bloqué pour une autre raison).
			$ss_blocked = array_filter( array_map( 'intval', explode( ',',
				(string) ProgramConfig( 'remove_access', 'SS_BLOCKED_PARENTS' ) ) ) );

			$ss_parents = array_map( 'intval', array_column( (array) DBGet( "SELECT DISTINCT st.STAFF_ID
				FROM staff st
				JOIN students_join_users j ON j.STAFF_ID=st.STAFF_ID
				WHERE j.STUDENT_ID IN (" . $st_list . ")
				AND st.SYEAR='" . UserSyear() . "'
				AND st.PROFILE='" . ( $accessfunc === 'grant' ? 'none' : 'parent' ) . "'" ), 'STAFF_ID' ) );

			if ( $accessfunc === 'grant' )
			{
				$ss_parents = array_values( array_intersect( $ss_parents, $ss_blocked ) );
				$ss_blocked = array_diff( $ss_blocked, $ss_parents );
			}
			else
			{
				$ss_blocked = array_unique( array_merge( $ss_blocked, $ss_parents ) );
			}

			if ( $ss_parents )
			{
				DBQuery( "UPDATE staff
					SET PROFILE='" . ( $accessfunc === 'grant' ? 'parent' : 'none' ) . "'
					WHERE STAFF_ID IN (" . implode( ',', $ss_parents ) . ")
					AND SYEAR='" . UserSyear() . "'" );
			}

			ProgramConfig( 'remove_access', 'SS_BLOCKED_PARENTS', implode( ',', $ss_blocked ) );
		}

		$note[] = button( 'check' ) . '&nbsp;' . ( $accessfunc === 'grant' ? 'Accès rendu.' : 'Accès bloqué.' );
	}
	else
	{
		$error[] = _( 'You must choose at least one student.' );
	}

	RedirectURL( [ 'modfunc', 'st_arr' ] );
}

if ( ! $_REQUEST['modfunc'] )
{
	if ( $accessfunc === 'grant' )
	{
		DrawHeader( '<span class="module-icon Students"></span> ' . _( 'Grant Access' ) );

		SchoolSafeCard(
			'lock',
			'Access',
			'Accès',
			'Grant access',
			'Rendre l’accès',
			'This page turns access back on for the chosen students, and for their linked parents if that box is checked.',
			'Cette page rend l’accès aux élèves choisis, et à leurs parents liés si la case est cochée.'
		);

		$access_header = '<a href="' . URLEscape( 'Modules.php?modname=' . $_REQUEST['modname'] ) . '"><b>' .
			_( 'Remove Access' ) . '</b></a>';

		$button_label = _( 'Grant Access for Selected Students' );

		$checkbox_parents_label = _( 'Grant Access for Associated Parents' );
	}
	else
	{
		DrawHeader( ProgramTitle() );

		SchoolSafeCard(
			'lock',
			'Access',
			'Accès',
			'Remove access',
			'Bloquer l’accès',
			'This page removes access for the chosen students, and for their linked parents if that box is checked. The student file stays.',
			'Cette page retire l’accès aux élèves choisis, et à leurs parents liés si la case est cochée. La fiche de l’élève reste.'
		);

		$access_header = '<a href="' . URLEscape( 'Modules.php?modname=' . $_REQUEST['modname'] . '&accessfunc=grant' ) . '"><b>' .
			_( 'Grant Access' ) . '</b></a>';

		$button_label = _( 'Remove Access for Selected Students' );

		$checkbox_parents_label = _( 'Remove Access for Associated Parents' );
	}

	DrawHeader( $access_header );

	echo ErrorMessage( $error );

	echo ErrorMessage( $note, 'note' );

	$username_prefix_add = Config( 'REMOVE_ACCESS_USERNAME_PREFIX_ADD' );

	if ( $_REQUEST['search_modfunc'] === 'list' )
	{
		echo '<form action="' . URLEscape( 'Modules.php?modname=' . $_REQUEST['modname'] .
			'&modfunc=save&include_inactive=' . issetVal( $_REQUEST['include_inactive'], '' ) .
			'&_search_all_schools=' . issetVal( $_REQUEST['_search_all_schools'], '' ) .
			'&accessfunc=' . $accessfunc  ) . '" method="POST">';

		$extra['header_right'] = SubmitButton( $button_label );

		$extra['extra_header_left'] = '';

		if ( $accessfunc !== 'grant' )
		{
			$extra['extra_header_left'] .= TextInput(
				$username_prefix_add,
				'username_prefix_add',
				_( 'Add prefix to username' ),
				'required',
				false
			) . '<br />';
		}

		$extra['extra_header_left'] .= CheckboxInput(
			( $accessfunc === 'grant' ? 'Y' : '' ),
			'parents_no_access',
			$checkbox_parents_label,
			'autocomplete="off"',
			true
		);
	}

	// Students having Username, Password set.
	$extra['WHERE'] = " AND s.USERNAME IS NOT NULL
		AND s.PASSWORD IS NOT NULL";

	if ( $accessfunc === 'grant' )
	{
		if ( ! $username_prefix_add )
		{
			$extra['WHERE'] .= " AND s.USERNAME LIKE 'no_username_prefix_set...%'";
		}
		else
		{
			// Student already being blocked.
			$extra['WHERE'] .= " AND s.USERNAME LIKE '" . DBEscapeString( $username_prefix_add ) . "%'";
		}
	}
	elseif ( $username_prefix_add )
	{
		// Student not already being blocked.
		$extra['WHERE'] .= " AND s.USERNAME NOT LIKE '" . DBEscapeString( $username_prefix_add ) . "%'";
	}

	$extra['columns_after'] = [ 'USERNAME' => _( 'Username' ) ];

	$extra['SELECT'] = issetVal( $extra['SELECT'], '' );
	$extra['SELECT'] .= ",s.STUDENT_ID AS CHECKBOX,s.USERNAME";
	$extra['link'] = [ 'FULL_NAME' => false ];
	$extra['functions'] = [ 'CHECKBOX' => 'MakeChooseCheckbox' ];
	$extra['columns_before'] = [ 'CHECKBOX' => MakeChooseCheckbox( 'Y_required', '', 'st_arr' ) ];
	$extra['new'] = true;

	$extra['action'] = '&accessfunc=' . $accessfunc;

	$extra['search_title'] = $accessfunc === 'grant' ?
		_( 'Students without Access' ) :
		_( 'Students with Access' );

	Search( 'student_id', $extra );

	if ( $_REQUEST['search_modfunc'] === 'list' )
	{
		echo '<br /><div class="center">' .

		SubmitButton( $button_label ) . '</div></form>';
	}
}
