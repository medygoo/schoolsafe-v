<?php

header( 'Content-Type: application/json; charset=utf-8' );
header( 'Cache-Control: no-store' );
header( 'X-Content-Type-Options: nosniff' );
header( 'Cross-Origin-Resource-Policy: same-origin' );
function SchoolSafePrepareReply( $status, $body )
{
    http_response_code( $status );
    echo json_encode( $body, JSON_THROW_ON_ERROR );
    exit;
}
if ( $_SERVER['REQUEST_METHOD'] !== 'GET' )
{
    header( 'Allow: GET' );
    SchoolSafePrepareReply( 405, [ 'error' => 'METHOD_NOT_ALLOWED' ] );
}
if ( array_diff( array_keys( $_GET ), [ 'student_id', 'resource' ] ) || ! empty( $_POST ) )
{
    SchoolSafePrepareReply( 400, [ 'error' => 'INVALID_QUERY' ] );
}
$student_id = filter_var( $_GET['student_id'] ?? null, FILTER_VALIDATE_INT,
    [ 'options' => [ 'min_range' => 1 ] ] );
if ( $student_id === false ) { SchoolSafePrepareReply( 400, [ 'error' => 'INVALID_STUDENT_ID' ] ); }
$resource = $_GET['resource'] ?? 'prepare';
if ( ! in_array( $resource, [ 'prepare', 'metadata', 'studio-input', 'issuance-check' ], true ) )
{
    SchoolSafePrepareReply( 400, [ 'error' => 'INVALID_CARD_RESOURCE' ] );
}
chdir( __DIR__ );
$_REQUEST = [ 'modname' => 'Student_ID_Card/StudentIDCard.php', 'modfunc' => '' ];
$level = ob_get_level();
ob_start();
try
{
    require_once __DIR__ . '/Warehouse.php';
    if ( ! User( 'STAFF_ID' ) )
    {
        while ( ob_get_level() > $level ) { ob_end_clean(); }
        SchoolSafePrepareReply( 401, [ 'error' => 'NATIVE_SESSION_REQUIRED' ] );
    }
    require_once __DIR__ . '/modules/SchoolSafe/Gateway.php';
    if (SchoolSafeEndpointGateway('card-bridge-prepare.php', 'GET', '', $_GET) === 'deny') {
        throw new RuntimeException('NATIVE_ACCESS_DENIED');
    }
    require_once __DIR__ . '/schoolsafe-card-bridge/RosarioCardRead.fnc.php';
    require_once __DIR__ . '/schoolsafe-card-bridge/NativeCardRelay.fnc.php';
    $result = SchoolSafePrepareNativeCard( $student_id, $resource );
    while ( ob_get_level() > $level ) { ob_end_clean(); }
    SchoolSafePrepareReply( 200, $result );
}
catch ( Throwable $error )
{
    while ( ob_get_level() > $level ) { ob_end_clean(); }
    $known = [ 'NATIVE_ACCESS_DENIED' => 403, 'CARD_BRIDGE_ACCESS_DENIED' => 403,
        'NATIVE_STUDENT_NOT_ACCESSIBLE' => 404, 'NATIVE_SCHOOL_NOT_ACCESSIBLE' => 404,
        'INVALID_NATIVE_CONTEXT' => 400 ];
    $code = $error->getMessage();
    SchoolSafePrepareReply( $known[$code] ?? 503,
        [ 'error' => isset( $known[$code] ) ? $code : 'CARD_BRIDGE_UNAVAILABLE' ] );
}
