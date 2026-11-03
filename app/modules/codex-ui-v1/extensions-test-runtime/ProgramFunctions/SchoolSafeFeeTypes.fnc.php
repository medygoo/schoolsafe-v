<?php
// Bareme des frais par type (03/11) : valide / tolere / rouge sur la part echue a la date du jour.
// Lecture seule de la facturation native (billing_fees, billing_payments) : aucun paiement ni frais cree.
// Reglages : program_config PROGRAM='schoolsafe_scan' TITLE='FEE_TYPES' (ecole, annee), verrouillable.
require_once __DIR__ . '/SchoolSafeFeeScan.fnc.php';

function SchoolSafeFeeTypesWhere( int $school, int $year ): string
{
    return "PROGRAM='schoolsafe_scan' AND TITLE='FEE_TYPES' AND SCHOOL_ID=" . $school . ' AND SYEAR=' . $year;
}

function SchoolSafeFeeTypesDefault(): array
{
    return [ 'version' => 1, 'locked' => false, 'types' => [], 'canteen' => null ];
}

// Normalise et valide les reglages ; exception en francais si une valeur est invalide.
function SchoolSafeFeeTypesValidate( array $config ): array
{
    $out = SchoolSafeFeeTypesDefault();
    $out['locked'] = ( $config['locked'] ?? false ) === true;
    $ids = [];
    foreach ( (array) ( $config['types'] ?? [] ) as $type )
    {
        $id = (string) ( $type['id'] ?? '' );
        $label = trim( (string) ( $type['label'] ?? '' ) );
        if ( ! preg_match( '/^[a-z0-9-]{1,40}$/D', $id ) || isset( $ids[ $id ] ) ) throw new RuntimeException( 'Identifiant de type invalide.' );
        if ( $label === '' || mb_strlen( $label ) > 60 ) throw new RuntimeException( 'Nom du type de frais requis (60 caractères au plus).' );
        $keywords = [];
        foreach ( (array) ( $type['keywords'] ?? [] ) as $word )
        {
            $word = trim( (string) $word );
            if ( $word !== '' && mb_strlen( $word ) <= 40 ) $keywords[] = $word;
        }
        $keywords = array_slice( array_values( array_unique( $keywords ) ), 0, 10 );
        if ( ! $keywords ) throw new RuntimeException( 'Indiquez au moins un mot-clé pour « ' . $label . ' ».' );
        $validated = $type['validated'] ?? null;
        $tolerated = $type['tolerated'] ?? null;
        if ( ! is_int( $validated ) || ! is_int( $tolerated ) || $validated < 0 || $validated > 100 || $tolerated < 0 || $tolerated > $validated )
            throw new RuntimeException( 'Pourcentages de « ' . $label . ' » : 0 ≤ toléré ≤ validé ≤ 100.' );
        $ids[ $id ] = true;
        $out['types'][] = [ 'id' => $id, 'label' => $label, 'keywords' => $keywords, 'validated' => $validated,
            'tolerated' => $tolerated, 'block_service' => ( $type['block_service'] ?? false ) === true ];
    }
    if ( count( $out['types'] ) > 20 ) throw new RuntimeException( '20 types de frais au plus.' );
    $canteen = $config['canteen'] ?? null;
    if ( is_array( $canteen ) )
    {
        $amount = '/^-?[0-9]{1,12}(?:\.[0-9]{1,2})?$/D';
        $v = (string) ( $canteen['validated'] ?? '' );
        $t = (string) ( $canteen['tolerated'] ?? '' );
        if ( ! preg_match( $amount, $v ) || ! preg_match( $amount, $t ) ) throw new RuntimeException( 'Seuils de cantine : montants invalides.' );
        if ( SchoolSafeFeeSignedCents( $t ) > SchoolSafeFeeSignedCents( $v ) ) throw new RuntimeException( 'Seuils de cantine : toléré ≤ validé.' );
        $out['canteen'] = [ 'validated' => SchoolSafeFeeFormatSigned( SchoolSafeFeeSignedCents( $v ) ),
            'tolerated' => SchoolSafeFeeFormatSigned( SchoolSafeFeeSignedCents( $t ) ) ];
    }
    return $out;
}

function SchoolSafeFeeSignedCents( string $value ): int
{
    $negative = strpos( $value, '-' ) === 0;
    $cents = SchoolSafeFeeCents( ltrim( $value, '-' ) );
    return $negative ? -$cents : $cents;
}

function SchoolSafeFeeFormatSigned( int $cents ): string
{
    return ( $cents < 0 ? '-' : '' ) . SchoolSafeFeeFormatCents( abs( $cents ) );
}

function SchoolSafeFeeTypesRead( int $school, int $year, callable $get ): array
{
    $rows = $get( 'SELECT VALUE FROM program_config WHERE ' . SchoolSafeFeeTypesWhere( $school, $year ) );
    if ( ! is_array( $rows ) || count( $rows ) > 1 ) throw new RuntimeException( 'FEE_TYPES_UNAVAILABLE' );
    if ( ! $rows ) return SchoolSafeFeeTypesDefault();
    $value = json_decode( (string) $rows[1]['VALUE'], true, 16 );
    if ( ! is_array( $value ) ) throw new RuntimeException( 'FEE_TYPES_UNAVAILABLE' );
    return SchoolSafeFeeTypesValidate( $value );
}

// Enregistre les reglages ; refuse toute modification si le bareme est verrouille (sauf le deverrouillage).
function SchoolSafeFeeTypesWrite( int $school, int $year, ?array $config, ?bool $lock, callable $get ): array
{
    $current = SchoolSafeFeeTypesRead( $school, $year, $get );
    if ( $config !== null && $current['locked'] ) throw new RuntimeException( 'Barème verrouillé : déverrouillez-le avant de le modifier.' );
    $next = $config === null ? $current : SchoolSafeFeeTypesValidate( [ 'locked' => $current['locked'] ] + $config );
    if ( $lock !== null ) $next['locked'] = $lock;
    $json = json_encode( $next, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    $sql = "'" . str_replace( "'", "''", $json ) . "'";
    $updated = $get( 'UPDATE program_config SET VALUE=' . $sql . ',UPDATED_AT=CURRENT_TIMESTAMP WHERE ' . SchoolSafeFeeTypesWhere( $school, $year ) . ' RETURNING TITLE' );
    if ( ! is_array( $updated ) || ! isset( $updated[1] ) )
        $get( "INSERT INTO program_config(SYEAR,SCHOOL_ID,PROGRAM,TITLE,VALUE) VALUES(" . $year . ',' . $school . ",'schoolsafe_scan','FEE_TYPES'," . $sql . ') RETURNING TITLE' );
    return $next;
}

function SchoolSafeFeeTypesFold( string $text ): string
{
    $text = mb_strtolower( $text );
    return strtr( $text, [ 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i',
        'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c' ] );
}

// Premier type dont un mot-cle figure dans le titre du frais ; null = « Autres frais ».
function SchoolSafeFeeTypeOf( string $title, array $types ): ?string
{
    $folded = SchoolSafeFeeTypesFold( $title );
    foreach ( $types as $type )
        foreach ( $type['keywords'] as $word )
            if ( strpos( $folded, SchoolSafeFeeTypesFold( $word ) ) !== false ) return $type['id'];
    return null;
}

// Calcul pur (teste) : $fees = [['id','title','cents','due'(AAAA-MM-JJ|null)]] nets des remises ; $paidCents = paye net.
// Les paiements couvrent les frais du plus ancien au plus recent (date d'echeance, sans date = des la creation).
function SchoolSafeFeeTypesClassify( array $fees, int $paidCents, array $types, string $day ): array
{
    $positive = array_values( array_filter( $fees, fn( $fee ) => (int) $fee['cents'] > 0 ) );
    usort( $positive, fn( $a, $b ) => [ (string) ( $a['due'] ?? '' ), (int) $a['id'] ] <=> [ (string) ( $b['due'] ?? '' ), (int) $b['id'] ] );
    $left = max( 0, $paidCents );
    $byType = [];
    foreach ( $types as $type ) $byType[ $type['id'] ] = $type + [ 'due' => 0, 'covered' => 0, 'next_due' => null, 'has_fees' => false ];
    $other = [ 'id' => 'autres', 'label' => 'Autres frais', 'validated' => 100, 'tolerated' => 50, 'block_service' => false,
        'due' => 0, 'covered' => 0, 'next_due' => null, 'has_fees' => false ];
    foreach ( $positive as $fee )
    {
        $cents = (int) $fee['cents'];
        $covered = min( $cents, $left );
        $left -= $covered;
        $id = SchoolSafeFeeTypeOf( (string) $fee['title'], $types ) ?? 'autres';
        if ( $id === 'autres' ) $byType['autres'] = $byType['autres'] ?? $other;
        $byType[ $id ]['has_fees'] = true;
        $due = $fee['due'] ?? null;
        if ( $due === null || $due === '' || strcmp( $due, $day ) <= 0 )
        {
            $byType[ $id ]['due'] += $cents;
            $byType[ $id ]['covered'] += $covered;
        }
        elseif ( $byType[ $id ]['next_due'] === null || strcmp( $due, $byType[ $id ]['next_due'] ) < 0 ) $byType[ $id ]['next_due'] = $due;
    }
    $rank = [ 'green' => 0, 'yellow' => 1, 'red' => 2 ];
    $worst = 'green';
    $result = [];
    foreach ( $byType as $id => $type )
    {
        if ( ! $type['has_fees'] ) continue;
        $pct = $type['due'] === 0 ? 100 : intdiv( $type['covered'] * 100, $type['due'] );
        $color = $pct >= $type['validated'] ? 'green' : ( $pct >= $type['tolerated'] ? 'yellow' : 'red' );
        if ( $rank[ $color ] > $rank[ $worst ] ) $worst = $color;
        $result[ $id ] = [ 'id' => $id, 'label' => $type['label'], 'color' => $color, 'percent' => $pct,
            'due_cents' => $type['due'], 'covered_cents' => $type['covered'], 'remaining_cents' => $type['due'] - $type['covered'],
            'next_due' => $type['next_due'], 'block_service' => $color === 'red' && $type['block_service'] ];
    }
    return [ 'color' => $result ? $worst : null, 'types' => array_values( $result ) ];
}

// Lecture native : frais nets des remises (avec titre et echeance) et total paye net, pour un eleve.
function SchoolSafeFeeTypesLedger( int $student, int $school, int $year, callable $get ): array
{
    $where = 'STUDENT_ID=' . $student . ' AND SCHOOL_ID=' . $school . ' AND SYEAR=' . $year;
    $rows = $get( "SELECT 'fee' AS KIND,ID,TITLE,AMOUNT,WAIVED_FEE_ID,DUE_DATE FROM billing_fees WHERE " . $where
        . " UNION ALL SELECT 'payment' AS KIND,ID,NULL AS TITLE,AMOUNT,NULL AS WAIVED_FEE_ID,NULL AS DUE_DATE FROM billing_payments WHERE " . $where );
    if ( ! is_array( $rows ) ) throw new RuntimeException( 'FEE_READ_UNAVAILABLE' );
    $fees = []; $adjust = []; $paid = 0;
    foreach ( $rows as $row )
    {
        $cents = SchoolSafeFeeSignedCents( (string) $row['AMOUNT'] );
        if ( $row['KIND'] === 'payment' ) { $paid += $cents; continue; }
        if ( isset( $row['WAIVED_FEE_ID'] ) && $row['WAIVED_FEE_ID'] !== '' ) { $adjust[ (int) $row['WAIVED_FEE_ID'] ] = ( $adjust[ (int) $row['WAIVED_FEE_ID'] ] ?? 0 ) + $cents; continue; }
        $fees[ (int) $row['ID'] ] = [ 'id' => (int) $row['ID'], 'title' => (string) $row['TITLE'], 'cents' => $cents,
            'due' => is_string( $row['DUE_DATE'] ) && $row['DUE_DATE'] !== '' ? substr( $row['DUE_DATE'], 0, 10 ) : null ];
    }
    foreach ( $adjust as $id => $cents ) if ( isset( $fees[ $id ] ) ) $fees[ $id ]['cents'] += $cents;
    return [ 'fees' => array_values( $fees ), 'paid_cents' => $paid, 'has_data' => (bool) $rows ];
}

// Etat des frais par type d'un eleve (null si aucun bareme par type n'est regle).
function SchoolSafeFeeTypesStatus( int $student, int $school, int $year, callable $get, string $day ): ?array
{
    $config = SchoolSafeFeeTypesRead( $school, $year, $get );
    if ( ! $config['types'] ) return null;
    $ledger = SchoolSafeFeeTypesLedger( $student, $school, $year, $get );
    if ( ! $ledger['has_data'] ) return [ 'color' => null, 'types' => [] ];
    return SchoolSafeFeeTypesClassify( $ledger['fees'], $ledger['paid_cents'], $config['types'], $day );
}

// Resume pour l'ecran du scanner : couleur par type ; montants seulement si l'agent a le droit de les voir.
function SchoolSafeFeeTypesSummary( ?array $status, bool $detail ): array
{
    $out = [];
    foreach ( (array) ( $status['types'] ?? [] ) as $type )
    {
        $row = [ 'label' => $type['label'], 'color' => $type['color'], 'block_service' => $type['block_service'] ];
        if ( $detail ) $row += [ 'percent' => $type['percent'], 'remaining' => SchoolSafeFeeFormatCents( $type['remaining_cents'] ), 'next_due' => $type['next_due'] ];
        $out[] = $row;
    }
    return $out;
}

function SchoolSafeFeeTypesDirection( int $school, int $year, callable $get ): array
{
    $recipients = [];
    foreach ( (array) $get( "SELECT STAFF_ID,SCHOOLS FROM staff WHERE PROFILE='admin' AND SYEAR=" . $year ) as $admin )
        if ( preg_match( '/(^|,)' . $school . '(,|$)/', trim( (string) ( $admin['SCHOOLS'] ?? '' ), ',' ) ) )
            $recipients[] = [ 'audience' => 'direction', 'staff_id' => (int) $admin['STAFF_ID'], 'channel' => 'feed' ];
    return $recipients;
}

// Rappels apres un scan : jaune = rappel au parent ; rouge = parent + economat (direction).
// Au plus un message par type, par couleur et par semaine (cle de deduplication).
function SchoolSafeFeeTypesAlert( int $student, int $school, int $year, array $status, int $actor, array $db ): int
{
    require_once __DIR__ . '/SchoolSafeNotify.fnc.php';
    $get = $db['get'];
    $who = $get( 'SELECT s.FIRST_NAME,s.LAST_NAME,sc.TITLE FROM students s JOIN schools sc ON sc.ID=' . $school . ' AND sc.SYEAR=' . $year . ' WHERE s.STUDENT_ID=' . $student );
    if ( ! is_array( $who ) || ! isset( $who[1] ) ) return 0;
    $first = trim( (string) $who[1]['FIRST_NAME'] ) ?: 'Votre enfant';
    $full = trim( $who[1]['FIRST_NAME'] . ' ' . $who[1]['LAST_NAME'] );
    $sign = "\n— " . $who[1]['TITLE'] . ' · SchoolSafe';
    $week = date( 'o-W' ); $sent = 0;
    foreach ( (array) ( $status['types'] ?? [] ) as $type )
    {
        if ( ! in_array( $type['color'], [ 'yellow', 'red' ], true ) ) continue;
        $label = $type['label'];
        $message = $type['color'] === 'yellow'
            ? '🟡 Rappel de l’école : les frais « ' . $label . ' » de ' . $first . ' ne sont pas tout à fait à jour. Merci de passer à l’économat.' . $sign
            : '🔴 Les frais « ' . $label . ' » de ' . $first . ' sont en retard. Merci de régulariser auprès de l’économat.'
                . ( $type['block_service'] ? ' Le service « ' . $label . ' » reste suspendu jusqu’au paiement.' : '' ) . $sign;
        $recipients = SchoolSafeNotifyResolveRecipients( 'fee.reminder', $school, $year, $student, $db );
        if ( $type['color'] === 'red' ) $recipients = array_merge( $recipients, SchoolSafeFeeTypesDirection( $school, $year, $get ) );
        if ( ! $recipients ) continue;
        $out = SchoolSafeNotifyEmit( [ 'school_id' => $school, 'syear' => $year, 'student_id' => $student, 'actor_staff_id' => $actor ?: null,
            'event_type' => 'fee.reminder', 'dedup_key' => 'fee:' . $school . ':' . $student . ':' . $type['id'] . ':' . $week . ':' . $type['color'],
            'payload' => [ 'source' => 'fee_types', 'title' => $type['color'] === 'red' ? 'Frais en retard' : 'Rappel de frais', 'message' => $message,
                'staff_note' => $full . ' : ' . $label . ', ' . $type['percent'] . ' % de la part échue payé (reste ' . SchoolSafeFeeFormatCents( $type['remaining_cents'] ) . ').' ] ],
            $recipients, $db, true );
        if ( is_array( $out ) && ( $out['created'] ?? false ) ) $sent++;
    }
    return $sent;
}

// Cantine en rouge : le repas est servi ; parent et economat sont prevenus (une fois par semaine).
function SchoolSafeCanteenAlert( int $student, int $school, int $year, int $actor, array $db ): ?string
{
    require_once __DIR__ . '/SchoolSafeNotify.fnc.php';
    $get = $db['get'];
    $config = SchoolSafeFeeTypesRead( $school, $year, $get );
    if ( ! is_array( $config['canteen'] ) ) return null;
    $rows = $get( 'SELECT b.BALANCE FROM food_service_student_accounts a JOIN food_service_accounts b ON b.ACCOUNT_ID=a.ACCOUNT_ID WHERE a.STUDENT_ID=' . $student );
    if ( ! is_array( $rows ) || count( $rows ) !== 1 ) return null;
    $color = SchoolSafeCanteenColor( SchoolSafeFeeSignedCents( (string) $rows[1]['BALANCE'] ), $config['canteen'] );
    if ( $color !== 'red' ) return $color;
    $who = $get( 'SELECT s.FIRST_NAME,sc.TITLE FROM students s JOIN schools sc ON sc.ID=' . $school . ' AND sc.SYEAR=' . $year . ' WHERE s.STUDENT_ID=' . $student );
    $first = trim( (string) ( $who[1]['FIRST_NAME'] ?? '' ) ) ?: 'Votre enfant';
    $recipients = array_merge( SchoolSafeNotifyResolveRecipients( 'fee.reminder', $school, $year, $student, $db ), SchoolSafeFeeTypesDirection( $school, $year, $get ) );
    if ( $recipients ) SchoolSafeNotifyEmit( [ 'school_id' => $school, 'syear' => $year, 'student_id' => $student, 'actor_staff_id' => $actor ?: null,
        'event_type' => 'fee.reminder', 'dedup_key' => 'canteen:' . $school . ':' . $student . ':' . date( 'o-W' ),
        'payload' => [ 'source' => 'canteen', 'title' => 'Compte cantine insuffisant',
            'message' => '🔴 Le compte cantine de ' . $first . ' est insuffisant. Le repas est servi ; merci de recharger le compte auprès de l’économat.'
                . "\n— " . ( $who[1]['TITLE'] ?? '' ) . ' · SchoolSafe' ] ], $recipients, $db, true );
    return $color;
}

// Couleur du compte cantine selon les seuils de l'ecole (null si non regle).
function SchoolSafeCanteenColor( int $balanceCents, ?array $canteen ): ?string
{
    if ( ! is_array( $canteen ) ) return null;
    if ( $balanceCents >= SchoolSafeFeeSignedCents( $canteen['validated'] ) ) return 'green';
    return $balanceCents >= SchoolSafeFeeSignedCents( $canteen['tolerated'] ) ? 'yellow' : 'red';
}
