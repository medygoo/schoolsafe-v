<?php
// Bulletin RDC d'un eleve (04/11, etape D validee). Parent et eleve : trimestres valides seulement, et
// seulement si les frais dus sont payes (ou ouverture exceptionnelle, ou ecole sans lien avec les frais).
// Titulaire et direction : apercu a tout moment, marque « PROJET » tant que ce n'est pas valide.
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){http_response_code(405);exit;}
if(array_diff(array_keys($_GET),['eleve','pdf'])){http_response_code(400);exit('Requête invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeBulletinDoc.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeChildWork.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeSchoolDocument.fnc.php';
$school=(int)UserSchool();$year=(int)UserSyear();$profile=(string)User('PROFILE');$staff=(int)User('STAFF_ID');
$get=function($sql){return DBGet($sql);};
$tz=new DateTimeZone('Africa/Kinshasa');$today=(new DateTime('now',$tz))->format('Y-m-d');
$sid=isset($_GET['eleve'])&&ctype_digit((string)$_GET['eleve'])?(int)$_GET['eleve']:0;
$family=false;$isStaff=false;$access=null;$classCp=0;
if($school>0&&$year>0&&in_array($profile,['parent','student'],true)&&AllowUse('Grades/StudentAssignments.php')){
    $children=SchoolSafeChildWorkChildren($profile,$staff,(int)($_SESSION['STUDENT_ID']??0),$school,$year,$get);
    $child=SchoolSafeChildWorkPick($sid?:(UserStudentID()?:0),$children);
    if($child&&(!$sid||(int)$child['STUDENT_ID']===$sid)){$sid=(int)$child['STUDENT_ID'];$family=true;$access=SchoolSafeBulletinAccess($sid,$school,$year,$today,$get);$classCp=(int)$access['class'];}
}elseif($sid&&$school>0&&$year>0&&in_array($profile,['admin','teacher'],true)){
    $c=DBGet("SELECT DISTINCT b.CLASS_COURSE_PERIOD_ID,cp.TEACHER_ID FROM schoolsafe_class_branches b JOIN course_periods cp ON cp.COURSE_PERIOD_ID=b.CLASS_COURSE_PERIOD_ID JOIN schedule sc ON sc.COURSE_PERIOD_ID=b.CLASS_COURSE_PERIOD_ID AND sc.STUDENT_ID=".$sid
        ." WHERE b.SCHOOL_ID=".$school." AND b.SYEAR=".$year." LIMIT 1");
    if($c){
        $isDirection=$profile==='admin'&&(AllowEdit('School_Setup/Configuration.php')||AllowUse('Grades/ReportCards.php'));
        $isStaff=$isDirection||($profile==='teacher'&&(int)$c[1]['TEACHER_ID']===$staff);
        if($isStaff)$classCp=(int)$c[1]['CLASS_COURSE_PERIOD_ID'];
    }
}
$allowed=($family||$isStaff);
$html='';$state='';
if($allowed&&$classCp&&($isStaff||$access['open'])){
    $terms=SchoolSafeBulletinTerms($school,$year,$get);
    $fy=(int)(DBGetOne("SELECT MARKING_PERIOD_ID FROM school_marking_periods WHERE SCHOOL_ID=".$school." AND SYEAR=".$year." AND MP='FY' LIMIT 1")?:0);
    $branches=SchoolSafeBulletinBranches($classCp,$get);
    $students=SchoolSafeBulletinStudents($classCp,$get);
    $calc=SchoolSafeBulletinCompute($branches,$students,SchoolSafeBulletinMarks($classCp,$get),$terms,SchoolSafeBulletinLangMode($school,$get));
    $status=SchoolSafeBulletinStatus($classCp,$get)[$sid]??[];
    $validated=[];foreach($status as $mp=>$st)if(($st['STATUS']??'')==='valide')$validated[]=(int)$mp;
    $visible=$isStaff?array_merge(array_keys($terms),[$fy]):$validated;
    $draft=$isStaff&&count(array_diff(array_merge(array_keys($terms),[$fy]),$validated))>0;
    $st=DBGet("SELECT FIRST_NAME,LAST_NAME,CUSTOM_200000000 AS SEXE,CUSTOM_200000004 AS NAISSANCE,CUSTOM_200000012 AS MATRICULE FROM students WHERE STUDENT_ID=".$sid);
    $cls=DBGet("SELECT c.TITLE,(SELECT g.TITLE FROM school_gradelevels g WHERE g.ID=c.GRADE_LEVEL) AS LEVEL,t.FIRST_NAME,t.LAST_NAME FROM course_periods cp JOIN courses c ON c.COURSE_ID=cp.COURSE_ID LEFT JOIN staff t ON t.STAFF_ID=cp.TEACHER_ID WHERE cp.COURSE_PERIOD_ID=".$classCp);
    $html=SchoolSafeBulletinHtml(['identity'=>SchoolSafeLetterheadIdentity($school),'student'=>$st[1]??[],'class_title'=>(string)(($cls[1]['LEVEL']??'')?:ParseMLField((string)($cls[1]['TITLE']??''))),
        'year_label'=>$year.'-'.($year+1),'branches'=>$branches,'calc'=>$calc,'terms'=>$terms,'status'=>$status,'visible'=>$visible,'fy'=>$fy,'sid'=>$sid,'count'=>$calc['count'],
        'draft'=>$draft,'titulaire'=>trim(($cls[1]['FIRST_NAME']??'').' '.($cls[1]['LAST_NAME']??'')),'today'=>$today]);
}
$schoolName=$allowed?(string)SchoolInfo('TITLE'):'';
while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(403);exit('Ce bulletin n’est pas accessible avec ce compte.');}
if($html!==''&&isset($_GET['pdf'])){
    try{$bytes=SchoolSafeDocumentPdf($html);}catch(Throwable $failure){http_response_code(503);exit('Le PDF n’a pas pu être préparé. Réessayez dans un instant.');}
    header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="bulletin-'.$sid.'-'.$year.'.pdf"');echo $bytes;exit;
}
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bulletin | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<style>.bu{display:grid;gap:14px;max-width:760px}.bu-card{border:1px solid #c9d8d2;border-radius:12px;padding:16px;display:grid;gap:10px;background:#fff}.bu-btn{min-height:44px;background:#08766b;color:#fff;border-radius:8px;font-size:15px;padding:0 16px;text-decoration:none;display:inline-flex;align-items:center;width:max-content}
.bu-warn{background:#fff8ec;border:1px solid #f0d7a8}.bu-sub{color:#556777;font-size:14px}</style></head>
<body><header><strong>SchoolSafe</strong><?php if($family):?><a href="travail-enfant.php?enfant=<?=$sid?>">Travail de mon enfant</a><?php else:?><a href="synthese-bulletin.php">Synthèse du bulletin</a><?php endif;?></header>
<main class="bu"><h1>Bulletin</h1>
<?php if($html!==''):?><section class="bu-card"><p><?=$isStaff?'Aperçu du bulletin (marqué « PROJET » tant que tout n’est pas validé).':'Le bulletin de votre enfant est disponible.'?></p>
<a class="bu-btn" href="bulletin.php?<?=$e(http_build_query(['eleve'=>$sid,'pdf'=>1]))?>" target="_blank" rel="noopener">📄 Ouvrir le bulletin (PDF)</a>
<?php if($family&&($access['reason']??'')==='unlocked'):?><p class="bu-sub">Ouvert par la direction.</p><?php endif;?></section>
<?php elseif($family&&$access['reason']==='fees'):?><section class="bu-card bu-warn"><h2>Bulletin disponible après régularisation des frais</h2>
<p>Le bulletin est prêt. Il s’ouvrira automatiquement dès que les frais scolaires dus seront payés.</p><p><b>Reste à payer : <?=$e(SchoolSafeFeeFormatCents((int)$access['remaining']))?></b></p>
<p class="bu-sub">Les notes de chaque devoir restent visibles dans « Travail de mon enfant ». En cas de difficulté, adressez-vous à la direction.</p></section>
<?php elseif($family&&$access['reason']==='pending'):?><section class="bu-card"><p>Le bulletin n’est pas encore publié : le titulaire le prépare et la direction le valide. Vous serez prévenu dès qu’il sera disponible.</p></section>
<?php else:?><section class="bu-card"><p>Pas encore de bulletin pour cette classe.</p></section><?php endif;?>
<p class="bu-sub"><?=$e($schoolName)?> · SchoolSafe · Un enfant protégé, un parent informé</p></main></body></html>
