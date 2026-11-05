<?php
// Entry_Exit — presence du personnel (empreinte + pointage manuel).

// Hors fenetre horaire du point d'acces = pointage non autorise (enregistre quand meme, marque Y).
function EntryExitIsUnauthorized($checkpointId, $time)
{
    $checkpointId = (int) $checkpointId;
    $point = DBGet("SELECT authorized_from, authorized_to FROM entry_exit_checkpoints WHERE id=" . $checkpointId);
    if (!$point) return false;
    $from = reset($point)['AUTHORIZED_FROM'];
    $to = reset($point)['AUTHORIZED_TO'];
    if (empty($from) || empty($to)) return false; // pas de fenetre definie = toujours autorise
    return $time < $from || $time > $to;
}

// Les enregistrements de presence sur une periode.
function EntryExitStaffRecords($start, $end, $staffId = 0)
{
    $start = DBEscapeString($start);
    $end = DBEscapeString($end);
    $where = "WHERE r.recorded_at::date BETWEEN '" . $start . "' AND '" . $end . "'";
    if ($staffId) $where .= " AND r.staff_id=" . (int) $staffId;
    // SchoolSafe lot S1 (S10, 05/11/2026) : seulement le personnel de l'école en cours.
    $where .= " AND (s.current_school_id='" . (int) UserSchool() . "' OR POSITION('," . (int) UserSchool() . ",' IN s.schools) > 0)";
    return DBGet("SELECT r.id, r.staff_id,
        s.first_name || ' ' || s.last_name AS FULL_NAME,
        c.title AS CHECKPOINT,
        r.type AS TYPE,
        CASE WHEN r.unauthorized='Y' THEN 'Hors horaires' ELSE 'OK' END AS STATUT,
        TO_CHAR(r.recorded_at, 'DD/MM/YYYY HH24:MI') AS RECORDED
        FROM entry_exit_staff_records r
        JOIN staff s ON s.staff_id = r.staff_id
        LEFT JOIN entry_exit_checkpoints c ON c.id = r.checkpoint_id
        " . $where . " ORDER BY r.recorded_at DESC");
}

// Le resume journalier : entree / sortie par personnel.
function EntryExitStaffDailySummary($date)
{
    $date = DBEscapeString($date);
    return DBGet("SELECT s.staff_id,
        s.first_name || ' ' || s.last_name AS FULL_NAME,
        TO_CHAR(MIN(r.recorded_at), 'HH24:MI') AS ENTREE,
        TO_CHAR(MAX(CASE WHEN r.type='out' THEN r.recorded_at END), 'HH24:MI') AS SORTIE,
        COUNT(*) AS POINTAGES
        FROM entry_exit_staff_records r JOIN staff s ON s.staff_id = r.staff_id
        WHERE r.recorded_at::date = '" . $date . "'
        GROUP BY s.staff_id, s.first_name, s.last_name ORDER BY FULL_NAME");
}

// La vue annuelle : les jours de presence par personnel.
function EntryExitStaffYearSummary($year)
{
    $year = (int) $year;
    return DBGet("SELECT s.staff_id,
        s.first_name || ' ' || s.last_name AS FULL_NAME,
        TO_CHAR(MIN(r.recorded_at), 'DD/MM') AS PREMIERE,
        TO_CHAR(MAX(r.recorded_at), 'DD/MM') AS DERNIERE,
        COUNT(DISTINCT r.recorded_at::date) AS JOURS_PRESENTS
        FROM entry_exit_staff_records r JOIN staff s ON s.staff_id = r.staff_id
        WHERE EXTRACT(YEAR FROM r.recorded_at)=" . $year . "
        GROUP BY s.staff_id, s.first_name, s.last_name ORDER BY FULL_NAME");
}
