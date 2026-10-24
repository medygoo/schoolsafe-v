<?php
/** Counts real native TP assignments once per affected course section for the selected marking period. */
function SchoolSafePracticalClassesVisual(int $school, int $year): string
{
    if ($school < 1 || $year < 1 || User('PROFILE') !== 'admin'
        || !AllowUse('Grades/Assignments.php') || (int)UserMP() < 1) return '';
    $period=(int)UserMP();
    $escape=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
    $date=(new DateTimeImmutable('today',new DateTimeZone('Africa/Kinshasa')))->format('Y-m-d');
    $types=DBGet("SELECT count(*) AS tp_count FROM gradebook_assignment_types t
        JOIN courses c ON c.course_id=t.course_id
        WHERE c.school_id=$school AND c.syear=$year
        AND lower(trim(t.title)) IN ('tp','travaux pratiques')");
    $html='<section id="ss-photo-practical-classes" class="ss-photo-practical-classes" aria-label="Classes ayant reçu le plus de travaux pratiques"><header><div><h2>Classes en travaux pratiques</h2><p>Top 5 · Période sélectionnée · TP enregistrés</p></div></header>';
    if ((int)($types[0]['TP_COUNT']??$types[1]['TP_COUNT']??0)<1) return $html.'<p>Aucun TP configuré pour cette école.</p></section>';
    $rows=DBGet("SELECT * FROM (
        SELECT grouped.*,RANK() OVER (ORDER BY grouped.total DESC) AS position
        FROM (SELECT cp.course_period_id,cp.title,COUNT(DISTINCT a.assignment_id) AS total,
            STRING_AGG(DISTINCT trim(concat_ws(' ',st.first_name,st.last_name)), ', '
                ORDER BY trim(concat_ws(' ',st.first_name,st.last_name))) AS teachers
            FROM gradebook_assignments a
            JOIN school_marking_periods mp ON mp.marking_period_id=a.marking_period_id
                AND mp.school_id=$school AND mp.syear=$year
            JOIN gradebook_assignment_types t ON t.assignment_type_id=a.assignment_type_id
                AND t.course_id=a.course_id AND t.staff_id=a.staff_id
            JOIN courses c ON c.course_id=a.course_id AND c.school_id=$school AND c.syear=$year
            JOIN course_periods cp ON cp.course_id=c.course_id AND cp.school_id=$school AND cp.syear=$year
                AND (a.course_period_id=cp.course_period_id OR
                    (a.course_period_id IS NULL AND (cp.teacher_id=a.staff_id OR cp.secondary_teacher_id=a.staff_id)))
            JOIN staff st ON st.staff_id=a.staff_id AND st.syear=$year
            WHERE a.marking_period_id=$period AND lower(trim(t.title)) IN ('tp','travaux pratiques')
                AND (a.assigned_date IS NULL OR a.assigned_date<='$date') AND a.created_at::date<='$date'
                AND EXISTS (SELECT 1 FROM schedule sc WHERE sc.course_period_id=cp.course_period_id
                    AND sc.school_id=$school AND sc.syear=$year AND sc.start_date<='$date'
                    AND (sc.end_date IS NULL OR sc.end_date>='$date')
                    AND EXISTS (SELECT 1 FROM student_enrollment e WHERE e.student_id=sc.student_id
                        AND e.school_id=$school AND e.syear=$year AND e.start_date<='$date'
                        AND (e.end_date IS NULL OR e.end_date>='$date')))
            GROUP BY cp.course_period_id,cp.title) grouped
        ) ranked WHERE position<=5 ORDER BY total DESC,title,course_period_id");
    if (!$rows) return $html.'<p>Aucun TP donné à une classe active pendant cette période.</p></section>';
    $html.='<ol class="ss-photo-practical-list">';
    foreach ($rows as $row) {
        $rank=(int)$row['POSITION'];$count=(int)$row['TOTAL'];
        $html.='<li data-rank="'.$rank.'"><span class="ss-photo-practical-rank">'.$rank.'</span><div><strong>'.$escape(ParseMLField($row['TITLE'])).'</strong><small>Enseignant(s) : '.$escape($row['TEACHERS']?:'non renseigné').'</small></div><b>'.$count.' TP</b></li>';
    }
    return $html.'</ol></section>';
}
