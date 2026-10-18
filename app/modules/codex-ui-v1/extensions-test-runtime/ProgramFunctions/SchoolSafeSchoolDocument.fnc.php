<?php

function SchoolSafeDocumentEscape( $text )
{
    return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function SchoolSafeDocumentImage( $image, $mode = 'logo' )
{
    if ( ! is_string( $image ) || strlen( $image ) > 1500000 ||
        ! preg_match( '/^data:image\/(jpeg|png);base64,[A-Za-z0-9+\/=]+$/D', $image ) ) { return ''; }
    return '<img class="ss-document-' . ( $mode === 'photo' ? 'photo' : 'logo' ) . '" alt="' .
        ( $mode === 'photo' ? 'Photo' : 'Logo de l’école' ) . '" src="' . SchoolSafeDocumentEscape( $image ) . '">';
}

function SchoolSafeDocumentHeader( array $identity )
{
    $image = SchoolSafeDocumentImage( $identity['logo'] ?? '' );
    $parts = array_values( array_filter( array_map( 'trim', explode( ' • ', $identity['slogan'] ?? '' ) ), 'strlen' ) );
    $slogans = '';
    foreach ( $parts as $part ) { $slogans .= '<span>' . SchoolSafeDocumentEscape( $part ) . '</span>'; }
    return ( $image ? '<div class="ss-document-watermark" aria-hidden="true">' . $image . '</div>' : '' ) .
        '<header class="ss-document-header">' . $image . '<div class="school">' .
        SchoolSafeDocumentEscape( $identity['school'] ?? '' ) . '</div><div class="ss-document-slogans">' . $slogans . '</div></header>';
}

function SchoolSafeDocumentFooter( array $identity )
{
    $address = implode( ' • ', array_filter( [ $identity['address'] ?? '', $identity['city'] ?? '' ] ) );
    $contact = implode( ' • ', array_filter( [ $identity['phone'] ?? '', $identity['email'] ?? '', $identity['website'] ?? '' ] ) );
    $product = '';
    if ( is_file( 'schoolsafe-logo.png' ) && filesize( 'schoolsafe-logo.png' ) < 200000 )
    {
        $product = '<img alt="SchoolSafe" src="data:image/png;base64,' . base64_encode( file_get_contents( 'schoolsafe-logo.png' ) ) . '">';
    }
    return '<footer>' . SchoolSafeDocumentEscape( $address ) . '<br>' . SchoolSafeDocumentEscape( $contact ) .
        '<a class="product-brand" href="https://shoolsafe.tech/" target="_blank" rel="noopener noreferrer">' . $product . 'SchoolSafe · shoolsafe.tech</a></footer>';
}

function SchoolSafeDocumentIdentityCss()
{
    return '.ss-document-header{display:flex;gap:4mm;align-items:center;position:relative;z-index:1}.ss-document-logo{width:30mm;height:30mm;object-fit:contain;flex-shrink:0}.ss-document-photo{width:24mm;height:32mm;object-fit:cover;object-position:center}.ss-document-header .school{flex:1;min-width:0;font-size:16pt;overflow-wrap:anywhere}.ss-document-slogans{max-width:34mm;font-size:9pt;line-height:1.5;overflow-wrap:anywhere;flex-shrink:0}.ss-document-slogans span{display:block}.ss-document-watermark{position:fixed;top:35%;left:50%;transform:translateX(-50%);pointer-events:none;z-index:0}.ss-document-watermark img{width:85mm;height:85mm;object-fit:contain;opacity:.08;print-color-adjust:exact;-webkit-print-color-adjust:exact}.identity,.totals,table,h1,.meta,.signatures,footer,.ss-homework-content{position:relative;z-index:1}footer{background:transparent}.product-brand{display:block;margin-top:2mm;font-size:8pt}.product-brand img{width:8mm;height:8mm;object-fit:contain;vertical-align:middle;margin-right:2mm}';
}

function SchoolSafeDocumentSloganPost( array $post, $fallback = '' )
{
    if ( ! isset( $post['school_slogan'] ) && ! isset( $post['school_slogan_extra'] ) ) { return $fallback; }
    $previous = explode( ' • ', $fallback );
    $first = $post['school_slogan'] ?? ( $previous[0] ?? '' );
    if ( ! is_string( $first ) ) { return "\0"; }
    $parts = [ trim( $first ) ];
    $extra = $post['school_slogan_extra'] ?? [];
    if ( ! is_array( $extra ) || count( $extra ) > 3 ) { return "\0"; }
    foreach ( array_keys( $extra ) as $key )
    {
        if ( ! in_array( $key, [ 0, 1, 2 ], true ) ) { return "\0"; }
    }
    for ( $index = 0; $index < 3; $index++ )
    {
        $part = $extra[$index] ?? ( $previous[$index + 1] ?? '' );
        if ( ! is_string( $part ) ) { return "\0"; }
        $parts[] = trim( $part );
    }
    return implode( ' • ', array_filter( $parts, 'strlen' ) );
}

function SchoolSafeHomeworkContent( $html )
{
    $dom = new DOMDocument();
    $old = libxml_use_internal_errors( true );
    $dom->loadHTML( '<?xml encoding="UTF-8"><div id="ss-content">' . $html . '</div>', LIBXML_NONET );
    libxml_clear_errors(); libxml_use_internal_errors( $old );
    $xpath = new DOMXPath( $dom );
    foreach ( $xpath->query( '//script|//style|//iframe|//object|//embed|//form|//input|//link|//meta|//base' ) as $node ) { $node->parentNode->removeChild( $node ); }
    foreach ( $xpath->query( '//*[@*]' ) as $node )
    {
        foreach ( iterator_to_array( $node->attributes ) as $attribute )
        {
            if ( $node->nodeName === 'img' && $attribute->nodeName === 'src' &&
                preg_match( '/^data:image\/(jpeg|png);base64,[A-Za-z0-9+\/=]+$/D', $attribute->nodeValue ) && strlen( $attribute->nodeValue ) < 1500000 ) { continue; }
            if ( in_array( $node->nodeName, [ 'ol', 'li' ], true ) && in_array( $attribute->nodeName, [ 'start', 'value' ], true ) && ctype_digit( $attribute->nodeValue ) ) { continue; }
            $node->removeAttribute( $attribute->nodeName );
        }
    }
    $body = $dom->getElementsByTagName( 'body' )->item( 0 );
    $output = '';
    if ( $body ) { foreach ( $body->childNodes as $node ) { $output .= $dom->saveHTML( $node ); } }
    return $output;
}

function SchoolSafeHomeworkHtml( array $identity, array $assignment )
{
    $accent = preg_match( '/^#[0-9a-f]{6}$/iD', $identity['accent'] ?? '' ) ? $identity['accent'] : '#176b52';
    $title = SchoolSafeDocumentEscape( $assignment['TITLE'] ?? '' );
    return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>' . $title . '</title><style>' .
        '@page{size:A4 portrait;margin:12mm}*{box-sizing:border-box}body{margin:0;font:11pt/1.5 Arial,sans-serif;color:#142234;background:white}.document{width:186mm;margin:auto}header{border-bottom:2px solid ' . $accent . ';padding-bottom:5mm}h1{font-size:20pt;margin:6mm 0}.ss-homework-content{padding:5mm 0;min-height:100mm;overflow-wrap:anywhere}.ss-homework-content img{max-width:100%;max-height:100mm;object-fit:contain}.ss-homework-content table{width:100%;border-collapse:collapse}.ss-homework-content td{border:1px solid #bac6cc;padding:2mm}.ss-homework-content ol{padding-left:8mm}.ss-homework-content li{margin:3mm 0;break-inside:avoid}footer{margin-top:6mm;border-top:2px solid ' . $accent . ';padding-top:3mm;text-align:center;font-size:8pt;break-inside:avoid}@media screen{body{background:#eef1f4;padding:20px}.document{width:210mm;padding:12mm;background:#fff}}' .
        SchoolSafeDocumentIdentityCss() . '</style></head><body><article class="document">' . SchoolSafeDocumentHeader( $identity ) .
        '<h1>DEVOIR N° ' . (int) $assignment['ASSIGNMENT_ID'] . ' — ' . $title . '</h1><div>Matière : ' . SchoolSafeDocumentEscape( $assignment['COURSE_TITLE'] ?? '' ) .
        '<br>Attribué le : ' . SchoolSafeDocumentEscape( $assignment['ASSIGNED_DATE'] ?? '' ) . ' • À rendre le : ' . SchoolSafeDocumentEscape( $assignment['DUE_DATE'] ?? '' ) .
        '</div><section class="ss-homework-content">' . SchoolSafeHomeworkContent( $assignment['DESCRIPTION'] ?? '' ) . '</section>' .
        SchoolSafeDocumentFooter( $identity ) . '</article></body></html>';
}

function SchoolSafeDocumentPdf( $html )
{
    $directory = sys_get_temp_dir() . '/schoolsafe-document-' . bin2hex( random_bytes( 12 ) );
    if ( ! mkdir( $directory, 0700 ) ) { throw new RuntimeException( 'PDF unavailable' ); }
    $process = null;
    try
    {
        file_put_contents( $directory . '/document.html', $html );
        chmod( $directory . '/document.html', 0600 );
        $pipes = [];
        $process = proc_open( [ '/usr/bin/chromium', '--headless', '--no-sandbox', '--disable-gpu', '--disable-background-networking', '--host-resolver-rules=MAP * ~NOTFOUND', '--no-pdf-header-footer', '--user-data-dir=' . $directory . '/profile', '--print-to-pdf=' . $directory . '/document.pdf', 'file://' . $directory . '/document.html' ], [ 0 => [ 'file', '/dev/null', 'r' ], 1 => [ 'file', $directory . '/stdout', 'w' ], 2 => [ 'file', $directory . '/stderr', 'w' ] ], $pipes );
        if ( ! is_resource( $process ) ) { throw new RuntimeException( 'PDF unavailable' ); }
        $deadline = microtime( true ) + 25;
        while ( proc_get_status( $process )['running'] )
        {
            if ( microtime( true ) > $deadline ) { throw new RuntimeException( 'PDF timeout' ); }
            usleep( 100000 );
        }
        proc_close( $process ); $process = null;
        if ( ! is_file( $directory . '/document.pdf' ) ) { throw new RuntimeException( 'PDF unavailable' ); }
        $bytes = file_get_contents( $directory . '/document.pdf' );
        if ( substr( $bytes, 0, 5 ) !== '%PDF-' ) { throw new RuntimeException( 'Invalid PDF' ); }
        return $bytes;
    }
    finally
    {
        if ( is_resource( $process ) ) { proc_terminate( $process, 9 ); proc_close( $process ); }
        $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
        foreach ( $iterator as $item ) { $item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() ); }
        rmdir( $directory );
    }
}

function SchoolSafeHomeworkNotifications()
{
    if ( User( 'PROFILE' ) !== 'parent' || ! UserStudentID() || ! AllowUse( 'Grades/StudentAssignments.php' ) ) { return; }
    if ( ! DBGetOne( "SELECT 1 FROM students_join_users WHERE student_id='" . (int) UserStudentID() . "' AND staff_id='" . (int) User( 'STAFF_ID' ) . "'" ) ) { return; }
    require_once 'modules/Grades/includes/StudentAssignments.fnc.php';
    if ( ! UserMP() || ! GetAllMP( 'QTR', UserMP() ) ) { return; }
    $records = DBGet( "SELECT DISTINCT ga.assignment_id,ga.assigned_date FROM gradebook_assignments ga JOIN schedule ss ON (ga.course_period_id IS NULL OR ss.course_period_id=ga.course_period_id) AND (ga.course_id IS NULL OR ss.course_id=ga.course_id) WHERE ss.student_id='" . (int) UserStudentID() . "' AND ss.school_id='" . (int) UserSchool() . "' AND ss.syear='" . (int) UserSyear() . "' AND ga.marking_period_id='" . (int) UserMP() . "' AND ss.marking_period_id IN (" . GetAllMP( 'QTR', UserMP() ) . ") AND (ga.assigned_date IS NULL OR ga.assigned_date<=CURRENT_DATE) ORDER BY ga.assigned_date DESC NULLS LAST,ga.assignment_id DESC LIMIT 10" );
    $visible = [];
    foreach ( $records as $record )
    {
        $assignment = GetAssignment( (string) $record['ASSIGNMENT_ID'] );
        if ( $assignment ) { $visible[] = $assignment; }
    }
    if ( ! $visible ) { return; }
    echo '<section aria-label="Devoirs disponibles"><h3>Devoirs disponibles pour l’enfant sélectionné</h3><ul>';
    foreach ( $visible as $assignment )
    {
        echo '<li><strong>' . SchoolSafeDocumentEscape( $assignment['TITLE'] ) . '</strong> — ' . SchoolSafeDocumentEscape( $assignment['DUE_DATE'] ?? '' ) .
            ' · <a href="schoolsafe-homework.php?assignment_id=' . (int) $assignment['ASSIGNMENT_ID'] . '&amp;download=1">Télécharger le devoir A4</a></li>';
    }
    echo '</ul></section>';
}
