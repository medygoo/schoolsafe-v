<?php
/**
 * Reglages du rattrapage par ecole (04/11, valide par le proprietaire) : regle de detection, alerte
 * « vigilance », partage ecole/enseignant, regle de reussite. Sans reglage : regle d'origine.
 * La reussite reste une decision humaine ; la regle sert d'indication a la direction.
 */

function SchoolSafeRemediationDefaults(): array
{
    return ['threshold_percent' => 50.0, 'failures' => 3, 'min_kinds' => 2, 'vigilance' => true,
        'school_share_percent' => 40.0, 'pass_percent' => 50.0, 'pass_gain_points' => null, 'saved' => false];
}

function SchoolSafeRemediationSettings(int $school, callable $get): array
{
    $settings = SchoolSafeRemediationDefaults();
    if ($school < 1) return $settings;
    $rows = $get('SELECT THRESHOLD_PERCENT,FAILURES,MIN_KINDS,VIGILANCE,SCHOOL_SHARE_PERCENT,PASS_PERCENT,PASS_GAIN_POINTS'
        . ' FROM schoolsafe_remediation_settings WHERE SCHOOL_ID=' . $school);
    if (!is_array($rows) || !isset($rows[1])) return $settings;
    $row = $rows[1];
    $gain = $row['PASS_GAIN_POINTS'] ?? null;
    return ['threshold_percent' => (float) $row['THRESHOLD_PERCENT'], 'failures' => (int) $row['FAILURES'],
        'min_kinds' => (int) $row['MIN_KINDS'], 'vigilance' => in_array($row['VIGILANCE'], ['t', true, 1, '1'], true),
        'school_share_percent' => (float) $row['SCHOOL_SHARE_PERCENT'], 'pass_percent' => (float) $row['PASS_PERCENT'],
        'pass_gain_points' => $gain === null || $gain === '' ? null : (float) $gain, 'saved' => true];
}

/** Valide le formulaire ; message en francais en cas d'erreur. */
function SchoolSafeRemediationSettingsValidate(array $in): array
{
    $number = static function ($value, string $label, float $min, float $max): float {
        $text = str_replace(',', '.', trim((string) $value));
        if (!preg_match('/^\d{1,3}(\.\d{1,2})?$/D', $text) || (float) $text < $min || (float) $text > $max)
            throw new RuntimeException($label . ' : valeur attendue entre ' . (int) $min . ' et ' . (int) $max . '.');
        return (float) $text;
    };
    $s = [];
    $s['threshold_percent'] = $number($in['threshold_percent'] ?? '', 'Seuil d’échec', 1, 100);
    $s['failures'] = (int) $number($in['failures'] ?? '', 'Nombre d’évaluations', 2, 6);
    $s['min_kinds'] = (int) $number($in['min_kinds'] ?? '', 'Sortes différentes', 1, 3);
    if ($s['min_kinds'] > $s['failures']) throw new RuntimeException('Les sortes différentes ne peuvent pas dépasser le nombre d’évaluations.');
    $s['vigilance'] = !empty($in['vigilance']);
    $s['school_share_percent'] = $number($in['school_share_percent'] ?? '', 'Part de l’école', 0, 100);
    $s['pass_percent'] = $number($in['pass_percent'] ?? '', 'Note de réussite', 1, 100);
    $gain = trim((string) ($in['pass_gain_points'] ?? ''));
    $s['pass_gain_points'] = $gain === '' ? null : $number($gain, 'Progression', 1, 100);
    return $s;
}

function SchoolSafeRemediationSettingsSave(int $school, int $actor, array $s, callable $exec): void
{
    if ($school < 1 || $actor < 1) throw new RuntimeException('Contexte de l’école invalide.');
    $n = static function ($v) { return $v === null ? 'NULL' : number_format((float) $v, 2, '.', ''); };
    $exec('INSERT INTO schoolsafe_remediation_settings(school_id,threshold_percent,failures,min_kinds,vigilance,school_share_percent,pass_percent,pass_gain_points,updated_by,updated_at)'
        . ' VALUES(' . $school . ',' . $n($s['threshold_percent']) . ',' . (int) $s['failures'] . ',' . (int) $s['min_kinds'] . ',' . ($s['vigilance'] ? 'true' : 'false')
        . ',' . $n($s['school_share_percent']) . ',' . $n($s['pass_percent']) . ',' . $n($s['pass_gain_points']) . ',' . $actor . ',now())'
        . ' ON CONFLICT(school_id) DO UPDATE SET threshold_percent=EXCLUDED.threshold_percent,failures=EXCLUDED.failures,min_kinds=EXCLUDED.min_kinds,'
        . 'vigilance=EXCLUDED.vigilance,school_share_percent=EXCLUDED.school_share_percent,pass_percent=EXCLUDED.pass_percent,'
        . 'pass_gain_points=EXCLUDED.pass_gain_points,updated_by=EXCLUDED.updated_by,updated_at=now()');
}

function SchoolSafeRemediationPct(float $value): string
{
    return str_replace('.', ',', rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.')) . ' %';
}

/** Phrases lisibles des regles de l'ecole. */
function SchoolSafeRemediationRuleText(array $s): array
{
    $kinds = [1 => 'de n’importe quelle sorte', 2 => 'd’au moins 2 sortes (devoir, interrogation, examen)', 3 => 'des 3 sortes (devoir, interrogation, examen)'];
    $gain = $s['pass_gain_points'] !== null ? ', ou une progression d’au moins ' . rtrim(rtrim(str_replace('.', ',', number_format((float) $s['pass_gain_points'], 2, '.', '')), '0'), ',') . ' points' : '';
    return [
        'detection' => 'Un dossier est proposé après ' . (int) $s['failures'] . ' évaluations sous ' . SchoolSafeRemediationPct($s['threshold_percent'])
            . ' dans une même matière, ' . ($kinds[(int) $s['min_kinds']] ?? $kinds[2]) . '.',
        'vigilance' => $s['vigilance'] ? 'Alerte « vigilance » dès 2 évaluations sous le seuil : l’enseignant la voit ici, le parent est prévenu.' : 'Alerte « vigilance » désactivée.',
        'share' => 'Partage des paiements : ' . SchoolSafeRemediationPct($s['school_share_percent']) . ' pour l’école, ' . SchoolSafeRemediationPct(100 - $s['school_share_percent']) . ' pour l’enseignant.',
        'pass' => 'Réussite conseillée : ' . SchoolSafeRemediationPct($s['pass_percent']) . ' ou plus à la nouvelle évaluation' . $gain . '.',
    ];
}

/** Indication selon la regle de reussite ; $before = moyenne en % des notes du dossier, null si inconnue. */
function SchoolSafeRemediationPassVerdict(array $s, float $points, float $maximum, ?float $before): bool
{
    if ($maximum <= 0) return false;
    $percent = $points * 100 / $maximum;
    if ($percent >= (float) $s['pass_percent']) return true;
    return $s['pass_gain_points'] !== null && $before !== null && $percent - $before >= (float) $s['pass_gain_points'];
}
