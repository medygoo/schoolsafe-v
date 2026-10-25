<?php

// Internal server-to-server envelope; never return this bearer message to the browser.
function SchoolSafeNativeCardEnvelope( $student_id, $source_instance, $secret )
{
    if ( ! function_exists( 'User' ) || ! function_exists( 'SchoolSafeReadNativeCardInput' ) )
    {
        throw new RuntimeException( 'NATIVE_BOOTSTRAP_REQUIRED' );
    }
    if ( ! is_string( $secret ) || strlen( $secret ) < 32
        || ! is_string( $source_instance ) || ! preg_match( '/^[a-zA-Z0-9._-]{1,80}$/D', $source_instance ) )
    {
        throw new RuntimeException( 'BRIDGE_CONFIGURATION_REQUIRED' );
    }
    $user = filter_var( User( 'STAFF_ID' ), FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );
    if ( $user === false ) { throw new RuntimeException( 'NATIVE_IDENTITY_REQUIRED' ); }
    $data = SchoolSafeReadNativeCardInput( $student_id );
    $now = time();
    $body = json_encode( [
        'version' => 1,
        'audience' => 'schoolsafe-card-bridge',
        'sourceInstance' => $source_instance,
        'nativeUserId' => (string) $user,
        'nativeSchoolId' => (string) $data['enrollment']['SCHOOL_ID'],
        'nativeYear' => (string) $data['enrollment']['SYEAR'],
        'issuedAt' => $now,
        'expiresAt' => $now + 30,
        'nonce' => bin2hex( random_bytes( 32 ) ),
        'input' => $data,
    ], JSON_THROW_ON_ERROR );
    return [ 'body' => $body, 'signature' => hash_hmac( 'sha256', $body, $secret ) ];
}
