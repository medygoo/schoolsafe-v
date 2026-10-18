<?php
/**
 * SchoolSafe portal actions.
 *
 * Prints links that are already in the current profile menu.
 * Prints no count and runs no extra query.
 *
 * @package RosarioSIS
 * @subpackage modules
 */

/**
 * Priority actions for profiles that have no Rosario dashboard total.
 */
function SchoolSafeDashboardShortcuts()
{
	global $_ROSARIO;

	$profile = User( 'PROFILE' );

	$programs = [
		'teacher' => [
			'Scheduling/Schedule.php',
			'Scheduling/Courses.php',
			'Attendance/TakeAttendance.php',
			'Grades/Assignments.php',
			'Grades/Grades.php',
			'Discipline/Referrals.php',
		],
		'parent' => [
			'Scheduling/Schedule.php',
			'Attendance/DailySummary.php',
			'Grades/StudentGrades.php',
			'Grades/StudentAssignments.php',
			'Student_Billing/StudentFees.php',
			'Food_Service/Accounts.php',
			'Resources/Resources.php',
		],
		'student' => [
			'Scheduling/Schedule.php',
			'Attendance/DailySummary.php',
			'Grades/StudentAssignments.php',
			'Grades/StudentGrades.php',
			'Resources/Resources.php',
		],
	];

	if ( empty( $programs[$profile] ) || empty( $_ROSARIO['Menu'] ) )
	{
		return;
	}

	$links = [];

	foreach ( $programs[$profile] as $modname )
	{
		$title = SchoolSafeMenuProgramTitle( $modname );

		if ( $title === '' )
		{
			continue;
		}

		$links[] = '<a class="button-primary" href="' .
			URLEscape( 'Modules.php?modname=' . $modname ) . '">' . $title . '</a>';
	}

	if ( ! $links )
	{
		return;
	}

	echo '<nav class="ss-portal-actions" aria-label="' . AttrEscape( _( 'Dashboard' ) ) . '">';
	echo implode( '', $links );
	echo '</nav>';
}

/**
 * Title of a program already allowed for this profile.
 *
 * @param string $modname Rosario program.
 *
 * @return string Empty when the program is not in the menu.
 */
function SchoolSafeMenuProgramTitle( $modname )
{
	global $_ROSARIO;

	foreach ( (array) $_ROSARIO['Menu'] as $entries )
	{
		if ( ! empty( $entries[$modname] ) && is_string( $entries[$modname] ) )
		{
			return $entries[$modname];
		}
	}

	return '';
}
