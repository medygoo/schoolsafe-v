<?php
/**
 * « Ma journée de classe » (04/11, lot 4 valide) : classes du jour de l'enseignant, appel rapide pre-rempli
 * par le scan d'entree, cahier de textes. L'appel ecrit les registres natifs (attendance_period), comme
 * l'ecran natif « Saisir les absences ».
 */

/** Lettre native du jour (U dimanche, M lundi, T mardi, W mercredi, H jeudi, F vendredi, S samedi). */
function SchoolSafeClassDayLetter(string $date): string
{
    $time = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $time ? ['U', 'M', 'T', 'W', 'H', 'F', 'S'][(int) $time->format('w')] : '';
}

/** Codes de presence de l'ecole : present (par defaut), absent, retard, excuse. */
function SchoolSafeClassDayCodes(array $rows): array
{
    $codes = [];
    foreach ($rows as $row) {
        $id = (int) $row['ID'];
        $title = strtolower(trim((string) $row['TITLE'] . ' ' . (string) $row['SHORT_NAME']));
        if (($row['DEFAULT_CODE'] ?? '') === 'Y' && !isset($codes['present'])) $codes['present'] = $id;
        elseif (($row['TYPE'] ?? '') === 'official' && !isset($codes['excused'])) $codes['excused'] = $id;
        elseif (preg_match('/tard|retard|\bt\b|late/', $title) && !isset($codes['late'])) $codes['late'] = $id;
        elseif (($row['STATE_CODE'] ?? '') === 'A' && !isset($codes['absent'])) $codes['absent'] = $id;
        elseif (($row['STATE_CODE'] ?? '') === 'P' && !isset($codes['present'])) $codes['present'] = $id;
    }
    return $codes;
}

function SchoolSafeClassDayLabels(): array
{
    return ['present' => 'Présent', 'late' => 'Retard', 'absent' => 'Absent', 'excused' => 'Excusé'];
}

/** Arrivee lue au scan : [etat, heure]. Etat : non_scanne, a_l_heure, retard (apres l'heure toleree). */
function SchoolSafeClassDayArrival(?string $scannedAt, ?string $tolerated): array
{
    if ($scannedAt === null || $scannedAt === '') return ['non_scanne', ''];
    $time = substr($scannedAt, 11, 5);
    if ($tolerated !== null && $tolerated !== '' && strcmp($time, substr($tolerated, 0, 5)) > 0) return ['retard', $time];
    return ['a_l_heure', $time];
}

/** Choix propose a l'appel : appel deja fait, sinon retard si scanne en retard, sinon present. */
function SchoolSafeClassDayDefault(array $codes, ?int $savedCode, string $arrival): string
{
    if ($savedCode !== null) {
        $found = array_search($savedCode, $codes, true);
        if ($found !== false) return $found;
    }
    return $arrival === 'retard' && isset($codes['late']) ? 'late' : 'present';
}

/** Classes de l'enseignant ce jour-la (tranche horaire prevue ce jour de la semaine). */
function SchoolSafeClassDayClasses(int $staff, int $school, int $year, string $date, callable $get): array
{
    $letter = SchoolSafeClassDayLetter($date);
    if ($staff < 1 || $school < 1 || $letter === '') return [];
    return (array) $get("SELECT cp.COURSE_PERIOD_ID,cp.TITLE,cp.SHORT_NAME,cp.ROOM,c.COURSE_ID,c.TITLE AS COURSE_TITLE,sp.PERIOD_ID,sp.TITLE AS PERIOD_TITLE,sp.START_TIME,sp.END_TIME"
        . " FROM course_periods cp JOIN courses c ON c.COURSE_ID=cp.COURSE_ID"
        . " JOIN course_period_school_periods cpsp ON cpsp.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID"
        . " JOIN school_periods sp ON sp.PERIOD_ID=cpsp.PERIOD_ID"
        . " WHERE cp.SCHOOL_ID=" . $school . " AND cp.SYEAR=" . $year . " AND (cp.TEACHER_ID=" . $staff . " OR cp.SECONDARY_TEACHER_ID=" . $staff . ")"
        . " AND position('" . $letter . "' in cpsp.DAYS)>0 ORDER BY sp.SORT_ORDER,c.TITLE LIMIT 30");
}

/** Eleves de la classe ce jour-la, avec le premier scan d'entree et l'appel deja saisi. */
function SchoolSafeClassDayRoster(int $coursePeriod, int $period, int $school, int $year, string $date, callable $get): array
{
    return (array) $get("SELECT s.STUDENT_ID,s.FIRST_NAME,s.LAST_NAME,e.GRADE_ID,"
        . " (SELECT min(x.RECORDED_AT) FROM entry_exit_student_records x WHERE x.STUDENT_ID=s.STUDENT_ID AND x.TYPE='in' AND x.RECORDED_AT::date='" . $date . "') AS SCANNED_AT,"
        . " (SELECT ap.ATTENDANCE_TEACHER_CODE FROM attendance_period ap WHERE ap.STUDENT_ID=s.STUDENT_ID AND ap.SCHOOL_DATE='" . $date . "' AND ap.PERIOD_ID=" . $period . " LIMIT 1) AS SAVED_CODE"
        . " FROM schedule ss JOIN students s ON s.STUDENT_ID=ss.STUDENT_ID"
        . " JOIN student_enrollment e ON e.STUDENT_ID=s.STUDENT_ID AND e.SCHOOL_ID=" . $school . " AND e.SYEAR=" . $year
        . " AND e.START_DATE<='" . $date . "' AND (e.END_DATE IS NULL OR e.END_DATE>='" . $date . "')"
        . " WHERE ss.COURSE_PERIOD_ID=" . $coursePeriod . " AND ss.START_DATE<='" . $date . "' AND (ss.END_DATE IS NULL OR ss.END_DATE>='" . $date . "')"
        . " ORDER BY s.LAST_NAME,s.FIRST_NAME");
}

/** Appel envoye : seuls les eleves de la classe et les choix connus sont gardes. */
function SchoolSafeClassDayValidate(array $post, array $studentIds, array $codes): array
{
    $out = [];
    foreach ($studentIds as $student) {
        $choice = $post['a_' . (int) $student] ?? 'present';
        if (!is_string($choice) || !isset($codes[$choice])) throw new RuntimeException('Choix de présence invalide.');
        $out[(int) $student] = (int) $codes[$choice];
    }
    return $out;
}

/** Jours permis : aujourd'hui et les 7 jours precedents (corrections), jamais l'avenir. */
function SchoolSafeClassDayDate(?string $requested, string $today): string
{
    if ($requested === null || $requested === '') return $today;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $requested);
    if (!$date || $date->format('Y-m-d') !== $requested) return $today;
    $min = (new DateTimeImmutable($today))->modify('-7 day')->format('Y-m-d');
    return strcmp($requested, $today) > 0 || strcmp($requested, $min) < 0 ? $today : $requested;
}
