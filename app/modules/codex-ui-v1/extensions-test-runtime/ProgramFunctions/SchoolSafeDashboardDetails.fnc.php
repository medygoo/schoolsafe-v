<?php
/** Detailed portal area; every reader retains its native authorization. */
function SchoolSafeDashboardDetailsLinks(string $html, string $active): string
{
    $previous = libxml_use_internal_errors(true);
    try {
        $doc = new DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="ss-detail-fragment">'.$html.'</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        foreach ($doc->getElementsByTagName('a') as $link) {
            $parts = parse_url($link->getAttribute('href'));
            if (($parts['path'] ?? '') !== 'Modules.php') continue;
            parse_str($parts['query'] ?? '', $query);
            if (($query['modname'] ?? '') !== 'misc/Portal.php' || !isset($query['dashboard_tab'])) continue;
            $query['details_tab'] = $query['dashboard_tab'] === 'overview' ? $active : $query['dashboard_tab'];
            $query['dashboard_tab'] = 'overview';
            $link->setAttribute('href', 'Modules.php?'.http_build_query($query).'#ss-dashboard-details');
        }
        foreach ($doc->getElementsByTagName('form') as $form) {
            $portal = false;
            foreach ($form->getElementsByTagName('input') as $input) {
                if ($input->getAttribute('name') === 'modname' && $input->getAttribute('value') === 'misc/Portal.php') $portal = true;
            }
            if (!$portal) continue;
            foreach ($form->getElementsByTagName('input') as $input) {
                if ($input->getAttribute('name') === 'dashboard_tab') $input->setAttribute('value', 'overview');
            }
            $input = $doc->createElement('input');
            $input->setAttribute('type', 'hidden'); $input->setAttribute('name', 'details_tab'); $input->setAttribute('value', $active);
            $form->appendChild($input); $form->setAttribute('action', 'Modules.php#ss-dashboard-details');
        }
        $result = '';
        foreach ($doc->getElementById('ss-detail-fragment')->childNodes as $node) $result .= $doc->saveHTML($node);
        return $result;
    } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
}

function SchoolSafeDashboardScanDetails(array $data): string
{
    require_once __DIR__.'/../schoolsafe-attendance/ArrivalDashboard.php';
    $e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $html = '';
    if (AllowUse('Attendance/DailySummary.php')) {
        $html .= '<h3>Presences officielles du jour</h3><p>'.$e(DBDate()).' - Perimetre du profil connecte.</p><div class="ss-detail-bars">';
        $total = max(1, (int)($data['total'] ?? 0));
        foreach (['present'=>'Presents','absent'=>'Absents','partial'=>'Journee partielle','unknown'=>'Non verifies'] as $key=>$label) {
            $value = $data[$key] ?? null;
            if ($value === null) continue;
            $html .= '<div><span>'.$label.'</span><strong>'.(int)$value.'</strong><meter min="0" max="'.max($total,(int)$value).'" value="'.(int)$value.'">'.(int)$value.'</meter></div>';
        }
        $html .= '</div>';
    }
    if (SchoolSafeArrivalViewPermission()) {
        $date = $_GET['details_date'] ?? DBDate();
        try { $date = SchoolSafeArrivalReadDate($date); } catch (InvalidArgumentException $error) { $date = DBDate(); }
        $html .= '<form method="get" action="Modules.php#ss-dashboard-details"><input type="hidden" name="modname" value="misc/Portal.php"><input type="hidden" name="dashboard_tab" value="overview"><input type="hidden" name="details_tab" value="presence"><label>Date des scans <input type="date" name="details_date" value="'.$e($date).'"></label><button type="submit">Afficher</button></form>';
        try {
            $context = ['profile'=>User('PROFILE'),'staff'=>User('STAFF_ID'),'school'=>UserSchool(),'year'=>UserSyear()];
            $read = SchoolSafeArrivalDashboardRead($context, $date, static function($sql) {
                ob_start(); try { $rows = DBGet($sql); } finally { ob_end_clean(); }
                if (!is_array($rows)) throw new RuntimeException('ARRIVAL_READ_UNAVAILABLE');
                return $rows;
            });
            $html .= '<h3>Scans d\'arrivee valides</h3><div class="ss-detail-totals"><div><span>Evenements retenus</span><strong>'.(int)$read['summary']['events'].'</strong></div><div><span>Eleves distincts</span><strong>'.(int)$read['summary']['students'].'</strong></div></div>'.SchoolSafeArrivalDashboardRender($read);
        } catch (Throwable $error) { $html .= '<p>Les preuves de scan ne sont pas disponibles dans ce perimetre.</p>'; }
    }
    return $html.'<p>Un scan ne remplace pas une presence en cours. Aucun scan manquant n\'est transforme automatiquement en absence. Les evenements retenus ne comptent pas les tentatives refusees ou les doublons techniques.</p>';
}

function SchoolSafeDashboardDetails(array $data, array $feed): string
{
    require_once __DIR__.'/SchoolSafeDashboardTabs.fnc.php';
    require_once __DIR__.'/SchoolSafeDashboardDirectory.fnc.php';
    require_once __DIR__.'/SchoolSafePersonnelStats.fnc.php';
    require_once __DIR__.'/SchoolSafeFinanceOverview.fnc.php';
    require_once __DIR__.'/SchoolSafePersonalDashboardDetails.fnc.php';
    require_once __DIR__.'/../schoolsafe-attendance/ArrivalDashboard.php';
    $saved = $_GET;
    try {
        $_GET['dashboard_tab'] = $_GET['details_tab'] ?? 'students';
        [$active, $unused] = SchoolSafeDashboardTabs();
        $choices = ['students'=>User('PROFILE')==='parent'?'Mes enfants':'Eleves'];
        if (User('PROFILE')==='admin') {
            if (!AllowUse('Students/Student.php')) unset($choices['students']);
            if (AllowUse('Users/User.php')) $choices['personnel']='Personnel';
            if (AllowUse('Users/User.php') && AllowUse('Students/Student.php')) $choices['parents']='Parents';
        }
        if (SchoolSafeArrivalViewPermission() || (User('PROFILE')==='admin' && AllowUse('Attendance/DailySummary.php'))) $choices['presence']='Scans et presences';
        if (AllowUse('Student_Billing/DailyTransactions.php') || AllowUse('Accounting/DailyTransactions.php') || (in_array(User('PROFILE'),['parent','student'],true) && (AllowUse('Student_Billing/StudentFees.php') || AllowUse('Student_Billing/StudentPayments.php')))) $choices['finance']='Finances';
        if (AllowUse('School_Setup/Calendar.php')) $choices['activities']='Activites et calendrier';
        $choices['modules']='Syntheses SchoolSafe';
        $requested = $saved['details_tab'] ?? 'students';
        $active = is_string($requested) && isset($choices[$requested]) ? $requested : array_key_first($choices);
        $_GET['dashboard_tab'] = $active;
        $e = static fn($v) => htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
        $html = '<link rel="stylesheet" href="assets/css/schoolsafe-dashboard-details.css?v=1"><section id="ss-dashboard-details" class="ss-dashboard-details"><header><h2>Informations detaillees</h2><p>Donnees natives actualisees a chaque consultation, selon vos droits et votre ecole.</p></header><nav aria-label="Rubriques detaillees du tableau de bord">';
        foreach ($choices as $key=>$label) $html .= '<a href="Modules.php?'.$e(http_build_query(['modname'=>'misc/Portal.php','dashboard_tab'=>'overview','details_tab'=>$key])).'#ss-dashboard-details"'.($active===$key?' aria-current="page"':'').'>'.$e($label).'</a>';
        $html .= '</nav><div class="ss-detail-panel"><h3>'.$e($choices[$active]).'</h3>';
        $body = '';
        if ($active==='presence') $body = SchoolSafeDashboardScanDetails($data);
        elseif (in_array($active,['students','personnel','parents'],true)) {
            $body = User('PROFILE')==='admin' ? SchoolSafeDashboardDirectory($active,true) : SchoolSafePersonalDashboardDetails(User('PROFILE'));
            if ($active==='students' && User('PROFILE')==='admin' && AllowUse('Students/Student.php')) {
                foreach ((array)($data['gender'] ?? []) as $row) {
                    if ((int)($row['IS_TOTAL'] ?? 0)!==1) continue;
                    $total=max(1,(int)$row['TOTAL']);
                    $chart='<h3>Effectifs des inscriptions actives</h3><div class="ss-detail-bars">';
                    foreach (['Filles'=>(int)$row['GIRLS'],'Garcons'=>(int)$row['BOYS'],'Non renseigne'=>max(0,(int)$row['TOTAL']-(int)$row['GIRLS']-(int)$row['BOYS'])] as $label=>$value) {
                        $chart.='<div><span>'.$label.'</span><strong>'.$value.'</strong><meter min="0" max="'.$total.'" value="'.$value.'">'.$value.'</meter></div>';
                    }
                    $body=$chart.'</div>'.$body;
                    break;
                }
            }
            if ($active==='personnel') $body .= SchoolSafePersonnelStats();
        } elseif ($active==='finance') $body = User('PROFILE')==='admin' ? SchoolSafeFinanceOverview() : SchoolSafePersonalDashboardDetails(User('PROFILE'));
        elseif ($active==='activities') {
            $body = '<table><thead><tr><th>Date</th><th>Evenement ou annonce</th></tr></thead><tbody>';
            foreach ($feed['events'] as $row) $body .= '<tr><td>'.$e($row['SCHOOL_DATE']).'</td><td>'.$e(ParseMLField($row['TITLE'])).'</td></tr>';
            foreach ($feed['activities'] as $row) $body .= '<tr><td>'.$e(substr($row['CREATED_AT'],0,10)).'</td><td>'.$e(ParseMLField($row['TITLE'])).'</td></tr>';
            $body .= '</tbody></table>';
        } else $body = SchoolSafeNativeDashboardSources();
        return $html.SchoolSafeDashboardDetailsLinks($body ?: '<p>Aucune information disponible avec vos droits actuels.</p>',$active).'</div></section>';
    } finally { $_GET = $saved; }
}
