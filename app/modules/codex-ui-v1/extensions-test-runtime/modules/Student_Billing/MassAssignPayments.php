<?php
// Lot F1 (05/11) : montant positif, école en cours, pas de doublon.
require_once 'ProgramFunctions/SchoolSafeMoney.fnc.php';

if ( $_REQUEST['modfunc'] === 'save' )
{
	if ( ! empty( $_REQUEST['student'] ) && AllowEdit() )
	{
		$date = RequestedDate( 'date', '' );

		// FJ fix SQL bug invalid amount.

		if ( $problem = SchoolSafeMoneyAmountProblem( $_REQUEST['amount'] ) )
		{
			$error[] = $problem;
		}
		else
		{
			$_REQUEST['amount'] = SchoolSafeMoneyAmount( $_REQUEST['amount'] );

			if ( $date )
			{
				$student_ids = $_REQUEST['student'];

				// F9 (lot F1, 05/11) : seuls les élèves inscrits dans l'école en cours, quel que soit le compte.
				$students_list = implode( ',', array_map( 'intval', (array) $_REQUEST['student'] ) );

				$students_RET = $students_list === '' ? [] : DBGet( "SELECT DISTINCT STUDENT_ID
					FROM student_enrollment
					WHERE SYEAR='" . UserSyear() . "'
					AND SCHOOL_ID='" . UserSchool() . "'
					AND STUDENT_ID IN(" . $students_list . ")",
					[], [ 'STUDENT_ID' ] );

				$student_ids = empty( $students_RET ) ? [] : array_keys( $students_RET );

				$skipped = 0;

				foreach ( (array) $student_ids as $student_id )
				{
					// F11 : le même paiement vient d'être enregistré pour cet élève (double clic).
					if ( SchoolSafeMoneyRecentDuplicate( 'billing_payments', [
						'STUDENT_ID' => (int) $student_id,
						'SCHOOL_ID' => (int) UserSchool(),
						'SYEAR' => (int) UserSyear(),
						'AMOUNT' => $_REQUEST['amount'],
						'PAYMENT_DATE' => $date,
					] ) )
					{
						$skipped++;
						continue;
					}

					$inserted = DBInsert(
						'billing_payments',
						[
							'SYEAR' => UserSyear(),
							'SCHOOL_ID' => UserSchool(),
							'STUDENT_ID' => (int) $student_id,
							'PAYMENT_DATE' => $date,
							'AMOUNT' => $_REQUEST['amount'],
							'COMMENTS' => $_REQUEST['comments'],
							// @since 11.2 Add CREATED_BY column to billing_fees & billing_payments tables
							'CREATED_BY' => DBEscapeString( User( 'NAME' ) ),
						]
					);
				}

				if ( ! empty( $inserted ) )
				{
					$note[] = button( 'check' ) . '&nbsp;' . _( 'That payment has been added to the selected students.' );
				}

				if ( $skipped )
				{
					$error[] = $skipped . ' élève(s) avai(en)t déjà ce paiement à l’instant : il n’a pas été enregistré une seconde fois.';
				}

				if ( empty( $student_ids ) )
				{
					$error[] = 'Aucun des élèves choisis n’est inscrit dans l’école en cours.';
				}
			}
			else
			{
				$error[] = _( 'The date you entered is not valid' );
			}
		}
	}
	else
	{
		$error[] = _( 'You must choose at least one student.' );
	}

	// Unset modfunc, student & redirect URL.
	RedirectURL( [ 'modfunc', 'student' ] );
}

if ( ! $_REQUEST['modfunc'] )
{
	DrawHeader( ProgramTitle() );

SchoolSafeCard(
	'card',
	'Billing',
	'Facturation',
	'Mass assign payments',
	'Assigner paiements en masse',
	'This page records the same payment for several students.',
	'Cette page enregistre le même paiement pour plusieurs élèves.'
);


	echo ErrorMessage( $error );

	echo ErrorMessage( $note, 'note' );

	if ( $_REQUEST['search_modfunc'] === 'list' )
	{
		echo '<form action="' . URLEscape( 'Modules.php?modname=' . $_REQUEST['modname'] . '&modfunc=save' ) . '" method="POST">';

		DrawHeader( '', SubmitButton( _( 'Add Payment to Selected Students' ) ) );

		echo '<br />';

		PopTable( 'header', _( 'Payment' ) );

		echo '<table><tr><td>' . TextInput(
			'',
			'amount',
			_( 'Amount' ),
			'  type="number" step="0.01" max="999999999999" min="-999999999999" required'
		) . '</td></tr>';

		echo '<tr><td>' . DateInput(
			DBDate(),
			'date',
			_( 'Date' ),
			false,
			false
		) . '</td></tr>';

		echo '<tr><td>' . TextInput(
			'',
			'comments',
			_( 'Comment' ),
			'maxlength="1000" size="25"'
		) . '</td></tr></table>';

		PopTable( 'footer' );

		echo '<br />';
	}

	$extra['link'] = [ 'FULL_NAME' => false ];
	$extra['SELECT'] = ",NULL AS CHECKBOX";
	$extra['functions'] = [ 'CHECKBOX' => 'MakeChooseCheckbox' ];
	$extra['columns_before'] = [ 'CHECKBOX' => MakeChooseCheckbox( 'required', 'STUDENT_ID', 'student' ) ];
	$extra['new'] = true;

	Search( 'student_id', $extra );

	if ( $_REQUEST['search_modfunc'] === 'list' )
	{
		echo '<br /><div class="center">' . SubmitButton( _( 'Add Payment to Selected Students' ) ) . '</div>';
		echo '</form>';
	}
}
