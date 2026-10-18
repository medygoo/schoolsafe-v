<?php
function SchoolSafePersonnelPeriod(array $request): array
{
    $valid = static function ($value) {
        if (!is_string($value)) return false;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value;
    };
    $start = $request['personnel_start'] ?? date('Y-m-01');
    $end = $request['personnel_end'] ?? date('Y-m-d');
    if (!$valid($start) || !$valid($end) || $start > $end
        || (new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days > 366) {
        return [date('Y-m-01'), date('Y-m-d'), 'Periode invalide : choisissez au maximum 367 jours.'];
    }
    return [$start, $end, ''];
}

function SchoolSafePersonnelStats(): string
{
    if (User('PROFILE') !== 'admin' || !AllowUse('Users/User.php')
        || !AllowUse('Staff_Absences/Absences.php')) return '';
    $school = (int) UserSchool();
    $year = (int) UserSyear();
    if ($school < 1 || $year < 1) return '';
    [$start, $end, $error] = SchoolSafePersonnelPeriod($_GET);
    $staff = DBGet("SELECT s.staff_id,s.first_name,s.last_name,s.profile,
        COALESCE(p.title,s.profile) AS function_title,s.schools
        FROM staff s LEFT JOIN user_profiles p ON p.id=s.profile_id
        WHERE s.syear=$year AND s.profile IN ('admin','teacher')
        AND (s.current_school_id=$school OR position(',$school,' IN s.schools)>0)
        ORDER BY s.last_name,s.first_name,s.staff_id");
    // An absence has no school key: only unambiguously single-school staff are counted.
    $days = DBGet("SELECT DISTINCT a.staff_id,CAST(d.day AS DATE) AS absent_day
        FROM staff_absences a JOIN staff s ON s.staff_id=a.staff_id AND s.syear=a.syear
        CROSS JOIN LATERAL generate_series(
            GREATEST(CAST(a.start_date AS DATE),DATE '$start'),
            LEAST(CAST(a.end_date AS DATE),DATE '$end'),INTERVAL '1 day') AS d(day)
        WHERE a.syear=$year AND s.profile IN ('admin','teacher')
        AND s.schools=',$school,' AND a.end_date>=a.start_date
        AND a.start_date<DATE '$end'+INTERVAL '1 day' AND a.end_date>=DATE '$start'");
    $e = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
    $byPerson = []; $byDay = []; $functions = []; $ranking = []; $ambiguous = 0;
    foreach ($days as $row) {
        $id = (int) $row['STAFF_ID']; $day = $row['ABSENT_DAY'];
        $byPerson[$id][$day] = true; $byDay[$day][$id] = true;
    }
    foreach ($staff as $row) {
        $title = ParseMLField($row['FUNCTION_TITLE']);
        $functions[$title] = ($functions[$title] ?? 0) + 1;
        if ($row['SCHOOLS'] !== ",$school,") $ambiguous++;
        $id = (int) $row['STAFF_ID'];
        if (!empty($byPerson[$id])) {
            $dates = array_keys($byPerson[$id]); sort($dates);
            $ranking[] = [$row['FIRST_NAME'].' '.$row['LAST_NAME'], $title, count($dates), $dates];
        }
    }
    usort($ranking, static function ($a, $b) { return ($b[2] <=> $a[2]) ?: strcmp($a[0], $b[0]); });
    ksort($byDay); arsort($functions);
    $html = '<section class="ss-personnel-statistics"><header><h3>Personnel : effectifs et absences</h3>'
        .'<a href="Modules.php?modname=Staff_Absences/Absences.php&amp;start='.$e($start).'&amp;end='.$e($end).'">Absences Rosario</a></header>'
        .'<form method="get" action="Modules.php" class="ss-personnel-period"><input type="hidden" name="modname" value="misc/Portal.php"><input type="hidden" name="dashboard_tab" value="personnel">'
        .'<label>Du <input type="date" name="personnel_start" value="'.$e($start).'" required></label>'
        .'<label>Au <input type="date" name="personnel_end" value="'.$e($end).'" required></label><button type="submit">Actualiser</button></form>';
    if ($error) $html .= '<p role="alert">'.$e($error).'</p>';
    $html .= '<p><strong>'.count($staff).' personnes</strong> dans l\'ecole selectionnee ; <strong>'.count($ranking)
        .' personnes avec absence</strong> ; <strong>'.array_sum(array_map('count', $byPerson)).' jours-personnes avec absence enregistree</strong>.</p>'
        .'<p>Un jour compte une fois par personne, meme avec plusieurs declarations. Une absence partielle compte comme un jour touche, pas comme une journee complete. Aucun pointage manquant n\'est compte comme absence.</p>';
    if ($ambiguous) $html .= '<p>'.$ambiguous.' rattachements sans attribution unique a cette ecole : absences exclues des statistiques par ecole.</p>';
    $html .= '<div class="ss-personnel-tables"><div><h4>Effectifs par fonction</h4><table><thead><tr><th>Fonction</th><th>Effectif</th></tr></thead><tbody>';
    foreach ($functions as $title => $count) $html .= '<tr><th scope="row">'.$e($title).'</th><td>'.$count.'</td></tr>';
    if (!$functions) $html .= '<tr><td colspan="2">Aucun personnel renseigne.</td></tr>';
    $html .= '</tbody></table></div><div><h4>Absences par jour</h4><table><thead><tr><th>Date</th><th>Personnes</th></tr></thead><tbody>';
    foreach ($byDay as $day => $people) $html .= '<tr><th scope="row">'.$e($day).'</th><td>'.count($people).'</td></tr>';
    if (!$byDay) $html .= '<tr><td colspan="2">Aucune absence enregistree sur cette periode.</td></tr>';
    $html .= '</tbody></table></div></div><h4>Personnel avec le plus de jours touches par une absence</h4><div class="ss-personnel-table-scroll"><table><thead><tr><th>Personne</th><th>Fonction</th><th>Jours</th><th>Dates</th></tr></thead><tbody>';
    foreach ($ranking as $row) $html .= '<tr><th scope="row">'.$e($row[0]).'</th><td>'.$e($row[1]).'</td><td>'.$row[2].'</td><td><details><summary>Voir les dates</summary>'.$e(implode(', ', $row[3])).'</details></td></tr>';
    if (!$ranking) $html .= '<tr><td colspan="4">Aucune absence attribuable a cette ecole sur cette periode.</td></tr>';
    return $html.'</tbody></table></div></section>';
}
