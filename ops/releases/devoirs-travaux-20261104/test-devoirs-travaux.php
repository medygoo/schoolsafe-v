<?php
// « Devoirs et travaux » (04/11). Usage : php test-devoirs-travaux.php /var/www/html [dossier PDF]
$root = $argv[1] ?? '/var/www/html';
$out = $argv[2] ?? '';
chdir($root);
require_once $root . '/ProgramFunctions/SchoolSafeHomeworkDesk.fnc.php';
require_once $root . '/ProgramFunctions/SchoolSafeLetterhead.fnc.php';
require_once $root . '/ProgramFunctions/SchoolSafeSchoolDocument.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }
function fails(callable $f): string { try { $f(); return ''; } catch (RuntimeException $e) { return $e->getMessage(); } }

check('5 modeles', array_keys(SchoolSafeHomeworkModels()), ['devoir', 'interrogation', 'examen', 'lecture', 'libre']);
check('texte : paragraphes et retours', SchoolSafeHomeworkTextToHtml("Exercice 1 :\nCalcule 3/4.\n\nExercice 2"), '<p>Exercice 1 :<br>Calcule 3/4.</p><p>Exercice 2</p>');
check('texte : liste', SchoolSafeHomeworkTextToHtml("- un\n- deux"), '<ul><li>un</li><li>deux</li></ul>');
check('texte : echappement', SchoolSafeHomeworkTextToHtml('<script>x</script>'), '<p>&lt;script&gt;x&lt;/script&gt;</p>');
$classes = [4 => ['COURSE_ID' => 4], 5 => ['COURSE_ID' => 5]];
$types = [7 => 4, 8 => 4, 9 => 5];
$in = ['cp' => '4', 'type' => '7', 'title' => '  Les   fractions ', 'text' => "Calcule 3/4 + 1/8.", 'points' => '20', 'assigned' => '2026-11-05', 'due' => '2026-11-12', 'submission' => '1'];
$v = SchoolSafeHomeworkValidate($in, $classes, $types);
check('devoir valide', [$v['cp'], $v['course'], $v['type'], $v['title'], $v['points'], $v['submission']], [4, 4, 7, 'Les fractions', '20', true]);
check('classe d un autre enseignant refusee', fails(fn() => SchoolSafeHomeworkValidate(['cp' => '1'] + $in, $classes, $types)), 'Choisissez une de vos classes.');
check('type d un autre cours refuse', fails(fn() => SchoolSafeHomeworkValidate(['type' => '9'] + $in, $classes, $types)), 'Choisissez un type de devoir de ce cours.');
check('remise avant le devoir refusee', fails(fn() => SchoolSafeHomeworkValidate(['due' => '2026-11-01'] + $in, $classes, $types)), 'La date de remise doit suivre la date du devoir.');
check('bareme invalide', fails(fn() => SchoolSafeHomeworkValidate(['points' => '0'] + $in, $classes, $types)) !== '', true);
check('consignes obligatoires', fails(fn() => SchoolSafeHomeworkValidate(['text' => '  '] + $in, $classes, $types)), 'Écrivez les consignes du devoir.');
$g = SchoolSafeHomeworkGrades(['n_3' => '15', 'c_3' => 'Bon travail', 'n_4' => '*', 'n_5' => '', 'n_99' => '20'], [3, 4, 5], 20);
check('notes : note, dispense, vide ; eleve hors classe ignore', $g, [3 => ['15', 'Bon travail'], 4 => ['-1', ''], 5 => [null, '']]);
check('notes : virgule', SchoolSafeHomeworkGrades(['n_3' => '12,5'], [3], 20)[3][0], '12.5');
check('notes : au-dessus du bareme refusee', fails(fn() => SchoolSafeHomeworkGrades(['n_3' => '21'], [3], 20)), 'Note invalide : entre 0 et 20, ou * pour une dispense.');
check('notes : texte refuse', fails(fn() => SchoolSafeHomeworkGrades(['n_3' => 'abc'], [3], 20)) !== '', true);

// Toute la classe : une fiche par eleve
$id = ['school' => 'Complexe scolaire le sage', 'phone' => '+243 000', 'email' => 'contact@ecole.example', 'address' => 'Kinshasa'];
$base = ['title' => 'Devoir — Les fractions', 'student_box' => true, 'points' => '20', 'body' => '<p>Calcule 3/4 + 1/8.</p>', 'answer_lines' => 4];
$pages = [];
foreach (['Josué KABEYA' => '1ère Primaire', 'Grâce MBALA' => 'Maternelle'] as $n => $c) $pages[] = SchoolSafeLetterheadPage($id, 'devoir', ['student_name' => $n, 'student_class' => $c] + $base);
$book = SchoolSafeLetterheadBook($pages);
check('classe : deux fiches', substr_count($book, '<article class="ss-paper'), 2);
check('classe : nom de chaque eleve', strpos($book, 'Josué KABEYA') !== false && strpos($book, 'Grâce MBALA') !== false, true);
check('classe : saut de page entre fiches', substr_count($book, 'page-break-after:always'), 1);
check('classe : document unique', substr_count($book, '<html'), 1);
try { $pdf = SchoolSafeDocumentPdf($book); } catch (Throwable $e) { $pdf = ''; }
check('classe : PDF produit', substr($pdf, 0, 5) === '%PDF-', true);
if ($out !== '' && $pdf !== '') file_put_contents($out . '/classe.pdf', $pdf);

$page = file_get_contents($root . '/devoirs-travaux.php');
check('page : enseignants avec le droit natif', strpos($page, "User('PROFILE')==='teacher'") !== false && strpos($page, "AllowUse('Grades/Assignments.php')") !== false, true);
check('page : devoir natif (type, enseignant, periode de notes)', strpos($page, 'INSERT INTO gradebook_assignments(ASSIGNMENT_TYPE_ID,STAFF_ID,MARKING_PERIOD_ID,COURSE_PERIOD_ID') !== false, true);
check('page : correction seulement de ses devoirs', strpos($page, "if(!\$a)throw new RuntimeException('Ce devoir n’est pas le vôtre.');") !== false, true);
check('page : notes natives', strpos($page, 'INSERT INTO gradebook_grades(STUDENT_ID,COURSE_PERIOD_ID,ASSIGNMENT_ID,POINTS,COMMENT)') !== false, true);
check('page : jeton et transaction', strpos($page, 'hash_equals($csrf') !== false && strpos($page, "if(\$inTx)DBQuery('ROLLBACK');") !== false, true);
$hw = file_get_contents($root . '/schoolsafe-homework.php');
check('fiche : toute la classe interdite aux parents', strpos($hw, "if(in_array(\$profile,['parent','student'],true)){http_response_code(403);") !== false, true);
check('menu : devoirs et travaux', strpos(file_get_contents($root . '/central-navigation.php'), "'Devoirs et travaux', 'devoirs-travaux.php'") !== false, true);
echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
