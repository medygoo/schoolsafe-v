<?php
// « Ma journée de classe » (04/11, lot 4 valide) : classes du jour, appel rapide pre-rempli par le scan
// d'entree (registres natifs), cahier de textes, acces directs. Reserve a l'enseignant de la classe.
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','POST'],true)){http_response_code(405);exit;}
if(array_diff(array_keys($_GET),['date'])){http_response_code(400);exit('Requête invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeClassDay.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeAttendance.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeLetterhead.fnc.php';
require_once __DIR__.'/modules/Attendance/includes/UpdateAttendanceDaily.fnc.php';
$school=(int)UserSchool();$year=(int)UserSyear();$staff=(int)User('STAFF_ID');
$allowed=$staff>0&&User('PROFILE')==='teacher'&&$school>0&&$year>0;
$canCall=$allowed&&AllowUse('Attendance/TakeAttendance.php');
$tz=new DateTimeZone('Africa/Kinshasa');$today=(new DateTime('now',$tz))->format('Y-m-d');
$date=SchoolSafeClassDayDate(is_string($_GET['date']??null)?$_GET['date']:null,$today);
if(empty($_SESSION['SchoolSafeScannerCsrf']))$_SESSION['SchoolSafeScannerCsrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['SchoolSafeScannerCsrf'];
$get=function($sql){return DBGet($sql);};$db=['get'=>$get];
$message='';$error='';$inTx=false;
$classes=$allowed?SchoolSafeClassDayClasses($staff,$school,$year,$date,$get):[];
$byId=[];foreach($classes as $c)$byId[(int)$c['COURSE_PERIOD_ID']]=$c;
$codes=$allowed?SchoolSafeClassDayCodes((array)DBGet("SELECT ID,TITLE,SHORT_NAME,TYPE,STATE_CODE,DEFAULT_CODE FROM attendance_codes WHERE SCHOOL_ID=".$school." AND SYEAR=".$year." AND TABLE_NAME=0 ORDER BY SORT_ORDER")):[];
$schoolDay=$allowed&&SchoolSafeAttendanceIsSchoolDay($school,$year,$date,$db);
if($allowed&&$_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf']))throw new RuntimeException('Session expirée : rechargez la page.');
        foreach($_POST as $v)if(!is_string($v))throw new RuntimeException('Formulaire invalide.');
        $cp=(int)($_POST['cp']??0);
        if(!isset($byId[$cp]))throw new RuntimeException('Cette classe n’est pas la vôtre ce jour-là.');
        $period=(int)$byId[$cp]['PERIOD_ID'];
        if(($_POST['action']??'')==='appel'){
            if(!$canCall)throw new RuntimeException('Permission requise : saisir les absences.');
            if(!$schoolDay)throw new RuntimeException('Ce jour n’est pas un jour de classe.');
            if(!isset($codes['present'],$codes['absent']))throw new RuntimeException('Codes de présence de l’école incomplets.');
            $roster=SchoolSafeClassDayRoster($cp,$period,$school,$year,$date,$get);
            $choices=SchoolSafeClassDayValidate($_POST,array_map(static function($r){return (int)$r['STUDENT_ID'];},$roster),$codes);
            $qtr=(int)GetCurrentMP('QTR',$date,false);
            DBQuery('BEGIN');$inTx=true;
            foreach($choices as $student=>$code){
                // Meme ecriture que l'ecran natif « Saisir les absences » (code de l'enseignant ; code officiel sauf saisie de la direction).
                $current=DBGet("SELECT ADMIN,ATTENDANCE_CODE FROM attendance_period WHERE STUDENT_ID=".$student." AND SCHOOL_DATE='".$date."' AND PERIOD_ID=".$period);
                if($current){
                    $official=($current[1]['ADMIN']??'')!=='Y'||empty($current[1]['ATTENDANCE_CODE']);
                    DBQuery("UPDATE attendance_period SET ATTENDANCE_TEACHER_CODE=".$code.",COURSE_PERIOD_ID=".$cp.($official?",ATTENDANCE_CODE=".$code:'')." WHERE STUDENT_ID=".$student." AND SCHOOL_DATE='".$date."' AND PERIOD_ID=".$period);
                }else{
                    DBQuery("INSERT INTO attendance_period(STUDENT_ID,SCHOOL_DATE,PERIOD_ID,MARKING_PERIOD_ID,COURSE_PERIOD_ID,ATTENDANCE_CODE,ATTENDANCE_TEACHER_CODE,COMMENT) VALUES(".$student.",'".$date."',".$period.",".$qtr.",".$cp.",".$code.",".$code.",'')");
                }
                UpdateAttendanceDaily($student,$date);
            }
            if(!DBGet("SELECT 1 FROM attendance_completed WHERE STAFF_ID=".$staff." AND SCHOOL_DATE='".$date."' AND PERIOD_ID=".$period." AND TABLE_NAME=0"))
                DBQuery("INSERT INTO attendance_completed(STAFF_ID,SCHOOL_DATE,PERIOD_ID,TABLE_NAME) VALUES(".$staff.",'".$date."',".$period.",0)");
            DBQuery('COMMIT');$inTx=false;
            $absent=count(array_filter($choices,static function($c)use($codes){return $c===$codes['absent'];}));
            $message='Appel enregistré : '.count($choices).' élève(s), '.$absent.' absent(s).';
        }elseif(($_POST['action']??'')==='cahier'){
            $done=trim((string)($_POST['done']??''));$todo=trim((string)($_POST['todo']??''));
            if(mb_strlen($done)>4000||mb_strlen($todo)>2000)throw new RuntimeException('Texte trop long.');
            $q=static function($v){return "'".DBEscapeString($v)."'";};
            $updated=DBGet("UPDATE schoolsafe_class_diary SET DONE=".$q($done).",TODO=".$q($todo).",STAFF_ID=".$staff.",UPDATED_AT=now() WHERE COURSE_PERIOD_ID=".$cp." AND SCHOOL_DATE='".$date."' RETURNING ID");
            if(!$updated)DBQuery("INSERT INTO schoolsafe_class_diary(SCHOOL_ID,SYEAR,COURSE_PERIOD_ID,SCHOOL_DATE,STAFF_ID,DONE,TODO) VALUES(".$school.",".$year.",".$cp.",'".$date."',".$staff.",".$q($done).",".$q($todo).")");
            $message='Cahier de textes enregistré.';
        }else throw new RuntimeException('Action inconnue.');
    }catch(Throwable $failure){
        if($inTx)DBQuery('ROLLBACK');
        $error=$failure instanceof RuntimeException?$failure->getMessage():'Enregistrement impossible.';
    }
}
while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(403);exit('Page réservée aux enseignants.');}
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$labels=SchoolSafeClassDayLabels();
$prev=(new DateTimeImmutable($date))->modify('-1 day')->format('Y-m-d');$next=(new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d');
$policies=[];
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ma journée de classe | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<style>.ss-day{display:grid;gap:16px;max-width:980px}.ss-nav{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.ss-nav a{color:#08766b}
.ss-class{border:1px solid #c9d8d2;border-radius:12px;padding:14px;display:grid;gap:12px;background:#fff}.ss-class h2{margin:0;font-size:19px;color:#08766b}
.ss-sub{color:#556777;font-size:14px;margin:0}.ss-roll{width:100%;border-collapse:collapse;font-size:15px}.ss-roll td{border-bottom:1px solid #e3ebe8;padding:6px 4px;vertical-align:middle}
.ss-pick{display:flex;gap:4px;flex-wrap:wrap}.ss-pick label{border:1px solid #c9d8d2;border-radius:8px;padding:6px 9px;cursor:pointer;font-size:14px;min-height:36px;display:inline-flex;align-items:center;gap:4px}
.ss-pick input{accent-color:#08766b}.ss-pick label:has(input:checked){background:#e6f4ef;border-color:#08766b;font-weight:600}
.ss-pick label.absent:has(input:checked){background:#fbeaea;border-color:#a32d2d;color:#a32d2d}.ss-pick label.late:has(input:checked){background:#fff4e0;border-color:#b9581a;color:#8a4a10}
.ss-badge{font-size:12px;border-radius:10px;padding:2px 8px;white-space:nowrap}.b-ok{background:#e6f4ef;color:#08766b}.b-late{background:#fff4e0;color:#8a4a10}.b-none{background:#eef2f5;color:#556777}
.ss-btn{min-height:44px;background:#08766b;color:#fff;border:0;border-radius:8px;font-size:15px;padding:0 16px;cursor:pointer}.ss-btn.light{background:#eef7f4;color:#08766b;border:1px solid #08766b}
.ss-links{display:flex;gap:10px;flex-wrap:wrap}.ss-links a{background:#f4fbf8;border:1px solid #c9d8d2;border-radius:8px;padding:8px 12px;color:#08766b;text-decoration:none}
.ss-diary{display:grid;gap:8px}.ss-diary textarea{width:100%;min-height:70px;font-size:15px;box-sizing:border-box}.ss-ok{color:#08766b;font-weight:600}.ss-ko{color:#a32d2d;font-weight:600}
@media(max-width:600px){.ss-roll td:nth-child(2){display:none}}</style></head>
<body><header><strong>SchoolSafe</strong><a href="Modules.php?modname=misc/Portal.php">Tableau de bord</a></header>
<main class="ss-day"><h1>Ma journée de classe</h1>
<div class="ss-nav"><a href="ma-journee.php?date=<?=$e($prev)?>">← Jour précédent</a><strong><?=$e(ucfirst(SchoolSafeLetterheadDate($date)))?></strong>
<?php if($date!==$today):?><a href="ma-journee.php?date=<?=$e($next)?>">Jour suivant →</a> <a href="ma-journee.php">Aujourd’hui</a><?php endif;?></div>
<?php if($message):?><p class="ss-ok" role="status"><?=$e($message)?></p><?php endif;?>
<?php if($error):?><p class="ss-ko" role="alert"><?=$e($error)?></p><?php endif;?>
<?php if(!$schoolDay):?><p class="ss-sub">Ce jour n’est pas un jour de classe dans le calendrier de l’école.</p><?php endif;?>
<?php if(!$classes):?><p>Aucune classe prévue pour vous ce jour-là.</p><?php endif;?>
<?php foreach($classes as $c): $cp=(int)$c['COURSE_PERIOD_ID'];$period=(int)$c['PERIOD_ID'];
 $roster=SchoolSafeClassDayRoster($cp,$period,$school,$year,$date,$get);
 $doneCall=(bool)DBGet("SELECT 1 FROM attendance_completed WHERE STAFF_ID=".$staff." AND SCHOOL_DATE='".$date."' AND PERIOD_ID=".$period." AND TABLE_NAME=0");
 $diary=DBGet("SELECT DONE,TODO FROM schoolsafe_class_diary WHERE COURSE_PERIOD_ID=".$cp." AND SCHOOL_DATE='".$date."'");
 $scanned=0; ?>
<section class="ss-class" id="classe-<?=$cp?>">
<h2><?=$e(ParseMLField((string)$c['COURSE_TITLE']))?></h2>
<p class="ss-sub"><?=$e(ParseMLField((string)$c['PERIOD_TITLE']))?><?=$c['START_TIME']?' · '.$e(substr($c['START_TIME'],0,5)).'–'.$e(substr((string)$c['END_TIME'],0,5)):''?><?=$c['ROOM']?' · '.$e($c['ROOM']):''?> · <?=count($roster)?> élève(s) · <?=$doneCall?'<b>appel fait</b>':'appel à faire'?></p>
<?php if($canCall&&$schoolDay&&$roster&&isset($codes['present'],$codes['absent'])):?>
<form method="post" class="ss-call"><input type="hidden" name="csrf" value="<?=$e($csrf)?>"><input type="hidden" name="action" value="appel"><input type="hidden" name="cp" value="<?=$cp?>">
<table class="ss-roll"><tbody>
<?php foreach($roster as $p): $gid=(int)$p['GRADE_ID'];
 if(!array_key_exists($gid,$policies)){try{$policies[$gid]=SchoolSafeAttendanceResolvePolicy($school,$year,$gid,null,$date,$db);}catch(Throwable $x){$policies[$gid]=null;}}
 [$arr,$at]=SchoolSafeClassDayArrival($p['SCANNED_AT']??null,$policies[$gid]['CLOSURE_TIME']??null);
 if($arr!=='non_scanne')$scanned++;
 $pick=SchoolSafeClassDayDefault($codes,($p['SAVED_CODE']??'')!==''&&$p['SAVED_CODE']!==null?(int)$p['SAVED_CODE']:null,$arr); ?>
<tr data-scan="<?=$e($arr)?>"><td><b><?=$e($p['FIRST_NAME'].' '.$p['LAST_NAME'])?></b></td>
<td><?=$arr==='non_scanne'?'<span class="ss-badge b-none">non scanné</span>':($arr==='retard'?'<span class="ss-badge b-late">entré à '.$e($at).' (retard)</span>':'<span class="ss-badge b-ok">entré à '.$e($at).'</span>')?></td>
<td><div class="ss-pick"><?php foreach($labels as $k=>$lab): if(!isset($codes[$k]))continue; ?><label class="<?=$e($k)?>"><input type="radio" name="a_<?=(int)$p['STUDENT_ID']?>" value="<?=$e($k)?>"<?=$k===$pick?' checked':''?>><?=$e($lab)?></label><?php endforeach;?></div></td></tr>
<?php endforeach;?></tbody></table>
<div class="ss-links"><button type="button" class="ss-btn light" data-all="present">Tous présents</button>
<?php if($scanned>0):?><button type="button" class="ss-btn light" data-unscanned="absent">Non scannés absents</button><?php endif;?>
<button type="submit" class="ss-btn">Enregistrer l’appel</button></div></form>
<?php elseif(!$roster):?><p class="ss-sub">Aucun élève placé dans cette classe.</p><?php endif;?>
<form method="post" class="ss-diary"><input type="hidden" name="csrf" value="<?=$e($csrf)?>"><input type="hidden" name="action" value="cahier"><input type="hidden" name="cp" value="<?=$cp?>">
<label><b>Cahier de textes</b> — ce qui a été fait<textarea name="done" maxlength="4000" placeholder="ex. Leçon : les fractions de même dénominateur. Exercices 1 à 4 p. 32."><?=$e($diary[1]['DONE']??'')?></textarea></label>
<label>À faire pour la prochaine fois<textarea name="todo" maxlength="2000" placeholder="ex. Apprendre la règle, exercice 5 p. 33."><?=$e($diary[1]['TODO']??'')?></textarea></label>
<div><button type="submit" class="ss-btn">Enregistrer le cahier de textes</button></div></form>
<div class="ss-links"><a href="Modules.php?modname=Grades/Assignments.php">Donner un devoir</a><a href="Modules.php?modname=Grades/Grades.php">Saisir des notes</a><a href="rattrapage.php">Rattrapage</a><a href="Modules.php?modname=SchoolSafe_Jaspe/Assistant.php">Préparer avec JASPE</a></div>
</section>
<?php endforeach;?></main>
<script>
document.querySelectorAll('.ss-call').forEach(function(f){
 f.querySelectorAll('[data-all]').forEach(function(b){b.addEventListener('click',function(){f.querySelectorAll('input[type=radio][value="'+b.dataset.all+'"]').forEach(function(r){r.checked=true;});});});
 f.querySelectorAll('[data-unscanned]').forEach(function(b){b.addEventListener('click',function(){f.querySelectorAll('tr[data-scan="non_scanne"] input[value="absent"]').forEach(function(r){r.checked=true;});});});
});
</script></body></html>
