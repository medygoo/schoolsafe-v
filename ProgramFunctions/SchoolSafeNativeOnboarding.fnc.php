<?php
/** Targeted native first access. No global password policy or provider changes. */
function SchoolSafeNativeOnboardingRow( $staff_id, $syear )
{
    static $ready = null;
    if ( $ready === null )
    {
        $ready = (bool) DBGetOne( "SELECT to_regclass('schoolsafe_native_onboarding')" );
    }
    if ( ! $ready || (int) $staff_id < 1 ) return [];
    $rows = DBGet( "SELECT *, (expires_at>CURRENT_TIMESTAMP) AS VALID
        FROM schoolsafe_native_onboarding WHERE staff_id=" . (int) $staff_id . " AND syear=" . (int) $syear );
    return $rows[1] ?? [];
}

function SchoolSafeNativeOnboardingPending()
{
    $row = SchoolSafeNativeOnboardingRow( User( 'STAFF_ID' ), UserSyear() );
    return $row && empty( $row['COMPLETED_AT'] );
}

/** Called at native login, before any authenticated redirect. */
function SchoolSafeNativeOnboardingMarkLogin( $staff_id, $syear )
{
    $row = SchoolSafeNativeOnboardingRow( $staff_id, $syear );
    unset( $_SESSION['SchoolSafeNativePending'] );
    if ( $row && empty( $row['COMPLETED_AT'] ) ) $_SESSION['SchoolSafeNativePending'] = (int) $row['GENERATION'];
}

/** Every authenticated request is gated, not only the default landing page. */
function SchoolSafeNativeOnboardingGuard()
{
    if ( empty( $_SESSION['STAFF_ID'] ) ) return;
    $row = SchoolSafeNativeOnboardingRow( $_SESSION['STAFF_ID'], UserSyear() );
    if ( ! $row && empty( $_SESSION['SchoolSafeNativePending'] ) ) return;
    if ( ! $row )
    {
        SchoolSafeAccountEndSession();
        http_response_code( 403 );
        header( 'Cache-Control: no-store' );
        echo 'First access unavailable / Accès initial indisponible.';
        exit;
    }
    if ( ! empty( $row['COMPLETED_AT'] ) && empty( $_SESSION['SchoolSafeNativePending'] ) ) return;
    if ( ! empty( $row['COMPLETED_AT'] ) || $row['VALID'] !== 't'
        || (int) ( $_SESSION['SchoolSafeNativePending'] ?? 0 ) !== (int) $row['GENERATION'] )
    {
        SchoolSafeAccountEndSession();
        http_response_code( 403 );
        header( 'Cache-Control: no-store' );
        echo SchoolSafeT( 'This initial access has expired or been replaced. Contact your administrator.',
            'Cet accès initial a expiré ou a été remplacé. Contactez votre gestionnaire.' ) . ' <a href="/index.php">SchoolSafe</a>';
        exit;
    }
    $is_index = basename( $_SERVER['SCRIPT_NAME'] ?? '' ) === 'index.php';
    if ( $is_index && in_array( $_REQUEST['modfunc'] ?? '', [ 'first-login', 'logout' ], true ) ) return;
    header( 'Location: /index.php?modfunc=first-login&token=' . rawurlencode( (string) $_SESSION['token'] ), true, 303 );
    exit;
}

/** Save password, consume targeted first access and audit atomically.
 * All other temporary-password sessions are rejected by the guard afterwards.
 */
function SchoolSafeNativeOnboardingComplete( $password )
{
    global $db_connection, $error, $note;
    if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST'
        || ! is_string( $_POST['token'] ?? null ) || empty( $_SESSION['token'] )
        || ! hash_equals( (string) $_SESSION['token'], $_POST['token'] )
        || ! is_string( $password ) || ! mb_check_encoding( $password, 'UTF-8' )
        || mb_strlen( $password ) < 12 || mb_strlen( $password ) > 128 || strpos( $password, "\0" ) !== false )
    {
        $error[] = SchoolSafeT( 'Choose a personal password of 12 to 128 characters and reload the form if necessary.', 'Choisissez un mot de passe personnel de 12 à 128 caractères et rechargez le formulaire si nécessaire.' );
        return false;
    }
    if ( pg_transaction_status( $db_connection ) !== PGSQL_TRANSACTION_IDLE ) return false;
    $query = static function ( $sql ) {
        $result = @db_query( $sql, false );
        if ( $result === false ) throw new RuntimeException( 'Native access unavailable' );
        return $result;
    };
    $started = false;
    try
    {
        $hash = encrypt_password( $password );
        if ( ! is_string( $hash ) || $hash === '' ) throw new RuntimeException( 'No password' );
        $query( 'BEGIN' ); $started = true;
        $query( "SET LOCAL lock_timeout='5s'" );
        $id = (int) User( 'STAFF_ID' ); $year = (int) UserSyear();
        $rows = DBGet( $query( "SELECT o.completed_at,o.generation,(o.expires_at>CURRENT_TIMESTAMP) AS VALID,s.password
            FROM schoolsafe_native_onboarding o JOIN staff s ON s.staff_id=o.staff_id AND s.syear=o.syear
            WHERE o.staff_id=" . $id . ' AND o.syear=' . $year . ' FOR UPDATE OF o,s' ) );
        $row = $rows[1] ?? [];
        if ( ! $row || ! empty( $row['COMPLETED_AT'] ) || $row['VALID'] !== 't'
            || (int) ( $_SESSION['SchoolSafeNativePending'] ?? 0 ) !== (int) $row['GENERATION']
            || match_password( $row['PASSWORD'], $password ) ) throw new RuntimeException( 'Access consumed or password unchanged' );
        $query( "UPDATE staff SET PASSWORD='" . DBEscapeString( $hash ) . "',LAST_LOGIN=CURRENT_TIMESTAMP
            WHERE staff_id=" . $id . ' AND syear=' . $year );
        $query( 'UPDATE schoolsafe_native_onboarding SET completed_at=CURRENT_TIMESTAMP
            WHERE staff_id=' . $id . ' AND syear=' . $year );
        $query( "INSERT INTO schoolsafe_identity_audit(actor_staff_id,target_staff_id,action,result)
            VALUES (" . $id . ',' . $id . ",'first_login','success')" );
        $query( 'COMMIT' ); $started = false;
        unset( $_SESSION['SchoolSafeNativePending'] );
        $_SESSION['LAST_LOGIN'] = date( 'Y-m-d H:i:s' );
        $note[] = _( 'Your new password was saved.' );
        return true;
    }
    catch ( Throwable $failure )
    {
        if ( $started ) @db_query( 'ROLLBACK', false );
        $error[] = SchoolSafeT( 'Your password was not saved. Check your access and choose a password different from the temporary password.', 'Le mot de passe n’a pas été enregistré. Vérifiez votre accès et choisissez un mot de passe différent du temporaire.' );
        return false;
    }
}

/** Pending access, or a legacy single-school administrator never connected.
 * A completed/previously connected legacy account is never reset through this flow.
 */
function SchoolSafeNativeOnboardingCanPrepare( array $staff, array $initial )
{
    $scope = SchoolSafeScopeParse( $staff['SCHOOLS'] ?? null );
    return ( $staff['PROFILE'] ?? '' ) === 'admin' && empty( $staff['SUBJECT'] )
        && is_array( $scope ) && count( $scope ) === 1
        && ( $initial ? empty( $initial['COMPLETED_AT'] ) : empty( $staff['LAST_LOGIN'] ) );
}

/** Prepare an unused legacy access or renew an uncompleted native first access.
 * Controller checks rights/CSRF. Password, generation and audit change together;
 * old pending sessions expire. No existing account is enrolled automatically.
 */
function SchoolSafeNativeOnboardingRenew( $staff_id, $syear, $actor )
{
    global $db_connection;
    if ( pg_transaction_status( $db_connection ) !== PGSQL_TRANSACTION_IDLE ) return false;
    $query = static function ( $sql ) {
        $result = @db_query( $sql, false );
        if ( $result === false ) throw new RuntimeException( 'Renew unavailable' );
        return $result;
    };
    $started = false; $id = (int) $staff_id; $year = (int) $syear;
    try
    {
        $temporary = SchoolSafeSetupTempPassword(); $hash = encrypt_password( $temporary );
        if ( ! is_string( $hash ) || $hash === '' ) return false;
        $query( 'BEGIN' ); $started = true;
        $query( "SET LOCAL lock_timeout='5s'" );
        $fresh = false;
        $rows = DBGet( $query( 'SELECT o.completed_at,s.profile,s.schools,s.last_login FROM schoolsafe_native_onboarding o
            JOIN staff s ON s.staff_id=o.staff_id AND s.syear=o.syear WHERE o.staff_id=' . $id
            . ' AND o.syear=' . $year . ' FOR UPDATE OF o,s' ) );
        if ( ! $rows )
        {
            $rows = DBGet( $query( 'SELECT profile,schools,last_login FROM staff WHERE staff_id=' . $id
                . ' AND syear=' . $year . ' FOR UPDATE' ) );
            if ( ! $rows || ! SchoolSafeNativeOnboardingCanPrepare( $rows[1], [] )
                || DBGet( $query( 'SELECT 1 FROM schoolsafe_native_onboarding WHERE staff_id=' . $id . ' AND syear=' . $year ) ) )
                throw new RuntimeException( 'Not an unused legacy native access' );
            $query( 'INSERT INTO schoolsafe_native_onboarding (staff_id,syear,created_by) VALUES ('
                . $id . ',' . $year . ',' . (int) $actor . ')' );
            $fresh = true;
        }
        if ( ! SchoolSafeNativeOnboardingCanPrepare( $rows[1], $fresh ? [] : $rows[1] )
            || DBGet( $query( 'SELECT 1 FROM schoolsafe_identity_links WHERE staff_id=' . $id ) ) )
            throw new RuntimeException( 'Not a pending native access' );
        $query( "UPDATE staff SET password='" . DBEscapeString( $hash ) . "' WHERE staff_id=" . $id . ' AND syear=' . $year );
        $query( 'UPDATE schoolsafe_native_onboarding SET generation=' . ( $fresh ? 'generation' : 'generation+1' )
            . ",expires_at=CURRENT_TIMESTAMP+interval '24 hours' WHERE staff_id=" . $id . ' AND syear=' . $year );
        $query( "INSERT INTO schoolsafe_identity_audit(actor_staff_id,target_staff_id,action,result)
            VALUES (" . (int) $actor . ',' . $id . ",'temporary','success')" );
        $query( 'COMMIT' ); $started = false;
        return $temporary;
    }
    catch ( Throwable $failure )
    {
        if ( $started ) @db_query( 'ROLLBACK', false );
        return false;
    }
}
