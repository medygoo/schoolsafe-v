<?php
/**
 * Accounting Dashboard module
 *
 * @package RosarioSIS
 * @subpackage modules
 */

/**
 * Dashboard Default Accounting module
 *
 * @since 4.0
 *
 * @param  boolean $export   Exporting data, defaults to false. Optional.
 * @return string  Dashboard module HTML.
 */
function DashboardDefaultAccounting()
{
	require_once 'ProgramFunctions/DashboardModule.fnc.php';

	$profile = User( 'PROFILE' );

	$data = '';

	if ( $profile === 'admin' )
	{
		$data = DashboardAccountingAdmin();
	}

	return DashboardModule( 'Accounting', $data );
}

if ( ! function_exists( 'DashboardAccountingAdmin' ) )
{
	/**
	 * Dashboard data
	 * Accounting module & admin profile
	 *
	 * @since 4.0
	 * @since 9.3 SQL use CAST(X AS char(X)) instead of to_char() for MySQL compatibility
	 * @since 12.4 Hide totals if user has no access to the Daily Transactions program
	 *
	 * @return array Dashboard data
	 */
	function DashboardAccountingAdmin()
	{
		if ( ! AllowUse( 'Accounting/DailyTransactions.php' ) )
		{
			// Hide totals if user has no access to the Daily Transactions program
			return [];
		}

		require_once 'ProgramFunctions/SchoolSafeMoney.fnc.php';

		if ( SchoolSafeMoneyAccountingReady() )
		{
			// Lot F4c (05/11) : solde général et mois par monnaie (USD, FC), jamais additionnés.
			return DashboardAccountingAdminByCurrency();
		}

		$general_balance = 0;

		$incomes_RET = DBGet( "SELECT CAST(ASSIGNED_DATE AS char(7)) AS YEAR_MONTH_DATE,
			SUM(AMOUNT) AS TOTAL_INCOMES
			FROM accounting_incomes
			WHERE SYEAR='" . UserSyear() . "'
			AND SCHOOL_ID='" . UserSchool() . "'
			GROUP BY YEAR_MONTH_DATE
			ORDER BY YEAR_MONTH_DATE DESC
			LIMIT 2", [], [ 'YEAR_MONTH_DATE' ] );

		$expenses_RET = DBGet( "SELECT CAST(PAYMENT_DATE AS char(7)) AS YEAR_MONTH_DATE,
			SUM(CASE WHEN STAFF_ID IS NULL THEN AMOUNT END) AS TOTAL_EXPENSES
			FROM accounting_payments
			WHERE SYEAR='" . UserSyear() . "'
			AND SCHOOL_ID='" . UserSchool() . "'
			GROUP BY YEAR_MONTH_DATE
			ORDER BY YEAR_MONTH_DATE DESC
			LIMIT 2", [], [ 'YEAR_MONTH_DATE' ] );

		$staff_payments_RET = DBGet( "SELECT CAST(PAYMENT_DATE AS char(7)) AS YEAR_MONTH_DATE,
			SUM(CASE WHEN STAFF_ID IS NOT NULL THEN AMOUNT END) AS TOTAL_STAFF
			FROM accounting_payments
			WHERE SYEAR='" . UserSyear() . "'
			AND SCHOOL_ID='" . UserSchool() . "'
			GROUP BY YEAR_MONTH_DATE
			ORDER BY YEAR_MONTH_DATE DESC
			LIMIT 2", [], [ 'YEAR_MONTH_DATE' ] );

		require_once 'ProgramFunctions/SchoolSafeMoney.fnc.php';

		// Lot F4b (05/11) : le solde général est en USD ; seuls les paiements des élèves en USD y entrent.
		// Les paiements en FC sont lus à part (TOTAL_STUDENT_PAYMENTS_FC) et affichés sur une ligne séparée.
		$student_payments_sql = SchoolSafeMoneyCurrencyReady() ?
			"SUM(CASE WHEN CURRENCY='USD' THEN AMOUNT END) AS TOTAL_STUDENT_PAYMENTS,
			SUM(CASE WHEN CURRENCY='FC' THEN AMOUNT END) AS TOTAL_STUDENT_PAYMENTS_FC" :
			"SUM(AMOUNT) AS TOTAL_STUDENT_PAYMENTS";

		$student_payments_RET = DBGet( "SELECT CAST(PAYMENT_DATE AS char(7)) AS YEAR_MONTH_DATE,
			" . $student_payments_sql . "
			FROM billing_payments
			WHERE SYEAR='" . UserSyear() . "'
			AND SCHOOL_ID='" . UserSchool() . "'
			GROUP BY YEAR_MONTH_DATE
			ORDER BY YEAR_MONTH_DATE DESC
			LIMIT 2", [], [ 'YEAR_MONTH_DATE' ] );

		$accounting_RET = array_replace_recursive( $incomes_RET, $expenses_RET, $staff_payments_RET, $student_payments_RET );

		krsort( $accounting_RET );

		if ( ! empty( $accounting_RET[date( 'Y-m' )] ) )
		{
			$accounting_month = $accounting_RET[date( 'Y-m' )][1];

			// Accounting General Balance.

			if ( ! empty( $accounting_month['TOTAL_INCOMES'] ) )
			{
				$general_balance = $accounting_month['TOTAL_INCOMES'];
			}

			if ( ! empty( $accounting_month['TOTAL_EXPENSES'] ) )
			{
				$general_balance -= $accounting_month['TOTAL_EXPENSES'];
			}

			if ( ! empty( $accounting_month['TOTAL_STAFF'] ) )
			{
				$general_balance -= $accounting_month['TOTAL_STAFF'];
			}

			if ( ! empty( $accounting_month['TOTAL_STUDENT_PAYMENTS'] ) )
			{
				$general_balance += $accounting_month['TOTAL_STUDENT_PAYMENTS'];
			}
		}

		$accounting_data[_( 'General Balance' )] = Currency( $general_balance );

		if ( ! empty( $accounting_RET[date( 'Y-m' )][1]['TOTAL_STUDENT_PAYMENTS_FC'] ) )
		{
			// Lot F4b (05/11) : paiements des élèves en FC du mois, hors du solde en USD.
			$accounting_data['Dont paiements des élèves en FC'] = SchoolSafeMoneyFormat(
				$accounting_RET[date( 'Y-m' )][1]['TOTAL_STUDENT_PAYMENTS_FC'],
				'FC'
			) . ', non inclus dans le solde en USD';
		}

		foreach ( (array) $accounting_RET as $year_month => $accounting )
		{
			// Remove dummy day from proper date.
			// @since 9.0 Fix PHP8.1 deprecated strftime() use strftime_compat() instead
			$proper_month_year = ucfirst( strftime_compat(
				trim( str_replace( [ '%d', '//' ], [ '', '/'], Preferences( 'DATE' ) ), '-./ ' ),
				strtotime( $year_month . '-28' )
			) );

			$month_balance = 0;

			$accounting_data_month = null;

			if ( ! empty( $accounting[1]['TOTAL_INCOMES'] ) )
			{
				$month_balance = $accounting[1]['TOTAL_INCOMES'];

				// Incomes by month.
				$accounting_data_month .= NoInput(
					Currency( $accounting[1]['TOTAL_INCOMES'] ),
					_( 'Incomes' )
				) . '<br />';
			}

			if ( ! empty( $accounting[1]['TOTAL_EXPENSES'] ) )
			{
				$month_balance -= $accounting[1]['TOTAL_EXPENSES'];

				// Student Payments by month.
				$accounting_data_month .= NoInput(
					Currency( $accounting[1]['TOTAL_EXPENSES'] ),
					_( 'Expenses' )
				) . '<br />';
			}

			if ( ! empty( $accounting[1]['TOTAL_STAFF'] ) )
			{
				$month_balance -= $accounting[1]['TOTAL_STAFF'];

				// Student Payments by month.
				$accounting_data_month .= NoInput(
					Currency( $accounting[1]['TOTAL_STAFF'] ),
					_( 'Staff Payments' )
				) . '<br />';
			}

			if ( ! empty( $accounting[1]['TOTAL_STUDENT_PAYMENTS'] ) )
			{
				$month_balance += $accounting[1]['TOTAL_STUDENT_PAYMENTS'];

				// Student Payments by month.
				$accounting_data_month .= NoInput(
					Currency( $accounting[1]['TOTAL_STUDENT_PAYMENTS'] ),
					_( 'Student Payments' )
				) . '<br />';
			}

			if ( ! empty( $accounting[1]['TOTAL_STUDENT_PAYMENTS_FC'] ) )
			{
				// Lot F4b (05/11) : paiements des élèves en FC, non ajoutés au solde du mois (en USD).
				$accounting_data_month .= NoInput(
					SchoolSafeMoneyFormat( $accounting[1]['TOTAL_STUDENT_PAYMENTS_FC'], 'FC' ),
					'Dont paiements des élèves en FC, non inclus dans le solde en USD'
				) . '<br />';
			}

			$month_key = NoInput( Currency( $month_balance ), $proper_month_year );

			// Remove last <br />.
			$accounting_data[$month_key] = mb_substr( $accounting_data_month, 0, ( mb_strlen( $accounting_data_month ) - 6 ) );
		}

		$data = [];

		if ( $general_balance
			|| count( $accounting_data ) > 1 )
		{
			$data = $accounting_data;
		}

		return $data;
	}
}

if ( ! function_exists( 'DashboardAccountingAdminByCurrency' ) )
{
	/**
	 * Lot F4c (05/11) : données du tableau de bord Comptabilité par monnaie (USD, FC).
	 * Pour chaque monnaie : recettes + paiements des élèves - dépenses - paiements du personnel.
	 *
	 * @return array Dashboard data
	 */
	function DashboardAccountingAdminByCurrency()
	{
		$scope = "SYEAR='" . UserSyear() . "' AND SCHOOL_ID='" . UserSchool() . "'";

		$rows = DBGet( "SELECT YEAR_MONTH_DATE,CURRENCY,
			SUM(CASE WHEN K='I' THEN AMOUNT ELSE 0 END) AS TOTAL_INCOMES,
			SUM(CASE WHEN K='E' THEN AMOUNT ELSE 0 END) AS TOTAL_EXPENSES,
			SUM(CASE WHEN K='S' THEN AMOUNT ELSE 0 END) AS TOTAL_STAFF,
			SUM(CASE WHEN K='P' THEN AMOUNT ELSE 0 END) AS TOTAL_STUDENT_PAYMENTS
			FROM (SELECT CAST(ASSIGNED_DATE AS char(7)) AS YEAR_MONTH_DATE,CURRENCY,'I' AS K,AMOUNT
				FROM accounting_incomes WHERE " . $scope . "
			UNION ALL SELECT CAST(PAYMENT_DATE AS char(7)),CURRENCY,CASE WHEN STAFF_ID IS NULL THEN 'E' ELSE 'S' END,AMOUNT
				FROM accounting_payments WHERE " . $scope . "
			UNION ALL SELECT CAST(PAYMENT_DATE AS char(7)),CURRENCY,'P',AMOUNT
				FROM billing_payments WHERE " . $scope . ") t
			GROUP BY YEAR_MONTH_DATE,CURRENCY" );

		$months = [];

		foreach ( (array) $rows as $row )
		{
			$months[ $row['YEAR_MONTH_DATE'] ][ SchoolSafeMoneyCurrency( $row['CURRENCY'] ) ] = $row;
		}

		krsort( $months );

		// Les deux derniers mois, comme le tableau de bord d'origine.
		$months = array_slice( $months, 0, 2, true );

		$balances = function( $by_currency )
		{
			$balances = [];

			foreach ( array_keys( SchoolSafeMoneyCurrencies() ) as $currency )
			{
				if ( isset( $by_currency[ $currency ] ) )
				{
					$row = $by_currency[ $currency ];

					$balances[ $currency ] = (float) $row['TOTAL_INCOMES'] + (float) $row['TOTAL_STUDENT_PAYMENTS']
						- (float) $row['TOTAL_EXPENSES'] - (float) $row['TOTAL_STAFF'];
				}
			}

			return $balances ? $balances : [ 'USD' => 0.0 ];
		};

		$general_balance = isset( $months[ date( 'Y-m' ) ] ) ? $balances( $months[ date( 'Y-m' ) ] ) : [ 'USD' => 0.0 ];

		$accounting_data = [ _( 'General Balance' ) => SchoolSafeMoneyBalancesText( $general_balance ) ];

		$labels = [
			'TOTAL_INCOMES' => _( 'Incomes' ),
			'TOTAL_EXPENSES' => _( 'Expenses' ),
			'TOTAL_STAFF' => _( 'Staff Payments' ),
			'TOTAL_STUDENT_PAYMENTS' => _( 'Student Payments' ),
		];

		foreach ( $months as $year_month => $by_currency )
		{
			// Remove dummy day from proper date.
			$proper_month_year = ucfirst( strftime_compat(
				trim( str_replace( [ '%d', '//' ], [ '', '/'], Preferences( 'DATE' ) ), '-./ ' ),
				strtotime( $year_month . '-28' )
			) );

			$lines = [];

			foreach ( array_keys( SchoolSafeMoneyCurrencies() ) as $currency )
			{
				if ( ! isset( $by_currency[ $currency ] ) )
				{
					continue;
				}

				foreach ( $labels as $column => $label )
				{
					if ( (float) $by_currency[ $currency ][ $column ] )
					{
						$lines[] = NoInput( SchoolSafeMoneyFormat( $by_currency[ $currency ][ $column ], $currency ), $label );
					}
				}
			}

			$month_key = NoInput( SchoolSafeMoneyBalancesText( $balances( $by_currency ) ), $proper_month_year );

			$accounting_data[ $month_key ] = implode( '<br />', $lines );
		}

		if ( array_filter( $general_balance )
			|| count( $accounting_data ) > 1 )
		{
			return $accounting_data;
		}

		return [];
	}
}
