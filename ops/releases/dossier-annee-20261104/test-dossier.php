<?php
// Dossier de l'annee (04/11). Partie 1 sans base ; partie 2 sur la base reelle dans une transaction annulee
// (un devoir, une note, un corrige et une copie d'essai), avec un PDF garde dans /out pour verification.
// Usage : php test-dossier.php /var/www/html [--db]
$root = $argv[1] ?? '/var/www/html';
chdir($root);
require_once $root . '/ProgramFunctions/SchoolSafePortfolio.fnc.php';
require_once $root . '/ProgramFunctions/SchoolSafeGradeNotice.fnc.php';
require_once $root . '/ProgramFunctions/SchoolSafeLetterhead.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }

$w = static function ($id, $course, $term, $sort, $due, $pts, $max) {
    return ['ASSIGNMENT_ID' => (string) $id, 'TITLE' => 'T' . $id, 'COURSE_TITLE' => $course, 'TERM_TITLE' => $term, 'TERM_SORT' => (string) $sort, 'DUE_DATE' => $due, 'ASSIGNED_DATE' => $due,
        'POINTS' => $pts, 'MAXIMUM' => (string) $max, 'TYPE_TITLE' => 'Devoir', 'COMMENT' => '', 'HAS_COPY' => null];
};
$works = [$w(3, '|fr_FR.utf8:Mathématiques', '2e trimestre', 2, '2027-01-10', '9', 10), $w(1, '|fr_FR.utf8:Mathématiques', '1er trimestre', 1, '2026-10-01', '4', 10),
    $w(2, 'Français', '1er trimestre', 1, '2026-10-02', '18', 20), $w(4, 'Français', '1er trimestre', 1, '2026-10-05', null, 20), $w(5, 'Français', '1er trimestre', 1, '2026-10-06', '-1', 20)];
$g = SchoolSafePortfolioGroup($works);
check('trimestres dans l ordre', array_keys($g), ['1er trimestre', '2e trimestre']);
check('matieres du 1er trimestre', array_keys($g['1er trimestre']), ['Français', 'Mathématiques']);
check('travaux par date', array_map(static function ($x) { return $x['ASSIGNMENT_ID']; }, $g['1er trimestre']['Français']), ['2', '4', '5']);
$s = SchoolSafePortfolioSummary($works, 50);
check('maths : moyenne et progression', [$s['courses']['Mathématiques']['average'], $s['courses']['Mathématiques']['direction']], [65.0, 'hausse']);
check('francais : dispense et non note exclus', [$s['courses']['Français']['works'], $s['courses']['Français']['graded'], $s['courses']['Français']['average']], [3, 1, 90.0]);
check('points forts', $s['strong'], ['Français']);
check('moyenne des matieres', $s['average'], 77.5);
check('pourcentage', [SchoolSafePortfolioPercent('5', '20'), SchoolSafePortfolioPercent('-1', '20'), SchoolSafePortfolioPercent('3', '0')], [25.0, null, null]);
check('courbe SVG', strpos(SchoolSafePortfolioSpark([40, 90]), '<polyline') !== false, true);
check('pas de courbe pour une note', SchoolSafePortfolioSpark([40]), '');
check('annee scolaire', SchoolSafePortfolioYearLabel(2026), '2026-2027');
foreach (['dossier-annee.php', 'note-devoir.php', 'travail-enfant.php', 'central-navigation.php', 'ProgramFunctions/SchoolSafePortfolio.fnc.php'] as $f) {
    $out = []; exec('php -l ' . escapeshellarg($root . '/' . $f) . ' 2>&1', $out, $code); check('syntaxe ' . $f, $code, 0);
}

if (in_array('--db', $argv, true)) {
    require_once $root . '/schoolsafe-notify-cli.php';
    require_once $root . '/ProgramFunctions/SchoolSafeSchoolDocument.fnc.php';
    $conn = pg_connect('host=' . (getenv('SCHOOLSAFE_DB_HOST') ?: 'db') . ' port=' . (getenv('SCHOOLSAFE_DB_PORT') ?: '5432') . ' dbname=' . getenv('POSTGRES_DB') . ' user=' . getenv('POSTGRES_USER') . ' password=' . getenv('POSTGRES_PASSWORD') . ' connect_timeout=5');
    SchoolSafeNotifyCliInit($conn);
    $get = function ($q) { return DBGet($q); };
    DBQuery('BEGIN');
    try {
        $a = (int) DBGet("INSERT INTO gradebook_assignments(ASSIGNMENT_TYPE_ID,STAFF_ID,MARKING_PERIOD_ID,COURSE_PERIOD_ID,TITLE,ASSIGNED_DATE,DUE_DATE,POINTS) VALUES(10,12,19,4,'Essai dossier','2026-11-03','2026-11-04',10) RETURNING ASSIGNMENT_ID")[1]['ASSIGNMENT_ID'];
        DBQuery("INSERT INTO gradebook_grades(STUDENT_ID,COURSE_PERIOD_ID,ASSIGNMENT_ID,POINTS,COMMENT) VALUES(4,4," . $a . ",4,'Revois les fractions')");
        DBQuery("INSERT INTO schoolsafe_assignment_keys(ASSIGNMENT_ID,ANSWER,STAFF_ID) VALUES(" . $a . ",'Exercice 1 : 3/4 + 1/4 = 1',12)");
        $img = imagecreatetruecolor(600, 800); imagefill($img, 0, 0, imagecolorallocate($img, 250, 250, 245)); imagestring($img, 5, 40, 40, 'Copie corrigee - essai', imagecolorallocate($img, 200, 0, 0));
        ob_start(); imagejpeg($img, null, 80); $jpg = (string) ob_get_clean();
        DBQuery("INSERT INTO schoolsafe_grade_copies(ASSIGNMENT_ID,STUDENT_ID,MIME,SIZE_BYTES,SHA256,CONTENT,CREATED_BY) VALUES(" . $a . ",4,'image/jpeg'," . strlen($jpg) . ",'" . hash('sha256', $jpg) . "',decode('" . base64_encode($jpg) . "','base64'),12)");
        $years = SchoolSafePortfolioYears(4, 3, $get);
        check('annees de l eleve', in_array(2026, $years, true), true);
        $real = SchoolSafePortfolioWorks(4, 3, 2026, '2026-11-05', $get);
        $mine = array_values(array_filter($real, static function ($r) use ($a) { return (int) $r['ASSIGNMENT_ID'] === $a; }));
        check('travail d essai dans le dossier', count($mine), 1);
        check('trimestre, copie et corrige', [$mine[0]['TERM_TITLE'] ?? '', (bool) $mine[0]['HAS_COPY'], (bool) $mine[0]['HAS_KEY']], ['1er trimestre', true, true]);
        check('presences lisibles', array_keys(SchoolSafePortfolioAttendance(4, 3, 2026, $get)), ['Absent', 'Absence excusée', 'Retard']);
        $summary = SchoolSafePortfolioSummary($real, 50);
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $t = fn($v) => SchoolSafePortfolioTitle((string) $v);
        $pct = fn($v) => $v === null ? '—' : str_replace('.', ',', (string) $v) . ' %';
        try { $identity = SchoolSafeLetterheadIdentity(3); } catch (Throwable $x) { $identity = ['name' => 'Complexe scolaire le sage', 'layout' => 'classique']; }
        $html = SchoolSafePortfolioPdfHtml(['e' => $e, 't' => $t, 'pct' => $pct, 'photo' => '', 'name' => 'Josué (essai)', 'classTitle' => '1ère Primaire', 'yearLabel' => '2026-2027',
            'schoolName' => 'Complexe scolaire le sage', 'works' => $real, 'graded' => 1, 'summary' => $summary, 'bilan' => '<p>Bilan</p>', 'strengths' => '', 'presenceText' => 'Absences : 0',
            'groups' => SchoolSafePortfolioGroup($real), 'answers' => [$a => 'Exercice 1 : 3/4 + 1/4 = 1'], 'copies' => [$a => ['mime' => 'image/jpeg', 'b64' => base64_encode($jpg)]],
            'threshold' => 50.0, 'today' => '2026-11-05', 'identity' => $identity]);
        check('PDF : corrige et copie presents', strpos($html, 'Corrigé :') !== false && strpos($html, 'Copies corrigées') !== false, true);
        $pdf = SchoolSafeDocumentPdf($html);
        check('PDF produit', substr($pdf, 0, 5), '%PDF-');
        if (is_dir('/out')) file_put_contents('/out/dossier.pdf', $pdf);
    } catch (Throwable $x) { $ko++; echo 'KO   exception : ' . $x->getMessage() . "\n"; }
    finally { DBQuery('ROLLBACK'); }
    check('rien n est reste en base', (int) DBGet("SELECT count(*) AS N FROM gradebook_assignments WHERE TITLE='Essai dossier'")[1]['N'], 0);
}
echo "\n$ok OK, $ko KO\n";
exit($ko ? 1 : 0);
