<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/ArrivalProjection.php';

$event = ['sourceInstance' => 'codex-addons-local', 'eventId' => '11111111-1111-4111-8111-111111111111',
    'schoolId' => '22222222-2222-4222-8222-222222222222', 'studentId' => '33333333-3333-4333-8333-333333333333',
    'occurredAt' => '2026-10-12T23:30:00Z', 'eventType' => 'entry', 'decision' => 'allowed', 'source' => 'scanner_entry'];
$mapping = ['sourceInstance' => 'codex-addons-local', 'targetSchoolId' => $event['schoolId'], 'targetStudentId' => $event['studentId'],
    'nativeSchoolId' => '1', 'nativeStudentId' => '2', 'nativeYear' => '2026', 'schoolTimezone' => 'Africa/Kinshasa'];
$passed = 0;
function check(bool $value): void { if (!$value) throw new RuntimeException('ASSERTION_FAILED'); }
function rejects(callable $fn, string $code): void {
    try { $fn(); } catch (Throwable $error) { check($error->getMessage() === $code); return; }
    throw new RuntimeException('EXPECTED_REJECTION_' . $code);
}
function runTest(string $name, callable $fn): void { global $passed; $fn(); ++$passed; echo "PASS $name\n"; }
runTest('UTC instant maps to next school day without course presence', function () use ($event, $mapping) {
    $r = SchoolSafeArrivalValidate($event, $mapping);
    check($r['school_date'] === '2026-10-13' && $r['status'] === 'arrival_evidence');
    check($r['occurred_at'] === '2026-10-12T23:30:00.000000+00:00');
});
runTest('fractional UTC timestamps and school DST are deterministic', function () use ($event, $mapping) {
    $r = SchoolSafeArrivalValidate(array_replace($event, ['occurredAt' => '2026-03-29T01:30:00.123Z']),
        array_replace($mapping, ['schoolTimezone' => 'Europe/Paris']));
    check($r['school_date'] === '2026-03-29' && $r['occurred_at'] === '2026-03-29T01:30:00.123000+00:00');
});
foreach ([['decision', 'denied'], ['eventType', 'pickup_confirmed'], ['source', 'other'], ['eventId', 'not-uuid'], ['schoolTimezone', 'UTC']] as [$key, $value]) {
    runTest('reject event field ' . $key, function () use ($event, $mapping, $key, $value) {
        rejects(fn() => SchoolSafeArrivalValidate(array_replace($event, [$key => $value]), $mapping), 'INVALID_ARRIVAL_EVENT');
    });
}
runTest('mapping mismatch and timezone cannot default', function () use ($event, $mapping) {
    rejects(fn() => SchoolSafeArrivalValidate($event, array_replace($mapping, ['targetStudentId' => $event['schoolId']])), 'ARRIVAL_MAPPING_MISMATCH');
    rejects(fn() => SchoolSafeArrivalValidate($event, array_replace($mapping, ['schoolTimezone' => ''])), 'SCHOOL_TIMEZONE_REQUIRED');
});
runTest('invalid date and non UTC timestamp rejected', function () use ($event, $mapping) {
    rejects(fn() => SchoolSafeArrivalValidate(array_replace($event, ['occurredAt' => '2026-02-30T12:00:00Z']), $mapping), 'INVALID_ARRIVAL_TIMESTAMP');
    rejects(fn() => SchoolSafeArrivalValidate(array_replace($event, ['occurredAt' => '2026-10-12T12:00:00+01:00']), $mapping), 'UTC_ARRIVAL_TIMESTAMP_REQUIRED');
});
function dbMock(array $enrollments, bool $duplicate = false, bool $conflict = false, ?string $existingEnrollment = null): array {
    $state = new stdClass(); $state->calls = []; $state->arrival = [];
    $query = function (string $sql, array $params) use ($enrollments, $duplicate, $conflict, $existingEnrollment, $state): array {
        $state->calls[] = $sql;
        check(!preg_match('/\b(attendance_day|attendance_period)\b/i', $sql));
        if (str_starts_with($sql, 'SELECT id FROM schoolsafe_attendance.lock_enrollment_at')) {
            check($params === ['school_id' => '1', 'student_id' => '2', 'syear' => '2026', 'school_date' => '2026-10-13']);
            check(str_contains($sql, 'CAST(:school_date AS date)'));
            return $enrollments;
        }
        if (str_starts_with($sql, 'INSERT')) {
            check(str_contains($sql, 'ON CONFLICT (source_instance,source_event_id) DO NOTHING'));
            $state->arrival = $params; return $duplicate ? [] : [['source_event_id' => $params['source_event_id']]];
        }
        if (str_starts_with($sql, 'SELECT target_school_id')) {
            check(str_contains($sql, 'enrollment_id::text'));
            $r = array_intersect_key($state->arrival, array_flip(['target_school_id','target_student_id','school_id','student_id','syear','enrollment_id','occurred_at','school_timezone','school_date','status']));
            if ($existingEnrollment !== null) $r['enrollment_id'] = $existingEnrollment;
            if ($conflict) $r['student_id'] = '3';
            return [$r];
        }
        return [];
    };
    return [$query, $state];
}
runTest('enrolled arrival insert commits with no attendance mutation', function () use ($event, $mapping) {
    [$query, $state] = dbMock([['id' => '9']]);
    $r = SchoolSafeArrivalProject($event, $mapping, $query);
    check($r['result'] === 'inserted' && end($state->calls) === 'COMMIT');
});
runTest('duplicate insert is atomic and commits without replacement', function () use ($event, $mapping) {
    [$query, $state] = dbMock([['id' => '9']], true);
    check(SchoolSafeArrivalProject($event, $mapping, $query)['result'] === 'duplicate');
    check(end($state->calls) === 'COMMIT');
});
runTest('missing or ambiguous enrollment rolls back before insert', function () use ($event, $mapping) {
    foreach ([[], [['id' => '9'], ['id' => '10']]] as $rows) {
        [$query, $state] = dbMock($rows);
        rejects(fn() => SchoolSafeArrivalProject($event, $mapping, $query), $rows ? 'AMBIGUOUS_NATIVE_ENROLLMENT' : 'NATIVE_ENROLLMENT_REQUIRED');
        check(end($state->calls) === 'ROLLBACK' && count($state->calls) === 3);
    }
});
runTest('reused event with different native identity rolls back', function () use ($event, $mapping) {
    [$query, $state] = dbMock([['id' => '9']], true, true);
    rejects(fn() => SchoolSafeArrivalProject($event, $mapping, $query), 'ARRIVAL_EVENT_CONFLICT');
    check(end($state->calls) === 'ROLLBACK');
});
runTest('database failure rolls back and propagates original failure', function () use ($event, $mapping) {
    $calls = [];
    $query = function ($sql, $params) use (&$calls): array {
        $calls[] = $sql;
        if (str_starts_with($sql, 'SELECT id FROM schoolsafe_attendance.lock_enrollment_at')) return [['id' => '9']];
        if (str_starts_with($sql, 'INSERT')) throw new RuntimeException('TEST_DATABASE_FAILURE');
        return [];
    };
    rejects(fn() => SchoolSafeArrivalProject($event, $mapping, $query), 'TEST_DATABASE_FAILURE');
    check(end($calls) === 'ROLLBACK');
});
runTest('invalid input never opens a transaction', function () use ($event, $mapping) {
    $calls = 0;
    $query = function ($sql, $params) use (&$calls): array { ++$calls; return []; };
    rejects(fn() => SchoolSafeArrivalProject(array_replace($event, ['decision' => 'denied']), $mapping, $query), 'INVALID_ARRIVAL_EVENT');
    check($calls === 0);
});
runTest('duplicate with replaced enrollment rejects and rolls back', function () use ($event, $mapping) {
    [$query, $state] = dbMock([['id' => '10']], true, false, '9');
    rejects(fn() => SchoolSafeArrivalProject($event, $mapping, $query), 'ARRIVAL_EVENT_CONFLICT');
    check(end($state->calls) === 'ROLLBACK' && !in_array('COMMIT', $state->calls, true));
});
echo "$passed tests passed; mocked SQL only\n";
