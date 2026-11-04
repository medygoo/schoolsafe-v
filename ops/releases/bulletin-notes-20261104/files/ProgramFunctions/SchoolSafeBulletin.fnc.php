<?php
// Notes de bulletin et synthese du titulaire (04/11, etape C validee par le proprietaire).
// Colonnes RDC : pour chaque trimestre, ses periodes, l'examen et le total ; puis le total general.
// Chaque enseignant note dans sa langue (fr : titulaire, en : professeur d'anglais) ; la note finale d'une
// branche combine les deux (moyenne par defaut, somme au choix de l'ecole). Totaux, pourcentages et places
// se calculent seuls ; le titulaire propose, la direction valide.
require_once __DIR__ . '/SchoolSafeBranches.fnc.php';

function SchoolSafeBulletinNumber($v): string
{
    if ($v === null || $v === '') return '';
    return str_replace('.', ',', rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'));
}

/** Trimestres (SEM) de l'annee, chacun avec ses periodes (QTR), dans l'ordre. */
function SchoolSafeBulletinTerms(int $school, int $year, callable $get): array
{
    $terms = [];
    foreach ((array) $get("SELECT MARKING_PERIOD_ID,TITLE,SORT_ORDER FROM school_marking_periods WHERE SCHOOL_ID=" . $school . " AND SYEAR=" . $year . " AND MP='SEM' ORDER BY SORT_ORDER,START_DATE") as $s)
        $terms[(int) $s['MARKING_PERIOD_ID']] = ['id' => (int) $s['MARKING_PERIOD_ID'], 'title' => (string) $s['TITLE'], 'periods' => []];
    foreach ((array) $get("SELECT MARKING_PERIOD_ID,TITLE,PARENT_ID FROM school_marking_periods WHERE SCHOOL_ID=" . $school . " AND SYEAR=" . $year . " AND MP='QTR' ORDER BY SORT_ORDER,START_DATE") as $q)
        if (isset($terms[(int) $q['PARENT_ID']])) $terms[(int) $q['PARENT_ID']]['periods'][(int) $q['MARKING_PERIOD_ID']] = (string) $q['TITLE'];
    return $terms;
}

/** Colonnes du bulletin : P (periode), E (examen d'un trimestre), T (total du trimestre), TG (total general). */
function SchoolSafeBulletinSlots(array $terms): array
{
    $slots = [];
    foreach ($terms as $t) {
        foreach ($t['periods'] as $id => $title) $slots['P:' . $id] = ['kind' => 'periode', 'mp' => $id, 'term' => $t['id'], 'label' => $title];
        $slots['E:' . $t['id']] = ['kind' => 'examen', 'mp' => $t['id'], 'term' => $t['id'], 'label' => 'Examen'];
        $slots['T:' . $t['id']] = ['kind' => 'total', 'mp' => $t['id'], 'term' => $t['id'], 'label' => 'Total ' . $t['title']];
    }
    $slots['TG'] = ['kind' => 'general', 'mp' => 0, 'term' => 0, 'label' => 'Total général'];
    return $slots;
}

function SchoolSafeBulletinLangMode(int $school, callable $get): string
{
    $r = $get("SELECT CONFIG_VALUE FROM config WHERE SCHOOL_ID=" . $school . " AND TITLE='SCHOOLSAFE_BULLETIN_LANG_MODE'");
    return is_array($r) && isset($r[1]) && trim((string) $r[1]['CONFIG_VALUE']) === 'somme' ? 'somme' : 'moyenne';
}

/**
 * Note finale d'une case : francais et anglais combines. Retourne [points|null, maxima].
 * Moyenne : (fr + en) / 2 sur le maxima ; somme : fr + en sur deux fois le maxima. Une seule note : elle compte seule
 * (en somme, ramenee sur le double pour garder la meme echelle).
 */
function SchoolSafeBulletinCombine($fr, $en, float $max, bool $hasEn, string $mode): array
{
    $has = static fn($v) => $v !== null && $v !== '';
    $scale = ($hasEn && $mode === 'somme') ? 2 : 1;
    if (!$has($fr) && !$has($en)) return [null, $max * $scale];
    if ($has($fr) && $has($en)) return [$mode === 'somme' ? (float) $fr + (float) $en : ((float) $fr + (float) $en) / 2, $max * $scale];
    $one = (float) ($has($fr) ? $fr : $en);
    return [$one * $scale, $max * $scale];
}

/** Branches de la classe (cours natifs crees a l'etape B), avec maxima. */
function SchoolSafeBulletinBranches(int $classCp, callable $get): array
{
    return (array) $get("SELECT b.COURSE_PERIOD_ID,b.TEACHER_FR,b.TEACHER_EN,m.TITLE,m.DOMAIN,m.SHORT_NAME,m.MAX_PERIOD,m.MAX_EXAM,m.SORT_ORDER"
        . " FROM schoolsafe_class_branches b JOIN schoolsafe_branch_models m ON m.ID=b.MODEL_ID WHERE b.CLASS_COURSE_PERIOD_ID=" . $classCp . " ORDER BY m.SORT_ORDER,m.ID");
}

/** Eleves de la classe aujourd'hui. */
function SchoolSafeBulletinStudents(int $classCp, callable $get): array
{
    $out = [];
    foreach ((array) $get("SELECT DISTINCT s.STUDENT_ID,s.FIRST_NAME,s.LAST_NAME FROM schedule sc JOIN students s ON s.STUDENT_ID=sc.STUDENT_ID WHERE sc.COURSE_PERIOD_ID=" . $classCp
        . " AND (sc.END_DATE IS NULL OR sc.END_DATE>=CURRENT_DATE) ORDER BY s.LAST_NAME,s.FIRST_NAME") as $r) $out[(int) $r['STUDENT_ID']] = trim($r['LAST_NAME'] . ' ' . $r['FIRST_NAME']);
    return $out;
}

/** Notes saisies : [branche][eleve][slotKey][langue] = points. */
function SchoolSafeBulletinMarks(int $classCp, callable $get): array
{
    $out = [];
    foreach ((array) $get("SELECT BRANCH_COURSE_PERIOD_ID,STUDENT_ID,MARKING_PERIOD_ID,KIND,LANG,POINTS FROM schoolsafe_bulletin_marks WHERE CLASS_COURSE_PERIOD_ID=" . $classCp . " AND POINTS IS NOT NULL") as $r)
        $out[(int) $r['BRANCH_COURSE_PERIOD_ID']][(int) $r['STUDENT_ID']][($r['KIND'] === 'examen' ? 'E:' : 'P:') . (int) $r['MARKING_PERIOD_ID']][$r['LANG']] = (float) $r['POINTS'];
    return $out;
}

/** Statuts (application, conduite, decision, brouillon/propose/valide) : [eleve][mp]. */
function SchoolSafeBulletinStatus(int $classCp, callable $get): array
{
    $out = [];
    foreach ((array) $get("SELECT * FROM schoolsafe_bulletin_status WHERE CLASS_COURSE_PERIOD_ID=" . $classCp) as $r) $out[(int) $r['STUDENT_ID']][(int) $r['MARKING_PERIOD_ID']] = $r;
    return $out;
}

/**
 * Suggestion depuis les devoirs d'un enseignant dans une branche : moyenne de ses notes (en %) ramenee au maxima.
 * Periode : travaux de la periode hors examens ; examen : travaux de type examen du trimestre.
 */
function SchoolSafeBulletinSuggest(int $branchCp, int $staff, string $kind, int $mp, float $max, callable $get): array
{
    $examType = "EXISTS (SELECT 1 FROM schoolsafe_remediation_types k WHERE k.ASSIGNMENT_TYPE_ID=ga.ASSIGNMENT_TYPE_ID AND k.KIND='exam')";
    $where = $kind === 'examen'
        ? $examType . " AND ga.MARKING_PERIOD_ID IN (SELECT MARKING_PERIOD_ID FROM school_marking_periods WHERE PARENT_ID=" . $mp . " OR MARKING_PERIOD_ID=" . $mp . ")"
        : "NOT " . $examType . " AND ga.MARKING_PERIOD_ID=" . $mp;
    $out = [];
    foreach ((array) $get("SELECT gg.STUDENT_ID,avg(gg.POINTS*100.0/ga.POINTS) AS PCT FROM gradebook_grades gg JOIN gradebook_assignments ga ON ga.ASSIGNMENT_ID=gg.ASSIGNMENT_ID"
        . " WHERE gg.COURSE_PERIOD_ID=" . $branchCp . " AND ga.STAFF_ID=" . $staff . " AND gg.POINTS>=0 AND ga.POINTS>0 AND " . $where . " GROUP BY gg.STUDENT_ID") as $r)
        $out[(int) $r['STUDENT_ID']] = round((float) $r['PCT'] * $max / 100, 1);
    return $out;
}

/** Maxima d'une branche pour une colonne de note. */
function SchoolSafeBulletinMax(array $branch, string $kind): float
{
    return $kind === 'examen' ? SchoolSafeBranchExamMax($branch['MAX_PERIOD'], $branch['MAX_EXAM']) : (float) $branch['MAX_PERIOD'];
}

/**
 * Calcul complet de la classe : pour chaque eleve, la note finale de chaque branche et de chaque colonne,
 * les totaux par colonne, le pourcentage, la place, et le nombre de notes manquantes.
 */
function SchoolSafeBulletinCompute(array $branches, array $students, array $marks, array $terms, string $mode): array
{
    $slots = SchoolSafeBulletinSlots($terms);
    $result = [];
    foreach ($students as $sid => $name) {
        $cells = []; $totals = []; $missing = [];
        foreach ($branches as $b) {
            $bcp = (int) $b['COURSE_PERIOD_ID'];
            $hasEn = (int) ($b['TEACHER_EN'] ?? 0) > 0;
            $termSum = []; $general = [0.0, 0.0, false];
            foreach ($slots as $key => $slot) {
                if ($slot['kind'] === 'periode' || $slot['kind'] === 'examen') {
                    $m = $marks[$bcp][$sid][$key] ?? [];
                    [$p, $max] = SchoolSafeBulletinCombine($m['fr'] ?? null, $m['en'] ?? null, SchoolSafeBulletinMax($b, $slot['kind']), $hasEn, $mode);
                    $cells[$bcp][$key] = [$p, $max];
                    if ($p === null) $missing[$slot['term']] = ($missing[$slot['term']] ?? 0) + 1;
                    $termSum[$slot['term']][0] = ($termSum[$slot['term']][0] ?? 0) + (float) $p;
                    $termSum[$slot['term']][1] = ($termSum[$slot['term']][1] ?? 0) + $max;
                    $termSum[$slot['term']][2] = ($termSum[$slot['term']][2] ?? false) || $p !== null;
                } elseif ($slot['kind'] === 'total') {
                    $t = $termSum[$slot['term']] ?? [0, 0, false];
                    $cells[$bcp][$key] = [$t[2] ? round($t[0], 2) : null, $t[1]];
                    $general[0] += $t[0]; $general[1] += $t[1]; $general[2] = $general[2] || $t[2];
                } else {
                    $cells[$bcp][$key] = [$general[2] ? round($general[0], 2) : null, $general[1]];
                }
                $c = $cells[$bcp][$key];
                $totals[$key][0] = ($totals[$key][0] ?? 0) + (float) $c[0];
                $totals[$key][1] = ($totals[$key][1] ?? 0) + $c[1];
                $totals[$key][2] = ($totals[$key][2] ?? false) || $c[0] !== null;
            }
        }
        $pct = [];
        foreach ($totals as $key => $t) $pct[$key] = $t[2] && $t[1] > 0 ? round($t[0] * 100 / $t[1], 1) : null;
        $result[$sid] = ['name' => $name, 'cells' => $cells, 'totals' => $totals, 'pct' => $pct, 'missing' => $missing, 'rank' => []];
    }
    foreach (array_keys($slots) as $key) {
        $list = [];
        foreach ($result as $sid => $r) if (($r['pct'][$key] ?? null) !== null) $list[$sid] = $r['pct'][$key];
        arsort($list);
        $rank = 0; $prev = null; $i = 0;
        foreach ($list as $sid => $v) { $i++; if ($v !== $prev) { $rank = $i; $prev = $v; } $result[$sid]['rank'][$key] = $rank; }
    }
    return ['slots' => $slots, 'students' => $result, 'count' => count($result)];
}

/** Classes ou l'enseignant note au moins une branche, avec sa langue. */
function SchoolSafeBulletinTeacherClasses(int $staff, int $school, int $year, callable $get): array
{
    return (array) $get("SELECT DISTINCT b.CLASS_COURSE_PERIOD_ID,c.TITLE AS CLASS_TITLE,CASE WHEN b.TEACHER_FR=" . $staff . " THEN 'fr' ELSE 'en' END AS LANG"
        . " FROM schoolsafe_class_branches b JOIN course_periods cp ON cp.COURSE_PERIOD_ID=b.CLASS_COURSE_PERIOD_ID JOIN courses c ON c.COURSE_ID=cp.COURSE_ID"
        . " WHERE b.SCHOOL_ID=" . $school . " AND b.SYEAR=" . $year . " AND (b.TEACHER_FR=" . $staff . " OR b.TEACHER_EN=" . $staff . ") ORDER BY c.TITLE");
}

/** Une note saisie : vide (retire), ou nombre entre 0 et le maxima. */
function SchoolSafeBulletinParse($value, float $max): ?string
{
    $v = str_replace(',', '.', trim((string) $value));
    if ($v === '') return null;
    if (!preg_match('/^\d{1,4}(\.\d{1,2})?$/', $v) || (float) $v > $max) throw new RuntimeException('Note invalide : ' . $value . ' (entre 0 et ' . SchoolSafeBulletinNumber($max) . ').');
    return $v;
}

function SchoolSafeBulletinDecisions(): array
{
    return ['passe' => 'Passe dans la classe supérieure', 'double' => 'Double sa classe', 'repechage' => 'Repêchage', 'echoue' => 'A échoué', 'oriente' => 'A échoué et orienté vers…'];
}
