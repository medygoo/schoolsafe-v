<?php
/**
 * Notes visibles chaque jour (04/11, valide par le proprietaire) : chaque note enregistree par l'enseignant
 * arrive chez le parent (fil, e-mail, WhatsApp avec accord), une seule fois, avec le lien de la correction
 * (appreciation, corrige type, photo de la copie corrigee). L'ecole choisit : tout de suite, resume du soir
 * (18 h, heure de Kinshasa) ou pas d'envoi (config SCHOOLSAFE_GRADE_NOTIFY). Les dispenses ne sont pas
 * annoncees. Une note n'est envoyee que 5 minutes apres sa derniere modification (le temps de corriger
 * une faute de frappe).
 */
require_once __DIR__ . '/SchoolSafeNotify.fnc.php';

function SchoolSafeGradeNoticeStart(): string
{
    return '2026-11-04';
}

function SchoolSafeGradeNoticeModes(): array
{
    return ['immediat' => 'Tout de suite (quelques minutes après l’enregistrement)', 'soir' => 'Un résumé chaque soir à 18 h', 'non' => 'Ne pas envoyer (le parent consulte « Travail de mon enfant »)'];
}

function SchoolSafeGradeNoticeMode(int $school, callable $get): string
{
    $rows = $get("SELECT CONFIG_VALUE FROM config WHERE SCHOOL_ID=" . $school . " AND TITLE='SCHOOLSAFE_GRADE_NOTIFY'");
    $value = is_array($rows) && isset($rows[1]) ? strtolower(trim((string) $rows[1]['CONFIG_VALUE'])) : '';
    return isset(SchoolSafeGradeNoticeModes()[$value]) ? $value : 'immediat';
}

/** Nombre lisible : 4 ; 7,5. */
function SchoolSafeGradeNoticeNumber($value): string
{
    $text = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    return str_replace('.', ',', $text === '' || $text === '-0' ? '0' : $text);
}

/** « 4/10 (40 %) » ; « dispensé » ; vide sans note. */
function SchoolSafeGradeNoticeMark($points, $maximum): string
{
    if ($points === null || $points === '') return '';
    if ((float) $points < 0) return 'dispensé';
    $text = SchoolSafeGradeNoticeNumber($points) . '/' . SchoolSafeGradeNoticeNumber($maximum);
    return (float) $maximum > 0 ? $text . ' (' . SchoolSafeGradeNoticeNumber(round((float) $points * 100 / (float) $maximum)) . ' %)' : $text;
}

function SchoolSafeGradeNoticeTitle(string $title): string
{
    if (strpos($title, '|') === false) return trim($title);
    if (preg_match('/\|fr_FR\.utf8:([^|]*)/', $title, $m) && trim($m[1]) !== '') return trim($m[1]);
    return trim(explode('|', $title)[0]);
}

/** Une ligne : « Mathématiques — Devoir « Les fractions » du 6 novembre : 4/10 (40 %) ». */
function SchoolSafeGradeNoticeLine(array $row): string
{
    require_once __DIR__ . '/SchoolSafeLetterhead.fnc.php';
    $course = SchoolSafeGradeNoticeTitle((string) ($row['COURSE'] ?? ''));
    $kind = SchoolSafeGradeNoticeTitle((string) ($row['TYPE_TITLE'] ?? '')) ?: 'Devoir';
    $day = (string) (($row['DUE_DATE'] ?? '') ?: ($row['ASSIGNED_DATE'] ?? ''));
    $when = $day !== '' ? SchoolSafeLetterheadDate($day, false) : '';
    return ($course !== '' ? $course . ' — ' : '') . $kind . ' « ' . trim((string) $row['TITLE']) . ' »' . ($when !== '' ? ' du ' . $when : '')
        . ' : ' . SchoolSafeGradeNoticeMark($row['POINTS'], $row['MAXIMUM']);
}

/** Message d'une note (envoi immediat). */
function SchoolSafeGradeNoticeMessage(array $row, string $link): array
{
    $who = trim((string) ($row['FIRST_NAME'] ?? '')) ?: 'Votre enfant';
    $course = SchoolSafeGradeNoticeTitle((string) ($row['COURSE'] ?? ''));
    $comment = trim((string) ($row['COMMENT'] ?? ''));
    $school = SchoolSafeGradeNoticeTitle((string) ($row['SCHOOL_TITLE'] ?? ''));
    return ['Nouvelle note' . ($course !== '' ? ' : ' . $course : ''),
        '📗 Note de ' . $who . ' — ' . SchoolSafeGradeNoticeLine($row) . '.'
        . ($comment !== '' ? "\nAppréciation de l’enseignant : " . $comment : '')
        . "\nCorrection et copie : " . $link
        . "\n— " . ($school !== '' ? $school . ' · ' : '') . 'SchoolSafe · Un enfant protégé, un parent informé'];
}

/** Resume du soir : toutes les notes du jour d'un eleve. */
function SchoolSafeGradeNoticeDigest(array $rows, string $link): array
{
    $first = $rows[0];
    $who = trim((string) ($first['FIRST_NAME'] ?? '')) ?: 'votre enfant';
    $school = SchoolSafeGradeNoticeTitle((string) ($first['SCHOOL_TITLE'] ?? ''));
    $lines = [];
    foreach ($rows as $row) {
        $comment = trim((string) ($row['COMMENT'] ?? ''));
        $lines[] = '• ' . SchoolSafeGradeNoticeLine($row) . ($comment !== '' ? ' — « ' . $comment . ' »' : '');
    }
    return [count($rows) > 1 ? 'Notes du jour de ' . $who : 'Note du jour de ' . $who,
        '📗 ' . (count($rows) > 1 ? 'Notes du jour de ' : 'Note du jour de ') . $who . " :\n" . implode("\n", $lines)
        . "\nCorrections et copies : " . $link
        . "\n— " . ($school !== '' ? $school . ' · ' : '') . 'SchoolSafe · Un enfant protégé, un parent informé'];
}

/**
 * Choisit ce qui part maintenant. Retourne des lots : ['key' => dedup, 'rows' => [...], 'digest' => bool].
 * $rows : notes en attente (CHANGED_LOCAL = derniere modification, heure de Kinshasa).
 */
function SchoolSafeGradeNoticePlan(array $rows, array $modes, string $nowLocal): array
{
    $batches = [];
    $evening = substr($nowLocal, 0, 10) . ' 18:00:00';
    $digests = [];
    foreach ($rows as $row) {
        $school = (int) $row['SCHOOL_ID'];
        $mode = $modes[$school] ?? 'immediat';
        $a = (int) $row['ASSIGNMENT_ID'];
        $s = (int) $row['STUDENT_ID'];
        if ($mode === 'immediat') {
            $batches[] = ['key' => 'grade:' . $a . ':' . $s, 'rows' => [$row], 'digest' => false];
        } elseif ($mode === 'soir' && $nowLocal >= $evening && (string) $row['CHANGED_LOCAL'] < $evening) {
            $digests[$s][] = $row;
        }
    }
    foreach ($digests as $s => $list) {
        $ids = array_map(static function ($r) { return (int) $r['ASSIGNMENT_ID']; }, $list);
        sort($ids);
        $batches[] = ['key' => 'grades:' . $s . ':' . str_replace('-', '', substr($nowLocal, 0, 10)) . ':' . sprintf('%u', crc32(implode(',', $ids))), 'rows' => $list, 'digest' => true];
    }
    return $batches;
}

/** Releve les notes en attente et previent les parents. Retourne le nombre de notifications creees. */
function SchoolSafeGradeNoticeRelay(array $db, string $base, ?DateTimeImmutable $now = null, int $limit = 200): int
{
    $get = $db['get'];
    $base = rtrim($base, '/');
    $now = $now ?: new DateTimeImmutable('now', new DateTimeZone('Africa/Kinshasa'));
    $rows = $get("SELECT gg.ASSIGNMENT_ID,gg.STUDENT_ID,gg.POINTS,gg.COMMENT,ga.TITLE,ga.POINTS AS MAXIMUM,ga.DUE_DATE,ga.ASSIGNED_DATE,cp.SCHOOL_ID,cp.SYEAR,"
        . "c.TITLE AS COURSE,t.TITLE AS TYPE_TITLE,s.FIRST_NAME,sc.TITLE AS SCHOOL_TITLE,"
        . "to_char(COALESCE(gg.UPDATED_AT,gg.CREATED_AT)::timestamptz AT TIME ZONE 'Africa/Kinshasa','YYYY-MM-DD HH24:MI:SS') AS CHANGED_LOCAL"
        . " FROM gradebook_grades gg JOIN gradebook_assignments ga ON ga.ASSIGNMENT_ID=gg.ASSIGNMENT_ID"
        . " JOIN course_periods cp ON cp.COURSE_PERIOD_ID=gg.COURSE_PERIOD_ID JOIN courses c ON c.COURSE_ID=cp.COURSE_ID"
        . " JOIN students s ON s.STUDENT_ID=gg.STUDENT_ID JOIN schools sc ON sc.ID=cp.SCHOOL_ID AND sc.SYEAR=cp.SYEAR"
        . " LEFT JOIN gradebook_assignment_types t ON t.ASSIGNMENT_TYPE_ID=ga.ASSIGNMENT_TYPE_ID"
        . " WHERE gg.POINTS IS NOT NULL AND gg.POINTS>=0 AND COALESCE(gg.UPDATED_AT,gg.CREATED_AT)>='" . SchoolSafeGradeNoticeStart() . "'"
        . " AND COALESCE(gg.UPDATED_AT,gg.CREATED_AT) BETWEEN CURRENT_TIMESTAMP-interval '2 days' AND CURRENT_TIMESTAMP-interval '5 minutes'"
        . " AND NOT EXISTS (SELECT 1 FROM schoolsafe_grade_notices n WHERE n.ASSIGNMENT_ID=gg.ASSIGNMENT_ID AND n.STUDENT_ID=gg.STUDENT_ID)"
        . " ORDER BY cp.SCHOOL_ID,gg.STUDENT_ID,ga.DUE_DATE,gg.ASSIGNMENT_ID LIMIT " . max(1, min(500, $limit)));
    if (!is_array($rows) || !$rows) return 0;
    $rows = array_values($rows);
    $modes = [];
    foreach ($rows as $row) {
        $school = (int) $row['SCHOOL_ID'];
        if (!isset($modes[$school])) $modes[$school] = SchoolSafeGradeNoticeMode($school, $get);
    }
    $sent = 0;
    foreach (SchoolSafeGradeNoticePlan($rows, $modes, $now->format('Y-m-d H:i:s')) as $batch) {
        $first = $batch['rows'][0];
        $student = (int) $first['STUDENT_ID'];
        try {
            $message = $batch['digest']
                ? SchoolSafeGradeNoticeDigest($batch['rows'], $base . '/travail-enfant.php?' . http_build_query(['enfant' => $student]))
                : SchoolSafeGradeNoticeMessage($first, $base . '/note-devoir.php?' . http_build_query(['devoir' => (int) $first['ASSIGNMENT_ID'], 'eleve' => $student]));
            $recipients = SchoolSafeNotifyResolveRecipients('grade.published', (int) $first['SCHOOL_ID'], (int) $first['SYEAR'], $student, $db);
            if ($recipients) {
                $out = SchoolSafeNotifyEmit(['school_id' => (int) $first['SCHOOL_ID'], 'syear' => (int) $first['SYEAR'], 'student_id' => $student, 'actor_staff_id' => null,
                    'event_type' => 'grade.published', 'dedup_key' => $batch['key'],
                    'payload' => ['source' => 'grade', 'assignment_id' => (int) $first['ASSIGNMENT_ID'], 'title' => $message[0], 'message' => $message[1]]], $recipients, $db, true);
                if (is_array($out) && ($out['created'] ?? false)) $sent++;
            }
            foreach ($batch['rows'] as $row) {
                $db['exec']("INSERT INTO schoolsafe_grade_notices(ASSIGNMENT_ID,STUDENT_ID,DEDUP_KEY) VALUES(" . (int) $row['ASSIGNMENT_ID'] . "," . (int) $row['STUDENT_ID'] . ",'" . $batch['key'] . "') ON CONFLICT DO NOTHING");
            }
        } catch (Throwable $failure) {
            error_log('SchoolSafe grade notice: ' . $failure->getMessage()); // un envoi en echec n'arrete pas les autres
        }
    }
    return $sent;
}

/** Moyenne de la classe pour un devoir, seulement a partir de 5 notes (aucune note d'un autre enfant devinable). */
function SchoolSafeGradeNoticeClassAverage(int $assignment, callable $get): ?float
{
    $rows = $get("SELECT count(*) AS N,avg(gg.POINTS*100.0/NULLIF(ga.POINTS,0)) AS AVG FROM gradebook_grades gg JOIN gradebook_assignments ga ON ga.ASSIGNMENT_ID=gg.ASSIGNMENT_ID"
        . " WHERE gg.ASSIGNMENT_ID=" . $assignment . " AND gg.POINTS IS NOT NULL AND gg.POINTS>=0");
    $row = is_array($rows) && isset($rows[1]) ? $rows[1] : null;
    if (!$row || (int) $row['N'] < 5 || $row['AVG'] === null || $row['AVG'] === '') return null;
    return round((float) $row['AVG'], 1);
}

/** Progression : les 5 dernieres notes de l'enfant dans le meme cours, en pourcentage, de la plus ancienne a la plus recente. */
function SchoolSafeGradeNoticeTrend(int $student, int $coursePeriod, callable $get): array
{
    $rows = $get("SELECT ga.ASSIGNMENT_ID,ga.TITLE,round(gg.POINTS*100.0/ga.POINTS) AS PCT FROM gradebook_grades gg JOIN gradebook_assignments ga ON ga.ASSIGNMENT_ID=gg.ASSIGNMENT_ID"
        . " WHERE gg.STUDENT_ID=" . $student . " AND gg.COURSE_PERIOD_ID=" . $coursePeriod . " AND gg.POINTS>=0 AND ga.POINTS>0"
        . " ORDER BY COALESCE(ga.DUE_DATE,ga.ASSIGNED_DATE) DESC NULLS LAST,ga.ASSIGNMENT_ID DESC LIMIT 5");
    return array_reverse(array_values(is_array($rows) ? $rows : []));
}

/** Sens de la progression : 'hausse', 'baisse', 'stable' ou '' (pas assez de notes). */
function SchoolSafeGradeNoticeDirection(array $trend): string
{
    if (count($trend) < 2) return '';
    $last = (float) $trend[count($trend) - 1]['PCT'];
    $before = (float) $trend[count($trend) - 2]['PCT'];
    if ($last - $before >= 5) return 'hausse';
    if ($before - $last >= 5) return 'baisse';
    return 'stable';
}

/** Le corrige type n'est montre aux familles qu'apres la date de remise (personne ne copie la reponse). */
function SchoolSafeGradeNoticeAnswerVisible(?string $due, string $today, bool $staff): bool
{
    return $staff || ($due !== null && $due !== '' && $due < $today);
}
