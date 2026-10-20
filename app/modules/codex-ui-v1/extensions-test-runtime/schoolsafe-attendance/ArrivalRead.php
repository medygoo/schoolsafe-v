<?php
declare(strict_types=1);

function SchoolSafeArrivalReadId($value): int
{
    if ((!is_string($value) && !is_int($value)) || !preg_match('/^[1-9][0-9]{0,9}$/D', (string) $value)
        || (float) $value > 2147483647) throw new InvalidArgumentException('INVALID_ARRIVAL_SCOPE');
    return (int) $value;
}

function SchoolSafeArrivalReadContext(array $context): array
{
    if (!in_array($context['profile'] ?? null, ['admin', 'teacher'], true)) throw new RuntimeException('ARRIVAL_ACCESS_DENIED');
    return ['profile' => $context['profile'], 'staff' => SchoolSafeArrivalReadId($context['staff'] ?? null),
        'school' => SchoolSafeArrivalReadId($context['school'] ?? null), 'year' => SchoolSafeArrivalReadId($context['year'] ?? null)];
}

function SchoolSafeArrivalReadDate($value): string
{
    if (!is_string($value) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value)) throw new InvalidArgumentException('INVALID_ARRIVAL_DATE');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException('INVALID_ARRIVAL_DATE');
    return $value;
}

function SchoolSafeArrivalReadRows($rows): array
{
    if (!is_array($rows)) throw new RuntimeException('ARRIVAL_READ_UNAVAILABLE');
    $result = [];
    foreach ($rows as $row) {
        if (!is_array($row)) throw new RuntimeException('ARRIVAL_READ_UNAVAILABLE');
        $result[] = array_change_key_case($row, CASE_LOWER);
    }
    return $result;
}

function SchoolSafeArrivalAssignedClasses(array $context, callable $query): array
{
    $scope = SchoolSafeArrivalReadContext($context);
    if ($scope['profile'] !== 'teacher') return [];
    return SchoolSafeArrivalReadRows($query('SELECT cp.course_period_id,cp.title FROM course_periods cp'
        . ' WHERE cp.school_id=' . $scope['school'] . ' AND cp.syear=' . $scope['year']
        . ' AND (cp.teacher_id=' . $scope['staff'] . ' OR cp.secondary_teacher_id=' . $scope['staff'] . ')'
        . ' ORDER BY cp.title,cp.course_period_id'));
}

/** Shared evidence scope for the detail view and portal aggregates. */
function SchoolSafeArrivalReadScope(array $context, array $filters, callable $query): array
{
    $scope = SchoolSafeArrivalReadContext($context);
    if (array_diff(array_keys($filters), ['date', 'course_period_id', 'page'])) throw new InvalidArgumentException('INVALID_ARRIVAL_FILTER');
    $date = SchoolSafeArrivalReadDate($filters['date'] ?? null);
    $page = SchoolSafeArrivalReadId($filters['page'] ?? '1');
    if ($page > 10000) throw new InvalidArgumentException('INVALID_ARRIVAL_FILTER');
    // Lot ENTRY : le registre natif Rosario (entry_exit_student_records) s'ajoute
    // au registre bridge, mêmes colonnes, même périmètre (inscription + classes).
    $ledger = 'SELECT l.student_id,l.school_id,l.syear,l.school_date,l.occurred_at,'
        . 'l.school_timezone,l.source_event_id,l.enrollment_id,l.status'
        . ' FROM schoolsafe_attendance.arrival_ledger l';
    $branches = [];
    if (SchoolSafeArrivalTableReady($query, 'schoolsafe_attendance.arrival_ledger')) $branches[] = $ledger;
        $branches[] = 'SELECT r.student_id,e.school_id,e.syear,(r.recorded_at::date) AS school_date,'
            . "r.recorded_at AS occurred_at,current_setting('TIMEZONE') AS school_timezone,"
            . "md5('rosario-entry-'||r.id)::uuid AS source_event_id,e.id AS enrollment_id,"
            . "'arrival_evidence' AS status"
            . ' FROM entry_exit_student_records r JOIN student_enrollment e ON e.student_id=r.student_id'
            . " WHERE r.type='in' AND r.unauthorized IS NULL"
            . ' AND e.start_date<=(r.recorded_at::date) AND (e.end_date IS NULL OR e.end_date>=(r.recorded_at::date))';
    // Sans aucune source, le SQL historique est conservé : même 503 qu'avant, jamais de faux vide.
    $native = $branches ? implode(' UNION ALL ', $branches) : $ledger;
    $from = ' FROM (' . $native . ') l JOIN students s ON s.student_id=l.student_id'
        . ' JOIN student_enrollment e ON e.id=l.enrollment_id AND e.student_id=l.student_id'
        . ' AND e.school_id=l.school_id AND e.syear=l.syear';
    $where = ' WHERE l.school_id=' . $scope['school'] . ' AND l.syear=' . $scope['year']
        . " AND l.school_date='" . $date . "' AND l.status='arrival_evidence'"
        . ' AND e.start_date<=l.school_date AND (e.end_date IS NULL OR e.end_date>=l.school_date)';
    if ($scope['profile'] === 'teacher') {
        $period = SchoolSafeArrivalReadId($filters['course_period_id'] ?? null);
        $assigned = SchoolSafeArrivalReadRows($query('SELECT cp.course_period_id FROM course_periods cp'
            . ' WHERE cp.course_period_id=' . $period . ' AND cp.school_id=' . $scope['school']
            . ' AND cp.syear=' . $scope['year'] . ' AND (cp.teacher_id=' . $scope['staff']
            . ' OR cp.secondary_teacher_id=' . $scope['staff'] . ')'));
        if (count($assigned) !== 1 || (string) ($assigned[0]['course_period_id'] ?? '') !== (string) $period) throw new RuntimeException('ARRIVAL_ACCESS_DENIED');
        $where .= ' AND EXISTS(SELECT 1 FROM schedule ss JOIN course_periods cp ON cp.course_period_id=ss.course_period_id'
            . ' WHERE ss.student_id=l.student_id AND ss.school_id=l.school_id AND ss.syear=l.syear AND cp.school_id=l.school_id AND cp.syear=l.syear'
            . ' AND cp.course_period_id=' . $period . ' AND (cp.teacher_id=' . $scope['staff']
            . ' OR cp.secondary_teacher_id=' . $scope['staff'] . ')'
            . ' AND ss.start_date<=l.school_date AND (ss.end_date IS NULL OR ss.end_date>=l.school_date))';
    } elseif (isset($filters['course_period_id'])) throw new InvalidArgumentException('INVALID_ARRIVAL_FILTER');
    return ['date' => $date, 'page' => $page, 'from' => $from, 'where' => $where, 'scope' => $scope];
}

function SchoolSafeArrivalTableReady(callable $query, $name): bool
{
    try {
        $rows = SchoolSafeArrivalReadRows($query("SELECT to_regclass('" . $name . "') AS t"));
    } catch (Throwable $error) { return false; }
    $found = (string) ($rows[0]['t'] ?? '');
    return $found !== '' && substr($found, -strlen($name)) === $name;
}

function SchoolSafeArrivalNativeRecordsReady(callable $query): bool
{
    return SchoolSafeArrivalTableReady($query, 'public.entry_exit_student_records');
}

/** Only SELECT statements. No roster-minus-arrivals inference or attendance mutation. */
function SchoolSafeArrivalRead(array $context, array $filters, callable $query): array
{
    $read = SchoolSafeArrivalReadScope($context, $filters, $query);
    $from = $read['from'];
    $where = $read['where'];
    $date = $read['date'];
    $page = $read['page'];
    $offset = ($page - 1) * 100;
    $summary = SchoolSafeArrivalReadRows($query('SELECT count(DISTINCT l.student_id) AS students,count(*) AS events' . $from . $where));
    if (count($summary) !== 1) throw new RuntimeException('ARRIVAL_READ_UNAVAILABLE');
    $rows = SchoolSafeArrivalReadRows($query('SELECT l.student_id,s.first_name,s.last_name,l.school_date,'
        . " (array_agg(to_char(l.occurred_at AT TIME ZONE l.school_timezone,'HH24:MI:SS') ORDER BY l.occurred_at,l.source_event_id))[1] AS first_arrival,"
        . " (array_agg(to_char(l.occurred_at AT TIME ZONE l.school_timezone,'HH24:MI:SS') ORDER BY l.occurred_at DESC,l.source_event_id DESC))[1] AS last_arrival,count(*) AS events"
        . $from . $where . ' GROUP BY l.student_id,s.first_name,s.last_name,l.school_date'
        . ' ORDER BY s.last_name,s.first_name,l.student_id LIMIT 100 OFFSET ' . $offset));
    return ['date' => $date, 'page' => $page, 'summary' => $summary[0], 'rows' => $rows];
}

function SchoolSafeArrivalViewPermission(): bool
{
    global $RosarioModules;
    if (!function_exists('User') || !function_exists('AllowUse')
        || !in_array(User('PROFILE'), ['admin', 'teacher'], true)) return false;
    // Evidence-read permission is separate from arrival scanning permission.
    if (!empty($RosarioModules['SchoolSafe'])) {
        $helper = dirname(__DIR__) . '/modules/SchoolSafe/Permissions.php';
        if (!is_file($helper)) return false;
        require_once $helper;
        return SchoolSafeNativePermission('SchoolSafe/ArrivalAttendance.php');
    }
    return AllowUse('Attendance/DailySummary.php');
}
