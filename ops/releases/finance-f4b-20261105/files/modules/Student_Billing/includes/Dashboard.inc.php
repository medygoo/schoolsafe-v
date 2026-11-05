<?php
/**
 * Student Billing Dashboard module
 *
 * @package RosarioSIS
 * @subpackage modules
 */

/**
 * Dashboard Default Student Billing module
 *
 * @since 4.0
 *
 * @param  boolean $export   Exporting data, defaults to false. Optional.
 * @return string  Dashboard module HTML.
 */
function DashboardDefaultStudentBilling()
{
	require_once 'ProgramFunctions/DashboardModule.fnc.php';

	$profile = User( 'PROFILE' );

	$data = '';

	if ( $profile === 'admin' )
	{
		$data = DashboardStudentBillingAdmin();
	}

	return DashboardModule( 'Student_Billing', $data );
}

if ( ! function_exists( 'DashboardStudentBillingAdmin' ) )
{
	/**
	 * Dashboard data
	 * Student Billing module & admin profile
	 *
	 * @since 4.0
	 * @since 9.3 SQL use CAST(X AS char(X)) instead of to_char() for MySQL compatibility
	 * @since 12.4 Hide totals if user has no access to the Daily Transactions program
	 *
	 * @return array Dashboard data
	 */
	function DashboardStudentBillingAdmin()
	{
		if ( ! AllowUse( 'Student_Billing/DailyTransactions.php' ) )
		{
			// Hide totals if user has no access to the Daily Transactions program
			return [];
		}

		require_once 'ProgramFunctions/SchoolSafeMoney.fnc.php';

		if ( SchoolSafeMoneyCurrencyReady() )
		{
			// Lot F4b (05/11) : deux monnaies : totaux par mois ET par monnaie, jamais d'addition FC + USD.
			return DashboardStudentBillingAdminByCurrency();
		}

		$balance = 0;

		// Limit Results to Months between User MP Start & End Date.
		$fees_RET = DBGet( "SELECT CAST(ASSIGNED_DATE AS char(7)) AS YEAR_MONTH_DATE,
			SUM(AMOUNT) AS TOTAL_FEES
			FROM billing_fees
			WHERE SYEAR='" . UserSyear() . "'
			AND SCHOOL_ID='" . UserSchool() . "'
			GROUP BY YEAR_MONTH_DATE
			ORDER BY YEAR_MONTH_DATE DESC
			LIMIT 3", [], [ 'YEAR_MONTH_DATE' ] );

		$payments_RET = DBGet( "SELECT CAST(PAYMENT_DATE AS char(7)) AS YEAR_MONTH_DATE,
			SUM(AMOUNT) AS TOTAL_PAYMENTS
			FROM billing_payments
			WHERE SYEAR='" . UserSyear() . "'
			AND SCHOOL_ID='" . UserSchool() . "'
			GROUP BY YEAR_MONTH_DATE
			ORDER BY YEAR_MONTH_DATE DESC
			LIMIT 3", [], [ 'YEAR_MONTH_DATE' ] );

		$fees_payments_RET = array_replace_recursive( $fees_RET, $payments_RET );

		krsort( $fees_payments_RET );

		if ( ! empty( $fees_payments_RET[date( 'Y-m' )] ) )
		{
			$fees_payments_month = $fees_payments_RET[date( 'Y-m' )][1];

			// Student Billing Balance.

			if ( ! empty( $fees_payments_month['TOTAL_FEES'] ) )
			{
				$balance = $fees_payments_month['TOTAL_FEES'];
			}

			if ( ! empty( $fees_payments_month['TOTAL_PAYMENTS'] ) )
			{
				$balance -= $fees_payments_month['TOTAL_PAYMENTS'];
			}
		}

		$billing_data[_( 'Balance' )] = Currency( $balance, 'CR' );

		foreach ( (array) $fees_payments_RET as $year_month => $fees_payments )
		{
			// Remove dummy day from proper date.
			// @since 9.0 Fix PHP8.1 deprecated strftime() use strftime_compat() instead
			$proper_month_year = ucfirst( strftime_compat(
				trim( str_replace( [ '%d', '//' ], [ '', '/'], Preferences( 'DATE' ) ), '-./ ' ),
				strtotime( $year_month . '-28' )
			) );

			$month_balance = 0;

			$billing_data_month = null;

			if ( ! empty( $fees_payments[1]['TOTAL_FEES'] ) )
			{
				$month_balance = $fees_payments[1]['TOTAL_FEES'];

				// Fees by month.
				$billing_data_month .= NoInput(
					Currency( $fees_payments[1]['TOTAL_FEES'] ),
					_( 'Fees' )
				) . '<br />';
			}

			if ( ! empty( $fees_payments[1]['TOTAL_PAYMENTS'] ) )
			{
				$month_balance -= $fees_payments[1]['TOTAL_PAYMENTS'];

				// Payments by month.
				$billing_data_month .= NoInput(
					Currency( $fees_payments[1]['TOTAL_PAYMENTS'] ),
					_( 'Payments' )
				) . '<br />';
			}

			// Month Balance.
			$month_key = NoInput( Currency( $month_balance, 'CR' ), $proper_month_year );

			// Remove last <br />.
			$billing_data[$month_key] = mb_substr( $billing_data_month, 0, ( mb_strlen( $billing_data_month ) - 6 ) );
		}

		$data = [];

		if ( $balance
			|| count( $billing_data ) > 1 )
		{
			$data = $billing_data;
		}

		return $data;
	}
}

if ( ! function_exists( 'DashboardStudentBillingAdminByCurrency' ) )
{
	/**
	 * Lot F4b (05/11) : même tableau de bord, par monnaie (USD d'abord, FC seulement s'il y a des montants en FC).
	 * Trois derniers mois ; solde du mois en cours par monnaie.
	 *
	 * @return array Dashboard data
	 */
	function DashboardStudentBillingAdminByCurrency()
	{
		$months_RET = DBGet( "SELECT YEAR_MONTH_DATE,CURRENCY,
			SUM(FEES) AS TOTAL_FEES,SUM(PAYMENTS) AS TOTAL_PAYMENTS
			FROM (SELECT CAST(ASSIGNED_DATE AS char(7)) AS YEAR_MONTH_DATE,CURRENCY,AMOUNT AS FEES,0 AS PAYMENTS
				FROM billing_fees
				WHERE SYEAR='" . UserSyear() . "'
				AND SCHOOL_ID='" . UserSchool() . "'
			UNION ALL SELECT CAST(PAYMENT_DATE AS char(7)),CURRENCY,0,AMOUNT
				FROM billing_payments
				WHERE SYEAR='" . UserSyear() . "'
				AND SCHOOL_ID='" . UserSchool() . "') t
			GROUP BY YEAR_MONTH_DATE,CURRENCY" );

		$months = [];

		foreach ( (array) $months_RET as $row )
		{
			$months[ $row['YEAR_MONTH_DATE'] ][ SchoolSafeMoneyCurrency( $row['CURRENCY'] ) ] = [
				'FEES' => (float) $row['TOTAL_FEES'],
				'PAYMENTS' => (float) $row['TOTAL_PAYMENTS'],
			];
		}

		krsort( $months );

		$months = array_slice( $months, 0, 3, true );

		// Monnaies dans l'ordre d'affichage (USD d'abord).
		$ordered = function( $by_currency )
		{
			$out = [];

			foreach ( array_keys( SchoolSafeMoneyCurrencies() ) as $currency )
			{
				if ( isset( $by_currency[ $currency ] ) )
				{
					$out[ $currency ] = $by_currency[ $currency ];
				}
			}

			return $out;
		};

		$balances = [ 'USD' => 0 ];

		$has_balance = false;

		if ( ! empty( $months[ date( 'Y-m' ) ] ) )
		{
			$balances = [];

			foreach ( $ordered( $months[ date( 'Y-m' ) ] ) as $currency => $totals )
			{
				$balances[ $currency ] = $totals['FEES'] - $totals['PAYMENTS'];

				$has_balance = $has_balance || $balances[ $currency ];
			}
		}

		$billing_data[_( 'Balance' )] = SchoolSafeMoneyBalancesText( $balances );

		foreach ( $months as $year_month => $by_currency )
		{
			// Remove dummy day from proper date.
			$proper_month_year = ucfirst( strftime_compat(
				trim( str_replace( [ '%d', '//' ], [ '', '/'], Preferences( 'DATE' ) ), '-./ ' ),
				strtotime( $year_month . '-28' )
			) );

			$month_balances = [];

			$billing_data_month = [];

			foreach ( $ordered( $by_currency ) as $currency => $totals )
			{
				$month_balances[ $currency ] = $totals['FEES'] - $totals['PAYMENTS'];

				if ( $totals['FEES'] )
				{
					$billing_data_month[] = NoInput(
						SchoolSafeMoneyFormat( $totals['FEES'], $currency ),
						_( 'Fees' ) . ' (' . $currency . ')'
					);
				}

				if ( $totals['PAYMENTS'] )
				{
					$billing_data_month[] = NoInput(
						SchoolSafeMoneyFormat( $totals['PAYMENTS'], $currency ),
						_( 'Payments' ) . ' (' . $currency . ')'
					);
				}
			}

			// Month Balance, per currency.
			$month_key = NoInput( SchoolSafeMoneyBalancesText( $month_balances ), $proper_month_year );

			$billing_data[$month_key] = implode( '<br />', $billing_data_month );
		}

		$data = [];

		if ( $has_balance
			|| count( $billing_data ) > 1 )
		{
			$data = $billing_data;
		}

		return $data;
	}
}
