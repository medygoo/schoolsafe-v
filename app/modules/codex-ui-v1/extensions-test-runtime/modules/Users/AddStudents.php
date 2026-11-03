<?php

DrawHeader( ProgramTitle() );

SchoolSafeCard(
	'staff',
	'Family',
	'Famille',
	'Associate students with parents',
	'Associer les élèves aux parents',
	'This page links students to a parent user. Choose the parent, then the students. The parent then sees those students.',
	'Cette page relie des élèves à un utilisateur parent. Choisissez le parent, puis les élèves. Le parent voit ensuite ces élèves.'
);

if ( $_REQUEST['modfunc'] === 'save'
	&& AllowEdit()
	&& UserStaffID() )
{
	if ( isset( $_REQUEST['student'] )
		&& is_array( $_REQUEST['student'] ) )
	{
		$current_RET = DBGet( "SELECT STUDENT_ID
			FROM students_join_users
			WHERE STAFF_ID='" . UserStaffID() . "'", [], [ 'STUDENT_ID' ] );

		$student_ids = $_REQUEST['student'];

		if ( User( 'SCHOOLS' ) )
		{
			// @since 12.9 Security fix #376 IDOR: Unauthorized Cross-School Data Assignment
			$students_list = implode( ',', array_map( 'intval', $_REQUEST['student'] ) );

			$students_RET = DBGet( "SELECT DISTINCT STUDENT_ID
				FROM student_enrollment
				WHERE SYEAR='" . UserSyear() . "'
				AND SCHOOL_ID IN(" . mb_substr( str_replace( ',', "','", User( 'SCHOOLS' ) ), 2, -2 ) . ")
				AND STUDENT_ID IN(" . $students_list . ")",
				[], [ 'STUDENT_ID' ] );

			$student_ids = empty( $students_RET ) ? [] : array_keys( $students_RET );
		}

		foreach ( (array) $student_ids as $student_id )
		{
			if ( empty( $current_RET[$student_id] ) )
			{
				DBInsert(
					'students_join_users',
					[ 'STUDENT_ID' => (int) $student_id, 'STAFF_ID' => UserStaffID() ]
				);

				//hook
				do_action( 'Users/AddStudents.php|user_assign_role' );
			}
		}

		$note[] = _( 'The selected user\'s profile now includes access to the selected students.' );
	}
	else
	{
		$error[] = _( 'You must choose at least one student.' );
	}

	// Unset modfunc, student & redirect URL.
	RedirectURL( [ 'modfunc', 'student' ] );
}

if ( $_REQUEST['modfunc'] === 'delete'
	&& AllowEdit()
	&& UserStaffID() )
{
	// SchoolSafe A5 : nommer l'eleve concerne et rappeler l'impact avant confirmation.
	$remove_student_name = '';

	if ( ! empty( $_REQUEST['student_id_remove'] ) )
	{
		$remove_student_name = (string) DBGetOne( "SELECT " . DisplayNameSQL( 's' ) . "
			FROM students s
			WHERE s.STUDENT_ID='" . (int) $_REQUEST['student_id_remove'] . "'" );
	}

	echo ErrorMessage( [ SchoolSafeT(
		'You are about to change the access of: ',
		'Vous vous apprêtez à modifier l’accès de : '
	) . AttrEscape( $remove_student_name ) . SchoolSafeT(
		'. Confirming will decide whether this parent keeps seeing this student and receiving their scan notifications.',
		'. La confirmation décidera si ce parent continue de voir cet élève et de recevoir ses notifications de scan.'
	) ], 'note' );

	if ( DeletePrompt( _( 'student from that user' ), _( 'remove access to' ) )
		&& ! empty( $_REQUEST['student_id_remove'] ) )
	{
		// SchoolSafe A1 : meme garde ecole que le correctif #376 de l'ajout —
		// ne pas retirer un eleve hors du perimetre des ecoles du compte.
		$remove_id = (int) $_REQUEST['student_id_remove'];

		if ( User( 'SCHOOLS' ) )
		{
			$remove_allowed = (bool) DBGetOne( "SELECT 1
				FROM student_enrollment
				WHERE SYEAR='" . UserSyear() . "'
				AND SCHOOL_ID IN(" . mb_substr( str_replace( ',', "','", User( 'SCHOOLS' ) ), 2, -2 ) . ")
				AND STUDENT_ID='" . $remove_id . "'" );
		}
		else
		{
			$remove_allowed = true;
		}

		if ( $remove_allowed )
		{
			DBQuery( "DELETE FROM students_join_users
				WHERE STUDENT_ID='" . $remove_id . "'
				AND STAFF_ID='" . UserStaffID() . "'" );

			// Hook.
			do_action( 'Users/AddStudents.php|user_unassign_role' );
		}

		// Unset modfunc & student ID remove & redirect URL.
		RedirectURL( [ 'modfunc', 'student_id_remove' ] );
	}
}

echo ErrorMessage( $note, 'note' );

echo ErrorMessage( $error );

if ( ! $_REQUEST['modfunc'] )
{
	if ( UserStaffID() )
	{
		$profile = DBGetOne( "SELECT PROFILE
			FROM staff
			WHERE STAFF_ID='" . UserStaffID() . "'" );

		if ( $profile !== 'parent' )
		{
			unset( $_SESSION['staff_id'] );
		}
	}

	if ( ! UserStaffID()
		|| ! empty( $_REQUEST['profile'] ) ) // Fix reset UserStaffID() when pressing back button.
	{
		// FJ add # Associated students.
		$extra['SELECT'] = ",(SELECT count(u.STUDENT_ID)
		FROM students_join_users u,student_enrollment ssm
		WHERE u.STAFF_ID=s.STAFF_ID
		AND ssm.STUDENT_ID=u.STUDENT_ID
		AND ssm.SYEAR='" . UserSyear() . "'
		AND ('" . DBDate() . "' BETWEEN ssm.START_DATE AND ssm.END_DATE OR ssm.END_DATE IS NULL)) AS ASSOCIATED";

		$extra['columns_after'] = [ 'ASSOCIATED' => '# ' . _( 'Associated' ) ];

		$extra['profile'] = 'parent';

		$extra['new'] = true;

		Search( 'staff_id', $extra );
	}

	if ( UserStaffID() )
	{
		// SchoolSafe A2 : rappel visuel du parent selectionne — evite de rattacher au mauvais.
		$selected_parent = DBGet( "SELECT FIRST_NAME,LAST_NAME,USERNAME
			FROM staff
			WHERE STAFF_ID='" . UserStaffID() . "'" );

		if ( $selected_parent )
		{
			$selected_parent = $selected_parent[1];

			$parent_role = ! empty( $selected_parent['USERNAME'] ) ?
				sprintf( SchoolSafeT( 'Main parent (account: %s)', 'Parent principal (compte : %s)' ), $selected_parent['USERNAME'] ) :
				SchoolSafeT( 'Guardian (no application account)', 'Gardien (sans compte d’application)' );

			echo '<div style="margin:10px 0;padding:10px 14px;border-left:4px solid #176b52;background:#f4faf7;border-radius:4px;">
				<strong>' . AttrEscape( trim( (string) $selected_parent['FIRST_NAME'] . ' ' . $selected_parent['LAST_NAME'] ) ) . '</strong>
				<span style="color:#5b6b64;"> — ' . AttrEscape( $parent_role ) . '</span>
			</div>';
		}

		if ( $_REQUEST['search_modfunc'] === 'list' )
		{
			echo '<form action="' . URLEscape( 'Modules.php?modname=' . $_REQUEST['modname'] . '&modfunc=save' ) . '" method="POST">';
			DrawHeader( '', SubmitButton( _( 'Add Selected Students' ) ) );
		}

		echo '<table class="center"><tr><td>';

		// SQL fix only display enrolled students.
		$current_RET = DBGet( "SELECT u.STUDENT_ID," . DisplayNameSQL( 's' ) . " AS FULL_NAME,ssm.SCHOOL_ID
			FROM students_join_users u,students s,student_enrollment ssm
			WHERE s.STUDENT_ID=u.STUDENT_ID
			AND u.STAFF_ID='" . UserStaffID() . "'
			AND ssm.STUDENT_ID=u.STUDENT_ID
			AND ssm.SYEAR='" . UserSyear() . "'
			AND ('" . DBDate() . "' BETWEEN ssm.START_DATE AND ssm.END_DATE OR ssm.END_DATE IS NULL)", [ 'FULL_NAME' => '_makeStudentInfoLink' ] );

		$link['remove'] = [
			'link' => 'Modules.php?modname=' . $_REQUEST['modname'] . '&modfunc=delete',
			'variables' => [ 'student_id_remove' => 'STUDENT_ID' ],
		];

		ListOutput(
			$current_RET,
			[ 'FULL_NAME' => _( 'Students' ) ],
			'Student',
			'Students',
			$link,
			[],
			[ 'search' => false ]
		);

		echo '</td></tr><tr><td>';

		$current_student_ids = [];

		foreach ( $current_RET as $current_student )
		{
			$current_student_ids[] = $current_student['STUDENT_ID'];
		}

		if ( $current_student_ids )
		{
			// @since 10.9 Exclude already associated students from Search()
			$extra['WHERE'] = " AND s.STUDENT_ID NOT IN(" . implode( ',', $current_student_ids ) . ")";
		}

		$extra['link'] = [ 'FULL_NAME' => false ];
		$extra['SELECT'] = ",NULL AS CHECKBOX";
		$extra['functions'] = [ 'CHECKBOX' => 'MakeChooseCheckbox' ];
		$extra['columns_before'] = [ 'CHECKBOX' => MakeChooseCheckbox( 'required', 'STUDENT_ID', 'student' ) ];
		$extra['new'] = true;
		$extra['options']['search'] = false;

		if ( AllowEdit() )
		{
			Search( 'student_id', $extra );
		}

		echo '</td></tr></table>';

		if ( $_REQUEST['search_modfunc'] === 'list' )
		{
			echo '<br /><div class="center">' . SubmitButton( _( 'Add Selected Students' ) ) . '</div></form>';
		}
	}
}

/**
 * Make Student Info link
 *
 * @since 7.2
 *
 * Local function
 * DBGet() callback
 *
 * @param  string $value  Student Full Name.
 * @param  string $column Column.
 *
 * @return string         Link to Student Info program.
 */
function _makeStudentInfoLink( $value, $column = 'FULL_NAME' )
{
	global $THIS_RET;

	$modname = 'Students/Student.php';

	if ( ! AllowUse( $modname )
		|| ! $THIS_RET['STUDENT_ID'] )
	{
		return $value;
	}

	$link = 'Modules.php?modname=' . $modname . '&student_id=' . $THIS_RET['STUDENT_ID'];

	if ( $THIS_RET['SCHOOL_ID'] !== UserSchool() )
	{
		$link .= '&school_id=' . $THIS_RET['SCHOOL_ID'];
	}

	return '<a href="' . URLEscape( $link ) . '">' . $value . '</a>';
}
