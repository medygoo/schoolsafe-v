<?php
/**
 * Medical Report functions
 *
 * @package Medical Report module
 */

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
function MedicalReportMakeStudentInfoLink( $value, $column = 'FULL_NAME' )
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
