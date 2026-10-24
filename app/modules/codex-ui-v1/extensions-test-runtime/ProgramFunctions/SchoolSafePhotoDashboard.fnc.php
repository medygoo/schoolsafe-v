<?php
function SchoolSafeClosingDirectory(string $kind): string
{
    $saved=$_GET;
    foreach(['group','search','page'] as $key)$_GET['directory_'.$key]=$saved['matrix_'.$kind.'_'.$key]??($key==='page'?1:'');
    try{$html=SchoolSafeDashboardDirectory($kind,true);}finally{$_GET=$saved;}
    if($html==='')return '';
    $previous=libxml_use_internal_errors(true);$doc=new DOMDocument();
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="matrix-fragment">'.$html.'</div>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
    foreach($doc->getElementsByTagName('a') as $link){
        $href=$link->getAttribute('href');$parts=parse_url($href);
        if(($parts['path']??'')!=='Modules.php')continue;
        parse_str($parts['query']??'',$params);
        if(($params['dashboard_tab']??'')!==$kind)continue;
        $params['dashboard_tab']='overview';
        foreach(['group','search','page'] as $key)if(isset($params['directory_'.$key])){$params['matrix_'.$kind.'_'.$key]=$params['directory_'.$key];unset($params['directory_'.$key]);}
        $link->setAttribute('href','Modules.php?'.http_build_query($params).'#ss-closing-matrices');
    }
    foreach($doc->getElementsByTagName('input') as $input){
        if($input->getAttribute('name')==='dashboard_tab')$input->setAttribute('value','overview');
        foreach(['group','search','page'] as $key)if($input->getAttribute('name')==='directory_'.$key)$input->setAttribute('name','matrix_'.$kind.'_'.$key);
    }
    $result='';foreach($doc->getElementById('matrix-fragment')->childNodes as $node)$result.=$doc->saveHTML($node);
    libxml_clear_errors();libxml_use_internal_errors($previous);return $result;
}

function SchoolSafePhotoIcon(string $name): string
{
    return '<img src="assets/themes/SchoolSafeCodex/navigation-icons/'.htmlspecialchars($name,ENT_QUOTES,'UTF-8').'.svg" width="22" height="22" alt="">';
}

function SchoolSafePhotoAttendanceChart(array $days): string
{
    $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
    $html='<svg viewBox="0 0 320 160" role="img" aria-label="Taux de présence journalier enregistré"><title>Présences enregistrées du mois</title>';
    foreach([100,75,50,25,0] as $percent){$y=14+(100-$percent)*1.12;$html.='<line x1="32" x2="310" y1="'.$y.'" y2="'.$y.'"/><text x="26" y="'.($y+4).'" text-anchor="end">'.$percent.'%</text>';}
    if(!$days)return $html.'<text x="170" y="76" text-anchor="middle">Aucun relevé ce mois</text></svg>';
    $points=[];$count=count($days);
    foreach(array_values($days) as $i=>$day){$x=$count===1?171:32+$i*278/($count-1);$rate=100*(float)$day['PRESENT']/max(1,(int)$day['KNOWN']);$y=126-$rate*1.12;$points[]=$x.','.$y;
        $html.='<circle cx="'.$x.'" cy="'.$y.'" r="2.5"><title>'.$e($day['DAY'].' : '.round($rate,1).'%').'</title></circle>';
        if($i===0||$i===$count-1)$html.='<text x="'.$x.'" y="150" text-anchor="middle">'.$e(substr($day['DAY'],8,2)).'</text>';
    }
    $html.='<polygon class="ss-photo-chart-area" points="32,126 '.implode(' ',$points).' 310,126"/><polyline points="'.implode(' ',$points).'"/></svg>';
    return $html;
}

/** Reposition the already-authorized birthday fragment without another query or copy. */
function SchoolSafePhotoSplitSchoolLife(string $html): array
{
    if($html==='')return ['',''];
    $previous=libxml_use_internal_errors(true);$doc=new DOMDocument();
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="photo-school-life">'.$html.'</div>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
    $xpath=new DOMXPath($doc);$birthdays='';
    foreach($xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," ss-birthday-visual ")]') as $node){
        $details=$node->getElementsByTagName('details')->item(0);
        if($details)$details->setAttribute('open','');
        $birthdays.=$doc->saveHTML($node);$node->parentNode->removeChild($node);
    }
    $remaining='';foreach($doc->getElementById('photo-school-life')->childNodes as $node)$remaining.=$doc->saveHTML($node);
    libxml_clear_errors();libxml_use_internal_errors($previous);
    return [$birthdays===''?'':'<section id="ss-photo-birthdays" aria-label="Anniversaires de l’école">'.$birthdays.'</section>',$remaining];
}

function SchoolSafePhotoDashboard(array $data,array $feed,string $tabs): void
{
    global $RosarioModules;
    if(User('PROFILE')!=='admin')return;
    require_once __DIR__.'/SchoolSafeFinanceOverview.fnc.php';
    require_once __DIR__.'/SchoolSafeSchoolLife.fnc.php';
    require_once __DIR__.'/SchoolSafePracticalClasses.fnc.php';
    require_once __DIR__.'/SchoolSafeDashboardDirectory.fnc.php';
    require_once __DIR__.'/SchoolSafePersonnelStats.fnc.php';
    require_once __DIR__.'/SchoolSafeSchoolLogo.fnc.php';
    $school=(int)UserSchool();$year=(int)UserSyear();
    if($school<1||$year<1)return;
    $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
    $money=static fn($v)=>html_entity_decode(strip_tags(Currency($v)),ENT_QUOTES,'UTF-8');
    $count=static function(string $sql):int{$rows=DBGet($sql);$row=reset($rows);return (int)($row['TOTAL']??0);};
    $staff=AllowUse('Users/User.php')?$count("SELECT COUNT(*) AS total FROM staff WHERE syear=$year AND profile IN ('admin','teacher') AND (current_school_id=$school OR position(',$school,' IN schools)>0)"):null;
    $staffDetail='Sexe non renseigne';
    if($staff!==null){
        $fields=DBGet("SELECT id FROM staff_fields WHERE LOWER(title) IN ('gender','sex','sexe','genre') ORDER BY id LIMIT 1");
        if($fields){
            $field='custom_'.(int)reset($fields)['ID'];
            $column=DBGet("SELECT column_name FROM information_schema.columns WHERE table_schema='public' AND table_name='staff' AND column_name='$field'");
            if($column){
                $sex=DBGet("SELECT COUNT(*) FILTER(WHERE LOWER(TRIM($field::text)) IN ('female','f','feminin','femme')) AS women,COUNT(*) FILTER(WHERE LOWER(TRIM($field::text)) IN ('male','m','masculin','homme')) AS men FROM staff WHERE syear=$year AND profile IN ('admin','teacher') AND (current_school_id=$school OR position(',$school,' IN schools)>0)");
                $sex=reset($sex);$women=(int)$sex['WOMEN'];$men=(int)$sex['MEN'];
                $staffDetail=$women.' femmes · '.$men.' hommes';
                if($staff>$women+$men)$staffDetail.=' · '.($staff-$women-$men).' non renseigne(s)';
            }
        }
    }
    $subjects=AllowUse('Scheduling/Courses.php')?$count("SELECT COUNT(*) AS total FROM courses WHERE school_id=$school AND syear=$year"):null;
    $billing=AllowUse('Student_Billing/DailyTransactions.php');$accounting=AllowUse('Accounting/DailyTransactions.php');
    $receipts=null;
    $dailyReceipts=null;$annualReceipts=null;$annualExpenses=null;
    foreach([[$billing,'billing_payments','payment_date'],[$accounting,'accounting_incomes','assigned_date']] as [$allowed,$table,$date]){
        if(!$allowed)continue;
        $rows=DBGet("SELECT COALESCE(SUM(amount),0) AS total FROM $table WHERE school_id=$school AND syear=$year AND $date>=date_trunc('month',CURRENT_DATE) AND $date<=CURRENT_DATE");
        $row=reset($rows);$receipts=($receipts??0)+(float)($row['TOTAL']??0);
        $cash=DBGet("SELECT COALESCE(SUM(amount) FILTER(WHERE $date=CURRENT_DATE),0) AS daily,COALESCE(SUM(amount),0) AS annual FROM $table WHERE school_id=$school AND syear=$year AND $date<=CURRENT_DATE");
        $cash=reset($cash);$dailyReceipts=($dailyReceipts??0)+(float)($cash['DAILY']??0);$annualReceipts=($annualReceipts??0)+(float)($cash['ANNUAL']??0);
    }
    if($accounting){$expense=DBGet("SELECT COALESCE(SUM(amount),0) AS total FROM accounting_payments WHERE school_id=$school AND syear=$year AND payment_date<=CURRENT_DATE");$expense=reset($expense);$annualExpenses=(float)($expense['TOTAL']??0);}
    $unpaid=null;
    if($billing&&AllowUse('Students/Student.php'))$unpaid=$count("SELECT COUNT(*) AS total FROM
        (SELECT student_id,SUM(amount) AS total FROM billing_fees WHERE school_id=$school AND syear=$year GROUP BY student_id) f
        LEFT JOIN (SELECT student_id,SUM(amount) AS total FROM billing_payments WHERE school_id=$school AND syear=$year GROUP BY student_id) p USING(student_id)
        WHERE f.total>COALESCE(p.total,0)");
    $known=(int)($data['present']??0)+(int)($data['absent']??0)+(int)($data['partial']??0);
    $rate=$known>0?round((int)$data['present']/$known*100,1):null;
    $alerts=[];
    if(($data['absent']??0)>0)$alerts[]=['Attendance/DailySummary.php','ShieldCheck','Absences enregistrées aujourd’hui',$data['absent'].' élève(s) absent(s)','danger'];
    if($unpaid>0)$alerts[]=['Student_Billing/DailyTransactions.php','WalletCards','Frais scolaires restant à régler',$unpaid.' dossier(s) avec solde positif','info'];
    foreach(array_slice(array_values($feed['activities']),0,2) as $activity)$alerts[]=['School_Setup/PortalNotes.php','MessagesSquare',ParseMLField($activity['TITLE']),substr($activity['CREATED_AT'],0,10),'success'];
    $alertTotal=($data['absent']??0)+($unpaid??0);
    $days=[];
    if(AllowUse('Attendance/DailySummary.php'))$days=DBGet("SELECT * FROM (WITH records AS (
        SELECT a.school_date,a.student_id,MIN(a.state_value) AS lo,MAX(a.state_value) AS hi FROM attendance_day a
        WHERE a.syear=$year AND a.school_date>=date_trunc('month',CURRENT_DATE) AND a.school_date<=CURRENT_DATE
        AND EXISTS(SELECT 1 FROM student_enrollment en WHERE en.student_id=a.student_id AND en.school_id=$school AND en.syear=$year
            AND en.start_date<=a.school_date AND (en.end_date IS NULL OR en.end_date>=a.school_date))
        GROUP BY a.school_date,a.student_id)
        SELECT school_date::text AS day,COUNT(*) FILTER(WHERE lo=hi AND lo IS NOT NULL) AS known,
        COUNT(*) FILTER(WHERE lo=hi AND lo>=1) AS present FROM records GROUP BY school_date
        HAVING COUNT(*) FILTER(WHERE lo=hi AND lo IS NOT NULL)>0 ORDER BY school_date) AS attendance_month");
    $finance=SchoolSafeFinanceOverview();
    $donut='';
    if($finance){
        // Reuse the existing financial renderer, rather than inventing diagram values.
        $previous=libxml_use_internal_errors(true);$doc=new DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8"><div>'.$finance.'</div>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
        $xpath=new DOMXPath($doc);$fragment=$xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," ss-expense-breakdown ")]')->item(0);
        if($fragment)$donut=$doc->saveHTML($fragment);
        libxml_clear_errors();libxml_use_internal_errors($previous);
    }
    $now=new DateTimeImmutable('now',new DateTimeZone('Africa/Kinshasa'));
    $schoolName=ParseMLField(SchoolInfo('TITLE'));
    $logoUrl=SchoolSafeSchoolLogoUrl($school);
    $logoCampus='/assets/dashboard-reference/campus-day-logo.webp';
    [$birthdays,$schoolLife]=SchoolSafePhotoSplitSchoolLife(SchoolSafeDashboardNativeWidget(false));
    echo '<link rel="stylesheet" href="assets/css/schoolsafe-photo-dashboard.css?v=school-practical-classes-20261024"><link rel="stylesheet" href="assets/css/schoolsafe-dashboard-matrices.css?v=1"><script defer src="assets/js/schoolsafe-matrix-primary.js?v=2"></script>';
    echo '<main id="ss-photo-dashboard" class="ss-photo-dashboard ss-connected-dashboard" data-dashboard-profile="admin" data-dashboard-tab="overview">';
    echo '<div class="ss-photo-toolbar"><a class="ss-photo-mobile-brand" href="Modules.php?modname=misc/Portal.php"><img src="/schoolsafe-card-bridge/studio-validated-20261008/logo-schoolsafe.png" alt="" width="38" height="38"><strong>School<span>Safe</span></strong></a>';
    echo '<figure class="ss-photo-school-logo" data-school-logo data-logo-stage="'.($logoUrl!==''?'school':'campus').'"><span class="ss-photo-logo-orbit" aria-hidden="true"></span><img src="'.$e($logoUrl!==''?$logoUrl:$logoCampus).'" data-logo-campus="'.$e($logoCampus).'" data-logo-brand="/schoolsafe-logo.png" alt="'.($logoUrl!==''?'Logo de l’école '.$e($schoolName):'Image par défaut de l’école '.$e($schoolName)).'" loading="lazy" width="120" height="120"><figcaption>'.$e($schoolName).'</figcaption></figure>';
    echo '<div data-photo-search></div><div data-photo-school></div><div data-photo-year></div></div>';
    echo '<section id="ss-photo-home" class="ss-photo-hero"><p class="ss-photo-greeting">Bonjour, <strong>Administrateur</strong></p><div class="ss-photo-introduction"><h1>Pilotez votre établissement<br>avec <span>clarté et confiance.</span></h1><p>'.$e($schoolName).' · Une vue claire de votre établissement.</p><div class="ss-photo-hero-actions"><a class="ss-photo-primary" href="#ss-photo-rubriques">'.SchoolSafePhotoIcon('ChartNoAxesCombined').'Modules de l’école</a>';
    if(AllowUse('School_Setup/Configuration.php'))echo '<a href="/Modules.php?modname=School_Setup/Configuration.php">'.SchoolSafePhotoIcon('FolderCog').'Configurer mon école</a>';
    echo '</div></div><time class="ss-photo-date" datetime="'.$now->format('c').'">'.$e($now->format('d/m/Y')).'<strong>'.$e($now->format('H:i')).'</strong></time><button class="ss-photo-theme" type="button" aria-label="Activer le mode sombre" aria-pressed="false">'.SchoolSafePhotoIcon('Palette').'<span>Mode sombre</span><i aria-hidden="true"></i></button></section>';
    echo SchoolSafePracticalClassesVisual($school,$year);
    $genderDetail='Inscriptions actives';
    foreach((array)($data['gender']??[]) as $gender){
        if((int)($gender['IS_TOTAL']??0)!==1)continue;
        $girls=(int)$gender['GIRLS'];$boys=(int)$gender['BOYS'];
        $unspecified=max(0,(int)$gender['TOTAL']-$girls-$boys);
        $genderDetail=$girls.' filles · '.$boys.' garçons';
        if($unspecified>0)$genderDetail.=' · '.$unspecified.' non renseigné(s)';
        break;
    }
    $metrics=[['students','ContactRound','Élèves inscrits',$data['total'],$genderDetail,'Students/Student.php'],
        ['staff','ContactRound','Personnel actif',$staff,$staffDetail,'Users/User.php'],
        ['classes','School','Classes',AllowUse('Scheduling/Courses.php')?count($data['classes']):null,'Groupes de cours','Scheduling/Courses.php'],
        ['subjects','BookOpenCheck','Matières',$subjects,'Année scolaire '.$year,'Scheduling/Courses.php'],
        ['receipts','Landmark','Recettes du mois',$receipts===null?null:$money($receipts),'Encaissements enregistrés','Student_Billing/DailyTransactions.php'],
        ['alerts','ShieldCheck','Alertes suivies',$alertTotal,'Absences et soldes scolaires','#ss-photo-alerts']];
    echo '<section class="ss-photo-metrics" aria-label="Indicateurs de l’école">';
    foreach($metrics as [$kind,$icon,$label,$value,$detail,$route]){
        if($value===null&&!AllowUse($route))continue;
        $url=str_starts_with($route,'#')?$route:'Modules.php?modname='.$route;
        if($kind==='receipts'&&!$billing)$url='Modules.php?modname=Accounting/DailyTransactions.php';
        echo '<a class="ss-photo-metric" data-photo-metric="'.$kind.'" href="'.$e($url).'"><span class="ss-photo-icon">'.SchoolSafePhotoIcon($icon).'</span><span class="ss-photo-metric-copy"><small>'.$e($label).'</small><strong>'.($value===null?'&mdash;':$e($value)).'</strong><span>'.$e($detail).'</span></span></a>';
    }
    echo '<article class="ss-photo-metric ss-photo-mobile-presence" data-photo-metric="presence"><span class="ss-photo-icon">'.SchoolSafePhotoIcon('ChartNoAxesCombined').'</span><span class="ss-photo-metric-copy"><small>Taux de présence</small><strong>'.($rate===null?'&mdash;':$e($rate).'%').'</strong><span>'.($rate===null?'Relevés non renseignés':'Relevés du jour').'</span></span></article></section>';
    $topTen=SchoolSafePalmaresMatrixVisual($school,$year);
    if($birthdays!==''||$topTen!=='')echo '<div class="ss-photo-top-row">'.$birthdays.$topTen.'</div>';
    $financeSummary='<section class="ss-closing-finance" aria-label="Encaissements et depenses">';
    foreach([['Encaissements du jour',$dailyReceipts,'Aujourd\'hui'],['Encaissements du mois',$receipts,'Mois en cours'],['Depenses',$annualExpenses,'Annee scolaire, a ce jour'],['Recettes',$annualReceipts,'Annee scolaire, a ce jour']] as [$label,$amount,$period])if($amount!==null)$financeSummary.='<article><span>'.$e($label).'</span><strong>'.$e($money($amount)).'</strong><small>'.$e($period).'</small></article>';
    $financeSummary.='</section>';
    echo '<div class="ss-photo-grid"><div class="ss-photo-core"><section id="ss-photo-rubriques" class="ss-photo-modules"><header><h2><span class="ss-photo-desktop-title">Modules principaux</span><span class="ss-photo-mobile-title">Accès rapides</span></h2><a href="#ss-photo-rubriques">Voir tout</a></header><div class="ss-photo-module-grid">';
    $modules=[['pilotage','Pilotage','LayoutDashboard','#ss-photo-home','Retour à l’accueil de l’école'],['school','École','School','Students/Student.php',($data['total']??0).' élèves inscrits'],['people','Personnel','ContactRound','Users/User.php',($staff??0).' membres'],['pedagogy','Pédagogie','BookOpenCheck','Scheduling/Courses.php',($subjects??0).' matières'],['finance','Finances','WalletCards','Student_Billing/DailyTransactions.php','Année scolaire '.$year],['accounting','Comptabilité','Landmark','Accounting/DailyTransactions.php','Année scolaire '.$year],['communication','Communication','MessagesSquare','School_Setup/PortalNotes.php',count($feed['activities']).' annonces accessibles'],['reports','Contrôle et rapports','ChartNoAxesCombined','Grades/GPARankList.php','Période sélectionnée']];
    foreach($modules as [$branch,$label,$icon,$route,$detail]){
        if($branch==='pilotage')$label='Tableau de bord';
        if(!str_starts_with($route,'#')&&!AllowUse($route))continue;
        $url=str_starts_with($route,'#')?$route:'Modules.php?modname='.$route;
        echo '<a class="ss-photo-module" data-photo-branch="'.$branch.'" data-open-native-branch="'.$branch.'" href="'.$e($url).'"><span class="ss-photo-icon">'.SchoolSafePhotoIcon($icon).'</span><div><h3>'.$e($label).'</h3><p>'.$e($detail).'</p><span class="ss-photo-module-link">Accéder &rarr;</span></div></a>';
    }
    echo '</div></section><div class="ss-photo-charts"><section class="ss-photo-chart"><header><h2>Fréquentation scolaire</h2><a href="Modules.php?modname=Attendance/DailySummary.php">Ce mois</a></header><strong class="ss-photo-attendance-rate">'.($rate===null?'&mdash;':$e($rate).'%').'</strong><p>'.($rate===null?'Aucun relevé journalier disponible':'Présences vérifiées du jour').'</p>'.SchoolSafePhotoAttendanceChart($days).'</section><section class="ss-photo-chart"><header><h2>Synthèse financière</h2><a href="Modules.php?modname=misc/Portal.php&amp;dashboard_tab=finance">Cette année</a></header><p>Dépenses par catégorie</p>'.($donut?:'<p class="ss-photo-empty">Données comptables non accessibles.</p>').'<a class="ss-photo-chart-link" href="Modules.php?modname=misc/Portal.php&amp;dashboard_tab=finance">Voir le rapport financier &rarr;</a></section><section class="ss-photo-chart"><header><h2>Top alertes</h2><a href="#ss-photo-alerts">Voir tout</a></header>';
    if(!$alerts)echo '<p class="ss-photo-empty">Aucune alerte prioritaire enregistrée.</p>';
    foreach(array_slice($alerts,0,3) as $i=>$alert)echo '<a class="ss-photo-top-alert" href="Modules.php?modname='.$e($alert[0]).'"><b>'.($i+1).'</b><span><strong>'.$e($alert[2]).'</strong><small>'.$e($alert[3]).'</small></span></a>';
    echo '</section></div></div><aside class="ss-photo-rail">';
    echo '<div class="ss-photo-school-life" aria-label="Palmarès, anniversaires et rattrapages">'.$schoolLife.SchoolSafeRemediationVisual().'</div>';
    echo '<section id="ss-photo-alerts" class="ss-photo-alerts"><header><h2>Alertes importantes</h2><a href="#ss-photo-rubriques">Tout voir</a></header>';
    if(!$alerts)echo '<p class="ss-photo-empty">Aucune alerte prioritaire enregistrée.</p>';
    foreach(array_slice($alerts,0,3) as $alert)echo '<a class="ss-photo-alert" data-severity="'.$alert[4].'" href="Modules.php?modname='.$e($alert[0]).'"><span class="ss-photo-icon">'.SchoolSafePhotoIcon($alert[1]).'</span><span><strong>'.$e($alert[2]).'</strong><small>'.$e($alert[3]).'</small></span><b aria-hidden="true">&rsaquo;</b></a>';
    require_once __DIR__.'/SchoolSafeDashboardDetails.fnc.php';
    echo '</section></aside></div>'.$financeSummary.SchoolSafeDashboardDetails($data,$feed).'<footer class="ss-photo-footer"><span>SchoolSafe · Votre allié serein et fort, près de vous.</span><span>&copy; '.$now->format('Y').' SchoolSafe.</span></footer>';
    echo '<div id="ss-photo-create" hidden><h3>Actions rapides</h3>';
    foreach(['Students/Student.php&student_id=new'=>'Ajouter un élève','Users/User.php&staff_id=new'=>'Ajouter un utilisateur','Student_Billing/StudentPayments.php'=>'Enregistrer un paiement'] as $route=>$label){$permission=explode('&',$route)[0];if(AllowEdit($permission))echo '<a href="Modules.php?modname='.$e($route).'">'.$e($label).'</a>';}
    echo '</div></main><script src="assets/js/schoolsafe-photo-dashboard.js?v=school-palmares-matrix-20261024"></script><script defer src="assets/js/schoolsafe-school-logo.js?v=1"></script>';
}
