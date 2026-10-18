<?php
/**
 * Nurse Visits Report
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
	'month_start',
	'day_start',
	'year_start',
	'month_end',
	'day_end',
	'year_end',
] ) . '" method="GET">';

DrawHeader(
	_( 'Timeframe' ) . ': ' . PrepareDate( $start_date, '_start', false ) . ' ' .
	_( 'to' ) . ' ' . PrepareDate( $end_date, '_end', false ) . ' ' .
	SubmitButton( _( 'Go' ) ) );

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

$nurse_visits_sql = "SELECT smv.SCHOOL_DATE,smv.TIME_IN,smv.TIME_OUT,smv.REASON,smv.RESULT,smv.COMMENTS,
	se.STUDENT_ID," . DisplayNameSQL( 's' ) . " AS FULL_NAME
	FROM student_enrollment se,students s,student_medical_visits smv
	WHERE s.STUDENT_ID=se.STUDENT_ID
	AND smv.STUDENT_ID=s.STUDENT_ID
	AND smv.SCHOOL_DATE BETWEEN '" . $start_date . "' AND '" . $end_date . "'
	" . $where_active_sql . "
	AND se.SCHOOL_ID='" . UserSchool() . "'
	AND se.SYEAR='" . UserSyear() . "'
	ORDER BY SCHOOL_DATE DESC";

$sql_count = "SELECT COUNT(1)
	FROM student_enrollment se,students s,student_medical_visits smv
	WHERE s.STUDENT_ID=se.STUDENT_ID
	AND smv.STUDENT_ID=s.STUDENT_ID
	" . $where_active_sql . "
	AND se.SCHOOL_ID='" . UserSchool() . "'
	AND se.SYEAR='" . UserSyear() . "'";

$nurse_visits_sql .= SQLLimitForList( $sql_count );

$nurse_visits_RET = DBGet( $nurse_visits_sql, [
	'FULL_NAME' => 'MedicalReportMakeStudentInfoLink',
	'SCHOOL_DATE' => 'ProperDate',
] );

$columns = [
	'FULL_NAME' => _( 'Student' ),
	'SCHOOL_DATE' => _( 'Date' ),
	'TIME_IN' => _( 'Time In' ),
	'TIME_OUT' => _( 'Time Out' ),
	'REASON' => _( 'Reason' ),
	'RESULT' => _( 'Result' ),
	'COMMENTS' => _( 'Comments' ),
];

ListOutput( $nurse_visits_RET, $columns, 'Nurse Visit', 'Nurse Visits' );

/**
 * Make Student Info link
 *
 * Local function
 * DBGet() callback
 *
 * @param  string $value  Student Full Name.
 * @param  string $column Column.
 *
 * @return string         Link to Student Info program.
 */
function _makeStudentInfoLink( $value, $column = 'FULL_NAME' )
{
	global $THIS_RET;

	$modname = 'Students/Student.php&category_id=2';

	if ( ! AllowUse( $modname )
		|| ! $THIS_RET['STUDENT_ID'] )
	{
		return $value;
	}

	$link = 'Modules.php?modname=' . $modname . '&student_id=' . $THIS_RET['STUDENT_ID'] . '&category_id=2';

	return '<a href="' . URLEscape( $link ) . '">' . MakeStudentPhotoTipMessage( $THIS_RET['STUDENT_ID'], $value ) . '</a>';
}
