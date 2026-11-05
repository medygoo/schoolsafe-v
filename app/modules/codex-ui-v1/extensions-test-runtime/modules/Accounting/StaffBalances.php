<?php
DrawHeader( ProgramTitle() );

SchoolSafeCard(
	'report',
	'Reports',
	'Rapports',
	'Staff balances',
	'Soldes du personnel',
	'This page lists what the school owes each staff member.',
	'Cette page liste ce que l’école doit à chaque membre du personnel.'
);


// @since 12.9 SQL performance: replace subqueries with LEFT JOINs
// SQL remove SCHOOL_ID join: fix when "Search All Schools" is checked
$extra['SELECT'] = ',COALESCE(f.TOTAL,0)-COALESCE(p.TOTAL,0) AS BALANCE';

// F10 (lot F1, 05/11) : salaires et paiements de l'école en cours seulement.
$extra['FROM'] = ' LEFT JOIN (SELECT STAFF_ID,SYEAR,SUM(AMOUNT) AS TOTAL
	FROM accounting_salaries
	WHERE SCHOOL_ID=' . (int) UserSchool() . '
	GROUP BY STAFF_ID,SYEAR) f ON f.STAFF_ID=s.STAFF_ID
	AND f.SYEAR=s.SYEAR
LEFT JOIN (SELECT STAFF_ID,SYEAR,SUM(AMOUNT) AS TOTAL
	FROM accounting_payments
	WHERE SCHOOL_ID=' . (int) UserSchool() . '
	GROUP BY STAFF_ID,SYEAR) p ON p.STAFF_ID=s.STAFF_ID
	AND p.SYEAR=s.SYEAR';

require_once 'ProgramFunctions/SchoolSafeMoney.fnc.php';

if ( SchoolSafeMoneyAccountingReady() )
{
	// Lot F4c (05/11) : solde par monnaie (USD, FC), jamais additionné. La colonne reçoit « USD|FC »
	// (FC vide s'il n'y a aucun mouvement en FC), mis en forme par _makeCurrency() ; le total est fait par monnaie.
	// Le tri sur la colonne Solde se fait sur ce texte et n'est donc plus numérique.
	$extra['SELECT'] = ",CONCAT(COALESCE(b.USD::text,''),'|',COALESCE(b.FC::text,'')) AS BALANCE";

	$extra['FROM'] = ' LEFT JOIN (SELECT STAFF_ID,SYEAR,
		SUM(CASE WHEN CURRENCY=\'USD\' THEN AMOUNT END) AS USD,
		SUM(CASE WHEN CURRENCY=\'FC\' THEN AMOUNT END) AS FC
		FROM (SELECT STAFF_ID,SYEAR,CURRENCY,AMOUNT FROM accounting_salaries
			WHERE SCHOOL_ID=' . (int) UserSchool() . '
			UNION ALL SELECT STAFF_ID,SYEAR,CURRENCY,-AMOUNT FROM accounting_payments
			WHERE SCHOOL_ID=' . (int) UserSchool() . ' AND STAFF_ID IS NOT NULL) m
		GROUP BY STAFF_ID,SYEAR) b ON b.STAFF_ID=s.STAFF_ID
		AND b.SYEAR=s.SYEAR';
}

$extra['columns_after'] = [ 'BALANCE' => _( 'Balance' ) ];

$extra['link']['FULL_NAME'] = false;
$extra['new'] = true;
$extra['functions'] = [ 'BALANCE' => '_makeCurrency' ];

if ( User( 'PROFILE' ) === 'parent' || User( 'PROFILE' ) === 'teacher' )
{
	$_REQUEST['search_modfunc'] = 'list';
}

// Fix SQL error table name "sam" specified more than once
$extra2 = $extra;

if ( $_REQUEST['search_modfunc'] === 'list' )
{
	// Call GetStaffList() only so we calculate the $total.
	GetStaffList( $extra );
}

// @since 10.0 Add Total sum of balances.
$extra2['link']['add']['html'] = [
	'FULL_NAME' => '<b>' . _( 'Total' ) . '</b>',
	'BALANCE' => '<b>' . Currency( ( isset( $total ) ? $total * -1 : 0 ) ) . '</b>',
];

if ( SchoolSafeMoneyAccountingReady() )
{
	// Lot F4c (05/11) : total des soldes par monnaie (USD d'abord, USD seul si aucun montant).
	$balance_totals = [];

	foreach ( array_keys( SchoolSafeMoneyCurrencies() ) as $currency )
	{
		if ( isset( $total_by_currency[ $currency ] ) )
		{
			$balance_totals[ $currency ] = $total_by_currency[ $currency ] * -1;
		}
	}

	$balance_totals = $balance_totals ?: [ 'USD' => 0 ];

	$extra2['link']['add']['html']['BALANCE'] = '<b>' .
		implode( '<br>', array_map( 'SchoolSafeMoneyFormat', $balance_totals, array_keys( $balance_totals ) ) ) .
		'</b>';
}

Search( 'staff_id', $extra2 );

/**
 * @param $value
 * @param $column
 */
function _makeCurrency( $value, $column )
{
	global $total, $total_by_currency;

	if ( strpos( (string) $value, '|' ) !== false )
	{
		// Lot F4c (05/11) : « USD|FC » (partie vide = aucun mouvement dans cette monnaie) ; une ligne par monnaie.
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
