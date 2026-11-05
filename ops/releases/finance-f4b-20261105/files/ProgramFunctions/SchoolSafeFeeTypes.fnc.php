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

// Rattrapage exclu (04/11) : jamais bloquant pour l'enfant, suivi dans ses propres paiements.
// Lecture native : frais nets des remises (avec titre et echeance) et total paye net, pour un eleve.
function SchoolSafeFeeTypesLedger( int $student, int $school, int $year, callable $get ): array
{
    $where = 'STUDENT_ID=' . $student . ' AND SCHOOL_ID=' . $school . ' AND SYEAR=' . $year;
    // Lot F4b (05/11) : avec les deux monnaies, frais et paiements sont aussi regroupés par monnaie ('by_currency').
    // 'fees' et 'paid_cents' ne gardent alors que le USD (jamais d'addition FC + USD).
    $multiCurrency = SchoolSafeFeeCurrencyReady( $get );
    $currency = $multiCurrency ? ',CURRENCY' : '';
    $rows = $get( "SELECT 'fee' AS KIND,ID,TITLE,AMOUNT,WAIVED_FEE_ID,DUE_DATE" . $currency . " FROM billing_fees WHERE " . $where . " AND NOT EXISTS(SELECT 1 FROM schoolsafe_remediation_cases rc WHERE rc.fee_id IN (billing_fees.id,billing_fees.waived_fee_id))"
        . " UNION ALL SELECT 'payment' AS KIND,ID,NULL AS TITLE,AMOUNT,NULL AS WAIVED_FEE_ID,NULL AS DUE_DATE" . $currency . " FROM billing_payments WHERE " . $where . " AND NOT EXISTS(SELECT 1 FROM schoolsafe_remediation_payment_purpose rp WHERE rp.payment_id IN (billing_payments.id,billing_payments.refunded_payment_id))" );
    if ( ! is_array( $rows ) ) throw new RuntimeException( 'FEE_READ_UNAVAILABLE' );
    $fees = []; $adjust = []; $paid = [];
    foreach ( $rows as $row )
    {
        $code = $multiCurrency && strtoupper( trim( (string) ( $row['CURRENCY'] ?? '' ) ) ) === 'FC' ? 'FC' : 'USD';
        $cents = SchoolSafeFeeSignedCents( (string) $row['AMOUNT'] );
        if ( $row['KIND'] === 'payment' ) { $paid[ $code ] = ( $paid[ $code ] ?? 0 ) + $cents; continue; }
        // Une remise suit la monnaie du frais qu'elle réduit (même élève, même écriture d'origine).
        if ( isset( $row['WAIVED_FEE_ID'] ) && $row['WAIVED_FEE_ID'] !== '' ) { $adjust[ (int) $row['WAIVED_FEE_ID'] ] = ( $adjust[ (int) $row['WAIVED_FEE_ID'] ] ?? 0 ) + $cents; continue; }
        $fees[ (int) $row['ID'] ] = [ 'id' => (int) $row['ID'], 'title' => (string) $row['TITLE'], 'cents' => $cents,
            'due' => is_string( $row['DUE_DATE'] ) && $row['DUE_DATE'] !== '' ? substr( $row['DUE_DATE'], 0, 10 ) : null, 'currency' => $code ];
    }
    foreach ( $adjust as $id => $cents ) if ( isset( $fees[ $id ] ) ) $fees[ $id ]['cents'] += $cents;
    if ( ! $multiCurrency || ! in_array( 'FC', array_merge( array_column( $fees, 'currency' ), array_keys( $paid ) ), true ) )
    {
        // Comportement d'origine (une seule monnaie, ou aucune ligne en FC) : lignes de frais sans clé de monnaie.
        foreach ( $fees as $id => $fee ) unset( $fees[ $id ]['currency'] );
        return [ 'fees' => array_values( $fees ), 'paid_cents' => $paid['USD'] ?? 0, 'has_data' => (bool) $rows ];
    }
    $byCurrency = [];
    foreach ( $fees as $fee ) $byCurrency[ $fee['currency'] ]['fees'][] = $fee;
    foreach ( $paid as $code => $cents ) $byCurrency[ $code ]['paid_cents'] = $cents;
    foreach ( $byCurrency as $code => $part ) $byCurrency[ $code ] += [ 'fees' => [], 'paid_cents' => 0 ];
    uksort( $byCurrency, fn( $a, $b ) => ( $a === 'USD' ? 0 : 1 ) <=> ( $b === 'USD' ? 0 : 1 ) );
    return [ 'fees' => $byCurrency['USD']['fees'] ?? [], 'paid_cents' => $byCurrency['USD']['paid_cents'] ?? 0,
        'has_data' => (bool) $rows, 'by_currency' => $byCurrency ];
}

// Lot F4b (05/11) : classement par monnaie (frais d'une monnaie contre paiements de la même monnaie), puis pour
// chaque type la pire couleur des monnaies (rouge > jaune > vert). Même structure que SchoolSafeFeeTypesClassify ;
// chaque type porte en plus 'currency' (monnaie dont viennent ses montants).
function SchoolSafeFeeTypesClassifyByCurrency( array $byCurrency, array $types, string $day ): array
{
    $rank = [ 'green' => 0, 'yellow' => 1, 'red' => 2 ];
    $merged = []; $worst = null;
    foreach ( $byCurrency as $code => $part )
    {
        $result = SchoolSafeFeeTypesClassify( $part['fees'] ?? [], (int) ( $part['paid_cents'] ?? 0 ), $types, $day );
        if ( $result['color'] !== null && ( $worst === null || $rank[ $result['color'] ] > $rank[ $worst ] ) ) $worst = $result['color'];
        foreach ( $result['types'] as $type )
            if ( ! isset( $merged[ $type['id'] ] ) || $rank[ $type['color'] ] > $rank[ $merged[ $type['id'] ]['color'] ] )
                $merged[ $type['id'] ] = $type + [ 'currency' => $code ];
    }
    // Ordre d'origine : types du barème, puis « Autres frais ».
    $order = array_flip( array_merge( array_column( $types, 'id' ), [ 'autres' ] ) );
    uksort( $merged, fn( $a, $b ) => ( $order[ $a ] ?? PHP_INT_MAX ) <=> ( $order[ $b ] ?? PHP_INT_MAX ) );
    return [ 'color' => $merged ? $worst : null, 'types' => array_values( $merged ) ];
}

// Etat des frais par type d'un eleve (null si aucun bareme par type n'est regle).
function SchoolSafeFeeTypesStatus( int $student, int $school, int $year, callable $get, string $day ): ?array
{
    $config = SchoolSafeFeeTypesRead( $school, $year, $get );
    if ( ! $config['types'] ) return null;
    $ledger = SchoolSafeFeeTypesLedger( $student, $school, $year, $get );
    if ( ! $ledger['has_data'] ) return [ 'color' => null, 'types' => [] ];
    // Lot F4b (05/11) : deux monnaies => classement par monnaie, pire couleur gardée pour chaque type.
    if ( isset( $ledger['by_currency'] ) ) return SchoolSafeFeeTypesClassifyByCurrency( $ledger['by_currency'], $config['types'], $day );
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
        // Lot F4b (05/11) : monnaie du reste affiché (seulement quand les deux monnaies sont en service).
        if ( $detail && isset( $type['currency'] ) ) $row['currency'] = $type['currency'];
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
    $sign = "\n— " . $who[1]['TITLE'] . ' · SchoolSafe · Un enfant protégé, un parent informé';
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
                // Lot F4b (05/11) : la monnaie du reste est précisée quand elle est connue.
                'staff_note' => $full . ' : ' . $label . ', ' . $type['percent'] . ' % de la part échue payé (reste ' . SchoolSafeFeeFormatCents( $type['remaining_cents'] ) . ( isset( $type['currency'] ) ? ' ' . $type['currency'] : '' ) . ').' ] ],
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
                . "\n— " . ( $who[1]['TITLE'] ?? '' ) . ' · SchoolSafe · Un enfant protégé, un parent informé' ] ], $recipients, $db, true );
    return $color;
}

// Couleur du compte cantine selon les seuils de l'ecole (null si non regle).
function SchoolSafeCanteenColor( int $balanceCents, ?array $canteen ): ?string
{
    if ( ! is_array( $canteen ) ) return null;
    if ( $balanceCents >= SchoolSafeFeeSignedCents( $canteen['validated'] ) ) return 'green';
    return $balanceCents >= SchoolSafeFeeSignedCents( $canteen['tolerated'] ) ? 'yellow' : 'red';
}

// Tracabilite (03/11) : une ligne par type ajoutee a l'instantane financier existant (version 1 conservee,
// anciens controles lisibles). Fees : complete la consultation ; entree : complete la consultation liee ou
// en cree une (sans ancien bareme) rattachee au passage. Ne bloque jamais un scan (appelant en try/catch).
function SchoolSafeFeeTypesTraceRows( array $status, string $day ): array
{
    $rows = [];
    foreach ( (array) ( $status['types'] ?? [] ) as $type )
        $rows[] = [ 'id' => $type['id'], 'label' => $type['label'], 'color' => $type['color'], 'percent' => $type['percent'],
            'due' => SchoolSafeFeeFormatCents( $type['due_cents'] ), 'covered' => SchoolSafeFeeFormatCents( $type['covered_cents'] ),
            'remaining' => SchoolSafeFeeFormatCents( $type['remaining_cents'] ), 'next_due' => $type['next_due'], 'block_service' => $type['block_service'] ]
            // Lot F4b (05/11) : monnaie des montants de la ligne (deux monnaies en service seulement).
            + ( isset( $type['currency'] ) ? [ 'currency' => $type['currency'] ] : [] );
    return [ 'day' => $day, 'color' => $status['color'] ?? null, 'types' => $rows ];
}

function SchoolSafeFeeTypesTrace( int $consult, int $entry, array $verify, int $school, int $year, int $actor, array $status, string $day ): ?int
{
    $student = (int) ( $verify['student_id'] ?? 0 );
    if ( min( $student, $school, $year, $actor ) < 1 ) return null;
    $json = json_encode( SchoolSafeFeeTypesTraceRows( $status, $day ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR );
    $scope = ' AND SCHOOL_ID=' . $school . ' AND SYEAR=' . $year . ' AND STUDENT_ID=' . $student;
    if ( $consult < 1 && $entry > 0 )
    {
        $linked = DBGet( 'SELECT ID FROM schoolsafe_fee_consults WHERE ENTRY_RECORD_ID=' . $entry . $scope );
        $consult = (int) ( $linked[1]['ID'] ?? 0 );
    }
    if ( $consult < 1 && $entry > 0 )
    {
        require_once __DIR__ . '/SchoolSafeFeeSnapshot.fnc.php';
        $get = function( $sql ) { return DBGet( DBQuery( $sql ) ); };
        $ctx = [ 'school' => $school, 'year' => $year, 'user' => $actor ];
        $ledger = SchoolSafeFeeLedgerRead( $student, $school, $year, $get, $day );
        $snapshot = SchoolSafeFeeControlSnapshot( $verify, $ctx, $ledger, null, [ 'state' => 'inactive', 'color' => null, 'reason' => 'no_active_policy' ],
            SchoolSafeFeeControlMetadata( $ctx, $get ), 'entry' );
        if ( isset( $verify['checkpoint'] ) ) $snapshot['checkpoint'] = $verify['checkpoint'];
        $consult = SchoolSafeFeeConsultRecord( $student, $ctx, $ledger, [ 'get' => $get ], $snapshot );
        DBQuery( 'UPDATE schoolsafe_fee_consults SET ENTRY_RECORD_ID=' . $entry . ' WHERE ID=' . $consult . $scope
            . " AND OPERATION='entry' AND ENTRY_RECORD_ID IS NULL" );
    }
    if ( $consult < 1 ) return null;
    DBQuery( "UPDATE schoolsafe_fee_consults SET context_snapshot=context_snapshot || jsonb_build_object('fee_types','"
        . DBEscapeString( $json ) . "'::jsonb) WHERE ID=" . $consult . $scope );
    return $consult;
}
