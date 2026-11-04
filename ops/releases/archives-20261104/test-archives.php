<?php
// Archives de l'ecole (04/11). Usage : php test-archives.php /var/www/html
$root = $argv[1] ?? '/var/www/html';
require_once $root . '/ProgramFunctions/SchoolSafeArchive.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }
function fails(callable $f): string { try { $f(); return ''; } catch (RuntimeException $e) { return $e->getMessage(); } }

check('12 categories', count(SchoolSafeArchiveCategories()), 12);
check('assurance, paiement, achat presents', array_intersect(['assurance', 'paiement', 'achat'], array_keys(SchoolSafeArchiveCategories())), ['assurance', 'paiement', 'achat']);
$in = ['category' => 'achat', 'title' => ' Achat de  tableaux ', 'issuer' => 'Librairie', 'doc_date' => '2026-11-03', 'amount' => '150 000,50', 'currency' => 'CDF', 'tags' => 'tableaux', 'notes' => '', 'student' => '0'];
$v = SchoolSafeArchiveValidate($in, [4 => true]);
check('classement valide', [$v['category'], $v['title'], $v['amount'], $v['currency'], $v['doc_date'], $v['student']], ['achat', 'Achat de tableaux', '150000.50', 'CDF', '2026-11-03', null]);
check('sans montant : sans devise', SchoolSafeArchiveValidate(['amount' => ''] + $in, [])['currency'], null);
check('categorie obligatoire', fails(fn() => SchoolSafeArchiveValidate(['category' => 'x'] + $in, [])), 'Choisissez une catégorie.');
check('titre obligatoire', fails(fn() => SchoolSafeArchiveValidate(['title' => ' '] + $in, [])), 'Titre : obligatoire.');
check('montant invalide', fails(fn() => SchoolSafeArchiveValidate(['amount' => '12abc'] + $in, [])), 'Montant invalide.');
check('date invalide', fails(fn() => SchoolSafeArchiveValidate(['doc_date' => '2026-02-31'] + $in, [])), 'Date du document invalide.');
check('eleve d une autre ecole refuse', fails(fn() => SchoolSafeArchiveValidate(['student' => '99'] + $in, [4 => true])), 'Élève inconnu.');
check('reference', SchoolSafeArchiveReference(2026, 7), 'ARC-2026-0007');

$dir = sys_get_temp_dir() . '/arch-' . bin2hex(random_bytes(4)); mkdir($dir);
$big = imagecreatetruecolor(3300, 2400); imagefill($big, 0, 0, imagecolorallocate($big, 240, 240, 240)); imagepng($big, $dir . '/big.png');
[$mime, $bytes] = SchoolSafeArchiveFile($dir . '/big.png');
$info = getimagesizefromstring($bytes);
check('grande photo : JPEG reduit a 2200 px', [$mime, max($info[0], $info[1])], ['image/jpeg', 2200]);
$small = imagecreatetruecolor(800, 600); imagejpeg($small, $dir . '/small.jpg', 80);
check('petite photo JPEG gardee telle quelle', SchoolSafeArchiveFile($dir . '/small.jpg'), ['image/jpeg', file_get_contents($dir . '/small.jpg')]);
file_put_contents($dir . '/doc.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\n%%EOF");
check('PDF garde tel quel', SchoolSafeArchiveFile($dir . '/doc.pdf')[0], 'application/pdf');
file_put_contents($dir . '/x.php', '<?php echo 1;');
check('fichier non document refuse', fails(fn() => SchoolSafeArchiveFile($dir . '/x.php')), 'Format refusé : photo (JPEG, PNG) ou PDF seulement.');
$tiny = imagecreatetruecolor(20, 20); imagepng($tiny, $dir . '/tiny.png');
check('image minuscule refusee', fails(fn() => SchoolSafeArchiveFile($dir . '/tiny.png')), 'Image trop petite pour être lisible.');
array_map('unlink', glob($dir . '/*')); rmdir($dir);

$w = SchoolSafeArchiveWhere(3, "tableaux 50%_x'", 'achat', 2026, function ($x) { return "'" . str_replace("'", "''", $x) . "'"; });
check('recherche : ecole, non retires', strpos($w, 'd.SCHOOL_ID=3 AND d.WITHDRAWN_AT IS NULL') === 0, true);
check('recherche : categorie et annee', strpos($w, "d.CATEGORY='achat'") !== false && strpos($w, '=2026') !== false, true);
check('recherche : jokers neutralises', strpos($w, "'%50\\%\\_x''%'") !== false, true);
check('recherche : categorie inconnue ignoree', strpos(SchoolSafeArchiveWhere(3, '', 'x', 0, fn($x) => "''"), 'CATEGORY') === false, true);

$page = file_get_contents($root . '/archives.php');
check('page : direction seulement', strpos($page, "User('PROFILE')==='admin'&&\$school>0&&\$year>0&&AllowEdit('School_Setup/Configuration.php')") !== false, true);
check('page : fichier de son ecole seulement', strpos($page, "WHERE ID=\".(int)\$_GET['fichier'].\" AND SCHOOL_ID=\".\$school") !== false, true);
check('page : fichier jamais execute (sandbox)', strpos($page, 'Content-Security-Policy: sandbox') !== false, true);
check('page : doublon detecte', strpos($page, "Ce document est déjà archivé sous la référence ") !== false, true);
check('page : retrait garde le document', strpos($page, 'UPDATE schoolsafe_archive_documents SET WITHDRAWN_AT=now()') !== false && strpos($page, 'DELETE FROM') === false, true);
check('page : courriers retrouves', strpos($page, 'FROM schoolsafe_letters l WHERE') !== false, true);
check('migration 061 : contenu dans la base', strpos(file_get_contents($root . '/schoolsafe-remediation/061-archive-documents.sql'), 'content bytea NOT NULL') !== false, true);
check('menu : archives', strpos(file_get_contents($root . '/central-navigation.php'), "'Archives de l’école', 'archives.php'") !== false, true);
echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
