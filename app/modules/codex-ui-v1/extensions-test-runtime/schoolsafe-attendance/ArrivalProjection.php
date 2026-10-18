<?php
declare(strict_types=1);

function SchoolSafeArrivalValidate(array $event, array $mapping): array
{
    $keys = ['sourceInstance', 'eventId', 'schoolId', 'studentId', 'occurredAt', 'eventType', 'decision', 'source'];
    if (array_diff(array_keys($event), $keys) || array_diff($keys, array_keys($event))) {
        throw new InvalidArgumentException('INVALID_ARRIVAL_EVENT');
    }
    $uuid = static fn($value): bool => is_string($value)
        && preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/Di', $value) === 1;
    if (!is_string($event['sourceInstance'])
        || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D', $event['sourceInstance'])
        || !$uuid($event['eventId']) || !$uuid($event['schoolId']) || !$uuid($event['studentId'])
        || $event['eventType'] !== 'entry' || $event['decision'] !== 'allowed' || $event['source'] !== 'scanner_entry') {
        throw new InvalidArgumentException('INVALID_ARRIVAL_EVENT');
    }
    // Mapping and timezone must come from trusted native configuration, never the event body.
    if (($mapping['sourceInstance'] ?? null) !== $event['sourceInstance']
        || !$uuid($mapping['targetSchoolId'] ?? null) || !$uuid($mapping['targetStudentId'] ?? null)
        || strtolower($mapping['targetSchoolId']) !== strtolower($event['schoolId'])
        || strtolower($mapping['targetStudentId']) !== strtolower($event['studentId'])) {
        throw new InvalidArgumentException('ARRIVAL_MAPPING_MISMATCH');
    }
    foreach (['nativeSchoolId', 'nativeStudentId', 'nativeYear'] as $key) {
        if (!is_string($mapping[$key] ?? null) || !preg_match('/^[1-9][0-9]{0,9}$/D', $mapping[$key])
            || (int) $mapping[$key] > 2147483647) {
            throw new InvalidArgumentException('INVALID_NATIVE_ARRIVAL_MAPPING');
        }
    }
    $timezone = $mapping['schoolTimezone'] ?? null;
    if (!is_string($timezone) || !in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
        throw new InvalidArgumentException('SCHOOL_TIMEZONE_REQUIRED');
    }
    $timestamp = $event['occurredAt'];
    if (!is_string($timestamp) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|\+00:00)$/D', $timestamp)) {
        throw new InvalidArgumentException('UTC_ARRIVAL_TIMESTAMP_REQUIRED');
    }
    $utc = new DateTimeZone('UTC');
    $at = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.uP',
        preg_replace('/(Z|\+00:00)$/', '+00:00', preg_replace('/(\d{2}:\d{2}:\d{2})(?=Z|\+00:00$)/', '$1.000000', $timestamp)), $utc);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$at || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
        throw new InvalidArgumentException('INVALID_ARRIVAL_TIMESTAMP');
    }
    return [
        'source_instance' => $event['sourceInstance'], 'source_event_id' => strtolower($event['eventId']),
        'target_school_id' => strtolower($event['schoolId']), 'target_student_id' => strtolower($event['studentId']),
        'school_id' => $mapping['nativeSchoolId'], 'student_id' => $mapping['nativeStudentId'], 'syear' => $mapping['nativeYear'],
        'occurred_at' => $at->setTimezone($utc)->format('Y-m-d\TH:i:s.uP'),
        'school_timezone' => $timezone, 'school_date' => $at->setTimezone(new DateTimeZone($timezone))->format('Y-m-d'),
        'status' => 'arrival_evidence',
    ];
}

/** $query executes parameterized PostgreSQL statements on one dedicated connection and returns associative rows.
 * The caller must authenticate provenance and resolve mapping first. Do not call inside an existing transaction.
 */
function SchoolSafeArrivalProject(array $event, array $mapping, callable $query): array
{
    $arrival = SchoolSafeArrivalValidate($event, $mapping);
    $query('BEGIN', []);
    try {
        $enrollments = $query('SELECT id FROM schoolsafe_attendance.lock_enrollment_at(
            CAST(:school_id AS integer),CAST(:student_id AS integer),CAST(:syear AS numeric),CAST(:school_date AS date))',
            array_intersect_key($arrival, array_flip(['school_id', 'student_id', 'syear', 'school_date'])));
        if (count($enrollments) !== 1 || !isset($enrollments[0]['id'])) {
            throw new RuntimeException(count($enrollments) > 1 ? 'AMBIGUOUS_NATIVE_ENROLLMENT' : 'NATIVE_ENROLLMENT_REQUIRED');
        }
        $arrival['enrollment_id'] = (string) $enrollments[0]['id'];
        $columns = implode(',', array_keys($arrival));
        $values = implode(',', array_map(static fn($key) => ':' . $key, array_keys($arrival)));
        $rows = $query('INSERT INTO schoolsafe_attendance.arrival_ledger (' . $columns . ') VALUES (' . $values . ')
            ON CONFLICT (source_instance,source_event_id) DO NOTHING RETURNING source_event_id', $arrival);
        $duplicate = !$rows;
        if ($duplicate) {
            $existing = $query('SELECT target_school_id::text,target_student_id::text,school_id::text,student_id::text,syear::text,enrollment_id::text,
                to_char(occurred_at AT TIME ZONE \'UTC\',\'YYYY-MM-DD"T"HH24:MI:SS.US\') || \'+00:00\' AS occurred_at,
                school_timezone,school_date::text,status FROM schoolsafe_attendance.arrival_ledger
                WHERE source_instance=:source_instance AND source_event_id=:source_event_id',
                array_intersect_key($arrival, array_flip(['source_instance', 'source_event_id'])));
            if (count($existing) !== 1) throw new RuntimeException('ARRIVAL_LEDGER_CONFLICT');
            foreach ($existing[0] as $key => $value) {
                if (!array_key_exists($key, $arrival) || (string) $value !== (string) $arrival[$key]) {
                    throw new RuntimeException('ARRIVAL_EVENT_CONFLICT');
                }
            }
        }
        $query('COMMIT', []);
        return ['result' => $duplicate ? 'duplicate' : 'inserted', 'arrival' => $arrival];
    } catch (Throwable $error) {
        try { $query('ROLLBACK', []); } catch (Throwable $rollbackError) { /* Preserve the original error. */ }
        throw $error;
    }
}
