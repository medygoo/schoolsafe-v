<?php
function SchoolSafeReferenceDashboard(array $data,array $feed): void
{
    require_once __DIR__.'/SchoolSafeFinanceOverview.fnc.php';
    require_once __DIR__.'/SchoolSafePersonnelStats.fnc.php';
    require_once __DIR__.'/SchoolSafeStudentDashboardDetails.fnc.php';
    require_once __DIR__.'/SchoolSafeDashboardTabs.fnc.php';
    require_once __DIR__.'/SchoolSafeDashboardDirectory.fnc.php';
    require_once __DIR__.'/SchoolSafeSchoolLife.fnc.php';
    [$active,$tabs]=SchoolSafeDashboardTabs();
    if($active==='overview'){
        require_once __DIR__.'/SchoolSafePhotoDashboard.fnc.php';
        SchoolSafePhotoDashboard($data,$feed,$tabs);
        return;
    }
    $directory=SchoolSafeDashboardDirectory($active);
    $staffGroups=[];$staffTotal=0;
    if(AllowUse('Users/User.php')){
        $school=(int)UserSchool();$year=(int)UserSyear();
        $staffGroups=DBGet("SELECT COALESCE(p.title,s.profile) AS title,COUNT(*) AS total FROM staff s LEFT JOIN user_profiles p ON p.id=s.profile_id
            WHERE s.syear=$year AND s.profile IN ('admin','teacher') AND (s.current_school_id=$school OR position(',$school,' IN s.schools)>0)
            GROUP BY p.title,s.profile ORDER BY title");
        foreach($staffGroups as $group)$staffTotal+=(int)$group['TOTAL'];
    }
    $e=static function($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');};
    echo '<div class="sscx-app ss-connected-dashboard ss-reference-dashboard" data-dashboard-tab="'.$e($active).'" data-dashboard-profile="admin"><section class="sscx-section sscx-role-overview"><header class="ss-reference-heading"><div><h2>Finances, personnel &amp; operations</h2><p>Une vue consolidee de votre etablissement.</p></div><time datetime="'.date('c').'">'.$e(date('d/m/Y')).'<br>'.$e(date('H:i')).'</time></header>'.$tabs;
    echo $directory;
    $finance=SchoolSafeFinanceOverview();
    if($finance)echo $finance;
    else echo '<p class="sscx-empty">Synthese financiere non accessible pour votre profil.</p>';
    echo '<div class="ss-reference-operations">';
    $operations=[
        ['Scheduling/Courses.php','School','Classes',count($data['classes']),'Groupes de cours'],
        ['Users/User.php','ContactRound','Personnel',$staffTotal,'Personnel de l\'ecole'],
        ['Attendance/DailySummary.php','BadgeCheck','Presences',$data['present'],'Élèves présents aujourd\'hui'],
        ['School_Setup/Calendar.php','BookOpenCheck','Calendrier',count($feed['events']),'Evenements a venir'],
        ['School_Setup/PortalNotes.php','MessagesSquare','Communication',count($feed['activities']),'Annonces accessibles'],
    ];
    foreach($operations as $item)if(AllowUse($item[0]))echo '<a class="ss-reference-operation" href="Modules.php?modname='.$e($item[0]).'"><img src="assets/themes/SchoolSafeCodex/navigation-icons/'.$item[1].'.svg" width="24" height="24" alt=""><div><strong>'.$item[2].'</strong><b>'.($item[3]===null?'&mdash;':$e($item[3])).'</b><small>'.$e($item[4]).'</small></div></a>';
    echo SchoolSafeRemediationVisual();
    echo '</div><div class="ss-reference-bottom sscx-overview-summary">';
    if(AllowUse('Users/User.php')){
        echo '<section class="ss-reference-personnel"><header><h3>Personnel</h3><a href="Modules.php?modname=misc/Portal.php&amp;dashboard_tab=personnel">Voir tout</a></header><table><thead><tr><th>Fonction</th><th>Effectif</th></tr></thead><tbody>';
        foreach($staffGroups as $group)echo '<tr><th scope="row">'.$e(ParseMLField($group['TITLE'])).'</th><td>'.(int)$group['TOTAL'].'</td></tr>';
        echo '<tr><th scope="row">Total</th><td>'.$staffTotal.'</td></tr></tbody></table><details><summary>Pointages des enseignants du jour</summary><table><thead><tr><th>Enseignant</th><th>Pointage du jour</th></tr></thead><tbody>';
        $states=[];foreach($feed['presence'] as $row)$states[(int)$row['STAFF_ID']]=$row['TYPE'];
        foreach(array_slice(array_values($data['staff']),0,6) as $row){$state=$states[(int)$row['STAFF_ID']]??null;$label=$state==='in'?'Entree enregistree':($state==='out'?'Sortie enregistree':'Non verifie');echo '<tr><th scope="row">'.$e($row['FIRST_NAME'].' '.$row['LAST_NAME']).'</th><td>'.$label.'</td></tr>';}
        if(!$data['staff'])echo '<tr><td colspan="2">Aucun enseignant renseigné.</td></tr>';
        echo '</tbody></table></details></section>';
    }
    echo '<section class="ss-reference-events"><header><h3>Activités et calendrier</h3>';
    if(AllowUse('School_Setup/Calendar.php'))echo '<a href="Modules.php?modname=School_Setup/Calendar.php">Voir tout</a>';
    echo '</header><ul>';
    foreach($feed['events'] as $row)echo '<li><time>'.$e($row['SCHOOL_DATE']).'</time><strong>'.$e(ParseMLField($row['TITLE'])).'</strong></li>';
    foreach($feed['activities'] as $row)echo '<li><time>'.$e(substr($row['CREATED_AT'],0,10)).'</time><strong>'.$e(ParseMLField($row['TITLE'])).'</strong></li>';
    if(!$feed['events']&&!$feed['activities'])echo '<li>Aucun evenement ou annonce accessible.</li>';
    echo '</ul></section><section class="ss-reference-alerts"><h3>Alertes et distinctions</h3>'.SchoolSafeDashboardNativeWidget(false).'</section></div>'.SchoolSafeStudentDashboardDetails($data).SchoolSafePersonnelStats().'<details class="ss-reference-school-details"><summary>Syntheses scolaires et modules Rosario</summary>'.SchoolSafeNativeDashboardSources().'</details></section></div>';
}
