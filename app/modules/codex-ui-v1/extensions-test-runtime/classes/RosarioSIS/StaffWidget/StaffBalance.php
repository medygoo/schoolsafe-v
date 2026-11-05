<?php
/**
 * Staff Payroll Balance (Accounting) Staff Widget class
 *
 * @since 12.7
 *
 * @package RosarioSIS
 */

namespace RosarioSIS\StaffWidget;

class StaffBalance implements \RosarioSIS\StaffWidget
{
	function canBuild( $modules )
	{
		return $modules['Accounting'] && AllowUse( 'Accounting/StaffBalances.php' );
	}

	function extra( $extra )
	{
		if ( ! isset( $_REQUEST['balance_low'] )
			|| ! is_numeric( $_REQUEST['balance_low'] )
			|| ! isset( $_REQUEST['balance_high'] )
			|| ! is_numeric( $_REQUEST['balance_high'] ) )
		{
			return $extra;
		}

		if ( $_REQUEST['balance_low'] > $_REQUEST['balance_high'] )
		{
			$temp = $_REQUEST['balance_high'];

			$_REQUEST['balance_high'] = $_REQUEST['balance_low'];

			$_REQUEST['balance_low'] = $temp;
		}

		// Lot F4c (05/11) : avec deux monnaies, la recherche porte sur le solde de paie en USD seulement
		// (un intervalle de montants n'a pas de sens si FC et USD sont additionnés).
		require_once 'ProgramFunctions/SchoolSafeMoney.fnc.php';

		$usd_only = SchoolSafeMoneyAccountingReady();

		$extra['WHERE'] .= " AND (coalesce((SELECT sum(p.AMOUNT)
				FROM accounting_payments p
				WHERE p.STAFF_ID=s.STAFF_ID
				AND p.SYEAR=s.SYEAR" . ( $usd_only ? " AND p.CURRENCY='USD'" : '' ) . "),0)
			-coalesce((SELECT sum(f.AMOUNT)
				FROM accounting_salaries f
				WHERE f.STAFF_ID=s.STAFF_ID
				AND f.SYEAR=s.SYEAR" . ( $usd_only ? " AND f.CURRENCY='USD'" : '' ) . "),0))
			BETWEEN '" . $_REQUEST['balance_low'] . "'
			AND '" . $_REQUEST['balance_high'] . "' ";

		if ( ! $extra['NoSearchTerms'] )
		{
			$extra['SearchTerms'] .= '<b>' . _( 'Staff Payroll Balance' ) . ( $usd_only ? ' (USD)' : '' ) . ': </b>' .
				_( 'Between' ) . ' ' . $_REQUEST['balance_low'] .
				' &amp; ' . $_REQUEST['balance_high'] . '<br />';
		}

		return $extra;
	}

	function html()
	{
		// Lot F4c (05/11) : avec deux monnaies, le critère porte sur le solde de paie en USD.
		require_once 'ProgramFunctions/SchoolSafeMoney.fnc.php';

		return '<tr class="st"><td>' . _( 'Staff Payroll Balance' ) . ( SchoolSafeMoneyAccountingReady() ? ' (USD)' : '' ) . '</td><td><label>' .
		_( 'Between' ) .
		' <input type="number" name="balance_low" step="0.01" min="-999999999999999" max="999999999999999"></label> <label>&amp;
		<input type="number" name="balance_high" step="0.01" min="-999999999999999" max="999999999999999"></label>
		</td></tr>';
	}
}
