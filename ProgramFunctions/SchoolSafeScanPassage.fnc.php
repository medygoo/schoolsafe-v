<?php
// Scope and arrival cycles on the EXISTING native records. No attendance writes.
require_once __DIR__ . '/SchoolSafePickupManage.fnc.php';

function SchoolSafeScanPassageRequireScope(array $db): void
{
    $rows = $db['get']("SELECT COUNT(*) AS N FROM information_schema.columns WHERE table_schema='public' AND ("
        . "(table_name='entry_exit_student_records' AND column_name IN ('schoolsafe_scan_school_id','schoolsafe_scan_syear')) OR "
        . "(table_name='entry_exit_checkpoints' AND column_name IN ('schoolsafe_scan_school_id','schoolsafe_scan_syear','schoolsafe_scan_enabled')) OR "
        . "(table_name='schoolsafe_pickup_proofs' AND column_name IN ('syear','entry_record_id')))");
    if (!is_array($rows) || (int) ($rows[1]['N'] ?? 0) !== 7) throw new RuntimeException('SCAN_SCOPE_UNAVAILABLE');
}

function SchoolSafeScanCheckpointInScope(array $checkpoint, $school, $year): bool
{
    return (int) ($checkpoint['SCHOOLSAFE_SCAN_SCHOOL_ID'] ?? 0) === (int) $school
        && (int) ($checkpoint['SCHOOLSAFE_SCAN_SYEAR'] ?? 0) === (int) $year
        && in_array($checkpoint['SCHOOLSAFE_SCAN_ENABLED'] ?? false, [true,'t','1',1], true);
}

// The caller supplies modes resolved from the existing native rights, never
// from a client-selected school or a client-provided permission flag.
function SchoolSafeScanLocations($school, $year, array $modes, array $db): array
{
    $school = (int) $school;
    $year = (int) $year;
    if ($school < 1 || $year < 1) throw new RuntimeException('INVALID_CONTEXT');
    if (array_diff($modes, ['entry','pickup'])) throw new RuntimeException('INVALID_SCAN_MODE');
    if (!$modes) return [];
    SchoolSafeScanPassageRequireScope($db);
    $directions = ["TYPE IS NULL", "TYPE=''"];
    if (in_array('entry', $modes, true)) $directions[] = "TYPE='in'";
    if (in_array('pickup', $modes, true)) $directions[] = "TYPE='out'";
    $rows = $db['get']('SELECT ID, TITLE FROM entry_exit_checkpoints WHERE SCHOOLSAFE_SCAN_SCHOOL_ID=' . $school
        . ' AND SCHOOLSAFE_SCAN_SYEAR=' . $year . ' AND SCHOOLSAFE_SCAN_ENABLED AND (' . implode(' OR ', $directions) . ')'
        . ' ORDER BY SORT_ORDER NULLS LAST, TITLE, ID');
    if (!is_array($rows)) throw new RuntimeException('SCAN_LOCATIONS_UNAVAILABLE');
    $locations = [];
    foreach ($rows as $row) {
        if (!isset($row['ID'], $row['TITLE'])) throw new RuntimeException('SCAN_LOCATIONS_UNAVAILABLE');
        $locations[] = ['id'=>(string)$row['ID'], 'label'=>$row['TITLE']];
    }
    return $locations;
}

// Call while holding the per-child transaction lock. Unscoped historical events
// and refused attempts cannot establish a current arrival in another tenant.
// A newer unassigned/foreign event invalidates the old episode; never infer
// that it was a harmless event or grant a second handover from stale evidence.
function SchoolSafeScanLatestPassage($student, $school, $year, array $db)
{
    $rows = $db['get']('SELECT ID, TYPE FROM entry_exit_student_records WHERE STUDENT_ID=' . (int) $student
        . ' AND SCHOOLSAFE_SCAN_SCHOOL_ID=' . (int) $school . ' AND SCHOOLSAFE_SCAN_SYEAR=' . (int) $year
        . " AND UNAUTHORIZED IS NULL AND TYPE IN ('in','out')"
        . " AND NOT EXISTS(SELECT 1 FROM entry_exit_student_records newer WHERE newer.STUDENT_ID=entry_exit_student_records.STUDENT_ID"
        . " AND newer.UNAUTHORIZED IS NULL AND newer.TYPE IN ('in','out') AND newer.ID>entry_exit_student_records.ID)"
        . ' ORDER BY ID DESC LIMIT 1');
    return is_array($rows) && isset($rows[1]) ? $rows[1] : null;
}
