<?php
function SchoolSafeArrivalRender(array $data, array $classes, string $profile, ?int $period): string
{
    $escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    $html = '<form method="get"><label>Date <input type="date" name="date" required value="' . $escape($data['date']) . '"></label>';
    if ($profile === 'teacher') {
        $html .= '<label>Classe <select name="course_period_id" required>';
        foreach ($classes as $class) $html .= '<option value="' . $escape($class['course_period_id']) . '"'
            . ((int) $class['course_period_id'] === $period ? ' selected' : '') . '>' . $escape($class['title']) . '</option>';
        $html .= '</select></label>';
    }
    $html .= '<button type="submit">Afficher</button></form><p>Eleves avec arrivee enregistree : '
        . $escape($data['summary']['students']) . ' · Evenements : ' . $escape($data['summary']['events']) . '</p>';
    if (!$data['rows']) $html .= '<p>Aucune arrivee enregistree dans ce perimetre pour cette date.</p>';
    else {
        $html .= '<div class="table-scroll"><table><thead><tr><th>Eleve</th><th>Premiere arrivee</th><th>Derniere arrivee</th><th>Evenements</th></tr></thead><tbody>';
        foreach ($data['rows'] as $row) $html .= '<tr><td>' . $escape($row['first_name'] . ' ' . $row['last_name'])
            . '</td><td>' . $escape($row['first_arrival']) . '</td><td>' . $escape($row['last_arrival'])
            . '</td><td>' . $escape($row['events']) . '</td></tr>';
        $html .= '</tbody></table></div>';
    }
    $pages = max(1, (int) ceil((int) $data['summary']['students'] / 100));
    $html .= '<nav aria-label="Pages">Page ' . $escape($data['page']) . ' / ' . $escape($pages);
    foreach (['Precedente' => $data['page'] - 1, 'Suivante' => $data['page'] + 1] as $label => $page) {
        if ($page < 1 || $page > $pages) continue;
        $query = ['date' => $data['date'], 'page' => $page];
        if ($period !== null) $query['course_period_id'] = $period;
        $html .= ' <a href="arrival-attendance.php?' . $escape(http_build_query($query)) . '">' . $label . '</a>';
    }
    return $html . '</nav>';
}
