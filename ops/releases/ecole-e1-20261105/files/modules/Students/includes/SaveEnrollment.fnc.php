<?php

/*
 * save student enrollment
 * create new enrollment when adding student
 */
function SaveEnrollment()
{
	global $error, $student_id;

	// Add eventual Dates to $_REQUEST['values'].
	AddRequestedDates( 'values' );

	if ( empty( $_REQUEST['values']['student_enrollment'] ) )
	{
		return;
	}

	foreach ( (array) $_REQUEST['values']['student_enrollment'] as $id => $columns )
	{
		if ( $id == 'new' && empty( $columns['START_DATE'] ) )
		{
			if ( isset( $columns['START_DATE'] ) )
			{
				unset( $_REQUEST['values']['student_enrollment'][$id] );
			}

			continue;
		}

		if ( $id == 'new'
			&& $columns['SCHOOL_ID'] )
		{
			// Check if student already enrolled on that date when inserting START_DATE.
			$found = DBGetOne( "SELECT ID
				FROM student_enrollment
				WHERE STUDENT_ID='" . UserStudentID() . "'
				AND SYEAR='" . UserSyear() . "'
				AND ('" . $columns['START_DATE'] . "' BETWEEN START_DATE AND END_DATE
					OR '" . $columns['START_DATE'] . "'>=START_DATE AND END_DATE IS NULL)" );

			if ( $found )
			{
				unset( $_REQUEST['values']['student_enrollment'][$id] );

				$error[] = _( 'The student is already enrolled on that date, and cannot be enrolled a second time on the date you specified. Please fix, and try enrolling the student again.' );
			}
			elseif ( $columns['SCHOOL_ID'] != UserSchool() )
			{
				// @since 5.4 Update current school to enrollment school.
				$_SESSION['UserSchool'] = DBGetOne( "SELECT ID FROM schools
					WHERE SYEAR='" . UserSyear() . "'
					AND ID='" . (int) $columns['SCHOOL_ID'] . "'" );
			}

			continue;
		}

		if ( ! UserStudentID() )
		{
			continue;
		}

		if ( ! empty( $columns['START_DATE'] ) )
		{
			// Check if student already enrolled on that date when updating START_DATE.
			$found = DBGetOne( "SELECT ID
				FROM student_enrollment
				WHERE STUDENT_ID='" . UserStudentID() . "'
				AND SYEAR='" . UserSyear() . "'
				AND ('" . $columns['START_DATE'] . "' BETWEEN START_DATE AND END_DATE
					OR '" . $columns['START_DATE'] . "'>=START_DATE AND END_DATE IS NULL)
				AND ID<>'" . (int) $id . "'" );

			if ( $found )
			{
				unset( $_REQUEST['values']['student_enrollment'][$id] );

				$error[] = _( 'The student is already enrolled on that date, and cannot be enrolled a second time on the date you specified. Please fix, and try enrolling the student again.' );
			}

			continue;
		}

		if ( ! empty( $columns['END_DATE'] ) )
		{
			// SchoolSafe lot E1 (A13, 05/11/2026) : une sortie d'élève exige son motif.
			$ss_drop_code = ! empty( $columns['DROP_CODE'] )
				? $columns['DROP_CODE']
				: DBGetOne( "SELECT DROP_CODE FROM student_enrollment WHERE ID='" . (int) $id . "'" );

			if ( empty( $ss_drop_code ) )
			{
				unset( $_REQUEST['values']['student_enrollment'][$id] );

				$error[] = 'Indiquez le motif de sortie (code de retrait) en même temps que la date de sortie.';

				continue;
			}

			// @since 12.2 SQL Check if student already enrolled on that date when updating END_DATE
			$found = DBGetOne( "SELECT ID
				FROM student_enrollment
				WHERE STUDENT_ID='" . UserStudentID() . "'
				AND SYEAR='" . UserSyear() . "'
				AND ('" . $columns['END_DATE'] . "' BETWEEN START_DATE AND END_DATE
					OR '" . $columns['END_DATE'] . "'>=START_DATE AND END_DATE IS NULL)
				AND ID<>'" . (int) $id . "'" );

			if ( $found )
			{
				unset( $_REQUEST['values']['student_enrollment'][$id] );

				$error[] = _( 'The student is already enrolled on that date, and cannot be enrolled a second time on the date you specified. Please fix, and try enrolling the student again.' );
			}

			continue;
		}

		if ( ! isset( $columns['START_DATE'] ) )
		{
			continue;
		}

		// SchoolSafe lot E1 (A2, 05/11/2026) : effacer la date d'entrée supprimait
		// l'inscription sans confirmation (et pouvait changer l'école de la session).
		// Désormais refusé : une sortie se fait par une date de sortie et son motif.
		unset( $_REQUEST['values']['student_enrollment'][$id] );

		$error[] = 'La date d’entrée ne peut pas être effacée. Pour faire sortir un élève, indiquez sa date de '
			. 'sortie et le motif de sortie.';
	}

	foreach ( $_REQUEST['values']['student_enrollment'] as $id => $columns )
	{
		$columns['SYEAR'] = UserSyear();

		$where_columns = [ 'STUDENT_ID' => ( UserStudentID() ? UserStudentID() : $student_id ) ];

		if ( $id !== 'new' )
		{
			$where_columns['ID'] = $id;
		}

		DBUpsert(
			'student_enrollment',
			$columns,
			$where_columns,
			( $id === 'new' ? 'insert' : 'update' )
		);
	}
}
