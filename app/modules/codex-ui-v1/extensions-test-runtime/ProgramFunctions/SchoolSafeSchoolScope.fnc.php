<?php

// NULL/empty retain the native all-schools meaning; malformed lists fail closed.
function SchoolSafeScopeParse( $value )
{
    if ( $value === null || $value === '' ) return null;
    if ( ! is_string( $value ) || ! preg_match( '/^,(?:[1-9][0-9]*,)+$/D', $value ) ) return false;
    return array_values( array_unique( array_map( 'intval', explode( ',', trim( $value, ',' ) ) ) ) );
}

function SchoolSafeScopeContains( $scope, $school )
{
    return ctype_digit( (string) $school ) && (int) $school > 0
        && $scope !== false && ( $scope === null || in_array( (int) $school, $scope, true ) );
}

function SchoolSafeScopeDelegates( $actor, $target )
{
    if ( $actor === false || $target === false ) return false;
    if ( $actor === null ) return true;
    return $target !== null && ! array_diff( $target, $actor );
}

function SchoolSafeSchoolAllowed( $school )
{
    return in_array( User( 'PROFILE' ), [ 'admin', 'teacher' ], true )
        && SchoolSafeScopeContains( SchoolSafeScopeParse( User( 'SCHOOLS' ) ), $school );
}

function SchoolSafeGlobalAdmin()
{
    return User( 'PROFILE' ) === 'admin' && SchoolSafeScopeParse( User( 'SCHOOLS' ) ) === null;
}

function SchoolSafeCanCreateSchool()
{
    $scope = SchoolSafeScopeParse( User( 'SCHOOLS' ) );
    return User( 'PROFILE' ) === 'admin' && ( $scope === null || ( is_array( $scope ) && count( $scope ) > 1 ) )
        && AllowEdit( 'School_Setup/CopySchool.php' );
}

function SchoolSafeScopeDeny()
{
    http_response_code( 403 );
    echo ErrorMessage( [ _( 'You\'re not allowed to use this program!' ) ] );
}

function SchoolSafeProfileDelegable( $profile_id )
{
    if ( SchoolSafeGlobalAdmin() ) return true;
    // Custom/global permissions cannot be assigned by a scoped administrator.
    if ( ! ctype_digit( (string) $profile_id ) || (int) $profile_id < 1 ) return false;
    if ( ! DBGet( "SELECT ID FROM user_profiles WHERE ID='" . (int) $profile_id . "'" ) ) return false;
    $actor_from = User( 'PROFILE_ID' ) ? "profile_exceptions WHERE PROFILE_ID='" . (int) User( 'PROFILE_ID' ) . "'"
        : "staff_exceptions WHERE USER_ID='" . (int) User( 'STAFF_ID' ) . "'";
    $actor = DBGet( 'SELECT MODNAME,CAN_USE,CAN_EDIT FROM ' . $actor_from );
    $target = DBGet( "SELECT MODNAME,CAN_USE,CAN_EDIT FROM profile_exceptions WHERE PROFILE_ID='" . (int) $profile_id . "'" );
    $rights = [];
    foreach ( (array) $actor as $row ) $rights[$row['MODNAME']] = $row;
    foreach ( (array) $target as $row )
    {
        foreach ( [ 'CAN_USE', 'CAN_EDIT' ] as $right )
            if ( $row[$right] === 'Y' && ( $rights[$row['MODNAME']][$right] ?? '' ) !== 'Y' ) return false;
    }
    return true;
}

function SchoolSafeStaffWithinScope( $staff_id )
{
    $target = DBGet( "SELECT PROFILE,SCHOOLS FROM staff WHERE STAFF_ID='" . (int) $staff_id . "' AND SYEAR='" . (int) UserSyear() . "'" );
    if ( ! $target ) return false;
    $scope = SchoolSafeScopeParse( User( 'SCHOOLS' ) );
    if ( $target[1]['PROFILE'] === 'parent' )
    {
        $schools = DBGet( "SELECT DISTINCT se.SCHOOL_ID FROM students_join_users j JOIN student_enrollment se ON se.STUDENT_ID=j.STUDENT_ID WHERE j.STAFF_ID='" . (int) $staff_id . "' AND se.SYEAR='" . (int) UserSyear() . "'" );
        foreach ( (array) $schools as $school )
            if ( ! SchoolSafeScopeContains( $scope, $school['SCHOOL_ID'] ) ) return false;
        return $scope !== false;
    }
    return SchoolSafeScopeDelegates( $scope, SchoolSafeScopeParse( $target[1]['SCHOOLS'] ) );
}
