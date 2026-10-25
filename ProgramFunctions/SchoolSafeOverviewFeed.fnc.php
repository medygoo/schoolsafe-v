<?php
function SchoolSafeOverviewFeed(): array
{
    $data = ['events'=>[], 'activities'=>[], 'presence'=>[], 'staff_present'=>null];
    $school=(int)UserSchool(); $year=(int)UserSyear(); $staff=(int)User('STAFF_ID');
    $profile=User('PROFILE');
    if($school<1 || $year<1 || $staff<1 || !in_array($profile,['admin','teacher','parent'],true)) return $data;
    // Mirror the portal's publication and linked-child filters, limited to the selected school.
    $scope="pn.school_id=$school AND pn.syear=$year";
    if($profile==='parent') $scope.=" AND pn.school_id IN (SELECT se.school_id FROM students_join_users sju JOIN student_enrollment se USING(student_id)
        WHERE sju.staff_id=$staff AND se.syear=$year AND se.start_date<=CURRENT_DATE AND (se.end_date IS NULL OR se.end_date>=CURRENT_DATE))";
    if(AllowUse('School_Setup/Calendar.php')) $data['events']=DBGet("SELECT title,school_date FROM calendar_events pn WHERE $scope
        AND school_date BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '30 DAY' ORDER BY school_date,id LIMIT 6");
    $data['activities']=DBGet("SELECT pn.title,pn.created_at FROM portal_notes pn JOIN staff st ON st.staff_id=$staff
        WHERE $scope AND (pn.start_date IS NULL OR pn.start_date<=CURRENT_DATE) AND (pn.end_date IS NULL OR pn.end_date>=CURRENT_DATE)
        AND (st.schools IS NULL OR position(CONCAT(',',pn.school_id,',') IN st.schools)>0)
        AND ((st.profile_id IS NULL AND position(',$profile,' IN pn.published_profiles)>0)
            OR (st.profile_id IS NOT NULL AND position(CONCAT(',',st.profile_id,',') IN pn.published_profiles)>0))
        ORDER BY pn.created_at DESC,pn.id DESC LIMIT 6");
    if($profile==='admin' && AllowUse('Users/User.php') && AllowUse('Entry_Exit/Records.php')) {
        // Records have no school_id: only single-school staff can be attributed safely.
        $data['presence']=DBGet("SELECT s.staff_id,r.type FROM staff s LEFT JOIN LATERAL
            (SELECT type FROM entry_exit_staff_records WHERE staff_id=s.staff_id
                AND recorded_at>=CURRENT_DATE AND recorded_at<=CURRENT_TIMESTAMP ORDER BY recorded_at DESC,id DESC LIMIT 1) r ON TRUE
            WHERE s.syear=$year AND s.profile='teacher' AND s.current_school_id=$school AND s.schools=',$school,'");
        $data['staff_present']=count(array_filter($data['presence'],static function($r){return $r['TYPE']==='in';}));
    }
    return $data;
}
