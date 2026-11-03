<?php
// Horaires d'arrivee par cycle (03/11), regles par l'administrateur de l'ecole.
// Stockage : table existante schoolsafe_attendance_policy, une ligne par niveau (gradelevel),
// en vigueur a partir du jour de l'enregistrement. arrival = a l'heure, closure = tolere,
// detection = heure rouge (au-dela : retard grave ; aussi l'heure de detection des absences).

function SchoolSafeArrivalCycles(): array
{
    return [ 'maternelle' => 'Maternelle', 'primaire' => 'Primaire', 'secondaire' => 'Secondaire et Humanités' ];
}

function SchoolSafeArrivalCycleOf( string $gradeTitle ): string
{
    $folded = mb_strtolower( strtr( $gradeTitle, [ 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'È' => 'e', 'É' => 'e' ] ) );
    if ( strpos( $folded, 'maternel' ) !== false ) return 'maternelle';
    if ( strpos( $folded, 'primaire' ) !== false ) return 'primaire';
    return 'secondaire';
}

// Niveaux de l'ecole regroupes par cycle, avec les heures en vigueur aujourd'hui (premier niveau du cycle).
function SchoolSafeArrivalRead( int $school, int $year, string $day, callable $get ): array
{
    $cycles = [];
    foreach ( SchoolSafeArrivalCycles() as $key => $label ) $cycles[ $key ] = [ 'label' => $label, 'grades' => [], 'hours' => null ];
    $grades = $get( 'SELECT ID,TITLE FROM school_gradelevels WHERE SCHOOL_ID=' . $school . ' ORDER BY SORT_ORDER,ID' );
    foreach ( (array) $grades as $grade )
    {
        $cycle = SchoolSafeArrivalCycleOf( (string) $grade['TITLE'] );
        $cycles[ $cycle ]['grades'][] = [ 'id' => (int) $grade['ID'], 'title' => (string) $grade['TITLE'] ];
        if ( $cycles[ $cycle ]['hours'] !== null ) continue;
        $policy = $get( 'SELECT ARRIVAL_TIME,CLOSURE_TIME,DETECTION_TIME FROM schoolsafe_attendance_policy WHERE SCHOOL_ID=' . $school
            . ' AND SYEAR=' . $year . ' AND GRADELEVEL_ID=' . (int) $grade['ID'] . " AND COURSE_PERIOD_ID IS NULL AND EFFECTIVE_ON<='" . $day . "'"
            . ' ORDER BY EFFECTIVE_ON DESC LIMIT 1' );
        if ( is_array( $policy ) && isset( $policy[1] ) )
            $cycles[ $cycle ]['hours'] = [ substr( $policy[1]['ARRIVAL_TIME'], 0, 5 ), substr( $policy[1]['CLOSURE_TIME'], 0, 5 ), substr( $policy[1]['DETECTION_TIME'], 0, 5 ) ];
    }
    return $cycles;
}

// Enregistre les heures d'un cycle pour tous ses niveaux, en vigueur a partir d'aujourd'hui.
function SchoolSafeArrivalSave( int $school, int $year, string $cycle, string $arrival, string $tolerated, string $red, string $day, callable $get ): int
{
    foreach ( [ $arrival, $tolerated, $red ] as $time )
        if ( ! preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]$/D', $time ) ) throw new RuntimeException( 'Heure invalide (format HH:MM).' );
    if ( ! ( strcmp( $arrival, $tolerated ) <= 0 && strcmp( $tolerated, $red ) <= 0 ) )
        throw new RuntimeException( 'Les heures doivent se suivre : arrivée ≤ tolérée ≤ rouge.' );
    $cycles = SchoolSafeArrivalRead( $school, $year, $day, $get );
    if ( ! isset( $cycles[ $cycle ] ) ) throw new RuntimeException( 'Cycle inconnu.' );
    $saved = 0;
    foreach ( $cycles[ $cycle ]['grades'] as $grade )
    {
        $where = ' WHERE SCHOOL_ID=' . $school . ' AND SYEAR=' . $year . ' AND GRADELEVEL_ID=' . (int) $grade['id']
            . " AND COURSE_PERIOD_ID IS NULL AND EFFECTIVE_ON='" . $day . "'";
        $set = "ARRIVAL_TIME='" . $arrival . "',CLOSURE_TIME='" . $tolerated . "',DETECTION_TIME='" . $red . "'";
        $updated = $get( 'UPDATE schoolsafe_attendance_policy SET ' . $set . ',UPDATED_AT=CURRENT_TIMESTAMP' . $where . ' RETURNING ID' );
        if ( ! is_array( $updated ) || ! isset( $updated[1] ) )
            $get( 'INSERT INTO schoolsafe_attendance_policy(SCHOOL_ID,SYEAR,GRADELEVEL_ID,ARRIVAL_TIME,CLOSURE_TIME,DETECTION_TIME,EFFECTIVE_ON)'
                . ' VALUES(' . $school . ',' . $year . ',' . (int) $grade['id'] . ",'" . $arrival . "','" . $tolerated . "','" . $red . "','" . $day . "') RETURNING ID" );
        $saved++;
    }
    return $saved;
}
