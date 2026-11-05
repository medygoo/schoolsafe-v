<?php

DrawHeader( ProgramTitle() );

SchoolSafeCard(
	'report',
	'Reports',
	'Rapports',
	'Student balances',
	'Soldes des élèves',
	'This page lists what each student still owes.',
	'Cette page liste ce que chaque élève doit encore.'
);


$_REQUEST['cumulative_balance'] = issetVal( $_REQUEST['cumulative_balance'] );

// @since 12.9 SQL performance: replace subqueries with LEFT JOINs
$extra['SELECT'] = ',COALESCE(f.TOTAL,0)-COALESCE(p.TOTAL,0) AS BALANCE';

// F10 (lot F1, 05/11) : le solde ne compte que les frais et paiements de l'école de l'inscription.
$extra['FROM'] = ' LEFT JOIN (SELECT STUDENT_ID,SCHOOL_ID,SYEAR,SUM(AMOUNT) AS TOTAL
	FROM billing_fees
	GROUP BY STUDENT_ID,SCHOOL_ID,SYEAR) f ON f.STUDENT_ID=ssm.STUDENT_ID
	AND f.SCHOOL_ID=ssm.SCHOOL_ID
	AND f.SYEAR=ssm.SYEAR
LEFT JOIN (SELECT STUDENT_ID,SCHOOL_ID,SYEAR,SUM(AMOUNT) AS TOTAL
	FROM billing_payments
	GROUP BY STUDENT_ID,SCHOOL_ID,SYEAR) p ON p.STUDENT_ID=ssm.STUDENT_ID
	AND p.SCHOOL_ID=ssm.SCHOOL_ID
	AND p.SYEAR=ssm.SYEAR';

if ( $_REQUEST['cumulative_balance'] === 'Y' )
{
	// @since 10.3 Add "Cumulative Balance over school years" checkbox.
	$extra['FROM'] = ' LEFT JOIN (SELECT STUDENT_ID,SCHOOL_ID,SUM(AMOUNT) AS TOTAL
		FROM billing_fees
		GROUP BY STUDENT_ID,SCHOOL_ID) f ON f.STUDENT_ID=ssm.STUDENT_ID
		AND f.SCHOOL_ID=ssm.SCHOOL_ID
	LEFT JOIN (SELECT STUDENT_ID,SCHOOL_ID,SUM(AMOUNT) AS TOTAL
		FROM billing_payments
		GROUP BY STUDENT_ID,SCHOOL_ID) p ON p.STUDENT_ID=ssm.STUDENT_ID
		AND p.SCHOOL_ID=ssm.SCHOOL_ID';
}

require_once 'ProgramFunctions/SchoolSafeMoney.fnc.php';

if ( SchoolSafeMoneyCurrencyReady() )
{
	// Lot F4b (05/11) : solde par monnaie (USD, FC), jamais additionné. La colonne reçoit « USD|FC »
	// (FC vide s'il n'y a aucun mouvement en FC), mis en forme par _makeCurrency() ; le total est fait par monnaie.
	// Le tri sur la colonne Solde se fait sur ce texte et n'est donc plus numérique.
	$balance_year = $_REQUEST['cumulative_balance'] === 'Y' ? '' : ',SYEAR';

	$extra['SELECT'] = ",CONCAT(COALESCE(b.USD::text,''),'|',COALESCE(b.FC::text,'')) AS BALANCE";

	$extra['FROM'] = ' LEFT JOIN (SELECT STUDENT_ID,SCHOOL_ID' . $balance_year . ',
		SUM(CASE WHEN CURRENCY=\'USD\' THEN AMOUNT END) AS USD,
		SUM(CASE WHEN CURRENCY=\'FC\' THEN AMOUNT END) AS FC
		FROM (SELECT STUDENT_ID,SCHOOL_ID,SYEAR,CURRENCY,AMOUNT FROM billing_fees
			UNION ALL SELECT STUDENT_ID,SCHOOL_ID,SYEAR,CURRENCY,-AMOUNT FROM billing_payments) m
		GROUP BY STUDENT_ID,SCHOOL_ID' . $balance_year . ') b ON b.STUDENT_ID=ssm.STUDENT_ID
		AND b.SCHOOL_ID=ssm.SCHOOL_ID' .
		( $balance_year ? ' AND b.SYEAR=ssm.SYEAR' : '' );
}

$extra['columns_after'] = [ 'BALANCE' => _( 'Balance' ) ];

$extra['link']['FULL_NAME'] = false;
$extra['new'] = true;
$extra['functions'] = [ 'BALANCE' => '_makeCurrency' ];

Widgets( 'balance' );

if ( User( 'PROFILE' ) === 'parent' || User( 'PROFILE' ) === 'student' )
{
	$_REQUEST['search_modfunc'] = 'list';
}

// Fix SQL error table name "sam" specified more than once
$extra2 = $extra;

if ( $_REQUEST['search_modfunc'] === 'list' )
{
	// @since 10.3 Add "Cumulative Balance over school years" checkbox.
	DrawHeader( CheckBoxOnclick( 'cumulative_balance', _( 'Cumulative Balance over school years' ) ) );

	// Call GetStuList() only so we calculate the $total.
	GetStuList( $extra );

	// Fix Search terms displayed twice
	$_ROSARIO['SearchTerms'] = '';
}

// @since 9.0 Add Total sum of balances.
$extra2['link']['add']['html'] = [
	'FULL_NAME' => '<b>' . _( 'Total' ) . '</b>',
	'BALANCE' => '<b>' . Currency( ( isset( $total ) ? $total * -1 : 0 ) ) . '</b>',
];

if ( SchoolSafeMoneyCurrencyReady() )
{
	// Lot F4b (05/11) : total des soldes par monnaie (USD d'abord, USD seul si aucun montant).
	$balance_totals = [];

	foreach ( array_keys( SchoolSafeMoneyCurrencies() ) as $currency )
	{
		if ( isset( $total_by_currency[ $currency ] ) )
		{
			$balance_totals[ $currency ] = $total_by_currency[ $currency ] * -1;
		}
	}

	$extra2['link']['add']['html']['BALANCE'] = '<b>' .
		implode( '<br>', array_map( 'SchoolSafeMoneyFormat', $balance_totals ?: [ 'USD' => 0 ], array_keys( $balance_totals ?: [ 'USD' => 0 ] ) ) ) .
		'</b>';
}

Search( 'student_id', $extra2 );

/**
 * @param $value
 * @param $column
 */
function _makeCurrency( $value, $column )
{
	global $total, $total_by_currency;

	if ( strpos( (string) $value, '|' ) !== false )
	{
		// Lot F4b (05/11) : « USD|FC » (partie vide = aucun mouvement dans cette monnaie) ; une ligne par monnaie.
		list( $usd, $fc ) = explode( '|', (string) $value, 2 );

		$lines = [];

		foreach ( [ 'USD' => $usd, 'FC' => $fc ] as $currency => $amount )
		{
			if ( $amount === '' && ( $currency === 'FC' || $fc !== '' ) )
			{
				continue;
			}

			if ( ! isset( $total_by_currency[ $currency ] ) )
			{
				$total_by_currency[ $currency ] = 0;
			}

			$total_by_currency[ $currency ] += (float) $amount;

			$lines[] = SchoolSafeMoneyFormat( (float) $amount * -1, $currency );
		}

		return implode( '<br>', $lines );
	}

	if ( ! isset( $total ) )
	{
		$total = 0;
	}

	$total += (float) $value;

	return Currency( $value * -1 );
}
