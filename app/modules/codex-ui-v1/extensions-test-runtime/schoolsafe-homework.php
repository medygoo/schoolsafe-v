<?php
// Fiche de devoir A4 au papier de l'ecole (04/11, lot 3 valide) : 5 dispositions, mise en page de
// l'enseignant gardee, cadre eleve et lignes de reponse au choix, direction autorisee pour son ecole.
if ( ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' ) { http_response_code(405); exit; }
$id = filter_input(INPUT_GET, 'assignment_id', FILTER_VALIDATE_INT);
if (!$id || $id<1) { http_response_code(400); exit('Devoir invalide.'); }
if (array_diff(array_keys($_GET), ['assignment_id','download','layout','lines','box','style','demande','jaspe','student','img','bulle','classe'])) { http_response_code(400); exit('Requête invalide.'); }
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
// Fiche au nom de l'enfant (04/11) : le lien du message designe l'enfant ; il doit etre celui du parent ou de l'eleve.
$child=(int)(filter_input(INPUT_GET,'student',FILTER_VALIDATE_INT)?:0);
if($child>0&&in_array($profile,['parent','student'],true)){
 $own=$profile==='student'?(int)User('STUDENT_ID')===$child:(bool)DBGetOne("SELECT 1 FROM students_join_users WHERE student_id='".$child."' AND staff_id='".(int)User('STAFF_ID')."'");
 if(!$own){http_response_code(403);exit('Accès au devoir non autorisé.');}
 if((int)UserStudentID()!==$child)SetUserStudentID($child);
}
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
// Nom et classe repris de la fiche de l'eleve : parent et eleve voient leur enfant, l'enseignant ou la direction
// un eleve inscrit dans la classe du devoir.
$sid=in_array($profile,['parent','student'],true)?(int)UserStudentID():$child;
$pupil=$sid>0?DBGet("SELECT s.FIRST_NAME,s.LAST_NAME,(SELECT g.TITLE FROM student_enrollment e JOIN school_gradelevels g ON g.ID=e.GRADE_ID WHERE e.STUDENT_ID=s.STUDENT_ID AND e.SCHOOL_ID=".$school." AND e.SYEAR=".$year." ORDER BY e.START_DATE DESC NULLS LAST LIMIT 1) AS CLASS_TITLE"
 ." FROM students s WHERE s.STUDENT_ID=".$sid." AND EXISTS(SELECT 1 FROM student_enrollment e WHERE e.STUDENT_ID=s.STUDENT_ID AND e.SCHOOL_ID=".$school." AND e.SYEAR=".$year.")"
 ." AND EXISTS(SELECT 1 FROM schedule ss JOIN course_periods cp ON cp.COURSE_PERIOD_ID=ss.COURSE_PERIOD_ID JOIN gradebook_assignments ga ON ga.ASSIGNMENT_ID=".(int)$id
 ." AND (cp.COURSE_PERIOD_ID=ga.COURSE_PERIOD_ID OR (ga.COURSE_PERIOD_ID IS NULL AND cp.COURSE_ID=ga.COURSE_ID)) WHERE ss.STUDENT_ID=s.STUDENT_ID)"):[];
$pupilName=$pupil?trim($pupil[1]['FIRST_NAME'].' '.$pupil[1]['LAST_NAME']):'';
$pupilClass=$pupil?ParseMLField((string)($pupil[1]['CLASS_TITLE']??'')):'';
$layouts=SchoolSafeLetterheadLayouts();
$layout=isset($_GET['layout'])&&is_string($_GET['layout'])&&isset($layouts[$_GET['layout']])?$_GET['layout']:$identity['layout'];
$lines=isset($_GET['lines'])&&ctype_digit((string)$_GET['lines'])?min(30,(int)$_GET['lines']):($layout==='devoir'?12:0);
$box=isset($_GET['box'])?$_GET['box']==='1':true;
$style=isset($_GET['style'])&&is_string($_GET['style'])?SchoolSafeLetterheadStyleEncode(SchoolSafeLetterheadStyleDecode($_GET['style'])):(string)$identity['style'];
// Image de JASPE (04/11) : ajoutee ou retiree librement, avec un message libre dans sa bulle.
if(isset($_GET['img'])&&is_string($_GET['img'])&&in_array($_GET['img'],['aucune','salut','debout'],true)){$styleParts=SchoolSafeLetterheadStyleDecode($style);$styleParts['jaspe']=$_GET['img'];$style=SchoolSafeLetterheadStyleEncode($styleParts);}
$bubble=isset($_GET['bulle'])&&is_string($_GET['bulle'])?mb_substr(trim($_GET['bulle']),0,90):'';
// JASPE (04/11) : la demande devient des reglages permis (jamais de retrait d'information), puis retour a l'apercu.
if(isset($_GET['demande'])&&is_string($_GET['demande'])&&trim($_GET['demande'])!==''&&($_GET['download']??'')!=='1'){
 $now=time();$hits=array_values(array_filter($_SESSION['jaspe_ai_hits']??[],static function($h)use($now){return is_int($h)&&$now-$h<60;}));
 $ask=count($hits)<10?SchoolSafeLetterheadJaspeTransport():null;
 if($ask)$_SESSION['jaspe_ai_hits']=array_merge($hits,[$now]);
 require_once 'ProgramFunctions/SchoolSafeJaspePrompt.fnc.php';
 $demande=SchoolSafeJaspeMask(mb_substr($_GET['demande'],0,300),SchoolSafeJaspeStudentNames($school,$year,static function($sql){return DBGet($sql);}));
 $r=SchoolSafeLetterheadAsk($demande,$layout,SchoolSafeLetterheadStyleDecode($style),$ask);
 header('Location: schoolsafe-homework.php?'.http_build_query(['assignment_id'=>$id,'layout'=>$r['layout'],'lines'=>$lines,'box'=>$box?'1':'0','style'=>SchoolSafeLetterheadStyleEncode($r['style']),'jaspe'=>$r['by']?:'aucun']+($child>0?['student'=>$child]:[])+($bubble!==''?['bulle'=>$bubble]:[])));
 exit;
}
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
 'student_box'=>$box,'points'=>$points!==''&&$points!=='0'?$points:'','answer_lines'=>$lines,'style'=>$style,
 'student_name'=>$pupilName,'student_class'=>$pupilClass,'jaspe_text'=>$bubble,
];
// Toute la classe (04/11, lot 5) : un seul PDF, une fiche au nom de chaque eleve ; enseignant ou direction seulement.
if(($_GET['classe']??'')==='1'){
 if(in_array($profile,['parent','student'],true)){http_response_code(403);exit('Accès au devoir non autorisé.');}
 require_once 'ProgramFunctions/SchoolSafeHomeworkDesk.fnc.php';
 $class=DBGet("SELECT DISTINCT ON (s.STUDENT_ID) s.STUDENT_ID,s.FIRST_NAME,s.LAST_NAME,(SELECT g.TITLE FROM student_enrollment e JOIN school_gradelevels g ON g.ID=e.GRADE_ID WHERE e.STUDENT_ID=s.STUDENT_ID AND e.SCHOOL_ID=".$school." AND e.SYEAR=".$year." ORDER BY e.START_DATE DESC NULLS LAST LIMIT 1) AS CLASS_TITLE"
  ." FROM gradebook_assignments ga JOIN course_periods cp ON (ga.COURSE_PERIOD_ID IS NOT NULL AND cp.COURSE_PERIOD_ID=ga.COURSE_PERIOD_ID) OR (ga.COURSE_PERIOD_ID IS NULL AND cp.COURSE_ID=ga.COURSE_ID AND cp.TEACHER_ID=ga.STAFF_ID)"
  ." JOIN schedule ss ON ss.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID AND ss.START_DATE<=CURRENT_DATE AND (ss.END_DATE IS NULL OR ss.END_DATE>=CURRENT_DATE) JOIN students s ON s.STUDENT_ID=ss.STUDENT_ID"
  ." WHERE ga.ASSIGNMENT_ID=".(int)$id." AND cp.SCHOOL_ID=".$school." AND cp.SYEAR=".$year." ORDER BY s.STUDENT_ID");
 $sheets=[];
 foreach((array)$class as $k){$sheets[]=[trim($k['FIRST_NAME'].' '.$k['LAST_NAME']),SchoolSafeLetterheadPage($identity,$layout,['student_name'=>trim($k['FIRST_NAME'].' '.$k['LAST_NAME']),'student_class'=>ParseMLField((string)($k['CLASS_TITLE']??''))]+$opts)];}
 usort($sheets,static function($x,$y){return strcmp($x[0],$y[0]);});
 if(!$sheets){http_response_code(404);exit('Aucun élève placé dans cette classe.');}
 $book=SchoolSafeLetterheadBook(array_column($sheets,1));
 if(($_GET['download']??'')!=='1'){header('Content-Type: text/html; charset=UTF-8');echo $book;exit;}
 try{$pdf=SchoolSafeDocumentPdf($book);header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="devoir-'.$id.'-classe.pdf"');echo $pdf;}
 catch(Throwable $error){http_response_code(503);echo 'La génération du PDF de la classe est temporairement indisponible.';}
 exit;
}
$query=static function(array $more)use($id,$layout,$lines,$box,$style,$child,$bubble){return 'schoolsafe-homework.php?'.http_build_query(['assignment_id'=>$id,'layout'=>$layout,'lines'=>$lines,'box'=>$box?'1':'0','style'=>$style]+($child>0?['student'=>$child]:[])+($bubble!==''?['bulle'=>$bubble]:[])+$more);};
if(($_GET['download']??'')!=='1'){
 $e='SchoolSafeLetterheadEscape';
 $choice='';foreach($layouts as $key=>$label)$choice.='<option value="'.$e($key).'"'.($key===$layout?' selected':'').'>'.$e($label[0]).'</option>';
 $opts['toolbar']='<form class="ss-toolbar" method="get" action="schoolsafe-homework.php"><input type="hidden" name="assignment_id" value="'.(int)$id.'">'.($child>0?'<input type="hidden" name="student" value="'.$child.'">':'')
  .'<label>Disposition <select name="layout">'.$choice.'</select></label>'
  .'<label>Lignes de réponse <input type="number" name="lines" min="0" max="30" value="'.(int)$lines.'" style="width:64px"></label>'
  .'<label><input type="hidden" name="box" value="0"><input type="checkbox" name="box" value="1"'.($box?' checked':'').'> Cadre nom, classe, note</label>'
  .(static function()use($e,$style,$bubble){$cur=SchoolSafeLetterheadStyleDecode($style)['jaspe']??'aucune';$o='';foreach(['aucune'=>'Sans JASPE','salut'=>'JASPE qui salue (en haut)','debout'=>'JASPE debout (en bas)'] as $k=>$v)$o.='<option value="'.$k.'"'.($k===$cur?' selected':'').'>'.$v.'</option>';
   return '<label>Image de JASPE <select name="img">'.$o.'</select></label><label>Message de JASPE <input type="text" name="bulle" maxlength="90" value="'.$e($bubble).'" placeholder="ex. Courage, tu vas y arriver !"></label>';})()
  .'<input type="hidden" name="style" value="'.$e($style).'"><button type="submit">Voir</button><a class="ss-pdf" href="'.$e($query(['download'=>'1'])).'">Télécharger le PDF</a>'
  .'<label style="flex:1 1 100%">Demander à JASPE de changer la disposition <input type="text" name="demande" maxlength="300" style="width:100%" placeholder="ex. logo à droite, titre centré, texte plus grand, en bleu"></label>'
  .'<span style="flex:1 1 100%;color:#405064">Disposition actuelle : '.$e(SchoolSafeLetterheadStyleText(SchoolSafeLetterheadStyleDecode($style)))
  .(($_GET['jaspe']??'')==='jaspe'?' · réglée par JASPE':(($_GET['jaspe']??'')==='consignes'?' · réglée par les consignes simples (JASPE indisponible)':(($_GET['jaspe']??'')==='aucun'?' · demande non comprise, rien n’a changé':''))).' · les informations de l’école et le contenu restent toujours présents.</span></form>';
 header('Content-Type: text/html; charset=UTF-8');echo SchoolSafeLetterheadPage($identity,$layout,$opts);exit;
}
try{
 $pdf=SchoolSafeDocumentPdf(SchoolSafeLetterheadPage($identity,$layout,$opts));
 header('Content-Type: application/pdf');
 header('Content-Disposition: attachment; filename="devoir-'.$id.($pupilName!==''?'-'.preg_replace('/[^A-Za-z0-9]+/','-',iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$pupilName)):'').'.pdf"');
 echo $pdf;
}catch(Throwable $error){http_response_code(503);echo 'La génération du devoir PDF est temporairement indisponible.';}
