<?php
/**
 * Balance (Student Billing) Widget class
 *
 * @since 12.7
 *
 * @package RosarioSIS
 */

namespace RosarioSIS\Widget;

class Balance implements \RosarioSIS\Widget
{
	function canBuild( $modules )
	{
		return $modules['Student_Billing']
			&& AllowUse( 'Student_Billing/StudentFees.php' );
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

		// Lot F4b (05/11) : avec deux monnaies, la recherche porte sur le solde en USD seulement
		// (un intervalle de montants n'a pas de sens si FC et USD sont additionnés).
		require_once 'ProgramFunctions/SchoolSafeMoney.fnc.php';

		$usd_only = SchoolSafeMoneyCurrencyReady();

		$extra['WHERE'] .= " AND (
			coalesce((SELECT sum(p.AMOUNT)
				FROM billing_payments p
				WHERE p.STUDENT_ID=ssm.STUDENT_ID
				AND p.SYEAR=ssm.SYEAR" . ( $usd_only ? " AND p.CURRENCY='USD'" : '' ) . "),0) -
			coalesce((SELECT sum(f.AMOUNT)
				FROM billing_fees f
				WHERE f.STUDENT_ID=ssm.STUDENT_ID
				AND f.SYEAR=ssm.SYEAR" . ( $usd_only ? " AND f.CURRENCY='USD'" : '' ) . "),0))
			BETWEEN '" . $_REQUEST['balance_low'] . "'
			AND '" . $_REQUEST['balance_high'] . "' ";

		if ( ! $extra['NoSearchTerms'] )
		{
			$extra['SearchTerms'] .= '<b>' . _( 'Student Billing Balance' ) . ( $usd_only ? ' (USD)' : '' ) . ' ' . _( 'Between' ) .': </b>' .
				$_REQUEST['balance_low'] . ' &amp; ' .
				$_REQUEST['balance_high'] . '<br />';
		}

		return $extra;
	}

	function html()
	{
		// Lot F4b (05/11) : avec deux monnaies, le critère porte sur le solde en USD.
		require_once 'ProgramFunctions/SchoolSafeMoney.fnc.php';

		return '<tr class="st"><td>' . _( 'Balance' ) . ( SchoolSafeMoneyCurrencyReady() ? ' (USD)' : '' ) . '</td><td><label>' . _( 'Between' ) .
		' <input type="number" name="balance_low" step="0.01" min="-999999999999999" max="999999999999999"></label>' .
		' <label>&amp;' .
		' <input type="number" name="balance_high" step="0.01" min="-999999999999999" max="999999999999999"></label>
		</td></tr>';
	}
}
