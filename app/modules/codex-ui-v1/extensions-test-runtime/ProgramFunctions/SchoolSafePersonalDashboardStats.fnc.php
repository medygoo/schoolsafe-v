<?php
function SchoolSafePersonalDashboardStats(string $profile): string
{
    $school=(int)UserSchool();$year=(int)UserSyear();$staff=(int)User('STAFF_ID');
    if($school<1||$year<1)return '';
    $e=static function($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');};
    $rows=[];$labels=[];$title='';
    if($profile==='teacher'&&$staff>0&&AllowUse('Grades/Grades.php')){
        $rows=DBGet("SELECT cp.title,COUNT(DISTINCT sc.student_id) AS total FROM course_periods cp
            LEFT JOIN schedule sc ON sc.course_period_id=cp.course_period_id AND sc.school_id=$school AND sc.syear=$year
            AND sc.start_date<=CURRENT_DATE AND (sc.end_date IS NULL OR sc.end_date>=CURRENT_DATE)
            WHERE cp.school_id=$school AND cp.syear=$year AND (cp.teacher_id=$staff OR cp.secondary_teacher_id=$staff)
            GROUP BY cp.course_period_id,cp.title ORDER BY cp.title");
        $title='Effectifs de mes cours';
        foreach($rows as $row)$labels[]=[ParseMLField($row['TITLE']),(int)$row['TOTAL']];
    }elseif(in_array($profile,['parent','student'],true)&&AllowUse('Attendance/StudentSummary.php')){
        if($profile==='parent'){
            if($staff<1)return '';
            $scope="EXISTS(SELECT 1 FROM students_join_users u WHERE u.student_id=a.student_id AND u.staff_id=$staff)";
        }else{
            $student=(int)UserStudentID();if($student<1)return '';
            $scope="a.student_id=$student";
        }
        // Resolve conflicting daily categories as unverified, never as presence.
        $rows=DBGet("SELECT CASE WHEN lo<>hi OR lo IS NULL THEN 'unverified' WHEN lo>=1 THEN 'present' WHEN lo=0 THEN 'absent' ELSE 'partial' END AS status,COUNT(*) AS total FROM (
            SELECT a.student_id,a.school_date,MIN(a.state_value) AS lo,MAX(a.state_value) AS hi FROM attendance_day a
            WHERE a.syear=$year AND a.school_date<=CURRENT_DATE AND $scope
            AND EXISTS(SELECT 1 FROM student_enrollment en WHERE en.student_id=a.student_id AND en.school_id=$school AND en.syear=$year
                AND en.start_date<=a.school_date AND (en.end_date IS NULL OR en.end_date>=a.school_date))
            GROUP BY a.student_id,a.school_date) daily GROUP BY status ORDER BY status");
        $title=$profile==='parent'?'Presences enregistrees de mes enfants':'Mes presences enregistrees';
        $names=['present'=>'Journees presentes','absent'=>'Journees absentes','partial'=>'Journees partielles','unverified'=>'Journees non verifiees'];
        foreach($rows as $row)$labels[]=[$names[$row['STATUS']]??'Non verifie',(int)$row['TOTAL']];
    }else return '';
    $html='<section class="ss-personal-stats"><h3>'.$e($title).'</h3>';
    if(!$labels)return $html.'<p class="sscx-empty">Aucun releve accessible pour cette annee scolaire.</p></section>';
    $maximum=max(1,max(array_column($labels,1)));
    $html.='<div class="ss-personal-bars">';
    foreach($labels as $row)$html.='<div><strong>'.$e($row[0]).'</strong><span>'.$row[1].'</span><progress max="'.$maximum.'" value="'.$row[1].'" aria-label="'.$e($row[0]).'"></progress></div>';
    $html.='</div><details><summary>Voir les chiffres</summary><table><thead><tr><th>Rubrique</th><th>Total</th></tr></thead><tbody>';
    foreach($labels as $row)$html.='<tr><th scope="row">'.$e($row[0]).'</th><td>'.$row[1].'</td></tr>';
    $route=$profile==='teacher'?'Grades/Grades.php':'Attendance/StudentSummary.php';
    return $html.'</tbody></table></details><p><a href="Modules.php?modname='.$route.'">Consulter les releves et leurs details</a></p></section>';
}
