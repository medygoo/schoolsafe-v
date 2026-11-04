<?php
// Enfants dont l'utilisateur est le responsable principal actif (04/11). Source native : la fiche
// famille (schoolsafe_pickup_guardians, pere/mere/tuteur principal et actif) — plus aucun appel au
// service de pont des essais (codex-card-bridge-http), absent en production.
function SchoolSafeRemediationPrincipalScope(int $user, int $school, int $year): array
{
    $rows=DBGet('SELECT DISTINCT su.student_id FROM students_join_users su JOIN student_enrollment e ON e.student_id=su.student_id'
        . ' JOIN schoolsafe_pickup_guardians g ON g.student_id=su.student_id AND g.staff_id=su.staff_id AND g.school_id=e.school_id'
        . " AND g.guardian_type IN ('pere','mere','tuteur') AND g.is_primary AND g.is_active"
        . ' WHERE su.staff_id=' . $user . ' AND e.school_id=' . $school . ' AND e.syear=' . $year);
    $ids=array_values(array_map(static function($row){return (int)$row['STUDENT_ID'];},(array)$rows));
    if (count($ids)>500) throw new RuntimeException('PRINCIPAL_SCOPE_UNAVAILABLE');
    return $ids;
}
