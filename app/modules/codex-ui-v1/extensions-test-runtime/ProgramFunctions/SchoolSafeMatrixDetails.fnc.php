<?php
function SchoolSafeMatrixDetails(string $kind,array $rows,int $school,int $year): array
{
    $ids=array_unique(array_map(static fn($r)=>(int)$r['PERSON_ID'],$rows));
    if(!$ids)return [[],[]];
    $list=implode(',',$ids);$values=[];
    $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
    $money=static fn($v)=>html_entity_decode(strip_tags(Currency($v)),ENT_QUOTES,'UTF-8');
    if($kind==='students'){
        $headers=['Moyenne native / periode','Retards du mois','Responsable principal','Presences du mois','Absences du mois'];
        foreach($ids as $id)$values[$id]=['Non renseignee','Non accessible','<span data-matrix-primary="'.$id.'">Chargement du responsable...</span>','Non accessible','Non accessible'];
        $period=(int)UserMP();
        if($period>0&&AllowUse('Grades/GPARankList.php')){
            $grades=DBGet("SELECT sms.student_id,sms.cum_weighted_factor*s.reporting_gp_scale AS score,s.reporting_gp_scale AS scale,mp.title FROM student_mp_stats sms JOIN school_marking_periods mp ON mp.marking_period_id=sms.marking_period_id JOIN schools s ON s.id=mp.school_id AND s.syear=mp.syear WHERE mp.school_id=$school AND mp.syear=$year AND mp.marking_period_id=$period AND sms.student_id IN ($list) AND sms.cum_weighted_factor IS NOT NULL AND sms.gp_credits>0 AND s.reporting_gp_scale>0");
            foreach($grades as $r)$values[(int)$r['STUDENT_ID']][0]=$e(round((float)$r['SCORE'],2).' / '.$r['SCALE'].' · '.ParseMLField($r['TITLE']));
        }
        if(AllowUse('Attendance/DailySummary.php')){
            foreach($ids as $id)$values[$id][1]='0';
            foreach($ids as $id){$values[$id][3]='Aucun releve';$values[$id][4]='Aucun releve';}
            $days=DBGet("SELECT student_id,COUNT(*) FILTER(WHERE lo=hi AND lo>=1) AS present,COUNT(*) FILTER(WHERE lo=hi AND lo=0) AS absent FROM (SELECT a.student_id,a.school_date,MIN(a.state_value) AS lo,MAX(a.state_value) AS hi FROM attendance_day a WHERE a.syear=$year AND a.student_id IN ($list) AND a.school_date>=date_trunc('month',CURRENT_DATE) AND a.school_date<=CURRENT_DATE AND EXISTS(SELECT 1 FROM student_enrollment en WHERE en.student_id=a.student_id AND en.school_id=$school AND en.syear=$year AND en.start_date<=a.school_date AND (en.end_date IS NULL OR en.end_date>=a.school_date)) GROUP BY a.student_id,a.school_date) d GROUP BY student_id");
            foreach($days as $r){$id=(int)$r['STUDENT_ID'];$values[$id][3]=(string)(int)$r['PRESENT'];$values[$id][4]=(string)(int)$r['ABSENT'];}
            // T is the native tardy short code, not a missing attendance record.
            $late=DBGet("SELECT a.student_id,COUNT(DISTINCT a.school_date) AS total FROM attendance_period a JOIN attendance_codes c ON c.id=a.attendance_code AND c.school_id=$school AND c.syear=$year WHERE c.short_name='T' AND a.student_id IN ($list) AND a.school_date>=date_trunc('month',CURRENT_DATE) AND a.school_date<=CURRENT_DATE AND EXISTS(SELECT 1 FROM course_periods cp WHERE cp.course_period_id=a.course_period_id AND cp.school_id=$school AND cp.syear=$year) GROUP BY a.student_id");
            foreach($late as $r)$values[(int)$r['STUDENT_ID']][1]=(string)(int)$r['TOTAL'];
        }
    }else{
        $headers=['Cours / groupes affectes','Jours pointes ce mois','Absences declarees ce mois','Salaires enregistres','Paiements enregistres','Solde comptable'];
        foreach($ids as $id)$values[$id]=array_fill(0,6,'Non accessible');
        if(AllowUse('Scheduling/Courses.php')){
            foreach($ids as $id)$values[$id][0]='Non affecte';
            $courses=DBGet("SELECT cp.teacher_id,cp.title,c.title AS course_title FROM course_periods cp JOIN courses c ON c.course_id=cp.course_id AND c.school_id=cp.school_id AND c.syear=cp.syear WHERE cp.school_id=$school AND cp.syear=$year AND cp.teacher_id IN ($list) ORDER BY cp.teacher_id,cp.title");
            $groups=[];foreach($courses as $r)$groups[(int)$r['TEACHER_ID']][]=ParseMLField($r['COURSE_TITLE']).' · '.ParseMLField($r['TITLE']);
            foreach($groups as $id=>$titles)$values[$id][0]=$e(implode('; ',array_unique($titles)));
        }
        if(AllowUse('Entry_Exit/Records.php')){
            $days=DBGet("SELECT r.staff_id,COUNT(DISTINCT CAST(r.recorded_at AS DATE)) AS total FROM entry_exit_staff_records r JOIN staff s ON s.staff_id=r.staff_id AND s.syear=$year WHERE r.staff_id IN ($list) AND r.type='in' AND r.recorded_at>=date_trunc('month',CURRENT_DATE) AND r.recorded_at<CURRENT_DATE+1 AND (s.schools=',$school,' OR EXISTS(SELECT 1 FROM schoolsafe_device_events e JOIN schoolsafe_devices d ON d.id=e.device_id WHERE e.record_id=r.id AND d.school_id=$school)) GROUP BY r.staff_id");
            foreach($ids as $id)$values[$id][1]='Aucun pointage';
            foreach($days as $r)$values[(int)$r['STAFF_ID']][1]=(string)(int)$r['TOTAL'];
        }
        if(AllowUse('Staff_Absences/Absences.php')){
            foreach($ids as $id)$values[$id][2]='Attribution non verifiee';
            $single=DBGet("SELECT staff_id FROM staff WHERE syear=$year AND staff_id IN ($list) AND schools=',$school,'");
            foreach($single as $r)$values[(int)$r['STAFF_ID']][2]='0';
            $absence=DBGet("SELECT a.staff_id,COUNT(DISTINCT CAST(d.day AS DATE)) AS total FROM staff_absences a JOIN staff s ON s.staff_id=a.staff_id AND s.syear=a.syear CROSS JOIN LATERAL generate_series(GREATEST(CAST(a.start_date AS DATE),date_trunc('month',CURRENT_DATE)::date),LEAST(CAST(a.end_date AS DATE),CURRENT_DATE),INTERVAL '1 day') d(day) WHERE a.syear=$year AND a.staff_id IN ($list) AND s.schools=',$school,' AND a.end_date>=a.start_date GROUP BY a.staff_id");
            foreach($absence as $r)$values[(int)$r['STAFF_ID']][2]=(string)(int)$r['TOTAL'];
        }
        $totals=[];
        foreach([['accounting_salaries','Accounting/Salaries.php',3],['accounting_payments','Accounting/StaffPayments.php',4]] as [$table,$permission,$index])if(AllowUse($permission)){
            foreach($ids as $id){$totals[$id][$index]=0;$values[$id][$index]=$e($money(0));}
            $amounts=DBGet("SELECT staff_id,SUM(amount) AS total FROM $table WHERE school_id=$school AND syear=$year AND staff_id IN ($list) GROUP BY staff_id");
            foreach($amounts as $r){$id=(int)$r['STAFF_ID'];$totals[$id][$index]=(float)$r['TOTAL'];$values[$id][$index]=$e($money($r['TOTAL']));}
        }
        foreach($totals as $id=>$amounts)if(isset($amounts[3],$amounts[4]))$values[$id][5]=$e($money($amounts[3]-$amounts[4]));
    }
    return [$headers,$values];
}
