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
    if ( ! in_array( User( 'PROFILE' ), [ 'admin', 'teacher' ], true )
        || ! SchoolSafeScopeContains( SchoolSafeScopeParse( User( 'SCHOOLS' ) ), $school ) )
    {
        return false;
    }

    // SchoolSafe — etat d'etablissement : une ecole suspendue ou desactivee
    // n'est plus accessible a ses utilisateurs (donnees conservees). Le
    // proprietaire de la plateforme garde l'acces pour administrer l'ecole.
    if ( function_exists( 'SchoolSafeSchoolBlocked' )
        && ! SchoolSafeGlobalAdmin()
        && SchoolSafeSchoolBlocked( $school ) )
    {
        return false;
    }

    return true;
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
    // Journalise le refus : indispensable pour distinguer une attaque d'un
    // perimetre mal configure (l'ecran reste, lui, strictement inchange).
    // Frame 0 is the call site itself: the most useful information when a
    // perimeter refuses a legitimate screen.
    $caller = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 );
    error_log( 'SchoolSafeScopeDeny ' . ( $_SERVER['REQUEST_URI'] ?? '' )
        . ' called from ' . ( $caller[0]['file'] ?? '?' ) . ':' . ( $caller[0]['line'] ?? '?' )
        . ' (via ' . ( $caller[1]['file'] ?? '?' ) . ':' . ( $caller[1]['line'] ?? '?' ) . ')' );

    http_response_code( 403 );
    echo ErrorMessage( [ _( 'You\'re not allowed to use this program!' ) ] );
}

/**
 * Vrai si les droits du profil $target sont STRICTEMENT contenus dans ceux du
 * profil $reference : chaque droit du premier existe chez le second, et le
 * second en possede au moins un que le premier n'a pas. Deux profils aux droits
 * identiques ne sont donc pas delegables l'un a l'autre (un Directeur ne peut
 * pas nommer un pair). NULL et '' designent le meme droit absent.
 *
 * @param int $target    Profil nomme a attribuer.
 * @param int $reference Profil nomme de l'acteur.
 *
 * @return bool
 */
function SchoolSafeProfileStrictlyWithin( $target, $reference )
{
    $target = (int) $target;
    $reference = (int) $reference;

    if ( $target < 1 || $reference < 1 || $target === $reference ) return false;

    $rows = DBGet( "SELECT PROFILE_ID,MODNAME,COALESCE(CAN_USE,'') AS CAN_USE,COALESCE(CAN_EDIT,'') AS CAN_EDIT
        FROM profile_exceptions
        WHERE PROFILE_ID IN (" . $target . "," . $reference . ")" );

    $target_rights = [];

    $reference_rights = [];

    foreach ( (array) $rows as $row )
    {
        $right = $row['MODNAME'] . '|' . $row['CAN_USE'] . '|' . $row['CAN_EDIT'];

        if ( (int) $row['PROFILE_ID'] === $target ) $target_rights[$right] = true;
        else $reference_rights[$right] = true;
    }

    // Un profil sans aucun droit n'est pas une delegation : rien a contenir.
    if ( ! $target_rights ) return false;

    foreach ( array_keys( $target_rights ) as $right )
    {
        if ( ! isset( $reference_rights[$right] ) ) return false;
    }

    // Strictement : l'acteur doit posseder au moins un droit de plus.
    return (bool) array_diff_key( $reference_rights, $target_rights );
}

function SchoolSafeProfileDelegable( $profile_id, $profile = null )
{
    if ( SchoolSafeGlobalAdmin() ) return true;

    // "Default permissions" posts an empty PROFILE_ID: resolve it from the
    // selected profile so a plain teacher/parent account stays creatable.
    if ( $profile_id === null || $profile_id === '' || $profile_id === '0' )
    {
        if ( (string) $profile === 'none' ) return true; // no permission at all
        $default_profiles = [ 'admin' => 1, 'teacher' => 2, 'parent' => 3 ];
        if ( ! isset( $default_profiles[(string) $profile] ) ) return false;
        $profile_id = $default_profiles[(string) $profile];
    }

    // Custom/global permissions cannot be assigned by a scoped administrator.
    if ( ! ctype_digit( (string) $profile_id ) || (int) $profile_id < 1 ) return false;
    $target_kind = DBGet( "SELECT PROFILE FROM user_profiles WHERE ID='" . (int) $profile_id . "'" );
    if ( ! $target_kind ) return false;

    $kind = (string) $target_kind[1]['PROFILE'];

    // Administrator profiles (platform admin, director, accountant...) manage
    // schools and permissions. A global administrator hands out any of them; a
    // scoped administrator only one whose rights are STRICTLY narrower than
    // theirs: a director may name an accountant, never a peer director and
    // never a platform administrator (identical or wider rights are refused).
    if ( $kind === 'admin'
        && ! SchoolSafeProfileStrictlyWithin( (int) $profile_id, (int) User( 'PROFILE_ID' ) ) )
    {
        return false;
    }

    // Families without a native counterpart are still refused: a bespoke
    // profile can carry arbitrary permissions nobody audited.
    if ( $kind !== 'admin' && ! in_array( $kind, [ 'teacher', 'parent', 'student' ], true ) ) return false;

    // Defence in depth: a native family whose permissions were edited must not
    // be able to reach the screens reserved to global administrators.
    $reserved = [
        'Users/Profiles.php',
        'Users/Exceptions.php',
        'Users/User.php&category_id=1&schools',
        'Users/User.php&category_id=1&user_profile',
    ];

    $target = DBGet( "SELECT MODNAME,CAN_USE,CAN_EDIT FROM profile_exceptions WHERE PROFILE_ID='" . (int) $profile_id . "'" );

    foreach ( (array) $target as $row )
    {
        if ( ! in_array( $row['MODNAME'], $reserved, true ) ) continue;

        if ( $row['CAN_USE'] === 'Y' || $row['CAN_EDIT'] === 'Y' ) return false;
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
