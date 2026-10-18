<?php

function SchoolSafeSchoolEmail( $school_id )
{
    return (string) DBGetOne( "SELECT CONFIG_VALUE FROM config WHERE SCHOOL_ID='" .
        (int) $school_id . "' AND TITLE='SCHOOLSAFE_SCHOOL_EMAIL'" );
}

function SchoolSafeSchoolEmailValid( $email )
{
    return $email === '' || ( strlen( $email ) <= 254 && filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false );
}

function SchoolSafeSaveSchoolEmail( $school_id, $email )
{
    $email = trim( (string) $email );
    if ( ! SchoolSafeSchoolEmailValid( $email ) )
    {
        return false;
    }
    return SchoolSafeSaveSchoolIdentitySetting( $school_id, 'SCHOOLSAFE_SCHOOL_EMAIL', $email );
}

function SchoolSafeSchoolSlogan( $school_id )
{
    return (string) DBGetOne( "SELECT CONFIG_VALUE FROM config WHERE SCHOOL_ID='" .
        (int) $school_id . "' AND TITLE='SCHOOLSAFE_SCHOOL_SLOGAN'" );
}

function SchoolSafeSchoolSloganValid( $slogan )
{
    return mb_check_encoding( $slogan, 'UTF-8' ) && mb_strlen( $slogan, 'UTF-8' ) <= 180 &&
        ! preg_match( '/[\x00-\x1F\x7F]/', $slogan );
}

function SchoolSafeSaveSchoolSlogan( $school_id, $slogan )
{
    $slogan = trim( (string) $slogan );
    if ( ! SchoolSafeSchoolSloganValid( $slogan ) )
    {
        return false;
    }
    return SchoolSafeSaveSchoolIdentitySetting( $school_id, 'SCHOOLSAFE_SCHOOL_SLOGAN', $slogan );
}

function SchoolSafeSaveSchoolIdentitySetting( $school_id, $key, $value )
{
    if ( ! in_array( $key, [ 'SCHOOLSAFE_SCHOOL_EMAIL', 'SCHOOLSAFE_SCHOOL_SLOGAN', 'SCHOOLSAFE_RECEIPT_LOGO', 'SCHOOLSAFE_RECEIPT_ACCENT' ], true ) )
    {
        return false;
    }
    $school_id = (int) $school_id;
    $where = [ 'SCHOOL_ID' => $school_id, 'TITLE' => $key ];
    $exists = DBGetOne( "SELECT 1 FROM config WHERE SCHOOL_ID='" . $school_id .
        "' AND TITLE='" . $key . "'" );
    if ( $exists )
    {
        DBUpdate( 'config', [ 'CONFIG_VALUE' => $value ], $where );
    }
    else
    {
        DBInsert( 'config', $where + [ 'CONFIG_VALUE' => $value ] );
    }
    return true;
}

function SchoolSafeUpdateReceiptBrand( $school_id )
{
    $path = 'assets/school_logo_' . (int) $school_id . '.jpg';
    if ( ! is_file( $path ) || ! function_exists( 'imagecreatefromjpeg' ) ) { return; }
    $source = @imagecreatefromjpeg( $path );
    if ( ! $source ) { return; }
    $width = imagesx( $source );
    $height = imagesy( $source );
    $scale = min( 256 / $width, 256 / $height, 1 );
    $logo = imagecreatetruecolor( max( 1, (int) round( $width * $scale ) ), max( 1, (int) round( $height * $scale ) ) );
    imagecopyresampled( $logo, $source, 0, 0, 0, 0, imagesx( $logo ), imagesy( $logo ), $width, $height );
    ob_start();
    imagejpeg( $logo, null, 90 );
    $bytes = ob_get_clean();
    // Sample saturated pixels; ignore white backgrounds and neutral lettering.
    $bins = [];
    for ( $y = 0; $y < imagesy( $logo ); $y += 4 )
    {
        for ( $x = 0; $x < imagesx( $logo ); $x += 4 )
        {
            $pixel = imagecolorat( $logo, $x, $y );
            $r = ( $pixel >> 16 ) & 255; $g = ( $pixel >> 8 ) & 255; $b = $pixel & 255;
            if ( max( $r, $g, $b ) - min( $r, $g, $b ) < 35 || max( $r, $g, $b ) < 45 ) { continue; }
            $key = ( $r >> 5 ) . ',' . ( $g >> 5 ) . ',' . ( $b >> 5 );
            $bins[$key] = ( $bins[$key] ?? 0 ) + 1;
        }
    }
    $accent = '#176b52';
    if ( $bins )
    {
        arsort( $bins );
        $rgb = array_map( static function ( $v ) { return (int) $v * 32 + 16; }, explode( ',', array_key_first( $bins ) ) );
        $factor = min( 1, 145 / max( $rgb ) );
        $accent = sprintf( '#%02x%02x%02x', (int) ( $rgb[0] * $factor ), (int) ( $rgb[1] * $factor ), (int) ( $rgb[2] * $factor ) );
    }
    imagedestroy( $source ); imagedestroy( $logo );
    SchoolSafeSaveSchoolIdentitySetting( $school_id, 'SCHOOLSAFE_RECEIPT_LOGO', 'data:image/jpeg;base64,' . base64_encode( $bytes ) );
    SchoolSafeSaveSchoolIdentitySetting( $school_id, 'SCHOOLSAFE_RECEIPT_ACCENT', $accent );
}
