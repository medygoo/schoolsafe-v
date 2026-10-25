<?php
/** Presentation-only adapter; data and permissions stay in Rosario's dashboard. */
function SchoolSafeDashboardView()
{
    global $_ROSARIO;
    echo '<link rel="stylesheet" href="assets/css/schoolsafe-dashboard.css?v=3">';
    echo '<section class="ss-connected-dashboard" aria-label="Tableau de bord">';
    echo '<header class="ss-connected-heading"><h2>Vue generale</h2><span>' . htmlspecialchars((string)UserSyear(), ENT_QUOTES, 'UTF-8') . '</span></header>';
    echo '<div class="ss-connected-metrics">';
    foreach ((array) ($_ROSARIO['Dashboard'] ?? []) as $module => $html) {
        if (!is_string($html) || $html === '' || empty($_ROSARIO['Menu'][$module])) continue;
        echo '<article class="ss-connected-metric">' . $html . '</article>';
    }
    echo '</div>';
    if (function_exists('User') && User('PROFILE') === 'admin' && AllowUse('Students/Student.php')) {
        $school = (int)UserSchool(); $year = (int)UserSyear();
        $groups = DBGet("SELECT g.title,COUNT(DISTINCT e.student_id) AS total FROM student_enrollment e
            JOIN school_gradelevels g ON g.id=e.grade_id AND g.school_id=e.school_id
            WHERE e.school_id=$school AND e.syear=$year AND e.start_date<=CURRENT_DATE
            AND (e.end_date IS NULL OR e.end_date>=CURRENT_DATE) GROUP BY g.id,g.title,g.sort_order ORDER BY g.sort_order,g.title");
        $total = array_sum(array_map(static function($r) { return (int)$r['TOTAL']; }, array_values($groups)));
        echo '<div class="ss-connected-overview"><section><h3>Repartition par niveau</h3>';
        foreach ($groups as $group) {
            $count=(int)$group['TOTAL'];
            echo '<div class="ss-connected-distribution"><span>'.htmlspecialchars(ParseMLField($group['TITLE']), ENT_QUOTES, 'UTF-8').'</span><strong>'.$count.'</strong><progress max="'.max(1,$total).'" value="'.$count.'"></progress></div>';
        }
        if (!$groups) echo '<p>Aucun eleve inscrit.</p>';
        echo '</section><section><h3>Acces rapides</h3><div class="ss-connected-links">';
        $links=['Students/Student.php'=>'Eleves','Scheduling/Courses.php'=>'Cours et classes','Users/User.php'=>'Personnel','Attendance/DailySummary.php'=>'Presences','School_Setup/Calendar.php'=>'Calendrier'];
        foreach($links as $route=>$label) if(AllowUse($route)) echo '<a href="Modules.php?modname='.htmlspecialchars($route,ENT_QUOTES,'UTF-8').'">'.$label.'</a>';
        echo '</div></section></div>';
        echo '<div class="ss-connected-tabs" role="tablist" aria-label="Rubriques de la vue generale"><button type="button" role="tab" id="ss-dashboard-tab-students" aria-selected="true" aria-controls="ss-dashboard-students" data-dashboard-tab="students">Eleves</button>';
        if(AllowUse('Users/User.php')) echo '<button type="button" role="tab" id="ss-dashboard-tab-staff" aria-selected="false" aria-controls="ss-dashboard-staff" tabindex="-1" data-dashboard-tab="staff">Enseignants</button>';
        if(AllowUse('Grades/GPARankList.php')) echo '<button type="button" role="tab" id="ss-dashboard-tab-awards" aria-selected="false" aria-controls="ss-dashboard-awards" tabindex="-1" data-dashboard-tab="awards">Palmares</button>';
        echo '</div><section id="ss-dashboard-students" role="tabpanel" aria-labelledby="ss-dashboard-tab-students"><h3>Eleves et niveaux</h3><table class="ss-connected-table"><thead><tr><th>Niveau</th><th>Inscrits</th></tr></thead><tbody>';
        foreach($groups as $group) echo '<tr><td>'.htmlspecialchars(ParseMLField($group['TITLE']),ENT_QUOTES,'UTF-8').'</td><td>'.(int)$group['TOTAL'].'</td></tr>';
        echo '</tbody></table></section>';
        if(AllowUse('Users/User.php')) {
            $staff=DBGet("SELECT first_name,last_name FROM staff WHERE syear=$year AND profile='teacher' AND (current_school_id=$school OR position(',$school,' IN schools)>0) ORDER BY last_name,first_name LIMIT 100");
            echo '<section id="ss-dashboard-staff" role="tabpanel" aria-labelledby="ss-dashboard-tab-staff" hidden><h3>Enseignants</h3><ul>';
            foreach($staff as $row) echo '<li>'.htmlspecialchars($row['FIRST_NAME'].' '.$row['LAST_NAME'],ENT_QUOTES,'UTF-8').'</li>';
            if(!$staff) echo '<li>Aucun enseignant renseigne.</li>';
            echo '</ul></section>';
        }
        if(AllowUse('Grades/GPARankList.php')) {
            require_once __DIR__.'/SchoolSafeDashboardNative.fnc.php';
            echo '<section id="ss-dashboard-awards" role="tabpanel" aria-labelledby="ss-dashboard-tab-awards" hidden>'.SchoolSafeDashboardNativeWidget().'</section>';
        }
    } elseif(function_exists('SchoolSafeDashboardNativeWidget')) {
        echo SchoolSafeDashboardNativeWidget();
    } else {
        require_once __DIR__.'/SchoolSafeDashboardNative.fnc.php';
        echo SchoolSafeDashboardNativeWidget();
    }
    echo '</section><script src="assets/js/schoolsafe-dashboard.js?v=1"></script>';
}
