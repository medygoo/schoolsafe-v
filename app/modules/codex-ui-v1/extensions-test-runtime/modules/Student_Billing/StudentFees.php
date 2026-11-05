<?php

require_once 'ProgramFunctions/FileUpload.fnc.php';
require_once 'modules/Student_Billing/functions.inc.php';
// Lot F1 (05/11) : colonnes autorisées, montants, doublons, école en cours.
require_once 'ProgramFunctions/SchoolSafeMoney.fnc.php';

if ( empty( $_REQUEST['print_statements'] ) )
{
	DrawHeader( ProgramTitle() );

SchoolSafeCard(
	'card',
	'Billing',
	'Facturation',
	'Fees',
	'Frais de scolarité',
	'This page records the fees of one student.',
	'Cette page enregistre les frais d’un élève.'
);


	Search( 'student_id', issetVal( $extra ) );
}

if ( $_REQUEST['modfunc'] === 'save'
	&& AllowEdit()
	&& UserStudentID() )
{
	// Add eventual Dates to $_REQUEST['values'].
	AddRequestedDates( 'values', 'post' );

	foreach ( (array) $_REQUEST['values'] as $id => $columns )
	{
		// F3 : seules les colonnes du formulaire sont écrites.
		$columns = SchoolSafeMoneyColumns( $columns, [ 'TITLE', 'AMOUNT', 'DUE_DATE', 'COMMENTS' ] );

		if ( $id !== 'new' )
		{
			// Le montant d'un frais existant ne se modifie pas ici (exemption ou nouveau frais).
			unset( $columns['AMOUNT'] );

			$columns['FILE_ATTACHED'] = _saveFeesFile( $id );

			if ( ! $columns['FILE_ATTACHED'] )
			{
				unset( $columns['FILE_ATTACHED'] );

				if ( empty( $columns ) )
				{
					// No file, and FILE_ATTACHED was the only column, skip.
					continue;
				}
			}

			DBUpdate(
				'billing_fees',
				$columns,
				[
					'ID' => (int) $id,
					'STUDENT_ID' => UserStudentID(),
					'SCHOOL_ID' => UserSchool(),
					'SYEAR' => UserSyear(),
				]
			);
		}

		// F4 : un nouveau frais exige un titre et un montant positif.
		elseif ( trim( (string) ( $columns['AMOUNT'] ?? '' ) ) === ''
			&& trim( (string) ( $columns['TITLE'] ?? '' ) ) === '' )
		{
			// Ligne vide : rien à ajouter.
		}
		elseif ( trim( (string) ( $columns['TITLE'] ?? '' ) ) === '' )
		{
			$error[] = 'Frais refusé : indiquez son titre (par exemple « Scolarité – tranche 1 »).';
		}
		elseif ( $problem = SchoolSafeMoneyAmountProblem( $columns['AMOUNT'] ?? '' ) )
		{
			$error[] = $problem;
		}
		elseif ( SchoolSafeMoneyRecentDuplicate( 'billing_fees', [
			'STUDENT_ID' => (int) UserStudentID(),
			'SCHOOL_ID' => (int) UserSchool(),
			'SYEAR' => (int) UserSyear(),
			'TITLE' => $columns['TITLE'],
			'AMOUNT' => SchoolSafeMoneyAmount( $columns['AMOUNT'] ),
		] ) )
		{
			// F11 : double clic ou formulaire renvoyé.
			$error[] = 'Ce frais vient déjà d’être ajouté. Il n’a pas été ajouté une seconde fois.';
		}
		else
		{
			$columns['AMOUNT'] = SchoolSafeMoneyAmount( $columns['AMOUNT'] );

			$insert_columns = [
				'STUDENT_ID' => UserStudentID(),
				'SCHOOL_ID' => UserSchool(),
				'SYEAR' => UserSyear(),
				'ASSIGNED_DATE' => DBDate(),
			];

			$columns['FILE_ATTACHED'] = _saveFeesFile( $id );

			// @since 11.2 Add CREATED_BY column to billing_fees & billing_payments tables
			$columns['CREATED_BY'] = DBEscapeString( User( 'NAME' ) );

			DBInsert(
				'billing_fees',
				$insert_columns + $columns
			);
		}
	}

	// Unset modfunc, values, dates & redirect URL.
	RedirectURL( [ 'modfunc', 'values', 'month_values', 'day_values', 'year_values' ] );
}

// Lot F2 (F6, 05/11) : « Annuler » remplace « Supprimer ». Le frais n'est plus effacé : une ligne
// d'annulation (montant opposé, motif, auteur, date) s'ajoute dessous ; le solde en tient compte.
if ( $_REQUEST['modfunc'] === 'remove'
	&& AllowEdit() )
{
	$fee_RET = DBGet( "SELECT f.TITLE,f.AMOUNT
		FROM billing_fees f
		WHERE f.ID='" . (int) $_REQUEST['id'] . "'
		AND f.STUDENT_ID='" . UserStudentID() . "'
		AND f.SCHOOL_ID='" . UserSchool() . "'
		AND f.SYEAR='" . UserSyear() . "'
		AND f.WAIVED_FEE_ID IS NULL
		AND f.AMOUNT>0
		AND NOT EXISTS(SELECT 1 FROM billing_fees w WHERE w.WAIVED_FEE_ID=f.ID)" );

	if ( empty( $fee_RET[1] ) )
	{
		$error[] = 'Annulation impossible : ce frais n’appartient pas à cet élève, ou il est déjà annulé ou exempté.';

		RedirectURL( [ 'modfunc', 'id' ] );
	}
	elseif ( $reason = SchoolSafeMoneyReasonPrompt( 'ce frais' ) )
	{
		DBInsert(
			'billing_fees',
			[
				'SYEAR' => UserSyear(),
				'SCHOOL_ID' => UserSchool(),
				'STUDENT_ID' => UserStudentID(),
				'TITLE' => DBEscapeString( $fee_RET[1]['TITLE'] . ' (annulé)' ),
				'AMOUNT' => ( $fee_RET[1]['AMOUNT'] * -1 ),
				'WAIVED_FEE_ID' => (int) $_REQUEST['id'],
				'ASSIGNED_DATE' => DBDate(),
				'COMMENTS' => DBEscapeString( 'Annulation : ' . $reason ),
				'CREATED_BY' => DBEscapeString( User( 'NAME' ) ),
			]
		);

		// Unset modfunc & ID & redirect URL.
		RedirectURL( [ 'modfunc', 'id' ] );
	}
}

if ( $_REQUEST['modfunc'] === 'waive'
	&& AllowEdit() )
{
	if ( DeletePrompt( _( 'Fee' ), _( 'Waive' ) ) )
	{
		// F2 : le frais doit être celui de cet élève, dans l'école et l'année en cours,
		// ne pas être lui-même une exemption, et ne pas être déjà exempté.
		$fee_RET = DBGet( "SELECT f.TITLE,f.AMOUNT
			FROM billing_fees f
			WHERE f.ID='" . (int) $_REQUEST['id'] . "'
			AND f.STUDENT_ID='" . UserStudentID() . "'
			AND f.SCHOOL_ID='" . UserSchool() . "'
			AND f.SYEAR='" . UserSyear() . "'
			AND f.WAIVED_FEE_ID IS NULL
			AND f.AMOUNT>0
			AND NOT EXISTS(SELECT 1 FROM billing_fees w WHERE w.WAIVED_FEE_ID=f.ID)" );

		if ( empty( $fee_RET[1] ) )
		{
			$error[] = 'Exemption impossible : ce frais n’appartient pas à cet élève, ou il est déjà exempté.';
			RedirectURL( [ 'modfunc', 'id' ] );
		}
		else
		DBInsert(
			'billing_fees',
			[
				'SYEAR' => UserSyear(),
				'SCHOOL_ID' => UserSchool(),
				'TITLE' => DBEscapeString( $fee_RET[1]['TITLE'] . " " . _( 'Waiver' ) ),
				'AMOUNT' => ( $fee_RET[1]['AMOUNT'] * -1 ),
				'WAIVED_FEE_ID' => (int) $_REQUEST['id'],
				'STUDENT_ID' => UserStudentID(),
				'ASSIGNED_DATE' => DBDate(),
				'COMMENTS' => DBEscapeString( _( 'Waiver' ) ),
				// @since 11.2 Add CREATED_BY column to billing_fees & billing_payments tables
				'CREATED_BY' => DBEscapeString( User( 'NAME' ) ),
			]
		);

		// Unset modfunc & ID & redirect URL.
		RedirectURL( [ 'modfunc', 'id' ] );
	}
}

echo ErrorMessage( $error );

if ( UserStudentID()
	&& ! $_REQUEST['modfunc'] )
{
	$fees_total = 0;

	$functions = [
		'REMOVE' => '_makeFeesRemove',
		'ASSIGNED_DATE' => 'ProperDate',
		'DUE_DATE' => '_makeFeesDateInput',
		'COMMENTS' => '_makeFeesTextInput',
		'AMOUNT' => '_makeFeesAmount',
		'FILE_ATTACHED' => '_makeFeesFileInput',
		'CREATED_AT' => 'ProperDateTime',
	];

	$waived_fees_RET = DBGet( "SELECT '' AS REMOVE,f.ID,f.TITLE,f.ASSIGNED_DATE,
		f.DUE_DATE,f.COMMENTS,f.AMOUNT,f.WAIVED_FEE_ID,f.FILE_ATTACHED,
		CREATED_BY,CREATED_AT
		FROM billing_fees f
		WHERE f.STUDENT_ID='" . UserStudentID() . "'
		AND f.SCHOOL_ID='" . UserSchool() . "' AND f.SYEAR='" . UserSyear() . "'
		AND f.WAIVED_FEE_ID IS NOT NULL", $functions, [ 'WAIVED_FEE_ID' ] );

	$fees_RET = DBGet( "SELECT '' AS REMOVE,f.ID,f.TITLE,f.ASSIGNED_DATE,
		f.DUE_DATE,f.COMMENTS,f.AMOUNT,f.WAIVED_FEE_ID,f.FILE_ATTACHED,
		CREATED_BY,CREATED_AT
		FROM billing_fees f
		WHERE f.STUDENT_ID='" . UserStudentID() . "'
		AND f.SCHOOL_ID='" . UserSchool() . "' AND f.SYEAR='" . UserSyear() . "'
		AND f.WAIVED_FEE_ID IS NULL
		ORDER BY f.ASSIGNED_DATE", $functions );

	$i = 1;
	$RET = [];

	foreach ( (array) $fees_RET as $fee )
	{
		$RET[$i] = $fee;

		if ( ! empty( $waived_fees_RET[$fee['ID']] ) )
		{
			$i++;
			$RET[$i] = ( $waived_fees_RET[$fee['ID']][1] + [ 'row_color' => '00FF66' ] );
		}

		$i++;
	}

	$columns = [];

	if ( ! empty( $RET )
		&& empty( $_REQUEST['print_statements'] )
		&& AllowEdit()
		&& ! isset( $_REQUEST['_ROSARIO_PDF'] ) )
	{
		$columns = [ 'REMOVE' => '<span class="a11y-hidden">' . _( 'Delete' ) . '</span>' ];
	}

	$columns += [
		'TITLE' => _( 'Fee' ),
		'AMOUNT' => _( 'Amount' ),
		'ASSIGNED_DATE' => _( 'Assigned' ),
		'DUE_DATE' => _( 'Due' ),
		'COMMENTS' => _( 'Comment' ),
	];

	if ( empty( $_REQUEST['print_statements'] ) )
	{
		$columns += [ 'FILE_ATTACHED' => _( 'File Attached' ) ];
	}

	if ( isset( $_REQUEST['expanded_view'] )
		&& $_REQUEST['expanded_view'] === 'true' )
	{
		// @since 11.2 Expanded View: Add Created by & Created at columns.
		$columns += [
			'CREATED_BY' => _( 'Created by' ),
			'CREATED_AT' => _( 'Created at' ),
		];
	}

	$link = [];

	if ( empty( $_REQUEST['print_statements'] ) )
	{
		$link['add']['html'] = [
			'REMOVE' => button( 'add' ),
			'TITLE' => _makeFeesTextInput( '', 'TITLE' ),
			'AMOUNT' => _makeFeesTextInput( '', 'AMOUNT' ),
			'ASSIGNED_DATE' => ProperDate( DBDate() ),
			'DUE_DATE' => _makeFeesDateInput( '', 'DUE_DATE' ),
			'COMMENTS' => _makeFeesTextInput( '', 'COMMENTS' ),
			'FILE_ATTACHED' => _makeFeesFileInput( '', 'FILE_ATTACHED' ),
		];
	}

	$options = [];

	if ( empty( $_REQUEST['print_statements'] ) )
	{
		echo '<form action="' . URLEscape(
			'Modules.php?modname=' . $_REQUEST['modname'] . '&modfunc=save&student_id=' . UserStudentID()
		) . '" method="POST">';
		//DrawStudentHeader();

		if ( AllowEdit() )
		{
			if ( ! isset( $_REQUEST['expanded_view'] )
				|| $_REQUEST['expanded_view'] !== 'true' )
			{
				$expanded_view_header = '<a href="' . PreparePHP_SELF( $_REQUEST, [], [ 'expanded_view' => 'true' ] ) . '">' .
				_( 'Expanded View' ) . '</a>';
			}
			else
			{
				$expanded_view_header = '<a href="' . PreparePHP_SELF( $_REQUEST, [], [ 'expanded_view' => 'false' ] ) . '">' .
				_( 'Original View' ) . '</a>';
			}

			DrawHeader( $expanded_view_header, SubmitButton() );

			$options['valign-middle'] = true;
		}
	}

	// Do hook.
	do_action( 'Student_Billing/StudentFees.php|student_fees_header' );

	ListOutput( $RET, $columns, 'Fee', 'Fees', $link, [], $options );

	if ( empty( $_REQUEST['print_statements'] )
		&& AllowEdit() )
	{
		echo '<div class="center">' . SubmitButton() . '</div>';
	}

	echo '<br />';

	if ( empty( $_REQUEST['print_statements'] ) )
	{
		$payments_total = DBGetOne( "SELECT SUM(p.AMOUNT) AS TOTAL
			FROM billing_payments p
			WHERE p.STUDENT_ID='" . UserStudentID() . "'
			AND p.SCHOOL_ID='" . UserSchool() . "' AND p.SYEAR='" . UserSyear() . "'" );

		$table = '<table class="align-right student-billing-totals"><tr><td>' . _( 'Total from Fees' ) . ': ' . '</td><td>' . Currency( $fees_total ) . '</td></tr>';

		$table .= '<tr><td>' . _( 'Less' ) . ': ' . _( 'Total from Payments' ) . ': ' . '</td><td>' . Currency( $payments_total ) . '</td></tr>';

		$table .= '<tr><td>' . _( 'Balance' ) . ': </td>
			<td><b>' . Currency(  ( $fees_total - $payments_total ), 'CR' ) .
			'</b></td></tr></table>';

		DrawHeader( $table );

		echo '</form>';
	}
}
