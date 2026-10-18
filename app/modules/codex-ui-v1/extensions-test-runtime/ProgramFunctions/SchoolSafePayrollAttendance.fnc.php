<?php
function SchoolSafePayrollAttendance(): string
{
    if(!empty($_REQUEST['print_statements'])||isset($_REQUEST['_ROSARIO_PDF'])||!AllowUse('Entry_Exit/Records.php'))return '';
    $staff=(int)UserStaffID();$school=(int)UserSchool();$year=(int)UserSyear();
    if($staff<1||$school<1||$year<1||!in_array(User('PROFILE'),['admin','teacher'],true))return '';
    if(User('PROFILE')==='teacher'&&$staff!==(int)User('STAFF_ID'))return '';
    $people=DBGet("SELECT schools FROM staff WHERE staff_id=$staff AND syear=$year AND profile IN ('admin','teacher') AND (current_school_id=$school OR position(',$school,' IN schools)>0)");
    if(count($people)!==1)return '';
    $today=new DateTimeImmutable('today',new DateTimeZone('Africa/Kinshasa'));
    $month=(string)($_GET['payroll_month']??$today->format('Y-m'));
    if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$month))$month=$today->format('Y-m');
    $from=$month.'-01';$until=(new DateTimeImmutable($from))->modify('+1 month')->format('Y-m-d');
    $single=reset($people)['SCHOOLS']===",$school,";
    // Native Entry/Exit has no school key: ambiguous legacy records stay out.
    $scope=$single?'':" AND EXISTS(SELECT 1 FROM schoolsafe_device_events e JOIN schoolsafe_devices d ON d.id=e.device_id WHERE e.record_id=r.id AND d.school_id=$school)";
    $rows=DBGet("SELECT CAST(r.recorded_at AS DATE) AS day,COUNT(*) FILTER(WHERE r.type='in') AS entries,
        COUNT(*) FILTER(WHERE r.type='out') AS exits,MIN(r.recorded_at) AS first_event,MAX(r.recorded_at) AS last_event
        FROM entry_exit_staff_records r WHERE r.staff_id=$staff AND r.recorded_at>='$from' AND r.recorded_at<'$until'$scope
        GROUP BY CAST(r.recorded_at AS DATE) ORDER BY day");
    $e=static function($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');};
    $html='<section class="ss-device-hub"><h3>Presences du personnel · '.$e($month).'</h3><form method="get" action="Modules.php"><input type="hidden" name="modname" value="'.$e($_REQUEST['modname']).'"><input type="hidden" name="staff_id" value="'.$staff.'"><label>Mois<input type="month" name="payroll_month" value="'.$e($month).'"></label><button type="submit">Consulter</button></form><p>Pointages informatifs uniquement. Aucun calcul automatique de salaire ni deduction. Un pointage manquant ne prouve pas une absence.</p>';
    if(!$single)$html.='<p>Personnel multi-ecoles : seuls les pointages identifies par le Device Hub de cette ecole sont affiches.</p>';
    if($single&&AllowUse('Staff_Absences/Absences.php')){
        $absent=DBGetOne("SELECT COUNT(DISTINCT CAST(d.day AS DATE)) FROM staff_absences a CROSS JOIN LATERAL generate_series(GREATEST(CAST(a.start_date AS DATE),DATE '$from'),LEAST(CAST(a.end_date AS DATE),DATE '$until'-1),INTERVAL '1 day') d(day) WHERE a.staff_id=$staff AND a.syear=$year AND a.end_date>=a.start_date");
        $html.='<p>Jours touches par une absence declaree : '.(int)$absent.'. Une absence partielle n\'equivaut pas a une journee complete.</p>';
    }
    $html.='<div class="ss-device-table"><table><thead><tr><th>Jour</th><th>Entrees</th><th>Sorties</th><th>Premier pointage</th><th>Dernier pointage</th></tr></thead><tbody>';
    foreach($rows as $row)$html.='<tr><td>'.$e($row['DAY']).'</td><td>'.(int)$row['ENTRIES'].'</td><td>'.(int)$row['EXITS'].'</td><td>'.$e($row['FIRST_EVENT']).'</td><td>'.$e($row['LAST_EVENT']).'</td></tr>';
    if(!$rows)$html.='<tr><td colspan="5">Aucun pointage accessible pour ce mois.</td></tr>';
    return '<link rel="stylesheet" href="assets/css/schoolsafe-device-hub.css">'.$html.'</tbody></table></div></section>';
}
