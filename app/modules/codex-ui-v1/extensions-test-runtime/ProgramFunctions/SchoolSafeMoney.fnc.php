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

/* ---------------------------------------------------------------------------------------------------------
 * Lot F4c (05/11) : paie, trésorerie, cantine et rattrapage en deux monnaies. Décision du propriétaire,
 * docs/DECISIONS.md (5 novembre 2026) : chaque salaire, paiement du personnel, recette et dépense porte sa
 * monnaie (USD par défaut) ; soldes et solde général par monnaie, jamais additionnés ; cantine en FC.
 * --------------------------------------------------------------------------------------------------------- */

/**
 * Lot F4c (05/11) : les colonnes de monnaie de la paie et de la trésorerie existent-elles (script F4c appliqué) ?
 * Le lot F4c suppose le lot F4a (monnaies des frais et paiements des élèves, taux du jour).
 */
function SchoolSafeMoneyAccountingReady()
{
	static $ready = null;

	if ( $ready === null )
	{
		$ready = SchoolSafeMoneyCurrencyReady()
			&& (bool) DBGetOne( "SELECT 1 FROM information_schema.columns
				WHERE table_name='accounting_payments' AND column_name='paid_currency'" );
	}

	return $ready;
}

/** Lot F4c (05/11) : la monnaie des dossiers de rattrapage existe-t-elle (script F4c appliqué) ? */
function SchoolSafeMoneyRemediationReady()
{
	static $ready = null;

	if ( $ready === null )
	{
		$ready = SchoolSafeMoneyCurrencyReady()
			&& (bool) DBGetOne( "SELECT 1 FROM information_schema.columns
				WHERE table_name='schoolsafe_remediation_cases' AND column_name='currency'" );
	}

	return $ready;
}

/** Lot F4c (05/11) : monnaie de la cantine de l'école en cours (une seule par école, FC par défaut). */
function SchoolSafeMoneyCanteenCurrency()
{
	static $by_school = [];

	$school = (int) UserSchool();

	if ( ! isset( $by_school[ $school ] ) )
	{
		$value = $school > 0 ? DBGetOne( "SELECT CONFIG_VALUE FROM config
			WHERE SCHOOL_ID='" . $school . "'
			AND TITLE='SCHOOLSAFE_CANTEEN_CURRENCY'" ) : '';

		$value = strtoupper( trim( (string) $value ) );

		$by_school[ $school ] = isset( SchoolSafeMoneyCurrencies()[ $value ] ) ? $value : 'FC';
	}

	return $by_school[ $school ];
}

/**
 * Lot F4c (05/11) : montant porté au compte cantine pour un montant saisi dans une monnaie.
 * Seul un dépôt peut être reçu dans l'autre monnaie : il est converti au taux du jour.
 *
 * @param float  $amount        Montant saisi (déjà validé, positif).
 * @param string $paid_currency Monnaie reçue (USD ou FC ; vide = monnaie de la cantine).
 * @param string $type          Deposit, Credit ou Debit.
 *
 * @return array [ montant dans la monnaie de la cantine ou false, message de refus, note de conversion ].
 */
function SchoolSafeMoneyCanteenAmount( $amount, $paid_currency, $type )
{
	$canteen = SchoolSafeMoneyCanteenCurrency();

	$paid = trim( (string) $paid_currency ) === '' ? $canteen : SchoolSafeMoneyCurrency( $paid_currency );

	if ( $paid === $canteen )
	{
		return [ (float) $amount, '', '' ];
	}

	if ( $type !== 'Deposit' )
	{
		return [ false, 'Opération refusée : seul un dépôt peut être reçu en ' . $paid . '. Un crédit ou un débit se saisit en ' .
			$canteen . ', la monnaie de la cantine.', '' ];
	}

	$rate = SchoolSafeMoneyRate( DBDate() );

	$converted = SchoolSafeMoneyConvert( $amount, $paid, $canteen, $rate );

	if ( $converted === false )
	{
		return [ false, 'Dépôt refusé : aucun taux du jour pour le ' . ProperDate( DBDate() ) .
			'. Saisissez le taux (Finance, Réglages, « Taux du jour ») ou encaissez en ' . $canteen . ', la monnaie de la cantine.', '' ];
	}

	if ( $converted <= 0 || $converted > 9999999.99 )
	{
		return [ false, 'Dépôt refusé : le montant converti (' . SchoolSafeMoneyFormat( $converted, $canteen ) .
			') est hors des limites d’un compte cantine.', '' ];
	}

	return [ $converted, '', ' (reçu ' . SchoolSafeMoneyFormat( $amount, $paid ) . ', ' . SchoolSafeMoneyRateText( $rate ) . ')' ];
}

/**
 * Lot F4c (05/11) : tableau des totaux de paie d'un membre du personnel, une ligne par monnaie
 * (salaires, paiements, reste dû), dans l'école et l'année en cours.
 */
function SchoolSafeMoneyStaffTotalsTable( $staff_id )
{
	$rows = DBGet( "SELECT CURRENCY,
		SUM(CASE WHEN K='S' THEN AMOUNT ELSE 0 END) AS SALARIES,
		SUM(CASE WHEN K='P' THEN AMOUNT ELSE 0 END) AS PAID
		FROM (SELECT 'S' AS K, CURRENCY, AMOUNT FROM accounting_salaries
			WHERE STAFF_ID='" . (int) $staff_id . "' AND SCHOOL_ID='" . UserSchool() . "' AND SYEAR='" . UserSyear() . "'
		UNION ALL SELECT 'P', CURRENCY, AMOUNT FROM accounting_payments
			WHERE STAFF_ID='" . (int) $staff_id . "' AND SCHOOL_ID='" . UserSchool() . "' AND SYEAR='" . UserSyear() . "') t
		GROUP BY CURRENCY ORDER BY CASE WHEN CURRENCY='USD' THEN 0 ELSE 1 END" );

	if ( empty( $rows ) )
	{
		$rows = [ 1 => [ 'CURRENCY' => 'USD', 'SALARIES' => 0, 'PAID' => 0 ] ];
	}

	$html = '<table class="align-right accounting-staff-payroll-totals"><tr><th></th><th>' . _( 'Salaries' ) . '</th><th>' .
		_( 'Staff Payments' ) . '</th><th>' . _( 'Balance' ) . '</th></tr>';

	foreach ( (array) $rows as $row )
	{
		$balance = (float) $row['SALARIES'] - (float) $row['PAID'];

		$html .= '<tr><td><b>' . SchoolSafeMoneyCurrency( $row['CURRENCY'] ) . '</b></td>
			<td>' . SchoolSafeMoneyFormat( $row['SALARIES'], $row['CURRENCY'] ) . '</td>
			<td>' . SchoolSafeMoneyFormat( $row['PAID'], $row['CURRENCY'] ) . '</td>
			<td><b>' . ( $balance < 0 ?
				'Trop-versé ' . SchoolSafeMoneyFormat( -$balance, $row['CURRENCY'] ) :
				SchoolSafeMoneyFormat( $balance, $row['CURRENCY'] ) ) . '</b></td></tr>';
	}

	return $html . '</table>';
}

/**
 * Lot F4c (05/11) : sommes de la trésorerie par monnaie sur une période (dates AAAA-MM-JJ).
 *
 * @return array [ 'USD' => [ 'INCOMES', 'STUDENT_PAYMENTS', 'EXPENSES', 'STAFF_PAYMENTS' ], 'FC' => [ … ] ].
 */
function SchoolSafeMoneyAccountingSums( $start_date, $end_date, $with_student_payments )
{
	$start_date = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $start_date ) ? $start_date : '1900-01-01';
	$end_date = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $end_date ) ? $end_date : DBDate();

	$scope = "SYEAR='" . UserSyear() . "' AND SCHOOL_ID='" . UserSchool() . "'";

	$sql = "SELECT CURRENCY,K,SUM(AMOUNT) AS TOTAL FROM (
		SELECT CURRENCY,'INCOMES' AS K,AMOUNT FROM accounting_incomes
			WHERE " . $scope . " AND ASSIGNED_DATE BETWEEN '" . $start_date . "' AND '" . $end_date . "'
		UNION ALL SELECT CURRENCY,CASE WHEN STAFF_ID IS NULL THEN 'EXPENSES' ELSE 'STAFF_PAYMENTS' END,AMOUNT FROM accounting_payments
			WHERE " . $scope . " AND PAYMENT_DATE BETWEEN '" . $start_date . "' AND '" . $end_date . "'" .
		( $with_student_payments ? "
		UNION ALL SELECT CURRENCY,'STUDENT_PAYMENTS',AMOUNT FROM billing_payments
			WHERE " . $scope . " AND PAYMENT_DATE BETWEEN '" . $start_date . "' AND '" . $end_date . "'" : '' ) . "
		) t GROUP BY CURRENCY,K";

	$sums = [];

	foreach ( array_keys( SchoolSafeMoneyCurrencies() ) as $currency )
	{
		$sums[ $currency ] = [ 'INCOMES' => 0.0, 'STUDENT_PAYMENTS' => 0.0, 'EXPENSES' => 0.0, 'STAFF_PAYMENTS' => 0.0 ];
	}

	foreach ( (array) DBGet( $sql ) as $row )
	{
		$sums[ SchoolSafeMoneyCurrency( $row['CURRENCY'] ) ][ $row['K'] ] += (float) $row['TOTAL'];
	}

	return $sums;
}

/**
 * Lot F4c (05/11) : totaux des pages Recettes et Dépenses, une colonne par monnaie (USD, FC) :
 * recettes moins dépenses, puis solde général (recettes, paiements des élèves, dépenses, paiements du personnel).
 */
function SchoolSafeMoneyAccountingTotalsTable( $start_date, $end_date, $with_student_payments )
{
	$sums = SchoolSafeMoneyAccountingSums( $start_date, $end_date, $with_student_payments );

	$line = function( $label, $values, $bold = false )
	{
		$html = '<tr><td>' . $label . '</td>';

		foreach ( $values as $currency => $amount )
		{
			$html .= '<td>' . ( $bold ? '<b>' : '' ) . SchoolSafeMoneyFormat( $amount, $currency ) . ( $bold ? '</b>' : '' ) . '</td>';
		}

		return $html . '</tr>';
	};

	$pick = function( $callback ) use ( $sums )
	{
		$values = [];

		foreach ( $sums as $currency => $sum )
		{
			$values[ $currency ] = $callback( $sum );
		}

		return $values;
	};

	$table = '<table class="align-right accounting-totals"><tr><th></th>';

	foreach ( array_keys( $sums ) as $currency )
	{
		$table .= '<th>' . $currency . '</th>';
	}

	$table .= '</tr>';

	$table .= $line( _( 'Total from Incomes' ) . ': ', $pick( function( $s ) { return $s['INCOMES']; } ) );
	$table .= $line( _( 'Less' ) . ': ' . _( 'Total from Expenses' ) . ': ', $pick( function( $s ) { return $s['EXPENSES']; } ) );
	$table .= $line( _( 'Balance' ) . ': ', $pick( function( $s ) { return $s['INCOMES'] - $s['EXPENSES']; } ), true );

	// Solde général, par monnaie.
	$table .= '<tr><td colspan="' . ( count( $sums ) + 1 ) . '"><hr></td></tr>';
	$table .= $line( _( 'Total from Incomes' ) . ': ', $pick( function( $s ) { return $s['INCOMES']; } ) );

	if ( $with_student_payments )
	{
		$table .= $line( '& ' . _( 'Total from Student Payments' ) . ': ', $pick( function( $s ) { return $s['STUDENT_PAYMENTS']; } ) );
	}

	$table .= $line( _( 'Less' ) . ': ' . _( 'Total from Expenses' ) . ': ', $pick( function( $s ) { return $s['EXPENSES']; } ) );
	$table .= $line( '& ' . _( 'Total from Staff Payments' ) . ': ', $pick( function( $s ) { return $s['STAFF_PAYMENTS']; } ) );
	$table .= $line( _( 'General Balance' ) . ': ', $pick( function( $s ) {
		return $s['INCOMES'] + $s['STUDENT_PAYMENTS'] - $s['EXPENSES'] - $s['STAFF_PAYMENTS'];
	} ), true );

	return $table . '</table>';
}

/**
 * Lot F4c (05/11) : montant d'une ligne de paie ou de trésorerie, dans la monnaie de sa ligne ;
 * total tenu par monnaie. Payé dans l'autre monnaie : montant reçu et taux appliqué.
 *
 * @param array $row    Ligne (CURRENCY, et éventuellement PAID_CURRENCY, PAID_AMOUNT, RATE).
 * @param array $totals Totaux par monnaie (modifiés).
 */
function SchoolSafeMoneyRowAmount( $value, $row, &$totals )
{
	$currency = SchoolSafeMoneyCurrency( $row['CURRENCY'] ?? 'USD' );

	if ( ! is_array( $totals ) )
	{
		$totals = [];
	}

	$totals[ $currency ] = ( $totals[ $currency ] ?? 0.0 ) + (float) $value;

	$html = SchoolSafeMoneyFormat( $value, $currency );

	if ( ! empty( $row['RATE'] ) && ! empty( $row['PAID_CURRENCY'] ) && $row['PAID_CURRENCY'] !== $currency )
	{
		$html .= '<br><small>reçu ' . SchoolSafeMoneyFormat( $row['PAID_AMOUNT'], $row['PAID_CURRENCY'] )
			. ' · ' . SchoolSafeMoneyRateText( $row['RATE'] ) . '</small>';
	}

	return $html;
}

/**
 * Lot F4c (05/11) : conversion d'un paiement du personnel ou d'un paiement reçu dans l'autre monnaie.
 *
 * @param string $amount        Montant reçu (validé).
 * @param string $paid_currency Monnaie reçue.
 * @param string $account       Monnaie du compte réglé.
 * @param string $date          Date du paiement (AAAA-MM-JJ), pour le taux du jour.
 *
 * @return array|string Colonnes AMOUNT, CURRENCY, PAID_CURRENCY, PAID_AMOUNT (et RATE), ou message de refus.
 */
function SchoolSafeMoneyPaymentColumns( $amount, $paid_currency, $account, $date )
{
	$paid_currency = SchoolSafeMoneyCurrency( $paid_currency );
	$account = SchoolSafeMoneyCurrency( $account );
	$amount = SchoolSafeMoneyAmount( $amount );

	$rate = $paid_currency === $account ? 0 : SchoolSafeMoneyRate( $date );

	$converted = SchoolSafeMoneyConvert( $amount, $paid_currency, $account, $rate );

	if ( $converted === false )
	{
		return 'Paiement refusé : aucun taux du jour pour le ' . ProperDate( $date ) .
			'. Saisissez le taux (Finance, Réglages, « Taux du jour ») ou payez dans la monnaie du compte.';
	}

	if ( $converted <= 0 )
	{
		return 'Paiement refusé : le montant converti est nul.';
	}

	$columns = [
		'AMOUNT' => number_format( $converted, 2, '.', '' ),
		'CURRENCY' => $account,
		'PAID_CURRENCY' => $paid_currency,
		'PAID_AMOUNT' => $amount,
	];

	if ( $rate > 0 )
	{
		$columns['RATE'] = $rate;
	}

	return $columns;
}
