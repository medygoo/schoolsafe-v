<?php
/**
 * Immunizations or Physicals Report
 *
 * @package Medical Report module
 */

require_once 'modules/Medical_Report/includes/MedicalReport.fnc.php';
require_once 'ProgramFunctions/TipMessage.fnc.php';

// Set start date.
$start_date = RequestedDate( 'start', date( 'Y-m' ) . '-01' );

// Set end date.
$end_date = RequestedDate( 'end', DBDate() );

echo '<form action="' . PreparePHP_SELF( [], [
	'immunizations_or_physicals',
	'month_start',
	'day_start',
	'year_start',
	'month_end',
	'day_end',
	'year_end',
] ) . '" method="GET">';

$immunizations_or_physicals_select = SelectInput(
	issetVal( $_REQUEST['immunizations_or_physicals'] ),
	'immunizations_or_physicals',
	'<span class="a11y-hidden">' . _( 'Type' ) . '</span>',
	[ 'Immunization' => _( 'Immunization' ), 'Physical' => _( 'Physical' ) ],
	_( 'All' ),
	'autocomplete="off"',
	false
);

DrawHeader(
	_( 'Timeframe' ) . ': ' . PrepareDate( $start_date, '_start', false ) . ' ' .
	_( 'to' ) . ' ' . PrepareDate( $end_date, '_end', false ),
	$immunizations_or_physicals_select . ' ' . SubmitButton( _( 'Go' ) )
);

echo '</form>';

// Add Include Inactive Students checkbox.
DrawHeader(
	CheckBoxOnclick(
		'include_inactive',
		_( 'Include Inactive Students' )
	)
);

$where_active_sql = '';

if ( empty( $_REQUEST['include_inactive'] ) )
{
	$where_active_sql = " AND ( ('" . $end_date . "' BETWEEN se.START_DATE AND se.END_DATE OR se.END_DATE IS NULL)
		AND '" . $end_date . "'>=se.START_DATE )";
}

$where_immunizations_or_physicals_sql = '';

if ( ! empty( $_REQUEST['immunizations_or_physicals'] ) )
{
	$where_immunizations_or_physicals_sql = " AND sm.TYPE='" . $_REQUEST['immunizations_or_physicals'] . "'";
}

$immunizations_or_physicals_sql = "SELECT sm.MEDICAL_DATE,sm.TYPE,sm.COMMENTS,
	se.STUDENT_ID," . DisplayNameSQL( 's' ) . " AS FULL_NAME
	FROM student_enrollment se,students s,student_medical sm
	WHERE s.STUDENT_ID=se.STUDENT_ID
	AND sm.STUDENT_ID=s.STUDENT_ID
	AND sm.MEDICAL_DATE BETWEEN '" . $start_date . "' AND '" . $end_date . "'
	" . $where_active_sql . "
	AND se.SCHOOL_ID='" . UserSchool() . "'
	AND se.SYEAR='" . UserSyear() . "'
	" . $where_immunizations_or_physicals_sql . "
	ORDER BY MEDICAL_DATE DESC";

$sql_count = "SELECT COUNT(1)
	FROM student_enrollment se,students s,student_medical sm
	WHERE s.STUDENT_ID=se.STUDENT_ID
	AND sm.STUDENT_ID=s.STUDENT_ID
	" . $where_active_sql . "
	AND se.SCHOOL_ID='" . UserSchool() . "'
	AND se.SYEAR='" . UserSyear() . "'
	" . $where_immunizations_or_physicals_sql;

$immunizations_or_physicals_sql .= SQLLimitForList( $sql_count );

$immunizations_or_physicals_RET = DBGet( $immunizations_or_physicals_sql, [
	'FULL_NAME' => 'MedicalReportMakeStudentInfoLink',
	'MEDICAL_DATE' => 'ProperDate',
	'TYPE' => '_makeType',
] );

$columns = [
	'FULL_NAME' => _( 'Student' ),
	'TYPE' => _( 'Type' ),
	'MEDICAL_DATE' => _( 'Date' ),
	'COMMENTS' => _( 'Comments' ),
];

ListOutput( $immunizations_or_physicals_RET, $columns, 'Immunization or Physical', 'Immunizations or Physicals' );


/**
 * Make Medical Immunization or Physical type
 *
 * @global array  $THIS_RET
 *
 * @param  string $value    Field value.
 * @param  string $column   Field column.
 *
 * @return string Immunization or Physical
 */
function _makeType( $value, $column )
{
	return $value === 'Immunization' ? _( 'Immunization' ) : _( 'Physical' );
}
