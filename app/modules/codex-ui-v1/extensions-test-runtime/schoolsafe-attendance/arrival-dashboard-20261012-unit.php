<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/ArrivalDashboard.php';
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$profile = 'admin';
$permit = true;
$RosarioModules = [];
$DatabaseType = 'postgres';
$nativeGrant = false;
function DBGetOne($sql) {
    global $nativeGrant;
    return $nativeGrant && strpos($sql, "MODNAME='SchoolSafe/ArrivalAttendance.php'") !== false;
}
function User($key) { global $profile; return $key === 'PROFILE' ? $profile : '7'; }
function AllowUse($key) { global $permit; return $permit && $key === 'Attendance/DailySummary.php'; }
function UserSchool() { return '2'; }
function UserSyear() { return '2026'; }
function DBDate() { return '2026-10-12'; }
function checkDashboard(bool $value): void { if (!$value) throw new RuntimeException('ASSERTION_FAILED'); }
function testDashboard(string $name, callable $test): void { $test(); echo "PASS $name\n"; }
function rejectDashboard(callable $test, string $message): void {
    try { $test(); } catch (Throwable $error) { checkDashboard($error->getMessage() === $message); return; }
    throw new RuntimeException('EXPECTED_REJECTION');
}
$admin = ['profile' => 'admin', 'staff' => '7', 'school' => '2', 'year' => '2026'];
$teacher = array_replace($admin, ['profile' => 'teacher']);

testDashboard('admin aggregates isolate school year date and valid enrollment', function () use ($admin) {
    $calls = [];
    $data = SchoolSafeArrivalDashboardRead($admin, '2026-10-12', function ($sql) use (&$calls) {
        $calls[] = $sql;
        checkDashboard(strpos($sql, 'SELECT ') === 0 && strpos($sql, 'arrival_ledger') !== false);
        foreach (['l.school_id=2', 'l.syear=2026', "l.school_date='2026-10-12'",
            "l.status='arrival_evidence'", 'e.id=l.enrollment_id', 'e.start_date<=l.school_date',
            'e.end_date>=l.school_date'] as $clause) checkDashboard(strpos($sql, $clause) !== false);
        checkDashboard(strpos($sql, 'first_name') === false);
        return count($calls) === 1 ? [['STUDENTS' => '2', 'EVENTS' => '3']]
            : [['COURSE_PERIOD_ID' => '9', 'TITLE' => 'Classe A', 'STUDENTS' => '2', 'EVENTS' => '3']];
    });
    checkDashboard($data['summary']['students'] === '2' && count($data['classes']) === 1);
    foreach (['cp.school_id=l.school_id', 'cp.syear=l.syear', 'ss.school_id=l.school_id',
        'ss.syear=l.syear', 'ss.start_date<=l.school_date', 'ss.end_date>=l.school_date',
        'EXISTS(SELECT 1', 'LIMIT 101'] as $clause) checkDashboard(strpos($calls[1], $clause) !== false);
});
testDashboard('teacher first assigned class is reauthorized and evidence is dated', function () use ($teacher) {
    $calls = [];
    $data = SchoolSafeArrivalDashboardRead($teacher, '2026-10-12', function ($sql) use (&$calls) {
        $calls[] = $sql;
        checkDashboard(strpos($sql, 'cp.school_id=2') !== false || strpos($sql, 'cp.school_id=l.school_id') !== false);
        checkDashboard(strpos($sql, 'cp.teacher_id=7 OR cp.secondary_teacher_id=7') !== false);
        if (count($calls) === 1) return [['COURSE_PERIOD_ID' => '9', 'TITLE' => 'Ma classe']];
        if (count($calls) === 2) return [['COURSE_PERIOD_ID' => '9']];
        checkDashboard(strpos($sql, 'cp.course_period_id=9') !== false);
        checkDashboard(strpos($sql, 'ss.school_id=l.school_id') !== false);
        checkDashboard(strpos($sql, 'ss.start_date<=l.school_date') !== false);
        return [['STUDENTS' => '1', 'EVENTS' => '2']];
    });
    checkDashboard(count($calls) === 3 && $data['period'] === 9 && $data['title'] === 'Ma classe');
    checkDashboard(!$data['classes']);
});
testDashboard('teacher detail excludes foreign-school schedule from totals and rows', function () use ($teacher) {
    foreach ([3 => 0, 2 => 1] as $scheduleSchool => $expected) {
        $evidenceQueries = 0;
        $data = SchoolSafeArrivalRead($teacher, ['date' => '2026-10-12', 'course_period_id' => '9'],
            function ($sql) use ($scheduleSchool, &$evidenceQueries) {
                if (strpos($sql, 'SELECT cp.course_period_id FROM course_periods cp') === 0) {
                    return [['course_period_id' => '9']];
                }
                ++$evidenceQueries;
                // Query contract plus a substituted planning fixture; not a PostgreSQL execution.
                checkDashboard(strpos($sql, 'WHERE ss.student_id=l.student_id AND ss.school_id=l.school_id AND ss.syear=l.syear') !== false);
                checkDashboard(strpos($sql, 'cp.school_id=l.school_id AND cp.syear=l.syear') !== false);
                checkDashboard(strpos($sql, 'cp.course_period_id=9') !== false);
                $matches = $scheduleSchool === 2;
                if (strpos($sql, 'SELECT count(DISTINCT') === 0) {
                    return [['students' => $matches ? '1' : '0', 'events' => $matches ? '1' : '0']];
                }
                return $matches ? [['student_id' => '11']] : [];
            });
        checkDashboard($evidenceQueries === 2);
        checkDashboard((int) $data['summary']['students'] === $expected);
        checkDashboard((int) $data['summary']['events'] === $expected);
        checkDashboard(count($data['rows']) === $expected);
    }
});
testDashboard('teacher without assignment cannot read evidence', function () use ($teacher) {
    $calls = 0;
    rejectDashboard(function () use ($teacher, &$calls) {
        SchoolSafeArrivalDashboardRead($teacher, '2026-10-12',
            function ($sql) use (&$calls) { ++$calls; return []; });
    }, 'ARRIVAL_ACCESS_DENIED');
    checkDashboard($calls === 1);
});
testDashboard('revoked assignment fails before aggregate', function () use ($teacher) {
    $calls = 0;
    rejectDashboard(function () use ($teacher, &$calls) {
        SchoolSafeArrivalDashboardRead($teacher, '2026-10-12',
            function ($sql) use (&$calls) { return ++$calls === 1 ? [['course_period_id' => 9, 'title' => 'A']] : []; });
    }, 'ARRIVAL_ACCESS_DENIED');
    checkDashboard($calls === 2);
});
testDashboard('invalid scopes and date fail before queries', function () use ($admin) {
    $query = function () { throw new RuntimeException('UNEXPECTED_QUERY'); };
    foreach (['staff', 'school', 'year'] as $key) {
        rejectDashboard(fn() => SchoolSafeArrivalDashboardRead(array_replace($admin, [$key => '2 OR 1=1']),
            '2026-10-12', $query), 'INVALID_ARRIVAL_SCOPE');
    }
    rejectDashboard(fn() => SchoolSafeArrivalDashboardRead($admin, '2026-02-30', $query), 'INVALID_ARRIVAL_DATE');
    rejectDashboard(fn() => SchoolSafeArrivalDashboardRead(array_replace($admin, ['profile' => 'parent']),
        '2026-10-12', $query), 'ARRIVAL_ACCESS_DENIED');
});
testDashboard('denied gate executes no query and exposes no widget', function () {
    global $permit, $profile;
    $permit = false;
    checkDashboard(SchoolSafeArrivalDashboardWidget(function () { throw new RuntimeException('UNEXPECTED_QUERY'); }) === '');
    $permit = true; $profile = 'parent';
    checkDashboard(SchoolSafeArrivalDashboardWidget(function () { throw new RuntimeException('UNEXPECTED_QUERY'); }) === '');
    $profile = 'admin';
});
testDashboard('read failure is unavailable rather than zero or raw SQL', function () {
    $html = SchoolSafeArrivalDashboardWidget(function () { throw new RuntimeException('SECRET SQL'); });
    checkDashboard(strpos($html, 'indisponible') !== false && strpos($html, 'SECRET') === false);
    checkDashboard(strpos($html, 'Evenements : 0') === false);
});
testDashboard('enabled module requires exact read permission and handles revocation', function () {
    global $RosarioModules, $nativeGrant;
    $RosarioModules = ['SchoolSafe' => true];
    checkDashboard(SchoolSafeArrivalDashboardWidget(function () {
        throw new RuntimeException('UNEXPECTED_QUERY');
    }) === '');
    $nativeGrant = true;
    $html = SchoolSafeArrivalDashboardWidget(fn($sql) => strpos($sql, 'SELECT cp.course_period_id,cp.title,') === 0
        ? [] : [['students' => '0', 'events' => '0']]);
    checkDashboard(strpos($html, 'Eleves avec preuve') !== false);
    $nativeGrant = false;
    checkDashboard(SchoolSafeArrivalDashboardWidget(function () {
        throw new RuntimeException('UNEXPECTED_QUERY');
    }) === '');
    $RosarioModules = [];
});
testDashboard('mysql never queries ledger', function () {
    global $DatabaseType;
    $DatabaseType = 'mysql';
    checkDashboard(strpos(SchoolSafeArrivalDashboardWidget(function () {
        throw new RuntimeException('UNEXPECTED_QUERY');
    }), 'indisponible') !== false);
    $DatabaseType = 'postgres';
});
testDashboard('class list is bounded and titles escaped', function () use ($admin) {
    $calls = 0;
    $data = SchoolSafeArrivalDashboardRead($admin, '2026-10-12', function () use (&$calls) {
        return ++$calls === 1 ? [['students' => '1', 'events' => '1']]
            : array_fill(0, 101, ['title' => '<script>alert(1)</script>', 'students' => '1', 'events' => '1']);
    });
    $html = SchoolSafeArrivalDashboardRender($data);
    checkDashboard(count($data['classes']) === 100 && $data['more']);
    checkDashboard(strpos($html, '<script>') === false && strpos($html, '&lt;script&gt;') !== false);
});
testDashboard('detail read contract remains paginated', function () use ($admin) {
    $calls = 0;
    $data = SchoolSafeArrivalRead($admin, ['date' => '2026-10-12', 'page' => '2'],
        function ($sql) use (&$calls) {
            if (++$calls === 1) return [['STUDENTS' => '101', 'EVENTS' => '102']];
            checkDashboard(strpos($sql, 'LIMIT 100 OFFSET 100') !== false);
            return [];
        });
    checkDashboard($data['page'] === 2 && $data['summary']['students'] === '101');
});
testDashboard('portal mounts read-only adapter after shortcuts', function () {
    $source = file_get_contents(__DIR__ . '/../modules/misc/Portal.php');
    checkDashboard(strpos($source, 'SchoolSafeArrivalDashboardWidget') > strpos($source, 'SchoolSafeDashboardShortcuts();'));
    checkDashboard(strpos($source, '$rows = DBGet($sql);') !== false);
});
echo "13 dashboard regression tests passed.\n";
