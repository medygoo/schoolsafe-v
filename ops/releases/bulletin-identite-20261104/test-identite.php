<?php
// Lieu de naissance, code, N° ID, commune (04/11). Transaction ANNULEE sur la base reelle.
$root = $argv[1] ?? '/var/www/html';
chdir($root);
require_once $root . '/schoolsafe-notify-cli.php';
$conn = pg_connect('host=' . (getenv('SCHOOLSAFE_DB_HOST') ?: 'db') . ' port=' . (getenv('SCHOOLSAFE_DB_PORT') ?: '5432') . ' dbname=' . getenv('POSTGRES_DB') . ' user=' . getenv('POSTGRES_USER') . ' password=' . getenv('POSTGRES_PASSWORD') . ' connect_timeout=5');
SchoolSafeNotifyCliInit($conn);
require_once $root . '/ProgramFunctions/SchoolSafeBulletinDoc.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }
foreach (['bulletin.php', 'papier-ecole.php', 'ProgramFunctions/SchoolSafeLetterhead.fnc.php', 'ProgramFunctions/SchoolSafeBulletinDoc.fnc.php'] as $f) {
    $out = []; exec('php -l ' . escapeshellarg($root . '/' . $f) . ' 2>&1', $out, $code); check('syntaxe ' . $f, $code, 0);
}
check('reglages : valeurs par defaut', [SchoolSafeLetterheadDefaults()['code'], SchoolSafeLetterheadDefaults()['commune']], ['', '']);
check('validation : code et commune', array_intersect_key(SchoolSafeLetterheadValidate(['layout' => 'officielle', 'code' => ' 9006613 ', 'idnum' => 'A-12', 'commune' => 'Limete']), ['code' => 1, 'idnum' => 1, 'commune' => 1]), ['code' => '9006613', 'idnum' => 'A-12', 'commune' => 'Limete']);
$get = function ($q) { return DBGet($q); };
$fieldsBefore = (int) DBGet("SELECT count(*) AS N FROM custom_fields")[1]['N'];
DBQuery('BEGIN');
try {
    pg_query($conn, preg_replace('/^(BEGIN|COMMIT);\s*$/m', '', file_get_contents($root . '/schoolsafe-remediation/067-birthplace-field.sql'))) or throw new RuntimeException('migration 067');
    $id = (int) DBGet("SELECT ID FROM custom_fields WHERE TITLE LIKE '%fr_FR.utf8:Lieu de naissance%'")[1]['ID'];
    check('champ natif cree dans Informations generales', (int) DBGet("SELECT CATEGORY_ID FROM custom_fields WHERE ID=" . $id)[1]['CATEGORY_ID'], 1);
    pg_query($conn, preg_replace('/^(BEGIN|COMMIT);\s*$/m', '', file_get_contents($root . '/schoolsafe-remediation/067-birthplace-field.sql'))) or throw new RuntimeException('rejeu 067');
    check('rejeu : pas de doublon', (int) DBGet("SELECT count(*) AS N FROM custom_fields WHERE TITLE LIKE '%fr_FR.utf8:Lieu de naissance%'")[1]['N'], 1);
    DBQuery("UPDATE students SET CUSTOM_" . $id . "='Kinshasa' WHERE STUDENT_ID=4");
    foreach (['SCHOOLSAFE_DOC_CODE' => '9006613', 'SCHOOLSAFE_DOC_IDNUM' => 'A-12', 'SCHOOLSAFE_DOC_COMMUNE' => 'Limete'] as $t => $v) DBQuery("INSERT INTO config(SCHOOL_ID,TITLE,CONFIG_VALUE) VALUES(3,'" . $t . "','" . $v . "')");
    $settings = SchoolSafeLetterheadSettings(3, $get);
    check('reglages relus', [$settings['code'], $settings['idnum'], $settings['commune']], ['9006613', 'A-12', 'Limete']);
    $st = DBGet("SELECT FIRST_NAME,LAST_NAME,CUSTOM_" . $id . " AS LIEU FROM students WHERE STUDENT_ID=4")[1];
    $html = SchoolSafeBulletinHtml(['identity' => $settings + ['school' => 'Complexe scolaire le sage', 'city' => 'Kinshasa'], 'student' => $st, 'class_title' => '1ère Primaire', 'year_label' => '2026-2027',
        'branches' => [], 'calc' => ['slots' => [], 'students' => [], 'count' => 0], 'terms' => [], 'status' => [], 'visible' => [], 'fy' => 15, 'sid' => 4, 'count' => 0, 'draft' => false, 'titulaire' => '', 'today' => '2026-12-12']);
    check('bulletin : ne a Kinshasa, code, N ID, commune', [strpos($html, 'NÉ(E) À : Kinshasa') !== false, strpos($html, 'CODE : 9006613') !== false, strpos($html, 'N° ID : A-12') !== false, strpos($html, 'COMMUNE : Limete') !== false], [true, true, true, true]);
} catch (Throwable $x) { $ko++; echo 'KO   exception : ' . $x->getMessage() . "\n"; }
finally { DBQuery('ROLLBACK'); }
check('rien n est reste en base', (int) DBGet("SELECT count(*) AS N FROM custom_fields")[1]['N'], $fieldsBefore);
echo "\n$ok OK, $ko KO\n";
exit($ko ? 1 : 0);
