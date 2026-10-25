<?php
// SchoolSafe — Lot U5 : tableaux de bord par profil (LECTURE SEULE).
//
// - Direction : presences, absences, retards, alertes, notifications,
//   justificatifs a examiner (son ecole/annee uniquement).
// - Enseignant : eleves de SES classes (cours ou il enseigne), appels
//   (runs), absences/retards de ses classes.
// - Parent : historique de SES enfants lies (students_join_users).
// - Technique : sante des files, erreurs d'envoi, etats des canaux —
//   compteurs uniquement, jamais de contenu prive ni de destinataires.
// Aucune ecriture, aucune donnee inter-ecoles, aucune sanction.

function SchoolSafeDashboardsCount(array $db, $sql)
{
    $rows = $db['get']($sql);
    if (!is_array($rows) || !isset($rows[1])) return 0;
    $first = reset($rows[1]);
    return (int) $first;
}

function SchoolSafeDashboardDirection($staffId, $school, $year, array $db)
{
    $school = (int) $school;
    $year = (int) $year;
    if ($school < 1 || $year < 1 || (int) $staffId < 1) throw new RuntimeException('INVALID_CONTEXT');
    $today = date('Y-m-d');
    return [
        'absences_provisoires_jour' => SchoolSafeDashboardsCount($db,
            "SELECT COUNT(*) FROM schoolsafe_absence_events WHERE SCHOOL_ID=$school"
            . " AND SYEAR=$year AND SCHOOL_DATE='$today' AND STATUS='provisional'"),
        'retards_resolus_jour' => SchoolSafeDashboardsCount($db,
            "SELECT COUNT(*) FROM schoolsafe_absence_events WHERE SCHOOL_ID=$school"
            . " AND SYEAR=$year AND SCHOOL_DATE='$today' AND STATUS='resolved_arrived'"),
        'alertes_p1_ouvertes' => SchoolSafeDashboardsCount($db,
            "SELECT COUNT(*) FROM schoolsafe_p1_alerts WHERE SCHOOL_ID=$school"
            . " AND SYEAR=$year AND STATUS='open'"),
        'justificatifs_a_examiner' => SchoolSafeDashboardsCount($db,
            'SELECT COUNT(*) FROM schoolsafe_justifications j'
            . ' JOIN schoolsafe_case_files f ON f.ID=j.FILE_ID'
            . " WHERE f.SCHOOL_ID=$school AND f.SYEAR=$year AND j.STATUS='pending'"),
        'notifications_en_attente' => SchoolSafeDashboardsCount($db,
            "SELECT COUNT(*) FROM schoolsafe_notify_deliveries WHERE SCHOOL_ID=$school"
            . " AND SYEAR=$year AND STATUS='pending'"),
        'notifications_en_erreur' => SchoolSafeDashboardsCount($db,
            "SELECT COUNT(*) FROM schoolsafe_notify_deliveries WHERE SCHOOL_ID=$school"
            . " AND SYEAR=$year AND STATUS='failed'"),
    ];
}

function SchoolSafeDashboardTeacher($staffId, $school, $year, array $db)
{
    $staffId = (int) $staffId;
    $school = (int) $school;
    $year = (int) $year;
    if ($staffId < 1 || $school < 1 || $year < 1) throw new RuntimeException('INVALID_CONTEXT');
    $today = date('Y-m-d');
    $cpRows = $db['get']('SELECT COURSE_PERIOD_ID FROM course_periods WHERE TEACHER_ID='
        . $staffId . " AND SCHOOL_ID=$school AND SYEAR=$year");
    $cps = [];
    if (is_array($cpRows)) foreach ($cpRows as $r) {
        if (isset($r['COURSE_PERIOD_ID'])) $cps[] = (int) $r['COURSE_PERIOD_ID'];
    }
    if (!$cps) return ['classes' => 0, 'eleves' => 0, 'absences_jour' => 0, 'retards_jour' => 0, 'appels_jour' => 0];
    $in = implode(',', $cps);
    $students = $db['get']('SELECT DISTINCT sc.STUDENT_ID FROM schedule sc'
        . " WHERE sc.COURSE_PERIOD_ID IN ($in) AND sc.SCHOOL_ID=$school AND sc.SYEAR=$year");
    $sids = [];
    if (is_array($students)) foreach ($students as $s) {
        if (isset($s['STUDENT_ID'])) $sids[] = (int) $s['STUDENT_ID'];
    }
    $abs = 0; $late = 0;
    if ($sids) {
        $sin = implode(',', $sids);
        $abs = SchoolSafeDashboardsCount($db,
            'SELECT COUNT(*) FROM schoolsafe_absence_events WHERE SCHOOL_ID='
            . "$school AND SYEAR=$year AND SCHOOL_DATE='$today' AND STATUS='provisional'"
            . " AND STUDENT_ID IN ($sin)");
        $late = SchoolSafeDashboardsCount($db,
            'SELECT COUNT(*) FROM schoolsafe_absence_events WHERE SCHOOL_ID='
            . "$school AND SYEAR=$year AND SCHOOL_DATE='$today' AND STATUS='resolved_arrived'"
            . " AND STUDENT_ID IN ($sin)");
    }
    return ['classes' => count($cps), 'eleves' => count($sids),
        'absences_jour' => $abs, 'retards_jour' => $late,
        'appels_jour' => SchoolSafeDashboardsCount($db,
            'SELECT COUNT(*) FROM schoolsafe_attendance_runs WHERE SCHOOL_ID='
            . "$school AND SYEAR=$year AND SCHOOL_DATE='$today'")];
}

function SchoolSafeDashboardParent($staffId, $school, $year, array $db, $limit = 50)
{
    $staffId = (int) $staffId;
    $school = (int) $school;
    $year = (int) $year;
    $limit = max(1, min(200, (int) $limit));
    if ($staffId < 1 || $school < 1 || $year < 1) throw new RuntimeException('INVALID_CONTEXT');
    $kids = $db['get']('SELECT DISTINCT u.STUDENT_ID, s.FIRST_NAME, s.LAST_NAME'
        . ' FROM students_join_users u JOIN student_enrollment e ON e.STUDENT_ID=u.STUDENT_ID'
        . ' JOIN students s ON s.STUDENT_ID=u.STUDENT_ID'
        . ' WHERE u.STAFF_ID=' . $staffId . " AND e.SCHOOL_ID=$school AND e.SYEAR=$year");
    $out = [];
    if (is_array($kids)) foreach ($kids as $k) {
        if (!isset($k['STUDENT_ID'])) continue;
        $sid = (int) $k['STUDENT_ID'];
        $hist = $db['get']('SELECT SCHOOL_DATE, STATUS, LATE_MINUTES FROM schoolsafe_absence_events'
            . " WHERE SCHOOL_ID=$school AND SYEAR=$year AND STUDENT_ID=$sid"
            . " ORDER BY SCHOOL_DATE DESC LIMIT $limit");
        $files = $db['get']('SELECT SCHOOL_DATE, STATUS FROM schoolsafe_case_files'
            . " WHERE SCHOOL_ID=$school AND SYEAR=$year AND STUDENT_ID=$sid"
            . " ORDER BY SCHOOL_DATE DESC LIMIT $limit");
        $out[] = ['student_id' => $sid,
            'name' => trim(($k['FIRST_NAME'] ?? '') . ' ' . ($k['LAST_NAME'] ?? '')),
            'absences' => is_array($hist) ? array_values($hist) : [],
            'dossiers' => is_array($files) ? array_values($files) : []];
    }
    return $out;
}

function SchoolSafeDashboardTech($school, $year, array $db)
{
    $school = (int) $school;
    $year = (int) $year;
    if ($school < 1 || $year < 1) throw new RuntimeException('INVALID_CONTEXT');
    $byStatus = $db['get']('SELECT STATUS, COUNT(*) AS C FROM schoolsafe_notify_deliveries'
        . " WHERE SCHOOL_ID=$school AND SYEAR=$year GROUP BY STATUS");
    $byChannel = $db['get']('SELECT CHANNEL, STATUS, COUNT(*) AS C FROM schoolsafe_notify_deliveries'
        . " WHERE SCHOOL_ID=$school AND SYEAR=$year GROUP BY CHANNEL, STATUS");
    $errors = $db['get']('SELECT CHANNEL, LAST_ERROR, COUNT(*) AS C FROM schoolsafe_notify_deliveries'
        . " WHERE SCHOOL_ID=$school AND SYEAR=$year AND STATUS='failed'"
        . ' AND LAST_ERROR<> ' . "''" . ' GROUP BY CHANNEL, LAST_ERROR ORDER BY C DESC LIMIT 20');
    $toMap = function ($rows, $key) {
        $m = [];
        if (is_array($rows)) foreach ($rows as $r) {
            if (isset($r[$key], $r['C'])) $m[(string) $r[$key]] = (int) $r['C'];
        }
        return $m;
    };
    return ['par_statut' => $toMap($byStatus, 'STATUS'),
        'par_canal_statut' => is_array($byChannel) ? array_values($byChannel) : [],
        'erreurs' => is_array($errors) ? array_values($errors) : [],
        'alertes_p1_ouvertes' => SchoolSafeDashboardsCount($db,
            "SELECT COUNT(*) FROM schoolsafe_p1_alerts WHERE SCHOOL_ID=$school"
            . " AND SYEAR=$year AND STATUS='open'")];
}
