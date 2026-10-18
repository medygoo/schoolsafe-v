<?php
declare(strict_types=1);
require_once __DIR__ . '/ArrivalRead.php';

function SchoolSafeArrivalDashboardRead(array $context, string $date, callable $query): array
{
    $scope = SchoolSafeArrivalReadContext($context);
    $date = SchoolSafeArrivalReadDate($date);
    $filters = ['date' => $date];
    $period = null;
    $title = null;
    if ($scope['profile'] === 'teacher') {
        $assigned = SchoolSafeArrivalAssignedClasses($context, $query);
        if (!$assigned) throw new RuntimeException('ARRIVAL_ACCESS_DENIED');
        $period = SchoolSafeArrivalReadId($assigned[0]['course_period_id'] ?? null);
        $title = $assigned[0]['title'] ?? '';
        $filters['course_period_id'] = $period;
    }
    $read = SchoolSafeArrivalReadScope($context, $filters, $query);
    $summary = SchoolSafeArrivalReadRows($query('SELECT count(DISTINCT l.student_id) AS students,count(*) AS events'
        . $read['from'] . $read['where']));
    if (count($summary) !== 1) throw new RuntimeException('ARRIVAL_READ_UNAVAILABLE');
    $classes = [];
    if ($scope['profile'] === 'admin') {
        // EXISTS avoids multiplying evidence when dated schedule entries overlap.
        $classes = SchoolSafeArrivalReadRows($query('SELECT cp.course_period_id,cp.title,'
            . 'count(DISTINCT l.student_id) AS students,count(*) AS events'
            . $read['from'] . ' JOIN course_periods cp ON cp.school_id=l.school_id AND cp.syear=l.syear'
            . $read['where'] . ' AND EXISTS(SELECT 1 FROM schedule ss WHERE ss.student_id=l.student_id'
            . ' AND ss.school_id=l.school_id AND ss.syear=l.syear AND ss.course_period_id=cp.course_period_id'
            . ' AND ss.start_date<=l.school_date AND (ss.end_date IS NULL OR ss.end_date>=l.school_date))'
            . ' GROUP BY cp.course_period_id,cp.title ORDER BY cp.title,cp.course_period_id LIMIT 101'));
    }
    $more = count($classes) > 100;
    return ['date' => $read['date'], 'summary' => $summary[0], 'classes' => array_slice($classes, 0, 100),
        'more' => $more, 'period' => $period, 'title' => $title];
}

function SchoolSafeArrivalDashboardRender(array $data): string
{
    $escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    $filters = ['date' => $data['date']];
    if ($data['period'] !== null) $filters['course_period_id'] = $data['period'];
    $html = '<section aria-label="Arrivees enregistrees"><h2>Arrivees enregistrees</h2><p>'
        . $escape($data['date']);
    if ($data['title'] !== null) $html .= ' · Classe : ' . $escape($data['title']);
    $html .= '</p><p>Eleves avec preuve d\'arrivee : ' . $escape($data['summary']['students'])
        . ' · Evenements : ' . $escape($data['summary']['events']) . '</p>';
    if ($data['classes']) {
        $html .= '<div class="table-scroll"><table><thead><tr><th>Classe / cours</th>'
            . '<th>Eleves avec arrivee</th><th>Evenements</th></tr></thead><tbody>';
        foreach ($data['classes'] as $class) $html .= '<tr><td>' . $escape($class['title'])
            . '</td><td>' . $escape($class['students']) . '</td><td>' . $escape($class['events']) . '</td></tr>';
        $html .= '</tbody></table></div>';
    } elseif ($data['title'] === null) {
        $html .= '<p>Aucune arrivee rattachee a une classe / un cours pour cette date.</p>';
    }
    if ($data['more']) $html .= '<p>Resume limite aux 100 premieres classes / cours.</p>';
    return $html . '<p>Une arrivee ne confirme ni la presence actuelle ni la presence en cours.'
        . ' Un eleve peut figurer dans plusieurs classes / cours.</p><a href="arrival-attendance.php?'
        . $escape(http_build_query($filters)) . '">Consulter les arrivees</a></section>';
}

/** Resolve the existing gate before reading any arrival evidence. */
function SchoolSafeArrivalDashboardWidget(callable $query): string
{
    try {
        if (!SchoolSafeArrivalViewPermission()) return '';
        global $DatabaseType;
        if ($DatabaseType === 'mysql') throw new RuntimeException('ARRIVAL_READ_UNAVAILABLE');
        $context = ['profile' => User('PROFILE'), 'staff' => User('STAFF_ID'),
            'school' => UserSchool(), 'year' => UserSyear()];
        return SchoolSafeArrivalDashboardRender(SchoolSafeArrivalDashboardRead($context, DBDate(), $query));
    } catch (Throwable $error) {
        return '<section aria-label="Arrivees enregistrees"><h2>Arrivees enregistrees</h2>'
            . '<p>Consultation des arrivees indisponible dans ce perimetre.</p></section>';
    }
}
