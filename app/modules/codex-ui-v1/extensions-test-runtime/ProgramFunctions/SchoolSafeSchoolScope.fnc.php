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

/**
 * Un profil nomme a portee d'ecole (Directeur, Comptable...) ne peut pas
 * exister sans ecole : SCHOOLS vide veut dire « toutes les ecoles », donc
 * proprietaire de la plateforme. On refuse plutot que de promouvoir en
 * silence un directeur au rang de proprietaire.
 *
 * Les profils natifs par defaut (1 admin, 2 teacher, 3 parent) restent hors
 * garde : l'administrateur global conserve le moyen natif de nommer un pair.
 *
 * @param int $profile_id Profil nomme attribue au compte.
 *
 * @return bool Vrai si ce profil exige au moins une ecole.
 */
function SchoolSafeSchoolScopedProfileRequiresSchool( $profile_id )
{
    $profile_id = (int) $profile_id;

    if ( $profile_id < 4 || ! function_exists( 'DBGetOne' ) )
    {
        return false;
    }

    return (bool) DBGetOne( "SELECT 1 FROM user_profiles
        WHERE ID='" . $profile_id . "' AND PROFILE='admin'" );
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

/**
 * Options de filtre par profil nomme (Directeur, Comptable, Gardien) pour les
 * ecrans de recherche d'utilisateurs, en plus des familles natives.
 *
 * Les cles sont prefixees « profile:<id> » pour ne pas se confondre avec les
 * familles (admin, teacher, parent, none) que la recherche native attend.
 *
 * @return array Cle => libelle traduit.
 */
function SchoolSafeNamedProfileOptions()
{
    $options = [];

    $profiles = DBGet( "SELECT ID,TITLE
        FROM user_profiles
        WHERE ID>3 AND PROFILE IN ('admin','teacher','parent')
        ORDER BY PROFILE,ID" );

    foreach ( (array) $profiles as $profile )
    {
        $options[ 'profile:' . (int) $profile['ID'] ] = _( $profile['TITLE'] );
    }

    return $options;
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

/**
 * L3.1 — la colonne additive 034 (user_profiles.school_id) existe-t-elle ?
 * Oui après migration ; non sur une base pas encore migrée. Mis en cache
 * par requête ; toute erreur SQL vaut « absente » (jamais de 500 : sans la
 * colonne, aucun profil d'école ne peut exister).
 *
 * @return bool Vrai si les profils d'école sont stockables sur cette base.
 */
function SchoolSafeSchoolProfileColumnExists()
{
    static $exists = null;
    if ( $exists !== null ) return $exists;
    $exists = false;
    // @codingStandardsIgnoreStart (sonde d'introspection, pas de données).
    $hit = @DBGetOne( "SELECT 1 FROM information_schema.columns
        WHERE table_name='user_profiles' AND column_name='school_id'" );
    // @codingStandardsIgnoreEnd
    if ( (string) $hit === '1' ) $exists = true;
    return $exists;
}

/**
 * L3.1 — périmètre d'un profil nommé (migration 034) :
 *   null  = profil global (natif, écrans réservés au GlobalAdmin) ;
 *   entier = profil propre à cette école (hub /ecoles/{id}/profils) ;
 *   false = profil inconnu.
 * Sans la colonne 034, tout profil est global (null) : les gardes natifs
 * retrouvent alors exactement leur comportement d'avant L3.1.
 *
 * @param int|string $profile_id Profil nommé.
 * @return int|null|false École propriétaire, null si global, false si inconnu.
 */
function SchoolSafeProfileSchoolId( $profile_id )
{
    if ( ! ctype_digit( (string) $profile_id ) || (int) $profile_id < 1 ) return false;
    if ( ! SchoolSafeSchoolProfileColumnExists() ) return null;
    $row = DBGet( "SELECT SCHOOL_ID FROM user_profiles WHERE ID='" . (int) $profile_id . "'" );
    if ( ! $row ) return false;
    $school = $row[1]['SCHOOL_ID'];
    if ( $school === null || $school === '' ) return null;
    return (int) $school > 0 ? (int) $school : false;
}

/**
 * L3.1 — un acteur peut-il attribuer le profil $profile_id à un compte dont
 * les écoles cibles sont $target_schools (liste ',N,' ou null pour parent) ?
 * Global : oui. Profil d'école S : oui si et seulement si S est dans le
 * périmètre de l'acteur ET les cibles sont exactement ',S,' (jamais un compte
 * multi-écoles, jamais une autre école : les droits natifs ne s'isolent pas
 * par contexte, on refuse plutôt que risquer une fuite).
 *
 * @param int|string $profile_id Profil nommé.
 * @param string|null $target_schools Écoles cibles ',N,' (null = parent).
 * @return bool Vrai si l'attribution respecte le périmètre du profil.
 */
function SchoolSafeProfileSchoolAssignable( $profile_id, $target_schools )
{
    $owner = SchoolSafeProfileSchoolId( $profile_id );
    if ( $owner === false ) return false;
    if ( $owner === null ) return true; // Global : les autres gardes décident.
    $scope = SchoolSafeScopeParse( User( 'SCHOOLS' ) );
    if ( $scope === false || ! SchoolSafeScopeContains( $scope, $owner ) ) return false;
    return $target_schools === ',' . $owner . ',';
}
