<?php
function SchoolSafeRemediationParentInbox(array $principalIds, array $nativeIds, int $user, int $school, int $year): array
{
    $ids = [];
    foreach ($principalIds as $id) {
        if (!is_string($id) || !preg_match('/^[1-9][0-9]{0,8}$/D', $id)
            || !in_array($id, $nativeIds, true)) {
            throw new RuntimeException('INVALID_PRINCIPAL_SCOPE');
        }
        $ids[] = (int) $id;
    }
    if (!$ids) return [];
    $rows = DBGet('SELECT e.id,e.kind,e.created_at,r.id AS case_id,r.student_id,c.title AS course_title,s.first_name,s.last_name'
        . ' FROM schoolsafe_remediation_events e JOIN schoolsafe_remediation_cases r ON r.id=e.case_id'
        . ' JOIN courses c ON c.course_id=r.course_id JOIN students s ON s.student_id=r.student_id'
        . ' WHERE r.school_id=' . $school . ' AND r.syear=' . $year
        . ' AND r.student_id IN (' . implode(',', $ids) . ')'
        . ' AND EXISTS(SELECT 1 FROM students_join_users su JOIN staff st ON st.staff_id=su.staff_id'
        . ' WHERE su.student_id=r.student_id AND su.staff_id=' . $user . " AND st.profile='parent')"
        . ' AND EXISTS(SELECT 1 FROM schoolsafe_remediation_notifications n WHERE n.event_id=e.id'
        . " AND n.audience='principal_parent' AND n.recipient_id=" . $user . ') ORDER BY e.created_at DESC,e.id DESC LIMIT 100');
    $labels = ['detected'=>'Difficulte scolaire detectee','approved'=>'Rattrapage valide','refused'=>'Rattrapage refuse',
        'session_scheduled'=>'Seance programmee','session_held'=>'Seance tenue','session_postponed'=>'Seance reportee',
        'session_cancelled'=>'Seance annulee','reassessment_passed'=>'Reevaluation reussie',
        'reassessment_repeat'=>'Accompagnement a reprendre','reassessment_closed'=>'Dossier cloture',
        'billing_configured'=>'Tarification du rattrapage definie','payment_recorded'=>'Paiement enregistre, confirmation en attente','payment_confirmed'=>'Paiement confirme',
        'payment_reconfirmed'=>'Paiement reconfirme apres correction','payment_review_required'=>'Paiement a reverifier'];
    return array_map(static function ($row) use ($labels) {
        return ['id'=>'remediation:' . $row['ID'], 'student_id'=>(string) $row['STUDENT_ID'],
            'student_name'=>$row['FIRST_NAME'] . ' ' . $row['LAST_NAME'],
            'event_type'=>'remediation.' . $row['KIND'], 'created_at'=>$row['CREATED_AT'],
            'payload'=>['message'=>$labels[$row['KIND']] ?? 'Dossier de rattrapage mis a jour',
                'course'=>ParseMLField($row['COURSE_TITLE']), 'case_id'=>(int) $row['CASE_ID']]];
    }, array_values($rows));
}
