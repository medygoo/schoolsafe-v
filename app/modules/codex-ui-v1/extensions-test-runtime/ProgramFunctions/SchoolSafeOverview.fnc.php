<?php
function SchoolSafeOverviewData(): array
{
    $d=['groups'=>[], 'classes'=>[], 'staff'=>[], 'gender'=>[], 'total'=>null, 'present'=>null, 'absent'=>null, 'partial'=>null, 'unknown'=>null];
    if(User('PROFILE')!=='admin'||!AllowUse('Students/Student.php')) return $d;
    $school=(int)UserSchool(); $year=(int)UserSyear();
    if($school<1||$year<1)return $d;
    $d['gender']=DBGet("SELECT COALESCE(g.title,'Niveau non renseigne') AS title,GROUPING(g.title) AS is_total,
        COUNT(DISTINCT s.student_id) AS total,
        COUNT(DISTINCT s.student_id) FILTER(WHERE LOWER(TRIM(s.custom_200000000)) IN ('female','f','feminin','fille')) AS girls,
        COUNT(DISTINCT s.student_id) FILTER(WHERE LOWER(TRIM(s.custom_200000000)) IN ('male','m','masculin','garcon')) AS boys
        FROM students s JOIN student_enrollment en ON en.student_id=s.student_id
        LEFT JOIN school_gradelevels g ON g.id=en.grade_id AND g.school_id=en.school_id
        WHERE en.school_id=$school AND en.syear=$year AND en.start_date<=CURRENT_DATE
        AND (en.end_date IS NULL OR en.end_date>=CURRENT_DATE)
        GROUP BY GROUPING SETS ((g.title,g.sort_order),()) ORDER BY g.sort_order NULLS FIRST,g.title NULLS FIRST");
    $d['groups']=DBGet("SELECT COALESCE(g.title,'Niveau non renseigne') AS title,COUNT(DISTINCT e.student_id) AS total FROM student_enrollment e
        LEFT JOIN school_gradelevels g ON g.id=e.grade_id AND g.school_id=e.school_id WHERE e.school_id=$school AND e.syear=$year
        AND e.start_date<=CURRENT_DATE AND (e.end_date IS NULL OR e.end_date>=CURRENT_DATE) GROUP BY g.title,g.sort_order ORDER BY g.sort_order,g.title");
    $d['total']=array_sum(array_map(static function($r){return (int)$r['TOTAL'];},array_values($d['groups'])));
    if(AllowUse('Scheduling/Courses.php')) $d['classes']=DBGet("SELECT cp.course_period_id,cp.title,COUNT(DISTINCT sc.student_id) AS total,
        COUNT(DISTINCT sc.student_id) FILTER(WHERE LOWER(TRIM(s.custom_200000000)) IN ('female','f','feminin','fille')) AS girls,
        COUNT(DISTINCT sc.student_id) FILTER(WHERE LOWER(TRIM(s.custom_200000000)) IN ('male','m','masculin','garcon')) AS boys FROM course_periods cp
        LEFT JOIN schedule sc ON sc.course_period_id=cp.course_period_id AND sc.school_id=$school AND sc.syear=$year
        AND sc.start_date<=CURRENT_DATE AND (sc.end_date IS NULL OR sc.end_date>=CURRENT_DATE)
        AND EXISTS(SELECT 1 FROM student_enrollment e WHERE e.student_id=sc.student_id AND e.school_id=$school AND e.syear=$year
            AND e.start_date<=CURRENT_DATE AND (e.end_date IS NULL OR e.end_date>=CURRENT_DATE))
        LEFT JOIN students s ON s.student_id=sc.student_id
        WHERE cp.school_id=$school AND cp.syear=$year GROUP BY cp.course_period_id,cp.title ORDER BY cp.title");
    if(AllowUse('Attendance/DailySummary.php')) {
        $rows=DBGet("SELECT * FROM (WITH active AS(SELECT DISTINCT student_id FROM student_enrollment WHERE school_id=$school AND syear=$year
            AND start_date<=CURRENT_DATE AND (end_date IS NULL OR end_date>=CURRENT_DATE)),
            daily AS(SELECT student_id,MIN(state_value) AS lo,MAX(state_value) AS hi FROM attendance_day WHERE syear=$year
                AND school_date=CURRENT_DATE GROUP BY student_id)
            SELECT COUNT(*) FILTER(WHERE lo=hi AND lo>=1) AS present,COUNT(*) FILTER(WHERE lo=hi AND lo=0) AS absent,
            COUNT(*) FILTER(WHERE lo=hi AND lo>0 AND lo<1) AS partial,COUNT(*) FILTER(WHERE lo IS NULL OR lo<>hi) AS unknown
            FROM active LEFT JOIN daily USING(student_id)) AS daily_summary");
        $row=reset($rows);
        foreach(['present','absent','partial','unknown'] as $key)$d[$key]=(int)($row[strtoupper($key)]??0);
    }
    if(AllowUse('Users/User.php'))$d['staff']=DBGet("SELECT staff_id,first_name,last_name FROM staff WHERE syear=$year AND profile='teacher'
        AND (current_school_id=$school OR position(',$school,' IN schools)>0) ORDER BY last_name,first_name LIMIT 101");
    return $d;
}

function SchoolSafeNativeDashboardSources(): string
{
    global $_ROSARIO, $RosarioModules;
    $escape=static function($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');};
    $html='';
    foreach((array)$RosarioModules as $module=>$active) {
        $menu=$_ROSARIO['Menu'][$module]??[];
        if(!$active||!$menu||!function_exists('Dashboard'.$module))continue;
        $route=$menu['default']??'';
        if(!$route||!AllowUse($route))continue;
        $title=$menu['title']??str_replace('_',' ',$module);
        $fragment=$_ROSARIO['Dashboard'][$module]??'';
        $html.='<details open data-native-dashboard-source="'.$escape($module).'"><summary>'.$escape($title).'</summary>';
        if(is_string($fragment)&&trim($fragment)!=='')$html.=$fragment;
        else $html.='<p class="sscx-empty">Aucune synthese disponible pour votre profil et le contexte selectionne.</p>';
        $html.='<p><a href="Modules.php?modname='.$escape($route).'">Ouvrir '.$escape($title).'</a></p></details>';
    }
    if(!$html)return '';
    return '<section class="ss-native-dashboard" aria-label="Syntheses Rosario"><h3>Syntheses Rosario</h3><div class="ss-dashboard-native-totals">'.$html.'</div></section>';
}

function SchoolSafePersonalOverview(string $profile, array $feed): void
{
    $escape=static function($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');};
    $school=(int)UserSchool(); $year=(int)UserSyear(); $staff=(int)User('STAFF_ID');
    $rows=[];
    $parent=$profile==='parent';
    $student=$profile==='student';
    $routes=($parent||$student)?[
        'Students/Student.php'=>'Dossier de mon enfant',
        'Grades/StudentAssignments.php'=>'Devoirs',
        'Grades/StudentGrades.php'=>'Cotes',
        'Grades/ReportCards.php'=>'Bulletins',
        'Attendance/StudentSummary.php'=>'Presences',
        'Student_Billing/StudentFees.php'=>'Frais scolaires',
        'Student_Billing/StudentPayments.php'=>'Paiements scolaires',
        'School_Setup/Calendar.php'=>'Calendrier',
    ]:[
        'Attendance/TakeAttendance.php'=>'Faire l\'appel',
        'Grades/Assignments.php'=>'Devoirs et evaluations',
        'Grades/Grades.php'=>'Enregistrer les cotes',
        'Grades/InputFinalGrades.php'=>'Notes finales',
        'Scheduling/Schedule.php'=>'Emploi du temps',
        'School_Setup/Calendar.php'=>'Calendrier',
    ];
    if($school>0&&$year>0&&$staff>0) {
        if($parent&&AllowUse('Students/Student.php')) {
            $rows=DBGet("SELECT DISTINCT s.student_id,s.first_name,s.last_name FROM students s
                JOIN students_join_users u ON u.student_id=s.student_id
                JOIN student_enrollment e ON e.student_id=s.student_id
                WHERE u.staff_id=$staff AND e.school_id=$school AND e.syear=$year
                AND e.start_date<=CURRENT_DATE AND (e.end_date IS NULL OR e.end_date>=CURRENT_DATE)
                ORDER BY s.last_name,s.first_name");
        } elseif($profile==='teacher'&&AllowUse('Grades/Grades.php')) {
            $rows=DBGet("SELECT course_period_id,title FROM course_periods
                WHERE (teacher_id=$staff OR secondary_teacher_id=$staff) AND school_id=$school AND syear=$year ORDER BY title");
        }
    }
    if($student&&$school>0&&$year>0&&AllowUse('Scheduling/Schedule.php')) {
        $id=(int)UserStudentID();
        if($id>0)$rows=DBGet("SELECT DISTINCT cp.course_period_id,cp.title FROM schedule sc JOIN course_periods cp ON cp.course_period_id=sc.course_period_id
            WHERE sc.student_id=$id AND sc.school_id=$school AND sc.syear=$year AND cp.school_id=$school AND cp.syear=$year
            AND sc.start_date<=CURRENT_DATE AND (sc.end_date IS NULL OR sc.end_date>=CURRENT_DATE) ORDER BY cp.title");
    }
    if($student){unset($routes['Students/Student.php']);$routes['Scheduling/Schedule.php']='Mon emploi du temps';}
    echo '<div class="sscx-overview-summary"><section><h3>'.($parent?'Mes enfants':($student?'Mes cours':'Mes groupes de cours')).'</h3><ul class="sscx-teacher-list">';
    foreach($rows as $row) {
        if($parent) {
            $url='Modules.php?modname=Students/Student.php&student_id='.(int)$row['STUDENT_ID'];
            $label=$row['FIRST_NAME'].' '.$row['LAST_NAME'];
        } elseif($student) {
            $url='Modules.php?modname=Scheduling/Schedule.php';
            $label=ParseMLField($row['TITLE']);
        } else {
            $url='Modules.php?modname=Grades/Grades.php&period='.(int)$row['COURSE_PERIOD_ID'].'&course_period_id='.(int)$row['COURSE_PERIOD_ID'];
            $label=ParseMLField($row['TITLE']);
        }
        echo '<li><a href="'.$escape($url).'">'.$escape($label).'</a></li>';
    }
    if(!$rows)echo '<li>'.($parent?'Aucun enfant actif accessible dans cette ecole.':'Aucun groupe de cours accessible.').'</li>';
    echo '</ul></section><aside><h3>Alertes et notifications</h3>';
    if($parent)echo '<a href="parent-notifications-view.php">Mes notifications</a>';
    echo SchoolSafeDashboardNativeWidget().'</aside></div><section class="sscx-overview-quick"><h3>Acces rapides</h3><div class="sscx-shortcuts">';
    foreach($routes as $route=>$label)if(AllowUse($route))echo '<a href="Modules.php?modname='.$escape($route).'">'.$escape($label).'</a>';
    echo '</div></section>'.SchoolSafeNativeDashboardSources().'<footer class="sscx-overview-footer"><section><h3>Annonces scolaires</h3><ul class="sscx-feed">';
    foreach($feed['activities'] as $item)echo '<li><time>'.$escape(substr($item['CREATED_AT'],0,10)).'</time><strong>'.$escape(ParseMLField($item['TITLE'])).'</strong></li>';
    if(!$feed['activities'])echo '<li>Aucune annonce publiee pour votre profil.</li>';
    echo '</ul></section><section><h3>Calendrier scolaire</h3><ul class="sscx-feed">';
    foreach($feed['events'] as $item)echo '<li><time>'.$escape($item['SCHOOL_DATE']).'</time><strong>'.$escape(ParseMLField($item['TITLE'])).'</strong></li>';
    if(!$feed['events'])echo '<li>Aucun evenement accessible dans les 30 prochains jours.</li>';
    echo '</ul></section></footer>';
}

function SchoolSafeOverview()
{
    global $_ROSARIO;
    require_once __DIR__.'/SchoolSafeDashboardNative.fnc.php';
    $d=SchoolSafeOverviewData();
    require_once __DIR__.'/SchoolSafeOverviewFeed.fnc.php';
    $feed=SchoolSafeOverviewFeed();
    $e=static function($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');};
    $profile=User('PROFILE');
    $titles=['admin'=>"Vue generale de l'ecole",'teacher'=>'Ma journee de classe','parent'=>'Le suivi de mes enfants','student'=>'Mon suivi scolaire'];
    $title=$titles[$profile]??'Mon tableau de bord';
    $icons=['students'=>'ContactRound','classes'=>'School','presence'=>'BadgeCheck','teachers'=>'ContactRound'];
    foreach(['codex-ui-v1','lot2-screens','overview-composition','schoolsafe-dashboard','schoolsafe-dashboard-reference'] as $css)echo '<link rel="stylesheet" href="assets/css/'.$css.'.css?v=reference-20">';
    $roles=['admin'=>'Administrateur','teacher'=>'Professeur','parent'=>'Parent','student'=>'Eleve'];
    echo '<header class="ss-dashboard-context"><div class="ss-dashboard-brand"><img src="/schoolsafe-card-bridge/studio-validated-20261008/logo-schoolsafe.png" width="40" height="40" alt=""><strong>SchoolSafe</strong></div>';
    if(AllowUse('Students/Student.php'))echo '<form class="ss-dashboard-search" method="get" action="Modules.php"><input type="hidden" name="modname" value="Students/Student.php"><input type="hidden" name="search_modfunc" value="list"><input name="last" aria-label="Rechercher un eleve par nom" placeholder="Rechercher un eleve par nom..."><button type="submit" title="Rechercher" aria-label="Rechercher"><img src="assets/themes/SchoolSafeCodex/navigation-icons/PackageSearch.svg" width="20" height="20" alt=""></button></form>';
    echo '<div><span>Ecole selectionnee</span><strong data-native-dashboard-context="school">'.$e(ParseMLField(SchoolInfo('TITLE'))).'</strong></div><div><span>Annee scolaire</span><strong data-native-dashboard-context="syear">'.$e(UserSyear()).'</strong></div><div><span>'.$e($roles[$profile]??'Profil connecte').'</span><strong>'.$e(User('NAME')).'</strong></div></header><script src="assets/js/schoolsafe-dashboard-context.js?v=2"></script>';
    if($profile==='admin'){
        require_once __DIR__.'/SchoolSafeReferenceDashboard.fnc.php';
        SchoolSafeReferenceDashboard($d,$feed);
        return;
    }
    if(in_array($profile,['parent','teacher','student'],true)) {
        require_once __DIR__.'/SchoolSafeDashboardTabs.fnc.php';
        [$active,$tabs]=SchoolSafeDashboardTabs();
        echo '<div class="sscx-app ss-connected-dashboard ss-personal-tabbed" data-dashboard-tab="'.$e($active).'" data-dashboard-profile="'.$e($profile).'"><section class="sscx-section sscx-role-overview"><header class="sscx-overview-header"><div><h2>'.$e($title).'</h2><p>'.$e(date('d/m/Y')).' <span class="sscx-badge">'.$e(UserSyear()).' · Rosario</span></p></div><a class="ss-dashboard-refresh" href="Modules.php?modname=misc/Portal.php">Actualiser</a></header>'.$tabs;
        require_once __DIR__.'/SchoolSafePersonalDashboardStats.fnc.php';
        echo SchoolSafePersonalDashboardStats($profile);
        require_once __DIR__.'/SchoolSafePersonalDashboardDetails.fnc.php';
        echo SchoolSafePersonalDashboardDetails($profile);
        SchoolSafePersonalOverview($profile,$feed);
        require_once __DIR__.'/SchoolSafeDashboardDetails.fnc.php';
        echo SchoolSafeDashboardDetails($d,$feed).'</section></div>';
        return;
    }
    echo '<div class="sscx-app ss-connected-dashboard" data-dashboard-profile="'.$e($profile).'"><section class="sscx-section sscx-role-overview sscx-school-overview"><header class="sscx-overview-header"><div><h2>'.$e($title).'</h2><p>'.$e(date('d/m/Y')).' <span class="sscx-badge">'.$e(UserSyear()).' · Rosario</span></p></div><a class="ss-dashboard-refresh" href="Modules.php?modname=misc/Portal.php" title="Actualiser la synthese" aria-label="Actualiser la synthese">Actualiser</a></header><div class="sscx-overview-summary"><div class="sscx-overview-core"><div class="sscx-metrics sscx-overview-metrics">';
    $metrics=[['students','Eleves inscrits',$d['total'],'Inscriptions actives'],['classes','Classes',User('PROFILE')==='admin'&&AllowUse('Scheduling/Courses.php')?count($d['classes']):null,'Groupes de cours Rosario'],['presence','Eleves presents',$d['present'],'Releves journaliers Rosario'],['teachers','Enseignants presents',$feed['staff_present'],'Dernier pointage du jour : entree']];
    foreach($metrics as $m)echo '<article class="sscx-metric" data-metric-kind="'.$m[0].'"><span><img src="assets/themes/SchoolSafeCodex/navigation-icons/'.$icons[$m[0]].'.svg" width="20" height="20" alt="">'.$m[1].'</span><strong>'.($m[2]===null?'&mdash;':$e($m[2])).'</strong><small>'.$m[3].'</small></article>';
    echo '</div><div class="sscx-overview-insights"><section class="sscx-snapshot"><h3>Presences du jour</h3>';
    if($d['present']!==null&&$d['total']>0){
        echo '<div class="sscx-presence-total"><strong>'.round($d['present']/$d['total']*100).'<small>%</small></strong><span>des eleves presents<br><b>'.$d['present'].' / '.$d['total'].'</b></span></div><dl class="sscx-presence-legend">';
        foreach(['present'=>'Presents','absent'=>'Absents','partial'=>'Journee partielle','unknown'=>'Non verifies'] as $key=>$label)echo '<div><dt>'.$label.'</dt><dd>'.$d[$key].'</dd></div>';
        echo '</dl>';
    }else echo '<p class="sscx-empty">Aucune presence journaliere disponible.</p>';
    echo '</section><section class="sscx-cycle-snapshot"><h3>Repartition par niveau</h3><ul class="sscx-cycle-distribution">';
    foreach($d['groups'] as $g)echo '<li><div><strong>'.$e(ParseMLField($g['TITLE'])).'</strong><span>'.(int)$g['TOTAL'].'</span></div><progress max="'.max(1,$d['total']).'" value="'.(int)$g['TOTAL'].'"></progress></li>';
    if(!$d['groups'])echo '<li>Aucune repartition autorisee disponible.</li>';
    echo '</ul></section></div></div><aside class="sscx-overview-inbox" aria-label="Alertes et dossiers"><h3>Alertes et notifications</h3><p class="sscx-empty">';
    echo User('PROFILE')==='parent'?'<a href="parent-notifications-view.php">Mes notifications</a>':'Consultez les annonces et alertes natives ci-dessous.';
    echo '</p></aside></div><section class="sscx-overview-quick"><h3>Acces rapides</h3><div class="sscx-shortcuts">';
    foreach(['Students/Student.php'=>'Eleves','Scheduling/Courses.php'=>'Classes','Users/User.php'=>'Personnel','Attendance/DailySummary.php'=>'Presences','Grades/StudentGrades.php'=>'Cotes','Student_Billing/StudentFees.php'=>'Frais scolaires','School_Setup/Calendar.php'=>'Calendrier'] as $route=>$label)if(AllowUse($route))echo '<a href="Modules.php?modname='.$e($route).'">'.$label.'</a>';
    require_once __DIR__.'/SchoolSafeFinanceOverview.fnc.php';
    echo '</div></section>'.SchoolSafeFinanceOverview().SchoolSafeNativeDashboardSources().'<div class="sscx-tabs" role="tablist" aria-label="Rubriques de la vue generale">';
    $tabs=['students'=>'Eleves'];
    if(User('PROFILE')==='admin'&&AllowUse('Users/User.php'))$tabs['staff']='Enseignants';
    if(AllowUse('Grades/GPARankList.php'))$tabs['awards']='Palmares';
    foreach($tabs as $id=>$label)echo '<button type="button" role="tab" id="ss-dashboard-tab-'.$id.'" aria-selected="'.($id==='students'?'true':'false').'" tabindex="'.($id==='students'?'0':'-1').'" aria-controls="ss-dashboard-'.$id.'" data-dashboard-tab="'.$id.'">'.$label.'</button>';
    echo '</div><section id="ss-dashboard-students" role="tabpanel" aria-labelledby="ss-dashboard-tab-students"><h3>Eleves et classes</h3>';
    if($d['classes']){
        echo '<div class="sscx-table-scroll sscx-class-table" tabindex="0" role="region" aria-label="Effectifs par classe"><table><thead><tr><th>Classe</th><th>Inscrits</th></tr></thead><tbody>';
        foreach($d['classes'] as $c)echo '<tr><th scope="row">'.$e(ParseMLField($c['TITLE'])).'</th><td>'.(int)$c['TOTAL'].'</td></tr>';
        echo '</tbody></table></div>';
    }else echo '<p class="sscx-empty">Aucun detail de classe disponible pour ce profil.</p>';
    echo '</section>';
    if(isset($tabs['staff'])){
        echo '<section id="ss-dashboard-staff" role="tabpanel" aria-labelledby="ss-dashboard-tab-staff" hidden><h3>Enseignants</h3><ul class="sscx-teacher-list">';
        $states=[];
        foreach($feed['presence'] as $record)$states[(int)$record['STAFF_ID']]=$record['TYPE'];
        foreach(array_slice(array_values($d['staff']),0,100) as $s){
            $state=$states[(int)($s['STAFF_ID']??0)]??null;
            $label=$state==='in'?'Entree enregistree':($state==='out'?'Sortie enregistree':'Non verifie');
            echo '<li><strong>'.$e($s['FIRST_NAME'].' '.$s['LAST_NAME']).'</strong><span>'.$label.'</span></li>';
        }
        if(!$d['staff'])echo '<li>Aucun enseignant renseigne.</li>';
        if(count($d['staff'])>100)echo '<li><a href="Modules.php?modname=Users/User.php">Consulter la liste complete</a></li>';
        echo '</ul>';
        if(AllowUse('Entry_Exit/Records.php'))echo '<a href="Modules.php?modname=Entry_Exit/Records.php">Consulter les pointages Rosario</a><p class="sscx-empty">Les enseignants non pointes ou rattaches a plusieurs ecoles restent non verifies.</p>';
        echo '</section>';
    }
    if(isset($tabs['awards']))echo '<section id="ss-dashboard-awards" role="tabpanel" aria-labelledby="ss-dashboard-tab-awards" hidden>'.SchoolSafeDashboardNativeWidget().'</section>';
    else echo SchoolSafeDashboardNativeWidget();
    echo '<footer class="sscx-overview-footer"><section><h3>Dernieres activites</h3><small>Annonces publiees de l\'ecole</small><ul class="sscx-feed">';
    foreach($feed['activities'] as $item)echo '<li><time>'.$e(substr($item['CREATED_AT'],0,10)).'</time><strong>'.$e(ParseMLField($item['TITLE'])).'</strong></li>';
    if(!$feed['activities'])echo '<li>Aucune annonce publiee pour votre profil.</li>';
    echo '</ul></section><section><h3>Calendrier scolaire</h3><ul class="sscx-feed">';
    foreach($feed['events'] as $item)echo '<li><time>'.$e($item['SCHOOL_DATE']).'</time><strong>'.$e(ParseMLField($item['TITLE'])).'</strong></li>';
    if(!$feed['events'])echo '<li>Aucun evenement accessible dans les 30 prochains jours.</li>';
    echo '</ul>';
    echo AllowUse('School_Setup/Calendar.php')?'<a href="Modules.php?modname=School_Setup/Calendar.php">Consulter le calendrier Rosario</a>':'<p class="sscx-empty">Calendrier non accessible.</p>';
    echo '</section></footer></section></div><script src="assets/js/schoolsafe-dashboard.js?v=1"></script>';
}
