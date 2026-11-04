<?php
// Note d'un devoir pour le parent (04/11, valide par le proprietaire) : la note, l'appreciation, le repere de
// la classe, la progression, le corrige type (apres la date de remise), la photo de la copie corrigee, la
// fiche du devoir, et « Vu par le parent ». Le parent ne voit que ses enfants ; l'enseignant du devoir et la
// direction peuvent ouvrir la meme page.
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','POST'],true)){http_response_code(405);exit;}
if(array_diff(array_keys($_GET),['devoir','eleve','copie'])){http_response_code(400);exit('Requête invalide.');}
$assignment=isset($_GET['devoir'])&&ctype_digit((string)$_GET['devoir'])?(int)$_GET['devoir']:0;
$student=isset($_GET['eleve'])&&ctype_digit((string)$_GET['eleve'])?(int)$_GET['eleve']:0;
if($assignment<1||$student<1){http_response_code(400);exit('Requête invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeGradeNotice.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeChildWork.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeRemediationSettings.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeLetterhead.fnc.php';
$school=(int)UserSchool();$year=(int)UserSyear();$profile=(string)User('PROFILE');$staff=(int)User('STAFF_ID');
$get=function($sql){return DBGet($sql);};
$row=null;
if($school>0&&$year>0){
    $r=DBGet("SELECT ga.ASSIGNMENT_ID,ga.TITLE,ga.POINTS AS MAXIMUM,ga.ASSIGNED_DATE,ga.DUE_DATE,ga.STAFF_ID,t.TITLE AS TYPE_TITLE,c.TITLE AS COURSE_TITLE,cp.COURSE_PERIOD_ID,cp.TEACHER_ID,cp.SECONDARY_TEACHER_ID,"
        ."st.FIRST_NAME AS T_FIRST,st.LAST_NAME AS T_LAST,s.FIRST_NAME,s.LAST_NAME,gg.POINTS,gg.COMMENT,COALESCE(gg.UPDATED_AT,gg.CREATED_AT) AS GRADED_AT"
        ." FROM gradebook_assignments ga JOIN course_periods cp ON (ga.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID) OR (ga.COURSE_PERIOD_ID IS NULL AND cp.COURSE_ID=ga.COURSE_ID AND cp.TEACHER_ID=ga.STAFF_ID)"
        ." JOIN schedule sc ON sc.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID AND sc.STUDENT_ID=".$student
        ." JOIN students s ON s.STUDENT_ID=sc.STUDENT_ID JOIN courses c ON c.COURSE_ID=cp.COURSE_ID"
        ." LEFT JOIN gradebook_assignment_types t ON t.ASSIGNMENT_TYPE_ID=ga.ASSIGNMENT_TYPE_ID LEFT JOIN staff st ON st.STAFF_ID=ga.STAFF_ID"
        ." LEFT JOIN gradebook_grades gg ON gg.ASSIGNMENT_ID=ga.ASSIGNMENT_ID AND gg.STUDENT_ID=sc.STUDENT_ID"
        ." WHERE ga.ASSIGNMENT_ID=".$assignment." AND cp.SCHOOL_ID=".$school." ORDER BY sc.START_DATE DESC LIMIT 1");
    $row=$r[1]??null;
}
// Qui peut voir : le parent de l'enfant, l'eleve lui-meme, l'enseignant du devoir, la direction.
$isParent=false;$isStaff=false;
if($row){
    if($profile==='parent'||$profile==='student'){
        foreach(SchoolSafeChildWorkChildren($profile,$staff,(int)($_SESSION['STUDENT_ID']??0),$school,$year,$get) as $child)if((int)$child['STUDENT_ID']===$student)$isParent=true;
        $isParent=$isParent&&AllowUse('Grades/StudentAssignments.php');
    }elseif($profile==='teacher'){
        $isStaff=$staff>0&&in_array($staff,[(int)$row['STAFF_ID'],(int)$row['TEACHER_ID'],(int)$row['SECONDARY_TEACHER_ID']],true);
    }elseif($profile==='admin'){
        $isStaff=AllowEdit('School_Setup/Configuration.php')||AllowUse('Grades/ReportCards.php');
    }
}
$allowed=$row&&($isParent||$isStaff);
// Copie corrigee : affichee dans le navigateur, jamais executee.
if(isset($_GET['copie'])){
    $f=$allowed?DBGet("SELECT MIME,encode(CONTENT,'base64') AS B64 FROM schoolsafe_grade_copies WHERE ASSIGNMENT_ID=".$assignment." AND STUDENT_ID=".$student):[];
    while(ob_get_level()>$level)ob_end_clean();
    if(!$f){http_response_code($allowed?404:403);exit('Copie non disponible.');}
    header('Content-Type: '.$f[1]['MIME']);header("Content-Security-Policy: sandbox; default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'");
    header('Content-Disposition: inline; filename="copie-corrigee-'.$assignment.'.'.($f[1]['MIME']==='application/pdf'?'pdf':'jpg').'"');
    echo base64_decode(str_replace(["\n","\r"],'',(string)$f[1]['B64']));exit;
}
if(empty($_SESSION['SchoolSafeScannerCsrf']))$_SESSION['SchoolSafeScannerCsrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['SchoolSafeScannerCsrf'];
$message='';$error='';
if($allowed&&$_SERVER['REQUEST_METHOD']==='POST'){
    if(!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf']))$error='Session expirée : rechargez la page.';
    elseif(($_POST['action']??'')==='vu'&&$profile==='parent'&&$staff>0){
        DBQuery("INSERT INTO schoolsafe_grade_seen(ASSIGNMENT_ID,STUDENT_ID,SEEN_BY) VALUES(".$assignment.",".$student.",".$staff.") ON CONFLICT DO NOTHING");
        $message='Merci : l’enseignant voit que vous avez pris connaissance de cette note.';
    }else $error='Action impossible.';
}
$tz=new DateTimeZone('Africa/Kinshasa');$today=(new DateTime('now',$tz))->format('Y-m-d');
$answer=null;$copy=null;$average=null;$trend=[];$seen=[];$threshold=50.0;
if($allowed){
    $a=DBGet("SELECT ANSWER FROM schoolsafe_assignment_keys WHERE ASSIGNMENT_ID=".$assignment);
    if($a&&SchoolSafeGradeNoticeAnswerVisible($row['DUE_DATE'],$today,$isStaff))$answer=(string)$a[1]['ANSWER'];
    $c=DBGet("SELECT MIME,CREATED_AT FROM schoolsafe_grade_copies WHERE ASSIGNMENT_ID=".$assignment." AND STUDENT_ID=".$student);$copy=$c[1]??null;
    $average=SchoolSafeGradeNoticeClassAverage($assignment,$get);
    $trend=SchoolSafeGradeNoticeTrend($student,(int)$row['COURSE_PERIOD_ID'],$get);
    $seen=(array)DBGet("SELECT p.FIRST_NAME,p.LAST_NAME,v.SEEN_AT,v.SEEN_BY FROM schoolsafe_grade_seen v JOIN staff p ON p.STAFF_ID=v.SEEN_BY WHERE v.ASSIGNMENT_ID=".$assignment." AND v.STUDENT_ID=".$student." ORDER BY v.SEEN_AT");
    $threshold=(float)(SchoolSafeRemediationSettings($school,$get)['threshold_percent']??50);
}
$schoolName=$allowed?(string)SchoolInfo('TITLE'):'';
while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(403);exit('Cette note n’est pas accessible avec ce compte.');}
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$graded=$row['POINTS']!==null&&$row['POINTS']!=='';
$pct=$graded&&(float)$row['POINTS']>=0&&(float)$row['MAXIMUM']>0?(float)$row['POINTS']*100/(float)$row['MAXIMUM']:null;
$tone=$pct===null?'':($pct<$threshold?'bad':($pct<$threshold+10?'mid':'ok'));
$direction=SchoolSafeGradeNoticeDirection($trend);
$iSaw=false;foreach($seen as $v)if((int)$v['SEEN_BY']===$staff)$iSaw=true;
$course=ParseMLField((string)$row['COURSE_TITLE']);
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Note — <?=$e($row['TITLE'])?> | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<style>.ss-nt{display:grid;gap:14px;max-width:760px}.ss-card{border:1px solid #c9d8d2;border-radius:12px;padding:14px;display:grid;gap:8px;background:#fff}.ss-card h2{margin:0;font-size:18px;color:#08766b}
.ss-mark{display:flex;gap:16px;align-items:center;flex-wrap:wrap}.ss-big{font-size:40px;font-weight:700;line-height:1}.ok{color:#08766b}.mid{color:#a86200}.bad{color:#a32d2d}.ss-sub{color:#556777;font-size:14px}
.ss-btn{min-height:44px;background:#08766b;color:#fff;border:0;border-radius:8px;font-size:15px;padding:0 16px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center}.ss-btn.light{background:#eef7f4;color:#08766b;border:1px solid #08766b}
.ss-act{display:flex;gap:8px;flex-wrap:wrap}.ss-answer{white-space:pre-wrap;background:#f4fbf8;border-radius:8px;padding:10px;font-size:15px}.ss-copy{max-width:100%;border:1px solid #c9d8d2;border-radius:8px}
.ss-bars{display:flex;gap:6px;align-items:flex-end;height:70px}.ss-bars div{flex:1;background:#cfe8e1;border-radius:4px 4px 0 0;position:relative;min-height:4px}.ss-bars div.cur{background:#08766b}.ss-bars span{position:absolute;top:-18px;left:0;right:0;text-align:center;font-size:12px}
.ss-help{background:#fff4f2;border:1px solid #f0c8c0}</style></head>
<body><header><strong>SchoolSafe</strong><?php if($isParent):?><a href="travail-enfant.php?enfant=<?=$student?>">Travail de mon enfant</a><?php elseif($profile==='teacher'):?><a href="devoirs-travaux.php?corriger=<?=$assignment?>#correction">Corriger ce devoir</a><?php endif;?></header>
<main class="ss-nt">
<h1><?=$e($course)?> — <?=$e(($row['TYPE_TITLE']?$row['TYPE_TITLE'].' « ':'« ').$row['TITLE'].' »')?></h1>
<p class="ss-sub"><?=$e($row['FIRST_NAME'].' '.$row['LAST_NAME'])?> · donné le <?=$e(SchoolSafeLetterheadDate((string)($row['ASSIGNED_DATE']??''),false))?><?=$row['DUE_DATE']?' · à rendre le '.$e(SchoolSafeLetterheadDate((string)$row['DUE_DATE'],false)):''?> · enseignant : <?=$e(trim(($row['T_FIRST']??'').' '.($row['T_LAST']??'')))?></p>
<?php if($message):?><p class="ok" role="status"><?=$e($message)?></p><?php endif;?><?php if($error):?><p class="bad" role="alert"><?=$e($error)?></p><?php endif;?>
<section class="ss-card"><h2>Note</h2>
<?php if(!$graded):?><p>Pas encore noté. Vous serez prévenu dès que l’enseignant enregistre la note.</p>
<?php else:?><div class="ss-mark"><span class="ss-big <?=$tone?>"><?=$e((float)$row['POINTS']<0?'Dispensé':SchoolSafeGradeNoticeNumber($row['POINTS']).' / '.SchoolSafeGradeNoticeNumber($row['MAXIMUM']))?></span>
<?php if($pct!==null):?><span class="<?=$tone?>" style="font-size:20px;font-weight:600"><?=$e(SchoolSafeGradeNoticeNumber(round($pct)))?> %</span><?php endif;?></div>
<?php if(trim((string)$row['COMMENT'])!==''):?><p><b>Appréciation de l’enseignant :</b> <?=$e($row['COMMENT'])?></p><?php endif;?>
<?php if($average!==null):?><p class="ss-sub">Moyenne de la classe pour ce devoir : <?=$e(SchoolSafeGradeNoticeNumber($average))?> %.</p><?php endif;?>
<p class="ss-sub">Noté le <?=$e(SchoolSafeLetterheadDate(substr((string)$row['GRADED_AT'],0,10),false))?>.</p><?php endif;?>
</section>
<?php if($tone==='bad'):?><section class="ss-card ss-help"><h2>Pour progresser</h2><p>Cette note est sous le seuil de l’école (<?=$e(SchoolSafeGradeNoticeNumber($threshold))?> %). Reprenez la correction ci-dessous avec votre enfant et refaites les exercices manqués. Si les difficultés se répètent, l’école propose un rattrapage.</p></section><?php endif;?>
<?php if(count($trend)>=2):?><section class="ss-card"><h2>Progression en <?=$e($course)?></h2>
<div class="ss-bars" aria-label="Dernières notes en pourcentage"><?php foreach($trend as $tr):?><div class="<?=(int)$tr['ASSIGNMENT_ID']===$assignment?'cur':''?>" style="height:<?=max(4,min(100,(int)$tr['PCT']))?>%" title="<?=$e($tr['TITLE'])?>"><span><?=(int)$tr['PCT']?> %</span></div><?php endforeach;?></div>
<p class="ss-sub"><?=$direction==='hausse'?'📈 En progrès par rapport à la note précédente.':($direction==='baisse'?'📉 En baisse par rapport à la note précédente.':'➡️ Stable par rapport à la note précédente.')?></p></section><?php endif;?>
<section class="ss-card"><h2>Correction</h2>
<?php if($answer!==null):?><p><b>Corrigé de l’enseignant</b></p><div class="ss-answer"><?=$e($answer)?></div>
<?php elseif(DBGet("SELECT 1 FROM schoolsafe_assignment_keys WHERE ASSIGNMENT_ID=".$assignment)):?><p class="ss-sub">Le corrigé sera visible après la date de remise.</p><?php endif;?>
<?php if($copy):?><p><b>Copie corrigée</b></p><?php if($copy['MIME']==='application/pdf'):?><a class="ss-btn light" href="note-devoir.php?devoir=<?=$assignment?>&amp;eleve=<?=$student?>&amp;copie=1" target="_blank" rel="noopener">Ouvrir la copie corrigée (PDF)</a>
<?php else:?><a href="note-devoir.php?devoir=<?=$assignment?>&amp;eleve=<?=$student?>&amp;copie=1" target="_blank" rel="noopener"><img class="ss-copy" src="note-devoir.php?devoir=<?=$assignment?>&amp;eleve=<?=$student?>&amp;copie=1" alt="Copie corrigée"></a><?php endif;?><?php endif;?>
<?php if($answer===null&&!$copy&&!DBGet("SELECT 1 FROM schoolsafe_assignment_keys WHERE ASSIGNMENT_ID=".$assignment)):?><p class="ss-sub">L’enseignant n’a pas encore ajouté de corrigé ni de copie corrigée.</p><?php endif;?>
<div class="ss-act"><a class="ss-btn light" href="schoolsafe-homework.php?assignment_id=<?=$assignment?>&amp;student=<?=$student?>&amp;download=1">Fiche du devoir</a></div>
</section>
<section class="ss-card"><h2>Vu par le parent</h2>
<?php foreach($seen as $v):?><p class="ok">✓ Vu par <?=$e(trim($v['FIRST_NAME'].' '.$v['LAST_NAME']))?> le <?=$e(SchoolSafeLetterheadDate(substr((string)$v['SEEN_AT'],0,10),false))?>.</p><?php endforeach;?>
<?php if($profile==='parent'&&$graded&&!$iSaw):?><form method="post"><input type="hidden" name="csrf" value="<?=$e($csrf)?>"><input type="hidden" name="action" value="vu"><button class="ss-btn" type="submit">J’ai vu cette note</button>
<span class="ss-sub">Remplace la signature du cahier ; l’enseignant le voit.</span></form>
<?php elseif(!$seen):?><p class="ss-sub">Pas encore vu.</p><?php endif;?>
</section>
<p class="ss-sub"><?=$e($schoolName)?> · SchoolSafe · Un enfant protégé, un parent informé</p>
</main></body></html>
