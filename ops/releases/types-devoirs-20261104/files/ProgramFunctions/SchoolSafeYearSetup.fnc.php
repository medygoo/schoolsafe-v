<?php
/**
 * « Preparer l'annee » (03/11, valide par le proprietaire) : etat de chaque etape de la preparation
 * de l'annee scolaire, calcule en lecture seule depuis le moteur. Chaque etape renvoie vers l'ecran
 * natif qui la realise ; aucune donnee n'est ecrite ici.
 *
 * Etat : 'ok' (fait), 'warn' (fait mais a corriger), 'todo' (a faire).
 */

/** Titres anglais laisses par l'installation (a traduire dans l'ecran natif). */
function SchoolSafeYearSetupEnglish(string $title): bool
{
    return (bool) preg_match('/\b(Full Year|Quarter|Semester|Full Day|Half Day|Period \d+|Main|Progress Period)\b/i', $title);
}

/** Titre lisible d'un champ multilingue natif (« |fr_FR.utf8:Botanique » -> « Botanique »). */
function SchoolSafeYearSetupTitle(string $title): string
{
    if (strpos($title, '|') === false) return trim($title);
    if (preg_match('/\|fr_FR\.utf8:([^|]*)/', $title, $m) && trim($m[1]) !== '') return trim($m[1]);
    return trim(explode('|', $title)[0]);
}

function SchoolSafeYearSetupSteps(int $school, int $year, callable $get): array
{
    $one = static function ($sql) use ($get) { $rows = $get($sql); return is_array($rows) && isset($rows[1]) ? $rows[1] : []; };
    $all = static function ($sql) use ($get) { $rows = $get($sql); return is_array($rows) ? array_values($rows) : []; };
    $s = $school; $y = $year;
    $steps = [];

    // 1. Ecole
    $info = $one("SELECT TITLE,ADDRESS,PHONE,PRINCIPAL FROM schools WHERE ID=$s AND SYEAR=$y");
    $missing = [];
    foreach (['ADDRESS' => 'adresse', 'PHONE' => 'téléphone', 'PRINCIPAL' => 'responsable'] as $k => $label) if (trim((string) ($info[$k] ?? '')) === '') $missing[] = $label;
    $steps[] = ['id' => 'ecole', 'title' => 'Informations de l’école', 'url' => 'Modules.php?modname=School_Setup/Schools.php',
        'state' => !$info ? 'todo' : ($missing ? 'warn' : 'ok'),
        'detail' => !$info ? 'École introuvable pour cette année.' : ($missing ? 'À compléter : ' . implode(', ', $missing) . '.' : trim((string) $info['TITLE']) . ' — adresse, téléphone et responsable renseignés.'),
        'help' => 'Nom, adresse, téléphone, responsable et logo. Ils apparaissent sur les cartes, les reçus, les fiches de devoirs et les bulletins.'];

    // 2. Annee et periodes
    $mps = $all("SELECT MP,TITLE,START_DATE,END_DATE FROM school_marking_periods WHERE SCHOOL_ID=$s AND SYEAR=$y ORDER BY SORT_ORDER,START_DATE");
    $years = array_values(array_filter($mps, static function ($m) { return $m['MP'] === 'FY'; }));
    $english = array_values(array_filter($mps, static function ($m) { return SchoolSafeYearSetupEnglish((string) $m['TITLE']); }));
    $problems = [];
    if (count($years) > 1) $problems[] = count($years) . ' années complètes au lieu d’une';
    if ($english) $problems[] = count($english) . ' période(s) au nom anglais (' . implode(', ', array_slice(array_map(static function ($m) { return $m['TITLE']; }, $english), 0, 3)) . ')';
    $steps[] = ['id' => 'periodes', 'title' => 'Année scolaire et périodes', 'url' => 'Modules.php?modname=School_Setup/MarkingPeriods.php',
        'tool' => ['decoupage-annee.php', 'Choisir le découpage'],
        'state' => !$years ? 'todo' : ($problems ? 'warn' : 'ok'),
        'detail' => !$years ? 'Aucune année scolaire définie.' : ($problems ? 'À corriger : ' . implode(' ; ', $problems) . '.' : count($mps) . ' période(s), du ' . $years[0]['START_DATE'] . ' au ' . $years[0]['END_DATE'] . '.'),
        'help' => 'Dates de l’année puis découpage (trimestres, semestres, périodes d’évaluation). Les notes et les bulletins sont rattachés à ces périodes.'];

    // 3. Calendrier
    $days = (int) ($one("SELECT COUNT(*) AS N FROM attendance_calendar WHERE SCHOOL_ID=$s AND SYEAR=$y")['N'] ?? 0);
    $steps[] = ['id' => 'calendrier', 'title' => 'Calendrier des jours de classe', 'url' => 'Modules.php?modname=School_Setup/Calendar.php',
        'state' => $days > 0 ? 'ok' : 'todo',
        'detail' => $days > 0 ? $days . ' jours de classe enregistrés.' : 'Aucun jour de classe : les présences et les absences ne peuvent pas être calculées.',
        'help' => 'Jours ouvrés, congés et jours fériés. Les présences, les absences automatiques et les retards s’appuient sur ce calendrier.'];

    // 4. Tranches horaires
    $periods = $all("SELECT TITLE FROM school_periods WHERE SCHOOL_ID=$s AND SYEAR=$y ORDER BY SORT_ORDER");
    $enPeriods = array_values(array_filter($periods, static function ($p) { return SchoolSafeYearSetupEnglish((string) $p['TITLE']); }));
    $steps[] = ['id' => 'tranches', 'title' => 'Tranches horaires de la journée', 'url' => 'Modules.php?modname=School_Setup/Periods.php',
        'state' => !$periods ? 'todo' : ($enPeriods ? 'warn' : 'ok'),
        'detail' => !$periods ? 'Aucune tranche horaire.' : ($enPeriods ? count($enPeriods) . ' tranche(s) au nom anglais (' . implode(', ', array_slice(array_column($enPeriods, 'TITLE'), 0, 3)) . ').' : count($periods) . ' tranche(s) horaire(s).'),
        'help' => 'Moments de la journée où ont lieu les cours (journée entière, matin, 1re heure…). Les classes et l’appel en dépendent.'];

    // 5. Niveaux
    $levels = $all("SELECT TITLE,NEXT_GRADE_ID FROM school_gradelevels WHERE SCHOOL_ID=$s ORDER BY SORT_ORDER");
    $broken = 0;
    foreach ($levels as $i => $l) if ($i < count($levels) - 1 && trim((string) $l['NEXT_GRADE_ID']) === '') $broken++;
    $steps[] = ['id' => 'niveaux', 'title' => 'Niveaux et passage de classe', 'url' => 'Modules.php?modname=School_Setup/GradeLevels.php',
        'state' => !$levels ? 'todo' : ($broken ? 'warn' : 'ok'),
        'detail' => !$levels ? 'Aucun niveau.' : count($levels) . ' niveau(x), de ' . $levels[0]['TITLE'] . ' à ' . $levels[count($levels) - 1]['TITLE'] . ($broken ? ' ; ' . $broken . ' niveau(x) sans niveau suivant.' : ' ; passage de classe complet.'),
        'help' => 'Maternelle, primaire, secondaire… et le niveau suivant de chacun, utilisé au passage de classe en fin d’année.'];

    // 6. Matieres, cours et classes
    $subjects = $all("SELECT TITLE FROM course_subjects WHERE SCHOOL_ID=$s AND SYEAR=$y ORDER BY SORT_ORDER,TITLE");
    $courses = (int) ($one("SELECT COUNT(*) AS N FROM courses WHERE SCHOOL_ID=$s AND SYEAR=$y")['N'] ?? 0);
    $cps = $one("SELECT COUNT(*) AS N, COUNT(*) FILTER (WHERE TEACHER_ID IS NULL) AS SANS FROM course_periods WHERE SCHOOL_ID=$s AND SYEAR=$y");
    $nCp = (int) ($cps['N'] ?? 0); $noTeacher = (int) ($cps['SANS'] ?? 0);
    $steps[] = ['id' => 'cours', 'title' => 'Matières, cours et classes', 'url' => 'Modules.php?modname=Scheduling/Courses.php',
        'state' => !$subjects || !$courses || !$nCp ? 'todo' : ($noTeacher ? 'warn' : 'ok'),
        'detail' => count($subjects) . ' matière(s)' . ($subjects ? ' (' . implode(', ', array_slice(array_map(static function ($m) { return SchoolSafeYearSetupTitle((string) $m['TITLE']); }, $subjects), 0, 4)) . ')' : '')
            . ', ' . $courses . ' cours, ' . $nCp . ' classe(s)' . ($noTeacher ? ' dont ' . $noTeacher . ' sans enseignant' : '') . '.',
        'help' => 'Matières (français, mathématiques…), cours par niveau, puis classes : un cours donné par un enseignant sur une tranche horaire.'];

    // 7. Baremes de notes
    $scales = $all("SELECT TITLE FROM report_card_grade_scales WHERE SCHOOL_ID=$s AND SYEAR=$y ORDER BY SORT_ORDER");
    $enScales = array_values(array_filter($scales, static function ($g) { return SchoolSafeYearSetupEnglish((string) $g['TITLE']); }));
    // Bareme americain d'installation (A+ a F, moyenne sur 4, reussite a 60 %) : a remplacer par celui de l'ecole.
    $letters = array_column($all("SELECT TITLE FROM report_card_grades WHERE SCHOOL_ID=$s AND SYEAR=$y"), 'TITLE');
    $american = in_array('A+', $letters, true) && in_array('F', $letters, true);
    // Une classe sans bareme n'envoie aucune note au bulletin.
    $noScale = (int) ($one("SELECT COUNT(*) AS N FROM course_periods WHERE SCHOOL_ID=$s AND SYEAR=$y AND GRADE_SCALE_ID IS NULL")['N'] ?? 0);
    $steps[] = ['id' => 'baremes', 'title' => 'Barèmes de notation', 'url' => 'Modules.php?modname=Grades/ReportCardGrades.php',
        'tool' => ['bareme-annee.php', 'Choisir le barème'],
        'state' => !$scales ? 'todo' : ($enScales || $american || $noScale ? 'warn' : 'ok'),
        'detail' => !$scales ? 'Aucun barème.' : count($scales) . ' barème(s) : ' . implode(', ', array_column($scales, 'TITLE')) . ($enScales ? ' (nom anglais à traduire)' : '')
            . ($american ? ' — barème américain d’installation (A+ à F, réussite à 60 %) : choisissez celui de votre école.' : '.')
            . ($noScale ? ' ' . $noScale . ' classe(s) sans barème : leurs notes n’iront pas au bulletin.' : ''),
        'help' => 'Échelle des notes (sur 10, sur 20, en pourcentage) et mentions. Les bulletins et le rattrapage (moins de 50 %) l’utilisent.'];

    // 8. Types de devoirs
    $types = $all("SELECT DISTINCT TRIM(TITLE) AS TITLE FROM gradebook_assignment_types WHERE COURSE_ID IN (SELECT COURSE_ID FROM course_periods WHERE SCHOOL_ID=$s AND SYEAR=$y) ORDER BY 1");
    // Un type sans sorte (devoir, interrogation, examen) ne compte pas pour le rattrapage.
    $unmapped = (int) ($one("SELECT COUNT(*) AS N FROM gradebook_assignment_types t WHERE t.COURSE_ID IN (SELECT COURSE_ID FROM course_periods WHERE SCHOOL_ID=$s AND SYEAR=$y) AND NOT EXISTS (SELECT 1 FROM schoolsafe_remediation_types r WHERE r.ASSIGNMENT_TYPE_ID=t.ASSIGNMENT_TYPE_ID)")['N'] ?? 0);
    $steps[] = ['id' => 'types', 'title' => 'Types de devoirs', 'url' => 'Modules.php?modname=Grades/MassCreateAssignments.php&assignment_type=new',
        'tool' => ['types-devoirs.php', 'Choisir les types'],
        'state' => $types ? ($unmapped ? 'warn' : 'ok') : 'todo',
        'detail' => $types ? count($types) . ' type(s) : ' . implode(', ', array_column($types, 'TITLE')) . '.' . ($unmapped ? ' ' . $unmapped . ' sans sorte pour le rattrapage.' : '') : 'Aucun type : aucun devoir ne peut être créé.',
        'help' => 'Devoir, interrogation, examen… créés en une fois pour tous les cours choisis. Ils débloquent les devoirs et servent au rattrapage.'];

    // 9. Horaires d'arrivee
    $policies = (int) ($one("SELECT COUNT(*) AS N FROM schoolsafe_attendance_policy WHERE SCHOOL_ID=$s")['N'] ?? 0);
    $steps[] = ['id' => 'horaires', 'title' => 'Horaires d’arrivée', 'url' => 'horaires-arrivee.php',
        'state' => $policies > 0 ? 'ok' : 'todo',
        'detail' => $policies > 0 ? $policies . ' niveau(x) avec horaires.' : 'Aucun horaire : retards et absences automatiques inactifs.',
        'help' => 'Heure d’arrivée, heure tolérée et heure rouge par cycle. Le scan d’entrée et les absences automatiques en dépendent.'];

    // 10. Eleves dans les classes
    $enrolled = (int) ($one("SELECT COUNT(DISTINCT STUDENT_ID) AS N FROM student_enrollment WHERE SCHOOL_ID=$s AND SYEAR=$y AND (END_DATE IS NULL OR END_DATE>=CURRENT_DATE)")['N'] ?? 0);
    $scheduled = (int) ($one("SELECT COUNT(DISTINCT STUDENT_ID) AS N FROM schedule WHERE SCHOOL_ID=$s AND SYEAR=$y AND (END_DATE IS NULL OR END_DATE>=CURRENT_DATE)")['N'] ?? 0);
    $steps[] = ['id' => 'programmation', 'title' => 'Élèves placés dans leurs classes', 'url' => 'Modules.php?modname=Scheduling/MassSchedule.php',
        'state' => $enrolled === 0 ? 'todo' : ($scheduled >= $enrolled ? 'ok' : ($scheduled > 0 ? 'warn' : 'todo')),
        'detail' => $scheduled . ' élève(s) placé(s) sur ' . $enrolled . ' inscrit(s).',
        'help' => 'Chaque élève inscrit est placé dans sa classe. Sans cela, il n’apparaît ni à l’appel, ni dans les notes, ni dans les devoirs.'];

    return $steps;
}

/** Nombre d'etapes terminees (ok) sur le total. */
function SchoolSafeYearSetupProgress(array $steps): array
{
    $done = count(array_filter($steps, static function ($st) { return $st['state'] === 'ok'; }));
    return [$done, count($steps)];
}

/* ---------------------------------------------------------------------------------------------
 * Decoupage de l'annee (04/11, valide par le proprietaire : « laisser le choix selon son ecole »).
 * Modeles proposes ; l'ecole choisit, ajuste les dates, confirme. Hierarchie native :
 * annee (FY) > semestre ou trimestre (SEM) > trimestre ou periode (QTR, ou se saisissent les notes).
 * ------------------------------------------------------------------------------------------- */

function SchoolSafeYearModels(): array
{
    return [
        'trimestres' => ['label' => '3 trimestres', 'desc' => 'Une note par trimestre.',
            'sem' => [['Année scolaire', 'AN', [['1er trimestre', 'T1'], ['2e trimestre', 'T2'], ['3e trimestre', 'T3']]]]],
        'trimestres_periodes' => ['label' => '3 trimestres de 2 périodes', 'desc' => 'Comme le primaire en RDC : 6 périodes, regroupées en 3 trimestres.',
            'sem' => [['1er trimestre', 'T1', [['1re période', 'P1'], ['2e période', 'P2']]],
                ['2e trimestre', 'T2', [['3e période', 'P3'], ['4e période', 'P4']]],
                ['3e trimestre', 'T3', [['5e période', 'P5'], ['6e période', 'P6']]]]],
        'semestres_periodes' => ['label' => '2 semestres de 2 périodes', 'desc' => 'Comme le secondaire en RDC : 4 périodes, regroupées en 2 semestres.',
            'sem' => [['1er semestre', 'S1', [['1re période', 'P1'], ['2e période', 'P2']]],
                ['2e semestre', 'S2', [['3e période', 'P3'], ['4e période', 'P4']]]]],
        'semestres' => ['label' => '2 semestres', 'desc' => 'Une note par semestre.',
            'sem' => [['1er semestre', 'S1', [['1er semestre', 'S1']]], ['2e semestre', 'S2', [['2e semestre', 'S2']]]]],
    ];
}

function SchoolSafeYearDate(string $date): ?DateTimeImmutable
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) return null;
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date ? $d : null;
}

/**
 * Plan du decoupage : dates reparties regulierement entre le debut et la fin de l'annee, sauf
 * dates choisies par l'ecole ($dates[i] = [debut, fin] pour la i-eme periode de notes).
 * Les dates d'un semestre/trimestre englobent ses periodes. Leve RuntimeException si incoherent.
 */
function SchoolSafeYearPlan(string $model, string $start, string $end, array $dates = []): array
{
    $models = SchoolSafeYearModels();
    if (!isset($models[$model])) throw new RuntimeException('Modèle de découpage inconnu.');
    $from = SchoolSafeYearDate($start); $to = SchoolSafeYearDate($end);
    if (!$from || !$to || $from >= $to) throw new RuntimeException('Dates de l’année invalides : le début doit précéder la fin.');
    if ($from->diff($to)->days > 400) throw new RuntimeException('Une année scolaire ne peut pas dépasser 13 mois.');
    $leaves = [];
    foreach ($models[$model]['sem'] as $si => $sem) foreach ($sem[2] as $q) $leaves[] = ['sem' => $si, 'title' => $q[0], 'short' => $q[1]];
    $n = count($leaves); $total = $from->diff($to)->days + 1; $cursor = $from;
    foreach ($leaves as $i => &$leaf) {
        $len = (int) floor($total * ($i + 1) / $n) - (int) floor($total * $i / $n);
        $leaf['start'] = $cursor->format('Y-m-d');
        $leaf['end'] = $i === $n - 1 ? $to->format('Y-m-d') : $cursor->modify('+' . ($len - 1) . ' days')->format('Y-m-d');
        if (isset($dates[$i]) && is_array($dates[$i])) {
            $a = SchoolSafeYearDate((string) ($dates[$i][0] ?? '')); $b = SchoolSafeYearDate((string) ($dates[$i][1] ?? ''));
            if (!$a || !$b) throw new RuntimeException('Dates invalides pour « ' . $leaf['title'] . ' ».');
            $leaf['start'] = $a->format('Y-m-d'); $leaf['end'] = $b->format('Y-m-d');
        }
        $cursor = SchoolSafeYearDate($leaf['end'])->modify('+1 day');
    }
    unset($leaf);
    foreach ($leaves as $i => $leaf) {
        if ($leaf['start'] > $leaf['end']) throw new RuntimeException('« ' . $leaf['title'] . ' » se termine avant de commencer.');
        if ($leaf['start'] < $from->format('Y-m-d') || $leaf['end'] > $to->format('Y-m-d')) throw new RuntimeException('« ' . $leaf['title'] . ' » sort de l’année scolaire.');
        if ($i > 0 && $leaf['start'] <= $leaves[$i - 1]['end']) throw new RuntimeException('« ' . $leaf['title'] . ' » chevauche la période précédente.');
    }
    $sems = [];
    foreach ($models[$model]['sem'] as $si => $sem) {
        $children = array_values(array_filter($leaves, static function ($l) use ($si) { return $l['sem'] === $si; }));
        $sems[] = ['title' => $sem[0], 'short' => $sem[1], 'start' => $children[0]['start'], 'end' => $children[count($children) - 1]['end'], 'qtrs' => $children];
    }
    $label = 'Année scolaire ' . $from->format('Y') . '-' . $to->format('Y');
    return ['model' => $model, 'fy' => ['title' => $label, 'short' => 'AN', 'start' => $from->format('Y-m-d'), 'end' => $to->format('Y-m-d')], 'sems' => $sems];
}

/** Nombre de lignes qui designent une periode, dans toutes les tables qui ont une colonne marking_period_id. */
function SchoolSafeYearPeriodUses(int $id, callable $get): int
{
    $uses = 0;
    // Vraies tables seulement : les vues (marking_periods, course_details) relisent les periodes elles-memes.
    foreach ((array) $get("SELECT c.TABLE_NAME FROM information_schema.columns c JOIN information_schema.tables t ON t.TABLE_SCHEMA=c.TABLE_SCHEMA AND t.TABLE_NAME=c.TABLE_NAME AND t.TABLE_TYPE='BASE TABLE'"
        . " WHERE c.TABLE_SCHEMA='public' AND c.COLUMN_NAME='marking_period_id' AND c.TABLE_NAME<>'school_marking_periods'") as $row) {
        $table = (string) ($row['TABLE_NAME'] ?? '');
        if (!preg_match('/^[a-z0-9_]+$/D', $table)) continue;
        $rows = $get('SELECT COUNT(*) AS N FROM ' . $table . " WHERE CAST(MARKING_PERIOD_ID AS TEXT)='" . $id . "'");
        $uses += (int) ($rows[1]['N'] ?? 0);
    }
    return $uses;
}

/**
 * Applique le plan dans une transaction : reutilise et renomme les periodes existantes (memes
 * identifiants, rien ne casse), cree celles qui manquent, ne supprime une periode en trop que si
 * aucune donnee ne la designe (sinon tout est annule). Retourne un resume.
 */
function SchoolSafeYearApply(int $school, int $year, array $plan, callable $get, callable $exec): array
{
    if ($school < 1 || $year < 1 || empty($plan['fy']) || empty($plan['sems'])) throw new RuntimeException('Plan incomplet.');
    $q = static function ($v) { return "'" . str_replace("'", "''", (string) $v) . "'"; };
    $post = static function (string $end, int $days) { return SchoolSafeYearDate($end)->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d'); };
    $exec('BEGIN');
    try {
        $rows = (array) $get("SELECT MARKING_PERIOD_ID AS ID,MP,START_DATE FROM school_marking_periods WHERE SCHOOL_ID=$school AND SYEAR=$year ORDER BY START_DATE,SORT_ORDER FOR UPDATE");
        $by = ['FY' => [], 'SEM' => [], 'QTR' => [], 'PRO' => []];
        foreach ($rows as $r) if (isset($by[$r['MP']])) $by[$r['MP']][] = (int) $r['ID'];
        $summary = ['renamed' => 0, 'created' => 0, 'deleted' => 0];
        // Annee : on garde celle qui porte des donnees (cours, emplois du temps...), sinon la premiere.
        $fyId = null; $best = -1;
        foreach ($by['FY'] as $id) { $u = SchoolSafeYearPeriodUses($id, $get); if ($u > $best) { $best = $u; $fyId = $id; } }
        $fy = $plan['fy'];
        if ($fyId) {
            $exec('UPDATE school_marking_periods SET TITLE=' . $q($fy['title']) . ',SHORT_NAME=' . $q($fy['short']) . ',SORT_ORDER=1,PARENT_ID=NULL,START_DATE=' . $q($fy['start'])
                . ',END_DATE=' . $q($fy['end']) . ',UPDATED_AT=CURRENT_TIMESTAMP WHERE MARKING_PERIOD_ID=' . $fyId);
            $summary['renamed']++;
        } else {
            $new = $get("INSERT INTO school_marking_periods(SYEAR,MP,SCHOOL_ID,TITLE,SHORT_NAME,SORT_ORDER,START_DATE,END_DATE) VALUES($year,'FY',$school," . $q($fy['title']) . ',' . $q($fy['short']) . ',1,' . $q($fy['start']) . ',' . $q($fy['end']) . ') RETURNING MARKING_PERIOD_ID AS ID');
            $fyId = (int) $new[1]['ID']; $summary['created']++;
        }
        $semIds = $by['SEM']; $qtrIds = $by['QTR']; $qi = 0; $usedQtr = []; $usedSem = [];
        foreach ($plan['sems'] as $si => $sem) {
            if (isset($semIds[$si])) {
                $semId = $semIds[$si];
                $exec('UPDATE school_marking_periods SET TITLE=' . $q($sem['title']) . ',SHORT_NAME=' . $q($sem['short']) . ',SORT_ORDER=' . ($si + 1) . ",PARENT_ID=$fyId,START_DATE=" . $q($sem['start'])
                    . ',END_DATE=' . $q($sem['end']) . ',POST_START_DATE=NULL,POST_END_DATE=NULL,UPDATED_AT=CURRENT_TIMESTAMP WHERE MARKING_PERIOD_ID=' . $semId);
                $summary['renamed']++;
            } else {
                $new = $get("INSERT INTO school_marking_periods(SYEAR,MP,SCHOOL_ID,PARENT_ID,TITLE,SHORT_NAME,SORT_ORDER,START_DATE,END_DATE) VALUES($year,'SEM',$school,$fyId," . $q($sem['title']) . ',' . $q($sem['short']) . ',' . ($si + 1) . ',' . $q($sem['start']) . ',' . $q($sem['end']) . ') RETURNING MARKING_PERIOD_ID AS ID');
                $semId = (int) $new[1]['ID']; $summary['created']++;
            }
            $usedSem[] = $semId;
            foreach ($sem['qtrs'] as $qtr) {
                $ps = $post($qtr['end'], -6); $pe = $post($qtr['end'], 14);
                if (isset($qtrIds[$qi])) {
                    $qtrId = $qtrIds[$qi];
                    $exec('UPDATE school_marking_periods SET TITLE=' . $q($qtr['title']) . ',SHORT_NAME=' . $q($qtr['short']) . ',SORT_ORDER=' . ($qi + 1) . ",PARENT_ID=$semId,START_DATE=" . $q($qtr['start'])
                        . ',END_DATE=' . $q($qtr['end']) . ',POST_START_DATE=' . $q($ps) . ',POST_END_DATE=' . $q($pe) . ",DOES_GRADES='Y',DOES_COMMENTS='Y',UPDATED_AT=CURRENT_TIMESTAMP WHERE MARKING_PERIOD_ID=" . $qtrId);
                    $summary['renamed']++;
                } else {
                    $new = $get("INSERT INTO school_marking_periods(SYEAR,MP,SCHOOL_ID,PARENT_ID,TITLE,SHORT_NAME,SORT_ORDER,START_DATE,END_DATE,POST_START_DATE,POST_END_DATE,DOES_GRADES,DOES_COMMENTS) VALUES($year,'QTR',$school,$semId,"
                        . $q($qtr['title']) . ',' . $q($qtr['short']) . ',' . ($qi + 1) . ',' . $q($qtr['start']) . ',' . $q($qtr['end']) . ',' . $q($ps) . ',' . $q($pe) . ",'Y','Y') RETURNING MARKING_PERIOD_ID AS ID");
                    $qtrId = (int) $new[1]['ID']; $summary['created']++;
                }
                $usedQtr[] = $qtrId; $qi++;
            }
        }
        // Periodes en trop : supprimees seulement si rien ne les designe (periodes de suivi d'abord).
        $surplus = array_merge($by['PRO'], array_diff($qtrIds, $usedQtr), array_diff($semIds, $usedSem), array_diff($by['FY'], [$fyId]));
        foreach ($surplus as $id) {
            if (SchoolSafeYearPeriodUses((int) $id, $get) > 0) throw new RuntimeException('Une période en trop contient déjà des données (notes, cours ou appels) : rien n’a été modifié. Ajustez-la dans l’écran natif.');
            $exec('DELETE FROM school_marking_periods WHERE MARKING_PERIOD_ID=' . (int) $id . " AND SCHOOL_ID=$school AND SYEAR=$year");
            $summary['deleted']++;
        }
        $exec('COMMIT');
        return $summary;
    } catch (Throwable $failure) {
        $exec('ROLLBACK');
        throw $failure;
    }
}

/* ---------------------------------------------------------------------------------------------
 * Choix du bareme (04/11, valide par le proprietaire) : l'ecole choisit un modele ou regle le sien
 * dans l'ecran natif. Une mention = [code, libelle, seuil en %, valeur pour la moyenne].
 * ------------------------------------------------------------------------------------------- */

function SchoolSafeScaleModels(): array
{
    return [
        'rdc' => ['label' => 'RDC (pourcentage et mentions)', 'desc' => 'Usage des bulletins et palmarès en RDC : réussite à 50 %, mentions de Satisfaction à Élite.',
            'scale' => 100, 'passing' => 50, 'grades' => [
                ['E', 'Élite', 80, 90], ['GD', 'Grande distinction', 70, 75], ['D', 'Distinction', 60, 65],
                ['S', 'Satisfaction', 50, 55], ['ECH', 'Échec', 0, 25]]],
        'sur20' => ['label' => 'Sur 20', 'desc' => 'Réussite à 10/20 : Très bien, Bien, Assez bien, Passable, Insuffisant.',
            'scale' => 20, 'passing' => 10, 'grades' => [
                ['TB', 'Très bien (16 à 20/20)', 80, 18], ['B', 'Bien (14 à 15,9/20)', 70, 15], ['AB', 'Assez bien (12 à 13,9/20)', 60, 13],
                ['P', 'Passable (10 à 11,9/20)', 50, 11], ['I', 'Insuffisant (moins de 10/20)', 0, 5]]],
        'sur10' => ['label' => 'Sur 10', 'desc' => 'Réussite à 5/10 : Très bien, Bien, Assez bien, Passable, Insuffisant.',
            'scale' => 10, 'passing' => 5, 'grades' => [
                ['TB', 'Très bien (8 à 10/10)', 80, 9], ['B', 'Bien (7 à 7,9/10)', 70, 7.5], ['AB', 'Assez bien (6 à 6,9/10)', 60, 6.5],
                ['P', 'Passable (5 à 5,9/10)', 50, 5.5], ['I', 'Insuffisant (moins de 5/10)', 0, 2.5]]],
    ];
}

/**
 * Mentions modifiees par l'ecole : [code, libelle, seuil %]. Seuils strictement decroissants, le dernier
 * a 0 %, codes uniques. La valeur pour la moyenne est le milieu de chaque tranche, a l'echelle du bareme.
 */
function SchoolSafeScaleValidate(array $rows, float $scale): array
{
    $clean = []; $codes = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $code = trim((string) ($row[0] ?? '')); $label = trim((string) ($row[1] ?? '')); $raw = trim(str_replace(',', '.', (string) ($row[2] ?? '')));
        if ($code === '' && $label === '' && $raw === '') continue; // ligne laissee vide = retiree
        if ($code === '' || mb_strlen($code) > 5 || !preg_match('/^[\p{L}\p{N}+\-]+$/u', $code)) throw new RuntimeException('Code de mention invalide : « ' . $code . ' » (1 à 5 lettres ou chiffres).');
        if (isset($codes[mb_strtoupper($code)]) || mb_strtoupper($code) === 'NE') throw new RuntimeException('Le code « ' . $code . ' » est utilisé deux fois ou réservé.');
        if ($label === '' || mb_strlen($label) > 80) throw new RuntimeException('Libellé manquant ou trop long pour « ' . $code . ' ».');
        if (!is_numeric($raw) || (float) $raw < 0 || (float) $raw > 100) throw new RuntimeException('Seuil invalide pour « ' . $code . ' » (0 à 100 %).');
        $codes[mb_strtoupper($code)] = true;
        $clean[] = [$code, $label, round((float) $raw, 2)];
    }
    if (count($clean) < 2) throw new RuntimeException('Il faut au moins deux mentions.');
    foreach ($clean as $i => $g) if ($i > 0 && $g[2] >= $clean[$i - 1][2]) throw new RuntimeException('Les seuils doivent aller du plus haut au plus bas (« ' . $g[0] . ' »).');
    if ($clean[count($clean) - 1][2] != 0) throw new RuntimeException('La dernière mention doit commencer à 0 %.');
    foreach ($clean as $i => &$g) {
        $upper = $i === 0 ? 100 : $clean[$i - 1][2];
        $g[3] = round(($g[2] + $upper) / 2 * $scale / 100, 2);
    }
    unset($g);
    return $clean;
}

/**
 * Applique le modele : met a jour le bareme existant (ou en cree un), remplace ses mentions, et
 * relie eventuellement les classes sans bareme. Refuse si des notes de bulletin utilisent deja ses
 * mentions (rien n'est alors modifie). Une seule transaction.
 */
function SchoolSafeScaleApply(int $school, int $year, string $model, bool $linkClasses, callable $get, callable $exec, ?array $custom = null): array
{
    $models = SchoolSafeScaleModels();
    if (!isset($models[$model]) || $school < 1 || $year < 1) throw new RuntimeException('Modèle de barème inconnu.');
    $m = $models[$model];
    if ($custom !== null) $m['grades'] = SchoolSafeScaleValidate($custom, (float) $m['scale']); // mentions modifiees par l'ecole
    $q = static function ($v) { return "'" . str_replace("'", "''", (string) $v) . "'"; };
    $exec('BEGIN');
    try {
        $scales = (array) $get("SELECT ID FROM report_card_grade_scales WHERE SCHOOL_ID=$school AND SYEAR=$year ORDER BY SORT_ORDER,ID FOR UPDATE");
        if (isset($scales[1])) {
            $scaleId = (int) $scales[1]['ID'];
            $used = (int) ($get("SELECT COUNT(*) AS N FROM student_report_card_grades WHERE REPORT_CARD_GRADE_ID IN (SELECT ID FROM report_card_grades WHERE GRADE_SCALE_ID=$scaleId)")[1]['N'] ?? 0);
            if ($used > 0) throw new RuntimeException('Des notes de bulletin utilisent déjà ce barème (' . $used . ') : rien n’a été modifié. Ajustez-le dans l’écran natif.');
            $exec('UPDATE report_card_grade_scales SET TITLE=' . $q($m['label']) . ',COMMENT=' . $q($m['desc']) . ',GP_SCALE=' . (float) $m['scale'] . ',GP_PASSING_VALUE=' . (float) $m['passing']
                . ',HR_GPA_VALUE=NULL,HHR_GPA_VALUE=NULL,HRS_GPA_VALUE=NULL,UPDATED_AT=CURRENT_TIMESTAMP WHERE ID=' . $scaleId);
            $exec('DELETE FROM report_card_grades WHERE GRADE_SCALE_ID=' . $scaleId . " AND SCHOOL_ID=$school AND SYEAR=$year");
        } else {
            $new = $get("INSERT INTO report_card_grade_scales(SYEAR,SCHOOL_ID,TITLE,COMMENT,SORT_ORDER,GP_SCALE,GP_PASSING_VALUE) VALUES($year,$school," . $q($m['label']) . ',' . $q($m['desc']) . ',1,' . (float) $m['scale'] . ',' . (float) $m['passing'] . ') RETURNING ID');
            $scaleId = (int) $new[1]['ID'];
        }
        $order = 0;
        foreach ($m['grades'] as $g) {
            $order++;
            $exec("INSERT INTO report_card_grades(SYEAR,SCHOOL_ID,TITLE,SORT_ORDER,GPA_VALUE,UNWEIGHTED_GP,BREAK_OFF,COMMENT,GRADE_SCALE_ID) VALUES($year,$school,"
                . $q($g[0]) . ",$order," . (float) $g[3] . ',' . (float) $g[3] . ',' . (float) $g[2] . ',' . $q($g[1]) . ",$scaleId)");
        }
        $exec("INSERT INTO report_card_grades(SYEAR,SCHOOL_ID,TITLE,SORT_ORDER,COMMENT,GRADE_SCALE_ID) VALUES($year,$school,'NE'," . ($order + 1) . ",'Non évalué (ne compte pas dans la moyenne)',$scaleId)");
        $linked = 0;
        if ($linkClasses) {
            $rows = $get("UPDATE course_periods SET GRADE_SCALE_ID=$scaleId WHERE SCHOOL_ID=$school AND SYEAR=$year AND GRADE_SCALE_ID IS NULL RETURNING COURSE_PERIOD_ID");
            $linked = is_array($rows) ? count($rows) : 0;
        }
        $exec('COMMIT');
        return ['scale' => $scaleId, 'grades' => $order + 1, 'linked' => $linked];
    } catch (Throwable $failure) {
        $exec('ROLLBACK');
        throw $failure;
    }
}

/* ---------------------------------------------------------------------------------------------
 * Types de devoirs (04/11, valide par le proprietaire) : l'ecole choisit ses types (modeles cochables,
 * noms modifiables, types libres). Chaque type est cree par cours et par enseignant (comme l'ecran
 * natif « Creer des devoirs en masse ») et recoit sa sorte pour le rattrapage (devoir, interrogation,
 * examen) — sans cette sorte, aucun dossier de rattrapage ne peut s'ouvrir.
 * ------------------------------------------------------------------------------------------- */

function SchoolSafeTypeModels(): array
{
    return [
        ['Devoir', 'homework', true], ['Interrogation', 'quiz', true], ['Examen', 'exam', true],
        ['Travail pratique', 'homework', false], ['Exercice en classe', 'quiz', false],
    ];
}

function SchoolSafeTypeKinds(): array
{
    return ['homework' => 'Devoir', 'quiz' => 'Interrogation', 'exam' => 'Examen', '' => 'Hors rattrapage'];
}

/** Types retenus par l'ecole : [titre, sorte, % de la note de periode ou null]. Titres uniques. */
function SchoolSafeTypeValidate(array $rows): array
{
    $kinds = SchoolSafeTypeKinds(); $clean = []; $seen = [];
    foreach ($rows as $row) {
        if (!is_array($row) || empty($row['on'])) continue;
        $title = trim(preg_replace('/\s+/u', ' ', (string) ($row['title'] ?? '')));
        if ($title === '') continue;
        if (mb_strlen($title) > 60) throw new RuntimeException('Nom de type trop long : « ' . mb_substr($title, 0, 20) . '… ».');
        $key = mb_strtolower($title);
        if (isset($seen[$key])) throw new RuntimeException('Le type « ' . $title . ' » est choisi deux fois.');
        $kind = (string) ($row['kind'] ?? '');
        if (!array_key_exists($kind, $kinds)) throw new RuntimeException('Sorte inconnue pour « ' . $title . ' ».');
        $raw = trim(str_replace(',', '.', (string) ($row['percent'] ?? '')));
        if ($raw !== '' && (!is_numeric($raw) || (float) $raw < 0 || (float) $raw > 100)) throw new RuntimeException('Part de la note invalide pour « ' . $title . ' » (0 à 100 %).');
        $seen[$key] = true;
        $clean[] = [$title, $kind, $raw === '' ? null : round((float) $raw, 2)];
    }
    if (!$clean) throw new RuntimeException('Cochez au moins un type de devoir.');
    return $clean;
}

/** Cours de l'ecole qui ont au moins un enseignant : [COURSE_ID => [titre, [enseignants]]]. */
function SchoolSafeTypeCourses(int $school, int $year, callable $get): array
{
    $courses = [];
    foreach ((array) $get("SELECT DISTINCT cp.COURSE_ID,c.TITLE,cp.TEACHER_ID FROM course_periods cp JOIN courses c ON c.COURSE_ID=cp.COURSE_ID"
        . " WHERE cp.SCHOOL_ID=$school AND cp.SYEAR=$year AND cp.TEACHER_ID IS NOT NULL ORDER BY c.TITLE") as $r) {
        $id = (int) $r['COURSE_ID'];
        if (!isset($courses[$id])) $courses[$id] = [SchoolSafeYearSetupTitle((string) $r['TITLE']), []];
        $courses[$id][1][] = (int) $r['TEACHER_ID'];
    }
    return $courses;
}

/**
 * Cree les types pour chaque cours choisi et chacun de ses enseignants ; un type deja present
 * (meme cours, meme enseignant, meme nom) n'est jamais double. Une seule transaction.
 */
function SchoolSafeTypeApply(int $school, int $year, int $mp, int $actor, array $types, array $courseIds, callable $get, callable $exec): array
{
    $courses = SchoolSafeTypeCourses($school, $year, $get);
    $chosen = array_values(array_filter(array_map('intval', $courseIds), static function ($id) use ($courses) { return isset($courses[$id]); }));
    if (!$chosen) throw new RuntimeException('Choisissez au moins un cours qui a un enseignant.');
    $q = static function ($v) { return "'" . str_replace("'", "''", (string) $v) . "'"; };
    $colors = ['homework' => '#2e7d32', 'quiz' => '#1565c0', 'exam' => '#c62828', '' => '#6d4c41'];
    $created = 0; $kept = 0;
    $exec('BEGIN');
    try {
        foreach ($chosen as $courseId) {
            foreach (array_unique($courses[$courseId][1]) as $teacher) {
                foreach ($types as $i => $t) {
                    $exists = $get("SELECT ASSIGNMENT_TYPE_ID AS ID FROM gradebook_assignment_types WHERE COURSE_ID=$courseId AND STAFF_ID=$teacher AND LOWER(TRIM(TITLE))=LOWER(TRIM(" . $q($t[0]) . '))');
                    if (is_array($exists) && isset($exists[1])) { $id = (int) $exists[1]['ID']; $kept++; }
                    else {
                        $new = $get("INSERT INTO gradebook_assignment_types(STAFF_ID,COURSE_ID,TITLE,FINAL_GRADE_PERCENT,SORT_ORDER,COLOR,CREATED_MP) VALUES($teacher,$courseId," . $q($t[0]) . ','
                            . ($t[2] === null ? 'NULL' : (float) $t[2] / 100) . ',' . ($i + 1) . ',' . $q($colors[$t[1]]) . ',' . ($mp > 0 ? $mp : 'NULL') . ') RETURNING ASSIGNMENT_TYPE_ID AS ID');
                        $id = (int) $new[1]['ID']; $created++;
                    }
                    if ($t[1] !== '') $exec('INSERT INTO schoolsafe_remediation_types(ASSIGNMENT_TYPE_ID,KIND,CONFIGURED_BY) VALUES(' . $id . ',' . $q($t[1]) . ',' . ($actor > 0 ? $actor : 'NULL') . ') ON CONFLICT (ASSIGNMENT_TYPE_ID) DO NOTHING');
                }
            }
        }
        $exec('COMMIT');
        return ['created' => $created, 'kept' => $kept, 'courses' => count($chosen)];
    } catch (Throwable $failure) {
        $exec('ROLLBACK');
        throw $failure;
    }
}
