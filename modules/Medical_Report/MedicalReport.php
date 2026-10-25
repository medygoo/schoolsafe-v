<?php
/**
 * Medical Report: Nurse Visits and Immunizations or Physicals
 *
 * @package Medical Report module
 */

DrawHeader( ProgramTitle() );

$_REQUEST['report'] = issetVal( $_REQUEST['report'], '' );

$report_link = PreparePHP_SELF(
	[],
	[ 'report', 'immunizations_or_physicals' ]
) . '&report=';

// @deprecated since RosarioSIS 12.4
if ( function_exists( 'AllowEditTemporary' ) )
{
	// Temporary AllowEdit for non admin users for SelectInput display.
	AllowEditTemporary( 'start' );
}
else
{
	if ( ! AllowEdit() )
	{
		// Temporary AllowEdit for non admin users for SelectIpnut display.
		$_ROSARIO['allow_edit'] = true;

		$allow_edit_tmp = true;
	}
}

$report_select = SelectInput(
	$_REQUEST['report'],
	'report',
	'',
	[
		'' => dgettext( 'Medical_Report', 'Nurse Visits' ),
		'immunizations_or_physicals' => dgettext( 'Medical_Report', 'Immunizations or Physicals' ),
	],
	false,
	'autocomplete="off" ' .
	( version_compare( ROSARIO_VERSION, '12.5', '>=' ) ?
		// @since RosarioSIS 12.5 CSP remove unsafe-inline Javascript
		'class="onchange-ajax-link" data-link="' . $report_link . 'this.value"' :
		'onchange="' . AttrEscape( 'ajaxLink(' . json_encode( $report_link ) . ' + this.value);' ) . '"' ),
	false
);

// @deprecated since RosarioSIS 12.4
if ( function_exists( 'AllowEditTemporary' ) )
{
	AllowEditTemporary( 'stop' );
}
else
{
	if ( ! empty( $allow_edit_tmp ) )
	{
		// Remove temporary AllowEdit for non admin users for input display.
		$_ROSARIO['allow_edit'] = false;
	}
}

DrawHeader( $report_select );

if ( $_REQUEST['report'] === 'immunizations_or_physicals' )
{
	require_once 'modules/Medical_Report/includes/ImmunizationsOrPhysicals.php';
}
else
{
	require_once 'modules/Medical_Report/includes/NurseVisits.php';
}
