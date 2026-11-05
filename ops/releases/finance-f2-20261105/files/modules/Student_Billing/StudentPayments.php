<?php

require_once 'ProgramFunctions/FileUpload.fnc.php';
require_once 'modules/Student_Billing/functions.inc.php';
// Lot F1 (05/11) : colonnes autorisées, montants, doublons, trop-perçu, école en cours.
require_once 'ProgramFunctions/SchoolSafeMoney.fnc.php';
if (isset($_SESSION['schoolsafe_payment_draft']) && ($_SESSION['schoolsafe_payment_draft']['expires']??0)<time()) unset($_SESSION['schoolsafe_payment_draft']);
if ($_REQUEST['modfunc']==='save' && isset($_POST['schoolsafe_payment_draft_id'])
 && (User('PROFILE')!=='admin'||!AllowEdit())) {
 http_response_code(403);echo ErrorMessage(['Acces a la confirmation du brouillon refuse.'],'error');return;
}

if ( empty( $_REQUEST['print_statements'] ) )
{
	DrawHeader( ProgramTitle() );

SchoolSafeCard(
	'card',
	'Billing',
	'Facturation',
	'Payments',
	'Paiements',
	'This page records the payments of one student.',
	'Cette page enregistre les paiements d’un élève.'
);


	Search( 'student_id', issetVal( $extra ) );
}

if ( $_REQUEST['modfunc'] === 'save'
	&& AllowEdit()
	&& UserStudentID() )
{
	// Add eventual Dates to $_REQUEST['values'].
	AddRequestedDates( 'values', 'post' );
	$draft_id=$_POST['schoolsafe_payment_draft_id']??null;
	if ($draft_id!==null || !empty($_SESSION['schoolsafe_payment_draft'])) {
		if ($draft_id===null && isset($_REQUEST['values']['new'])) {
			http_response_code(400);echo ErrorMessage(['Identifiant du brouillon manquant. Aucun paiement enregistre.'],'error');return;
		}
		if ($draft_id!==null) {
			if (!is_array($_POST['values']??null) || count($_POST['values'])!==1 || !is_array($_POST['values']['new']??null)) {
				http_response_code(400);echo ErrorMessage(['Valider le brouillon separement des autres modifications.'],'error');return;
			}
			require_once 'ProgramFunctions/SchoolSafePaymentIntent.fnc.php';
			try {
				$result=SchoolSafeConfirmPaymentIntent(SchoolSafePaymentDraftId($draft_id),$_POST['values']['new']);
				unset($_SESSION['schoolsafe_payment_draft']);
				echo '<p role="status">Paiement enregistre une seule fois pour ce brouillon. <a href="/schoolsafe-receipt.php?id='.(int)$result['receiptId'].'">Consulter le recu</a></p>';
				return;
			} catch(Throwable $failure) {
				http_response_code(in_array($failure->getCode(),[400,403,409],true)?$failure->getCode():503);
				echo ErrorMessage(['Confirmation refusee ou indisponible. Verifier le dossier : aucune nouvelle confirmation n’est affichee.'],'error');return;
			}
		}
	}

	foreach ( (array) $_REQUEST['values'] as $id => $columns )
	{
		// F3 : seules les colonnes du formulaire sont écrites (jamais école, élève, auteur, remboursement, fichier).
		$columns = SchoolSafeMoneyColumns( $columns, [ 'AMOUNT', 'PAYMENT_DATE', 'COMMENTS', 'LUNCH_PAYMENT' ] );

		if ( $id !== 'new' )
		{
			// F4 : un montant modifié doit rester un nombre positif.
			if ( isset( $columns['AMOUNT'] ) )
			{
				$problem = SchoolSafeMoneyAmountProblem( $columns['AMOUNT'] );

				if ( $problem )
				{
					$error[] = $problem;
					unset( $columns['AMOUNT'] );
				}
				else
				{
					$columns['AMOUNT'] = SchoolSafeMoneyAmount( $columns['AMOUNT'] );
				}
			}

			$columns['FILE_ATTACHED'] = _savePaymentsFile( $id );

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
				'billing_payments',
				$columns,
				[
					'ID' => (int) $id,
					'STUDENT_ID' => UserStudentID(),
					'SCHOOL_ID' => UserSchool(),
					'SYEAR' => UserSyear(),
				]
			);
		}
		elseif ( isset( $columns['AMOUNT'] )
			&& trim( (string) $columns['AMOUNT'] ) !== ''
			&& ( $problem = SchoolSafeMoneyAmountProblem( $columns['AMOUNT'] ) ) )
		{
			// F4 : montant négatif, nul ou illisible refusé (le remboursement reste un bouton à part).
			$error[] = $problem;
		}
		elseif ( isset( $columns['AMOUNT'] )
			&& trim( (string) $columns['AMOUNT'] ) !== ''
			&& empty( $columns['PAYMENT_DATE'] ) )
		{
			$error[] = 'Indiquez la date du paiement.';
		}
		elseif ( isset( $columns['AMOUNT'] )
			&& trim( (string) $columns['AMOUNT'] ) !== ''
			&& SchoolSafeMoneyRecentDuplicate( 'billing_payments', [
				'STUDENT_ID' => (int) UserStudentID(),
				'SCHOOL_ID' => (int) UserSchool(),
				'SYEAR' => (int) UserSyear(),
				'AMOUNT' => SchoolSafeMoneyAmount( $columns['AMOUNT'] ),
				'PAYMENT_DATE' => $columns['PAYMENT_DATE'],
			] ) )
		{
			// F11 : double clic ou formulaire renvoyé.
			$error[] = 'Ce paiement vient déjà d’être enregistré (même montant, même date). Il n’a pas été ajouté une seconde fois.';
		}
		elseif ( isset( $columns['AMOUNT'] )
			&& trim( (string) $columns['AMOUNT'] ) !== ''
			&& (float) SchoolSafeMoneyAmount( $columns['AMOUNT'] ) > max( 0, SchoolSafeMoneyStudentBalance( UserStudentID() ) )
			&& empty( $_POST['ss_overpay_ok'] ) )
		{
			// F12 : trop-perçu seulement après confirmation.
			$error[] = 'Le montant dépasse le reste dû (' . Currency( max( 0, SchoolSafeMoneyStudentBalance( UserStudentID() ) ) ) .
				'). Pour enregistrer quand même le surplus comme crédit, cochez « Accepter un trop-perçu » puis enregistrez à nouveau.';
		}
		elseif ( isset( $columns['AMOUNT'] )
			&& trim( (string) $columns['AMOUNT'] ) !== '' )
		{
			$columns['AMOUNT'] = SchoolSafeMoneyAmount( $columns['AMOUNT'] );

			$insert_columns = [
				'STUDENT_ID' => UserStudentID(),
				'SCHOOL_ID' => UserSchool(),
				'SYEAR' => UserSyear(),
			];

			$columns['FILE_ATTACHED'] = _savePaymentsFile( $id );

			// @since 11.2 Add CREATED_BY column to billing_fees & billing_payments tables
			$columns['CREATED_BY'] = DBEscapeString( User( 'NAME' ) );

			DBInsert(
				'billing_payments',
				$insert_columns + $columns
			);
			unset($_SESSION['schoolsafe_payment_draft']);
		}
	}

	// Unset modfunc, values, dates, billing_fees & redirect URL.
	RedirectURL( [ 'modfunc', 'values', 'month_values', 'day_values', 'year_values', 'billing_fees' ] );
}

// Lot F2 (F6, 05/11) : « Annuler » remplace « Supprimer ». Le paiement n'est plus effacé : une ligne
// d'annulation (montant opposé, motif, auteur, date) s'ajoute dessous, et le reçu passe à « Paiement annulé ».
if ( $_REQUEST['modfunc'] === 'remove'
	// @since 8.5 Admin Student Payments Delete restriction.
	&& AllowEdit( 'Student_Billing/StudentPayments.php&modfunc=remove' ) )
{
	$payment_RET = DBGet( "SELECT p.AMOUNT
		FROM billing_payments p
		WHERE p.ID='" . (int) $_REQUEST['id'] . "'
		AND p.STUDENT_ID='" . UserStudentID() . "'
		AND p.SCHOOL_ID='" . UserSchool() . "'
		AND p.SYEAR='" . UserSyear() . "'
		AND p.REFUNDED_PAYMENT_ID IS NULL
		AND p.AMOUNT>0
		AND NOT EXISTS(SELECT 1 FROM billing_payments r WHERE r.REFUNDED_PAYMENT_ID=p.ID)" );

	if ( empty( $payment_RET[1] ) )
	{
		$error[] = 'Annulation impossible : ce paiement n’appartient pas à cet élève, ou il est déjà annulé ou remboursé.';

		RedirectURL( [ 'modfunc', 'id' ] );
	}
	elseif ( $reason = SchoolSafeMoneyReasonPrompt( 'ce paiement' ) )
	{
		DBInsert(
			'billing_payments',
			[
				'SYEAR' => UserSyear(),
				'SCHOOL_ID' => UserSchool(),
				'STUDENT_ID' => UserStudentID(),
				'AMOUNT' => ( $payment_RET[1]['AMOUNT'] * -1 ),
				'PAYMENT_DATE' => DBDate(),
				'COMMENTS' => DBEscapeString( 'Annulation : ' . $reason ),
				'REFUNDED_PAYMENT_ID' => (int) $_REQUEST['id'],
				'CREATED_BY' => DBEscapeString( User( 'NAME' ) ),
			]
		);

		$note[] = button( 'check' ) . '&nbsp;Paiement annulé. Il reste visible, avec le motif ; son reçu indique « Paiement annulé ».';

		// Unset modfunc & ID & redirect URL.
		RedirectURL( [ 'modfunc', 'id' ] );
	}
}

if ( $_REQUEST['modfunc'] === 'refund'
	// @since 8.5 Also exclude Refund.
	&& AllowEdit( 'Student_Billing/StudentPayments.php&modfunc=remove' ) )
{
	if ( DeletePrompt( _( 'Payment' ), _( 'Refund' ) ) )
	{
		// F2 : le paiement doit être celui de cet élève, dans l'école et l'année en cours,
		// ne pas être lui-même un remboursement, et ne pas être déjà remboursé.
		$payment_RET = DBGet( "SELECT p.COMMENTS,p.AMOUNT
			FROM billing_payments p
			WHERE p.ID='" . (int) $_REQUEST['id'] . "'
			AND p.STUDENT_ID='" . UserStudentID() . "'
			AND p.SCHOOL_ID='" . UserSchool() . "'
			AND p.SYEAR='" . UserSyear() . "'
			AND p.REFUNDED_PAYMENT_ID IS NULL
			AND p.AMOUNT>0
			AND NOT EXISTS(SELECT 1 FROM billing_payments r WHERE r.REFUNDED_PAYMENT_ID=p.ID)" );

		if ( empty( $payment_RET[1] ) )
		{
			$error[] = 'Remboursement impossible : ce paiement n’appartient pas à cet élève, ou il est déjà remboursé.';
			RedirectURL( [ 'modfunc', 'id' ] );
		}
		else
		{

		$comments = $payment_RET[1]['COMMENTS'] ?
			$payment_RET[1]['COMMENTS'] . ' &mdash; ' . _( 'Refund' ) :
			_( 'Refund' );

		DBInsert(
			'billing_payments',
			[
				'SYEAR' => UserSyear(),
				'SCHOOL_ID' => UserSchool(),
				'STUDENT_ID' => UserStudentID(),
				'AMOUNT' => ( $payment_RET[1]['AMOUNT'] * -1 ),
				'PAYMENT_DATE' => DBDate(),
				'COMMENTS' => DBEscapeString( $comments ),
				'REFUNDED_PAYMENT_ID' => (int) $_REQUEST['id'],
				// @since 11.2 Add CREATED_BY column to billing_fees & billing_payments tables
				'CREATED_BY' => DBEscapeString( User( 'NAME' ) ),
			]
		);

		// Unset modfunc & ID & redirect URL.
		RedirectURL( [ 'modfunc', 'id' ] );
		}
	}
}

if ( UserStudentID()
	&& ! $_REQUEST['modfunc'] )
{
	echo ErrorMessage( $note, 'note' );
	echo ErrorMessage( $error );

	$payments_total = 0;

	$functions = [
		'REMOVE' => '_makePaymentsRemove',
		'AMOUNT' => '_makePaymentsAmount',
		'PAYMENT_DATE' => 'ProperDate',
		'COMMENTS' => '_makePaymentsCommentsInput',
		'LUNCH_PAYMENT' => '_lunchInput',
		'FILE_ATTACHED' => '_makePaymentsFileInput',
		'CREATED_AT' => 'ProperDateTime',
	];

	$refunded_payments_RET = DBGet( "SELECT '' AS REMOVE,ID,REFUNDED_PAYMENT_ID,
		AMOUNT,PAYMENT_DATE,COMMENTS,LUNCH_PAYMENT,FILE_ATTACHED,
		CREATED_BY,CREATED_AT
		FROM billing_payments
		WHERE STUDENT_ID='" . UserStudentID() . "'
		AND SCHOOL_ID='" . UserSchool() . "'
		AND SYEAR='" . UserSyear() . "'
		AND (REFUNDED_PAYMENT_ID IS NOT NULL)", $functions, [ 'REFUNDED_PAYMENT_ID' ] );

	$payments_RET = DBGet( "SELECT '' AS REMOVE,ID,REFUNDED_PAYMENT_ID,
		AMOUNT,PAYMENT_DATE,COMMENTS,LUNCH_PAYMENT,FILE_ATTACHED,
		CREATED_BY,CREATED_AT
		FROM billing_payments
		WHERE STUDENT_ID='" . UserStudentID() . "'
		AND SCHOOL_ID='" . UserSchool() . "'
		AND SYEAR='" . UserSyear() . "'
		AND REFUNDED_PAYMENT_ID IS NULL ORDER BY ID", $functions );

	$i = 1;
	$RET = [];

	// Lot F2 (F1, 05/11) : le reçu de chaque paiement (numéro, état, lien pour l'imprimer).
	require_once 'ProgramFunctions/SchoolSafeReceipts.fnc.php';

	$receipts_ready = SchoolSafeReceiptsTableReady();

	$receipts_RET = $receipts_ready ? DBGet( "SELECT DISTINCT ON (SOURCE_ID) ID,SOURCE_ID,NUMBER,STATUS,SCHOOL_ID,SYEAR
		FROM schoolsafe_payment_receipts
		WHERE SOURCE='billing'
		AND STUDENT_ID='" . UserStudentID() . "'
		AND SCHOOL_ID='" . UserSchool() . "'
		AND SYEAR='" . UserSyear() . "'
		ORDER BY SOURCE_ID,REVISION DESC", [], [ 'SOURCE_ID' ] ) : [];

	foreach ( (array) $payments_RET as $payment )
	{
		$receipt = $receipts_RET[ $payment['ID'] ][1] ?? null;

		$payment['RECEIPT'] = $receipt ?
			'<a href="schoolsafe-receipt.php?id=' . (int) $receipt['ID'] . '" target="_blank" rel="noopener">'
				. SchoolSafeReceiptNumber( $receipt ) . '</a>'
				. ( $receipt['STATUS'] === 'valid' ? '' : '<br><small>' . [ 'corrected' => 'remplacé', 'cancelled' => 'annulé', 'refunded' => 'remboursé' ][ $receipt['STATUS'] ] . '</small>' ) :
			'';

		$RET[$i] = $payment;

		if ( ! empty( $refunded_payments_RET[$payment['ID']] ) )
		{
			$i++;
			$RET[$i] = ( $refunded_payments_RET[$payment['ID']][1] + [ 'row_color' => 'FF0000' ] );
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
		'AMOUNT' => _( 'Amount' ),
		'PAYMENT_DATE' => _( 'Date' ),
		'COMMENTS' => _( 'Comment' ),
		'LUNCH_PAYMENT' => _( 'Lunch Payment' ),
	];

	if ( empty( $_REQUEST['print_statements'] ) )
	{
		$columns += [ 'FILE_ATTACHED' => _( 'File Attached' ) ];
	}

	if ( ! empty( $receipts_ready ) && ! isset( $_REQUEST['_ROSARIO_PDF'] ) )
	{
		$columns += [ 'RECEIPT' => 'Reçu' ];
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

	if ( empty( $_REQUEST['print_statements'] )
		&& AllowEdit() )
	{
		$local_draft=$_SESSION['schoolsafe_payment_draft']??[];
		if (($local_draft['staff']??0)!==(int)User('STAFF_ID') || ($local_draft['school']??0)!==(int)UserSchool()
			|| ($local_draft['year']??0)!==(int)UserSyear() || ($local_draft['student']??0)!==(int)UserStudentID()
			|| ($local_draft['expires']??0)<time()) $local_draft=[];
		if (User('PROFILE')==='admin' && !isset($_REQUEST['_ROSARIO_PDF']))
			echo '<p><a href="/pwa-payment-drafts.html">Brouillons de paiements hors connexion</a></p>';
		if ($local_draft) echo '<p role="status">Brouillon repris : verifier les informations avant de confirmer le paiement.</p>';
		$link['add']['html'] = [
			'REMOVE' => button( 'add' ),
			'AMOUNT' => $local_draft ? TextInput($local_draft['amount'],'values[new][AMOUNT]','',' type="number" step="0.01" min="0.01"',false) : _makePaymentsTextInput('', 'AMOUNT'),
			'PAYMENT_DATE' => $local_draft ? DateInput($local_draft['date'],'values[new][PAYMENT_DATE]','',false,false) : _makePaymentsDateInput(DBDate(), 'PAYMENT_DATE'),
			'COMMENTS' => $local_draft ? TextInput($local_draft['comment'],'values[new][COMMENTS]','','maxlength="250"',false) : _makePaymentsCommentsInput('', 'COMMENTS'),
			'LUNCH_PAYMENT' => _lunchInput( '', 'LUNCH_PAYMENT' ),
			'FILE_ATTACHED' => _makePaymentsFileInput( '', 'FILE_ATTACHED' ),
		];
	}

	// Do hook.
	// @since 6.5.1 Move header action hook above form.
	do_action( 'Student_Billing/StudentPayments.php|student_payments_header' );

	$options = [];

	if ( empty( $_REQUEST['print_statements'] ) )
	{
		echo '<form action="' . URLEscape(
			'Modules.php?modname=' . $_REQUEST['modname'] . '&modfunc=save&student_id=' . UserStudentID()
		) . '" method="POST">';
		if (!empty($local_draft['draft_id'])) echo '<input type="hidden" name="schoolsafe_payment_draft_id" value="'.AttrEscape($local_draft['draft_id']).'">';

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

			// F12 : confirmation explicite d'un trop-perçu.
			DrawHeader( $expanded_view_header, '<label class="nobr"><input type="checkbox" name="ss_overpay_ok" value="1"> Accepter un trop-perçu</label> ' . SubmitButton() );

			$options['valign-middle'] = true;
		}
	}

	ListOutput(
		$RET,
		$columns,
		'Payment',
		'Payments',
		$link,
		[],
		$options
	);

	if ( empty( $_REQUEST['print_statements'] )
		&& AllowEdit() )
	{
		echo '<div class="center">' . SubmitButton() . '</div>';
	}

	echo '<br />';

	$fees_total = DBGetOne( "SELECT SUM(f.AMOUNT) AS TOTAL
		FROM billing_fees f
		WHERE f.STUDENT_ID='" . UserStudentID() . "'
		AND f.SCHOOL_ID='" . UserSchool() . "'
		AND f.SYEAR='" . UserSyear() . "'" );

	$table = '<table class="align-right student-billing-totals"><tr>
		<td>' . _( 'Total from Fees' ) . ': </td>
		<td>' . Currency( $fees_total ) . '</td></tr>';

	$table .= '<tr><td>' . _( 'Less' ) . ': ' . _( 'Total from Payments' ) . ': </td>
		<td>' . Currency( $payments_total ) . '</td></tr>';

	$table .= '<tr><td>' . _( 'Balance' ) . ': </td>
		<td><b>' . Currency(  ( $fees_total - $payments_total ), 'CR' ) . '</b></td>
		</tr></table>';

	DrawHeader( $table );

	if ( empty( $_REQUEST['print_statements'] )
		&& AllowEdit() )
	{
		echo '</form>';
	}
}
