<?php
// Lot 11 OCR (04/11) : PDF avec texte, PDF scanne (image seule), photo ; puis recherche sur la base reelle
// dans une transaction ANNULEE.
$root = $argv[1] ?? '/var/www/html';
chdir($root);
require_once $root . '/schoolsafe-notify-cli.php';
$conn = pg_connect('host=' . (getenv('SCHOOLSAFE_DB_HOST') ?: 'db') . ' port=' . (getenv('SCHOOLSAFE_DB_PORT') ?: '5432') . ' dbname=' . getenv('POSTGRES_DB') . ' user=' . getenv('POSTGRES_USER') . ' password=' . getenv('POSTGRES_PASSWORD') . ' connect_timeout=5');
SchoolSafeNotifyCliInit($conn);
require_once $root . '/ProgramFunctions/SchoolSafeArchive.fnc.php';
require_once $root . '/ProgramFunctions/SchoolSafeSchoolDocument.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }
foreach (['archives.php', 'ProgramFunctions/SchoolSafeArchive.fnc.php'] as $f) { $out = []; exec('php -l ' . escapeshellarg($root . '/' . $f) . ' 2>&1', $out, $code); check('syntaxe ' . $f, $code, 0); }
check('outils OCR installes', [is_executable('/usr/bin/tesseract'), is_executable('/usr/bin/pdftotext'), is_executable('/usr/bin/pdftoppm')], [true, true, true]);
$html = '<!doctype html><html><head><meta charset="utf-8"><style>body{font:22pt Arial;margin:30mm}</style></head><body><h1>SONAS Assurance incendie</h1><p>Police numéro 4471-B. Prime annuelle : 350 000 francs congolais. Échéance le 15 janvier 2027.</p></body></html>';
$pdf = SchoolSafeDocumentPdf($html);
$t = microtime(true); $a = SchoolSafeArchiveOcr('application/pdf', $pdf);
check('PDF avec texte : lu (' . round(microtime(true) - $t, 1) . ' s)', $a !== null && mb_stripos($a, 'SONAS') !== false && mb_stripos($a, '4471') !== false, true);
$dir = sys_get_temp_dir() . '/ocr-test'; @mkdir($dir); file_put_contents($dir . '/d.pdf', $pdf);
exec('/usr/bin/pdftoppm -r 150 -l 1 -png ' . escapeshellarg($dir . '/d.pdf') . ' ' . escapeshellarg($dir . '/p'));
$png = (string) @file_get_contents((glob($dir . '/p*.png') ?: [''])[0]);
$t = microtime(true); $b = SchoolSafeArchiveOcr('image/png', $png);
check('photo : texte lu par OCR (' . round(microtime(true) - $t, 1) . ' s)', $b !== null && mb_stripos($b, 'SONAS') !== false && mb_stripos($b, 'incendie') !== false, true);
echo '      extrait lu : ' . mb_substr(str_replace("\n", ' ', (string) $b), 0, 120) . "\n";
// PDF scanne : une image dans un PDF, sans texte.
$scan = SchoolSafeDocumentPdf('<!doctype html><html><body style="margin:0"><img style="width:100%" src="data:image/png;base64,' . base64_encode($png) . '"></body></html>');
$t = microtime(true); $c = SchoolSafeArchiveOcr('application/pdf', $scan);
check('PDF scanne : OCR des pages (' . round(microtime(true) - $t, 1) . ' s)', $c !== null && mb_stripos($c, 'SONAS') !== false, true);
check('extrait autour du mot', strpos(SchoolSafeArchiveSnippet("Facture SONAS\nPolice 4471-B prime annuelle", 'police', 10), 'Police 4471') !== false, true);
check('aucun extrait sans recherche', SchoolSafeArchiveSnippet('texte', ''), '');
array_map('unlink', glob($dir . '/*')); @rmdir($dir);

DBQuery('BEGIN');
try {
    if (!DBGet("SELECT 1 AS X FROM information_schema.columns WHERE table_name='schoolsafe_archive_documents' AND column_name='ocr_text'")) throw new RuntimeException('migration 068 absente');
    $id = (int) DBGet("INSERT INTO schoolsafe_archive_documents(SCHOOL_ID,SYEAR,REFERENCE,CATEGORY,TITLE,MIME,SIZE_BYTES,SHA256,CONTENT,CREATED_BY,OCR_TEXT,OCR_AT) VALUES(3,2026,'ARC-2026-9999','assurance','Essai OCR','image/png',"
        . strlen($png) . ",'" . hash('sha256', $png) . "',decode('" . base64_encode($png) . "','base64'),6,'" . DBEscapeString((string) $b) . "',now()) RETURNING ID")[1]['ID'];
    $q = static function ($x) { return "'" . DBEscapeString($x) . "'"; };
    $found = DBGet("SELECT d.ID FROM schoolsafe_archive_documents d WHERE " . SchoolSafeArchiveWhere(3, 'incendie', '', 0, $q));
    check('recherche par un mot du texte lu (absent du titre)', in_array($id, array_map('intval', array_column((array) $found, 'ID')), true), true);
} catch (Throwable $x) { $ko++; echo 'KO   exception : ' . $x->getMessage() . "\n"; }
finally { DBQuery('ROLLBACK'); }
check('rien n est reste en base', (int) DBGet("SELECT count(*) AS N FROM schoolsafe_archive_documents WHERE REFERENCE='ARC-2026-9999'")[1]['N'], 0);
echo "\n$ok OK, $ko KO\n";
exit($ko ? 1 : 0);
