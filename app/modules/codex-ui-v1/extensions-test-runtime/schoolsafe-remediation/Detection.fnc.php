<?php
/** Caller supplies persisted evaluations for one student and course, across marking periods. */
function SchoolSafeRemediationDetection(array $evaluations): array
{
    $seen = []; $failed = []; $count = 0;
    foreach ($evaluations as $evaluation) {
        $id = $evaluation['assignment_id'] ?? null;
        $points = $evaluation['points'] ?? null;
        $maximum = $evaluation['maximum'] ?? null;
        $type = $evaluation['type'] ?? null;
        if (!is_int($id) || $id <= 0 || isset($seen[$id])) {
            continue;
        }
        // Missing grades, exemptions and extra-credit-only assignments are not evaluations.
        if (!is_numeric($points) || !is_numeric($maximum)
            || !is_finite((float) $points) || !is_finite((float) $maximum)
            || $points < 0 || $maximum <= 0 || !empty($evaluation['excluded'])
            || !in_array($type, ['homework', 'quiz', 'exam'], true)) {
            continue;
        }
        $seen[$id] = true; $count++;
        if ((float) $points < (float) $maximum * 0.5) {
            $failed[$id] = $type;
        }
        if (count($failed) >= 3 && count(array_unique($failed)) >= 2) {
            // Include a different type even when the first three failures share one type.
            $ids = array_keys($failed);
            $first = $ids[0];
            foreach ($failed as $different => $failedType) {
                if ($failedType !== $failed[$first]) break;
            }
            $third = array_values(array_diff($ids, [$first, $different]))[0];
            return ['eligible' => true, 'evidence' => [$first, $different, $third], 'evaluations' => $count];
        }
    }
    return ['eligible' => false, 'evidence' => [], 'evaluations' => $count];
}
