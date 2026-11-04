<?php
// Convocations et courriers (04/11). Usage : php test-courriers.php /var/www/html [dossier PDF]
$root = $argv[1] ?? '/var/www/html';
$out = $argv[2] ?? '';
chdir($root);
require_once $root . '/ProgramFunctions/SchoolSafeLetters.fnc.php';
require_once $root . '/ProgramFunctions/SchoolSafeLetterhead.fnc.php';
require_once $root . '/ProgramFunctions/SchoolSafeHomeworkDesk.fnc.php';
require_once $root . '/ProgramFunctions/SchoolSafeSchoolDocument.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }
function fails(callable $f): string { try { $f(); return ''; } catch (RuntimeException $e) { return $e->getMessage(); } }

$m = SchoolSafeLetterModels();
check('6 modeles', array_keys($m), ['convocation', 'reunion', 'discipline', 'frais', 'attestation', 'libre']);
check('prefixes', array_column($m, 1), ['CONV', 'REUN', 'DISC', 'FRAI', 'ATTE', 'LETT']);
check('attestation : pas d envoi', $m['attestation'][6], false);
check('rappel de frais : jamais bloquant', strpos($m['frais'][3], 'continue d’être accueilli') !== false, true);
$vars = ['ecole' => 'Complexe scolaire le sage', 'eleve' => 'Josué KABEYA', 'classe' => '1ère Primaire', 'directeur' => 'Awongi wole jaspe', 'annee' => '2026-2027',
    'date_rdv' => 'lundi 9 novembre 2026', 'heure_rdv' => '08 h 00', 'lieu' => 'au bureau de la direction'];
$body = SchoolSafeLetterFill($m['convocation'][3], $vars);
check('remplissage : eleve, classe, ecole, rendez-vous', strpos($body, 'à Complexe scolaire le sage le lundi 9 novembre 2026 à 08 h 00, au bureau de la direction, au sujet de votre enfant Josué KABEYA, élève de 1ère Primaire') !== false, true);
check('remplissage : rien a completer', SchoolSafeLetterMissing($body), []);
check('champ inconnu laisse visible', SchoolSafeLetterMissing(SchoolSafeLetterFill('Le {date_rdv} pour {eleve}', ['eleve' => 'X'])), ['date_rdv']);
check('numero', SchoolSafeLetterReference('CONV', 2026, 12), 'CONV-2026-0012');
$students = [4 => true]; $classes = [4 => true];
$in = ['kind' => 'convocation', 'student' => '4', 'date' => '2026-11-09', 'time' => '08:00', 'place' => 'Bureau', 'subject' => 'Convocation', 'body' => 'Texte', 'send' => '1'];
$v = SchoolSafeLetterValidate($in, $students, $classes);
check('convocation valide', [$v['kind'], $v['prefix'], $v['student'], $v['meeting'], $v['send']], ['convocation', 'CONV', 4, '2026-11-09 08:00', true]);
check('eleve obligatoire', fails(fn() => SchoolSafeLetterValidate(['student' => '99'] + $in, $students, $classes)), 'Choisissez l’élève concerné.');
check('rendez-vous obligatoire', fails(fn() => SchoolSafeLetterValidate(['date' => ''] + $in, $students, $classes)), 'Indiquez la date et l’heure du rendez-vous.');
check('reunion de toute l ecole permise', SchoolSafeLetterValidate(['kind' => 'reunion', 'student' => '0', 'class' => '0'] + $in, $students, $classes)['class'], 0);
check('attestation : jamais envoyee', SchoolSafeLetterValidate(['kind' => 'attestation'] + $in, $students, $classes)['send'], false);
check('modele inconnu', fails(fn() => SchoolSafeLetterValidate(['kind' => 'x'] + $in, $students, $classes)), 'Choisissez un modèle de courrier.');
$msg = SchoolSafeLetterMessage('CONV-2026-0001', 'Convocation au sujet de Josué', $body, 'https://app.shoolsafe.tech/courriers.php?pdf=1', 'Complexe scolaire le sage');
check('message : objet', $msg[0], '✉️ Convocation au sujet de Josué');
check('message : reference, lien, signature', strpos($msg[1], 'Référence CONV-2026-0001 — document à imprimer : https://app.shoolsafe.tech/courriers.php?pdf=1') !== false && strpos($msg[1], 'Un enfant protégé, un parent informé') !== false, true);

$identity = ['school' => 'Complexe scolaire le sage', 'phone' => '+243 000', 'email' => 'contact@ecole.example', 'address' => 'Kinshasa', 'principal' => 'Awongi wole jaspe', 'city' => 'Kinshasa'];
$html = SchoolSafeLetterheadPage($identity, 'officielle', ['title' => 'Convocation au sujet de Josué KABEYA', 'meta' => [['Référence', 'CONV-2026-0001'], ['Date', '4 novembre 2026'], ['Destinataire', 'Aux parents de Josué KABEYA']],
    'body' => SchoolSafeHomeworkTextToHtml($body) . '<p style="margin-top:14mm;text-align:right">Fait à Kinshasa, le 4 novembre 2026.<br><br><b>Awongi wole jaspe</b><br>Pour la direction</p>']);
try { $pdf = SchoolSafeDocumentPdf($html); } catch (Throwable $e) { $pdf = ''; }
check('PDF de convocation', substr($pdf, 0, 5) === '%PDF-', true);
if ($out !== '' && $pdf !== '') file_put_contents($out . '/convocation.pdf', $pdf);

$page = file_get_contents($root . '/courriers.php');
check('page : administration seulement pour rediger', strpos($page, "\$profile==='admin'&&\$school>0&&\$year>0&&AllowUse('Students/Student.php')") !== false, true);
check('page : document reserve au parent concerne', strpos($page, 'EXISTS(SELECT 1 FROM students_join_users su WHERE su.STUDENT_ID=s.STUDENT_ID AND su.STAFF_ID=') !== false, true);
check('page : numero sans doublon (verrou)', strpos($page, "LOCK TABLE schoolsafe_letters IN SHARE ROW EXCLUSIVE MODE") !== false, true);
check('page : champs a completer bloquent', strpos($page, "throw new RuntimeException('Champs à compléter dans le texte : {'") !== false, true);
check('page : envoi une seule fois par eleve', strpos($page, "'dedup_key'=>'letter:'.(int)\$L['ID'].':'.\$student") !== false, true);
check('migration 060', strpos(file_get_contents($root . '/schoolsafe-remediation/060-letters.sql'), 'UNIQUE (school_id, reference)') !== false, true);
check('menu : courriers', strpos(file_get_contents($root . '/central-navigation.php'), "'Convocations et courriers', 'courriers.php'") !== false, true);
echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
