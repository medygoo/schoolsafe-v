<?php
// Convocations et courriers (04/11, lot 7 valide) : modele rempli automatiquement, relecture, registre
// numerote, impression au papier de l'ecole, envoi aux parents (fil, e-mail, WhatsApp avec accord).
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','POST'],true)){http_response_code(405);exit;}
if(array_diff(array_keys($_GET),['modele','eleve','classe','pdf','voir'])){http_response_code(400);exit('Requête invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeLetters.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeLetterhead.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeHomeworkDesk.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeSchoolDocument.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeJaspeAssist.fnc.php';
$school=(int)UserSchool();$year=(int)UserSyear();$staff=(int)User('STAFF_ID');$profile=(string)User('PROFILE');
$staffAllowed=$staff>0&&$profile==='admin'&&$school>0&&$year>0&&AllowUse('Students/Student.php');
$models=SchoolSafeLetterModels();
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');

// Document d'un courrier : la direction, ou le parent / l'eleve concerne seulement.
$docId=isset($_GET['pdf'])&&ctype_digit((string)$_GET['pdf'])?(int)$_GET['pdf']:(isset($_GET['voir'])&&ctype_digit((string)$_GET['voir'])?(int)$_GET['voir']:0);
if($docId>0){
    $L=DBGet("SELECT * FROM schoolsafe_letters WHERE ID=".$docId." AND SCHOOL_ID=".$school);
    $L=$L[1]??null;
    $ok=$L&&$staffAllowed;
    if($L&&!$ok&&in_array($profile,['parent','student'],true)){
        $mine=$profile==='student'?"s.STUDENT_ID=".(int)($_SESSION['STUDENT_ID']??0):"EXISTS(SELECT 1 FROM students_join_users su WHERE su.STUDENT_ID=s.STUDENT_ID AND su.STAFF_ID=".$staff.")";
        if($L['STUDENT_ID'])$ok=(bool)DBGet("SELECT 1 FROM students s WHERE s.STUDENT_ID=".(int)$L['STUDENT_ID']." AND ".$mine);
        elseif($L['COURSE_PERIOD_ID'])$ok=(bool)DBGet("SELECT 1 FROM students s JOIN schedule ss ON ss.STUDENT_ID=s.STUDENT_ID AND ss.COURSE_PERIOD_ID=".(int)$L['COURSE_PERIOD_ID']." WHERE ".$mine);
        else $ok=(bool)DBGet("SELECT 1 FROM students s JOIN student_enrollment e ON e.STUDENT_ID=s.STUDENT_ID AND e.SCHOOL_ID=".$school." AND e.SYEAR=".$year." WHERE ".$mine);
    }
    $identity=$ok?SchoolSafeLetterheadIdentity($school):[];
    while(ob_get_level()>$level)ob_end_clean();
    if(!$ok){http_response_code(403);exit('Document non accessible.');}
    $created=substr((string)$L['CREATED_AT'],0,10);
    $sign='<p style="margin-top:14mm;text-align:right">Fait à '.$e($identity['city']?:'…').', le '.$e(SchoolSafeLetterheadDate($created,false)).'.<br><br><b>'.$e($identity['principal']??'').'</b><br>Pour la direction</p>';
    $dest='';
    if($L['STUDENT_ID']){$s=DBGet("SELECT FIRST_NAME,LAST_NAME FROM students WHERE STUDENT_ID=".(int)$L['STUDENT_ID']);$dest=$L['KIND']==='attestation'?'':'Aux parents de '.trim(($s[1]['FIRST_NAME']??'').' '.($s[1]['LAST_NAME']??''));}
    elseif($L['COURSE_PERIOD_ID']){$c=DBGet("SELECT c.TITLE FROM course_periods cp JOIN courses c ON c.COURSE_ID=cp.COURSE_ID WHERE cp.COURSE_PERIOD_ID=".(int)$L['COURSE_PERIOD_ID']);$dest='Aux parents des élèves de '.ParseMLField((string)($c[1]['TITLE']??''));}
    else $dest=$L['KIND']==='reunion'?'Aux parents d’élèves':'';
    $html=SchoolSafeLetterheadPage($identity,(string)($L['LAYOUT']?:$identity['layout']),['title'=>$L['SUBJECT'],'meta'=>[['Référence',$L['REFERENCE']],['Date',SchoolSafeLetterheadDate($created,false)],['Destinataire',$dest]],
        'body'=>SchoolSafeHomeworkTextToHtml((string)$L['BODY']).$sign,'style'=>$identity['style']??'']);
    if(isset($_GET['voir'])){header('Content-Type: text/html; charset=UTF-8');echo $html;exit;}
    try{$pdf=SchoolSafeDocumentPdf($html);header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.$L['REFERENCE'].'.pdf"');echo $pdf;}
    catch(Throwable $x){http_response_code(503);echo 'La génération du PDF est temporairement indisponible.';}
    exit;
}

if(empty($_SESSION['SchoolSafeScannerCsrf']))$_SESSION['SchoolSafeScannerCsrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['SchoolSafeScannerCsrf'];
$message='';$error='';$savedId=0;$inTx=false;
$students=[];$classes=[];
if($staffAllowed){
    foreach((array)DBGet("SELECT s.STUDENT_ID,s.FIRST_NAME,s.LAST_NAME,(SELECT g.TITLE FROM school_gradelevels g WHERE g.ID=e.GRADE_ID) AS LEVEL_TITLE FROM students s JOIN student_enrollment e ON e.STUDENT_ID=s.STUDENT_ID AND e.SCHOOL_ID=".$school." AND e.SYEAR=".$year
        ." AND (e.END_DATE IS NULL OR e.END_DATE>=CURRENT_DATE) ORDER BY s.LAST_NAME,s.FIRST_NAME") as $r)$students[(int)$r['STUDENT_ID']]=$r;
    foreach((array)DBGet("SELECT cp.COURSE_PERIOD_ID,c.TITLE FROM course_periods cp JOIN courses c ON c.COURSE_ID=cp.COURSE_ID WHERE cp.SCHOOL_ID=".$school." AND cp.SYEAR=".$year." ORDER BY c.TITLE") as $r)$classes[(int)$r['COURSE_PERIOD_ID']]=$r;
}
$identity=$staffAllowed?SchoolSafeLetterheadIdentity($school):[];
$vars=static function(int $student,int $class)use($students,$classes,$identity,$year){
    $v=['ecole'=>$identity['school']??'','directeur'=>$identity['principal']??'','annee'=>$year.'-'.($year+1)];
    if($student&&isset($students[$student])){$v['eleve']=trim($students[$student]['FIRST_NAME'].' '.$students[$student]['LAST_NAME']);$v['classe']=ParseMLField((string)$students[$student]['LEVEL_TITLE']);}
    if($class&&isset($classes[$class]))$v['classe']=ParseMLField((string)$classes[$class]['TITLE']);
    elseif(!$student)$v['classe']='l’école';
    return $v;
};
$send=static function(array $L)use($school,$year){
    require_once __DIR__.'/ProgramFunctions/SchoolSafeNotify.fnc.php';
    $db=['get'=>function($sql){return DBGet(DBQuery($sql));},'exec'=>function($sql){DBQuery($sql);}];
    if($L['STUDENT_ID'])$targets=[(int)$L['STUDENT_ID']];
    elseif($L['COURSE_PERIOD_ID'])$targets=array_map('intval',array_column((array)DBGet("SELECT DISTINCT STUDENT_ID FROM schedule WHERE COURSE_PERIOD_ID=".(int)$L['COURSE_PERIOD_ID']." AND START_DATE<=CURRENT_DATE AND (END_DATE IS NULL OR END_DATE>=CURRENT_DATE)"),'STUDENT_ID'));
    else $targets=array_map('intval',array_column((array)DBGet("SELECT DISTINCT STUDENT_ID FROM student_enrollment WHERE SCHOOL_ID=".$school." AND SYEAR=".$year." AND (END_DATE IS NULL OR END_DATE>=CURRENT_DATE) LIMIT 800"),'STUDENT_ID'));
    $base=rtrim(trim((string)getenv('SCHOOLSAFE_ASSETS_BASE_URL'))?:'https://app.shoolsafe.tech','/');
    $msg=SchoolSafeLetterMessage((string)$L['REFERENCE'],(string)$L['SUBJECT'],(string)$L['BODY'],$base.'/courriers.php?pdf='.(int)$L['ID'],(string)SchoolInfo('TITLE'));
    $count=0;
    foreach($targets as $student){
        try{
            $recipients=SchoolSafeNotifyResolveRecipients('school.announcement',$school,$year,$student,$db);
            if(!$recipients)continue;
            $out=SchoolSafeNotifyEmit(['school_id'=>$school,'syear'=>$year,'student_id'=>$student,'actor_staff_id'=>(int)User('STAFF_ID'),'event_type'=>'school.announcement',
                'dedup_key'=>'letter:'.(int)$L['ID'].':'.$student,'payload'=>['source'=>'letter','letter_id'=>(int)$L['ID'],'title'=>$msg[0],'message'=>$msg[1]]],$recipients,$db,true);
            if(is_array($out)&&($out['created']??false))$count++;
        }catch(Throwable $x){error_log('SchoolSafe letter send: '.$x->getMessage());}
    }
    DBQuery("UPDATE schoolsafe_letters SET SENT_AT=now(),SENT_COUNT=SENT_COUNT+".$count." WHERE ID=".(int)$L['ID']);
    return $count;
};
$jaspeBody='';$jaspeIntent='';
if($staffAllowed&&$_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf']))throw new RuntimeException('Session expirée : rechargez la page.');
        foreach($_POST as $v)if(!is_string($v))throw new RuntimeException('Formulaire invalide.');
        if(($_POST['action']??'')==='enregistrer'){
            $v=SchoolSafeLetterValidate($_POST,array_fill_keys(array_keys($students),true),array_fill_keys(array_keys($classes),true));
            $fill=$vars($v['student'],$v['class']);
            if($v['meeting']){$fill['date_rdv']=SchoolSafeLetterheadDate(substr($v['meeting'],0,10));$fill['heure_rdv']=str_replace(':',' h ',substr($v['meeting'],11,5));}
            $fill['lieu']=$v['place']!==''?$v['place']:'dans les locaux de l’école';
            $subject=SchoolSafeLetterFill($v['subject'],$fill);$body=SchoolSafeLetterFill($v['body'],$fill);
            $missing=SchoolSafeLetterMissing($subject.' '.$body);
            if($missing)throw new RuntimeException('Champs à compléter dans le texte : {'.implode('}, {',$missing).'}.');
            $q=static function($x){return "'".DBEscapeString($x)."'";};
            DBQuery('BEGIN');$inTx=true;
            DBQuery('LOCK TABLE schoolsafe_letters IN SHARE ROW EXCLUSIVE MODE');
            $n=(int)(DBGetOne("SELECT count(*) FROM schoolsafe_letters WHERE SCHOOL_ID=".$school." AND SYEAR=".$year." AND REFERENCE LIKE '".$v['prefix']."-%'")?:0)+1;
            $ref=SchoolSafeLetterReference($v['prefix'],$year,$n);
            $row=DBGet("INSERT INTO schoolsafe_letters(SCHOOL_ID,SYEAR,REFERENCE,KIND,STUDENT_ID,COURSE_PERIOD_ID,SUBJECT,BODY,MEETING_AT,PLACE,LAYOUT,STAFF_ID) VALUES("
                .$school.",".$year.",".$q($ref).",".$q($v['kind']).",".($v['student']?:'NULL').",".($v['class']?:'NULL').",".$q($subject).",".$q($body).","
                .($v['meeting']?$q($v['meeting']):'NULL').",".($v['place']!==''?$q($v['place']):'NULL').",".$q((string)$identity['layout']).",".$staff.") RETURNING *");
            DBQuery('COMMIT');$inTx=false;
            $savedId=(int)$row[1]['ID'];
            $message='Courrier '.$ref.' enregistré.';
            if($v['send']){$sent=$send($row[1]);$message.=' Envoyé aux parents : '.$sent.' élève(s) concerné(s).';}
        }elseif(($_POST['action']??'')==='envoyer'){
            $L=DBGet("SELECT * FROM schoolsafe_letters WHERE ID=".(int)($_POST['id']??0)." AND SCHOOL_ID=".$school);
            if(!$L)throw new RuntimeException('Courrier introuvable.');
            if($L[1]['KIND']==='attestation')throw new RuntimeException('Une attestation s’imprime ; elle ne s’envoie pas.');
            $sent=$send($L[1]);$savedId=(int)$L[1]['ID'];
            $message='Courrier '.$L[1]['REFERENCE'].' envoyé : '.$sent.' élève(s) dont les parents n’avaient pas encore reçu ce courrier.';
        }elseif(($_POST['action']??'')==='jaspe'){
            // Lot 10 : JASPE propose un texte ; la direction relit et complete avant d'enregistrer.
            $k=(string)($_POST['kind']??'');
            if(!isset($models[$k]))throw new RuntimeException('Choisissez un modèle.');
            $jaspeIntent=mb_substr((string)($_POST['intent']??''),0,400);
            $prompt=SchoolSafeJaspeLetterPrompt($models[$k][0],SchoolSafeJaspeMask($jaspeIntent,SchoolSafeJaspeStudentNames($school,$year,static function($sql){return DBGet($sql);})));
            if(!SchoolSafeJaspeAssistAllowed())throw new RuntimeException('Trop de demandes à JASPE : patientez une minute.');
            $reply=SchoolSafeJaspeAssistAsk($prompt,'courrier',1500,null,'Pour cette demande : écris le courrier complet demandé (120 à 220 mots), sans te limiter à quatre phrases.');
            if($reply===null)throw new RuntimeException('JASPE est indisponible pour le moment : partez du modèle ci-dessous.');
            $jaspeBody=SchoolSafeJaspeLetterClean($reply);
            $message='Brouillon de JASPE prêt : relisez-le, complétez les « … » et vérifiez chaque mot avant d’enregistrer.';
        }else throw new RuntimeException('Action inconnue.');
    }catch(Throwable $failure){
        if($inTx)DBQuery('ROLLBACK');
        $error=$failure instanceof RuntimeException?$failure->getMessage():'Enregistrement impossible.';
    }
}
$kind=isset($_GET['modele'])&&is_string($_GET['modele'])&&isset($models[$_GET['modele']])?$_GET['modele']:'';
$pickStudent=isset($_GET['eleve'])&&ctype_digit((string)$_GET['eleve'])&&isset($students[(int)$_GET['eleve']])?(int)$_GET['eleve']:0;
$pickClass=isset($_GET['classe'])&&ctype_digit((string)$_GET['classe'])&&isset($classes[(int)$_GET['classe']])?(int)$_GET['classe']:0;
$draftSubject=$kind?SchoolSafeLetterFill($models[$kind][2],$vars($pickStudent,$pickClass)):'';
$draftBody=$kind?SchoolSafeLetterFill($models[$kind][3],$vars($pickStudent,$pickClass)):'';
if($kind&&$jaspeBody!=='')$draftBody=SchoolSafeLetterFill($jaspeBody,$vars($pickStudent,$pickClass));
$register=$staffAllowed?(array)DBGet("SELECT l.*,s.FIRST_NAME,s.LAST_NAME,c.TITLE AS CLASS_TITLE FROM schoolsafe_letters l LEFT JOIN students s ON s.STUDENT_ID=l.STUDENT_ID LEFT JOIN course_periods cp ON cp.COURSE_PERIOD_ID=l.COURSE_PERIOD_ID LEFT JOIN courses c ON c.COURSE_ID=cp.COURSE_ID"
    ." WHERE l.SCHOOL_ID=".$school." AND l.SYEAR=".$year." ORDER BY l.CREATED_AT DESC LIMIT 100"):[];
while(ob_get_level()>$level)ob_end_clean();
if(!$staffAllowed){http_response_code(403);exit('Page réservée à l’administration de l’école.');}
$need=$kind?$models[$kind][4]:'';
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Convocations et courriers | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<style>.ss-l{display:grid;gap:16px;max-width:980px}.ss-box{border:1px solid #c9d8d2;border-radius:12px;padding:14px;display:grid;gap:10px;background:#fff}.ss-box h2{margin:0;font-size:19px;color:#08766b}
.ss-l label{display:grid;gap:4px;font-size:14px}.ss-l input[type=text],.ss-l input[type=date],.ss-l input[type=time],.ss-l select,.ss-l textarea{min-height:38px;font-size:15px;box-sizing:border-box;width:100%}.ss-l textarea{min-height:260px}
.ss-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px}.ss-chips{display:flex;gap:8px;flex-wrap:wrap}.ss-chips a{border:1px solid #c9d8d2;border-radius:16px;padding:6px 12px;background:#f4fbf8;color:#08766b;text-decoration:none}
.ss-chips a.on{background:#08766b;color:#fff}.ss-btn{min-height:44px;background:#08766b;color:#fff;border:0;border-radius:8px;font-size:15px;padding:0 16px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center}
.ss-table{width:100%;border-collapse:collapse;font-size:14px}.ss-table td,.ss-table th{border-bottom:1px solid #e3ebe8;padding:6px;text-align:left;vertical-align:top}.ss-ok{color:#08766b;font-weight:600}.ss-ko{color:#a32d2d;font-weight:600}.ss-sub{color:#556777;font-size:13px}</style><?php require_once __DIR__.'/ProgramFunctions/SchoolSafePedagogyNav.fnc.php'; echo SchoolSafePedagogyStyle(); ?></head>
<body><?php echo SchoolSafePedagogyHeader('courriers.php',0); ?>
<main class="ss-l"><h1>Convocations et courriers</h1>
<?php if($message):?><p class="ss-ok" role="status"><?=$e($message)?><?php if($savedId):?> <a href="courriers.php?voir=<?=$savedId?>" target="_blank" rel="noopener">Voir</a> · <a href="courriers.php?pdf=<?=$savedId?>">Imprimer (PDF)</a><?php endif;?></p><?php endif;?>
<?php if($error):?><p class="ss-ko" role="alert"><?=$e($error)?></p><?php endif;?>
<section class="ss-box"><h2>1. Choisir le modèle</h2><div class="ss-chips"><?php foreach($models as $k=>$m):?><a class="<?=$k===$kind?'on':''?>" href="courriers.php?modele=<?=$e($k)?>"><?=$e($m[0])?></a><?php endforeach;?></div></section>
<?php if($kind):?>
<?php if($need!=='aucun'):?><section class="ss-box"><h2>2. Destinataire</h2>
<form method="get" class="ss-grid"><input type="hidden" name="modele" value="<?=$e($kind)?>">
<?php if($need==='eleve'):?><label>Élève<select name="eleve" onchange="this.form.submit()"><option value="">— choisir —</option><?php foreach($students as $id=>$s):?><option value="<?=$id?>"<?=$id===$pickStudent?' selected':''?>><?=$e($s['LAST_NAME'].' '.$s['FIRST_NAME'].' — '.ParseMLField((string)$s['LEVEL_TITLE']))?></option><?php endforeach;?></select></label>
<?php else:?><label>Classe<select name="classe" onchange="this.form.submit()"><option value="">Tous les parents de l’école</option><?php foreach($classes as $id=>$c):?><option value="<?=$id?>"<?=$id===$pickClass?' selected':''?>><?=$e(ParseMLField((string)$c['TITLE']))?></option><?php endforeach;?></select></label><?php endif;?>
<noscript><button class="ss-btn" type="submit">Choisir</button></noscript></form></section><?php endif;?>
<?php if($need!=='eleve'||$pickStudent):?>
<section class="ss-box"><h2><?=$need==='aucun'?'2':'3'?>. Relire et compléter</h2>
<p class="ss-sub">Les informations de l’école, de l’élève et de la classe sont déjà remplies. Complétez les « … » et vérifiez le texte avant d’enregistrer.</p>
<form method="post" class="ss-grid" style="align-items:end;background:#f4fbf8;border:1px dashed #08766b;border-radius:10px;padding:10px"><input type="hidden" name="csrf" value="<?=$e($csrf)?>"><input type="hidden" name="action" value="jaspe"><input type="hidden" name="kind" value="<?=$e($kind)?>">
<label style="grid-column:1/-1">✨ Rédiger avec JASPE — dites en quelques mots ce que le courrier doit dire<input type="text" name="intent" maxlength="400" value="<?=$e($jaspeIntent)?>" placeholder="ex. retards répétés le matin depuis deux semaines ; rencontrer les parents pour trouver une solution"></label>
<div><button class="ss-btn" type="submit">Proposer un texte</button></div><p class="ss-sub" style="grid-column:1/-1;margin:0">JASPE propose, vous relisez et corrigez. Les noms des élèves ne lui sont jamais envoyés.</p></form>
<form method="post"><input type="hidden" name="csrf" value="<?=$e($csrf)?>"><input type="hidden" name="action" value="enregistrer"><input type="hidden" name="kind" value="<?=$e($kind)?>">
<input type="hidden" name="student" value="<?=$pickStudent?>"><input type="hidden" name="class" value="<?=$pickClass?>">
<?php if($models[$kind][5]):?><div class="ss-grid"><label>Date du rendez-vous<input type="date" name="date" required></label><label>Heure<input type="time" name="time" value="08:00" required></label><label>Lieu<input type="text" name="place" maxlength="200" placeholder="ex. au bureau de la direction"></label></div><?php endif;?>
<label>Objet<input type="text" name="subject" maxlength="200" required value="<?=$e($draftSubject)?>"></label>
<label>Texte<textarea name="body" maxlength="10000" required><?=$e($draftBody)?></textarea></label>
<?php if($models[$kind][6]):?><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="send" value="1" checked> Envoyer aussi aux parents (fil, e-mail, WhatsApp) avec le document à imprimer</label><?php endif;?>
<div><button class="ss-btn" type="submit">Enregistrer<?=$models[$kind][6]?' et envoyer':''?></button></div></form></section>
<?php endif;?><?php endif;?>
<section class="ss-box"><h2>Registre des courriers <?=$e($year.'-'.($year+1))?></h2>
<?php if(!$register):?><p class="ss-sub">Aucun courrier enregistré.</p><?php else:?>
<table class="ss-table"><thead><tr><th>Référence</th><th>Objet</th><th>Destinataire</th><th>Envoi</th><th></th></tr></thead><tbody>
<?php foreach($register as $r):?><tr><td><b><?=$e($r['REFERENCE'])?></b><br><span class="ss-sub"><?=$e(SchoolSafeLetterheadDate(substr((string)$r['CREATED_AT'],0,10),false))?></span></td><td><?=$e($r['SUBJECT'])?></td>
<td><?=$r['STUDENT_ID']?$e($r['FIRST_NAME'].' '.$r['LAST_NAME']):($r['COURSE_PERIOD_ID']?$e(ParseMLField((string)$r['CLASS_TITLE'])):'Toute l’école')?></td>
<td><?=$r['SENT_AT']?$e((int)$r['SENT_COUNT'].' élève(s)'):($r['KIND']==='attestation'?'—':'non envoyé')?></td>
<td><div class="ss-chips"><a href="courriers.php?voir=<?=(int)$r['ID']?>" target="_blank" rel="noopener">Voir</a><a href="courriers.php?pdf=<?=(int)$r['ID']?>">PDF</a>
<?php if($r['KIND']!=='attestation'&&!$r['SENT_AT']):?><form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?=$e($csrf)?>"><input type="hidden" name="action" value="envoyer"><input type="hidden" name="id" value="<?=(int)$r['ID']?>"><button class="ss-btn" style="min-height:30px;font-size:13px" type="submit">Envoyer</button></form><?php endif;?></div></td></tr><?php endforeach;?>
</tbody></table><?php endif;?></section></main></body></html>
