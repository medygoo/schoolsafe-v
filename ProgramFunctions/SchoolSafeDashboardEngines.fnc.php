<?php

final class SchoolSafeDashboardEngines
{
    public static function upcomingBirthday($birthdate, DateTimeImmutable $today, array $openDates)
    {
        $birth = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $birthdate, $today->getTimezone());
        if (!$birth || $birth->format('Y-m-d') !== $birthdate || $birth > $today) {
            return null;
        }
        $today = $today->setTime(0, 0);
        for ($offset = 0; $offset <= 7; $offset++) {
            $day = $today->modify('+' . $offset . ' days');
            if ($day->format('m-d') === $birth->format('m-d')) {
                return [
                    'date' => $day->format('Y-m-d'),
                    'days_remaining' => $offset,
                    'school_open' => in_array($day->format('Y-m-d'), $openDates, true),
                ];
            }
        }
        return null;
    }

    // Caller must supply only authorized, recorded assessments for one school and year.
    public static function academicRanking(array $assessments)
    {
        $courses = [];
        $seen = [];
        foreach ($assessments as $assessment) {
            foreach (['student_id', 'course_id', 'assignment_id', 'points', 'maximum'] as $field) {
                if (!isset($assessment[$field]) || !is_numeric($assessment[$field])) {
                    continue 2;
                }
            }
            $student = (int) $assessment['student_id'];
            $course = (int) $assessment['course_id'];
            $assignment = (int) $assessment['assignment_id'];
            $points = (float) $assessment['points'];
            $maximum = (float) $assessment['maximum'];
            if ($student <= 0 || $course <= 0 || $assignment <= 0
                || !is_finite($points) || !is_finite($maximum)
                || $points < 0 || $maximum <= 0 || $points > $maximum) {
                continue;
            }
            $key = $student . ':' . $assignment;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $courses[$student][$course][] = 100 * $points / $maximum;
        }
        $ranking = [];
        foreach ($courses as $student => $subjects) {
            $means = [];
            $count = 0;
            foreach ($subjects as $scores) {
                $means[] = array_sum($scores) / count($scores);
                $count += count($scores);
            }
            $ranking[] = ['student_id' => $student, 'score' => round(array_sum($means) / count($means), 6),
                'assessment_count' => $count, 'course_count' => count($means)];
        }
        usort($ranking, static function ($a, $b) {
            return ($b['score'] <=> $a['score']) ?: ($a['student_id'] <=> $b['student_id']);
        });
        $previous = null;
        $rank = 0;
        foreach ($ranking as $index => &$row) {
            if ($previous !== $row['score']) {
                $rank = $index + 1;
            }
            $row['rank'] = $rank;
            $previous = $row['score'];
        }
        unset($row);
        return array_slice($ranking, 0, 10);
    }
}
