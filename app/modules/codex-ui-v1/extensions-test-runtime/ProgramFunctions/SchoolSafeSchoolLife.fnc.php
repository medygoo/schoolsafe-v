<?php
function SchoolSafeBirthdayVisual(string $title,array $rows,bool $topTen = false): string
{
    $e=static function($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');};
    usort($rows,static function($a,$b){return strcmp($a['BIRTHDAY']['date'],$b['BIRTHDAY']['date']);});
    if ($topTen) $rows=array_slice($rows,0,10);
    $html='<section class="ss-school-life ss-birthday-visual"><details><summary><img src="assets/themes/SchoolSafeCodex/navigation-icons/ContactRound.svg" width="24" height="24" alt=""> '.$e($title).' <small>'.($topTen?'10 prochains anniversaires':'7 prochains jours').'</small></summary><ul>';
    foreach($rows as $row){
        $date=new DateTimeImmutable($row['BIRTHDAY']['date'],new DateTimeZone('Africa/Kinshasa'));
        $today=(new DateTimeImmutable('today',new DateTimeZone('Africa/Kinshasa')))->format('Y-m-d');
        $html.='<li><time datetime="'.$e($row['BIRTHDAY']['date']).'"><b>'.$date->format('d').'</b><small>'.$date->format('m/Y').'</small></time><div><strong>'.$e($row['FIRST_NAME'].' '.$row['LAST_NAME']).'</strong><small>'.$e($row['KIND']??'Eleve').' · '.($row['BIRTHDAY']['date']===$today?'Aujourd\'hui':$date->format('d/m/Y')).'</small></div></li>';
    }
    if(!$rows)$html.='<li class="ss-life-empty">'.($topTen?'Aucune date de naissance renseignee pour les personnes accessibles de cette ecole.':'Aucun anniversaire renseigne dans les sept prochains jours.').'</li>';
    return $html.'</ul></details></section>';
}

function SchoolSafePalmaresVisual(int $school, int $year): string
{
    if (!in_array(User('PROFILE'),['admin','teacher'],true) || !AllowUse('Grades/GPARankList.php')) return '';
    $e=static function($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');};
    $classes=DBGet("SELECT course_period_id,title FROM course_periods WHERE school_id=$school AND syear=$year ORDER BY title,course_period_id");
    $html='<section class="ss-school-life ss-palmares-visual"><header><img src="assets/themes/SchoolSafeCodex/navigation-icons/BadgeCheck.svg" width="24" height="24" alt=""><h3>Palmares · Top 10</h3></header>';
    $html.='<details><summary>Top 10 de toute l\'ecole</summary><div class="ss-palmares-columns">';
    $scopes=[['TITLE'=>'Toute l\'ecole','ID'=>null]];
    foreach($classes as $class)$scopes[]=['TITLE'=>'Classe · '.ParseMLField($class['TITLE']),'ID'=>(int)$class['COURSE_PERIOD_ID']];
    foreach($scopes as $index=>$scope){
        $title=$scope['TITLE'];$class=$scope['ID'];
        if($index===1)$html.='</div></details><details><summary>Top 10 de toutes les classes</summary><div class="ss-palmares-columns">';
        if($class!==null)$html.='<details class="ss-class-ranking"><summary>'.$e($title).'</summary>';
        $rows=SchoolSafeAcademicTopTen($school,$year,$class,(int)UserMP());
        $scopeId=$class??0;$period=(int)UserMP();
        $history=DBGet("SELECT student_id,position FROM schoolsafe_palmares_snapshots WHERE school_id=$school AND syear=$year AND scope_id=$scopeId AND marking_period_id=$period AND score_basis='native_weighted' AND snapshot_date=(SELECT MAX(snapshot_date) FROM schoolsafe_palmares_snapshots WHERE school_id=$school AND syear=$year AND scope_id=$scopeId AND marking_period_id=$period AND score_basis='native_weighted' AND snapshot_date<CURRENT_DATE)");
        $previous=[];foreach($history as $past)$previous[(int)$past['STUDENT_ID']]=(int)$past['POSITION'];
        $html.='<div><h4>'.$e($title).'</h4><ol class="ss-palmares-list">';
        foreach($rows as $row){
            $rank=(int)$row['POSITION'];
            $delta=isset($previous[(int)$row['STUDENT_ID']])?$previous[(int)$row['STUDENT_ID']]-$rank:null;
            $movement=!$history?'':($delta===null?'Nouveau':($delta===0?'Stable':($delta>0?'Monte de ':'Descend de ').abs($delta)));
            $html.='<li value="'.$rank.'"><span class="ss-rank" data-podium="'.($rank<=3?$rank:0).'">'.$rank.'</span><div><strong>'.$e(SchoolSafePalmaresFullName($row)).'</strong><small>'.$e(ParseMLField($row['PERIOD_TITLE'])).'</small><small>'.$e($movement).'</small></div><b>'.$e(number_format((float)$row['SCORE'],2,',',' ')).'<small> / '.$e($row['SCALE']).'</small></b></li>';
        }
        if(!$rows)$html.='<li class="ss-life-empty">Aucune moyenne native exploitable pour cette periode.</li>';
        $html.='</ol></div>';
        if($class!==null)$html.='</details>';
    }
    return $html.'</div></details></section>';
}

function SchoolSafeRemediationVisual(): string
{
    $profile=User('PROFILE');$school=(int)UserSchool();$year=(int)UserSyear();$user=(int)User('STAFF_ID');
    if(!in_array($profile,['admin','teacher','parent'],true)||$school<1||$year<1||$user<1||!AllowUse('SchoolSafe/RemediationRead.php'))return '';
    require_once __DIR__.'/../modules/SchoolSafe/Gateway.php';
    if(SchoolSafeEndpointGateway('rattrapage.php','GET')==='deny')return '';
    $scope="r.school_id=$school AND r.syear=$year";
    if($profile==='teacher')$scope.=" AND EXISTS(SELECT 1 FROM course_periods cp WHERE cp.course_id=r.course_id AND cp.school_id=$school AND cp.syear=$year AND cp.teacher_id=$user)";
    if($profile==='parent'){
        require_once __DIR__.'/../schoolsafe-remediation/PrincipalScope.fnc.php';
        try{$ids=SchoolSafeRemediationPrincipalScope($user,$school,$year);}catch(Throwable $error){return '<section class="ss-school-life"><h3>Rattrapages</h3><p>Verification des droits familiaux indisponible.</p></section>';}
        if(!$ids)return '';
        $scope.=' AND r.student_id IN ('.implode(',',array_map('intval',$ids)).") AND EXISTS(SELECT 1 FROM students_join_users u WHERE u.student_id=r.student_id AND u.staff_id=$user)";
    }
    $rows=DBGet("SELECT r.id,r.status,r.evidence_valid,s.first_name,s.last_name,c.title,
        (SELECT MIN(se.starts_at) FROM schoolsafe_remediation_sessions se WHERE se.case_id=r.id AND se.status='scheduled' AND se.starts_at>=CURRENT_TIMESTAMP) AS next_session
        FROM schoolsafe_remediation_cases r JOIN students s ON s.student_id=r.student_id JOIN courses c ON c.course_id=r.course_id
        WHERE $scope ORDER BY CASE r.status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 WHEN 'repeat' THEN 2 ELSE 3 END,r.created_at DESC,r.id DESC LIMIT 21");
    $e=static function($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');};
    $names=['pending'=>'A valider','approved'=>'Approuve','refused'=>'Refuse','passed'=>'Reussi','repeat'=>'A reprendre','closed'=>'Cloture'];
    $html='<section class="ss-school-life ss-remediation-visual"><header><img src="assets/themes/SchoolSafeCodex/navigation-icons/BookOpenCheck.svg" width="24" height="24" alt=""><h3>Rattrapages</h3><a href="Modules.php?modname=SchoolSafe/RemediationRead.php">Tous les dossiers</a></header><ul class="ss-remediation-line">';
    foreach(array_slice(array_values($rows),0,20) as $row){
        $date=$row['NEXT_SESSION']?(new DateTimeImmutable($row['NEXT_SESSION']))->setTimezone(new DateTimeZone('Africa/Kinshasa'))->format('d/m/Y H:i'):'Non programmee';
        $valid=in_array($row['EVIDENCE_VALID'],[true,'t','1',1],true);
        $html.='<li><div><strong>'.$e($row['FIRST_NAME'].' '.$row['LAST_NAME']).'</strong><small>'.$e(ParseMLField($row['TITLE'])).' · '.$e($date).'</small>'.(!$valid?'<small>Justificatifs a revoir</small>':'').'</div><span class="ss-life-status">'.$e($names[$row['STATUS']]??$row['STATUS']).'</span></li>';
    }
    if(!$rows)$html.='<li class="ss-life-empty">Aucun dossier de rattrapage accessible.</li>';
    return $html.'</ul>'.(count($rows)>20?'<p>20 dossiers prioritaires affiches. Consultez tous les dossiers pour la suite.</p>':'').'</section>';
}
