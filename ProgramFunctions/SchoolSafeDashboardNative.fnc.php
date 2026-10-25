<?php
require_once __DIR__ . '/SchoolSafeDashboardEngines.fnc.php';
require_once __DIR__ . '/SchoolSafePalmaresAwards.fnc.php';

function SchoolSafeBirthdayRows(int $school, int $year, array $studentIds = []): array
{
    if ($school < 1 || $year < 1) return [];
    $today = new DateTimeImmutable('today', new DateTimeZone('Africa/Kinshasa'));
    $last = $today->modify('+7 days')->format('Y-m-d');
    $first = $today->format('Y-m-d');
    $scope = $studentIds ? ' AND s.student_id IN (' . implode(',', array_map('intval', $studentIds)) . ')' : '';
    $days = [];
    for ($i = 0; $i <= 7; $i++) $days[] = "'" . $today->modify('+' . $i . ' days')->format('m-d') . "'";
    $rows = DBGet("SELECT DISTINCT s.student_id,s.first_name,s.last_name,s.custom_200000004 AS birthdate,e.calendar_id
        FROM students s JOIN student_enrollment e ON e.student_id=s.student_id
        WHERE e.school_id=$school AND e.syear=$year AND e.start_date<='$first'
        AND (e.end_date IS NULL OR e.end_date>='$first')
        AND to_char(s.custom_200000004,'MM-DD') IN (" . implode(',', $days) . ")$scope ORDER BY s.student_id LIMIT 201");
    if (count($rows) > 200) throw new RuntimeException('BIRTHDAY_SCOPE_TOO_LARGE');
    $open = DBGet("SELECT calendar_id,school_date FROM attendance_calendar WHERE school_id=$school
        AND syear=$year AND school_date BETWEEN '$first' AND '$last' AND minutes>0");
    $calendars = [];
    foreach ($open as $day) $calendars[$day['CALENDAR_ID']][] = $day['SCHOOL_DATE'];
    $result = [];
    foreach ($rows as $row) {
        $birthday = SchoolSafeDashboardEngines::upcomingBirthday($row['BIRTHDATE'], $today, $calendars[$row['CALENDAR_ID']] ?? []);
        if ($birthday) $result[] = $row + ['BIRTHDAY' => $birthday];
    }
    return $result;
}

function SchoolSafeBirthdayParentInbox(array $principalIds, array $nativeIds, int $school, int $year): array
{
    if (Config('SCHOOLSAFE_BIRTHDAYS') !== 'Y') return [];
    $ids = [];
    foreach ($principalIds as $id) {
        if (is_string($id) && preg_match('/^[1-9][0-9]{0,8}$/D', $id) && in_array($id, $nativeIds, true)) $ids[] = (int)$id;
    }
    if (!$ids) return [];
    $result = [];
    $rows = DBGet("SELECT a.student_id,s.first_name,s.last_name,a.birthday_date,a.notice_date,a.school_open
        FROM schoolsafe_birthday_alerts a JOIN students s ON s.student_id=a.student_id
        WHERE a.school_id=$school AND a.syear=$year AND a.student_id IN (" . implode(',', $ids) . ")
        AND a.notice_date<=CURRENT_DATE AND a.birthday_date>=CURRENT_DATE-30
        ORDER BY a.notice_date DESC,a.student_id LIMIT 100");
    foreach ($rows as $row) {
        $birthday = ['date'=>$row['BIRTHDAY_DATE'], 'school_open'=>in_array($row['SCHOOL_OPEN'], [true,'t','1',1], true)];
        $result[] = ['id'=>'birthday:' . $row['STUDENT_ID'] . ':' . $birthday['date'],
            'student_id'=>(string)$row['STUDENT_ID'], 'student_name'=>$row['FIRST_NAME'] . ' ' . $row['LAST_NAME'],
            'event_type'=>'student.birthday', 'created_at'=>(new DateTimeImmutable($birthday['date']))->modify('-7 days')->format('Y-m-d') . ' 00:00:00',
            'payload'=>['message'=>$birthday['school_open'] ? "Anniversaire dans les sept prochains jours : celebration possible a l'ecole." : "Anniversaire dans les sept prochains jours : l'ecole est fermee ce jour-la.",
                'birthday_date'=>$birthday['date'], 'school_open'=>$birthday['school_open']]];
    }
    return $result;
}

function SchoolSafeStaffBirthdayRows(int $school, int $year): array
{
    if ($school < 1 || $year < 1) return [];
    $fields = DBGet("SELECT id FROM staff_fields WHERE type='date' AND title='Date de naissance SchoolSafe' ORDER BY id LIMIT 1");
    if (!$fields) return [];
    $field = 'custom_' . (int)array_values($fields)[0]['ID'];
    $today = new DateTimeImmutable('today', new DateTimeZone('Africa/Kinshasa'));
    $days = [];
    for ($i=0; $i<=7; $i++) $days[] = "'" . $today->modify('+' . $i . ' days')->format('m-d') . "'";
    $rows = DBGet("SELECT staff_id,first_name,last_name,$field AS birthdate FROM staff
        WHERE syear=$year AND profile NOT IN ('parent','student')
        AND (current_school_id=$school OR position(',$school,' IN schools)>0)
        AND to_char($field,'MM-DD') IN (" . implode(',', $days) . ") ORDER BY staff_id LIMIT 201");
    if (count($rows)>200) throw new RuntimeException('BIRTHDAY_SCOPE_TOO_LARGE');
    $result = [];
    foreach ($rows as $row) {
        $birthday = SchoolSafeDashboardEngines::upcomingBirthday($row['BIRTHDATE'], $today, []);
        if ($birthday) $result[] = $row + ['BIRTHDAY'=>$birthday];
    }
    return $result;
}

function SchoolSafeAcademicTopTen(int $school, int $year, ?int $class = null, ?int $period = null, ?int $parent = null): array
{
    if ($school < 1 || $year < 1 || ($parent !== null && $parent < 1)) return [];
    $today = (new DateTimeImmutable('today', new DateTimeZone('Africa/Kinshasa')))->format('Y-m-d');
    if ($period === null) {
        $periods = DBGet("SELECT marking_period_id FROM school_marking_periods WHERE school_id=$school AND syear=$year
            AND does_grades='Y' AND start_date<='$today' AND end_date>='$today'
            ORDER BY (end_date-start_date),marking_period_id LIMIT 1");
        $period = (int)($periods[0]['MARKING_PERIOD_ID'] ?? $periods[1]['MARKING_PERIOD_ID'] ?? 0);
    }
    if ($period < 1) return [];
    $classScope = $class === null ? '' : " AND EXISTS(SELECT 1 FROM schedule sc WHERE sc.student_id=sms.student_id
        AND sc.school_id=$school AND sc.syear=$year AND sc.course_period_id=$class
        AND sc.start_date<='$today' AND (sc.end_date IS NULL OR sc.end_date>='$today'))";
    // Read Rosario's weighted averages. Ranking precedes the parent filter.
    $visibility = $parent === null ? ' WHERE position<=10' : " WHERE EXISTS(SELECT 1 FROM students_join_users u
        WHERE u.student_id=r.student_id AND u.staff_id=$parent)";
    return DBGet("SELECT * FROM (WITH ranked AS (
        SELECT sms.student_id,sms.cum_weighted_factor*sch.reporting_gp_scale AS score,
        sch.reporting_gp_scale AS scale,mp.marking_period_id,mp.title AS period_title,
        RANK() OVER(ORDER BY sms.cum_weighted_factor DESC) AS position
        FROM student_mp_stats sms JOIN school_marking_periods mp ON mp.marking_period_id=sms.marking_period_id
        JOIN schools sch ON sch.id=mp.school_id AND sch.syear=mp.syear
        WHERE mp.school_id=$school AND mp.syear=$year AND mp.marking_period_id=$period
        AND sms.cum_weighted_factor IS NOT NULL AND sms.gp_credits>0 AND sch.reporting_gp_scale>0
        AND EXISTS(SELECT 1 FROM student_enrollment e WHERE e.student_id=sms.student_id AND e.school_id=$school
          AND e.syear=$year AND e.start_date<='$today' AND (e.end_date IS NULL OR e.end_date>='$today'))$classScope
        ) SELECT r.*,s.first_name,s.middle_name,s.last_name,s.name_suffix FROM ranked r JOIN students s ON s.student_id=r.student_id
        $visibility ORDER BY score DESC,r.student_id) AS palmares ORDER BY score DESC,student_id");
}

function SchoolSafeSchoolBirthdayTopTen(int $school, int $year): array
{
    if ($school < 1 || $year < 1 || User('PROFILE') !== 'admin') return [];
    $today = new DateTimeImmutable('today', new DateTimeZone('Africa/Kinshasa'));
    $date = $today->format('Y-m-d');
    $rows = [];
    if (AllowUse('Students/Student.php')) {
        $rows = array_values(DBGet("SELECT s.student_id AS person_id,s.first_name,s.last_name,
            s.custom_200000004 AS birthdate,'Eleve' AS kind FROM students s
            WHERE s.custom_200000004 IS NOT NULL AND EXISTS(SELECT 1 FROM student_enrollment e
            WHERE e.student_id=s.student_id AND e.school_id=$school AND e.syear=$year
            AND e.start_date<='$date' AND (e.end_date IS NULL OR e.end_date>='$date'))"));
    }
    if (AllowUse('Users/User.php')) {
        $fields = DBGet("SELECT id FROM staff_fields WHERE type='date' AND title='Date de naissance SchoolSafe' ORDER BY id LIMIT 1");
        if ($fields) {
            $field = 'custom_' . (int)reset($fields)['ID'];
            $rows = array_merge($rows, array_values(DBGet("SELECT staff_id AS person_id,first_name,last_name,
                $field AS birthdate,'Personnel' AS kind FROM staff WHERE syear=$year
                AND profile NOT IN ('parent','student') AND $field IS NOT NULL
                AND (current_school_id=$school OR position(',$school,' IN schools)>0)")));
        }
    }
    $valid = [];
    foreach ($rows as $row) {
        $value = (string)$row['BIRTHDATE'];
        $birth = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $today->getTimezone());
        if (!$birth || $birth->format('Y-m-d') !== $value || $birth > $today) continue;
        // February 29 is celebrated on February 28 in non-leap years.
        for ($offset = 0; $offset <= 1; $offset++) {
            $targetYear = (int)$today->format('Y') + $offset;
            $month = (int)$birth->format('m');
            $first = $today->setDate($targetYear, $month, 1);
            $next = $first->setDate($targetYear, $month, min((int)$birth->format('d'), (int)$first->format('t')));
            if ($next >= $today) break;
        }
        $row['BIRTHDAY'] = ['date' => $next->format('Y-m-d')];
        $valid[] = $row;
    }
    $rows = $valid;
    usort($rows, static function ($a, $b) {
        return [$a['BIRTHDAY']['date'],$a['LAST_NAME'],$a['FIRST_NAME'],$a['KIND'],$a['PERSON_ID']]
            <=> [$b['BIRTHDAY']['date'],$b['LAST_NAME'],$b['FIRST_NAME'],$b['KIND'],$b['PERSON_ID']];
    });
    return array_slice($rows, 0, 10);
}

function SchoolSafeDashboardNativeWidget(bool $includeRemediation = true): string
{
    $school = (int)UserSchool(); $year = (int)UserSyear(); $user = (int)User('STAFF_ID');
    if ($school < 1 || $year < 1 || $user < 1) return '';
    $escape = static function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
    $html = '';
    require_once __DIR__.'/SchoolSafeSchoolLife.fnc.php';
    if ($includeRemediation) $html .= SchoolSafeRemediationVisual();
    if (Config('SCHOOLSAFE_BIRTHDAYS') === 'Y' && User('PROFILE') === 'parent') {
        $children = DBGet("SELECT DISTINCT u.student_id FROM students_join_users u JOIN student_enrollment e ON e.student_id=u.student_id
            WHERE u.staff_id=$user AND e.school_id=$school AND e.syear=$year");
        $ids = array_map(static function ($row) { return (int)$row['STUDENT_ID']; }, array_values($children));
        $rows = $ids ? SchoolSafeBirthdayRows($school, $year, $ids) : [];
        $html .= SchoolSafeBirthdayVisual('Anniversaires de mes enfants',$rows);
    }
    if (User('PROFILE') === 'admin' && (AllowUse('Students/Student.php') || AllowUse('Users/User.php'))) {
        $html .= SchoolSafeBirthdayVisual('Anniversaires de l\'ecole',SchoolSafeSchoolBirthdayTopTen($school,$year),true);
    }
    if (Config('SCHOOLSAFE_PALMARES') === 'Y') {
        if (!in_array(User('PROFILE'), ['admin', 'teacher', 'parent'], true) || !AllowUse('Grades/GPARankList.php')) return $html;
        if (User('PROFILE') === 'parent') return SchoolSafePalmaresParentBadges() . $html;
        $html = SchoolSafePalmaresVisual($school, $year) . $html;
    }
    return $html;
}
