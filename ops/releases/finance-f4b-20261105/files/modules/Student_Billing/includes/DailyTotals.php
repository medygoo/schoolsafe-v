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

$grade_level_breakdown = issetVal( $_REQUEST['grade_level_breakdown'], '' );

DrawHeader( _programMenu( 'totals' ) );

echo '<form action="' . URLEscape( 'Modules.php?modname=' . $_REQUEST['modname'] . '&program=totals'  ) . '" method="GET">';

DrawHeader( _( 'Report Timeframe' ) . ': ' .
	PrepareDate( $start_date, '_start', false ) . ' ' . _( 'to' ) . ' ' .
	PrepareDate( $end_date, '_end', false ) . ' ' . Buttons( _( 'Go' ) ),
	// @since 12.1 Add Grade Level Breakdown
	CheckboxInput(
		$grade_level_breakdown,
		'grade_level_breakdown',
		sprintf( _( '%s Breakdown' ), _( 'Grade Level' ) ),
		'',
		true,
		'Yes',
		'No',
		false,
		// @since 12.5 CSP remove unsafe-inline Javascript
		'autocomplete="off" class="onchange-ajax-post-form"'
	) );

echo '</form>';


require_once 'ProgramFunctions/SchoolSafeMoney.fnc.php';

// Lot F4b (05/11) : deux monnaies (USD, FC) : un tableau de totaux par monnaie, jamais d'addition FC + USD.
// Sans les colonnes de monnaie (script F4a non appliqué) : un seul passage, comportement d'origine.
$totals_currencies = [ null ];

if ( SchoolSafeMoneyCurrencyReady() )
{
	$totals_currencies = [];

	$currencies_RET = DBGet( "SELECT DISTINCT CURRENCY FROM (SELECT CURRENCY
		FROM billing_payments
		WHERE SYEAR='" . UserSyear() . "'
		AND SCHOOL_ID='" . UserSchool() . "'
		AND PAYMENT_DATE BETWEEN '" . $start_date . "' AND '" . $end_date . "'
		UNION SELECT CURRENCY
		FROM billing_fees
		WHERE SYEAR='" . UserSyear() . "'
		AND SCHOOL_ID='" . UserSchool() . "'
		AND ASSIGNED_DATE BETWEEN '" . $start_date . "' AND '" . $end_date . "') c" );

	foreach ( SchoolSafeMoneyCurrencies() as $code => $label )
	{
		foreach ( (array) $currencies_RET as $currency_row )
		{
			if ( SchoolSafeMoneyCurrency( $currency_row['CURRENCY'] ) === $code )
			{
				$totals_currencies[] = $code;

				break;
			}
		}
	}

	if ( ! $totals_currencies )
	{
		$totals_currencies = [ 'USD' ];
	}
}

foreach ( $totals_currencies as $totals_currency )
{
	// Lot F4b (05/11) : filtre et format de la monnaie du tableau (vides sans les colonnes de monnaie).
	$currency_where = $totals_currency === null ? '' : " AND CURRENCY='" . $totals_currency . "'";
	$p_currency_where = $totals_currency === null ? '' : " AND p.CURRENCY='" . $totals_currency . "'";
	$f_currency_where = $totals_currency === null ? '' : " AND f.CURRENCY='" . $totals_currency . "'";
	$currency_title = $totals_currency === null ? '' : ' (' . $totals_currency . ')';

	$money = function( $amount ) use ( $totals_currency )
	{
		return $totals_currency === null ?
			Currency( $amount ) :
			SchoolSafeMoneyFormat( $amount, $totals_currency );
	};

	$billing_payments = DBGetOne( "SELECT sum(AMOUNT) AS AMOUNT
		FROM billing_payments
		WHERE SYEAR='" . UserSyear() . "'
		AND SCHOOL_ID='" . UserSchool() . "'
		AND PAYMENT_DATE BETWEEN '" . $start_date . "'
		AND '" . $end_date . "'" . $currency_where );

	$billing_fees = DBGetOne( "SELECT sum(f.AMOUNT) AS AMOUNT
		FROM billing_fees f
		WHERE  f.SYEAR='" . UserSyear() . "'
		AND f.SCHOOL_ID='" . UserSchool() . "'
		AND f.ASSIGNED_DATE BETWEEN '" . $start_date . "'
		AND '" . $end_date . "'" . $f_currency_where );

	if ( ! $grade_level_breakdown )
	{
		echo '<br />';

		PopTable( 'header', _( 'Totals' ) . $currency_title );

		echo '<table class="cellspacing-5 align-right">';

		echo '<tr><td>' . _( 'Payments' ) . ': ' .
			'</td><td>' . $money( $billing_payments ) . '</td></tr>';

		echo '<tr><td>' . _( 'Less' ) . ': ' . _( 'Fees' ) . ': ' .
			'</td><td>' . $money( $billing_fees ) . '</td></tr>';

		echo '<tr><td><b>' . _( 'Total' ) . ': ' . '</b></td>' .
			'<td><b>' . $money( ( $billing_payments - $billing_fees ) ) . '</b></td></tr>';

		echo '</table>';

		PopTable( 'footer' );
	}
	else
	{
		// @since 12.1 Add Grade Level Breakdown
		$grade_level_breakdown_sql = "SELECT sgl.ID AS GRADE_ID";

		$grade_level_breakdown_sql .= ",coalesce((SELECT sum(p.AMOUNT)
			FROM billing_payments p
			WHERE p.STUDENT_ID IN (SELECT ssm.STUDENT_ID
				FROM student_enrollment ssm
				WHERE ssm.SYEAR='" . UserSyear() . "'
				AND p.SYEAR=ssm.SYEAR
				AND ssm.GRADE_ID=sgl.ID)
			AND p.PAYMENT_DATE BETWEEN '" . $start_date . "' AND '" . $end_date . "'" . $p_currency_where . "), 0) AS TOTAL_PAYMENTS";

		$grade_level_breakdown_sql .= ",coalesce((SELECT sum(f.AMOUNT)
			FROM billing_fees f
			WHERE f.STUDENT_ID IN (SELECT ssm.STUDENT_ID
				FROM student_enrollment ssm
				WHERE ssm.SYEAR='" . UserSyear() . "'
				AND f.SYEAR=ssm.SYEAR
				AND ssm.GRADE_ID=sgl.ID)
			AND f.ASSIGNED_DATE BETWEEN '" . $start_date . "' AND '" . $end_date . "'" . $f_currency_where . "), 0) AS TOTAL_FEES";

		$grade_level_breakdown_sql .= ",(coalesce((SELECT sum(p.AMOUNT)
			FROM billing_payments p
			WHERE p.STUDENT_ID IN (SELECT ssm1.STUDENT_ID
				FROM student_enrollment ssm1
				WHERE ssm1.SYEAR='" . UserSyear() . "'
				AND p.SYEAR=ssm1.SYEAR
				AND ssm1.GRADE_ID=sgl.ID)
			AND p.PAYMENT_DATE BETWEEN '" . $start_date . "' AND '" . $end_date . "'" . $p_currency_where . "), 0)
			- coalesce((SELECT sum(f.AMOUNT)
			FROM billing_fees f
			WHERE f.STUDENT_ID IN (SELECT ssm2.STUDENT_ID
				FROM student_enrollment ssm2
				WHERE ssm2.SYEAR='" . UserSyear() . "'
				AND f.SYEAR=ssm2.SYEAR
				AND ssm2.GRADE_ID=sgl.ID)
			AND f.ASSIGNED_DATE BETWEEN '" . $start_date . "' AND '" . $end_date . "'" . $f_currency_where . "), 0)) AS TOTAL_BALANCE";

		$grade_level_breakdown_sql .= " FROM school_gradelevels sgl
			WHERE sgl.SCHOOL_ID='" . UserSchool() . "'";

		$functions = [
			'GRADE_ID' => 'GetGrade',
			'TOTAL_FEES' => 'Currency',
			'TOTAL_PAYMENTS' => 'Currency',
			'TOTAL_BALANCE' => 'Currency',
		];

		if ( $totals_currency !== null )
		{
			// Lot F4b (05/11) : montants mis en forme après lecture, dans la monnaie du tableau.
			$functions = [ 'GRADE_ID' => 'GetGrade' ];
		}

		$grade_level_breakdown_RET = DBGet( $grade_level_breakdown_sql, $functions );

		foreach ( (array) $grade_level_breakdown_RET as $i => $grade_level_row )
		{
			if ( $totals_currency === null )
			{
				break;
			}

			foreach ( [ 'TOTAL_FEES', 'TOTAL_PAYMENTS', 'TOTAL_BALANCE' ] as $amount_column )
			{
				$grade_level_breakdown_RET[ $i ][ $amount_column ] = $money( $grade_level_row[ $amount_column ] );
			}
		}

		$columns = [
			'GRADE_ID' => _( 'Grade Level' ),
			'TOTAL_FEES' => _( 'Total from Fees' ) . $currency_title,
			'TOTAL_PAYMENTS' => _( 'Total from Payments' ) . $currency_title,
			'TOTAL_BALANCE' => _( 'Balance' ) . $currency_title,
		];

		$link['add']['html'] = [
			'GRADE_ID' => _( 'Total' ),
			'TOTAL_FEES' => '<b>' . $money( $billing_fees ) . '</b>',
			'TOTAL_PAYMENTS' => '<b>' . $money( $billing_payments ) . '</b>',
			'TOTAL_BALANCE' => '<b>' . $money( $billing_payments - $billing_fees ) . '</b>',
		];

		// Force display of $link['add'] on PDF, Export or if not allowed to edit
		$options = [ 'add' => true ];

		ListOutput(
			$grade_level_breakdown_RET,
			$columns,
			'Grade Level',
			'Grade Levels',
			$link,
			[],
			$options
		);
	}
}
