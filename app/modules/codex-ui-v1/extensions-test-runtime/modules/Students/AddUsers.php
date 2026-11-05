<?php

DrawHeader( ProgramTitle() );

if ( $_REQUEST['modfunc'] === 'save'
	&& AllowEdit()
	&& UserStudentID() )
{
	if ( isset( $_REQUEST['staff'] )
		&& is_array( $_REQUEST['staff'] ) )
	{
		$current_RET = DBGet( "SELECT STAFF_ID
			FROM students_join_users
			WHERE STUDENT_ID='" . UserStudentID() . "'", [], [ 'STAFF_ID' ] );

		// SchoolSafe - regle metier : 6 parents maximum par eleve (GO 2026-10-28).
		$ss_max_parents = 6;
		$ss_lies = count( (array) $current_RET );
		$ss_ajoutes = 0;
		$ss_refuses = 0;

		$ss_non_parents = 0;

		foreach ( (array) $_REQUEST['staff'] as $staff_id )
		{
			if ( empty( $current_RET[$staff_id] ) )
			{
				// SchoolSafe lot U1 (A2) : seul un compte parent est relié à un élève.
				if ( DBGetOne( "SELECT PROFILE FROM staff
					WHERE STAFF_ID='" . (int) $staff_id . "'" ) !== 'parent' )
				{
					$ss_non_parents++;
					continue;
				}

				if ( $ss_lies + $ss_ajoutes >= $ss_max_parents )
				{
					$ss_refuses++;
					continue;
				}

				DBInsert(
					'students_join_users',
					[ 'STAFF_ID' => (int) $staff_id, 'STUDENT_ID' => UserStudentID() ]
				);

				$ss_ajoutes++;

				//hook
				do_action( 'Students/AddUsers.php|user_assign_role' );
			}
		}

		if ( $ss_refuses )
		{
			$error[] = sprintf(
				'Limite de %d parents par élève atteinte : %d parent(s) non associé(s). Retirez d’abord un parent existant.',
				$ss_max_parents,
				$ss_refuses
			);
		}

		if ( $ss_non_parents )
		{
			$error[] = sprintf(
				'%d compte(s) non relié(s) : seul un compte parent peut être relié à un élève.',
				$ss_non_parents
			);
		}

		if ( $ss_ajoutes
			|| ! ( $ss_refuses || $ss_non_parents ) )
		{
			$note[] = _( 'The selected user\'s profile now includes access to the selected students.' );
		}
	}
	else
	{
		$error[] = _( 'You must choose at least one user' );
	}

	// Unset modfunc, staff & redirect URL.
	RedirectURL( [ 'modfunc', 'staff' ] );
}

if ( $_REQUEST['modfunc'] === 'delete'
	&& AllowEdit()
	&& UserStudentID() )
{
	// SchoolSafe lot U1 (A3, 05/11/2026) : nommer le parent concerné avant
	// confirmation, comme côté parent (Users/AddStudents.php).
	if ( ! empty( $_REQUEST['staff_id_remove'] ) )
	{
		$ss_remove_parent = (string) DBGetOne( "SELECT " . DisplayNameSQL( 's' ) . "
			FROM staff s
			WHERE s.STAFF_ID='" . (int) $_REQUEST['staff_id_remove'] . "'" );

		echo ErrorMessage( [ 'Vous vous apprêtez à retirer à <b>' . AttrEscape( $ss_remove_parent )
			. '</b> l’accès à cet élève : ce parent ne le verra plus et ne recevra plus ses notifications.' ], 'note' );
	}

	if ( DeletePrompt( _( 'student from that user' ), _( 'remove access to' ) )
		&& ! empty( $_REQUEST['staff_id_remove'] ) )
	{
		DBQuery( "DELETE FROM students_join_users
			WHERE STAFF_ID='" . (int) $_REQUEST['staff_id_remove'] . "'
			AND STUDENT_ID='" . UserStudentID() . "'" );

		// Hook.
		do_action( 'Students/AddUsers.php|user_unassign_role' );

		// Unset modfunc & staff ID remove & redirect URL.
		RedirectURL( [ 'modfunc', 'staff_id_remove' ] );
	}
}

echo ErrorMessage( $note, 'note' );

echo ErrorMessage( $error );

if ( ! $_REQUEST['modfunc'] )
{
	SchoolSafeCard(
		'staff',
		'Family',
		'Famille',
		'Associate parents with students',
		'Associer les parents aux élèves',
		'This page links a parent user to a student. Choose the student, then the parent. The parent then sees that student.',
		'Cette page relie un compte parent à un élève : le parent voit ensuite cet élève dans son application. Maximum 6 parents par élève. La tutelle principale — celle qui reçoit les alertes critiques et valide les changements — se désigne dans SchoolSafe → Familles → « Enregistrer une personne familiale », et se change via « Retirer ou remplacer ».'
	);

	$extra['SELECT'] = ",(SELECT count(u.STAFF_ID) FROM students_join_users u,staff st WHERE u.STUDENT_ID=s.STUDENT_ID AND st.STAFF_ID=u.STAFF_ID AND st.SYEAR=ssm.SYEAR) AS ASSOCIATED";
	$extra['columns_after'] = [ 'ASSOCIATED' => '# ' . _( 'Associated' ) ];

	if ( ! UserStudentID() )
	{
		Search( 'student_id', $extra );
	}

	if ( UserStudentID() )
	{
		if ( $_REQUEST['search_modfunc'] === 'list' )
		{
			echo '<form action="' . URLEscape( 'Modules.php?modname=' . $_REQUEST['modname'] . '&modfunc=save' ) . '" method="POST">';

			DrawHeader( '', SubmitButton( _( 'Add Selected Parents' ) ) );
		}

		echo '<table class="center"><tr><td>';

		// SchoolSafe - montrer qui est la tutelle principale (GO 2026-10-28).
		$ss_primary = (int) DBGetOne( "SELECT STAFF_ID
			FROM schoolsafe_pickup_guardians
			WHERE STUDENT_ID='" . UserStudentID() . "'
			AND IS_PRIMARY AND IS_ACTIVE
			AND STAFF_ID IS NOT NULL
			ORDER BY ID LIMIT 1" );

		$current_RET = DBGet( "SELECT u.STAFF_ID,
			" . DisplayNameSQL( 's' ) . " AS FULL_NAME,s.LAST_LOGIN,
			" . ( $ss_primary ?
				"CASE WHEN s.STAFF_ID='" . $ss_primary . "' THEN '" . _( 'Tutelle principale' ) . "' ELSE '' END" :
				"NULL" ) . " AS TUTELLE
			FROM students_join_users u,staff s
			WHERE s.STAFF_ID=u.STAFF_ID
			AND u.STUDENT_ID='" . UserStudentID() . "'
			AND s.SYEAR='" . UserSyear() . "'", [ 'LAST_LOGIN' => 'makeLogin' ] );

		$link['remove'] = [
			'link' => 'Modules.php?modname=' . $_REQUEST['modname'] . '&modfunc=delete',
			'variables' => [ 'staff_id_remove' => 'STAFF_ID' ],
		];

		$link['FULL_NAME'] = [
			'link' => 'Modules.php?modname=Users/User.php',
			'variables' => [ 'staff_id' => 'STAFF_ID' ],
		];

		ListOutput(
			$current_RET,
			[ 'FULL_NAME' => _( 'Parents' ), 'TUTELLE' => _( 'Role famille' ), 'LAST_LOGIN' => _( 'Last Login' ) ],
			'Associated Parent',
			'Associated Parents',
			$link,
			[],
			[ 'search' => false ]
		);

		echo '</td></tr><tr><td>';

		if ( AllowEdit() )
		{
			unset( $extra );

			$current_parent_ids = [];

			foreach ( $current_RET as $current_parent )
			{
				$current_parent_ids[] = $current_parent['STAFF_ID'];
			}

			if ( $current_parent_ids )
			{
				// @since 10.9 Exclude already associated parents from Search()
				$extra['WHERE'] = " AND s.STAFF_ID NOT IN(" . implode( ',', $current_parent_ids ) . ")";
			}

			$extra['link'] = [ 'FULL_NAME' => false ];
			$extra['SELECT'] = ",NULL AS CHECKBOX";
			$extra['functions'] = [ 'CHECKBOX' => 'MakeChooseCheckbox' ];
			$extra['columns_before'] = [ 'CHECKBOX' => MakeChooseCheckbox( 'required', 'STAFF_ID', 'staff' ) ];
			$extra['new'] = true;
			$extra['options']['search'] = false;
			$extra['profile'] = 'parent';

			Search( 'staff_id', $extra );
		}

		echo '</td></tr></table>';

		if ( $_REQUEST['search_modfunc'] === 'list' )
		{
			echo '<br /><div class="center">' . SubmitButton( _( 'Add Selected Parents' ) ) . '</div></form>';
		}
	}
}
