<?php
// Devoirs envoyes automatiquement, fiche au nom de l'enfant (04/11). Usage : php test-devoirs.php /var/www/html [dossier PDF]
$root = $argv[1] ?? '/var/www/html';
$out = $argv[2] ?? '';
chdir($root);
function DBGet($q) { return []; } function DBQuery($q) { return null; }
require_once $root . '/ProgramFunctions/SchoolSafeHomeworkRelay.fnc.php';
require_once $root . '/ProgramFunctions/SchoolSafeLetterhead.fnc.php';
require_once $root . '/ProgramFunctions/SchoolSafeSchoolDocument.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }

// Type de notification
check('type declare', in_array('homework.published', SchoolSafeNotifyEventTypes(), true), true);
require_once $root . '/ProgramFunctions/SchoolSafeNotifyRecipient.fnc.php';
check('envoye aussi par WhatsApp', in_array('homework.published', SchoolSafeNotifyWaEventTypes(), true), true);
check('migration 057', strpos(file_get_contents($root . '/schoolsafe-remediation/057-homework-published-event.sql'), "'fee.reminder',\n  'homework.published'") !== false, true);

// Message au parent
$m = SchoolSafeHomeworkMessage('Josué', 'Mathématiques', 'Les fractions', 'Interrogation', '2026-11-06', 'https://app.shoolsafe.tech/schoolsafe-homework.php?assignment_id=5&student=16&download=1', 'Complexe scolaire le sage');
check('titre', $m[0], 'Nouveau devoir : Mathématiques');
check('prenom, matiere, devoir', strpos($m[1], 'Interrogation pour Josué en Mathématiques : « Les fractions ».') !== false, true);
check('date en francais', strpos($m[1], 'À rendre le vendredi 6 novembre 2026.') !== false, true);
check('lien nominatif', strpos($m[1], 'déjà au nom de Josué : https://app.shoolsafe.tech/schoolsafe-homework.php?assignment_id=5&student=16&download=1') !== false, true);
check('signature ecole et slogan', strpos($m[1], '— Complexe scolaire le sage · SchoolSafe · Un enfant protégé, un parent informé') !== false, true);
check('sans prenom', strpos(SchoolSafeHomeworkMessage('', '', 'X', '', '', 'L', '')[1], 'Devoir pour votre enfant') !== false, true);

// Releve des devoirs
$q = '';
check('aucun devoir = 0', SchoolSafeHomeworkRelay(['get' => function ($s) use (&$q) { $q = $s; return []; }], 'https://app.shoolsafe.tech/', 40), 0);
check('jamais les anciens devoirs', strpos($q, "ga.CREATED_AT>='2026-11-04' AND ga.CREATED_AT>now()-interval '14 days'") !== false, true);
check('seulement publies et non echus', strpos($q, '(ga.ASSIGNED_DATE IS NULL OR ga.ASSIGNED_DATE<=CURRENT_DATE) AND (ga.DUE_DATE IS NULL OR ga.DUE_DATE>=CURRENT_DATE)') !== false, true);
check('eleves inscrits a ce jour', strpos($q, 'ss.START_DATE<=CURRENT_DATE AND (ss.END_DATE IS NULL OR ss.END_DATE>=CURRENT_DATE)') !== false, true);
check('une seule fois par devoir et par eleve', strpos($q, "n.DEDUP_KEY='homework:'||ga.ASSIGNMENT_ID||':'||ss.STUDENT_ID") !== false, true);
check('devoir de cours : classes de son enseignant', strpos($q, 'cp.COURSE_ID=ga.COURSE_ID AND cp.TEACHER_ID=ga.STAFF_ID') !== false, true);
$off = 0;
SchoolSafeHomeworkRelay(['get' => function ($s) use (&$off) {
    if (strpos($s, 'SCHOOLSAFE_HOMEWORK_AUTO') !== false) return [1 => ['CONFIG_VALUE' => 'N']];
    if (strpos($s, 'gradebook_assignments') !== false) return [1 => ['ASSIGNMENT_ID' => 5, 'TITLE' => 'T', 'DUE_DATE' => null, 'STUDENT_ID' => 16, 'SCHOOL_ID' => 3, 'SYEAR' => 2026, 'COURSE' => 'M', 'TYPE_TITLE' => null, 'FIRST_NAME' => 'A', 'SCHOOL_TITLE' => 'E']];
    if (strpos($s, 'student_enrollment') !== false || strpos($s, 'students_join_users') !== false) $off++;
    return [];
}], 'https://x', 40);
check('ecole qui coupe l envoi : aucun parent cherche', $off, 0);
check('interrupteur : actif par defaut', SchoolSafeHomeworkAutoEnabled(3, fn($q) => []), true);
check('interrupteur : coupe', SchoolSafeHomeworkAutoEnabled(3, fn($q) => [1 => ['CONFIG_VALUE' => 'n']]), false);

// Fiche au nom de l'enfant, logo SchoolSafe visible
$identity = ['school' => 'Complexe scolaire le sage', 'slogan' => 'Former • Réformer • Exceller', 'phone' => '+243 000', 'email' => 'contact@ecole.example', 'address' => 'Kinshasa'];
$html = SchoolSafeLetterheadPage($identity, 'devoir', ['title' => 'Interrogation — Les fractions', 'student_box' => true, 'points' => '20',
    'student_name' => 'Josué Mbala', 'student_class' => '6e primaire', 'body' => '<p>Calculez 3/4 + 1/8.</p>', 'answer_lines' => 5]);
check('nom de l enfant rempli', strpos($html, '<span class="ss-filled">Josué Mbala</span>') !== false, true);
check('classe remplie', strpos($html, '<span class="ss-filled">6e primaire</span>') !== false, true);
check('sans enfant : pointilles', strpos(SchoolSafeLetterheadPage($identity, 'devoir', ['title' => 't', 'student_box' => true]), '<span class="ss-filled">') === false, true);
check('logo SchoolSafe visible (9 mm)', strpos($html, '.ss-brand-logo{height:9mm') !== false && strpos($html, 'class="ss-brand-logo"') !== false, true);
check('nom et slogan SchoolSafe', strpos($html, '<b>SchoolSafe</b> · <i>Un enfant protégé, un parent informé</i>') !== false, true);
try { $pdf = SchoolSafeDocumentPdf($html); } catch (Throwable $e) { $pdf = ''; }
check('PDF nominatif produit', substr($pdf, 0, 5) === '%PDF-', true);
if ($out !== '' && $pdf !== '') file_put_contents($out . '/devoir-nominatif.pdf', $pdf);

// Pages
$hw = file_get_contents($root . '/schoolsafe-homework.php');
check('fiche : enfant du parent verifie avant changement', strpos($hw, "if(!\$own){http_response_code(403);exit('Accès au devoir non autorisé.');}") !== false, true);
check('fiche : eleve inscrit dans la classe du devoir', strpos($hw, 'AND (cp.COURSE_PERIOD_ID=ga.COURSE_PERIOD_ID OR (ga.COURSE_PERIOD_ID IS NULL AND cp.COURSE_ID=ga.COURSE_ID)) WHERE ss.STUDENT_ID=s.STUDENT_ID') !== false, true);
$dispatch = file_get_contents($root . '/schoolsafe-notify-dispatch.php');
check('repartiteur : releve des devoirs', strpos($dispatch, "\$relayed+=SchoolSafeHomeworkRelay(") !== false, true);
check('papier : interrupteur enregistre', strpos(file_get_contents($root . '/papier-ecole.php'), "'SCHOOLSAFE_HOMEWORK_AUTO'=>!empty(\$post['homework_auto'])?'Y':'N'") !== false, true);
echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
