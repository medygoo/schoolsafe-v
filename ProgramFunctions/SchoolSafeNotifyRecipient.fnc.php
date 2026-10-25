<?php
// Strict notification readers of the EXISTING native identities, enrollment,
// parent relationships and account/school states. No new permission/state store.
require_once __DIR__.'/SchoolSafeAccountState.fnc.php';
require_once __DIR__.'/SchoolSafeSchoolState.fnc.php';

function SchoolSafeNotifyAccountReadable($username,int $school,array $db): bool
{
    if($school<1)throw new RuntimeException('INVALID_CONTEXT');
    $meta=$db['get']("SELECT to_regclass('public.schoolsafe_account_state') AS ACCOUNT_TABLE,to_regclass('public.schoolsafe_school_state') AS SCHOOL_TABLE");
    if(!is_array($meta)||count($meta)!==1||!array_key_exists('ACCOUNT_TABLE',$meta[1])||!array_key_exists('SCHOOL_TABLE',$meta[1]))throw new RuntimeException('NOTIFY_RECIPIENT_UNAVAILABLE');
    if((string)$meta[1]['SCHOOL_TABLE']!==''){
        $rows=$db['get']('SELECT STATE,UNTIL FROM schoolsafe_school_state WHERE SCHOOL_ID='.$school);
        if(!is_array($rows)||count($rows)>1)throw new RuntimeException('NOTIFY_RECIPIENT_UNAVAILABLE');
        if($rows){
            $row=$rows[1];
            if(!in_array($row['STATE']??null,['active','suspended','inactive'],true)||!array_key_exists('UNTIL',$row))throw new RuntimeException('NOTIFY_RECIPIENT_UNAVAILABLE');
            if(SchoolSafeSchoolStateApplyUntil($row['STATE'],$row['UNTIL'])!=='active')return false;
        }
    }
    // Native 014 is explicitly inert when absent. A failed probe/read is NOT
    // absence and never becomes permission. Do not use fail-open cached readers.
    if((string)$meta[1]['ACCOUNT_TABLE']==='')return true;
    if(!is_string($username)||trim($username)==='')return false;
    $person=SchoolSafeAccountPerson($username);
    $rows=$db['get']("SELECT SCHOOL_ID,STATE,UNTIL FROM schoolsafe_account_state WHERE PERSON_KEY='".str_replace("'","''",$person)."' AND SCHOOL_ID IN (0,".$school.')');
    if(!is_array($rows))throw new RuntimeException('NOTIFY_RECIPIENT_UNAVAILABLE');
    $states=[];
    foreach($rows as$row){
        $id=(int)($row['SCHOOL_ID']??-1);
        if(!in_array($id,[0,$school],true)||isset($states[$id])||!in_array($row['STATE']??null,['active','readonly','suspended','expired'],true)||!array_key_exists('UNTIL',$row))throw new RuntimeException('NOTIFY_RECIPIENT_UNAVAILABLE');
        $states[$id]=SchoolSafeAccountApplyUntil($row['STATE'],$row['UNTIL']);
    }
    // Match the native session/access guard: a global suspension cuts every
    // school even if a local active override exists. Readonly may still read.
    if(in_array($states[0]??'active',['suspended','expired'],true))return false;
    return in_array($states[$school]??$states[0]??'active',['active','readonly'],true);
}

function SchoolSafeNotifyParentRows(int $school,int $year,int $student,array $db,?int $parent=null): array
{
    if(min($school,$year)<1||$student<0||($parent!==null&&$parent<1))throw new RuntimeException('INVALID_CONTEXT');
    $rows=$db['get']('SELECT DISTINCT st.STAFF_ID,st.USERNAME FROM students_join_users su JOIN staff st ON st.STAFF_ID=su.STAFF_ID'
        .' JOIN student_enrollment se ON se.STUDENT_ID=su.STUDENT_ID AND se.SCHOOL_ID='.$school.' AND se.SYEAR='.$year
        .' JOIN schools sc ON sc.ID=se.SCHOOL_ID AND sc.SYEAR=se.SYEAR'
        ." WHERE st.PROFILE='parent' AND st.SYEAR=".$year
        .($student>0?' AND su.STUDENT_ID='.$student:'').($parent!==null?' AND st.STAFF_ID='.$parent:''));
    if(!is_array($rows))throw new RuntimeException('NOTIFY_RECIPIENT_UNAVAILABLE');
    $allowed=[];
    foreach($rows as$row){
        if(!isset($row['STAFF_ID'])||(int)$row['STAFF_ID']<1||!array_key_exists('USERNAME',$row))throw new RuntimeException('NOTIFY_RECIPIENT_UNAVAILABLE');
        if(SchoolSafeNotifyAccountReadable($row['USERNAME'],$school,$db))$allowed[]=$row;
    }
    return $allowed;
}

// Called again at consumption, not just at queue creation. student=0 is only
// for native school-wide announcements; child events always supply their ID.
function SchoolSafeNotifyParentCurrent(int $parent,int $school,int $year,int $student,array $db): bool
{
    return count(SchoolSafeNotifyParentRows($school,$year,$student,$db,$parent))>0;
}
