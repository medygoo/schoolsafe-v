<?php
/**
 * Devoirs envoyes automatiquement (04/11, valide par le proprietaire) : des qu'un devoir est publie
 * (date de remise d'aujourd'hui ou deja passee), chaque parent de chaque eleve de la classe le recoit
 * (fil, e-mail, WhatsApp avec accord), une seule fois, avec le lien de la fiche A4 deja au nom de son enfant.
 * L'ecole peut couper l'envoi (config SCHOOLSAFE_HOMEWORK_AUTO = 'N'). Aucun envoi pour les devoirs anterieurs.
 */
require_once __DIR__ . '/SchoolSafeNotify.fnc.php';

/** Premier jour de l'envoi automatique : les devoirs plus anciens ne sont jamais envoyes d'un coup. */
function SchoolSafeHomeworkStart(): string
{
    return '2026-11-04';
}

/** Envoi automatique actif pour l'ecole (actif par defaut). */
function SchoolSafeHomeworkAutoEnabled(int $school, callable $get): bool
{
    $rows = $get("SELECT CONFIG_VALUE FROM config WHERE SCHOOL_ID=" . $school . " AND TITLE='SCHOOLSAFE_HOMEWORK_AUTO'");
    return !is_array($rows) || !isset($rows[1]) || strtoupper(trim((string) $rows[1]['CONFIG_VALUE'])) !== 'N';
}

/** Message au parent ; le prenom vient de la fiche de l'eleve, la date est ecrite en francais. */
function SchoolSafeHomeworkMessage(string $first, string $course, string $title, string $type, string $due, string $link, string $school): array
{
    require_once __DIR__ . '/SchoolSafeLetterhead.fnc.php';
    $who = $first !== '' ? $first : 'votre enfant';
    $kind = $type !== '' ? $type : 'Devoir';
    $when = SchoolSafeLetterheadDate($due);
    return ['Nouveau devoir : ' . ($course !== '' ? $course : $kind),
        '📘 ' . $kind . ' pour ' . $who . ($course !== '' ? ' en ' . $course : '') . ' : « ' . $title . ' ».'
        . ($when !== '' ? ' À rendre le ' . $when . '.' : '')
        . "\nFiche à imprimer, déjà au nom de " . $who . ' : ' . $link
        . "\n— " . ($school !== '' ? $school . ' · ' : '') . 'SchoolSafe · Un enfant protégé, un parent informé'];
}

/** Titre lisible d'un champ multilingue natif. */
function SchoolSafeHomeworkTitle(string $title): string
{
    if (strpos($title, '|') === false) return trim($title);
    if (preg_match('/\|fr_FR\.utf8:([^|]*)/', $title, $m) && trim($m[1]) !== '') return trim($m[1]);
    return trim(explode('|', $title)[0]);
}

/**
 * Releve les devoirs publies et previent les parents de chaque eleve inscrit dans la classe, une fois par
 * devoir et par eleve (cle « homework:<devoir>:<eleve> »). Retourne le nombre de notifications creees.
 */
function SchoolSafeHomeworkRelay(array $db, string $base, int $limit = 40): int
{
    $get = $db['get'];
    $base = rtrim($base, '/');
    $rows = $get("SELECT ga.ASSIGNMENT_ID,ga.TITLE,ga.DUE_DATE,ss.STUDENT_ID,cp.SCHOOL_ID,cp.SYEAR,c.TITLE AS COURSE,t.TITLE AS TYPE_TITLE,s.FIRST_NAME,sc.TITLE AS SCHOOL_TITLE"
        . " FROM gradebook_assignments ga"
        . " JOIN course_periods cp ON (ga.COURSE_PERIOD_ID IS NOT NULL AND cp.COURSE_PERIOD_ID=ga.COURSE_PERIOD_ID)"
        . "  OR (ga.COURSE_PERIOD_ID IS NULL AND cp.COURSE_ID=ga.COURSE_ID AND cp.TEACHER_ID=ga.STAFF_ID)"
        . " JOIN courses c ON c.COURSE_ID=cp.COURSE_ID"
        . " JOIN schedule ss ON ss.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID AND ss.START_DATE<=CURRENT_DATE AND (ss.END_DATE IS NULL OR ss.END_DATE>=CURRENT_DATE)"
        . " JOIN students s ON s.STUDENT_ID=ss.STUDENT_ID"
        . " JOIN schools sc ON sc.ID=cp.SCHOOL_ID AND sc.SYEAR=cp.SYEAR"
        . " LEFT JOIN gradebook_assignment_types t ON t.ASSIGNMENT_TYPE_ID=ga.ASSIGNMENT_TYPE_ID"
        . " WHERE ga.CREATED_AT>='" . SchoolSafeHomeworkStart() . "' AND ga.CREATED_AT>now()-interval '14 days'"
        . " AND (ga.ASSIGNED_DATE IS NULL OR ga.ASSIGNED_DATE<=CURRENT_DATE) AND (ga.DUE_DATE IS NULL OR ga.DUE_DATE>=CURRENT_DATE)"
        . " AND NOT EXISTS (SELECT 1 FROM schoolsafe_notify_events n WHERE n.DEDUP_KEY='homework:'||ga.ASSIGNMENT_ID||':'||ss.STUDENT_ID)"
        . ' ORDER BY ga.ASSIGNMENT_ID,ss.STUDENT_ID LIMIT ' . max(1, min(200, $limit)));
    $sent = 0;
    $enabled = [];
    $done = [];
    foreach ((array) $rows as $row) {
        $school = (int) $row['SCHOOL_ID'];
        $student = (int) $row['STUDENT_ID'];
        $assignment = (int) $row['ASSIGNMENT_ID'];
        if (isset($done[$assignment . ':' . $student])) continue; // un eleve inscrit deux fois dans le meme cours
        $done[$assignment . ':' . $student] = true;
        try {
            $enabled[$school] = $enabled[$school] ?? SchoolSafeHomeworkAutoEnabled($school, $get);
            if (!$enabled[$school]) continue;
            $link = $base . '/schoolsafe-homework.php?' . http_build_query(['assignment_id' => $assignment, 'student' => $student, 'download' => '1']);
            $message = SchoolSafeHomeworkMessage(trim((string) $row['FIRST_NAME']), SchoolSafeHomeworkTitle((string) $row['COURSE']), trim((string) $row['TITLE']),
                SchoolSafeHomeworkTitle((string) ($row['TYPE_TITLE'] ?? '')), (string) ($row['DUE_DATE'] ?? ''), $link, SchoolSafeHomeworkTitle((string) ($row['SCHOOL_TITLE'] ?? '')));
            $recipients = SchoolSafeNotifyResolveRecipients('homework.published', $school, (int) $row['SYEAR'], $student, $db);
            if (!$recipients) continue;
            $out = SchoolSafeNotifyEmit(['school_id' => $school, 'syear' => (int) $row['SYEAR'], 'student_id' => $student, 'actor_staff_id' => null,
                'event_type' => 'homework.published', 'dedup_key' => 'homework:' . $assignment . ':' . $student,
                'payload' => ['source' => 'homework', 'assignment_id' => $assignment, 'title' => $message[0], 'message' => $message[1]]], $recipients, $db, true);
            if (is_array($out) && ($out['created'] ?? false)) $sent++;
        } catch (Throwable $failure) {
            error_log('SchoolSafe homework relay: ' . $failure->getMessage()); // un envoi en echec n'arrete pas les autres
        }
    }
    return $sent;
}
