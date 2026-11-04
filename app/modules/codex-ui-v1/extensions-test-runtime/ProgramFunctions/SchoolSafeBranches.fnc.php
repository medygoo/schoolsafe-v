<?php
// Branches et maxima du bulletin RDC (04/11, etape B validee par le proprietaire).
// Chaque niveau a sa liste de branches (domaine, branche, maxima de periode et d'examen), modifiable par
// l'ecole. Appliquee a une classe, chaque branche devient un cours natif de la classe : le titulaire en est
// l'enseignant (francais), le second enseignant (anglais) l'enseignant secondaire ; chacun cree ses devoirs
// et donne ses notes. Les eleves de la classe y sont inscrits ; l'appel reste sur la classe.
// Rien n'est jamais efface : une branche retiree de la liste est seulement desactivee.

/** Listes proposees, a confirmer avec un ancien bulletin de l'ecole : [domaine, branche, abrege, maxima de periode]. */
function SchoolSafeBranchDefaults(string $kind): array
{
    if ($kind === 'humanites') return [
        ['Langues', 'Français', 'FRA', 40], ['Langues', 'Anglais', 'ANG', 20],
        ['Sciences', 'Mathématiques', 'MATH', 40], ['Sciences', 'Physique', 'PHY', 20], ['Sciences', 'Chimie', 'CHIM', 20], ['Sciences', 'Biologie', 'BIO', 20], ['Sciences', 'Informatique', 'INFO', 10],
        ['Sciences humaines', 'Histoire', 'HIST', 20], ['Sciences humaines', 'Géographie', 'GEO', 20],
        ['Formation générale', 'Éducation civique et morale', 'ECM', 10], ['Formation générale', 'Religion', 'REL', 10], ['Formation générale', 'Éducation à la vie', 'EDV', 10],
        ['Formation générale', 'Éducation physique', 'EPS', 10],
    ];
    return [
        ['Langues', 'Expression orale', 'EXPO', 10], ['Langues', 'Lecture', 'LECT', 10], ['Langues', 'Écriture', 'ECR', 10], ['Langues', 'Grammaire et orthographe', 'GRAM', 10],
        ['Langues', 'Langue nationale', 'LNAT', 10],
        ['Mathématiques', 'Numération et opérations', 'NUM', 20], ['Mathématiques', 'Grandeurs et mesures', 'GRAND', 10], ['Mathématiques', 'Formes géométriques', 'GEOM', 10],
        ['Mathématiques', 'Problèmes', 'PROB', 10],
        ['Sciences et technologie', 'Sciences d’éveil', 'EVEIL', 10], ['Sciences et technologie', 'Technologie et informatique', 'TECH', 10],
        ['Univers social', 'Éducation civique et morale', 'ECM', 10], ['Univers social', 'Géographie', 'GEO', 10], ['Univers social', 'Histoire', 'HIST', 10],
        ['Univers social', 'Éducation à la vie', 'EDV', 10],
        ['Arts', 'Dessin', 'DES', 10], ['Arts', 'Chant et musique', 'CHANT', 10], ['Arts', 'Travail manuel', 'TM', 10],
        ['Développement personnel', 'Éducation physique', 'EPS', 10], ['Développement personnel', 'Religion', 'REL', 10],
    ];
}

/** Maxima d'examen : celui choisi, sinon le double de la periode (usage RDC). */
function SchoolSafeBranchExamMax($maxPeriod, $maxExam): float
{
    return $maxExam !== null && $maxExam !== '' && (float) $maxExam > 0 ? (float) $maxExam : 2 * (float) $maxPeriod;
}

/** Lignes du formulaire -> lignes valides ; erreur claire sinon. Les lignes sans branche sont ignorees. */
function SchoolSafeBranchValidateRows(array $post): array
{
    $field = static function ($name) use ($post) { $v = $post[$name] ?? []; return is_array($v) ? array_values($v) : []; };
    $ids = $field('b_id'); $domains = $field('b_domain'); $titles = $field('b_title'); $shorts = $field('b_short'); $maxes = $field('b_max'); $exams = $field('b_exam'); $active = $field('b_off');
    $off = []; foreach ($active as $i) if (is_string($i) && ctype_digit($i)) $off[(int) $i] = true;
    $rows = []; $seen = [];
    foreach ($titles as $i => $title) {
        if (!is_string($title)) throw new RuntimeException('Formulaire invalide.');
        $title = trim(preg_replace('/\s+/u', ' ', $title));
        if ($title === '') continue;
        if (mb_strlen($title) > 80) throw new RuntimeException('Branche trop longue : ' . mb_substr($title, 0, 30) . '…');
        $key = mb_strtolower($title);
        if (isset($seen[$key])) throw new RuntimeException('Branche en double : ' . $title . '.');
        $seen[$key] = true;
        $domain = trim((string) ($domains[$i] ?? ''));
        if (mb_strlen($domain) > 80) throw new RuntimeException('Domaine trop long pour ' . $title . '.');
        $short = trim((string) ($shorts[$i] ?? ''));
        if ($short === '') $short = mb_strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]+/u', '', $title), 0, 6));
        if (mb_strlen($short) > 20) throw new RuntimeException('Abrégé trop long pour ' . $title . ' (20 caractères au plus).');
        $max = str_replace(',', '.', trim((string) ($maxes[$i] ?? '')));
        if (!preg_match('/^\d{1,4}(\.\d{1,2})?$/', $max) || (float) $max <= 0 || (float) $max > 1000) throw new RuntimeException('Maxima de période invalide pour ' . $title . '.');
        $exam = str_replace(',', '.', trim((string) ($exams[$i] ?? '')));
        if ($exam !== '' && (!preg_match('/^\d{1,4}(\.\d{1,2})?$/', $exam) || (float) $exam <= 0 || (float) $exam > 2000)) throw new RuntimeException('Maxima d’examen invalide pour ' . $title . '.');
        $id = isset($ids[$i]) && is_string($ids[$i]) && ctype_digit($ids[$i]) ? (int) $ids[$i] : 0;
        $rows[] = ['id' => $id, 'domain' => $domain, 'title' => $title, 'short' => $short, 'max' => $max, 'exam' => $exam === '' ? null : $exam, 'active' => !isset($off[$i]), 'sort' => count($rows) + 1];
    }
    if (count($rows) > 60) throw new RuntimeException('60 branches au plus par niveau.');
    return $rows;
}

function SchoolSafeBranchModels(int $school, int $level, callable $get, bool $activeOnly = false): array
{
    if ($school < 1 || $level < 1) return [];
    return (array) $get("SELECT ID,DOMAIN,TITLE,SHORT_NAME,MAX_PERIOD,MAX_EXAM,ACTIVE,SORT_ORDER FROM schoolsafe_branch_models WHERE SCHOOL_ID=" . $school . " AND GRADE_LEVEL_ID=" . $level
        . ($activeOnly ? " AND ACTIVE" : '') . " ORDER BY SORT_ORDER,ID");
}

/** Enregistre la liste d'un niveau ; les branches absentes du formulaire sont desactivees, jamais effacees. */
function SchoolSafeBranchSave(int $school, int $level, array $rows, int $staff, array $db): int
{
    $q = static function ($v) { return "'" . DBEscapeString((string) $v) . "'"; };
    $existing = [];
    foreach (SchoolSafeBranchModels($school, $level, $db['get']) as $m) $existing[(int) $m['ID']] = $m;
    $kept = [];
    foreach ($rows as $r) {
        $values = "DOMAIN=" . $q($r['domain']) . ",TITLE=" . $q($r['title']) . ",SHORT_NAME=" . $q($r['short']) . ",MAX_PERIOD=" . $r['max'] . ",MAX_EXAM=" . ($r['exam'] === null ? 'NULL' : $r['exam'])
            . ",ACTIVE=" . ($r['active'] ? 'true' : 'false') . ",SORT_ORDER=" . (int) $r['sort'] . ",UPDATED_BY=" . $staff . ",UPDATED_AT=now()";
        $id = $r['id'];
        if ($id && !isset($existing[$id])) $id = 0;
        if (!$id) foreach ($existing as $eid => $m) if (mb_strtolower((string) $m['TITLE']) === mb_strtolower($r['title'])) $id = $eid;
        if ($id) { $db['exec']("UPDATE schoolsafe_branch_models SET " . $values . " WHERE ID=" . $id . " AND SCHOOL_ID=" . $school); $kept[$id] = true; }
        else {
            $new = $db['get']("INSERT INTO schoolsafe_branch_models(SCHOOL_ID,GRADE_LEVEL_ID,DOMAIN,TITLE,SHORT_NAME,MAX_PERIOD,MAX_EXAM,ACTIVE,SORT_ORDER,UPDATED_BY) VALUES(" . $school . "," . $level . ","
                . $q($r['domain']) . "," . $q($r['title']) . "," . $q($r['short']) . "," . $r['max'] . "," . ($r['exam'] === null ? 'NULL' : $r['exam']) . "," . ($r['active'] ? 'true' : 'false') . "," . (int) $r['sort'] . "," . $staff . ") RETURNING ID");
            $kept[(int) $new[1]['ID']] = true;
        }
    }
    foreach ($existing as $eid => $m) if (!isset($kept[$eid])) $db['exec']("UPDATE schoolsafe_branch_models SET ACTIVE=false,UPDATED_BY=" . $staff . ",UPDATED_AT=now() WHERE ID=" . $eid);
    return count($rows);
}

/** Cours natifs qui sont des branches (ecole, annee) : exclus de l'appel et du cahier de textes par classe. */
function SchoolSafeBranchCoursePeriods(int $school, int $year, callable $get): array
{
    $ids = [];
    foreach ((array) $get("SELECT COURSE_PERIOD_ID FROM schoolsafe_class_branches WHERE SCHOOL_ID=" . $school . " AND SYEAR=" . $year) as $r) $ids[(int) $r['COURSE_PERIOD_ID']] = true;
    return $ids;
}

/** Classes de l'ecole (hors branches), avec niveau, titulaire, eleves et branches deja creees. */
function SchoolSafeBranchClasses(int $school, int $year, callable $get): array
{
    return (array) $get("SELECT cp.COURSE_PERIOD_ID,cp.TITLE,cp.TEACHER_ID,cp.SECONDARY_TEACHER_ID,c.TITLE AS COURSE_TITLE,c.GRADE_LEVEL,st.FIRST_NAME,st.LAST_NAME,"
        . "(SELECT count(*) FROM schedule sc WHERE sc.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID AND (sc.END_DATE IS NULL OR sc.END_DATE>=CURRENT_DATE)) AS STUDENTS,"
        . "(SELECT count(*) FROM schoolsafe_class_branches b WHERE b.CLASS_COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID) AS BRANCHES,"
        . "(SELECT max(b.TEACHER_EN) FROM schoolsafe_class_branches b WHERE b.CLASS_COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID) AS TEACHER_EN"
        . " FROM course_periods cp JOIN courses c ON c.COURSE_ID=cp.COURSE_ID LEFT JOIN staff st ON st.STAFF_ID=cp.TEACHER_ID"
        . " WHERE cp.SCHOOL_ID=" . $school . " AND cp.SYEAR=" . $year
        . " AND NOT EXISTS (SELECT 1 FROM schoolsafe_class_branches b WHERE b.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID) ORDER BY c.TITLE");
}

/** Enseignants de l'ecole pour l'annee. */
function SchoolSafeBranchTeachers(int $school, int $year, callable $get): array
{
    $out = [];
    foreach ((array) $get("SELECT STAFF_ID,FIRST_NAME,LAST_NAME FROM staff WHERE SYEAR=" . $year . " AND PROFILE='teacher' AND (SCHOOLS IS NULL OR SCHOOLS LIKE '%," . $school . ",%') ORDER BY LAST_NAME,FIRST_NAME") as $r)
        $out[(int) $r['STAFF_ID']] = trim($r['FIRST_NAME'] . ' ' . $r['LAST_NAME']);
    return $out;
}

/** Ce que l'application va faire pour une classe (aperçu) : branches a creer, branches deja en place. */
function SchoolSafeBranchPlan(array $models, array $existingModelIds): array
{
    $create = []; $keep = [];
    foreach ($models as $m) {
        if (!in_array((string) $m['ACTIVE'], ['t', '1', 'true', 'Y'], true) && $m['ACTIVE'] !== true) continue;
        if (isset($existingModelIds[(int) $m['ID']])) $keep[] = $m; else $create[] = $m;
    }
    return ['create' => $create, 'keep' => $keep];
}

/**
 * Applique la liste d'un niveau a une classe (dans une transaction ouverte par l'appelant) : cree les cours
 * natifs manquants, met a jour les enseignants, inscrit les eleves de la classe, cree les types de devoirs
 * de chaque enseignant (relies au rattrapage comme ceux de la classe). Rejouable sans doublon.
 */
function SchoolSafeBranchApply(int $school, int $year, int $classCp, int $level, int $teacherFr, int $teacherEn, int $staff, array $db): array
{
    $get = $db['get']; $exec = $db['exec'];
    $q = static function ($v) { return "'" . DBEscapeString((string) $v) . "'"; };
    $main = $get("SELECT cp.*,c.GRADE_LEVEL AS COURSE_LEVEL,c.COURSE_ID AS MAIN_COURSE FROM course_periods cp JOIN courses c ON c.COURSE_ID=cp.COURSE_ID WHERE cp.COURSE_PERIOD_ID=" . $classCp
        . " AND cp.SCHOOL_ID=" . $school . " AND cp.SYEAR=" . $year . " AND NOT EXISTS (SELECT 1 FROM schoolsafe_class_branches b WHERE b.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID)");
    if (!$main) throw new RuntimeException('Classe introuvable.');
    $main = $main[1];
    $teachers = SchoolSafeBranchTeachers($school, $year, $get);
    if (!isset($teachers[$teacherFr])) throw new RuntimeException('Choisissez le titulaire de la classe.');
    if ($teacherEn && (!isset($teachers[$teacherEn]) || $teacherEn === $teacherFr)) throw new RuntimeException('Le professeur d’anglais doit être un autre enseignant de l’école.');
    $models = SchoolSafeBranchModels($school, $level, $get, true);
    if (!$models) throw new RuntimeException('Aucune branche active pour ce niveau : enregistrez d’abord la liste.');
    $existing = [];
    foreach ((array) $get("SELECT MODEL_ID,COURSE_ID,COURSE_PERIOD_ID FROM schoolsafe_class_branches WHERE CLASS_COURSE_PERIOD_ID=" . $classCp) as $r) $existing[(int) $r['MODEL_ID']] = $r;
    $plan = SchoolSafeBranchPlan($models, $existing);
    $mainTypes = (array) $get("SELECT t.TITLE,t.FINAL_GRADE_PERCENT,t.SORT_ORDER,t.COLOR,t.CREATED_MP,m.KIND FROM gradebook_assignment_types t LEFT JOIN schoolsafe_remediation_types m ON m.ASSIGNMENT_TYPE_ID=t.ASSIGNMENT_TYPE_ID"
        . " WHERE t.COURSE_ID=" . (int) $main['MAIN_COURSE'] . " AND t.STAFF_ID=" . (int) $main['TEACHER_ID'] . " ORDER BY t.SORT_ORDER");
    if (!$mainTypes) $mainTypes = [['TITLE' => 'Devoir', 'FINAL_GRADE_PERCENT' => null, 'SORT_ORDER' => 1, 'COLOR' => null, 'CREATED_MP' => null, 'KIND' => null],
        ['TITLE' => 'Interrogation', 'FINAL_GRADE_PERCENT' => null, 'SORT_ORDER' => 2, 'COLOR' => null, 'CREATED_MP' => null, 'KIND' => null],
        ['TITLE' => 'Examen', 'FINAL_GRADE_PERCENT' => null, 'SORT_ORDER' => 3, 'COLOR' => null, 'CREATED_MP' => null, 'KIND' => null]];
    $ensureTypes = static function (int $course, int $teacher) use ($get, $exec, $q, $mainTypes, $staff) {
        if ($teacher < 1 || $get("SELECT 1 FROM gradebook_assignment_types WHERE COURSE_ID=" . $course . " AND STAFF_ID=" . $teacher . " LIMIT 1")) return;
        foreach ($mainTypes as $t) {
            $id = $get("INSERT INTO gradebook_assignment_types(STAFF_ID,COURSE_ID,TITLE,FINAL_GRADE_PERCENT,SORT_ORDER,COLOR,CREATED_MP) VALUES(" . $teacher . "," . $course . "," . $q($t['TITLE']) . ","
                . ($t['FINAL_GRADE_PERCENT'] === null || $t['FINAL_GRADE_PERCENT'] === '' ? 'NULL' : (float) $t['FINAL_GRADE_PERCENT']) . "," . (int) $t['SORT_ORDER'] . ","
                . ($t['COLOR'] ? $q($t['COLOR']) : 'NULL') . "," . ($t['CREATED_MP'] ? (int) $t['CREATED_MP'] : 'NULL') . ") RETURNING ASSIGNMENT_TYPE_ID");
            if (!empty($t['KIND'])) $exec("INSERT INTO schoolsafe_remediation_types(ASSIGNMENT_TYPE_ID,KIND,CONFIGURED_BY) VALUES(" . (int) $id[1]['ASSIGNMENT_TYPE_ID'] . "," . $q($t['KIND']) . "," . $staff . ")");
        }
    };
    $subjects = [];
    $created = 0;
    foreach ($plan['create'] as $m) {
        $domain = trim((string) $m['DOMAIN']) ?: 'Branches';
        if (!isset($subjects[$domain])) {
            $s = $get("SELECT SUBJECT_ID FROM course_subjects WHERE SCHOOL_ID=" . $school . " AND SYEAR=" . $year . " AND TITLE=" . $q('|fr_FR.utf8:' . $domain) . " LIMIT 1");
            $subjects[$domain] = $s ? (int) $s[1]['SUBJECT_ID'] : (int) $get("INSERT INTO course_subjects(SYEAR,SCHOOL_ID,TITLE,SORT_ORDER) VALUES(" . $year . "," . $school . "," . $q('|fr_FR.utf8:' . $domain) . ",20) RETURNING SUBJECT_ID")[1]['SUBJECT_ID'];
        }
        $title = (string) $m['TITLE'];
        $course = (int) $get("INSERT INTO courses(SYEAR,SUBJECT_ID,SCHOOL_ID,GRADE_LEVEL,TITLE,SHORT_NAME) VALUES(" . $year . "," . $subjects[$domain] . "," . $school . "," . ($main['COURSE_LEVEL'] ? (int) $main['COURSE_LEVEL'] : $level) . ","
            . $q('|fr_FR.utf8:' . $title . '|en_US.utf8:' . $title) . "," . $q(mb_substr((string) $m['SHORT_NAME'], 0, 20)) . ") RETURNING COURSE_ID")[1]['COURSE_ID'];
        $cp = (int) $get("INSERT INTO course_periods(SYEAR,SCHOOL_ID,COURSE_ID,TITLE,SHORT_NAME,MP,MARKING_PERIOD_ID,TEACHER_ID,SECONDARY_TEACHER_ID,TOTAL_SEATS,FILLED_SEATS,GENDER_RESTRICTION,CALENDAR_ID,GRADE_SCALE_ID,CREDITS)"
            . " SELECT SYEAR,SCHOOL_ID," . $course . "," . $q(mb_substr($title . ' - ' . $teachers[$teacherFr], 0, 200)) . "," . $q(mb_substr((string) $m['SHORT_NAME'], 0, 20)) . ",MP,MARKING_PERIOD_ID," . $teacherFr . "," . ($teacherEn ?: 'NULL')
            . ",TOTAL_SEATS,0,GENDER_RESTRICTION,CALENDAR_ID,GRADE_SCALE_ID,CREDITS FROM course_periods WHERE COURSE_PERIOD_ID=" . $classCp . " RETURNING COURSE_PERIOD_ID")[1]['COURSE_PERIOD_ID'];
        $exec("UPDATE course_periods SET PARENT_ID=" . $cp . " WHERE COURSE_PERIOD_ID=" . $cp);
        $exec("INSERT INTO course_period_school_periods(COURSE_PERIOD_ID,PERIOD_ID,DAYS) SELECT " . $cp . ",PERIOD_ID,DAYS FROM course_period_school_periods WHERE COURSE_PERIOD_ID=" . $classCp);
        $exec("INSERT INTO schoolsafe_class_branches(SCHOOL_ID,SYEAR,CLASS_COURSE_PERIOD_ID,MODEL_ID,COURSE_ID,COURSE_PERIOD_ID,TEACHER_FR,TEACHER_EN,CREATED_BY) VALUES("
            . $school . "," . $year . "," . $classCp . "," . (int) $m['ID'] . "," . $course . "," . $cp . "," . $teacherFr . "," . ($teacherEn ?: 'NULL') . "," . $staff . ")");
        $existing[(int) $m['ID']] = ['COURSE_ID' => $course, 'COURSE_PERIOD_ID' => $cp];
        $created++;
    }
    $students = 0;
    foreach ($existing as $b) {
        $course = (int) $b['COURSE_ID']; $cp = (int) $b['COURSE_PERIOD_ID'];
        $exec("UPDATE course_periods SET TEACHER_ID=" . $teacherFr . ",SECONDARY_TEACHER_ID=" . ($teacherEn ?: 'NULL') . " WHERE COURSE_PERIOD_ID=" . $cp);
        $exec("UPDATE schoolsafe_class_branches SET TEACHER_FR=" . $teacherFr . ",TEACHER_EN=" . ($teacherEn ?: 'NULL') . " WHERE COURSE_PERIOD_ID=" . $cp);
        $ensureTypes($course, $teacherFr);
        $ensureTypes($course, $teacherEn);
        $added = $get("INSERT INTO schedule(SYEAR,SCHOOL_ID,STUDENT_ID,START_DATE,COURSE_ID,COURSE_PERIOD_ID,MP,MARKING_PERIOD_ID)"
            . " SELECT sc.SYEAR,sc.SCHOOL_ID,sc.STUDENT_ID,sc.START_DATE," . $course . "," . $cp . ",sc.MP,sc.MARKING_PERIOD_ID FROM schedule sc WHERE sc.COURSE_PERIOD_ID=" . $classCp
            . " AND (sc.END_DATE IS NULL OR sc.END_DATE>=CURRENT_DATE)"
            . " AND NOT EXISTS (SELECT 1 FROM schedule x WHERE x.COURSE_PERIOD_ID=" . $cp . " AND x.STUDENT_ID=sc.STUDENT_ID AND (x.END_DATE IS NULL OR x.END_DATE>=CURRENT_DATE)) RETURNING STUDENT_ID");
        $students += is_array($added) ? count($added) : 0;
        $exec("UPDATE course_periods SET FILLED_SEATS=(SELECT count(*) FROM schedule WHERE COURSE_PERIOD_ID=" . $cp . " AND (END_DATE IS NULL OR END_DATE>=CURRENT_DATE)) WHERE COURSE_PERIOD_ID=" . $cp);
    }
    return ['created' => $created, 'kept' => count($plan['keep']), 'enrolled' => $students];
}
