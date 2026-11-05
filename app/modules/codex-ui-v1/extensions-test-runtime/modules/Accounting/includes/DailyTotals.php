<?php
/**
 * Daily Totals program
 *
 * @package RosarioSIS
 * @subpackage modules
 */

// Set start date.
$start_date = RequestedDate( 'start', date( 'Y-m' ) . '-01' );

// Set end date.
$end_date = RequestedDate( 'end', DBDate() );

DrawHeader( _programMenu( 'totals' ) );

echo '<form action="' . URLEscape( 'Modules.php?modname=' . $_REQUEST['modname'] . '&program=totals&accounting=' ) . '" method="GET">';

$header_checkboxes = '<label><input type="checkbox" value="true" name="accounting" id="accounting" ' .
	( ! isset( $_REQUEST['accounting'] )
		|| $_REQUEST['accounting'] == 'true' ? 'checked ' : '' ) . '/> ' .
	_( 'Income' ) . ' & ' . _( 'Expense' ) . '</label>&nbsp; ';

$header_checkboxes .= '<label><input type="checkbox" value="true" name="staff_payroll" id="staff_payroll" ' .
	( ! empty( $_REQUEST['staff_payroll'] ) ? 'checked ' : '' ) . '/> ' .
	_( 'Staff Payroll' ) . '</label>&nbsp; ';

if ( $RosarioModules['Student_Billing'] )
{
	$header_checkboxes .= '<label><input type="checkbox" value="true" name="student_billing" id="student_billing" ' .
		( ! empty( $_REQUEST['student_billing'] ) ? 'checked ' : '' ) . '/> ' .
		_( 'Student Billing' ) . '</label>';
}

DrawHeader( $header_checkboxes, '' );

DrawHeader( _( 'Report Timeframe' ) . ': ' .
	PrepareDate( $start_date, '_start', false ) . ' &nbsp; ' . _( 'to' ) . ' &nbsp; ' .
	PrepareDate( $end_date, '_end', false ) . ' ' . Buttons( _( 'Go' ) ) );

echo '</form>';

require_once 'ProgramFunctions/SchoolSafeMoney.fnc.php';

if ( SchoolSafeMoneyAccountingReady() )
{
	// Lot F4c (05/11) : totaux par monnaie (une colonne USD, une colonne FC), jamais additionnés :
	// recettes et dépenses, paie, frais et paiements des élèves, et total de chaque monnaie.
	$ss_period = " BETWEEN '" . $start_date . "' AND '" . $end_date . "'";

	$ss_lines = [];

	if ( ! isset( $_REQUEST['accounting'] )
		|| $_REQUEST['accounting'] == 'true' )
	{
		$ss_lines[] = [ _( 'Incomes' ), 1, "SELECT f.CURRENCY,sum(f.AMOUNT) AS AMOUNT
			FROM accounting_incomes f
			WHERE f.SYEAR='" . UserSyear() . "'
			AND f.SCHOOL_ID='" . UserSchool() . "'
			AND f.ASSIGNED_DATE" . $ss_period . "
			GROUP BY f.CURRENCY" ];

		$ss_lines[] = [ _( 'Less' ) . ': ' . _( 'Expenses' ), -1, "SELECT CURRENCY,sum(AMOUNT) AS AMOUNT
			FROM accounting_payments
			WHERE SYEAR='" . UserSyear() . "'
			AND SCHOOL_ID='" . UserSchool() . "'
			AND PAYMENT_DATE" . $ss_period . "
			AND STAFF_ID IS NULL
			GROUP BY CURRENCY" ];
	}

	if ( ! empty( $_REQUEST['staff_payroll'] ) )
	{
		$ss_lines[] = [ _( 'Salaries' ), 1, "SELECT f.CURRENCY,sum(f.AMOUNT) AS AMOUNT
			FROM accounting_salaries f, staff s
			WHERE f.SYEAR='" . UserSyear() . "'
			AND f.SCHOOL_ID='" . UserSchool() . "'
			AND f.ASSIGNED_DATE" . $ss_period . "
			AND f.STAFF_ID=s.STAFF_ID
			AND f.SYEAR=s.SYEAR
			GROUP BY f.CURRENCY" ];

		$ss_lines[] = [ _( 'Less' ) . ': ' . _( 'Staff Payments' ), -1, "SELECT p.CURRENCY,sum(p.AMOUNT) AS AMOUNT
			FROM accounting_payments p, staff s
			WHERE p.SYEAR='" . UserSyear() . "'
			AND p.SCHOOL_ID='" . UserSchool() . "'
			AND p.PAYMENT_DATE" . $ss_period . "
			AND p.STAFF_ID=s.STAFF_ID
			AND p.SYEAR=s.SYEAR
			GROUP BY p.CURRENCY" ];
	}

	if ( ! empty( $_REQUEST['student_billing'] )
		&& $RosarioModules['Student_Billing'] )
	{
		$ss_lines[] = [ _( 'Student Payments' ), 1, "SELECT CURRENCY,sum(AMOUNT) AS AMOUNT
			FROM billing_payments
			WHERE SYEAR='" . UserSyear() . "'
			AND SCHOOL_ID='" . UserSchool() . "'
			AND PAYMENT_DATE" . $ss_period . "
			GROUP BY CURRENCY" ];

		$ss_lines[] = [ _( 'Less' ) . ': ' . _( 'Fees' ), -1, "SELECT f.CURRENCY,sum(f.AMOUNT) AS AMOUNT
			FROM billing_fees f
			WHERE f.SCHOOL_ID='" . UserSchool() . "'
			AND f.ASSIGNED_DATE" . $ss_period . "
			GROUP BY f.CURRENCY" ];
	}

	$ss_totals = array_fill_keys( array_keys( SchoolSafeMoneyCurrencies() ), 0.0 );

	echo '<br />';

	PopTable( 'header', _( 'Totals' ) );

	echo '<table class="cellspacing-5 align-right"><tr><td></td>';

	foreach ( array_keys( $ss_totals ) as $ss_currency )
	{
		echo '<th>' . $ss_currency . '</th>';
	}

	echo '</tr>';

	foreach ( $ss_lines as $ss_line )
	{
		list( $ss_label, $ss_sign, $ss_sql ) = $ss_line;

		$ss_amounts = array_fill_keys( array_keys( $ss_totals ), 0.0 );

		foreach ( (array) DBGet( $ss_sql ) as $ss_row )
		{
			$ss_amounts[ SchoolSafeMoneyCurrency( $ss_row['CURRENCY'] ) ] += (float) $ss_row['AMOUNT'];
		}

		echo '<tr><td>' . $ss_label . ': </td>';

		foreach ( $ss_amounts as $ss_currency => $ss_amount )
		{
			$ss_totals[ $ss_currency ] += $ss_sign * $ss_amount;

			echo '<td>' . SchoolSafeMoneyFormat( $ss_amount, $ss_currency ) . '</td>';
		}

		echo '</tr>';
	}

	echo '<tr><td><b>' . _( 'Total' ) . ': </b></td>';

	foreach ( $ss_totals as $ss_currency => $ss_total )
	{
		echo '<td><b>' . SchoolSafeMoneyFormat( $ss_total, $ss_currency ) . '</b></td>';
	}

	echo '</tr></table>';

	PopTable( 'footer' );

	// Les anciens totaux ci-dessous ne servent que sans le script F4c.
	return;
}

// Accounting.
if ( ! isset( $_REQUEST['accounting'] )
	|| $_REQUEST['accounting'] == 'true' )
{
	$accounting_payments = DBGetOne( "SELECT sum(AMOUNT) AS AMOUNT
		FROM accounting_payments
		WHERE SYEAR='" . UserSyear() . "'
		AND SCHOOL_ID='" . UserSchool() . "'
		AND PAYMENT_DATE BETWEEN '" . $start_date . "'
		AND '" . $end_date . "'
		AND STAFF_ID IS NULL" );

	$accounting_incomes = DBGetOne( "SELECT sum(f.AMOUNT) AS AMOUNT
		FROM accounting_incomes f
		WHERE f.SYEAR='" . UserSyear() . "'
		AND f.SCHOOL_ID='" . UserSchool() . "'
		AND f.ASSIGNED_DATE BETWEEN '" . $start_date . "'
		AND '" . $end_date . "'" );
}

// Staff salaries.
if ( ! empty( $_REQUEST['staff_payroll'] ) )
{
	$staffpayroll_payments = DBGetOne( "SELECT sum(p.AMOUNT) AS AMOUNT
		FROM accounting_payments p, staff s
		WHERE p.SYEAR='" . UserSyear() . "'
		AND s.SYEAR=p.SYEAR
		AND p.SCHOOL_ID='" . UserSchool() . "'
		AND p.PAYMENT_DATE BETWEEN '" . $start_date . "'
		AND '" . $end_date . "'
		AND p.STAFF_ID=s.STAFF_ID
		AND p.SYEAR=s.SYEAR" );

	$staffpayroll_incomes = DBGetOne( "SELECT sum(f.AMOUNT) AS AMOUNT
		FROM accounting_salaries f, staff s
		WHERE f.SYEAR='" . UserSyear() . "'
		AND s.SYEAR=f.SYEAR
		AND f.SCHOOL_ID='" . UserSchool() . "'
		AND f.ASSIGNED_DATE BETWEEN '" . $start_date . "'
		AND '" . $end_date . "'
		AND f.STAFF_ID=s.STAFF_ID
		AND f.SYEAR=s.SYEAR" );
}

// Student Billing.
if ( ! empty( $_REQUEST['student_billing'] )
	&& $RosarioModules['Student_Billing'] )
{
	require_once 'ProgramFunctions/SchoolSafeMoney.fnc.php';

	// Lot F4b (05/11) : le solde général est en USD ; seuls les frais et paiements des élèves en USD y entrent.
	// Ceux en FC sont lus à part et affichés sur une ligne séparée.
	$billing_multi_currency = SchoolSafeMoneyCurrencyReady();

	$billing_currency_where = $billing_multi_currency ? " AND CURRENCY='USD'" : '';

	$billing_payments = DBGetOne( "SELECT sum(AMOUNT) AS AMOUNT
		FROM billing_payments
		WHERE SYEAR='" . UserSyear() . "'
		AND SCHOOL_ID='" . UserSchool() . "'
		AND PAYMENT_DATE BETWEEN '" . $start_date . "'
		AND '" . $end_date . "'" . $billing_currency_where );

	$billing_fees = DBGetOne( "SELECT sum(f.AMOUNT) AS AMOUNT
		FROM billing_fees f
		WHERE f.SCHOOL_ID='" . UserSchool() . "'
		AND f.ASSIGNED_DATE BETWEEN '" . $start_date . "'
		AND '" . $end_date . "'" . $billing_currency_where );

	if ( $billing_multi_currency )
	{
		$billing_payments_fc = DBGetOne( "SELECT sum(AMOUNT) AS AMOUNT
			FROM billing_payments
			WHERE SYEAR='" . UserSyear() . "'
			AND SCHOOL_ID='" . UserSchool() . "'
			AND PAYMENT_DATE BETWEEN '" . $start_date . "'
			AND '" . $end_date . "'
			AND CURRENCY='FC'" );

		$billing_fees_fc = DBGetOne( "SELECT sum(f.AMOUNT) AS AMOUNT
			FROM billing_fees f
			WHERE f.SCHOOL_ID='" . UserSchool() . "'
			AND f.ASSIGNED_DATE BETWEEN '" . $start_date . "'
			AND '" . $end_date . "'
			AND f.CURRENCY='FC'" );
	}
}

echo '<br />';

PopTable( 'header', _( 'Totals' ) );

echo '<table class="cellspacing-5 align-right">';

$total = 0;

// Accounting.
if ( ! isset( $_REQUEST['accounting'] )
	|| $_REQUEST['accounting'] == 'true' )
{
	echo '<tr><td>' . _( 'Incomes' ) . ': ' .
		'</td><td>' . Currency( $accounting_incomes ) . '</td></tr>';

	echo '<tr><td>' . _( 'Less' ) . ': ' . _( 'Expenses' ) . ': ' .
		'</td><td>' . Currency( $accounting_payments ) . '</td></tr>';

	$total += $accounting_incomes - $accounting_payments;
}


// Staff salaries.
if ( ! empty( $_REQUEST['staff_payroll'] ) )
{
	echo '<tr><td>' . _( 'Salaries' ) . ': ' .
		'</td><td>' . Currency( $staffpayroll_incomes ) . '</td></tr>';

	echo '<tr><td>' . _( 'Less' ) . ': ' . _( 'Staff Payments' ) . ': ' .
		'</td><td>' . Currency( $staffpayroll_payments ) . '</td></tr>';

	$total += $staffpayroll_incomes - $staffpayroll_payments;
}

// Student Billing.
if ( ! empty( $_REQUEST['student_billing'] )
	&& $RosarioModules['Student_Billing'] )
{
	echo '<tr><td>' . _( 'Student Payments' ) . ': ' .
		'</td><td>' . Currency( $billing_payments ) . '</td></tr>';

	echo '<tr><td>' . _( 'Less' ) . ': ' . _( 'Fees' ) . ': ' .
		'</td><td>' . Currency( $billing_fees ) . '</td></tr>';

	$total += $billing_payments - $billing_fees;
}

echo '<tr><td><b>' . _( 'Total' ) . ': ' . '</b></td>' .
	'<td><b>' . Currency( $total ) . '</b></td></tr>';

if ( ! empty( $billing_multi_currency )
	&& ( (float) $billing_payments_fc || (float) $billing_fees_fc ) )
{
	// Lot F4b (05/11) : frais et paiements des élèves en FC, hors du solde en USD.
	echo '<tr><td colspan="2">Dont paiements des élèves en FC : ' . SchoolSafeMoneyFormat( $billing_payments_fc, 'FC' ) .
		' (frais en FC : ' . SchoolSafeMoneyFormat( $billing_fees_fc, 'FC' ) . '), non inclus dans le solde en USD.</td></tr>';
}

echo '</table>';

PopTable( 'footer' );
