<?php
require_once __DIR__ . '/../modules/SchoolSafe/Gateway.php';
$ssActions = [];
foreach (['map', 'decide', 'session', 'update_session', 'reevaluate', 'billing'] as $ssAction) {
    $ssActions[$ssAction] = SchoolSafeEndpointGateway('rattrapage.php', 'POST', '', ['action' => $ssAction]) !== 'deny';
}
$_SESSION['ss_remediation_csrf'] = $_SESSION['ss_remediation_csrf'] ?? bin2hex(random_bytes(32));
$ssToken = $_SESSION['ss_remediation_csrf'];
$ssSchool = (int) UserSchool();
$ssYear = (int) UserSyear();
$ssUser = (int) User('STAFF_ID');
$ssManage = User('PROFILE') === 'admin' && AllowEdit('Grades/ReportCardGrades.php');
$ssCanTeach = User('PROFILE') === 'teacher' && AllowUse('Grades/Grades.php');
$ssDirectorTitle = DBEscapeString(json_decode('"Directeur p\u00e9dagogique"'));
$ssDirector = $ssManage && (bool) DBGet("SELECT id FROM user_profiles WHERE id=" . (int) User('PROFILE_ID') . " AND title='" . $ssDirectorTitle . "'");
$ssEscape = static function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };
$ssScope = 'r.school_id=' . $ssSchool . ' AND r.syear=' . $ssYear;
if (User('PROFILE') === 'teacher') {
    $ssScope .= ' AND EXISTS(SELECT 1 FROM course_periods cp WHERE cp.course_id=r.course_id AND cp.teacher_id=' . $ssUser . ')';
} elseif (User('PROFILE') === 'parent') {
    $ssScope .= ' AND EXISTS(SELECT 1 FROM students_join_users su WHERE su.student_id=r.student_id AND su.staff_id=' . $ssUser . ')';
    require_once __DIR__ . '/PrincipalScope.fnc.php';
    try {
        $ssPrincipalStudents=SchoolSafeRemediationPrincipalScope($ssUser,$ssSchool,$ssYear);
        $ssScope .= $ssPrincipalStudents ? ' AND r.student_id IN (' . implode(',',$ssPrincipalStudents) . ')' : ' AND FALSE';
    } catch (Throwable $error) {
        http_response_code(503); exit('Verification du parent principal indisponible. Aucun dossier affiche.');
    }
} elseif (User('PROFILE') !== 'admin') {
    $ssScope .= ' AND FALSE';
}
$ssMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !is_string($_POST['csrf']) || !hash_equals($ssToken, $_POST['csrf'])) {
        http_response_code(403); exit('Requete expiree.');
    }
    foreach ($_POST as $value) {
        if (!is_string($value)) {
            http_response_code(400); exit('Formulaire invalide.');
        }
    }
    $action = $_POST['action'] ?? '';
    if (SchoolSafeEndpointGateway('rattrapage.php', 'POST', '', $_POST) === 'deny') {
        http_response_code(403); exit('Operation SchoolSafe non autorisee.');
    }
    if ($action === 'map' && $ssManage) {
        $typeId = (int) ($_POST['type_id'] ?? 0);
        $kind = $_POST['kind'] ?? '';
        $type = DBGet('SELECT gt.assignment_type_id FROM gradebook_assignment_types gt JOIN courses c ON c.course_id=gt.course_id WHERE gt.assignment_type_id=' . $typeId . ' AND c.school_id=' . $ssSchool . ' AND c.syear=' . $ssYear);
        if (!$type || !in_array($kind, ['homework','quiz','exam'], true)) {
            http_response_code(400); exit('Categorie invalide.');
        }
        DBQuery('BEGIN');
        DBQuery("INSERT INTO schoolsafe_remediation_types(assignment_type_id,kind,configured_by) VALUES(" . $typeId . ",'" . $kind . "'," . $ssUser . ") ON CONFLICT(assignment_type_id) DO UPDATE SET kind=EXCLUDED.kind,configured_by=EXCLUDED.configured_by,configured_at=now()");
        DBQuery('SELECT schoolsafe_remediation_refresh(x.student_id,x.course_id) FROM (SELECT DISTINCT gg.student_id,cp.course_id FROM gradebook_grades gg JOIN course_periods cp ON cp.course_period_id=gg.course_period_id WHERE cp.school_id=' . $ssSchool . ' AND cp.syear=' . $ssYear . ') x');
        DBQuery('COMMIT');
        $ssMessage = 'Correspondance enregistree et notes existantes analysees.';
    } elseif ($action === 'decide' && $ssDirector) {
        $caseId = (int) ($_POST['case_id'] ?? 0);
        $decision = $_POST['decision'] ?? '';
        if (!in_array($decision, ['approved','refused'], true)) { http_response_code(400); exit('Decision invalide.'); }
        $note = DBEscapeString(mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 2000));
        DBQuery('BEGIN');
        $changed = DBGet(DBQuery("UPDATE schoolsafe_remediation_cases r SET status='" . $decision . "',decided_by=" . $ssUser . ",decided_at=now(),decision_note='" . $note . "' WHERE id=" . $caseId . " AND " . $ssScope . " AND status='pending' AND evidence_valid=true RETURNING id"));
        if ($changed) DBQuery("INSERT INTO schoolsafe_remediation_events(case_id,kind,actor_id) VALUES(" . $caseId . ",'" . $decision . "'," . $ssUser . ')');
        DBQuery('COMMIT');
        $ssMessage = $changed ? 'Decision enregistree.' : 'Dossier non disponible ou preuves a reverifier.';
    } elseif ($action === 'session' && $ssManage) {
        $caseId = (int) ($_POST['case_id'] ?? 0);
        $teacherId = (int) ($_POST['teacher_id'] ?? 0);
        $duration = (int) ($_POST['duration'] ?? 0);
        $objective = trim((string) ($_POST['objective'] ?? ''));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', (string) ($_POST['starts_at'] ?? ''), new DateTimeZone('Africa/Kinshasa'));
        if (!$date || $date->format('Y-m-d\TH:i') !== ($_POST['starts_at'] ?? '') || $duration < 1 || $duration > 480 || $objective === '' || mb_strlen($objective) > 2000) {
            http_response_code(400); exit('Seance invalide.');
        }
        $time = DBEscapeString($date->format('Y-m-d H:i:sP'));
        $objective = DBEscapeString($objective);
        DBQuery('BEGIN');
        $locked = DBGet('SELECT r.id FROM schoolsafe_remediation_cases r WHERE r.id=' . $caseId . ' AND ' . $ssScope . " AND r.status IN ('approved','repeat') FOR UPDATE");
        $saved = $locked ? DBGet(DBQuery("INSERT INTO schoolsafe_remediation_sessions(case_id,teacher_id,starts_at,duration_minutes,objective) SELECT r.id," . $teacherId . ",'" . $time . "'," . $duration . ",'" . $objective . "' FROM schoolsafe_remediation_cases r WHERE r.id=" . $caseId . ' AND EXISTS(SELECT 1 FROM course_periods cp WHERE cp.course_id=r.course_id AND cp.teacher_id=' . $teacherId . ') RETURNING id')) : [];
        if ($saved) DBQuery("INSERT INTO schoolsafe_remediation_events(case_id,kind,actor_id) VALUES(" . $caseId . ",'session_scheduled'," . $ssUser . ')');
        DBQuery('COMMIT');
        $ssMessage = $saved ? 'Seance programmee.' : 'Dossier non valide ou enseignant non titulaire.';
    } elseif ($action === 'update_session' && ($ssManage || $ssCanTeach)) {
        $sessionId = (int) ($_POST['session_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $attendance = $_POST['attendance'] ?? '';
        $report = trim((string) ($_POST['report'] ?? ''));
        $progress = trim((string) ($_POST['progress'] ?? ''));
        if (!in_array($status, ['held','postponed','cancelled'], true)
            || !in_array($attendance, ['present','absent','excused'], true)
            || $report === '' || mb_strlen($report) > 4000 || mb_strlen($progress) > 4000) {
            http_response_code(400); exit('Suivi incomplet.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', (string) ($_POST['starts_at'] ?? ''), new DateTimeZone('Africa/Kinshasa'));
        if ($status === 'postponed' && (!$date || $date->format('Y-m-d\TH:i') !== ($_POST['starts_at'] ?? ''))) {
            http_response_code(400); exit('Nouvelle date requise pour un report.');
        }
        DBQuery('BEGIN');
        $locked = DBGet('SELECT r.id FROM schoolsafe_remediation_cases r JOIN schoolsafe_remediation_sessions se ON se.case_id=r.id WHERE se.id=' . $sessionId . ' AND ' . $ssScope . " AND r.status IN ('approved','repeat')" . ($ssManage ? '' : ' AND se.teacher_id=' . $ssUser) . ' FOR UPDATE OF r');
        $changed = $locked ? DBGet(DBQuery("UPDATE schoolsafe_remediation_sessions SET status='" . $status . "',attendance=" . ($status === 'held' ? "'" . $attendance . "'" : 'NULL') . ",report='" . DBEscapeString($report) . "',progress='" . DBEscapeString($progress) . "',updated_by=" . $ssUser . ',updated_at=now()' . ($status === 'postponed' ? ",starts_at='" . DBEscapeString($date->format('Y-m-d H:i:sP')) . "'" : '') . ' WHERE id=' . $sessionId . ' RETURNING case_id')) : [];
        if ($changed) DBQuery("INSERT INTO schoolsafe_remediation_events(case_id,kind,actor_id) VALUES(" . (int) $changed[1]['CASE_ID'] . ",'session_" . $status . "'," . $ssUser . ')');
        DBQuery('COMMIT');
        $ssMessage = $changed ? 'Suivi de seance enregistre.' : 'Seance non accessible.';
    } elseif ($action === 'reevaluate' && $ssDirector) {
        $caseId = (int) ($_POST['case_id'] ?? 0);
        $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
        $outcome = $_POST['outcome'] ?? '';
        $note = trim((string) ($_POST['note'] ?? ''));
        if (!in_array($outcome, ['passed','repeat','closed'], true) || $note === '' || mb_strlen($note) > 4000) {
            http_response_code(400); exit('Decision de reevaluation incomplete.');
        }
        DBQuery('BEGIN');
        $locked = DBGet('SELECT r.id FROM schoolsafe_remediation_cases r WHERE r.id=' . $caseId . ' AND ' . $ssScope . " AND r.status IN ('approved','repeat') FOR UPDATE");
        $grade = $locked ? DBGet('SELECT gg.points,ga.points AS maximum FROM gradebook_grades gg JOIN gradebook_assignments ga ON ga.assignment_id=gg.assignment_id JOIN course_periods cp ON cp.course_period_id=gg.course_period_id JOIN schoolsafe_remediation_cases r ON r.student_id=gg.student_id AND r.course_id=cp.course_id WHERE r.id=' . $caseId . ' AND ga.assignment_id=' . $assignmentId . ' AND cp.school_id=r.school_id AND cp.syear=r.syear AND (ga.course_id=cp.course_id OR ga.course_period_id=cp.course_period_id) AND gg.points>=0 AND ga.points>0 AND ga.created_at>=r.created_at AND EXISTS(SELECT 1 FROM schoolsafe_remediation_sessions se WHERE se.case_id=r.id AND se.status=\'held\') ORDER BY gg.updated_at DESC NULLS LAST LIMIT 1') : [];
        if ($grade) {
            DBQuery("INSERT INTO schoolsafe_remediation_reassessments(case_id,assignment_id,points,maximum,outcome,note,decided_by) VALUES(" . $caseId . ',' . $assignmentId . ',' . (float) $grade[1]['POINTS'] . ',' . (int) $grade[1]['MAXIMUM'] . ",'" . $outcome . "','" . DBEscapeString($note) . "'," . $ssUser . ')');
            DBQuery("UPDATE schoolsafe_remediation_cases SET status='" . $outcome . "' WHERE id=" . $caseId);
            DBQuery("INSERT INTO schoolsafe_remediation_events(case_id,kind,actor_id) VALUES(" . $caseId . ",'reassessment_" . $outcome . "'," . $ssUser . ')');
        }
        DBQuery('COMMIT');
        $ssMessage = $grade ? 'Reevaluation enregistree depuis les notes SchoolSafe.' : 'Une seance tenue et une nouvelle evaluation notee dans SchoolSafe sont requises.';
    } elseif ($action === 'billing' && $ssDirector) {
        $caseId = (int) ($_POST['case_id'] ?? 0);
        $mode = $_POST['mode'] ?? '';
        $amount = str_replace(',', '.', trim((string) ($_POST['tariff'] ?? '0')));
        if (!in_array($mode, ['free','paid'], true) || !preg_match('/^\d{1,12}(\.\d{1,2})?$/D', $amount)
            || ($mode === 'paid' && (float) $amount <= 0)) { http_response_code(400); exit('Tarif invalide.'); }
        if ($mode === 'paid' && !AllowEdit('Student_Billing/StudentFees.php')) { http_response_code(403); exit('Permission de facturation requise.'); }
        DBQuery('BEGIN');
        $locked = DBGet('SELECT r.* FROM schoolsafe_remediation_cases r WHERE r.id=' . $caseId . ' AND ' . $ssScope . " AND r.status IN ('approved','repeat') AND r.billing_mode IS NULL FOR UPDATE");
        if ($locked) {
            $feeId = 'NULL';
            if ($mode === 'paid') {
                $fee = DBGet(DBQuery("INSERT INTO billing_fees(student_id,school_id,syear,title,amount,assigned_date,created_by) VALUES(" . (int) $locked[1]['STUDENT_ID'] . ',' . $ssSchool . ',' . $ssYear . ",'Rattrapage - dossier " . $caseId . "'," . $amount . ",CURRENT_DATE,'" . DBEscapeString(User('NAME')) . "') RETURNING id"));
                $feeId = (int) $fee[1]['ID'];
            }
            DBQuery("UPDATE schoolsafe_remediation_cases SET billing_mode='" . $mode . "',tariff=" . ($mode === 'free' ? '0' : $amount) . ',fee_id=' . $feeId . ' WHERE id=' . $caseId);
            DBQuery("INSERT INTO schoolsafe_remediation_events(case_id,kind,actor_id) VALUES(" . $caseId . ",'billing_configured'," . $ssUser . ')');
        }
        DBQuery('COMMIT');
        $ssMessage = $locked ? 'Tarification enregistree dans SchoolSafe.' : 'Tarification deja definie ou dossier non valide.';
    } else {
        http_response_code(403); exit('Operation non autorisee.');
    }
    $_SESSION['ss_remediation_csrf'] = bin2hex(random_bytes(32));
    $ssToken = $_SESSION['ss_remediation_csrf'];
}
if (SchoolSafeEndpointGateway('rattrapage.php', 'GET') === 'deny') {
    if ($ssMessage) echo '<p role="status">' . $ssEscape($ssMessage) . '</p>';
    return;
}
$ssTypes = ($ssManage && $ssActions['map']) ? DBGet('SELECT gt.assignment_type_id,gt.title,c.title AS course_title,mt.kind FROM gradebook_assignment_types gt JOIN courses c ON c.course_id=gt.course_id LEFT JOIN schoolsafe_remediation_types mt ON mt.assignment_type_id=gt.assignment_type_id WHERE c.school_id=' . $ssSchool . ' AND c.syear=' . $ssYear . ' ORDER BY c.title,gt.title') : [];
$ssCases = DBGet('SELECT r.*,s.first_name,s.last_name,c.title AS course_title FROM schoolsafe_remediation_cases r JOIN students s ON s.student_id=r.student_id JOIN courses c ON c.course_id=r.course_id WHERE ' . $ssScope . ' ORDER BY r.created_at DESC LIMIT 200');
$ssLabels = ['pending'=>'En attente','approved'=>'Valide','refused'=>'Refuse','passed'=>'Reussi','repeat'=>'A reprendre','closed'=>'Cloture'];
$ssEventLabels = ['detected'=>'Difficulte detectee','approved'=>'Dossier valide','refused'=>'Dossier refuse','session_scheduled'=>'Seance programmee','session_held'=>'Seance tenue','session_postponed'=>'Seance reportee','session_cancelled'=>'Seance annulee','reassessment_passed'=>'Reevaluation : reussi','reassessment_repeat'=>'Reevaluation : a reprendre','reassessment_closed'=>'Dossier cloture','billing_configured'=>'Tarification definie','payment_recorded'=>'Paiement enregistre, confirmation en attente','payment_confirmed'=>'Paiement confirme','payment_review_required'=>'Paiement a reverifier'];
$ssNotifications = DBGet('SELECT n.created_at,e.kind,r.id AS case_id,c.title AS course_title FROM schoolsafe_remediation_notifications n JOIN schoolsafe_remediation_events e ON e.id=n.event_id JOIN schoolsafe_remediation_cases r ON r.id=e.case_id JOIN courses c ON c.course_id=r.course_id WHERE n.recipient_id=' . $ssUser . ' AND ' . $ssScope . ' ORDER BY n.created_at DESC LIMIT 50');
?>
<?php if ($ssNotifications): ?>
<section aria-label="Mes notifications"><h2>Mes notifications de rattrapage</h2>
<?php foreach ($ssNotifications as $notification): ?><p><?= $ssEscape($notification['CREATED_AT']) ?> · <?= $ssEscape(ParseMLField($notification['COURSE_TITLE'])) ?> · <?= $ssEscape($ssEventLabels[$notification['KIND']] ?? 'Dossier mis a jour') ?> · Dossier <?= (int) $notification['CASE_ID'] ?></p><?php endforeach; ?>
</section>
<?php endif; ?>
<section aria-labelledby="dossiers-title">
<h2 id="dossiers-title">Dossiers de rattrapage</h2>
<?php if (User('PROFILE') === 'parent'): ?><p><a href="parent-notifications-view.php">Mes notifications de rattrapage</a></p><?php endif; ?>
<?php if ($ssMessage): ?><p role="status"><?= $ssEscape($ssMessage) ?></p><?php endif; ?>
<?php if (!$ssCases): ?><p>Aucun dossier d&eacute;tect&eacute; pour les cours accessibles dans cette &eacute;cole.</p><?php endif; ?>
<?php foreach ($ssCases as $case): ?>
<article class="remediation-case" id="remediation-case-<?= (int) $case['ID'] ?>">
<h3><?= $ssEscape($case['FIRST_NAME'] . ' ' . $case['LAST_NAME']) ?> · <?= $ssEscape(ParseMLField($case['COURSE_TITLE'])) ?></h3>
<p><?= $ssEscape($ssLabels[$case['STATUS']]) ?> · Dossier <?= (int) $case['ID'] ?></p>
<?php $evidenceGrades = DBGet('SELECT DISTINCT ga.assignment_id,ga.title,ga.marking_period_id,gg.points,ga.points AS maximum,mt.kind FROM schoolsafe_remediation_cases r JOIN gradebook_grades gg ON gg.student_id=r.student_id AND gg.assignment_id=ANY(r.evidence) JOIN course_periods cp ON cp.course_period_id=gg.course_period_id AND cp.course_id=r.course_id AND cp.school_id=r.school_id AND cp.syear=r.syear JOIN gradebook_assignments ga ON ga.assignment_id=gg.assignment_id AND (ga.course_id=cp.course_id OR ga.course_period_id=cp.course_period_id) LEFT JOIN schoolsafe_remediation_types mt ON mt.assignment_type_id=ga.assignment_type_id WHERE r.id=' . (int) $case['ID'] . ' ORDER BY ga.assignment_id'); ?>
<?php if ($evidenceGrades): ?><ul aria-label="Notes justificatives du dossier">
<?php foreach ($evidenceGrades as $evidence): ?><li><?= $ssEscape($evidence['TITLE']) ?> · <?= $ssEscape(['homework'=>'Devoir','quiz'=>'Interrogation','exam'=>'Examen'][$evidence['KIND']] ?? 'Type a reverifier') ?> · <?= $ssEscape($evidence['POINTS']) ?> / <?= $ssEscape($evidence['MAXIMUM']) ?> · P&eacute;riode <?= $ssEscape($evidence['MARKING_PERIOD_ID']) ?></li><?php endforeach; ?>
</ul><?php endif; ?>
<?php if ($case['BILLING_MODE'] === 'paid' && User('PROFILE') === 'admin' && AllowEdit('Student_Billing/StudentPayments.php') && SchoolSafeEndpointGateway('remediation-payments.php','GET') !== 'deny'): ?><p><a href="remediation-payments.php#remediation-case-<?= (int) $case['ID'] ?>">Paiements de ce rattrapage</a></p><?php endif; ?>
<?php if ($case['BILLING_MODE']): ?><p>Rattrapage <?= $case['BILLING_MODE'] === 'free' ? 'gratuit' : 'payant' ?><?php if ($case['BILLING_MODE'] === 'paid'): ?> · Tarif <?= $ssEscape($case['TARIFF']) ?><?php endif; ?></p><?php endif; ?>
<?php if ($ssDirector && $ssActions['billing'] && !$case['BILLING_MODE'] && in_array($case['STATUS'], ['approved','repeat'], true)): ?>
<form method="post"><input type="hidden" name="csrf" value="<?= $ssEscape($ssToken) ?>"><input type="hidden" name="action" value="billing"><input type="hidden" name="case_id" value="<?= (int) $case['ID'] ?>">
<label>Accompagnement<select name="mode"><option value="free">Gratuit</option><option value="paid">Payant</option></select></label>
<label>Tarif dans l'unit&eacute; mon&eacute;taire SchoolSafe<input name="tariff" type="number" min="0" step="0.01" value="0" required></label><button>Enregistrer la tarification</button></form>
<?php endif; ?>
<?php if ($case['EVIDENCE_VALID'] !== 't'): ?><p>Les notes ont chang&eacute; : les preuves doivent &ecirc;tre rev&eacute;rifi&eacute;es avant validation.</p><?php endif; ?>
<?php if ($ssDirector && $ssActions['decide'] && $case['STATUS'] === 'pending' && $case['EVIDENCE_VALID'] === 't'): ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= $ssEscape($ssToken) ?>">
<input type="hidden" name="action" value="decide"><input type="hidden" name="case_id" value="<?= (int) $case['ID'] ?>">
<label>Motif de la d&eacute;cision<textarea name="note" maxlength="2000"></textarea></label>
<button name="decision" value="approved">Valider</button><button name="decision" value="refused">Refuser</button>
</form>
<?php endif; ?>
<?php if ($ssManage && $ssActions['session'] && in_array($case['STATUS'], ['approved','repeat'], true)): ?>
<?php $teachers = DBGet('SELECT DISTINCT st.staff_id,st.first_name,st.last_name FROM course_periods cp JOIN staff st ON st.staff_id=cp.teacher_id WHERE cp.course_id=' . (int) $case['COURSE_ID']); ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= $ssEscape($ssToken) ?>"><input type="hidden" name="action" value="session"><input type="hidden" name="case_id" value="<?= (int) $case['ID'] ?>">
<label>Enseignant titulaire<select name="teacher_id" required><?php foreach ($teachers as $teacher): ?><option value="<?= (int) $teacher['STAFF_ID'] ?>"><?= $ssEscape($teacher['FIRST_NAME'] . ' ' . $teacher['LAST_NAME']) ?></option><?php endforeach; ?></select></label>
<label>Date et heure<input name="starts_at" type="datetime-local" required></label>
<label>Dur&eacute;e en minutes<input name="duration" type="number" min="1" max="480" required></label>
<label>Objectif p&eacute;dagogique<textarea name="objective" maxlength="2000" required></textarea></label><button>Programmer la s&eacute;ance</button>
</form>
<?php endif; ?>
<?php $sessions = DBGet('SELECT * FROM schoolsafe_remediation_sessions WHERE case_id=' . (int) $case['ID'] . ' ORDER BY starts_at'); ?>
<?php foreach ($sessions as $session): ?>
<div class="session-row"><p><strong><?= $ssEscape($session['STARTS_AT']) ?></strong> · <?= (int) $session['DURATION_MINUTES'] ?> min · <?= $ssEscape($session['OBJECTIVE']) ?> (<?= $ssEscape($session['STATUS']) ?>)</p>
<?php if ($session['REPORT']): ?><p>Compte rendu : <?= $ssEscape($session['REPORT']) ?></p><p>Progression : <?= $ssEscape($session['PROGRESS']) ?></p><?php endif; ?>
<?php if ($ssActions['update_session'] && ($ssManage || ($ssCanTeach && (int) $session['TEACHER_ID'] === $ssUser)) && in_array($case['STATUS'], ['approved','repeat'], true)): ?>
<form method="post"><input type="hidden" name="csrf" value="<?= $ssEscape($ssToken) ?>"><input type="hidden" name="action" value="update_session"><input type="hidden" name="session_id" value="<?= (int) $session['ID'] ?>">
<label>&Eacute;tat<select name="status"><option value="held">Tenue</option><option value="postponed">Report&eacute;e</option><option value="cancelled">Annul&eacute;e</option></select></label>
<label>Pr&eacute;sence<select name="attendance"><option value="present">Pr&eacute;sent</option><option value="absent">Absent</option><option value="excused">Excus&eacute;</option></select></label>
<label>Nouvelle date en cas de report<input type="datetime-local" name="starts_at"></label>
<label>Compte rendu ou motif<textarea name="report" maxlength="4000" required><?= $ssEscape($session['REPORT']) ?></textarea></label>
<label>Progression observ&eacute;e<textarea name="progress" maxlength="4000"><?= $ssEscape($session['PROGRESS']) ?></textarea></label><button>Enregistrer le suivi</button></form>
<?php endif; ?></div>
<?php endforeach; ?>
<?php if ($ssDirector && $ssActions['reevaluate'] && in_array($case['STATUS'], ['approved','repeat'], true)): ?>
<form method="post"><input type="hidden" name="csrf" value="<?= $ssEscape($ssToken) ?>"><input type="hidden" name="action" value="reevaluate"><input type="hidden" name="case_id" value="<?= (int) $case['ID'] ?>">
<?php $availableGrades = DBGet('SELECT DISTINCT ga.assignment_id,ga.title,gg.points,ga.points AS maximum FROM gradebook_grades gg JOIN gradebook_assignments ga ON ga.assignment_id=gg.assignment_id JOIN course_periods cp ON cp.course_period_id=gg.course_period_id WHERE gg.student_id=' . (int) $case['STUDENT_ID'] . ' AND cp.course_id=' . (int) $case['COURSE_ID'] . ' AND cp.school_id=' . $ssSchool . ' AND cp.syear=' . $ssYear . ' AND (ga.course_id=cp.course_id OR ga.course_period_id=cp.course_period_id) AND gg.points>=0 AND ga.points>0 AND ga.created_at>=\'' . DBEscapeString($case['CREATED_AT']) . '\' ORDER BY ga.assignment_id'); ?>
<label>Nouvelle &eacute;valuation SchoolSafe<select name="assignment_id" required><option value="">Choisir une &eacute;valuation enregistr&eacute;e</option><?php foreach ($availableGrades as $grade): ?><option value="<?= (int) $grade['ASSIGNMENT_ID'] ?>"><?= $ssEscape($grade['TITLE'] . ' : ' . $grade['POINTS'] . ' / ' . $grade['MAXIMUM']) ?></option><?php endforeach; ?></select></label>
<label>D&eacute;cision p&eacute;dagogique<select name="outcome"><option value="passed">R&eacute;ussi</option><option value="repeat">&Agrave; reprendre</option><option value="closed">Cl&ocirc;tur&eacute;</option></select></label>
<label>Motif<textarea name="note" maxlength="4000" required></textarea></label><button>Enregistrer la r&eacute;&eacute;valuation</button></form>
<?php endif; ?>
<?php $reassessments = DBGet('SELECT * FROM schoolsafe_remediation_reassessments WHERE case_id=' . (int) $case['ID'] . ' ORDER BY created_at'); ?>
<?php foreach ($reassessments as $result): ?><p>R&eacute;&eacute;valuation : <?= $ssEscape($result['POINTS']) ?> / <?= (int) $result['MAXIMUM'] ?> · <?= $ssEscape($ssLabels[$result['OUTCOME']]) ?> · <?= $ssEscape($result['NOTE']) ?></p><?php endforeach; ?>
</article>
<?php endforeach; ?>
</section>
<?php if ($ssManage && $ssActions['map']): ?>
<section aria-labelledby="categories-title"><h2 id="categories-title">Types d'&eacute;valuation SchoolSafe</h2>
<?php if (!$ssTypes): ?><p>Aucune cat&eacute;gorie d'&eacute;valuation n'est encore enregistr&eacute;e dans SchoolSafe pour cette &eacute;cole et cette ann&eacute;e.</p><?php endif; ?>
<?php foreach ($ssTypes as $type): ?>
<form method="post" class="type-map">
<input type="hidden" name="csrf" value="<?= $ssEscape($ssToken) ?>"><input type="hidden" name="action" value="map">
<input type="hidden" name="type_id" value="<?= (int) $type['ASSIGNMENT_TYPE_ID'] ?>">
<label><?= $ssEscape(ParseMLField($type['COURSE_TITLE']) . ' / ' . $type['TITLE']) ?>
<select name="kind" required><option value="">Non associ&eacute;</option>
<?php foreach (['homework'=>'Devoir','quiz'=>'Interrogation','exam'=>'Examen'] as $value=>$label): ?>
<option value="<?= $value ?>" <?= $type['KIND'] === $value ? 'selected' : '' ?>><?= $label ?></option>
<?php endforeach; ?></select></label><button>Enregistrer</button>
</form>
<?php endforeach; ?></section>
<?php endif; ?>
