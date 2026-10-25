<?php
if ( ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' ) { http_response_code(405); exit; }
$id = filter_input(INPUT_GET, 'assignment_id', FILTER_VALIDATE_INT);
if (!$id || $id<1) { http_response_code(400); exit('Devoir invalide.'); }
header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
chdir(__DIR__);
$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level(); ob_start(); require_once 'Warehouse.php';
while(ob_get_level()>$level){ob_end_clean();}
require_once 'ProgramFunctions/SchoolSafeSchoolDocument.fnc.php';
require_once 'modules/Grades/includes/StudentAssignments.fnc.php';
$profile=User('PROFILE');
$delegated=false;
if($profile==='admin' && AllowUse('Users/TeacherPrograms.php&include=Grades/Assignments.php') && UserStaffID() && UserCoursePeriod()){
 $context=DBGetOne("SELECT 1 FROM course_periods cp JOIN staff teacher ON teacher.staff_id=cp.teacher_id WHERE cp.course_period_id='".(int)UserCoursePeriod()."' AND cp.teacher_id='".(int)UserStaffID()."' AND cp.school_id='".(int)UserSchool()."' AND cp.syear='".(int)UserSyear()."' AND teacher.profile='teacher'");
 if($context){UserImpersonateTeacher(UserStaffID());$profile=User('PROFILE');$delegated=true;}
}
$teacher=$profile==='teacher';
$allowed=$delegated || AllowUse($teacher?'Grades/Assignments.php':'Grades/StudentAssignments.php');
if($profile==='parent'){
 $allowed=$allowed && UserStudentID() && DBGetOne("SELECT 1 FROM students_join_users WHERE student_id='".(int)UserStudentID()."' AND staff_id='".(int)User('STAFF_ID')."'");
}elseif($profile==='student'){
 $allowed=$allowed && UserStudentID() && (int)UserStudentID()===(int)User('STUDENT_ID');
}elseif(!$teacher){$allowed=false;}
if(!$allowed){http_response_code(403);exit('Accès au devoir non autorisé.');}
$assignment=GetAssignment((string)$id);
if(!$assignment){http_response_code(403);exit('Accès au devoir non autorisé.');}
if(trim((string)($assignment['DESCRIPTION']??''))==='' && !empty($assignment['FILE'])){
 header('Content-Type: text/html; charset=UTF-8');
 echo '<h1>Devoir en pièce jointe</h1>'.GetAssignmentFileLink($assignment['FILE']);
 exit;
}
$identity=[
 'school'=>SchoolInfo('TITLE'),'address'=>SchoolInfo('ADDRESS'),'city'=>SchoolInfo('CITY'),'phone'=>SchoolInfo('PHONE'),'website'=>SchoolInfo('WWW_ADDRESS')
];
foreach(['email'=>'SCHOOLSAFE_SCHOOL_EMAIL','slogan'=>'SCHOOLSAFE_SCHOOL_SLOGAN','logo'=>'SCHOOLSAFE_RECEIPT_LOGO','accent'=>'SCHOOLSAFE_RECEIPT_ACCENT'] as $key=>$setting){
 $identity[$key]=(string)DBGetOne("SELECT config_value FROM config WHERE school_id='".(int)UserSchool()."' AND title='".$setting."' LIMIT 1");
}
$html=SchoolSafeHomeworkHtml($identity,$assignment);
if(($_GET['download']??'')!=='1'){header('Content-Type: text/html; charset=UTF-8');echo $html;exit;}
try{
 $pdf=SchoolSafeDocumentPdf($html);
 header('Content-Type: application/pdf');
 header('Content-Disposition: attachment; filename="SchoolSafe-devoir-'.$id.'.pdf"');
 echo $pdf;
}catch(Throwable $error){http_response_code(503);echo 'La génération du devoir PDF est temporairement indisponible.';}
