<?php
$allow = true;
$schedule_allow = true;
$sections = [];
$email_type = 'text';
$email_value = 'test-school@example.invalid';
function DBEscapeIdentifier( $value ) { return '"' . strtolower( $value ) . '"'; }
$rows = [ [ 'STUDENT_ID' => 12, 'SCHOOL_ID' => 1, 'SYEAR' => 2026,
    'LAST_NAME' => 'TEST', 'FIRST_NAME' => 'EXAMPLE', 'GRADE_ID' => 'Formatted Grade', 'NATIVE_GRADE_ID' => 7, 'PASSWORD' => 'do-not-export' ] ];
function AllowUse( $module ) { global $allow, $schedule_allow; return $allow && ( $module !== 'Scheduling/Schedule.php' || $schedule_allow ); }
function UserSchool() { return 1; }
function UserSyear() { return 2026; }
function GetStuList( &$extra ) {
    global $rows;
    check( strpos( $extra['WHERE'], 's.STUDENT_ID=12' ) !== false );
    check( strpos( $extra['WHERE'], 'ssm.SCHOOL_ID=1' ) !== false );
    check( strpos( $extra['WHERE'], 'ssm.SYEAR=2026' ) !== false );
    check( ! isset( $extra['ASSOCIATED'] ) );
    check( strpos( $extra['SELECT'], 'ssm.GRADE_ID AS NATIVE_GRADE_ID' ) !== false );
    return $rows;
}
function DBGet( $sql ) {
    global $sections, $email_type, $email_value;
    if ( strpos( $sql, 'FROM school_fields' ) !== false ) {
        check( strpos( $sql, 'WHERE ID=99' ) !== false );
        return [ [ 'ID' => 99, 'TYPE' => $email_type ] ];
    }
    if ( strpos( $sql, 'AS SCHOOL_EMAIL' ) !== false ) {
        check( strpos( $sql, '"custom_99"' ) !== false );
        check( strpos( $sql, 'WHERE ID=1 AND SYEAR=2026' ) !== false );
        return [ [ 'SCHOOL_EMAIL' => $email_value ] ];
    }
    if ( strpos( $sql, 'FROM school_gradelevels' ) !== false ) {
        check( strpos( $sql, 'ID=7 AND SCHOOL_ID=1' ) !== false );
        return [ [ 'ID' => 7, 'TITLE' => 'TEST GRADE', 'SHORT_NAME' => 'TG' ] ];
    }
    if ( strpos( $sql, 'FROM schedule sch' ) !== false ) {
        check( strpos( $sql, 'sch.STUDENT_ID=12' ) !== false );
        check( strpos( $sql, 'sch.SCHOOL_ID=1' ) !== false );
        check( strpos( $sql, 'sch.SYEAR=2026' ) !== false );
        check( strpos( $sql, 'cp.SCHOOL_ID=sch.SCHOOL_ID AND cp.SYEAR=sch.SYEAR' ) !== false );
        check( strpos( $sql, 'sch.START_DATE<=CURRENT_DATE' ) !== false );
        return $sections;
    }
    return [ [ 'ID' => 1, 'SYEAR' => 2026, 'TITLE' => 'TEST SCHOOL', 'PASSWORD' => 'do-not-export' ] ];
}
require __DIR__ . '/RosarioCardRead.fnc.php';
function check( $value ) { if ( ! $value ) { throw new RuntimeException( 'TEST_FAILED' ); } }
function rejects( $callback, $message ) {
    try { $callback(); } catch ( RuntimeException $e ) { check( $e->getMessage() === $message ); return; }
    throw new RuntimeException( 'EXPECTED_REJECTION' );
}
$result = SchoolSafeReadNativeCardStudent( 12 );
check( $result['student']['FIRST_NAME'] === 'EXAMPLE' );
check( ! isset( $result['student']['PASSWORD'] ) );
rejects( function () { SchoolSafeReadNativeCardStudent( '12 OR 1=1' ); }, 'INVALID_NATIVE_CONTEXT' );
$allow = false;
rejects( function () { SchoolSafeReadNativeCardStudent( 12 ); }, 'NATIVE_ACCESS_DENIED' );
$allow = true;
$input = SchoolSafeReadNativeCardInput( 12 );
check( $input['authorized'] === true );
check( $input['school']['TITLE'] === 'TEST SCHOOL' );
check( ! isset( $input['school']['PASSWORD'] ) );
check( $input['verified']['photoUrl'] === '' );
check( $input['schoolingSource']['grade']['id'] === '7' );
check( $input['schoolingSource']['courseSections'] === [] );
$sections = [ [ 'COURSE_PERIOD_ID' => 10, 'COURSE_ID' => 4, 'TITLE' => 'TEST SECTION', 'TEACHER_ID' => 2, 'PASSWORD' => 'do-not-export' ] ];
$schooling = SchoolSafeReadNativeCardSchooling( [ 'enrollment' => $input['enrollment'], 'gradeId' => 7 ] );
check( $schooling['courseSections'][0]['teacherId'] === '2' );
check( ! isset( $schooling['courseSections'][0]['PASSWORD'] ) );
$schedule_allow = false;
$schooling = SchoolSafeReadNativeCardSchooling( [ 'enrollment' => $input['enrollment'], 'gradeId' => 7 ] );
check( $schooling['scheduleAccess'] === false && $schooling['courseSections'] === [] );
$schedule_allow = true;
check( SchoolSafeCardLocalAsset( '../not-an-asset.jpg' ) === '' );
check( SchoolSafeCardLocalAsset( __DIR__ . '/RosarioCardRead.fnc.php' ) === '/RosarioCardRead.fnc.php' );
check( SchoolSafeReadConfiguredSchoolEmail( $input['enrollment'], null ) === '' );
check( SchoolSafeReadConfiguredSchoolEmail( $input['enrollment'], 99 ) === 'test-school@example.invalid' );
rejects( function () use ( $input ) { SchoolSafeReadConfiguredSchoolEmail( $input['enrollment'], '99 OR 1=1' ); }, 'INVALID_SCHOOL_EMAIL_MAPPING' );
$email_type = 'numeric';
rejects( function () use ( $input ) { SchoolSafeReadConfiguredSchoolEmail( $input['enrollment'], 99 ); }, 'SCHOOL_EMAIL_FIELD_REQUIRED' );
$email_type = 'text';
$email_value = 'not an email';
rejects( function () use ( $input ) { SchoolSafeReadConfiguredSchoolEmail( $input['enrollment'], 99 ); }, 'INVALID_SCHOOL_EMAIL' );
$email_value = '';
check( SchoolSafeReadConfiguredSchoolEmail( $input['enrollment'], 99 ) === '' );
$rows[0]['SCHOOL_ID'] = 2;
rejects( function () { SchoolSafeReadNativeCardStudent( 12 ); }, 'NATIVE_STUDENT_NOT_ACCESSIBLE' );
$rows[0]['SCHOOL_ID'] = 1;
$rows[0]['SYEAR'] = 2025;
rejects( function () { SchoolSafeReadNativeCardStudent( 12 ); }, 'NATIVE_STUDENT_NOT_ACCESSIBLE' );
$rows[0]['SYEAR'] = 2026;
$rows[0]['STUDENT_ID'] = 13;
rejects( function () { SchoolSafeReadNativeCardStudent( 12 ); }, 'NATIVE_STUDENT_NOT_ACCESSIBLE' );
$rows = [];
rejects( function () { SchoolSafeReadNativeCardStudent( 12 ); }, 'NATIVE_STUDENT_NOT_ACCESSIBLE' );
rejects( function () { SchoolSafeReadNativeCardStudent( [12] ); }, 'INVALID_NATIVE_CONTEXT' );
if ( in_array( '--json', $argv, true ) )
{
    echo json_encode( $input, JSON_THROW_ON_ERROR );
}
else
{
    echo "PASS: simulated provider scope, permission, whitelist and invalid-input checks; no database writes\n";
}
