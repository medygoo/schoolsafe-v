<?php
// Fiche de devoir A4 au papier de l'ecole (04/11, lot 3 valide) : 5 dispositions, mise en page de
// l'enseignant gardee, cadre eleve et lignes de reponse au choix, direction autorisee pour son ecole.
if ( ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' ) { http_response_code(405); exit; }
$id = filter_input(INPUT_GET, 'assignment_id', FILTER_VALIDATE_INT);
if (!$id || $id<1) { http_response_code(400); exit('Devoir invalide.'); }
if (array_diff(array_keys($_GET), ['assignment_id','download','layout','lines','box'])) { http_response_code(400); exit('Requête invalide.'); }
header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
chdir(__DIR__);
$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level(); ob_start(); require_once 'Warehouse.php';
while(ob_get_level()>$level){ob_end_clean();}
require_once 'ProgramFunctions/SchoolSafeSchoolDocument.fnc.php';
require_once 'ProgramFunctions/SchoolSafeLetterhead.fnc.php';
require_once 'modules/Grades/includes/StudentAssignments.fnc.php';
$school=(int)UserSchool(); $year=(int)UserSyear();
$profile=User('PROFILE');
$delegated=false;
if($profile==='admin' && AllowUse('Users/TeacherPrograms.php&include=Grades/Assignments.php') && UserStaffID() && UserCoursePeriod()){
 $context=DBGetOne("SELECT 1 FROM course_periods cp JOIN staff teacher ON teacher.staff_id=cp.teacher_id WHERE cp.course_period_id='".(int)UserCoursePeriod()."' AND cp.teacher_id='".(int)UserStaffID()."' AND cp.school_id='".$school."' AND cp.syear='".$year."' AND teacher.profile='teacher'");
 if($context){UserImpersonateTeacher(UserStaffID());$profile=User('PROFILE');$delegated=true;}
}
$teacher=$profile==='teacher';
// Direction (04/11) : un administrateur qui gere les devoirs obtient la fiche des devoirs de son ecole.
$direction=!$delegated && $profile==='admin' && (AllowUse('Grades/MassCreateAssignments.php') || AllowUse('Users/TeacherPrograms.php&include=Grades/Assignments.php'));
$allowed=$delegated || $direction || AllowUse($teacher?'Grades/Assignments.php':'Grades/StudentAssignments.php');
if($profile==='parent'){
 $allowed=$allowed && UserStudentID() && DBGetOne("SELECT 1 FROM students_join_users WHERE student_id='".(int)UserStudentID()."' AND staff_id='".(int)User('STAFF_ID')."'");
}elseif($profile==='student'){
 $allowed=$allowed && UserStudentID() && (int)UserStudentID()===(int)User('STUDENT_ID');
}elseif(!$teacher && !$direction){$allowed=false;}
if(!$allowed){http_response_code(403);exit('Accès au devoir non autorisé.');}
if($direction){
 $rows=DBGet("SELECT ga.*,c.TITLE AS COURSE_TITLE FROM gradebook_assignments ga JOIN courses c ON c.COURSE_ID=COALESCE(ga.COURSE_ID,(SELECT cp.COURSE_ID FROM course_periods cp WHERE cp.COURSE_PERIOD_ID=ga.COURSE_PERIOD_ID))"
  ." WHERE ga.ASSIGNMENT_ID=".(int)$id." AND c.SCHOOL_ID=".$school." AND c.SYEAR=".$year);
 $assignment=$rows[1]??null;
}else{$assignment=GetAssignment((string)$id);}
if(!$assignment){http_response_code(403);exit('Accès au devoir non autorisé.');}
if(trim((string)($assignment['DESCRIPTION']??''))==='' && !empty($assignment['FILE'])){
 header('Content-Type: text/html; charset=UTF-8');
 echo '<h1>Devoir en pièce jointe</h1>'.GetAssignmentFileLink($assignment['FILE']);
 exit;
}
$identity=SchoolSafeLetterheadIdentity($school);
$layouts=SchoolSafeLetterheadLayouts();
$layout=isset($_GET['layout'])&&is_string($_GET['layout'])&&isset($layouts[$_GET['layout']])?$_GET['layout']:$identity['layout'];
$lines=isset($_GET['lines'])&&ctype_digit((string)$_GET['lines'])?min(30,(int)$_GET['lines']):($layout==='devoir'?12:0);
$box=isset($_GET['box'])?$_GET['box']==='1':true;
$extra=DBGet("SELECT t.TITLE AS TYPE_TITLE,st.FIRST_NAME,st.LAST_NAME FROM gradebook_assignments ga LEFT JOIN gradebook_assignment_types t ON t.ASSIGNMENT_TYPE_ID=ga.ASSIGNMENT_TYPE_ID LEFT JOIN staff st ON st.STAFF_ID=ga.STAFF_ID WHERE ga.ASSIGNMENT_ID=".(int)$id);
$type=trim((string)($extra[1]['TYPE_TITLE']??''));
// Images de l'application (editeur) integrees : jamais de ressource exterieure dans le PDF.
$local=static function(string $src):string{
 $path=parse_url($src,PHP_URL_PATH);
 if(!is_string($path)||(strpos($src,'//')!==false&&!preg_match('#^https://[a-z0-9.-]*shoolsafe\.tech/#',$src)))return '';
 $real=realpath(__DIR__.'/'.ltrim(rawurldecode($path),'/'));
 if(!$real||strpos($real,__DIR__.'/assets/')!==0||!is_file($real)||filesize($real)>3000000)return '';
 $mime=[IMAGETYPE_JPEG=>'image/jpeg',IMAGETYPE_PNG=>'image/png',IMAGETYPE_GIF=>'image/gif',IMAGETYPE_WEBP=>'image/webp'][@getimagesize($real)[2]??0]??'';
 return $mime?'data:'.$mime.';base64,'.base64_encode((string)file_get_contents($real)):'';
};
$points=(string)($assignment['POINTS']??'');
$opts=[
 'title'=>($type!==''?$type.' — ':'').(string)($assignment['TITLE']??''),
 'meta'=>[['Matière',ParseMLField((string)($assignment['COURSE_TITLE']??''))],['Enseignant',trim(($extra[1]['FIRST_NAME']??'').' '.($extra[1]['LAST_NAME']??''))],
  ['Donné le',SchoolSafeLetterheadDate($assignment['ASSIGNED_DATE']??'')],['À rendre le',SchoolSafeLetterheadDate($assignment['DUE_DATE']??'')]],
 'body'=>SchoolSafeLetterheadContent((string)($assignment['DESCRIPTION']??''),$local),
 'student_box'=>$box,'points'=>$points!==''&&$points!=='0'?$points:'','answer_lines'=>$lines,
];
$query=static function(array $more)use($id,$layout,$lines,$box){return 'schoolsafe-homework.php?'.http_build_query(['assignment_id'=>$id,'layout'=>$layout,'lines'=>$lines,'box'=>$box?'1':'0']+$more);};
if(($_GET['download']??'')!=='1'){
 $e='SchoolSafeLetterheadEscape';
 $choice='';foreach($layouts as $key=>$label)$choice.='<option value="'.$e($key).'"'.($key===$layout?' selected':'').'>'.$e($label[0]).'</option>';
 $opts['toolbar']='<form class="ss-toolbar" method="get" action="schoolsafe-homework.php"><input type="hidden" name="assignment_id" value="'.(int)$id.'">'
  .'<label>Disposition <select name="layout">'.$choice.'</select></label>'
  .'<label>Lignes de réponse <input type="number" name="lines" min="0" max="30" value="'.(int)$lines.'" style="width:64px"></label>'
  .'<label><input type="hidden" name="box" value="0"><input type="checkbox" name="box" value="1"'.($box?' checked':'').'> Cadre nom, classe, note</label>'
  .'<button type="submit">Voir</button><a class="ss-pdf" href="'.$e($query(['download'=>'1'])).'">Télécharger le PDF</a></form>';
 header('Content-Type: text/html; charset=UTF-8');echo SchoolSafeLetterheadPage($identity,$layout,$opts);exit;
}
try{
 $pdf=SchoolSafeDocumentPdf(SchoolSafeLetterheadPage($identity,$layout,$opts));
 header('Content-Type: application/pdf');
 header('Content-Disposition: attachment; filename="devoir-'.$id.'.pdf"');
 echo $pdf;
}catch(Throwable $error){http_response_code(503);echo 'La génération du devoir PDF est temporairement indisponible.';}
