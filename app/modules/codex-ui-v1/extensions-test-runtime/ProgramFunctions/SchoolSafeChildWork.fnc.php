<?php
/**
 * « Travail de mon enfant » (04/11, lot 6 valide) : une seule page pour le parent (et l'eleve) :
 * aujourd'hui (cahier de textes), devoirs, notes, presences, rattrapage, liens utiles. Lecture seule ;
 * le parent ne voit que ses propres enfants inscrits dans l'ecole et l'annee courantes.
 */

/** Enfants du parent inscrits dans l'ecole et l'annee (ou l'eleve lui-meme). */
function SchoolSafeChildWorkChildren(string $profile, int $user, int $studentSelf, int $school, int $year, callable $get): array
{
    if ($school < 1 || $year < 1) return [];
    $enrolled = " AND EXISTS(SELECT 1 FROM student_enrollment e WHERE e.STUDENT_ID=s.STUDENT_ID AND e.SCHOOL_ID=" . $school . " AND e.SYEAR=" . $year
        . " AND (e.END_DATE IS NULL OR e.END_DATE>=CURRENT_DATE))";
    $level = ",(SELECT g.TITLE FROM student_enrollment e JOIN school_gradelevels g ON g.ID=e.GRADE_ID WHERE e.STUDENT_ID=s.STUDENT_ID AND e.SCHOOL_ID=" . $school . " AND e.SYEAR=" . $year . " ORDER BY e.START_DATE DESC NULLS LAST LIMIT 1) AS LEVEL_TITLE";
    if ($profile === 'student' && $studentSelf > 0)
        return (array) $get("SELECT s.STUDENT_ID,s.FIRST_NAME,s.LAST_NAME" . $level . " FROM students s WHERE s.STUDENT_ID=" . $studentSelf . $enrolled);
    if ($profile === 'parent' && $user > 0)
        return (array) $get("SELECT s.STUDENT_ID,s.FIRST_NAME,s.LAST_NAME" . $level . " FROM students s JOIN students_join_users su ON su.STUDENT_ID=s.STUDENT_ID AND su.STAFF_ID=" . $user
            . " WHERE TRUE" . $enrolled . " ORDER BY s.FIRST_NAME");
    return [];
}

/** Enfant choisi : celui demande s'il est bien dans la liste, sinon le premier. */
function SchoolSafeChildWorkPick($requested, array $children): ?array
{
    if (!$children) return null;
    foreach ($children as $child) if ((int) $child['STUDENT_ID'] === (int) $requested) return $child;
    return reset($children);
}

/** Classes de l'enfant ce jour (inscription active). */
function SchoolSafeChildWorkClasses(int $student, int $school, int $year, callable $get): array
{
    return (array) $get("SELECT DISTINCT cp.COURSE_PERIOD_ID,cp.COURSE_ID,c.TITLE AS COURSE_TITLE,st.FIRST_NAME AS T_FIRST,st.LAST_NAME AS T_LAST"
        . " FROM schedule ss JOIN course_periods cp ON cp.COURSE_PERIOD_ID=ss.COURSE_PERIOD_ID JOIN courses c ON c.COURSE_ID=cp.COURSE_ID LEFT JOIN staff st ON st.STAFF_ID=cp.TEACHER_ID"
        . " WHERE ss.STUDENT_ID=" . $student . " AND cp.SCHOOL_ID=" . $school . " AND cp.SYEAR=" . $year
        . " AND ss.START_DATE<=CURRENT_DATE AND (ss.END_DATE IS NULL OR ss.END_DATE>=CURRENT_DATE) ORDER BY c.TITLE");
}

/** Devoirs donnes a l'enfant (deja donnes, a rendre ou rendus depuis 14 jours), avec rendu et note. */
function SchoolSafeChildWorkHomework(int $student, array $classes, callable $get): array
{
    if (!$classes) return [];
    $cps = implode(',', array_map(static function ($c) { return (int) $c['COURSE_PERIOD_ID']; }, $classes));
    return (array) $get("SELECT DISTINCT ga.ASSIGNMENT_ID,ga.TITLE,ga.ASSIGNED_DATE,ga.DUE_DATE,ga.POINTS,ga.SUBMISSION,t.TITLE AS TYPE_TITLE,c.TITLE AS COURSE_TITLE,"
        . "(SELECT 1 FROM student_assignments sa WHERE sa.ASSIGNMENT_ID=ga.ASSIGNMENT_ID AND sa.STUDENT_ID=" . $student . " LIMIT 1) AS HANDED_IN,"
        . "(SELECT gg.POINTS FROM gradebook_grades gg WHERE gg.ASSIGNMENT_ID=ga.ASSIGNMENT_ID AND gg.STUDENT_ID=" . $student . " LIMIT 1) AS GRADE"
        . " FROM gradebook_assignments ga JOIN course_periods cp ON (ga.COURSE_PERIOD_ID IS NOT NULL AND cp.COURSE_PERIOD_ID=ga.COURSE_PERIOD_ID)"
        . " OR (ga.COURSE_PERIOD_ID IS NULL AND cp.COURSE_ID=ga.COURSE_ID AND cp.TEACHER_ID=ga.STAFF_ID)"
        . " JOIN courses c ON c.COURSE_ID=cp.COURSE_ID LEFT JOIN gradebook_assignment_types t ON t.ASSIGNMENT_TYPE_ID=ga.ASSIGNMENT_TYPE_ID"
        . " WHERE cp.COURSE_PERIOD_ID IN (" . $cps . ") AND (ga.ASSIGNED_DATE IS NULL OR ga.ASSIGNED_DATE<=CURRENT_DATE)"
        . " AND (ga.DUE_DATE IS NULL OR ga.DUE_DATE>=CURRENT_DATE-14) ORDER BY ga.DUE_DATE NULLS LAST LIMIT 40");
}

/** Notes recentes (30 jours), avec bareme et appreciation. */
function SchoolSafeChildWorkGrades(int $student, int $school, int $year, callable $get): array
{
    return (array) $get("SELECT ga.TITLE,ga.POINTS AS MAXIMUM,gg.POINTS,gg.COMMENT,t.TITLE AS TYPE_TITLE,c.TITLE AS COURSE_TITLE,COALESCE(gg.UPDATED_AT,gg.CREATED_AT) AS GRADED_AT"
        . " FROM gradebook_grades gg JOIN gradebook_assignments ga ON ga.ASSIGNMENT_ID=gg.ASSIGNMENT_ID JOIN course_periods cp ON cp.COURSE_PERIOD_ID=gg.COURSE_PERIOD_ID"
        . " JOIN courses c ON c.COURSE_ID=cp.COURSE_ID LEFT JOIN gradebook_assignment_types t ON t.ASSIGNMENT_TYPE_ID=ga.ASSIGNMENT_TYPE_ID"
        . " WHERE gg.STUDENT_ID=" . $student . " AND cp.SCHOOL_ID=" . $school . " AND cp.SYEAR=" . $year . " AND gg.POINTS IS NOT NULL"
        . " AND COALESCE(gg.UPDATED_AT,gg.CREATED_AT)>now()-interval '30 days' ORDER BY COALESCE(gg.UPDATED_AT,gg.CREATED_AT) DESC LIMIT 20");
}

/** Absences et retards des 30 derniers jours (appel des enseignants). */
function SchoolSafeChildWorkAttendance(int $student, int $school, int $year, callable $get): array
{
    return (array) $get("SELECT ap.SCHOOL_DATE,ac.TITLE,ac.STATE_CODE,ac.TYPE FROM attendance_period ap JOIN attendance_codes ac ON ac.ID=ap.ATTENDANCE_CODE"
        . " WHERE ap.STUDENT_ID=" . $student . " AND ac.SCHOOL_ID=" . $school . " AND ac.SYEAR=" . $year . " AND ap.SCHOOL_DATE>=CURRENT_DATE-30"
        . " AND COALESCE(ac.DEFAULT_CODE,'')<>'Y' ORDER BY ap.SCHOOL_DATE DESC LIMIT 30");
}

/** Cahier de textes des classes de l'enfant (7 derniers jours). */
function SchoolSafeChildWorkDiary(array $classes, callable $get): array
{
    if (!$classes) return [];
    $cps = implode(',', array_map(static function ($c) { return (int) $c['COURSE_PERIOD_ID']; }, $classes));
    return (array) $get("SELECT d.SCHOOL_DATE,d.DONE,d.TODO,c.TITLE AS COURSE_TITLE FROM schoolsafe_class_diary d JOIN course_periods cp ON cp.COURSE_PERIOD_ID=d.COURSE_PERIOD_ID JOIN courses c ON c.COURSE_ID=cp.COURSE_ID"
        . " WHERE d.COURSE_PERIOD_ID IN (" . $cps . ") AND d.SCHOOL_DATE>=CURRENT_DATE-7 AND d.SCHOOL_DATE<=CURRENT_DATE AND (d.DONE<>'' OR d.TODO<>'') ORDER BY d.SCHOOL_DATE DESC,c.TITLE LIMIT 20");
}

/** Libelle francais d'un code de presence natif. */
function SchoolSafeChildWorkCode(string $title, string $state, string $type): string
{
    $t = strtolower($title);
    if ($type === 'official') return 'Absence excusée';
    if (preg_match('/tard|retard|late/', $t)) return 'Retard';
    if ($state === 'A') return 'Absent';
    return $title;
}

/** Note lisible : « 15 / 20 », « dispensé » (natif -1). */
function SchoolSafeChildWorkMark($points, $maximum): string
{
    if ($points === null || $points === '') return '';
    if ((float) $points < 0) return 'dispensé';
    $n = static function ($v) { return str_replace('.', ',', rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.')); };
    return $n($points) . ' / ' . $n($maximum);
}
