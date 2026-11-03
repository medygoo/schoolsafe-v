<?php

// Internal provider: call only after the standard Rosario bootstrap.
function SchoolSafeReadNativeCardStudent( $student_id )
{
    if ( ! function_exists( 'AllowUse' ) || ! function_exists( 'GetStuList' )
        || ! function_exists( 'UserSchool' ) || ! function_exists( 'UserSyear' ) )
    {
        throw new RuntimeException( 'NATIVE_BOOTSTRAP_REQUIRED' );
    }

    if ( ! AllowUse( 'Student_ID_Card/StudentIDCard.php' ) )
    {
        throw new RuntimeException( 'NATIVE_ACCESS_DENIED' );
    }

    $id = filter_var( $student_id, FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );
    $school = filter_var( UserSchool(), FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );
    $year = filter_var( UserSyear(), FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );

    if ( $id === false || $school === false || $year === false )
    {
        throw new RuntimeException( 'INVALID_NATIVE_CONTEXT' );
    }

    // Explicit scope remains enforced even if search-all-schools was requested.
    $extra = [
        'WHERE' => " AND s.STUDENT_ID=" . $id . " AND ssm.SCHOOL_ID=" . $school . " AND ssm.SYEAR=" . $year,
        'SELECT' => ',s.CUSTOM_200000003,s.CUSTOM_200000004,ssm.SYEAR,ssm.GRADE_ID AS NATIVE_GRADE_ID',
    ];
    $rows = GetStuList( $extra );
    $selected = null;

    foreach ( (array) $rows as $row )
    {
        if ( (string) $row['STUDENT_ID'] === (string) $id
            && (string) $row['SCHOOL_ID'] === (string) $school
            && (string) $row['SYEAR'] === (string) $year )
        {
            $selected = $row;
            break;
        }
    }

    if ( $selected === null )
    {
        throw new RuntimeException( 'NATIVE_STUDENT_NOT_ACCESSIBLE' );
    }

    $student = [];
    foreach ( [ 'STUDENT_ID', 'LAST_NAME', 'FIRST_NAME', 'MIDDLE_NAME', 'CUSTOM_200000003', 'CUSTOM_200000004' ] as $key )
    {
        $student[$key] = isset( $selected[$key] ) ? $selected[$key] : null;
    }

    return [
        'student' => $student,
        'enrollment' => [ 'STUDENT_ID' => $id, 'SCHOOL_ID' => $school, 'SYEAR' => $year ],
        'gradeId' => isset( $selected['NATIVE_GRADE_ID'] ) ? $selected['NATIVE_GRADE_ID'] : null,
    ];
}

function SchoolSafeReadNativeCardInput( $student_id )
{
    $data = SchoolSafeReadNativeCardStudent( $student_id );
    if ( ! function_exists( 'DBGet' ) )
    {
        throw new RuntimeException( 'NATIVE_BOOTSTRAP_REQUIRED' );
    }
    $context = $data['enrollment'];
    $rows = DBGet( 'SELECT ID,SYEAR,TITLE,ADDRESS,CITY,STATE,ZIPCODE,PHONE,PRINCIPAL,SCHOOL_NUMBER,WWW_ADDRESS FROM schools WHERE ID='
        . $context['SCHOOL_ID'] . ' AND SYEAR=' . $context['SYEAR'] );
    $school = null;
    foreach ( (array) $rows as $row )
    {
        if ( (string) $row['ID'] === (string) $context['SCHOOL_ID']
            && (string) $row['SYEAR'] === (string) $context['SYEAR'] )
        {
            $school = [];
            foreach ( [ 'ID', 'SYEAR', 'TITLE', 'ADDRESS', 'CITY', 'STATE', 'ZIPCODE', 'PHONE', 'PRINCIPAL', 'SCHOOL_NUMBER', 'WWW_ADDRESS' ] as $key )
            {
                $school[$key] = isset( $row[$key] ) ? $row[$key] : null;
            }
            break;
        }
    }
    if ( $school === null )
    {
        throw new RuntimeException( 'NATIVE_SCHOOL_NOT_ACCESSIBLE' );
    }

    global $StudentPicturesPath;
    $photo = '';
    if ( ! empty( $StudentPicturesPath ) )
    {
        $files = glob( $StudentPicturesPath . '*/' . $context['STUDENT_ID'] . '.*jpg' );
        $candidate = $files ? end( $files ) : '';
        $photo = SchoolSafeCardLocalAsset( $candidate );
    }
    require_once __DIR__ . '/../ProgramFunctions/SchoolSafeSchoolLogo.fnc.php';
    $logo = SchoolSafeCardLocalAsset( SchoolSafeSchoolLogoPath( (int) $context['SCHOOL_ID'] ) );
    $year_label = '';
    if ( function_exists( 'FormatSyear' ) && function_exists( 'Config' ) )
    {
        $year_label = FormatSyear( $context['SYEAR'], Config( 'SCHOOL_SYEAR_OVER_2_YEARS' ) );
    }
    return [
        'authorized' => true,
        'student' => $data['student'],
        'school' => $school,
        'enrollment' => $context,
        'verified' => [ 'photoUrl' => $photo, 'logoUrl' => $logo, 'academicYearLabel' => $year_label,
            'schoolEmail' => SchoolSafeReadNativeSchoolEmail( $context ),
            'matricule' => function_exists( 'SchoolSafeMatriculeLookup' ) ? SchoolSafeMatriculeLookup( $data['student']['STUDENT_ID'] ) : null,
            'studentAddress' => SchoolSafeReadNativeStudentAddress( $context ) ],
        'schoolingSource' => SchoolSafeReadNativeCardSchooling( $data ),
    ];
}

function SchoolSafeReadNativeStudentAddress( $context )
{
	// Adresse de residence principale de l'eleve (ligne complete) ; vide si aucune.
	if ( ! function_exists( 'DBGet' ) ) { return ''; }
	$rows = DBGet( 'SELECT a.HOUSE_NO,a.STREET,a.CITY,a.STATE,a.ADDRESS FROM students_join_address ja'
		. ' JOIN address a ON a.ADDRESS_ID=ja.ADDRESS_ID'
		. ' WHERE ja.STUDENT_ID=' . (int) $context['STUDENT_ID']
		. " ORDER BY (ja.PRIMARY_RESIDENCE='Y') DESC, ja.ADDRESS_ID ASC LIMIT 1" );
	foreach ( (array) $rows as $row )
	{
		$line = trim( (string) ( $row['ADDRESS'] ?? '' ) );
		if ( $line === '' )
		{
			$parts = array_filter( [ trim( (string) ( $row['HOUSE_NO'] ?? '' ) ),
				trim( (string) ( $row['STREET'] ?? '' ) ), trim( (string) ( $row['CITY'] ?? '' ) ) ],
				static function ( $p ) { return $p !== ''; } );
			$line = implode( ', ', $parts );
		}
		return mb_substr( $line, 0, 120 );
	}
	return '';
}

function SchoolSafeReadNativeSchoolEmail( $context )
{
	// Priorite : email enregistre a l onboarding (config), puis mapping legacy.
	$email = '';
	if ( function_exists( 'DBGet' ) )
	{
		$rows = DBGet( "SELECT CONFIG_VALUE FROM config WHERE TITLE='SCHOOLSAFE_SCHOOL_EMAIL' AND SCHOOL_ID=" . (int) $context['SCHOOL_ID'] );
		foreach ( (array) $rows as $row )
		{
			$email = trim( (string) ( $row['CONFIG_VALUE'] ?? '' ) );
			if ( $email !== '' ) break;
		}
	}
	if ( $email !== '' )
	{
		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) { throw new RuntimeException( 'INVALID_SCHOOL_EMAIL' ); }
		return $email;
	}
	$path = '/var/lib/schoolsafe-card-bridge/school-fields.json';
	if ( ! is_file( $path ) ) { return ''; }
	$config = json_decode( file_get_contents( $path ), true, 8, JSON_THROW_ON_ERROR );
	return SchoolSafeReadConfiguredSchoolEmail( $context, $config['school_email_field_id'] ?? null );
}

function SchoolSafeReadConfiguredSchoolEmail( $context, $field_id )
{
    if ( $field_id === null ) { return ''; }
    $id = filter_var( $field_id, FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );
    $school = filter_var( $context['SCHOOL_ID'] ?? null, FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );
    $year = filter_var( $context['SYEAR'] ?? null, FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );
    if ( $id === false || $school === false || $year === false ) { throw new RuntimeException( 'INVALID_SCHOOL_EMAIL_MAPPING' ); }
    $fields = DBGet( 'SELECT ID,TYPE FROM school_fields WHERE ID=' . $id );
    $field = null;
    foreach ( (array) $fields as $row )
    {
        if ( (string) $row['ID'] === (string) $id && $row['TYPE'] === 'text' ) { $field = $row; break; }
    }
    if ( $field === null ) { throw new RuntimeException( 'SCHOOL_EMAIL_FIELD_REQUIRED' ); }
    $column = DBEscapeIdentifier( 'CUSTOM_' . $id );
    $rows = DBGet( 'SELECT ' . $column . ' AS SCHOOL_EMAIL FROM schools WHERE ID=' . $school . ' AND SYEAR=' . $year );
    foreach ( (array) $rows as $row )
    {
        $email = trim( (string) ( $row['SCHOOL_EMAIL'] ?? '' ) );
        if ( $email === '' ) { return ''; }
        if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) { throw new RuntimeException( 'INVALID_SCHOOL_EMAIL' ); }
        return $email;
    }
    return '';
}

function SchoolSafeReadNativeCardSchooling( $data )
{
    $context = $data['enrollment'];
    $result = [ 'grade' => null, 'courseSections' => [], 'scheduleAccess' => false ];
    $grade = filter_var( $data['gradeId'], FILTER_VALIDATE_INT,
        [ 'options' => [ 'min_range' => 1 ] ] );
    if ( $grade !== false )
    {
        $rows = DBGet( 'SELECT ID,TITLE,SHORT_NAME FROM school_gradelevels WHERE ID='
            . $grade . ' AND SCHOOL_ID=' . (int) $context['SCHOOL_ID'] );
        foreach ( (array) $rows as $row )
        {
            if ( (string) $row['ID'] === (string) $grade )
            {
                $result['grade'] = [ 'id' => (string) $grade, 'title' => $row['TITLE'],
                    'shortName' => $row['SHORT_NAME'] ];
                break;
            }
        }
    }
    if ( ! AllowUse( 'Scheduling/Schedule.php' ) ) { return $result; }
    $result['scheduleAccess'] = true;
    // Sections are candidates, not proof of a homeroom or a primary teacher.
    $rows = DBGet( 'SELECT DISTINCT cp.COURSE_PERIOD_ID,cp.TITLE,cp.COURSE_ID,cp.TEACHER_ID'
        . ' FROM schedule sch JOIN course_periods cp ON cp.COURSE_PERIOD_ID=sch.COURSE_PERIOD_ID'
        . ' AND cp.SCHOOL_ID=sch.SCHOOL_ID AND cp.SYEAR=sch.SYEAR'
        . ' WHERE sch.STUDENT_ID=' . (int) $context['STUDENT_ID']
        . ' AND sch.SCHOOL_ID=' . (int) $context['SCHOOL_ID']
        . ' AND sch.SYEAR=' . (int) $context['SYEAR']
        . ' AND sch.START_DATE<=CURRENT_DATE AND (sch.END_DATE IS NULL OR sch.END_DATE>=CURRENT_DATE)'
        . ' ORDER BY cp.COURSE_PERIOD_ID' );
    foreach ( (array) $rows as $row )
    {
        $result['courseSections'][] = [ 'id' => (string) $row['COURSE_PERIOD_ID'],
            'title' => $row['TITLE'], 'courseId' => (string) $row['COURSE_ID'],
            'teacherId' => isset( $row['TEACHER_ID'] ) ? (string) $row['TEACHER_ID'] : null ];
    }
    return $result;
}

// Resolve only existing assets beneath the native installation, without leaking OS paths.
function SchoolSafeCardLocalAsset( $path )
{
    if ( ! $path ) { return ''; }
    $root = realpath( '.' );
    $file = realpath( $path );
    if ( ! $root || ! $file || ! is_file( $file ) ) { return ''; }
    $prefix = rtrim( $root, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
    if ( strpos( $file, $prefix ) !== 0 ) { return ''; }
    $relative = substr( $file, strlen( $prefix ) );
    return '/' . implode( '/', array_map( 'rawurlencode', explode( '/', str_replace( '\\', '/', $relative ) ) ) );
}
