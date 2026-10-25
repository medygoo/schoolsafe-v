<?php
function SchoolSafeDashboardDirectory(string $kind,bool $matrix=false): string
{
    if(User('PROFILE')!=='admin'||!in_array($kind,['students','personnel','parents'],true))return '';
    if(!AllowUse($kind==='students'?'Students/Student.php':'Users/User.php'))return '';
    if($kind==='parents'&&!AllowUse('Students/Student.php'))return '';
    $school=(int)UserSchool();$year=(int)UserSyear();
    if($school<1||$year<1)return '';
    $q=is_string($_GET['directory_search']??null)?mb_substr(trim($_GET['directory_search']),0,100):'';
    $page=filter_var($_GET['directory_page']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]])?:1;
    $offset=($page-1)*100;
    $needle=DBEscapeString(mb_strtolower($q));
    $active="en.school_id=$school AND en.syear=$year AND en.start_date<=CURRENT_DATE AND (en.end_date IS NULL OR en.end_date>=CURRENT_DATE)";
    if($kind==='students'){
        $courses=AllowUse('Scheduling/Courses.php');
        $group=$courses?"COALESCE(cp.title,'Sans cours affecte')":"COALESCE(g.title,'Niveau non renseigne')";
        $joins=$courses?"LEFT JOIN (schedule sc JOIN course_periods cp ON cp.course_period_id=sc.course_period_id AND cp.school_id=$school AND cp.syear=$year)
            ON sc.student_id=s.student_id AND sc.school_id=$school AND sc.syear=$year AND sc.start_date<=CURRENT_DATE AND (sc.end_date IS NULL OR sc.end_date>=CURRENT_DATE)":'';
        $sql="SELECT DISTINCT $group AS group_title,".($courses?'cp.course_period_id':'g.id')." AS group_id,s.student_id AS person_id,s.first_name,s.last_name,
            COALESCE(g.title,'Niveau non renseigne') AS detail,s.custom_200000000 AS gender
            FROM students s JOIN student_enrollment en ON en.student_id=s.student_id
            LEFT JOIN school_gradelevels g ON g.id=en.grade_id AND g.school_id=en.school_id $joins
            WHERE $active AND position('$needle' IN LOWER(CONCAT(s.last_name,' ',s.first_name,' ',s.student_id)))>0
            ";
        $title='Liste des eleves par '.($courses?'groupe de cours':'niveau');
        $headers=['Eleve','Identifiant','Niveau','Sexe'];
    }elseif($kind==='personnel'){
        $sql="SELECT COALESCE(p.title,s.profile) AS group_title,s.profile_id AS group_id,s.staff_id AS person_id,s.first_name,s.last_name,s.profile AS detail
            FROM staff s LEFT JOIN user_profiles p ON p.id=s.profile_id
            WHERE s.syear=$year AND s.profile IN ('admin','teacher')
            AND (s.current_school_id=$school OR position(',$school,' IN s.schools)>0)
            AND position('$needle' IN LOWER(CONCAT(s.last_name,' ',s.first_name,' ',s.staff_id)))>0
            ";
        $title='Repertoire du personnel par fonction';$headers=['Personne','Identifiant','Profil natif'];
    }else{
        $sql="SELECT DISTINCT COALESCE(g.title,'Niveau non renseigne') AS group_title,g.id AS group_id,p.staff_id AS person_id,p.first_name,p.last_name,
            CONCAT(s.first_name,' ',s.last_name) AS detail,s.student_id AS child_id
            FROM staff p JOIN students_join_users u ON u.staff_id=p.staff_id JOIN students s ON s.student_id=u.student_id
            JOIN student_enrollment en ON en.student_id=s.student_id LEFT JOIN school_gradelevels g ON g.id=en.grade_id AND g.school_id=en.school_id
            WHERE p.syear=$year AND p.profile='parent' AND $active
            AND (p.schools IS NULL OR position(',$school,' IN p.schools)>0)
            AND position('$needle' IN LOWER(CONCAT(p.last_name,' ',p.first_name,' ',s.last_name,' ',s.first_name)))>0
            ";
        $title='Parents et enfants rattaches';$headers=['Parent','Identifiant','Enfant'];
    }
    $e=static function($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');};
    $groups=DBGet("SELECT group_title,group_id,COUNT(*) AS total FROM ($sql) directory GROUP BY group_title,group_id ORDER BY group_title,group_id");
    $selected=is_string($_GET['directory_group']??null)?$_GET['directory_group']:'';
    $filter='';$valid=false;
    foreach($groups as $groupRow){
        $key=$groupRow['GROUP_ID']===null?'none':(string)(int)$groupRow['GROUP_ID'];
        if($selected===$key){$valid=true;$filter=$key==='none'?' WHERE group_id IS NULL':' WHERE group_id='.(int)$key;}
    }
    if(!$valid&&$groups){
        $first=reset($groups);$selected=$first['GROUP_ID']===null?'none':(string)(int)$first['GROUP_ID'];
        $filter=$selected==='none'?' WHERE group_id IS NULL':' WHERE group_id='.(int)$selected;
    }elseif(!$valid)$selected='';
    $rows=DBGet("SELECT * FROM ($sql) directory$filter ORDER BY group_title,group_id,last_name,first_name,person_id".($kind==='parents'?',child_id':'')." LIMIT 101 OFFSET $offset");
    $payments=[];$showPayments=$kind==='students'&&AllowUse('Student_Billing/DailyTransactions.php');
    if($showPayments){
        $ids=array_unique(array_map(static function($row){return (int)$row['PERSON_ID'];},$rows));
        if($ids){
            $balances=DBGet("SELECT s.student_id,COALESCE(f.total,0) AS fees,COALESCE(p.total,0) AS paid FROM students s
                LEFT JOIN (SELECT student_id,SUM(amount) AS total FROM billing_fees WHERE school_id=$school AND syear=$year GROUP BY student_id) f ON f.student_id=s.student_id
                LEFT JOIN (SELECT student_id,SUM(amount) AS total FROM billing_payments WHERE school_id=$school AND syear=$year GROUP BY student_id) p ON p.student_id=s.student_id
                WHERE s.student_id IN (".implode(',',$ids).")");
            foreach($balances as $balance)$payments[(int)$balance['STUDENT_ID']]=$balance;
        }
        $headers=array_merge($headers,['Facture','Paye','Solde']);
    }
    $attendance=[];$showAttendance=$kind==='students'&&AllowUse('Attendance/DailySummary.php');
    if($showAttendance){
        $ids=array_unique(array_map(static function($row){return (int)$row['PERSON_ID'];},$rows));
        if($ids){
            $daily=DBGet("SELECT student_id,MIN(state_value) AS lo,MAX(state_value) AS hi FROM attendance_day WHERE syear=$year AND school_date=CURRENT_DATE AND student_id IN (".implode(',',$ids).") GROUP BY student_id");
            foreach($daily as $day)$attendance[(int)$day['STUDENT_ID']]=$day;
        }
        $headers[]='Presence du jour';
    }
    if($kind==='students')$headers[]='Dossier et suivi';
    $matrixValues=[];
    if($matrix&&in_array($kind,['students','personnel'],true)){
        require_once __DIR__.'/SchoolSafeMatrixDetails.fnc.php';
        [$extraHeaders,$matrixValues]=SchoolSafeMatrixDetails($kind,$rows,$school,$year);
        $headers=array_merge($headers,$extraHeaders);
    }
    $more=count($rows)>100;$rows=array_slice(array_values($rows),0,100);
    $html='<section class="ss-dashboard-directory"><h3>'.$title.'</h3><form class="ss-directory-search" method="get" action="Modules.php"><input type="hidden" name="modname" value="misc/Portal.php"><input type="hidden" name="dashboard_tab" value="'.$kind.'"><input type="hidden" name="directory_group" value="'.$e($selected).'"><label>Rechercher <input name="directory_search" value="'.$e($q).'" maxlength="100"></label><button type="submit">Rechercher</button><a href="Modules.php?modname=misc/Portal.php&amp;dashboard_tab='.$kind.'">Reinitialiser</a></form>';
    $html.='<nav class="ss-directory-groups" aria-label="Choisir un groupe">';
    foreach($groups as $groupRow){
        $key=$groupRow['GROUP_ID']===null?'none':(string)(int)$groupRow['GROUP_ID'];
        $href=http_build_query(['modname'=>'misc/Portal.php','dashboard_tab'=>$kind,'directory_search'=>$q,'directory_group'=>$key]);
        $html.='<a class="button"'.($selected===$key?' aria-current="true"':'').' href="Modules.php?'.$e($href).'">'.$e(ParseMLField($groupRow['GROUP_TITLE'])).' ('.(int)$groupRow['TOTAL'].')</a>';
    }
    $html.='</nav>';
    if($matrix&&$kind==='students'&&$selected!==''&&$selected!=='none'&&AllowUse('Scheduling/Courses.php')&&AllowUse('Users/User.php')){
        $teachers=DBGet("SELECT s.first_name,s.last_name FROM course_periods cp JOIN staff s ON s.staff_id=cp.teacher_id AND s.syear=cp.syear WHERE cp.course_period_id=".(int)$selected." AND cp.school_id=$school AND cp.syear=$year AND (s.current_school_id=$school OR position(',$school,' IN s.schools)>0)");
        $names=array_map(static fn($r)=>$r['FIRST_NAME'].' '.$r['LAST_NAME'],$teachers);
        $html.='<p>Enseignant affecte au groupe : '.$e($names?implode(', ',$names):'Non renseigne').'.</p>';
    }
    if($matrix&&$kind==='personnel')$html.='<p>Pointages et absences : mois en cours. Paie : annee scolaire. Le solde comptable ne declenche aucune deduction. Un pointage manquant ne prouve pas une absence.</p>';
    if($kind==='students')$html.='<p>Inscriptions actives. Un eleve peut figurer dans plusieurs groupes de cours.</p>';
    if($kind==='parents')$html.='<p>Une ligne par lien parent-enfant actif dans cette ecole.</p>';
    $html.='<div class="ss-directory-scroll"><table><thead><tr>';
    foreach($headers as $label)$html.='<th scope="col">'.$label.'</th>';
    $html.='</tr></thead><tbody>';$last=null;
    foreach($rows as $row){
        $group=$row['GROUP_TITLE'].'|'.($row['GROUP_ID']??'');
        if($group!==$last){$html.='<tr class="ss-directory-group"><th colspan="'.count($headers).'" scope="rowgroup">'.$e(ParseMLField($row['GROUP_TITLE'])).'</th></tr>';$last=$group;}
        $id=(int)$row['PERSON_ID'];$url=$kind==='students'?'Students/Student.php&amp;student_id=':'Users/User.php&amp;staff_id=';
        $html.='<tr><th scope="row"><a href="Modules.php?modname='.$url.$id.'">'.$e($row['FIRST_NAME'].' '.$row['LAST_NAME']).'</a></th><td>'.$id.'</td><td>';
        if($kind==='parents')$html.='<a href="Modules.php?modname=Students/Student.php&amp;student_id='.(int)$row['CHILD_ID'].'">'.$e($row['DETAIL']).'</a>';
        else $html.=$e(ParseMLField($row['DETAIL']));
        $html.='</td>';
        if($kind==='students'){
            $sex=mb_strtolower(trim((string)$row['GENDER']));
            $label=in_array($sex,['female','f','feminin','fille'],true)?'Fille':(in_array($sex,['male','m','masculin','garcon'],true)?'Garcon':'Non renseigne / autre');
            $html.='<td>'.$label.'</td>';
        }
        if($showPayments){
            $balance=$payments[$id]??['FEES'=>0,'PAID'=>0];
            foreach([$balance['FEES'],$balance['PAID'],(float)$balance['FEES']-(float)$balance['PAID']] as $amount)$html.='<td>'.$e(html_entity_decode(strip_tags(Currency($amount)),ENT_QUOTES,'UTF-8')).'</td>';
        }
        if($showAttendance){
            $day=$attendance[$id]??null;$status='Non verifie';
            if($day&&$day['LO']!==null&&$day['LO']===$day['HI']){
                $value=(float)$day['LO'];$status=$value>=1?'Present':($value===0.0?'Absent':'Journee partielle');
            }
            $html.='<td>'.$status.'</td>';
        }
        if($kind==='students'){
            $html.='<td><a href="Modules.php?modname=Students/Student.php&amp;student_id='.$id.'">Dossier complet</a>';
            foreach(['Grades/StudentGrades.php'=>'Cotes','Grades/ReportCards.php'=>'Bulletins','Student_Billing/StudentFees.php'=>'Paiements'] as $route=>$label)if(AllowUse($route))$html.='<br><a href="Modules.php?modname='.$route.'&amp;student_id='.$id.'">'.$label.'</a>';
            $html.='</td>';
        }
        if($matrix)foreach($matrixValues[$id]??[] as $value)$html.='<td>'.$value.'</td>';
        $html.='</tr>';
    }
    if(!$rows)$html.='<tr><td colspan="'.count($headers).'">Aucun resultat accessible.</td></tr>';
    $html.='</tbody></table></div><nav class="ss-directory-pages" aria-label="Pages du repertoire"><span>Page '.$page.'</span>';
    foreach(['Precedente'=>$page-1,'Suivante'=>$page+1] as $label=>$target)if(($target<$page&&$page>1)||($target>$page&&$more))$html.='<a href="Modules.php?'.$e(http_build_query(['modname'=>'misc/Portal.php','dashboard_tab'=>$kind,'directory_search'=>$q,'directory_group'=>$selected,'directory_page'=>$target])).'">'.$label.'</a>';
    return $html.'</nav></section>';
}
