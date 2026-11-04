<?php
require_once __DIR__ . '/../modules/SchoolSafe/Gateway.php';
$ssActions = [];
foreach (['map', 'decide', 'session', 'update_session', 'reevaluate', 'billing', 'parent_response'] as $ssAction) {
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
    // Enseignant de la matiere, ou enseignant a qui la direction a confie une seance (04/11).
    $ssScope .= ' AND (EXISTS(SELECT 1 FROM course_periods cp WHERE cp.course_id=r.course_id AND cp.teacher_id=' . $ssUser . ') OR EXISTS(SELECT 1 FROM schoolsafe_remediation_sessions se WHERE se.case_id=r.id AND se.teacher_id=' . $ssUser . '))';
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
require_once __DIR__ . '/../ProgramFunctions/SchoolSafeRemediationSettings.fnc.php';
$ssRule = SchoolSafeRemediationSettings($ssSchool, function ($sql) { return DBGet($sql); });
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
        $saved = $locked ? DBGet(DBQuery("INSERT INTO schoolsafe_remediation_sessions(case_id,teacher_id,starts_at,duration_minutes,objective) SELECT r.id," . $teacherId . ",'" . $time . "'," . $duration . ",'" . $objective . "' FROM schoolsafe_remediation_cases r WHERE r.id=" . $caseId . ' AND EXISTS(SELECT 1 FROM staff st WHERE st.staff_id=' . $teacherId . " AND st.profile='teacher' AND st.syear=r.syear AND (st.schools IS NULL OR st.schools LIKE '%,'||r.school_id||',%'))" . (empty($ssRule['other_teacher']) ? '' : ' AND NOT EXISTS(SELECT 1 FROM course_periods cp WHERE cp.course_id=r.course_id AND cp.teacher_id=' . $teacherId . ')') . ' RETURNING id')) : [];
        if ($saved) DBQuery("INSERT INTO schoolsafe_remediation_events(case_id,kind,actor_id) VALUES(" . $caseId . ",'session_scheduled'," . $ssUser . ')');
        DBQuery('COMMIT');
        $ssMessage = $saved ? 'Séance programmée.' : 'Dossier non valide ou enseignant hors de l’école' . (empty($ssRule['other_teacher']) ? '.' : ' ; l’école exige un autre enseignant que celui de la matière.');
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
        $mode = (string) ($_POST['mode'] ?? '');
        // Tarif juste (04/11) : reduction motivee possible ; l'accompagnement ne depend jamais du paiement.
        try {
            $price = SchoolSafeRemediationTariff($mode, (string) ($_POST['tariff'] ?? '0'), (string) ($_POST['reduction'] ?? '0'), (string) ($_POST['reduction_reason'] ?? ''));
        } catch (RuntimeException $failure) { http_response_code(400); exit($failure->getMessage()); }
        if ($mode === 'paid' && !AllowEdit('Student_Billing/StudentFees.php')) { http_response_code(403); exit('Permission de facturation requise.'); }
        DBQuery('BEGIN');
        $locked = DBGet('SELECT r.* FROM schoolsafe_remediation_cases r WHERE r.id=' . $caseId . ' AND ' . $ssScope . " AND r.status IN ('approved','repeat') AND r.billing_mode IS NULL FOR UPDATE");
        if ($locked) {
            $feeId = 'NULL';
            if ($mode === 'paid') {
                $fee = DBGet(DBQuery("INSERT INTO billing_fees(student_id,school_id,syear,title,amount,assigned_date,created_by) VALUES(" . (int) $locked[1]['STUDENT_ID'] . ',' . $ssSchool . ',' . $ssYear . ",'Rattrapage - dossier " . $caseId . "'," . $price['tariff'] . ",CURRENT_DATE,'" . DBEscapeString(User('NAME')) . "') RETURNING id"));
                $feeId = (int) $fee[1]['ID'];
            }
            DBQuery("UPDATE schoolsafe_remediation_cases SET billing_mode='" . $mode . "',tariff=" . $price['tariff'] . ',base_tariff=' . ($price['base'] ?? 'NULL') . ',reduction_percent=' . ($price['reduction'] ?? 'NULL') . ',reduction_reason=' . ($price['reason'] === null ? 'NULL' : "'" . DBEscapeString($price['reason']) . "'") . ',fee_id=' . $feeId . ' WHERE id=' . $caseId);
            DBQuery("INSERT INTO schoolsafe_remediation_events(case_id,kind,actor_id) VALUES(" . $caseId . ",'billing_configured'," . $ssUser . ')');
        }
        DBQuery('COMMIT');
        $ssMessage = $locked ? 'Tarification enregistrée dans SchoolSafe.' : 'Tarification déjà définie ou dossier non valide.';
    } elseif ($action === 'parent_response' && User('PROFILE') === 'parent') {
        // Accord du parent (04/11) : une reponse par plan, parent principal de l'enfant uniquement ($ssScope).
        $caseId = (int) ($_POST['case_id'] ?? 0);
        $response = (string) ($_POST['response'] ?? '');
        $note = trim((string) ($_POST['parent_note'] ?? ''));
        if (!isset(SchoolSafeRemediationParentResponses()[$response]) || mb_strlen($note) > 1000) { http_response_code(400); exit('Réponse invalide.'); }
        DBQuery('BEGIN');
        $changed = DBGet(DBQuery("UPDATE schoolsafe_remediation_cases r SET parent_response='" . $response . "',parent_response_at=now(),parent_response_by=" . $ssUser . ',parent_note=' . ($note === '' ? 'NULL' : "'" . DBEscapeString($note) . "'") . ' WHERE r.id=' . $caseId . ' AND ' . $ssScope . " AND r.status IN ('approved','repeat') AND r.parent_response IS NULL RETURNING r.id"));
        if ($changed) DBQuery("INSERT INTO schoolsafe_remediation_events(case_id,kind,actor_id) VALUES(" . $caseId . ",'parent_" . $response . "'," . $ssUser . ')');
        DBQuery('COMMIT');
        $ssMessage = $changed ? 'Votre réponse est enregistrée ; l’école en est informée.' : 'Réponse déjà enregistrée ou plan indisponible.';
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
$ssLabels = ['pending'=>'En attente','approved'=>'Validé','refused'=>'Refusé','passed'=>'Réussi','repeat'=>'À reprendre','closed'=>'Clôturé'];
$ssSessionLabels = ['scheduled'=>'programmée','held'=>'tenue','postponed'=>'reportée','cancelled'=>'annulée'];
$ssEventLabels = ['detected'=>'Difficulté détectée','approved'=>'Dossier validé','refused'=>'Dossier refusé','session_scheduled'=>'Séance programmée','session_held'=>'Séance tenue','session_postponed'=>'Séance reportée','session_cancelled'=>'Séance annulée','reassessment_passed'=>'Réévaluation : réussi','reassessment_repeat'=>'Réévaluation : à reprendre','reassessment_closed'=>'Dossier clôturé','billing_configured'=>'Tarification définie','payment_recorded'=>'Paiement enregistré, confirmation en attente','payment_confirmed'=>'Paiement confirmé','payment_review_required'=>'Paiement à revérifier','parent_accepted'=>'Parent : plan accepté','parent_meeting'=>'Parent : rendez-vous demandé','parent_declined'=>'Parent : plan refusé'];
$ssNotifications = DBGet('SELECT n.created_at,e.kind,r.id AS case_id,c.title AS course_title FROM schoolsafe_remediation_notifications n JOIN schoolsafe_remediation_events e ON e.id=n.event_id JOIN schoolsafe_remediation_cases r ON r.id=e.case_id JOIN courses c ON c.course_id=r.course_id WHERE n.recipient_id=' . $ssUser . ' AND ' . $ssScope . ' ORDER BY n.created_at DESC LIMIT 50');
$ssWatchScope = User('PROFILE') === 'teacher' ? 'w.school_id=' . $ssSchool . ' AND w.syear=' . $ssYear . ' AND EXISTS(SELECT 1 FROM course_periods cp WHERE cp.course_id=w.course_id AND cp.teacher_id=' . $ssUser . ')' : preg_replace('/\br\./', 'w.', $ssScope);
// Vigilance (04/11) : au moins 2 notes sous le seuil, sans dossier ; direction et enseignant de la matiere.
$ssWatch = in_array(User('PROFILE'), ['admin', 'teacher'], true) ? DBGet('SELECT w.*,s.first_name,s.last_name,c.title AS course_title FROM schoolsafe_remediation_watch w JOIN students s ON s.student_id=w.student_id JOIN courses c ON c.course_id=w.course_id WHERE ' . $ssWatchScope . ' AND NOT w.cleared AND NOT EXISTS(SELECT 1 FROM schoolsafe_remediation_cases x WHERE x.student_id=w.student_id AND x.course_id=w.course_id AND x.school_id=w.school_id AND x.syear=w.syear) ORDER BY w.created_at DESC LIMIT 100') : [];
?>
<?php if ($ssNotifications): ?>
<section aria-label="Mes notifications"><h2>Mes notifications de rattrapage</h2>
<?php foreach ($ssNotifications as $notification): ?><p><?= $ssEscape($notification['CREATED_AT']) ?> · <?= $ssEscape(ParseMLField($notification['COURSE_TITLE'])) ?> · <?= $ssEscape($ssEventLabels[$notification['KIND']] ?? 'Dossier mis a jour') ?> · Dossier <?= (int) $notification['CASE_ID'] ?></p><?php endforeach; ?>
</section>
<?php endif; ?>
<?php if ($ssWatch): ?>
<section aria-labelledby="vigilance-title"><h2 id="vigilance-title">Vigilance</h2>
<p>&Eacute;l&egrave;ves avec au moins 2 &eacute;valuations sous <?= $ssEscape(SchoolSafeRemediationPct($ssRule['threshold_percent'])) ?>, sans dossier ouvert. Le parent est pr&eacute;venu ; un &eacute;change avec la famille peut suffire.</p><ul>
<?php foreach ($ssWatch as $watch): ?><li><?= $ssEscape($watch['FIRST_NAME'] . ' ' . $watch['LAST_NAME']) ?> · <?= $ssEscape(ParseMLField($watch['COURSE_TITLE'])) ?> · <?= (int) $watch['FAILURES'] ?> &eacute;valuations sous le seuil · depuis le <?= $ssEscape(substr((string) $watch['CREATED_AT'], 0, 10)) ?></li><?php endforeach; ?>
</ul></section>
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
<?php $ssResponses = SchoolSafeRemediationParentResponses(); ?>
<?php if (User('PROFILE') === 'parent'): ?>
<?php $ssPlan = DBGet("SELECT count(*) AS total,count(*) FILTER (WHERE status='held') AS held,count(*) FILTER (WHERE status='held' AND attendance='present') AS present,min(starts_at) FILTER (WHERE status IN ('scheduled','postponed') AND starts_at>=now()) AS next FROM schoolsafe_remediation_sessions WHERE case_id=" . (int) $case['ID']); ?>
<?php $ssPaidRows = $case['BILLING_MODE'] === 'paid' ? DBGet('SELECT COALESCE(sum(amount),0) AS paid FROM schoolsafe_remediation_payments WHERE invalidated_at IS NULL AND case_id=' . (int) $case['ID']) : []; $ssPaid = (float) ($ssPaidRows[1]['PAID'] ?? 0); ?>
<div class="remediation-plan"><h4>Plan d’accompagnement</h4><ul>
<li>Séances : <?= (int) $ssPlan[1]['HELD'] ?> tenue(s) sur <?= (int) $ssPlan[1]['TOTAL'] ?> programmée(s) · présent à <?= (int) $ssPlan[1]['PRESENT'] ?></li>
<li>Prochaine séance : <?= $ssPlan[1]['NEXT'] ? $ssEscape(substr((string) $ssPlan[1]['NEXT'], 0, 16)) : 'à programmer par l’école' ?></li>
<li>Participation : <?php if (!$case['BILLING_MODE']): ?>à définir par l’école<?php elseif ($case['BILLING_MODE'] === 'free'): ?>gratuite<?php else: ?><?= $ssEscape($case['TARIFF']) ?> · déjà payé <?= $ssEscape(number_format($ssPaid, 2, '.', '')) ?> · reste <?= $ssEscape(number_format(max(0, (float) $case['TARIFF'] - $ssPaid), 2, '.', '')) ?><?php endif; ?></li>
<li>L’accompagnement de votre enfant ne dépend jamais du paiement.</li></ul>
<?php if ($case['PARENT_RESPONSE']): ?><p>Votre réponse : <?= $ssEscape($ssResponses[$case['PARENT_RESPONSE']] ?? '') ?> (<?= $ssEscape(substr((string) $case['PARENT_RESPONSE_AT'], 0, 10)) ?>)</p>
<?php elseif ($ssActions['parent_response'] && in_array($case['STATUS'], ['approved','repeat'], true)): ?>
<form method="post"><input type="hidden" name="csrf" value="<?= $ssEscape($ssToken) ?>"><input type="hidden" name="action" value="parent_response"><input type="hidden" name="case_id" value="<?= (int) $case['ID'] ?>">
<label>Un mot pour l’école (facultatif)<textarea name="parent_note" maxlength="1000"></textarea></label>
<?php foreach ($ssResponses as $value => $label): ?><button name="response" value="<?= $ssEscape($value) ?>"><?= $ssEscape($label) ?></button> <?php endforeach; ?></form>
<?php endif; ?></div>
<?php elseif ($case['PARENT_RESPONSE']): ?><p><strong>Réponse du parent :</strong> <?= $ssEscape($ssResponses[$case['PARENT_RESPONSE']] ?? '') ?> (<?= $ssEscape(substr((string) $case['PARENT_RESPONSE_AT'], 0, 10)) ?>)<?php if ($case['PARENT_NOTE']): ?> — <?= $ssEscape($case['PARENT_NOTE']) ?><?php endif; ?></p>
<?php elseif (in_array($case['STATUS'], ['approved','repeat'], true)): ?><p>Réponse du parent : en attente.</p>
<?php endif; ?>
<?php $evidenceGrades = DBGet('SELECT DISTINCT ga.assignment_id,ga.title,ga.marking_period_id,gg.points,ga.points AS maximum,mt.kind FROM schoolsafe_remediation_cases r JOIN gradebook_grades gg ON gg.student_id=r.student_id AND gg.assignment_id=ANY(r.evidence) JOIN course_periods cp ON cp.course_period_id=gg.course_period_id AND cp.course_id=r.course_id AND cp.school_id=r.school_id AND cp.syear=r.syear JOIN gradebook_assignments ga ON ga.assignment_id=gg.assignment_id AND (ga.course_id=cp.course_id OR ga.course_period_id=cp.course_period_id) LEFT JOIN schoolsafe_remediation_types mt ON mt.assignment_type_id=ga.assignment_type_id WHERE r.id=' . (int) $case['ID'] . ' ORDER BY ga.assignment_id'); ?>
<?php if ($evidenceGrades): ?><ul aria-label="Notes justificatives du dossier">
<?php foreach ($evidenceGrades as $evidence): ?><li><?= $ssEscape($evidence['TITLE']) ?> · <?= $ssEscape(['homework'=>'Devoir','quiz'=>'Interrogation','exam'=>'Examen'][$evidence['KIND']] ?? 'Type a reverifier') ?> · <?= $ssEscape($evidence['POINTS']) ?> / <?= $ssEscape($evidence['MAXIMUM']) ?> · P&eacute;riode <?= $ssEscape($evidence['MARKING_PERIOD_ID']) ?></li><?php endforeach; ?>
</ul><?php endif; ?>
<?php if ($case['BILLING_MODE'] === 'paid' && User('PROFILE') === 'admin' && AllowEdit('Student_Billing/StudentPayments.php') && SchoolSafeEndpointGateway('remediation-payments.php','GET') !== 'deny'): ?><p><a href="remediation-payments.php#remediation-case-<?= (int) $case['ID'] ?>">Paiements de ce rattrapage</a></p><?php endif; ?>
<?php if ($case['BILLING_MODE']): ?><p>Rattrapage <?= $case['BILLING_MODE'] === 'free' ? 'gratuit' : 'payant' ?><?php if ($case['BILLING_MODE'] === 'paid'): ?> · Tarif <?= $ssEscape($case['TARIFF']) ?><?php if (($case['REDUCTION_PERCENT'] ?? '') !== '' && $case['REDUCTION_PERCENT'] !== null): ?> · <?= User('PROFILE') === 'parent' ? 'tarif réduit accordé' : $ssEscape('réduit de ' . (float) $case['REDUCTION_PERCENT'] . ' % (tarif de base ' . $case['BASE_TARIFF'] . ') : ' . $case['REDUCTION_REASON']) ?><?php endif; ?> · L’accompagnement ne dépend pas du paiement<?php endif; ?></p><?php endif; ?>
<?php if ($ssDirector && $ssActions['billing'] && !$case['BILLING_MODE'] && in_array($case['STATUS'], ['approved','repeat'], true)): ?>
<form method="post"><input type="hidden" name="csrf" value="<?= $ssEscape($ssToken) ?>"><input type="hidden" name="action" value="billing"><input type="hidden" name="case_id" value="<?= (int) $case['ID'] ?>">
<label>Accompagnement<select name="mode"><option value="free">Gratuit</option><option value="paid">Payant</option></select></label>
<label>Tarif dans l'unit&eacute; mon&eacute;taire SchoolSafe<input name="tariff" type="number" min="0" step="0.01" value="0" required></label>
<label>R&eacute;duction en % (facultatif)<input name="reduction" type="number" min="0" max="100" step="0.01" value="0"></label>
<label>Motif de la r&eacute;duction<input name="reduction_reason" maxlength="500" placeholder="ex. famille en difficult&eacute;, fratrie"></label>
<p>Le tarif est suivi par l'&eacute;conomat ; l'accompagnement ne d&eacute;pend jamais du paiement et l'entr&eacute;e de l'&eacute;l&egrave;ve n'est pas bloqu&eacute;e.</p><button>Enregistrer la tarification</button></form>
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
<?php $teachers = DBGet('SELECT st.staff_id,st.first_name,st.last_name,EXISTS(SELECT 1 FROM course_periods cp WHERE cp.course_id=' . (int) $case['COURSE_ID'] . ' AND cp.teacher_id=st.staff_id) AS teaches FROM staff st WHERE st.profile=\'teacher\' AND st.syear=' . $ssYear . ' AND (st.schools IS NULL OR st.schools LIKE \'%,' . $ssSchool . ',%\') ORDER BY 4 DESC,st.last_name,st.first_name LIMIT 300'); ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= $ssEscape($ssToken) ?>"><input type="hidden" name="action" value="session"><input type="hidden" name="case_id" value="<?= (int) $case['ID'] ?>">
<label>Enseignant du rattrapage<select name="teacher_id" required><?php foreach ($teachers as $teacher): $ssTeaches = $teacher['TEACHES'] === 't'; if ($ssTeaches && !empty($ssRule['other_teacher'])) continue; ?><option value="<?= (int) $teacher['STAFF_ID'] ?>"><?= $ssEscape($teacher['FIRST_NAME'] . ' ' . $teacher['LAST_NAME'] . ($ssTeaches ? ' — enseigne et note cette matière' : ' — autre enseignant')) ?></option><?php endforeach; ?></select></label>
<label>Date et heure<input name="starts_at" type="datetime-local" required></label>
<label>Dur&eacute;e en minutes<input name="duration" type="number" min="1" max="480" required></label>
<label>Objectif p&eacute;dagogique<textarea name="objective" maxlength="2000" required></textarea></label><button>Programmer la s&eacute;ance</button>
</form>
<?php endif; ?>
<?php $sessions = DBGet('SELECT * FROM schoolsafe_remediation_sessions WHERE case_id=' . (int) $case['ID'] . ' ORDER BY starts_at'); ?>
<?php foreach ($sessions as $session): ?>
<div class="session-row"><p><strong><?= $ssEscape($session['STARTS_AT']) ?></strong> · <?= (int) $session['DURATION_MINUTES'] ?> min · <?= $ssEscape($session['OBJECTIVE']) ?> (<?= $ssEscape($ssSessionLabels[$session['STATUS']] ?? $session['STATUS']) ?>)</p>
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
<?php $ssBefore = null; if ($evidenceGrades) { $ssSum = 0; foreach ($evidenceGrades as $evidence) $ssSum += (float) $evidence['POINTS'] * 100 / max(1e-9, (float) $evidence['MAXIMUM']); $ssBefore = $ssSum / count($evidenceGrades); } ?>
<p><?= $ssEscape(SchoolSafeRemediationRuleText($ssRule)['pass']) ?> Indication seulement : la d&eacute;cision reste la v&ocirc;tre.</p>
<label>Nouvelle &eacute;valuation SchoolSafe<select name="assignment_id" required><option value="">Choisir une &eacute;valuation enregistr&eacute;e</option><?php foreach ($availableGrades as $grade): ?><option value="<?= (int) $grade['ASSIGNMENT_ID'] ?>"><?= $ssEscape($grade['TITLE'] . ' : ' . $grade['POINTS'] . ' / ' . $grade['MAXIMUM'] . (SchoolSafeRemediationPassVerdict($ssRule, (float) $grade['POINTS'], (float) $grade['MAXIMUM'], $ssBefore) ? ' — atteint la règle de réussite' : ' — sous la règle de réussite')) ?></option><?php endforeach; ?></select></label>
<label>D&eacute;cision p&eacute;dagogique<select name="outcome"><option value="passed">R&eacute;ussi</option><option value="repeat">&Agrave; reprendre</option><option value="closed">Cl&ocirc;tur&eacute;</option></select></label>
<label>Motif<textarea name="note" maxlength="4000" required></textarea></label><button>Enregistrer la r&eacute;&eacute;valuation</button></form>
<?php endif; ?>
<?php $reassessments = DBGet('SELECT * FROM schoolsafe_remediation_reassessments WHERE case_id=' . (int) $case['ID'] . ' ORDER BY created_at'); ?>
<?php foreach ($reassessments as $result): ?><p>R&eacute;&eacute;valuation : <?= $ssEscape($result['POINTS']) ?> / <?= (int) $result['MAXIMUM'] ?> · <?= $ssEscape($ssLabels[$result['OUTCOME']]) ?> · <?= $ssEscape($result['NOTE']) ?></p><?php endforeach; ?>
</article>
<?php endforeach; ?>
</section>
<?php if ($ssManage): ?>
<?php require_once __DIR__ . '/../ProgramFunctions/SchoolSafeRemediationOverview.fnc.php';
$ssGet = function ($sql) { return DBGet($sql); };
$ssView = SchoolSafeRemediationOverview(SchoolSafeRemediationClassRows($ssSchool, $ssYear, $ssGet), SchoolSafeRemediationGiverRows($ssSchool, $ssYear, $ssGet));
$ssPct = static function ($v) { return $v === null ? '—' : $v . ' %'; }; ?>
<section aria-labelledby="suivi-title" class="remediation-overview"><h2 id="suivi-title">Suivi de la direction</h2>
<p>Élèves en difficulté = dossiers + alertes « vigilance ». Quand une classe dépasse 30 % (au moins 3 élèves), regardez d’abord l’enseignement (méthode, programme, appui à l’enseignant) avant le rattrapage.</p>
<h3>Par classe</h3>
<?php if (!$ssView['classes']): ?><p>Aucune classe pour cette année.</p><?php else: ?>
<table><thead><tr><th>Classe</th><th>Enseignant</th><th>Élèves</th><th>Dossiers</th><th>Vigilance</th><th>Taux</th><th>Réussis</th><th></th></tr></thead><tbody>
<?php foreach ($ssView['classes'] as $row): ?><tr><td><?= $ssEscape($row['title']) ?></td><td><?= $ssEscape($row['teacher']) ?></td><td><?= (int) $row['students'] ?></td><td><?= (int) $row['cases'] ?></td><td><?= (int) $row['watch'] ?></td><td><?= $ssEscape($ssPct($row['rate'])) ?></td><td><?= (int) $row['passed'] ?></td><td><?= $row['alert'] ? '<strong>À examiner : accompagner l’enseignement</strong>' : '' ?></td></tr><?php endforeach; ?>
</tbody></table><?php endif; ?>
<h3>Par enseignant de la matière</h3>
<?php if (!$ssView['teachers']): ?><p>Aucun enseignant.</p><?php else: ?>
<table><thead><tr><th>Enseignant</th><th>Classes</th><th>Élèves</th><th>Dossiers</th><th>Vigilance</th><th>Taux</th><th>Réussite des dossiers</th><th>Classes à examiner</th></tr></thead><tbody>
<?php foreach ($ssView['teachers'] as $row): ?><tr><td><?= $ssEscape($row['teacher']) ?></td><td><?= (int) $row['classes'] ?></td><td><?= (int) $row['students'] ?></td><td><?= (int) $row['cases'] ?></td><td><?= (int) $row['watch'] ?></td><td><?= $ssEscape($ssPct($row['rate'])) ?></td><td><?= $ssEscape($ssPct($row['success'])) ?></td><td><?= (int) $row['alerts'] ?></td></tr><?php endforeach; ?>
</tbody></table><?php endif; ?>
<h3>Qui donne le rattrapage</h3>
<?php if (!$ssView['givers']): ?><p>Aucune séance programmée pour l’instant.</p><?php else: ?>
<table><thead><tr><th>Enseignant</th><th>Dossiers suivis</th><th>Séances</th><th>Tenues</th><th>Dans sa propre matière</th></tr></thead><tbody>
<?php foreach ($ssView['givers'] as $row): ?><tr><td><?= $ssEscape($row['teacher']) ?></td><td><?= (int) $row['cases'] ?></td><td><?= (int) $row['sessions'] ?></td><td><?= (int) $row['held'] ?></td><td><?= (int) $row['own'] ?><?= $row['own'] > 0 ? ' (enseigne et note aussi la matière)' : '' ?></td></tr><?php endforeach; ?>
</tbody></table><?php endif; ?>
<p><?= $ssEscape(SchoolSafeRemediationRuleText($ssRule)['teacher']) ?></p>
</section>
<?php endif; ?>
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
