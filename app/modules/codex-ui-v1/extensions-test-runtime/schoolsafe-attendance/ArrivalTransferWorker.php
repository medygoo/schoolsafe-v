<?php
declare(strict_types=1);
require_once __DIR__ . '/ArrivalProjection.php';

/** One bounded tick. Adapter callbacks are trusted dependencies, never browser inputs. */
function SchoolSafeArrivalTransferTick(array $state, string $activation, int $limit, callable $fetch, callable $project): array
{
    if ($limit < 1 || $limit > 500) throw new InvalidArgumentException('INVALID_BATCH_LIMIT');
    if (($state['activation'] ?? $activation) !== $activation) throw new RuntimeException('ACTIVATION_CHANGED');
    $state += ['activation' => $activation, 'cursor' => null, 'pending' => [],
        'reconciliationCursor' => null, 'reconciliationTicks' => 0];
    if (!is_array($state['pending'])) throw new RuntimeException('INVALID_WORKER_STATE');
    if (!is_int($state['reconciliationTicks']) || $state['reconciliationTicks'] < 0
        || $state['reconciliationTicks'] >= 5) throw new RuntimeException('INVALID_WORKER_STATE');
    $counts = ['projected' => 0, 'duplicates' => 0, 'retrying' => 0];
    $attempt = function (array $event) use (&$state, &$counts, $project): void {
        $id = $event['eventId'];
        try {
            $result = $project($event);
            if (!in_array($result['result'] ?? null, ['inserted', 'duplicate'], true)) throw new RuntimeException('PROJECTION_NOT_CONFIRMED');
            unset($state['pending'][$id]);
            ++$counts[$result['result'] === 'inserted' ? 'projected' : 'duplicates'];
        } catch (Throwable $error) {
            // Rotate failed items to the back; retain every event until native commit succeeds.
            unset($state['pending'][$id]);
            $state['pending'][$id] = $event;
            ++$counts['retrying'];
        }
    };
    foreach (array_slice($state['pending'], 0, $limit, true) as $event) $attempt($event);
    $events = $fetch($activation, $state['cursor'], $limit);
    if (!is_array($events) || count($events) > $limit) throw new RuntimeException('INVALID_EVENT_BATCH');
    foreach ($events as $event) {
        $attempt($event);
        $state['cursor'] = ['occurredAt' => $event['occurredAt'], 'eventId' => $event['eventId']];
    }
    // Keep forward progress independent of periodic, bounded historical sweeps.
    if (++$state['reconciliationTicks'] === 5) {
        $history = $fetch($activation, $state['reconciliationCursor'], $limit);
        if (!is_array($history) || count($history) > $limit) throw new RuntimeException('INVALID_EVENT_BATCH');
        foreach ($history as $event) {
            $attempt($event);
            $state['reconciliationCursor'] = ['occurredAt' => $event['occurredAt'], 'eventId' => $event['eventId']];
        }
        if (count($history) < $limit) $state['reconciliationCursor'] = null;
        $state['reconciliationTicks'] = 0;
    }
    return ['state' => $state, 'counts' => $counts];
}

function SchoolSafeArrivalTransferMapping(array $event, callable $mappingQuery, callable $nativeQuery, array $timezones): array
{
    $rows = $mappingQuery('SELECT native_school_id::text,native_student_id::text
        FROM schoolsafe_arrival_mapping.identities
        WHERE source_instance=:instance AND target_school_id=:school AND target_student_id=:student',
        ['instance' => $event['sourceInstance'], 'school' => $event['schoolId'], 'student' => $event['studentId']]);
    if (count($rows) !== 1) throw new RuntimeException('ARRIVAL_MAPPING_REQUIRED');
    $school = $rows[0]['native_school_id']; $student = $rows[0]['native_student_id'];
    $timezone = $timezones[$school] ?? null;
    if (!is_string($timezone) || !in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) throw new RuntimeException('SCHOOL_TIMEZONE_REQUIRED');
    $day = (new DateTimeImmutable($event['occurredAt']))->setTimezone(new DateTimeZone($timezone))->format('Y-m-d');
    $years = $nativeQuery('SELECT syear::text FROM schoolsafe_attendance.transfer_enrollments
        CROSS JOIN (SELECT CAST(:day AS date) AS event_date) d
        WHERE school_id=:school AND student_id=:student AND start_date<=d.event_date
        AND (end_date IS NULL OR end_date>=d.event_date)', ['day' => $day, 'school' => $school, 'student' => $student]);
    if (count($years) !== 1) throw new RuntimeException('UNIQUE_EVENT_ENROLLMENT_REQUIRED');
    return ['sourceInstance' => $event['sourceInstance'], 'targetSchoolId' => $event['schoolId'], 'targetStudentId' => $event['studentId'],
        'nativeSchoolId' => $school, 'nativeStudentId' => $student, 'nativeYear' => $years[0]['syear'], 'schoolTimezone' => $timezone];
}
