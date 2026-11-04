<?php
// Bulletin RDC (04/11). Partie 1 sans base ; partie 2 (--db) dans une transaction ANNULEE : branches de la
// 1ere Primaire, notes du 1er trimestre, validation, regle des frais, ouverture exceptionnelle, PDF garde dans /out.
$root = $argv[1] ?? '/var/www/html';
chdir($root);
$dbMode = in_array('--db', $argv, true);
if ($dbMode) {
    require_once $root . '/schoolsafe-notify-cli.php';
    $conn = pg_connect('host=' . (getenv('SCHOOLSAFE_DB_HOST') ?: 'db') . ' port=' . (getenv('SCHOOLSAFE_DB_PORT') ?: '5432') . ' dbname=' . getenv('POSTGRES_DB') . ' user=' . getenv('POSTGRES_USER') . ' password=' . getenv('POSTGRES_PASSWORD') . ' connect_timeout=5');
    SchoolSafeNotifyCliInit($conn);
} else { function DBEscapeString($v) { return str_replace("'", "''", (string) $v); } }
require_once $root . '/ProgramFunctions/SchoolSafeBulletinDoc.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }

$g = SchoolSafeBulletinGroups([['TITLE' => 'Français', 'MAX_PERIOD' => '20'], ['TITLE' => 'Dessin', 'MAX_PERIOD' => '10'], ['TITLE' => 'Calcul', 'MAX_PERIOD' => '20'], ['TITLE' => 'Chant', 'MAX_PERIOD' => '10']]);
check('groupes par maxima croissants', array_map('strval', array_keys($g)), ['10', '20']);
check('ordre conserve dans un groupe', array_column($g['20'], 'TITLE'), ['Français', 'Calcul']);
$m = SchoolSafeBulletinNotice('Josué', '1er trimestre', 'https://x/bulletin.php?eleve=4', 1500000, true, 'Le sage');
check('avis : frais a regulariser', strpos($m[1], 'dès la régularisation des frais') !== false && strpos($m[1], 'https://') === false, true);
check('avis : bulletin disponible', strpos(SchoolSafeBulletinNotice('Josué', '1er trimestre', 'https://x/b', 0, true, 'Le sage')[1], 'est disponible : https://x/b') !== false, true);
foreach (['bulletin.php', 'synthese-bulletin.php', 'travail-enfant.php', 'dossier-annee.php', 'ProgramFunctions/SchoolSafeBulletinDoc.fnc.php'] as $f) {
    $out = []; exec('php -l ' . escapeshellarg($root . '/' . $f) . ' 2>&1', $out, $code); check('syntaxe ' . $f, $code, 0);
}

if ($dbMode) {
    require_once $root . '/ProgramFunctions/SchoolSafeSchoolDocument.fnc.php';
    $get = function ($q) { return DBGet($q); };
    $db = ['get' => $get, 'exec' => function ($q) { DBQuery($q); }];
    DBQuery('BEGIN');
    try {
        if (!DBGet("SELECT to_regclass('public.schoolsafe_bulletin_unlocks') AS T")[1]['T'])
            pg_query($conn, preg_replace('/^(BEGIN|COMMIT);\s*$/m', '', file_get_contents($root . '/schoolsafe-remediation/066-bulletin-unlocks.sql'))) or throw new RuntimeException('migration 066');
        $defaults = SchoolSafeBranchDefaults('primaire');
        SchoolSafeBranchSave(3, 20, SchoolSafeBranchValidateRows(['b_domain' => array_column($defaults, 0), 'b_title' => array_column($defaults, 1), 'b_short' => array_column($defaults, 2), 'b_max' => array_map('strval', array_column($defaults, 3))]), 6, $db);
        SchoolSafeBranchApply(3, 2026, 4, 20, 12, 0, 6, $db);
        $br = SchoolSafeBulletinBranches(4, $get);
        $i = 0;
        foreach ($br as $b) {
            $i++;
            $max = (float) $b['MAX_PERIOD'];
            foreach ([[18, 'periode', 0.6 + ($i % 4) * 0.1], [19, 'periode', 0.7], [16, 'examen', 0.65]] as [$mp, $kind, $ratio]) {
                $pts = round(($kind === 'examen' ? 2 * $max : $max) * $ratio, 1);
                DBQuery("INSERT INTO schoolsafe_bulletin_marks(SCHOOL_ID,SYEAR,CLASS_COURSE_PERIOD_ID,BRANCH_COURSE_PERIOD_ID,STUDENT_ID,MARKING_PERIOD_ID,KIND,LANG,POINTS,ENTERED_BY) VALUES(3,2026,4," . (int) $b['COURSE_PERIOD_ID'] . ",4," . $mp . ",'" . $kind . "','fr'," . $pts . ",12)");
            }
        }
        check('avant validation : non publie', SchoolSafeBulletinAccess(4, 3, 2026, '2026-11-05', $get)['reason'], 'pending');
        DBQuery("INSERT INTO schoolsafe_bulletin_status(SCHOOL_ID,SYEAR,CLASS_COURSE_PERIOD_ID,STUDENT_ID,MARKING_PERIOD_ID,APPLICATION,CONDUITE,STATUS,UPDATED_BY,VALIDATED_BY,VALIDATED_AT) VALUES(3,2026,4,4,16,'Bien','Très bien','valide',6,6,now())");
        $fee = (int) DBGet("INSERT INTO billing_fees(SYEAR,SCHOOL_ID,STUDENT_ID,TITLE,AMOUNT,DUE_DATE,ASSIGNED_DATE) VALUES(2026,3,4,'Essai frais scolaires',50000,'2026-11-01','2026-11-01') RETURNING ID")[1]['ID'];
        $acc = SchoolSafeBulletinAccess(4, 3, 2026, '2026-11-05', $get);
        check('valide mais frais dus : ferme, reste calcule', [$acc['open'], $acc['reason'], $acc['remaining'] >= 5000000], [false, 'fees', true]);
        DBQuery("INSERT INTO config(SCHOOL_ID,TITLE,CONFIG_VALUE) VALUES(3,'SCHOOLSAFE_BULLETIN_FEES','N')");
        check('interrupteur coupe : ouvert', SchoolSafeBulletinAccess(4, 3, 2026, '2026-11-05', $get)['open'], true);
        DBQuery("UPDATE config SET CONFIG_VALUE='Y' WHERE SCHOOL_ID=3 AND TITLE='SCHOOLSAFE_BULLETIN_FEES'");
        DBQuery("INSERT INTO schoolsafe_bulletin_unlocks(SCHOOL_ID,SYEAR,CLASS_COURSE_PERIOD_ID,STUDENT_ID,MARKING_PERIOD_ID,REASON,UNLOCKED_BY) VALUES(3,2026,4,4,16,'Essai arrangement',6)");
        check('ouverture exceptionnelle', [SchoolSafeBulletinAccess(4, 3, 2026, '2026-11-05', $get)['open'], SchoolSafeBulletinAccess(4, 3, 2026, '2026-11-05', $get)['reason']], [true, 'unlocked']);
        $terms = SchoolSafeBulletinTerms(3, 2026, $get);
        $calc = SchoolSafeBulletinCompute($br, SchoolSafeBulletinStudents(4, $get), SchoolSafeBulletinMarks(4, $get), $terms, 'moyenne');
        $st = DBGet("SELECT FIRST_NAME,LAST_NAME,CUSTOM_200000000 AS SEXE,CUSTOM_200000004 AS NAISSANCE,CUSTOM_200000012 AS MATRICULE FROM students WHERE STUDENT_ID=4")[1];
        try { $identity = SchoolSafeLetterheadIdentity(3); } catch (Throwable $x) { $identity = ['school' => 'Complexe scolaire le sage', 'city' => 'Kinshasa', 'principal' => '']; }
        $html = SchoolSafeBulletinHtml(['identity' => $identity, 'student' => $st, 'class_title' => '1ère Primaire', 'year_label' => '2026-2027', 'branches' => $br, 'calc' => $calc,
            'terms' => $terms, 'status' => SchoolSafeBulletinStatus(4, $get)[4] ?? [], 'visible' => [16], 'fy' => 15, 'sid' => 4, 'count' => $calc['count'], 'draft' => false, 'titulaire' => 'Medy Lomela', 'today' => '2026-12-12']);
        check('bulletin : en-tete RDC, maxima, totaux, place', strpos($html, 'RÉPUBLIQUE DÉMOCRATIQUE DU CONGO') !== false && strpos($html, 'MAXIMA GÉNÉRAUX') !== false && strpos($html, 'PLACE / NBRE ÉLÈVES') !== false, true);
        $pdf = SchoolSafeDocumentPdf($html);
        check('PDF du bulletin produit', substr($pdf, 0, 5), '%PDF-');
        check('une seule page', preg_match_all('#/Type\s*/Page[^s]#', $pdf), 1);
        if (is_dir('/out')) file_put_contents('/out/bulletin.pdf', $pdf);
    } catch (Throwable $x) { $ko++; echo 'KO   exception : ' . $x->getMessage() . "\n"; }
    finally { DBQuery('ROLLBACK'); }
    check('rien n est reste en base', [(int) DBGet("SELECT count(*) AS N FROM billing_fees WHERE TITLE='Essai frais scolaires'")[1]['N'], (int) DBGet("SELECT count(*) AS N FROM schoolsafe_class_branches")[1]['N']], [0, 0]);
}
echo "\n$ok OK, $ko KO\n";
exit($ko ? 1 : 0);
