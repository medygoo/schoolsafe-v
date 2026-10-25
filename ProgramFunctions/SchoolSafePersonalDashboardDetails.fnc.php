<?php
function SchoolSafePersonalDashboardDetails(string $profile): string
{
    if (!in_array($profile, ['parent', 'student', 'teacher'], true)) return '';
    $school=(int)UserSchool(); $year=(int)UserSyear(); $staff=(int)User('STAFF_ID');
    if ($school<1 || $year<1) return '';
    $e=static function($v){return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');};
    $active="en.school_id=$school AND en.syear=$year AND en.start_date<=CURRENT_DATE AND (en.end_date IS NULL OR en.end_date>=CURRENT_DATE)";
    if ($profile==='teacher') {
        if ($staff<1 || !AllowUse('Grades/Grades.php')) return '';
        $rows=DBGet("SELECT DISTINCT cp.course_period_id,cp.title,s.student_id,s.first_name,s.last_name
            FROM course_periods cp JOIN schedule sc ON sc.course_period_id=cp.course_period_id
            JOIN students s ON s.student_id=sc.student_id JOIN student_enrollment en ON en.student_id=s.student_id
            WHERE cp.school_id=$school AND cp.syear=$year AND (cp.teacher_id=$staff OR cp.secondary_teacher_id=$staff)
            AND sc.school_id=$school AND sc.syear=$year AND sc.start_date<=CURRENT_DATE
            AND (sc.end_date IS NULL OR sc.end_date>=CURRENT_DATE) AND $active
            ORDER BY cp.title,cp.course_period_id,s.last_name,s.first_name,s.student_id");
        $groups=[];
        foreach ($rows as $row) $groups[(int)$row['COURSE_PERIOD_ID']][]=$row;
        $html='<section class="ss-personal-details ss-personal-roster"><h3>Eleves de mes groupes</h3>';
        if (!$groups) return $html.'<p class="sscx-empty">Aucune inscription active dans vos groupes.</p></section>';
        foreach ($groups as $id=>$students) {
            $html.='<details><summary>'.$e(ParseMLField($students[0]['TITLE'])).' · '.count($students).' '.(count($students)===1?'eleve':'eleves').'</summary><div class="ss-personal-table-scroll"><table><thead><tr><th scope="col">Eleve</th><th scope="col">Identifiant</th></tr></thead><tbody>';
            foreach ($students as $row) $html.='<tr><th scope="row">'.$e($row['FIRST_NAME'].' '.$row['LAST_NAME']).'</th><td>'.(int)$row['STUDENT_ID'].'</td></tr>';
            $html.='</tbody></table></div><p><a href="Modules.php?modname=Grades/Grades.php&amp;course_period_id='.$id.'&amp;period='.$id.'">Enregistrer les cotes de ce groupe</a></p></details>';
        }
        return $html.'</section>';
    }
    $fees=AllowUse('Student_Billing/StudentFees.php');
    $payments=AllowUse('Student_Billing/StudentPayments.php');
    if (!$fees && !$payments) return '';
    if ($profile==='parent') {
        if ($staff<1) return '';
        $scope="EXISTS(SELECT 1 FROM students_join_users u WHERE u.student_id=s.student_id AND u.staff_id=$staff)";
    } else {
        $student=(int)UserStudentID();
        if ($student<1) return '';
        $scope="s.student_id=$student";
    }
    // Aggregate each ledger separately, so multiple fees and payments do not multiply totals.
    $columns=''; $joins='';
    if ($fees) {
        $columns.=',COALESCE(f.total,0) AS fees';
        $joins.=" LEFT JOIN (SELECT student_id,SUM(amount) AS total FROM billing_fees WHERE school_id=$school AND syear=$year GROUP BY student_id) f ON f.student_id=s.student_id";
    }
    if ($payments) {
        $columns.=',COALESCE(p.total,0) AS paid';
        $joins.=" LEFT JOIN (SELECT student_id,SUM(amount) AS total FROM billing_payments WHERE school_id=$school AND syear=$year GROUP BY student_id) p ON p.student_id=s.student_id";
    }
    $rows=DBGet("SELECT DISTINCT s.student_id,s.first_name,s.last_name$columns FROM students s
        JOIN student_enrollment en ON en.student_id=s.student_id $joins WHERE $active AND $scope ORDER BY s.last_name,s.first_name,s.student_id");
    $html='<section class="ss-personal-details ss-personal-finance"><h3>'.($profile==='parent'?'Frais de mes enfants':'Mes frais scolaires').'</h3><div class="ss-personal-table-scroll"><table><thead><tr><th scope="col">Eleve</th>';
    if ($fees) $html.='<th scope="col">Facture</th>';
    if ($payments) $html.='<th scope="col">Paye</th>';
    if ($fees && $payments) $html.='<th scope="col">Solde</th>';
    $html.='<th scope="col">Details</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $html.='<tr><th scope="row">'.$e($row['FIRST_NAME'].' '.$row['LAST_NAME']).'</th>';
        $amounts=[];
        if ($fees) $amounts[]=$row['FEES'];
        if ($payments) $amounts[]=$row['PAID'];
        if ($fees && $payments) $amounts[]=(float)$row['FEES']-(float)$row['PAID'];
        foreach ($amounts as $amount) $html.='<td>'.$e(html_entity_decode(strip_tags(Currency($amount)),ENT_QUOTES,'UTF-8')).'</td>';
        $route=$fees?'StudentFees.php':'StudentPayments.php';
        $html.='<td><a href="Modules.php?modname=Student_Billing/'.$route.'&amp;student_id='.(int)$row['STUDENT_ID'].'">Consulter</a></td></tr>';
    }
    if (!$rows) $html.='<tr><td colspan="'.(2+(int)$fees+(int)$payments+(int)($fees&&$payments)).'">Aucun eleve actif accessible dans cette ecole.</td></tr>';
    return $html.'</tbody></table></div><p>Montants cumules de l\'annee scolaire selectionnee.</p></section>';
}
