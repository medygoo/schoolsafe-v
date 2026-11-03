<?php
// Signalement par le parent (03/11) : maladie, absence prevue, retard prevu. L'enfant n'est pas
// signale absent a tort ce jour-la (schoolsafe_attendance_exceptions) et la direction est prevenue.
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)||$_GET){http_response_code(400);exit('Requête invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeNotify.fnc.php';
$parent=(int)User('STAFF_ID');$year=(int)UserSyear();
$allowed=$parent>0&&User('PROFILE')==='parent'&&$year>0;
if(empty($_SESSION['SchoolSafeParentReportCsrf']))$_SESSION['SchoolSafeParentReportCsrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['SchoolSafeParentReportCsrf'];
$get=function($sql){return DBGet(DBQuery($sql));};
$exec=function($sql){DBQuery($sql);};
$motives=['maladie'=>'Maladie','absence'=>'Absence prévue','retard'=>'Retard prévu','autre'=>'Autre motif'];
// Enfants lies a ce compte parent (lien natif) et inscrits cette annee.
$children=$allowed?$get('SELECT s.STUDENT_ID,s.FIRST_NAME,s.LAST_NAME,e.SCHOOL_ID,sc.TITLE AS SCHOOL_TITLE FROM students_join_users j'
    .' JOIN students s ON s.STUDENT_ID=j.STUDENT_ID JOIN student_enrollment e ON e.STUDENT_ID=s.STUDENT_ID AND e.SYEAR='.$year
    .' AND (e.END_DATE IS NULL OR e.END_DATE>=CURRENT_DATE) JOIN schools sc ON sc.ID=e.SCHOOL_ID AND sc.SYEAR=e.SYEAR'
    .' WHERE j.STAFF_ID='.$parent.' ORDER BY s.FIRST_NAME'):[];
$message='';$error='';
if($allowed&&$_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf']))throw new RuntimeException('Session expirée : rechargez la page.');
        $child=null;foreach((array)$children as$c)if((string)$c['STUDENT_ID']===(string)($_POST['student']??''))$child=$c;
        if(!$child)throw new RuntimeException('Choisissez votre enfant.');
        $motive=(string)($_POST['motive']??'');if(!isset($motives[$motive]))throw new RuntimeException('Choisissez un motif.');
        $day=(string)($_POST['day']??'');
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$day)||$day<date('Y-m-d')||$day>date('Y-m-d',strtotime('+30 days')))
            throw new RuntimeException('Choisissez une date entre aujourd’hui et les 30 prochains jours.');
        $note=mb_substr(trim((string)($_POST['note']??'')),0,300);
        $school=(int)$child['SCHOOL_ID'];$student=(int)$child['STUDENT_ID'];
        $reason=$motives[$motive].($note!==''?' — '.$note:'').' (signalé par le parent)';
        $sql="'".str_replace("'","''",$reason)."'";
        $get('INSERT INTO schoolsafe_attendance_exceptions(SCHOOL_ID,SYEAR,STUDENT_ID,SCHOOL_DATE,REASON) VALUES('.$school.','.$year.','.$student.",'".$day."',".$sql.')'
            .' ON CONFLICT (SCHOOL_ID,STUDENT_ID,SCHOOL_DATE) DO UPDATE SET REASON=EXCLUDED.REASON RETURNING ID');
        // La direction de l'ecole recoit le signalement dans son fil.
        $recipients=[];
        foreach((array)$get("SELECT STAFF_ID,SCHOOLS FROM staff WHERE PROFILE='admin' AND SYEAR=".$year)as$admin)
            if(preg_match('/(^|,)'.$school.'(,|$)/',trim((string)($admin['SCHOOLS']??''),',')))
                $recipients[]=['audience'=>'direction','staff_id'=>(int)$admin['STAFF_ID'],'channel'=>'feed'];
        $who=trim($child['FIRST_NAME'].' '.$child['LAST_NAME']);
        $text='📝 Signalement du parent : '.$who.' — '.mb_strtolower($motives[$motive]).' le '.date('d/m/Y',strtotime($day)).'.'
            .($note!==''?"\nNote : ".$note:'')."\n— ".$child['SCHOOL_TITLE'].' · SchoolSafe';
        if($recipients)SchoolSafeNotifyEmit(['school_id'=>$school,'syear'=>$year,'student_id'=>$student,'actor_staff_id'=>$parent,
            'event_type'=>'absence.detected','dedup_key'=>'parent-report:'.$school.':'.$student.':'.$day.':'.$motive,
            'payload'=>['source'=>'parent_report','school_date'=>$day,'title'=>'Signalement du parent','message'=>$text]],$recipients,['get'=>$get,'exec'=>$exec],true);
        $message='Merci. L’école est prévenue : '.$who.', '.mb_strtolower($motives[$motive]).' le '.date('d/m/Y',strtotime($day)).'.';
    }catch(Throwable $failure){$error=$failure instanceof RuntimeException?$failure->getMessage():'Signalement impossible pour le moment.';}
}
while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(403);exit('Réservé aux parents connectés.');}
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Signaler une absence | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<style>.ss-report{display:grid;gap:12px;max-width:520px}.ss-report label{display:grid;gap:4px;font-size:15px}
.ss-report select,.ss-report input,.ss-report textarea{min-height:44px;font-size:16px;padding:6px}
.ss-report button{min-height:48px;background:#08766b;color:#fff;border:0;border-radius:6px;font-size:16px}
.ss-ok{color:#08766b;font-weight:600}.ss-ko{color:#a32d2d}</style></head>
<body><header><strong>SchoolSafe</strong><a href="Modules.php?modname=misc/Portal.php">Tableau de bord</a></header>
<main><h1>Signaler une absence ou un retard</h1>
<p>Prévenez l’école si votre enfant est malade, absent ou en retard : il ne sera pas signalé absent à tort.</p>
<?php if($message):?><p class="ss-ok" role="status"><?=$e($message)?></p><?php endif;?>
<?php if($error):?><p class="ss-ko" role="alert"><?=$e($error)?></p><?php endif;?>
<?php if(!$children):?><p>Aucun enfant n’est rattaché à votre compte cette année. Contactez l’école.</p><?php else:?>
<form method="post" class="ss-report"><input type="hidden" name="csrf" value="<?=$e($csrf)?>">
<label>Enfant<select name="student" required><?php foreach($children as$c):?><option value="<?=(int)$c['STUDENT_ID']?>"><?=$e($c['FIRST_NAME'].' '.$c['LAST_NAME'].' — '.$c['SCHOOL_TITLE'])?></option><?php endforeach;?></select></label>
<label>Jour<input type="date" name="day" required value="<?=date('Y-m-d')?>" min="<?=date('Y-m-d')?>" max="<?=date('Y-m-d',strtotime('+30 days'))?>"></label>
<label>Motif<select name="motive" required><?php foreach($motives as$k=>$v):?><option value="<?=$k?>"><?=$e($v)?></option><?php endforeach;?></select></label>
<label>Note pour l’école (facultatif)<textarea name="note" maxlength="300" rows="3"></textarea></label>
<button type="submit">Prévenir l’école</button></form><?php endif;?></main></body></html>
