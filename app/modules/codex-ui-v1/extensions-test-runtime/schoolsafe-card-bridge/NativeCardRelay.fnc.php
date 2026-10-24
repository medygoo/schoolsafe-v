<?php

// Called only after native Rosario bootstrap, never with client-selected identity.
function SchoolSafePrepareNativeCard( $student_id, $resource = 'prepare' )
{
    if ( ! in_array( $resource, [ 'prepare', 'metadata', 'studio-input', 'issuance-check' ], true ) )
    {
        throw new RuntimeException( 'INVALID_CARD_RESOURCE' );
    }
    require_once __DIR__.'/NativeCardRegistryRead.fnc.php';
    $db=['get'=>function($sql){return DBGet(DBQuery($sql));}];
    if(SchoolSafeNativeCardRegistryPresent($db))return SchoolSafePrepareRegistryCard((int)$student_id,$resource,$db);
    // Preserve the existing isolated laboratory reader only when the native
    // registry is provably absent; never hide a failed schema/authority probe.
    $config = json_decode( file_get_contents( '/var/lib/schoolsafe-card-bridge/relay-config.json' ), true, 16, JSON_THROW_ON_ERROR );
    if ( ( $config['environment'] ?? '' ) !== 'local-isolated-test-only'
        || ( $config['sourceInstance'] ?? '' ) !== 'codex-addons-local'
        || ( $config['url'] ?? '' ) !== 'http://codex-card-bridge-http:8080/bridge/cards/prepare'
        || ! preg_match( '/^[a-f0-9]{64}$/D', $config['secretHex'] ?? '' ) )
    {
        throw new RuntimeException( 'BRIDGE_CONFIGURATION_REQUIRED' );
    }
    require_once __DIR__ . '/NativeCardEnvelope.fnc.php';
    $envelope = SchoolSafeNativeCardEnvelope( $student_id, $config['sourceInstance'], hex2bin( $config['secretHex'] ) );
    $url = 'http://codex-card-bridge-http:8080/bridge/cards/' . $resource;
    $curl = curl_init( $url );
    curl_setopt_array( $curl, [ CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [ 'Content-Type: application/json' ],
        CURLOPT_POSTFIELDS => json_encode( $envelope, JSON_THROW_ON_ERROR ),
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP,
        CURLOPT_WRITEFUNCTION => function ( $handle, $chunk ) use ( &$response ) {
            $response = ( $response ?? '' ) . $chunk;
            return strlen( $response ) <= 300000 ? strlen( $chunk ) : 0;
        },
    ] );
    try
    {
        $ok = curl_exec( $curl );
        $status = curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );
        if ( $ok === false ) { throw new RuntimeException( 'CARD_BRIDGE_UNAVAILABLE' ); }
        if ( $status === 403 ) { throw new RuntimeException( 'CARD_BRIDGE_ACCESS_DENIED' ); }
        if ( $status !== 200 ) { throw new RuntimeException( 'CARD_BRIDGE_UNAVAILABLE' ); }
        $result = json_decode( $response ?? '', true, 32, JSON_THROW_ON_ERROR );
        $valid = $resource === 'issuance-check'
            ? is_array( $result['data']['blockers'] ?? null ) && ( $result['data']['operationPerformed'] ?? null ) === false
                && ( $result['data']['issuanceAllowed'] ?? null ) === false
            : ( $resource === 'prepare' || $resource === 'studio-input'
            ? is_array( $result['data']['input'] ?? null ) && ( $result['data']['issuanceAllowed'] ?? null ) === false
            : is_array( $result['data']['credential'] ?? null ) && is_string( $result['data']['studentId'] ?? null ) );
        if ( ! $valid )
        {
            throw new RuntimeException( 'CARD_BRIDGE_RESULT_INVALID' );
        }
        return $result;
    }
    finally { curl_close( $curl ); }
}
