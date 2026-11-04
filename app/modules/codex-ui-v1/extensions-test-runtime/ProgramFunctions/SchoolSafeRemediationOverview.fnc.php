<?php
/**
 * Rattrapage, vue de la direction (04/11, valide par le proprietaire) : par classe, par enseignant de la
 * matiere et par enseignant qui donne le rattrapage. Beaucoup d'eleves en difficulte dans une meme classe
 * signale d'abord un besoin d'accompagner l'enseignant (methode, programme), pas un rattrapage payant.
 */

/** Lignes brutes par classe (cours x enseignant) de l'ecole et de l'annee. */
function SchoolSafeRemediationClassRows(int $school, int $year, callable $get): array
{
    if ($school < 1 || $year < 1) return [];
    $inClass = 'EXISTS(SELECT 1 FROM schedule sc WHERE sc.course_period_id=cp.course_period_id AND sc.student_id=%s.student_id)';
    return (array) $get('SELECT cp.course_period_id,cp.title,cp.teacher_id,st.first_name,st.last_name,'
        . '(SELECT count(DISTINCT sc.student_id) FROM schedule sc WHERE sc.course_period_id=cp.course_period_id AND (sc.end_date IS NULL OR sc.end_date>=CURRENT_DATE)) AS students,'
        . '(SELECT count(*) FROM schoolsafe_remediation_cases r WHERE r.course_id=cp.course_id AND r.school_id=cp.school_id AND r.syear=cp.syear AND ' . sprintf($inClass, 'r') . ') AS cases,'
        . "(SELECT count(*) FROM schoolsafe_remediation_cases r WHERE r.course_id=cp.course_id AND r.school_id=cp.school_id AND r.syear=cp.syear AND r.status='passed' AND " . sprintf($inClass, 'r') . ') AS passed,'
        . '(SELECT count(*) FROM schoolsafe_remediation_watch w WHERE w.course_id=cp.course_id AND w.school_id=cp.school_id AND w.syear=cp.syear AND NOT w.cleared'
        . ' AND NOT EXISTS(SELECT 1 FROM schoolsafe_remediation_cases x WHERE x.student_id=w.student_id AND x.course_id=w.course_id AND x.school_id=w.school_id AND x.syear=w.syear) AND ' . sprintf($inClass, 'w') . ') AS watch'
        . ' FROM course_periods cp LEFT JOIN staff st ON st.staff_id=cp.teacher_id'
        . ' WHERE cp.school_id=' . $school . ' AND cp.syear=' . $year . ' ORDER BY cp.title LIMIT 500');
}

/** Lignes brutes par enseignant qui donne le rattrapage ; « own » = seances donnees dans sa propre matiere. */
function SchoolSafeRemediationGiverRows(int $school, int $year, callable $get): array
{
    if ($school < 1 || $year < 1) return [];
    return (array) $get('SELECT se.teacher_id,st.first_name,st.last_name,count(DISTINCT se.case_id) AS cases,'
        . "count(*) FILTER (WHERE se.status='held') AS held,"
        . 'count(*) FILTER (WHERE EXISTS(SELECT 1 FROM course_periods cp WHERE cp.course_id=r.course_id AND cp.school_id=r.school_id AND cp.syear=r.syear AND cp.teacher_id=se.teacher_id)) AS own,'
        . 'count(*) AS sessions'
        . ' FROM schoolsafe_remediation_sessions se JOIN schoolsafe_remediation_cases r ON r.id=se.case_id LEFT JOIN staff st ON st.staff_id=se.teacher_id'
        . ' WHERE r.school_id=' . $school . ' AND r.syear=' . $year . ' GROUP BY se.teacher_id,st.first_name,st.last_name ORDER BY 4 DESC LIMIT 200');
}

/** Taux en % (entier), null sans eleve. */
function SchoolSafeRemediationRate(int $part, int $total): ?int
{
    return $total > 0 ? (int) round($part * 100 / $total) : null;
}

/** Calcule les tableaux : classes (avec alerte), enseignants de la matiere, enseignants qui donnent le rattrapage. */
function SchoolSafeRemediationOverview(array $classRows, array $giverRows): array
{
    $classes = []; $teachers = []; $givers = [];
    foreach ($classRows as $row) {
        $students = (int) $row['STUDENTS']; $cases = (int) $row['CASES']; $watch = (int) $row['WATCH']; $passed = (int) $row['PASSED'];
        $difficulty = $cases + $watch;
        $rate = SchoolSafeRemediationRate($difficulty, $students);
        $teacher = trim((string) ($row['FIRST_NAME'] ?? '') . ' ' . (string) ($row['LAST_NAME'] ?? ''));
        // Au moins 3 eleves et 30 % de la classe : on regarde l'enseignement avant les eleves.
        $alert = $difficulty >= 3 && $rate !== null && $rate >= 30;
        $classes[] = ['title' => (string) $row['TITLE'], 'teacher' => $teacher !== '' ? $teacher : 'Sans enseignant', 'students' => $students,
            'cases' => $cases, 'watch' => $watch, 'passed' => $passed, 'rate' => $rate, 'alert' => $alert];
        $key = (int) ($row['TEACHER_ID'] ?? 0);
        $t = $teachers[$key] ?? ['teacher' => $teacher !== '' ? $teacher : 'Sans enseignant', 'classes' => 0, 'students' => 0, 'cases' => 0, 'watch' => 0, 'passed' => 0, 'alerts' => 0];
        $t['classes']++; $t['students'] += $students; $t['cases'] += $cases; $t['watch'] += $watch; $t['passed'] += $passed;
        if ($alert) $t['alerts']++;
        $teachers[$key] = $t;
    }
    foreach ($teachers as $key => $t) {
        $teachers[$key]['rate'] = SchoolSafeRemediationRate($t['cases'] + $t['watch'], $t['students']);
        $teachers[$key]['success'] = SchoolSafeRemediationRate($t['passed'], $t['cases']);
    }
    foreach ($giverRows as $row) {
        $name = trim((string) ($row['FIRST_NAME'] ?? '') . ' ' . (string) ($row['LAST_NAME'] ?? ''));
        $givers[] = ['teacher' => $name !== '' ? $name : 'Enseignant ' . (int) $row['TEACHER_ID'], 'cases' => (int) $row['CASES'],
            'sessions' => (int) $row['SESSIONS'], 'held' => (int) $row['HELD'], 'own' => (int) $row['OWN']];
    }
    usort($classes, static function ($a, $b) { return [$b['alert'], $b['rate'] ?? -1] <=> [$a['alert'], $a['rate'] ?? -1]; });
    return ['classes' => $classes, 'teachers' => array_values($teachers), 'givers' => $givers];
}
