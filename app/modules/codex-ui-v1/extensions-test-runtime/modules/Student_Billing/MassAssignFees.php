<?php
// Lot F1 (05/11) : montant positif, titre obligatoire, école en cours, pas de doublon.
require_once 'ProgramFunctions/SchoolSafeMoney.fnc.php';

if ( $_REQUEST['modfunc'] === 'save' )
{
	if ( ! empty( $_REQUEST['student'] )
		&& AllowEdit() )
	{
		//FJ fix SQL bug invalid amount

		if ( trim( (string) issetVal( $_REQUEST['title'], '' ) ) === '' )
		{
			$error[] = 'Indiquez le titre du frais (par exemple « Scolarité – tranche 1 »).';
		}
		elseif ( $problem = SchoolSafeMoneyAmountProblem( $_REQUEST['amount'] ) )
		{
			$error[] = $problem;
		}
		else
		{
			$_REQUEST['amount'] = SchoolSafeMoneyAmount( $_REQUEST['amount'] );

			// Lot F4a : monnaie du frais (USD par défaut).
			$ss_currency = SchoolSafeMoneyCurrencyReady() ? [ 'CURRENCY' => SchoolSafeMoneyCurrency( $_POST['currency'] ?? 'USD' ) ] : [];
			$due_date = RequestedDate( 'due', '' );

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
				// F11 : le même frais vient d'être ajouté à cet élève (double clic).
				if ( SchoolSafeMoneyRecentDuplicate( 'billing_fees', [
					'STUDENT_ID' => (int) $student_id,
					'SCHOOL_ID' => (int) UserSchool(),
					'SYEAR' => (int) UserSyear(),
					'TITLE' => $_REQUEST['title'],
					'AMOUNT' => $_REQUEST['amount'],
				] + $ss_currency ) )
				{
					$skipped++;
					continue;
				}

				$inserted = DBInsert(
					'billing_fees',
					[
						'SYEAR' => UserSyear(),
						'SCHOOL_ID' => UserSchool(),
						'STUDENT_ID' => (int) $student_id,
						'TITLE' => $_REQUEST['title'],
						'AMOUNT' => $_REQUEST['amount'],
						'ASSIGNED_DATE' => DBDate(),
						'DUE_DATE' => $due_date,
						'COMMENTS' => $_REQUEST['comments'],
						// @since 11.2 Add CREATED_BY column to billing_fees & billing_payments tables
						'CREATED_BY' => DBEscapeString( User( 'NAME' ) ),
					] + $ss_currency
				);
			}

			if ( ! empty( $inserted ) )
			{
				$note[] = button( 'check' ) . '&nbsp;' . _( 'That fee has been added to the selected students.' );
			}

			if ( $skipped )
			{
				$error[] = $skipped . ' élève(s) avai(en)t déjà reçu ce frais à l’instant : il n’a pas été ajouté une seconde fois.';
			}

			if ( empty( $student_ids ) )
			{
				$error[] = 'Aucun des élèves choisis n’est inscrit dans l’école en cours.';
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
	'Mass assign fees',
	'Assigner les frais de scolarité en masse',
	'This page assigns the same fee to several students.',
	'Cette page assigne les mêmes frais à plusieurs élèves.'
);


	echo ErrorMessage( $error );

	echo ErrorMessage( $note, 'note' );

	if ( $_REQUEST['search_modfunc'] === 'list' )
	{
		echo '<form action="' . URLEscape( 'Modules.php?modname=' . $_REQUEST['modname'] . '&modfunc=save' ) . '" method="POST">';
		DrawHeader( '', SubmitButton( _( 'Add Fee to Selected Students' ) ) );

		echo '<br />';

		PopTable( 'header', _( 'Fee' ) );

		echo '<table><tr><td>' . TextInput(
			'',
			'title',
			_( 'Title' ),
			'required size="20"'
		) . '</td></tr>';

		echo '<tr><td>' . TextInput(
			'',
			'amount',
			_( 'Amount' ),
			' type="number" step="0.01" max="999999999999" min="0.01" required'
		) . '</td></tr>';

		if ( SchoolSafeMoneyCurrencyReady() )
		{
			echo '<tr><td>' . SchoolSafeMoneyCurrencySelect( 'currency', 'USD', 'Monnaie du frais' ) . '</td></tr>';
		}

		echo '<tr><td>' . DateInput( '', 'due', _( 'Due Date' ), false ) . '</td></tr>';

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
		echo '<br /><div class="center">' . SubmitButton( _( 'Add Fee to Selected Students' ) ) . '</div>';
		echo '</form>';
	}
}
