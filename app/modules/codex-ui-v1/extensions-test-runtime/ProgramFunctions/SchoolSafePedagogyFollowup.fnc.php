<?php
// Suivi pedagogique de la direction (04/11, lot 9 valide par le proprietaire) : par classe et enseignant,
// appels faits, cahier de textes, devoirs, copies non notees, moyenne, absences, rattrapages ouverts.
// Lecture seule : rien n'est modifie. Les chiffres viennent des registres natifs deja remplis.

/** Periode demandee (du, au), bornee a 92 jours, par defaut les 7 derniers jours. */
function SchoolSafeFollowupRange(?string $from, ?string $to, string $today): array
{
    $ok = static fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4));
    $end = $ok($to) ? $to : $today;
    if ($end > $today) $end = $today;
    $start = $ok($from) ? $from : date('Y-m-d', strtotime($end . ' -6 days'));
    if ($start > $end) $start = $end;
    if ((strtotime($end) - strtotime($start)) / 86400 > 92) $start = date('Y-m-d', strtotime($end . ' -92 days'));
    return [$start, $end];
}

/** Une ligne par classe-matiere de l'ecole, avec tous les compteurs de la periode. */
function SchoolSafeFollowupRows(int $school, int $year, string $from, string $to, string $today, callable $get): array
{
    if ($school < 1 || $year < 1) return [];
    $f = "'" . $from . "'"; $t = "'" . $to . "'"; $d = "'" . $today . "'";
    $asg = "(ga.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID OR (ga.COURSE_PERIOD_ID IS NULL AND ga.COURSE_ID=cp.COURSE_ID AND ga.STAFF_ID=cp.TEACHER_ID))";
    $enrolled = "SELECT sc.STUDENT_ID FROM schedule sc WHERE sc.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID AND sc.START_DATE<=" . $d . " AND (sc.END_DATE IS NULL OR sc.END_DATE>=" . $d . ")";
    $days = "SELECT ac.SCHOOL_DATE,cpsp.PERIOD_ID FROM attendance_calendar ac JOIN course_period_school_periods cpsp ON cpsp.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID"
        . " WHERE ac.CALENDAR_ID=cp.CALENDAR_ID AND ac.SCHOOL_ID=cp.SCHOOL_ID AND ac.SYEAR=cp.SYEAR AND ac.MINUTES>0 AND ac.SCHOOL_DATE BETWEEN " . $f . " AND " . $t
        . " AND position(substr('UMTWHFS',extract(dow from ac.SCHOOL_DATE)::int+1,1) in cpsp.DAYS)>0"
        // Jours attendus seulement depuis la premiere inscription d'un eleve dans la classe.
        . " AND ac.SCHOOL_DATE>=(SELECT min(sc0.START_DATE) FROM schedule sc0 WHERE sc0.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID)";
    return (array) $get("SELECT cp.COURSE_PERIOD_ID,cp.TITLE,c.TITLE AS COURSE_TITLE,cp.TEACHER_ID,st.FIRST_NAME,st.LAST_NAME,"
        . "(cp.DOES_ATTENDANCE IS NOT NULL AND cp.DOES_ATTENDANCE<>'') AS TAKES_ROLL,"
        . "(SELECT count(*) FROM (" . $enrolled . ") e) AS STUDENTS,"
        . "(SELECT count(*) FROM (" . $days . ") x) AS EXPECTED_ROLLS,"
        . "(SELECT count(*) FROM (" . $days . ") x WHERE EXISTS(SELECT 1 FROM attendance_completed acp WHERE acp.STAFF_ID=cp.TEACHER_ID AND acp.SCHOOL_DATE=x.SCHOOL_DATE AND acp.PERIOD_ID=x.PERIOD_ID)) AS DONE_ROLLS,"
        . "(SELECT count(DISTINCT x.SCHOOL_DATE) FROM (" . $days . ") x) AS EXPECTED_DAYS,"
        . "(SELECT count(*) FROM schoolsafe_class_diary cd WHERE cd.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID AND cd.SCHOOL_DATE BETWEEN " . $f . " AND " . $t . " AND (cd.DONE<>'' OR cd.TODO<>'')) AS DIARY_DAYS,"
        . "(SELECT count(*) FROM gradebook_assignments ga WHERE " . $asg . " AND COALESCE(ga.ASSIGNED_DATE,ga.DUE_DATE) BETWEEN " . $f . " AND " . $t . ") AS HOMEWORK,"
        . "(SELECT count(*) FROM gradebook_assignments ga CROSS JOIN (" . $enrolled . ") e WHERE " . $asg . " AND ga.DUE_DATE IS NOT NULL AND ga.DUE_DATE<" . $d
        . " AND NOT EXISTS(SELECT 1 FROM gradebook_grades gg WHERE gg.ASSIGNMENT_ID=ga.ASSIGNMENT_ID AND gg.STUDENT_ID=e.STUDENT_ID)) AS MISSING_GRADES,"
        . "(SELECT round(avg(gg.POINTS*100.0/ga.POINTS),1) FROM gradebook_grades gg JOIN gradebook_assignments ga ON ga.ASSIGNMENT_ID=gg.ASSIGNMENT_ID WHERE gg.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID AND gg.POINTS>=0 AND ga.POINTS>0) AS AVERAGE,"
        . "(SELECT count(*) FROM attendance_period ap JOIN attendance_codes aco ON aco.ID=ap.ATTENDANCE_CODE WHERE ap.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID AND ap.SCHOOL_DATE BETWEEN " . $f . " AND " . $t . " AND aco.STATE_CODE='A') AS ABSENCES,"
        . "(SELECT count(*) FROM schoolsafe_remediation_cases rc WHERE rc.COURSE_ID=cp.COURSE_ID AND rc.SCHOOL_ID=cp.SCHOOL_ID AND rc.SYEAR=cp.SYEAR AND rc.STATUS IN ('pending','approved','repeat')) AS REMEDIATION"
        . " FROM course_periods cp JOIN courses c ON c.COURSE_ID=cp.COURSE_ID LEFT JOIN staff st ON st.STAFF_ID=cp.TEACHER_ID"
        . " WHERE cp.SCHOOL_ID=" . $school . " AND cp.SYEAR=" . $year . " ORDER BY c.TITLE,cp.TITLE LIMIT 300");
}

/** Points a relancer pour une ligne : appel manquant, cahier non rempli, copies non notees. */
function SchoolSafeFollowupAlerts(array $row): array
{
    $alerts = [];
    $truthy = static fn($v) => $v === true || $v === 't' || $v === 'Y' || $v === 1 || $v === '1';
    if ($truthy($row['TAKES_ROLL'] ?? false) && (int) ($row['EXPECTED_ROLLS'] ?? 0) > (int) ($row['DONE_ROLLS'] ?? 0)) {
        $alerts[] = ((int) $row['EXPECTED_ROLLS'] - (int) $row['DONE_ROLLS']) . ' appel(s) non fait(s)';
    }
    if ((int) ($row['STUDENTS'] ?? 0) > 0 && (int) ($row['EXPECTED_DAYS'] ?? 0) > (int) ($row['DIARY_DAYS'] ?? 0)) {
        $alerts[] = ((int) $row['EXPECTED_DAYS'] - (int) $row['DIARY_DAYS']) . ' jour(s) sans cahier de textes';
    }
    if ((int) ($row['MISSING_GRADES'] ?? 0) > 0) {
        $alerts[] = (int) $row['MISSING_GRADES'] . ' copie(s) non notée(s)';
    }
    return $alerts;
}

/** Couleur de la moyenne selon le seuil de reussite de l'ecole (rattrapage). */
function SchoolSafeFollowupLevel($average, float $pass): string
{
    if ($average === null || $average === '') return '';
    $a = (float) $average;
    if ($a < $pass) return 'ko';
    if ($a < $pass + 10) return 'mid';
    return 'ok';
}

/** Dernieres modifications de notes de l'ecole (journal 062). */
function SchoolSafeFollowupJournal(int $school, int $year, callable $get, int $limit = 60): array
{
    if ($school < 1) return [];
    return (array) $get("SELECT j.SOURCE,j.ACTION,j.OLD_VALUE,j.NEW_VALUE,j.CHANGED_AT,s.FIRST_NAME,s.LAST_NAME,c.TITLE AS COURSE_TITLE,cp.TITLE AS CP_TITLE,ga.TITLE AS ASSIGNMENT,a.FIRST_NAME AS BY_FIRST,a.LAST_NAME AS BY_LAST"
        . " FROM schoolsafe_grade_journal j JOIN course_periods cp ON cp.COURSE_PERIOD_ID=j.COURSE_PERIOD_ID JOIN courses c ON c.COURSE_ID=cp.COURSE_ID"
        . " LEFT JOIN students s ON s.STUDENT_ID=j.STUDENT_ID LEFT JOIN gradebook_assignments ga ON ga.ASSIGNMENT_ID=j.ASSIGNMENT_ID LEFT JOIN staff a ON a.STAFF_ID=j.ACTOR_STAFF_ID"
        . " WHERE cp.SCHOOL_ID=" . $school . " AND cp.SYEAR=" . $year . " ORDER BY j.CHANGED_AT DESC,j.ID DESC LIMIT " . max(1, min(200, $limit)));
}
