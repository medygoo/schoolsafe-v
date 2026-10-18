<?php
function SchoolSafePalmaresFullName(array $row): string
{
    return implode(' ',array_filter(array_map(static fn($key)=>trim((string)($row[$key]??'')),
        ['FIRST_NAME','MIDDLE_NAME','LAST_NAME','NAME_SUFFIX']),static fn($part)=>$part!==''));
}
function SchoolSafePalmaresParentAwards(bool $events = false): array
{
    if (User('PROFILE') !== 'parent' || Config('SCHOOLSAFE_PALMARES') !== 'Y'
        || !AllowUse('Grades/GPARankList.php')) return [];
    $parent=(int)User('STAFF_ID'); $school=(int)UserSchool(); $year=(int)UserSyear(); $period=(int)UserMP();
    if(min($parent,$school,$year,$period)<1) return [];
    $join=$events ? 'JOIN schoolsafe_palmares_events ev ON ev.award_id=a.id' : '';
    $fields=$events ? 'ev.id AS event_id,ev.created_at,' : '';
    return DBGet("SELECT $fields a.id,a.student_id,a.scope_id,a.position,a.marking_period_id,
        s.first_name,s.middle_name,s.last_name,s.name_suffix,cp.title AS class_title,mp.title AS period_title
        FROM schoolsafe_palmares_awards a JOIN students s ON s.student_id=a.student_id
        JOIN school_marking_periods mp ON mp.marking_period_id=a.marking_period_id
        LEFT JOIN course_periods cp ON cp.course_period_id=a.scope_id $join
        WHERE a.school_id=$school AND a.syear=$year AND a.marking_period_id=$period AND a.active
        AND EXISTS(SELECT 1 FROM students_join_users u WHERE u.student_id=a.student_id AND u.staff_id=$parent)
        AND EXISTS(SELECT 1 FROM student_enrollment e WHERE e.student_id=a.student_id AND e.school_id=$school
          AND e.syear=$year AND e.start_date<=CURRENT_DATE AND (e.end_date IS NULL OR e.end_date>=CURRENT_DATE))
        AND (a.scope_id=0 OR EXISTS(SELECT 1 FROM schedule sc WHERE sc.student_id=a.student_id
          AND sc.course_period_id=a.scope_id AND sc.school_id=$school AND sc.syear=$year
          AND sc.start_date<=CURRENT_DATE AND (sc.end_date IS NULL OR sc.end_date>=CURRENT_DATE)))
        ORDER BY a.scope_id,a.student_id" . ($events ? ',ev.id DESC' : '') . ' LIMIT 200');
}
function SchoolSafePalmaresParentInbox(): array
{
    $items=[];
    foreach(SchoolSafePalmaresParentAwards(true) as $row) {
        $scope=(int)$row['SCOPE_ID']===0 ? 'ecole' : 'classe';
        $name=SchoolSafePalmaresFullName($row);
        $items[]=['id'=>'palmares:'.$row['EVENT_ID'],'student_id'=>(string)$row['STUDENT_ID'],
            'student_name'=>$name,'event_type'=>'student.top10.'.$scope,
            'created_at'=>$row['CREATED_AT'],'payload'=>['message'=>$name.' est dans le Top 10 '.($scope==='ecole'?"de l'ecole":'de sa classe').'.',
                'scope'=>$scope,'position'=>(int)$row['POSITION'],'period'=>ParseMLField($row['PERIOD_TITLE']),
                'class'=>$scope==='classe'?ParseMLField($row['CLASS_TITLE']):'']];
    }
    return $items;
}
function SchoolSafePalmaresParentBadges(): string
{
    if(User('PROFILE')!=='parent'||Config('SCHOOLSAFE_PALMARES')!=='Y'||!AllowUse('Grades/GPARankList.php')) return '';
    $escape=static fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
    $html='<link rel="stylesheet" href="assets/js/schoolsafe-palmares.css?v=1"><section class="ss-native-dashboard" data-palmares-parent><h3>Distinctions de mes enfants</h3><ul data-palmares-badges>';
    foreach(SchoolSafePalmaresParentAwards() as $row) {
        $label=(int)$row['SCOPE_ID']===0?"Top 10 de l'ecole":'Top 10 de sa classe · '.ParseMLField($row['CLASS_TITLE']);
        $html.='<li><strong>'.$escape($label).'</strong> · '.$escape(SchoolSafePalmaresFullName($row)).' · '.$escape(ParseMLField($row['PERIOD_TITLE'])).'</li>';
    }
    return $html.'</ul><p data-palmares-event role="status" aria-live="polite"></p><a href="parent-notifications-view.php">Mes notifications</a></section>'
        .'<script defer src="assets/js/schoolsafe-palmares.js?v=1"></script>';
}
