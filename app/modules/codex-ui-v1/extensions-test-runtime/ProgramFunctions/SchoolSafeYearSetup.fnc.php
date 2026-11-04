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
    $steps[] = ['id' => 'baremes', 'title' => 'Barèmes de notation', 'url' => 'Modules.php?modname=Grades/ReportCardGrades.php',
        'state' => !$scales ? 'todo' : ($enScales ? 'warn' : 'ok'),
        'detail' => !$scales ? 'Aucun barème.' : count($scales) . ' barème(s) : ' . implode(', ', array_column($scales, 'TITLE')) . ($enScales ? ' (nom anglais à traduire).' : '.'),
        'help' => 'Échelle des notes (sur 10, sur 20, en pourcentage) et mentions. Les bulletins et le rattrapage (moins de 50 %) l’utilisent.'];

    // 8. Types de devoirs
    $types = $all("SELECT DISTINCT TRIM(TITLE) AS TITLE FROM gradebook_assignment_types WHERE COURSE_ID IN (SELECT COURSE_ID FROM course_periods WHERE SCHOOL_ID=$s AND SYEAR=$y) ORDER BY 1");
    $steps[] = ['id' => 'types', 'title' => 'Types de devoirs', 'url' => 'Modules.php?modname=Grades/MassCreateAssignments.php&assignment_type=new',
        'state' => $types ? 'ok' : 'todo',
        'detail' => $types ? count($types) . ' type(s) : ' . implode(', ', array_column($types, 'TITLE')) . '.' : 'Aucun type : aucun devoir ne peut être créé.',
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
