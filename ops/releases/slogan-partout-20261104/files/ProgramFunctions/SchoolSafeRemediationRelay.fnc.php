<?php
/**
 * Rattrapage -> parents (04/11, valide par le proprietaire). Les evenements du rattrapage (detection,
 * decision, seances, reevaluation, facturation, paiement) sont relayes vers la chaine de notification
 * SchoolSafe (fil, e-mail, WhatsApp avec accord), adressee aux parents lies a l'eleve. Les declencheurs
 * SQL du rattrapage ne sont pas modifies ; chaque evenement n'est relaye qu'une fois (cle de dedoublonnage).
 */
require_once __DIR__ . '/SchoolSafeNotify.fnc.php';

/** Message au parent pour un evenement ; null = evenement interne (non relaye). */
function SchoolSafeRemediationMessage(string $kind, string $first, string $course, array $case): ?array
{
    $who = $first !== '' ? $first : 'votre enfant';
    $in = $course !== '' ? ' en ' . $course : '';
    switch ($kind) {
        case 'detected':
            return ['Rattrapage proposé', '📚 Un accompagnement de rattrapage est proposé pour ' . $who . $in . ' : trois évaluations récentes sont sous 50 %. La direction pédagogique vous informera de la suite.'];
        case 'approved':
            return ['Rattrapage confirmé', '✅ Le rattrapage de ' . $who . $in . ' est confirmé. Les séances vous seront communiquées.'];
        case 'refused':
            return ['Rattrapage non retenu', 'Le rattrapage proposé pour ' . $who . $in . ' n’est pas retenu par la direction pédagogique.'];
        case 'session_scheduled':
            return ['Séance de rattrapage', '🗓️ Une séance de rattrapage est programmée pour ' . $who . $in . '. Les détails sont dans votre espace parent.'];
        case 'session_held':
            return ['Séance de rattrapage tenue', 'La séance de rattrapage de ' . $who . $in . ' a eu lieu.'];
        case 'session_postponed':
            return ['Séance reportée', 'La séance de rattrapage de ' . $who . $in . ' est reportée. Une nouvelle date vous sera communiquée.'];
        case 'session_cancelled':
            return ['Séance annulée', 'La séance de rattrapage de ' . $who . $in . ' est annulée.'];
        case 'reassessment_passed':
            return ['Rattrapage réussi', '🎉 ' . $who . ' a réussi son rattrapage' . $in . '. Félicitations !'];
        case 'reassessment_repeat':
            return ['Rattrapage à reprendre', 'Le rattrapage de ' . $who . $in . ' doit être repris. La direction pédagogique vous en dira plus.'];
        case 'reassessment_closed':
            return ['Rattrapage clôturé', 'Le dossier de rattrapage de ' . $who . $in . ' est clôturé.'];
        case 'billing_configured':
            $paid = ($case['BILLING_MODE'] ?? '') === 'paid';
            $amount = $paid && is_numeric($case['TARIFF'] ?? null) ? ' (' . number_format((float) $case['TARIFF'], 0, ',', ' ') . ')' : '';
            return ['Rattrapage : participation', 'Le rattrapage de ' . $who . $in . ' est ' . ($paid ? 'payant' . $amount . ' ; le paiement se fait à l’économat. L’accompagnement ne dépend pas du paiement.' : 'gratuit.')];
        case 'payment_confirmed':
        case 'payment_recorded':
            return ['Paiement du rattrapage', 'Le paiement du rattrapage de ' . $who . $in . ' est bien reçu. Merci.'];
        default:
            return null; // revue de paiement, reconfirmation, essais : internes a l'ecole
    }
}

/** Titre lisible d'un champ multilingue natif. */
function SchoolSafeRemediationTitle(string $title): string
{
    if (strpos($title, '|') === false) return trim($title);
    if (preg_match('/\|fr_FR\.utf8:([^|]*)/', $title, $m) && trim($m[1]) !== '') return trim($m[1]);
    return trim(explode('|', $title)[0]);
}

/** Relaie les evenements des 30 derniers jours non encore relayes. Retourne le nombre de notifications creees. */
function SchoolSafeRemediationRelay(array $db, int $limit = 40): int
{
    $get = $db['get'];
    $rows = $get("SELECT e.ID,e.KIND,e.ACTOR_ID,c.STUDENT_ID,c.SCHOOL_ID,c.SYEAR,c.BILLING_MODE,c.TARIFF,s.FIRST_NAME,co.TITLE AS COURSE"
        . " FROM schoolsafe_remediation_events e JOIN schoolsafe_remediation_cases c ON c.ID=e.CASE_ID"
        . " JOIN students s ON s.STUDENT_ID=c.STUDENT_ID LEFT JOIN courses co ON co.COURSE_ID=c.COURSE_ID"
        . " WHERE e.CREATED_AT>now()-interval '30 days'"
        . " AND NOT EXISTS (SELECT 1 FROM schoolsafe_notify_events n WHERE n.DEDUP_KEY='remediation:event:'||e.ID)"
        . ' ORDER BY e.ID LIMIT ' . max(1, min(200, $limit)));
    $sent = 0;
    foreach ((array) $rows as $row) {
        $message = SchoolSafeRemediationMessage((string) $row['KIND'], trim((string) $row['FIRST_NAME']), SchoolSafeRemediationTitle((string) $row['COURSE']), $row);
        if ($message === null) continue;
        try {
            $recipients = SchoolSafeNotifyResolveRecipients('remediation.update', (int) $row['SCHOOL_ID'], (int) $row['SYEAR'], (int) $row['STUDENT_ID'], $db);
            if (!$recipients) continue;
            $out = SchoolSafeNotifyEmit(['school_id' => (int) $row['SCHOOL_ID'], 'syear' => (int) $row['SYEAR'], 'student_id' => (int) $row['STUDENT_ID'],
                'actor_staff_id' => $row['ACTOR_ID'] !== null && $row['ACTOR_ID'] !== '' ? (int) $row['ACTOR_ID'] : null,
                'event_type' => 'remediation.update', 'dedup_key' => 'remediation:event:' . (int) $row['ID'],
                'payload' => ['source' => 'remediation', 'title' => $message[0], 'message' => $message[1] . "\n— SchoolSafe · Un enfant protégé, un parent informé"]], $recipients, $db, true);
            if (is_array($out) && ($out['created'] ?? false)) $sent++;
        } catch (Throwable $failure) {
            error_log('SchoolSafe remediation relay: ' . $failure->getMessage()); // un evenement en echec n'arrete pas les autres
        }
    }
    return $sent + SchoolSafeRemediationWatchRelay($db, $limit);
}

/** Message « vigilance » au parent (au moins 2 notes sous le seuil, sans dossier). */
function SchoolSafeRemediationWatchMessage(string $first, string $course, int $failures, float $threshold): array
{
    $who = $first !== '' ? $first : 'votre enfant';
    return ['Vigilance', '👀 ' . $who . ' a ' . $failures . ' évaluations récentes sous ' . SchoolSafeRemediationPct($threshold)
        . ($course !== '' ? ' en ' . $course : '') . '. Aucun dossier n’est ouvert : un échange avec l’enseignant peut l’aider à remonter.'];
}

/** Relaie les alertes « vigilance » actives des 30 derniers jours, une fois chacune. */
function SchoolSafeRemediationWatchRelay(array $db, int $limit = 40): int
{
    require_once __DIR__ . '/SchoolSafeRemediationSettings.fnc.php';
    $get = $db['get'];
    $rows = $get("SELECT w.ID,w.STUDENT_ID,w.SCHOOL_ID,w.SYEAR,w.FAILURES,s.FIRST_NAME,co.TITLE AS COURSE"
        . " FROM schoolsafe_remediation_watch w JOIN students s ON s.STUDENT_ID=w.STUDENT_ID LEFT JOIN courses co ON co.COURSE_ID=w.COURSE_ID"
        . " WHERE NOT w.CLEARED AND w.CREATED_AT>now()-interval '30 days'"
        . " AND NOT EXISTS (SELECT 1 FROM schoolsafe_remediation_cases c WHERE c.STUDENT_ID=w.STUDENT_ID AND c.COURSE_ID=w.COURSE_ID AND c.SCHOOL_ID=w.SCHOOL_ID AND c.SYEAR=w.SYEAR)"
        . " AND NOT EXISTS (SELECT 1 FROM schoolsafe_notify_events n WHERE n.DEDUP_KEY='remediation:watch:'||w.ID)"
        . ' ORDER BY w.ID LIMIT ' . max(1, min(200, $limit)));
    $sent = 0;
    $rules = [];
    foreach ((array) $rows as $row) {
        $school = (int) $row['SCHOOL_ID'];
        try {
            $rules[$school] = $rules[$school] ?? SchoolSafeRemediationSettings($school, $get);
            if (!$rules[$school]['vigilance']) continue;
            $message = SchoolSafeRemediationWatchMessage(trim((string) $row['FIRST_NAME']), SchoolSafeRemediationTitle((string) $row['COURSE']),
                (int) $row['FAILURES'], (float) $rules[$school]['threshold_percent']);
            $recipients = SchoolSafeNotifyResolveRecipients('remediation.update', $school, (int) $row['SYEAR'], (int) $row['STUDENT_ID'], $db);
            if (!$recipients) continue;
            $out = SchoolSafeNotifyEmit(['school_id' => $school, 'syear' => (int) $row['SYEAR'], 'student_id' => (int) $row['STUDENT_ID'],
                'actor_staff_id' => null, 'event_type' => 'remediation.update', 'dedup_key' => 'remediation:watch:' . (int) $row['ID'],
                'payload' => ['source' => 'remediation', 'title' => $message[0], 'message' => $message[1] . "\n— SchoolSafe · Un enfant protégé, un parent informé"]], $recipients, $db, true);
            if (is_array($out) && ($out['created'] ?? false)) $sent++;
        } catch (Throwable $failure) {
            error_log('SchoolSafe remediation watch relay: ' . $failure->getMessage());
        }
    }
    return $sent;
}
