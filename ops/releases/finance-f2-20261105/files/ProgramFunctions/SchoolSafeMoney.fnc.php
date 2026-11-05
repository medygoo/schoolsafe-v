<?php
/**
 * Contrôles communs des pages d'argent (lot F1, 05/11/2026, docs/qualification/ANALYSE_FINANCE_20261105.md).
 * Utilisés par Student_Billing et Accounting. Lecture seule : aucune donnée n'est modifiée ici.
 */

/**
 * F3 : ne garder que les colonnes que le formulaire a le droit d'écrire.
 * L'école, l'année, l'élève, l'auteur ou le remboursement ne viennent jamais du navigateur.
 *
 * @param array $columns Colonnes envoyées.
 * @param array $allowed Noms de colonnes autorisés (majuscules).
 *
 * @return array Colonnes autorisées seulement.
 */
function SchoolSafeMoneyColumns( $columns, $allowed )
{
	$kept = [];

	foreach ( (array) $columns as $name => $value )
	{
		if ( in_array( strtoupper( (string) $name ), $allowed, true ) )
		{
			$kept[ strtoupper( (string) $name ) ] = $value;
		}
	}

	return $kept;
}

/**
 * F4 : montant normalisé (virgule acceptée comme séparateur décimal, espaces retirés).
 */
function SchoolSafeMoneyAmount( $amount )
{
	return trim( str_replace( [ ' ', ',' ], [ '', '.' ], (string) $amount ) );
}

/**
 * F4 : un montant doit être un nombre strictement positif, avec au plus deux décimales.
 *
 * @return string Message d'erreur, ou '' si le montant est valable.
 */
function SchoolSafeMoneyAmountProblem( $amount )
{
	$amount = SchoolSafeMoneyAmount( $amount );

	if ( ! preg_match( '/^\d{1,12}(\.\d{1,2})?$/', $amount ) || (float) $amount <= 0 )
	{
		return 'Montant refusé : indiquez un nombre positif (au plus deux chiffres après la virgule).';
	}

	return '';
}

/**
 * F11 : la même opération vient-elle d'être enregistrée (double clic, renvoi du formulaire) ?
 * Même table, mêmes valeurs, créée il y a moins de 60 secondes.
 *
 * @param string $table  Table (nom fixe dans le code, jamais venu du navigateur).
 * @param array  $fields Colonne => valeur déjà sûre (entier ou texte échappé).
 */
function SchoolSafeMoneyRecentDuplicate( $table, $fields )
{
	$where = [];

	foreach ( $fields as $column => $value )
	{
		$where[] = $value === null ?
			$column . ' IS NULL' :
			$column . "='" . $value . "'";
	}

	return (bool) DBGetOne( "SELECT 1 FROM " . $table . "
		WHERE " . implode( ' AND ', $where ) . "
		AND CREATED_AT > CURRENT_TIMESTAMP - INTERVAL '60 seconds'
		LIMIT 1" );
}

/**
 * F2 / F9 : l'élève est-il inscrit dans l'école et l'année en cours ?
 */
function SchoolSafeMoneyStudentInSchool( $student_id )
{
	return (bool) DBGetOne( "SELECT 1 FROM student_enrollment
		WHERE STUDENT_ID='" . (int) $student_id . "'
		AND SCHOOL_ID='" . UserSchool() . "'
		AND SYEAR='" . UserSyear() . "'
		LIMIT 1" );
}

/**
 * F12 : reste dû d'un élève dans l'école et l'année en cours (frais moins paiements).
 */
function SchoolSafeMoneyStudentBalance( $student_id )
{
	$fees = (float) DBGetOne( "SELECT COALESCE(SUM(AMOUNT),0) FROM billing_fees
		WHERE STUDENT_ID='" . (int) $student_id . "'
		AND SCHOOL_ID='" . UserSchool() . "'
		AND SYEAR='" . UserSyear() . "'" );

	$payments = (float) DBGetOne( "SELECT COALESCE(SUM(AMOUNT),0) FROM billing_payments
		WHERE STUDENT_ID='" . (int) $student_id . "'
		AND SCHOOL_ID='" . UserSchool() . "'
		AND SYEAR='" . UserSyear() . "'" );

	return round( $fees - $payments, 2 );
}

/**
 * F6 (lot F2, 05/11) : demande le motif d'une annulation. Affiche le formulaire tant que le motif manque.
 *
 * @param string $what Ce qui est annulé (« ce paiement », « ce frais »…).
 *
 * @return string Motif saisi, ou '' si le formulaire est affiché.
 */
function SchoolSafeMoneyReasonPrompt( $what )
{
	$reason = trim( (string) ( $_POST['ss_cancel_reason'] ?? '' ) );

	if ( $reason !== '' && ! empty( $_REQUEST['delete_ok'] ) )
	{
		RedirectURL( [ 'delete_ok' ] );

		return mb_substr( preg_replace( '/[\x00-\x1f\x7f]+/u', ' ', $reason ), 0, 200 );
	}

	$action = PreparePHP_SELF( $_REQUEST, [ 'delete_cancel' ], [ 'delete_ok' => '1' ] );
	$back = PreparePHP_SELF( $_REQUEST, [ 'modfunc', 'id', 'delete_ok' ] );

	PopTable( 'header', 'Annuler ' . $what );

	echo '<form action="' . $action . '" method="POST" class="ss-cancel-form">
		<p>L’opération n’est pas effacée : elle reste visible, barrée, avec votre nom, la date et le motif.</p>
		<label>Motif de l’annulation<br><textarea name="ss_cancel_reason" required maxlength="200" rows="3" cols="40"
		placeholder="Erreur de saisie, mauvais élève, montant erroné…"></textarea></label>
		<p>' . SubmitButton( 'Annuler ' . $what ) . ' <a href="' . $back . '">Retour, ne rien annuler</a></p>
	</form>';

	PopTable( 'footer' );

	return '';
}

/**
 * F6 (lot F2) : garde la ligne complète, le motif et l'auteur avant qu'une ligne d'argent soit effacée.
 *
 * @return bool false si la trace n'a pas pu être gardée (l'effacement doit alors être refusé).
 */
function SchoolSafeMoneyArchive( $table, $id, $reason, $extra_where = '' )
{
	if ( ! DBGetOne( "SELECT to_regclass('schoolsafe_money_cancellations')" ) )
	{
		return false;
	}

	$row = DBGet( "SELECT * FROM " . $table . "
		WHERE ID='" . (int) $id . "'
		AND SCHOOL_ID='" . UserSchool() . "'
		AND SYEAR='" . UserSyear() . "'" . $extra_where );

	if ( empty( $row[1] ) )
	{
		return false;
	}

	DBQuery( "INSERT INTO schoolsafe_money_cancellations
		(SCHOOL_ID,SYEAR,SOURCE_TABLE,SOURCE_ID,AMOUNT,REASON,CANCELLED_BY,ROW_DATA)
		VALUES('" . UserSchool() . "','" . UserSyear() . "','" . DBEscapeString( $table ) . "','" . (int) $id . "',
		" . ( is_numeric( $row[1]['AMOUNT'] ?? null ) ? "'" . $row[1]['AMOUNT'] . "'" : 'NULL' ) . ",
		'" . DBEscapeString( $reason ) . "','" . DBEscapeString( User( 'NAME' ) ) . "',
		'" . DBEscapeString( json_encode( $row[1], JSON_UNESCAPED_UNICODE ) ) . "')" );

	return true;
}

/**
 * F6 (lot F2) : motif obligatoire, puis trace gardée, avant l'effacement d'une ligne de paie ou de trésorerie.
 *
 * @return bool true si l'effacement peut se faire (motif saisi et trace gardée).
 */
function SchoolSafeMoneyCancelPrompt( $what, $table, $extra_where = '' )
{
	global $error;

	$reason = SchoolSafeMoneyReasonPrompt( $what );

	if ( $reason === '' )
	{
		return false;
	}

	if ( ! SchoolSafeMoneyArchive( $table, $_REQUEST['id'] ?? 0, $reason, $extra_where ) )
	{
		$error[] = 'Annulation impossible : la ligne est introuvable dans l’école en cours, ou la trace n’a pas pu être gardée.';

		RedirectURL( [ 'modfunc', 'id' ] );

		return false;
	}

	return true;
}
