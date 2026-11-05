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
function SchoolSafeMoneyStudentBalance( $student_id, $currency = null )
{
	// Lot F4a : reste dû dans une monnaie (frais et paiements de cette monnaie seulement).
	$in_currency = $currency !== null && SchoolSafeMoneyCurrencyReady() ?
		" AND CURRENCY='" . SchoolSafeMoneyCurrency( $currency ) . "'" : '';

	$fees = (float) DBGetOne( "SELECT COALESCE(SUM(AMOUNT),0) FROM billing_fees
		WHERE STUDENT_ID='" . (int) $student_id . "'
		AND SCHOOL_ID='" . UserSchool() . "'
		AND SYEAR='" . UserSyear() . "'" . $in_currency );

	$payments = (float) DBGetOne( "SELECT COALESCE(SUM(AMOUNT),0) FROM billing_payments
		WHERE STUDENT_ID='" . (int) $student_id . "'
		AND SCHOOL_ID='" . UserSchool() . "'
		AND SYEAR='" . UserSyear() . "'" . $in_currency );

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

/* ---------------------------------------------------------------------------------------------------------
 * Lot F4a (05/11) : deux monnaies, franc congolais (FC) et dollar (USD). Décision du propriétaire,
 * docs/DECISIONS.md : chaque frais et chaque paiement porte sa monnaie ; un paiement dans l'autre monnaie est
 * converti au taux du jour (FC pour 1 USD) ; USD par défaut.
 * --------------------------------------------------------------------------------------------------------- */

/** Monnaies acceptées, dans l'ordre d'affichage (USD par défaut). */
function SchoolSafeMoneyCurrencies()
{
	return [ 'USD' => 'USD (dollar)', 'FC' => 'FC (franc congolais)' ];
}

/** Les colonnes de monnaie existent-elles (script F4a appliqué) ? */
function SchoolSafeMoneyCurrencyReady()
{
	static $ready = null;

	if ( $ready === null )
	{
		$ready = (bool) DBGetOne( "SELECT 1 FROM information_schema.columns
			WHERE table_name='billing_payments' AND column_name='paid_currency'" );
	}

	return $ready;
}

/** Monnaie valide, sinon USD. */
function SchoolSafeMoneyCurrency( $currency )
{
	$currency = strtoupper( trim( (string) $currency ) );

	return isset( SchoolSafeMoneyCurrencies()[ $currency ] ) ? $currency : 'USD';
}

/** Montant au format français avec sa monnaie : « 75 000,00 USD », « -20 000,00 FC ». */
function SchoolSafeMoneyFormat( $amount, $currency = 'USD' )
{
	$amount = (float) $amount;

	return ( $amount < 0 ? '-' : '' ) . number_format( abs( $amount ), 2, ',', "\u{00A0}" ) . "\u{00A0}" . SchoolSafeMoneyCurrency( $currency );
}

/** Taux du jour de l'école (FC pour 1 USD) à une date, ou 0 s'il n'est pas saisi. */
function SchoolSafeMoneyRate( $date )
{
	if ( ! SchoolSafeMoneyCurrencyReady() || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) )
	{
		return 0.0;
	}

	return (float) DBGetOne( "SELECT RATE FROM schoolsafe_exchange_rates
		WHERE SCHOOL_ID='" . UserSchool() . "'
		AND RATE_DATE='" . $date . "'" );
}

/**
 * Convertit un montant reçu dans la monnaie du compte réglé.
 *
 * @return float|false Montant converti, ou false si le taux du jour manque.
 */
function SchoolSafeMoneyConvert( $amount, $from, $to, $rate )
{
	$from = SchoolSafeMoneyCurrency( $from );
	$to = SchoolSafeMoneyCurrency( $to );

	if ( $from === $to )
	{
		return round( (float) $amount, 2 );
	}

	if ( (float) $rate <= 0 )
	{
		return false;
	}

	return round( $from === 'FC' ? (float) $amount / (float) $rate : (float) $amount * (float) $rate, 2 );
}

/**
 * Restes dus d'un élève par monnaie, dans l'école et l'année en cours.
 *
 * @return array [ 'USD' => reste, 'FC' => reste ] (seulement les monnaies utilisées ; USD si aucune).
 */
function SchoolSafeMoneyStudentBalances( $student_id )
{
	if ( ! SchoolSafeMoneyCurrencyReady() )
	{
		return [ 'USD' => SchoolSafeMoneyStudentBalance( $student_id ) ];
	}

	$rows = DBGet( "SELECT CURRENCY, SUM(AMOUNT) AS TOTAL FROM (
		SELECT CURRENCY, AMOUNT FROM billing_fees
			WHERE STUDENT_ID='" . (int) $student_id . "' AND SCHOOL_ID='" . UserSchool() . "' AND SYEAR='" . UserSyear() . "'
		UNION ALL
		SELECT CURRENCY, -AMOUNT FROM billing_payments
			WHERE STUDENT_ID='" . (int) $student_id . "' AND SCHOOL_ID='" . UserSchool() . "' AND SYEAR='" . UserSyear() . "'
		) t GROUP BY CURRENCY" );

	$balances = [];

	foreach ( (array) $rows as $row )
	{
		$balances[ SchoolSafeMoneyCurrency( $row['CURRENCY'] ) ] = round( (float) $row['TOTAL'], 2 );
	}

	uksort( $balances, function ( $a, $b ) { return $a === 'USD' ? -1 : ( $b === 'USD' ? 1 : 0 ); } );

	return $balances ? $balances : [ 'USD' => 0.0 ];
}

/** « 145 000,00 USD et 30 000,00 FC ». */
function SchoolSafeMoneyBalancesText( $balances )
{
	$parts = [];

	foreach ( (array) $balances as $currency => $amount )
	{
		$parts[] = SchoolSafeMoneyFormat( $amount, $currency );
	}

	return implode( ' et ', $parts );
}

/** Liste de choix de la monnaie pour un formulaire (USD par défaut). */
function SchoolSafeMoneyCurrencySelect( $name, $value = 'USD', $title = '' )
{
	$html = ( $title !== '' ? '<label>' . $title . '<br>' : '' ) . '<select name="' . AttrEscape( $name ) . '">';

	foreach ( SchoolSafeMoneyCurrencies() as $code => $label )
	{
		$html .= '<option value="' . $code . '"' . ( $code === SchoolSafeMoneyCurrency( $value ) ? ' selected' : '' ) . '>' . $label . '</option>';
	}

	return $html . '</select>' . ( $title !== '' ? '</label>' : '' );
}

/**
 * Lot F4a : tableau des totaux d'un élève, une ligne par monnaie (frais, paiements, reste).
 */
function SchoolSafeMoneyTotalsTable( $student_id )
{
	$rows = SchoolSafeMoneyCurrencyReady() ? DBGet( "SELECT CURRENCY,
		SUM(CASE WHEN K='F' THEN AMOUNT ELSE 0 END) AS FEES,
		SUM(CASE WHEN K='P' THEN AMOUNT ELSE 0 END) AS PAID
		FROM (SELECT 'F' AS K, CURRENCY, AMOUNT FROM billing_fees
			WHERE STUDENT_ID='" . (int) $student_id . "' AND SCHOOL_ID='" . UserSchool() . "' AND SYEAR='" . UserSyear() . "'
		UNION ALL SELECT 'P', CURRENCY, AMOUNT FROM billing_payments
			WHERE STUDENT_ID='" . (int) $student_id . "' AND SCHOOL_ID='" . UserSchool() . "' AND SYEAR='" . UserSyear() . "') t
		GROUP BY CURRENCY ORDER BY CASE WHEN CURRENCY='USD' THEN 0 ELSE 1 END" ) : DBGet( "SELECT 'USD' AS CURRENCY,
		(SELECT COALESCE(SUM(AMOUNT),0) FROM billing_fees WHERE STUDENT_ID='" . (int) $student_id . "' AND SCHOOL_ID='" . UserSchool() . "' AND SYEAR='" . UserSyear() . "') AS FEES,
		(SELECT COALESCE(SUM(AMOUNT),0) FROM billing_payments WHERE STUDENT_ID='" . (int) $student_id . "' AND SCHOOL_ID='" . UserSchool() . "' AND SYEAR='" . UserSyear() . "') AS PAID" );

	$html = '<table class="align-right student-billing-totals"><tr><th></th><th>Frais</th><th>Payé</th><th>Reste à payer</th></tr>';

	foreach ( (array) $rows as $row )
	{
		$balance = (float) $row['FEES'] - (float) $row['PAID'];

		$html .= '<tr><td><b>' . SchoolSafeMoneyCurrency( $row['CURRENCY'] ) . '</b></td>
			<td>' . SchoolSafeMoneyFormat( $row['FEES'], $row['CURRENCY'] ) . '</td>
			<td>' . SchoolSafeMoneyFormat( $row['PAID'], $row['CURRENCY'] ) . '</td>
			<td><b>' . ( $balance < 0 ?
				'Crédit ' . SchoolSafeMoneyFormat( -$balance, $row['CURRENCY'] ) :
				SchoolSafeMoneyFormat( $balance, $row['CURRENCY'] ) ) . '</b></td></tr>';
	}

	return $html . '</table>';
}

/** Lot F4a : « 1 USD = 2 850,00 FC ». */
function SchoolSafeMoneyRateText( $rate )
{
	return '1 USD = ' . number_format( (float) $rate, 2, ',', "\u{00A0}" ) . "\u{00A0}FC";
}
