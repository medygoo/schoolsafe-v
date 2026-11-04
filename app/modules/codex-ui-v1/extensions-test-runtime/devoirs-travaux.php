<?php
// « Devoirs et travaux » (04/11, lot 5 valide) : creer un devoir depuis un modele ou la banque de l'ecole,
// suivre rendus et notes, imprimer la classe, corriger vite. Ecrit les tables natives (devoirs, notes) :
// les parents recoivent le devoir automatiquement, le rattrapage reagit aux notes.
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','POST'],true)){http_response_code(405);exit;}
if(array_diff(array_keys($_GET),['corriger','modele','banque'])){http_response_code(400);exit('Requête invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeHomeworkDesk.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeLetterhead.fnc.php';
$school=(int)UserSchool();$year=(int)UserSyear();$staff=(int)User('STAFF_ID');
$allowed=$staff>0&&User('PROFILE')==='teacher'&&$school>0&&$year>0&&AllowUse('Grades/Assignments.php');
$canGrade=$allowed&&AllowUse('Grades/Grades.php');
if(empty($_SESSION['SchoolSafeScannerCsrf']))$_SESSION['SchoolSafeScannerCsrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['SchoolSafeScannerCsrf'];
$tz=new DateTimeZone('Africa/Kinshasa');$today=(new DateTime('now',$tz))->format('Y-m-d');
$message='';$error='';$created=0;$inTx=false;
$classes=[];$types=[];$typeRows=[];
if($allowed){
    foreach((array)DBGet("SELECT cp.COURSE_PERIOD_ID,cp.COURSE_ID,cp.TITLE,c.TITLE AS COURSE_TITLE,(SELECT g.TITLE FROM school_gradelevels g WHERE g.ID=c.GRADE_LEVEL) AS LEVEL_TITLE FROM course_periods cp JOIN courses c ON c.COURSE_ID=cp.COURSE_ID"
        ." WHERE cp.SCHOOL_ID=".$school." AND cp.SYEAR=".$year." AND (cp.TEACHER_ID=".$staff." OR cp.SECONDARY_TEACHER_ID=".$staff.") ORDER BY c.TITLE") as $r)$classes[(int)$r['COURSE_PERIOD_ID']]=$r;
    $courseIds=array_unique(array_map(static function($r){return (int)$r['COURSE_ID'];},$classes));
    if($courseIds)foreach((array)DBGet("SELECT ASSIGNMENT_TYPE_ID,COURSE_ID,TITLE FROM gradebook_assignment_types WHERE STAFF_ID=".$staff." AND COURSE_ID IN (".implode(',',$courseIds).") ORDER BY SORT_ORDER,TITLE") as $t){$types[(int)$t['ASSIGNMENT_TYPE_ID']]=(int)$t['COURSE_ID'];$typeRows[]=$t;}
}
$mine=static function(int $assignment)use($staff,$classes){
    if(!$classes)return null;
    $r=DBGet("SELECT ga.*,t.TITLE AS TYPE_TITLE FROM gradebook_assignments ga LEFT JOIN gradebook_assignment_types t ON t.ASSIGNMENT_TYPE_ID=ga.ASSIGNMENT_TYPE_ID WHERE ga.ASSIGNMENT_ID=".$assignment." AND ga.STAFF_ID=".$staff
        ." AND (ga.COURSE_PERIOD_ID IN (".implode(',',array_keys($classes)).") OR (ga.COURSE_PERIOD_ID IS NULL AND ga.COURSE_ID IN (".implode(',',array_unique(array_map(static function($c){return (int)$c['COURSE_ID'];},$classes))).")))");
    return $r[1]??null;
};
$pupils=static function(array $a)use($classes,$staff){
    $cps=$a['COURSE_PERIOD_ID']?[(int)$a['COURSE_PERIOD_ID']]:array_keys(array_filter($classes,static function($c)use($a){return (int)$c['COURSE_ID']===(int)$a['COURSE_ID'];}));
    if(!$cps)return [];
    return (array)DBGet("SELECT DISTINCT ON (s.STUDENT_ID) s.STUDENT_ID,s.FIRST_NAME,s.LAST_NAME,ss.COURSE_PERIOD_ID,gg.POINTS,gg.COMMENT,(SELECT 1 FROM student_assignments sa WHERE sa.ASSIGNMENT_ID=".(int)$a['ASSIGNMENT_ID']." AND sa.STUDENT_ID=s.STUDENT_ID LIMIT 1) AS HANDED_IN,(SELECT 1 FROM schoolsafe_grade_copies gc WHERE gc.ASSIGNMENT_ID=".(int)$a['ASSIGNMENT_ID']." AND gc.STUDENT_ID=s.STUDENT_ID) AS HAS_COPY,(SELECT count(*) FROM schoolsafe_grade_seen gs WHERE gs.ASSIGNMENT_ID=".(int)$a['ASSIGNMENT_ID']." AND gs.STUDENT_ID=s.STUDENT_ID) AS SEEN"
        ." FROM schedule ss JOIN students s ON s.STUDENT_ID=ss.STUDENT_ID LEFT JOIN gradebook_grades gg ON gg.STUDENT_ID=s.STUDENT_ID AND gg.ASSIGNMENT_ID=".(int)$a['ASSIGNMENT_ID']
        ." WHERE ss.COURSE_PERIOD_ID IN (".implode(',',$cps).") AND ss.START_DATE<=CURRENT_DATE AND (ss.END_DATE IS NULL OR ss.END_DATE>=CURRENT_DATE) ORDER BY s.STUDENT_ID,s.LAST_NAME");
};
if($allowed&&$_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf']))throw new RuntimeException('Session expirée : rechargez la page.');
        foreach($_POST as $v)if(!is_string($v))throw new RuntimeException('Formulaire invalide.');
        $action=$_POST['action']??'';
        if($action==='creer'){
            $v=SchoolSafeHomeworkValidate($_POST,$classes,$types);
            $mp=(int)GetCurrentMP('QTR',$v['assigned'],false);
            if($mp<1)throw new RuntimeException('Aucune période de notes ne couvre la date du devoir.');
            $q=static function($x){return "'".DBEscapeString($x)."'";};
            DBQuery('BEGIN');$inTx=true;
            $row=DBGet("INSERT INTO gradebook_assignments(ASSIGNMENT_TYPE_ID,STAFF_ID,MARKING_PERIOD_ID,COURSE_PERIOD_ID,TITLE,ASSIGNED_DATE,DUE_DATE,POINTS,DESCRIPTION,SUBMISSION)"
                ." VALUES(".$v['type'].",".$staff.",".$mp.",".$v['cp'].",".$q($v['title']).",'".$v['assigned']."','".$v['due']."',".$v['points'].",".$q($v['html']).",".($v['submission']?"'Y'":'NULL').") RETURNING ASSIGNMENT_ID");
            $created=(int)($row[1]['ASSIGNMENT_ID']??0);
            if(!empty($_POST['to_bank'])){
                $c=$classes[$v['cp']];
                DBQuery("INSERT INTO schoolsafe_exercise_bank(SCHOOL_ID,SUBJECT,LEVEL,TITLE,CONTENT,STAFF_ID) VALUES(".$school.",".$q(mb_substr(ParseMLField((string)$c['COURSE_TITLE']),0,120)).",".$q(mb_substr(ParseMLField((string)($c['LEVEL_TITLE']??'')),0,120)).",".$q($v['title']).",".$q($v['text']).",".$staff.")");
            }
            DBQuery('COMMIT');$inTx=false;
            $message='Devoir créé.'.(strcmp($v['assigned'],$today)<=0?' Les parents le reçoivent automatiquement dans quelques instants, avec la fiche au nom de leur enfant.':' Les parents le recevront automatiquement le '.SchoolSafeLetterheadDate($v['assigned']).'.').(!empty($_POST['to_bank'])?' Consignes rangées dans la banque de l’école.':'');
        }elseif($action==='noter'){
            if(!$canGrade)throw new RuntimeException('Permission requise : saisir les notes.');
            $a=$mine((int)($_POST['assignment']??0));
            if(!$a)throw new RuntimeException('Ce devoir n’est pas le vôtre.');
            $list=$pupils($a);
            $byStudent=[];foreach($list as $p)$byStudent[(int)$p['STUDENT_ID']]=(int)$p['COURSE_PERIOD_ID'];
            $grades=SchoolSafeHomeworkGrades($_POST,array_keys($byStudent),(float)$a['POINTS']);
            DBQuery('BEGIN');$inTx=true;$count=0;
            foreach($grades as $student=>[$points,$comment]){
                $where=" WHERE STUDENT_ID=".$student." AND ASSIGNMENT_ID=".(int)$a['ASSIGNMENT_ID'];
                $exists=DBGet("SELECT 1 FROM gradebook_grades".$where);
                $c="'".DBEscapeString($comment)."'";
                if($points===null&&$comment===''){if($exists)DBQuery("UPDATE gradebook_grades SET POINTS=NULL,COMMENT=NULL".$where);continue;}
                $p=$points===null?'NULL':$points;
                if($exists)DBQuery("UPDATE gradebook_grades SET POINTS=".$p.",COMMENT=".$c.",COURSE_PERIOD_ID=".$byStudent[$student].$where);
                else DBQuery("INSERT INTO gradebook_grades(STUDENT_ID,COURSE_PERIOD_ID,ASSIGNMENT_ID,POINTS,COMMENT) VALUES(".$student.",".$byStudent[$student].",".(int)$a['ASSIGNMENT_ID'].",".$p.",".$c.")");
                if($points!==null)$count++;
            }
            // Corrige type (04/11) : montre aux familles apres la date de remise.
            $answer=trim(str_replace("\r\n","\n",(string)($_POST['answer']??'')));
            if(mb_strlen($answer)>20000)throw new RuntimeException('Corrigé trop long (20 000 caractères au plus).');
            if($answer!=='')DBQuery("INSERT INTO schoolsafe_assignment_keys(ASSIGNMENT_ID,ANSWER,STAFF_ID) VALUES(".(int)$a['ASSIGNMENT_ID'].",'".DBEscapeString($answer)."',".$staff.")"
                ." ON CONFLICT(ASSIGNMENT_ID) DO UPDATE SET ANSWER=EXCLUDED.ANSWER,STAFF_ID=EXCLUDED.STAFF_ID,UPDATED_AT=now()");
            DBQuery('COMMIT');$inTx=false;
            $message='Notes enregistrées : '.$count.' élève(s) noté(s). Chaque parent reçoit la note de son enfant avec la correction ; le carnet de notes et le rattrapage sont à jour.';
        }elseif($action==='copie'){
            // Copie corrigee (04/11) : photo ou PDF, visible par le parent avec la note.
            if(!$canGrade)throw new RuntimeException('Permission requise : saisir les notes.');
            $a=$mine((int)($_POST['assignment']??0));
            if(!$a)throw new RuntimeException('Ce devoir n’est pas le vôtre.');
            $sid=(int)($_POST['student']??0);$known=false;
            foreach($pupils($a) as $p)if((int)$p['STUDENT_ID']===$sid)$known=true;
            if(!$known)throw new RuntimeException('Élève inconnu dans cette classe.');
            $file=$_FILES['copie']??null;
            if(!is_array($file)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file((string)$file['tmp_name']))
                throw new RuntimeException(in_array($file['error']??0,[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true)?'Photo trop lourde (8 Mo au plus).':'Choisissez une photo ou un PDF de la copie.');
            require_once __DIR__.'/ProgramFunctions/SchoolSafeArchive.fnc.php';
            [$mime,$bytes]=SchoolSafeArchiveFile((string)$file['tmp_name']);
            DBQuery("INSERT INTO schoolsafe_grade_copies(ASSIGNMENT_ID,STUDENT_ID,MIME,SIZE_BYTES,SHA256,CONTENT,CREATED_BY) VALUES(".(int)$a['ASSIGNMENT_ID'].",".$sid.",'".$mime."',".strlen($bytes).",'".hash('sha256',$bytes)."',decode('".base64_encode($bytes)."','base64'),".$staff.")"
                ." ON CONFLICT(ASSIGNMENT_ID,STUDENT_ID) DO UPDATE SET MIME=EXCLUDED.MIME,SIZE_BYTES=EXCLUDED.SIZE_BYTES,SHA256=EXCLUDED.SHA256,CONTENT=EXCLUDED.CONTENT,CREATED_BY=EXCLUDED.CREATED_BY,CREATED_AT=now()");
            $message='Copie corrigée enregistrée : le parent la voit avec la note.';
        }else throw new RuntimeException('Action inconnue.');
    }catch(Throwable $failure){
        if($inTx)DBQuery('ROLLBACK');
        $error=$failure instanceof RuntimeException?$failure->getMessage():'Enregistrement impossible.';
    }
}
$list=$allowed&&$classes?(array)DBGet("SELECT ga.ASSIGNMENT_ID,ga.TITLE,ga.ASSIGNED_DATE,ga.DUE_DATE,ga.POINTS,ga.COURSE_PERIOD_ID,ga.COURSE_ID,t.TITLE AS TYPE_TITLE,c.TITLE AS COURSE_TITLE,"
    ."(SELECT count(*) FROM student_assignments sa WHERE sa.ASSIGNMENT_ID=ga.ASSIGNMENT_ID) AS HANDED,(SELECT count(*) FROM gradebook_grades gg WHERE gg.ASSIGNMENT_ID=ga.ASSIGNMENT_ID AND gg.POINTS IS NOT NULL) AS GRADED"
    ." FROM gradebook_assignments ga LEFT JOIN gradebook_assignment_types t ON t.ASSIGNMENT_TYPE_ID=ga.ASSIGNMENT_TYPE_ID LEFT JOIN courses c ON c.COURSE_ID=COALESCE(ga.COURSE_ID,t.COURSE_ID)"
    ." WHERE ga.STAFF_ID=".$staff." AND (ga.DUE_DATE IS NULL OR ga.DUE_DATE>=CURRENT_DATE-60) ORDER BY ga.DUE_DATE DESC NULLS LAST,ga.ASSIGNMENT_ID DESC LIMIT 60"):[];
$correct=isset($_GET['corriger'])&&ctype_digit((string)$_GET['corriger'])&&$allowed?$mine((int)$_GET['corriger']):null;
$bank=$allowed?(array)DBGet("SELECT ID,SUBJECT,LEVEL,TITLE,CONTENT FROM schoolsafe_exercise_bank WHERE SCHOOL_ID=".$school." ORDER BY SUBJECT,LEVEL,TITLE LIMIT 300"):[];
$models=SchoolSafeHomeworkModels();
$preset='';$presetTitle='';
if(isset($_GET['modele'])&&is_string($_GET['modele'])&&isset($models[$_GET['modele']]))$preset=$models[$_GET['modele']][1];
if(isset($_GET['banque'])&&ctype_digit((string)$_GET['banque']))foreach($bank as $b)if((int)$b['ID']===(int)$_GET['banque']){$preset=$b['CONTENT'];$presetTitle=$b['TITLE'];}
while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(403);exit('Page réservée aux enseignants.');}
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$in7=(new DateTimeImmutable($today))->modify('+7 day')->format('Y-m-d');
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Devoirs et travaux | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<style>.ss-hw{display:grid;gap:16px;max-width:1000px}.ss-box{border:1px solid #c9d8d2;border-radius:12px;padding:14px;display:grid;gap:10px;background:#fff}
.ss-box h2{margin:0;font-size:19px;color:#08766b}.ss-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}.ss-hw label{display:grid;gap:4px;font-size:14px}
.ss-hw input[type=text],.ss-hw input[type=date],.ss-hw select,.ss-hw textarea{min-height:38px;font-size:15px;box-sizing:border-box;width:100%}.ss-hw textarea{min-height:180px}
.ss-btn{min-height:44px;background:#08766b;color:#fff;border:0;border-radius:8px;font-size:15px;padding:0 16px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center}
.ss-btn.light{background:#eef7f4;color:#08766b;border:1px solid #08766b}.ss-chips{display:flex;gap:6px;flex-wrap:wrap}.ss-chips a,.ss-chips button{border:1px solid #c9d8d2;border-radius:16px;padding:4px 10px;background:#f4fbf8;color:#08766b;text-decoration:none;font-size:13px;cursor:pointer}
.ss-table{width:100%;border-collapse:collapse;font-size:14px}.ss-table th,.ss-table td{border-bottom:1px solid #e3ebe8;padding:6px;text-align:left;vertical-align:middle}.ss-table input{min-height:34px;font-size:15px}
.ss-ok{color:#08766b;font-weight:600}.ss-ko{color:#a32d2d;font-weight:600}.ss-sub{color:#556777;font-size:13px}
@media(max-width:640px){.ss-table .hide-sm{display:none}}</style></head>
<body><header><strong>SchoolSafe</strong><a href="ma-journee.php">Ma journée de classe</a></header>
<main class="ss-hw"><h1>Devoirs et travaux</h1>
<?php if($message):?><p class="ss-ok" role="status"><?=$e($message)?><?php if($created):?> <a href="schoolsafe-homework.php?assignment_id=<?=$created?>" target="_blank" rel="noopener">Voir la fiche A4</a> · <a href="schoolsafe-homework.php?assignment_id=<?=$created?>&amp;classe=1&amp;download=1">Imprimer pour toute la classe</a><?php endif;?></p><?php endif;?>
<?php if($error):?><p class="ss-ko" role="alert"><?=$e($error)?></p><?php endif;?>
<?php if($correct): $list2=$pupils($correct); ?>
<section class="ss-box" id="correction"><h2>Corriger : <?=$e(($correct['TYPE_TITLE']?$correct['TYPE_TITLE'].' — ':'').$correct['TITLE'])?></h2>
<p class="ss-sub">Barème : <?=$e(rtrim(rtrim((string)$correct['POINTS'],'0'),'.'))?> points · laissez vide si pas encore noté, * pour une dispense. Les notes vont dans le carnet natif ; le rattrapage réagit tout seul.</p>
<?php if(!$canGrade):?><p class="ss-ko">Permission requise : saisir les notes.</p><?php elseif(!$list2):?><p>Aucun élève dans cette classe.</p><?php else:?>
<form method="post"><input type="hidden" name="csrf" value="<?=$e($csrf)?>"><input type="hidden" name="action" value="noter"><input type="hidden" name="assignment" value="<?=(int)$correct['ASSIGNMENT_ID']?>">
<table class="ss-table"><thead><tr><th>Élève</th><th class="hide-sm">Rendu</th><th>Note</th><th>Appréciation</th><th>Copie corrigée</th><th class="hide-sm">Vu</th></tr></thead><tbody>
<?php foreach($list2 as $p): $sid=(int)$p['STUDENT_ID']; $pts=$p['POINTS']===null||$p['POINTS']===''?'':((float)$p['POINTS']<0?'*':rtrim(rtrim((string)$p['POINTS'],'0'),'.')); ?>
<tr><td><b><?=$e($p['FIRST_NAME'].' '.$p['LAST_NAME'])?></b></td><td class="hide-sm"><?=$p['HANDED_IN']?'✅ rendu en ligne':'—'?></td>
<td><input type="text" inputmode="decimal" name="n_<?=$sid?>" value="<?=$e($pts)?>" style="width:70px" aria-label="Note"> / <?=$e(rtrim(rtrim((string)$correct['POINTS'],'0'),'.'))?></td>
<td><input type="text" name="c_<?=$sid?>" value="<?=$e($p['COMMENT']??'')?>" maxlength="200" list="ss-comments" aria-label="Appréciation"></td>
<td><div class="ss-chips"><label style="cursor:pointer;border:1px solid #c9d8d2;border-radius:16px;padding:4px 10px;background:#f4fbf8;color:#08766b;font-size:13px">📷 <?=$p['HAS_COPY']?'Remplacer':'Photo'?><input type="file" name="copie" form="ss-copy-<?=$sid?>" accept="image/*,application/pdf" data-copy style="display:none"></label>
<?php if($p['HAS_COPY']):?><a href="note-devoir.php?devoir=<?=(int)$correct['ASSIGNMENT_ID']?>&amp;eleve=<?=$sid?>&amp;copie=1" target="_blank" rel="noopener">Voir</a><?php endif;?><a href="note-devoir.php?devoir=<?=(int)$correct['ASSIGNMENT_ID']?>&amp;eleve=<?=$sid?>" target="_blank" rel="noopener" title="Ce que voit le parent">Parent</a></div></td>
<td class="hide-sm"><?=(int)$p['SEEN']?'✓ vu':'—'?></td></tr>
<?php endforeach;?></tbody></table>
<datalist id="ss-comments"><?php foreach(SchoolSafeHomeworkComments() as $c):?><option value="<?=$e($c)?>"><?php endforeach;?></datalist>
<label>Corrigé type (montré aux parents après la date de remise)<textarea name="answer" maxlength="20000" style="min-height:110px" placeholder="ex. Exercice 1 : 3/4 + 1/4 = 1. Exercice 2 : …"><?=$e((string)(DBGetOne("SELECT ANSWER FROM schoolsafe_assignment_keys WHERE ASSIGNMENT_ID=".(int)$correct['ASSIGNMENT_ID'])?:''))?></textarea></label>
<p class="ss-sub">Chaque parent reçoit la note de son enfant avec l’appréciation, le corrigé et la photo de la copie corrigée (quelques minutes après l’enregistrement, ou le soir selon le choix de l’école).</p>
<div><button class="ss-btn" type="submit">Enregistrer les notes</button> <a class="ss-btn light" href="devoirs-travaux.php">Fermer</a></div></form>
<?php foreach($list2 as $p):?><form id="ss-copy-<?=(int)$p['STUDENT_ID']?>" method="post" enctype="multipart/form-data" hidden><input type="hidden" name="csrf" value="<?=$e($csrf)?>"><input type="hidden" name="action" value="copie"><input type="hidden" name="assignment" value="<?=(int)$correct['ASSIGNMENT_ID']?>"><input type="hidden" name="student" value="<?=(int)$p['STUDENT_ID']?>"></form><?php endforeach;?>
<?php endif;?>
</section>
<?php endif;?>
<section class="ss-box" id="nouveau"><h2>Nouveau devoir</h2>
<?php if(!$classes):?><p>Aucune classe ne vous est attribuée cette année.</p><?php elseif(!$typeRows):?><p class="ss-ko">Aucun type de devoir pour vos cours : demandez à la direction de les créer (Préparer l’année, étape 8).</p><?php else:?>
<div class="ss-chips"><span class="ss-sub">Partir d’un modèle :</span><?php foreach($models as $k=>$m):?><a href="devoirs-travaux.php?modele=<?=$e($k)?>#nouveau"><?=$e($m[0])?></a><?php endforeach;?></div>
<?php if($bank):?><div class="ss-chips"><span class="ss-sub">Ou de la banque de l’école :</span><?php foreach(array_slice($bank,0,40) as $b):?><a href="devoirs-travaux.php?banque=<?=(int)$b['ID']?>#nouveau" title="<?=$e($b['SUBJECT'].' · '.$b['LEVEL'])?>"><?=$e($b['TITLE'])?></a><?php endforeach;?></div><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=$e($csrf)?>"><input type="hidden" name="action" value="creer">
<div class="ss-grid">
<label>Classe<select name="cp" required id="ss-cp"><?php foreach($classes as $id=>$c):?><option value="<?=$id?>" data-course="<?=(int)$c['COURSE_ID']?>"><?=$e(ParseMLField((string)$c['COURSE_TITLE']))?></option><?php endforeach;?></select></label>
<label>Type<select name="type" required id="ss-type"><?php foreach($typeRows as $t):?><option value="<?=(int)$t['ASSIGNMENT_TYPE_ID']?>" data-course="<?=(int)$t['COURSE_ID']?>"><?=$e($t['TITLE'])?></option><?php endforeach;?></select></label>
<label>Barème (points)<input type="text" name="points" inputmode="decimal" value="20" required></label>
<label>Donné le<input type="date" name="assigned" value="<?=$e($today)?>" required></label>
<label>À rendre le<input type="date" name="due" value="<?=$e($in7)?>" required></label>
</div>
<label>Titre<input type="text" name="title" maxlength="100" required value="<?=$e($presetTitle)?>" placeholder="ex. Les fractions"></label>
<label>Consignes et exercices<textarea name="text" maxlength="20000" required placeholder="Écrivez les consignes. Une ligne vide sépare les paragraphes ; « - » au début d’une ligne fait une liste."><?=$e($preset)?></textarea></label>
<label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="submission" value="1" checked> Rendu en ligne (photo ou fichier depuis le téléphone)</label>
<label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="to_bank" value="1"> Ranger aussi ces consignes dans la banque de l’école</label>
<p class="ss-sub">À la date du devoir, chaque parent le reçoit automatiquement avec la fiche au nom de son enfant.</p>
<div><button class="ss-btn" type="submit">Créer le devoir</button></div></form><?php endif;?>
</section>
<section class="ss-box"><h2>Mes devoirs</h2>
<?php if(!$list):?><p>Aucun devoir récent.</p><?php else:?>
<table class="ss-table"><thead><tr><th>Devoir</th><th class="hide-sm">Cours</th><th>À rendre</th><th class="hide-sm">Rendus</th><th>Notés</th><th></th></tr></thead><tbody>
<?php foreach($list as $a):?><tr><td><b><?=$e(($a['TYPE_TITLE']?$a['TYPE_TITLE'].' — ':'').$a['TITLE'])?></b><br><span class="ss-sub">donné le <?=$e(SchoolSafeLetterheadDate($a['ASSIGNED_DATE']??'',false))?></span></td>
<td class="hide-sm"><?=$e(ParseMLField((string)$a['COURSE_TITLE']))?></td><td><?=$e(SchoolSafeLetterheadDate($a['DUE_DATE']??'',false))?></td>
<td class="hide-sm"><?=(int)$a['HANDED']?></td><td><?=(int)$a['GRADED']?></td>
<td><div class="ss-chips"><a href="schoolsafe-homework.php?assignment_id=<?=(int)$a['ASSIGNMENT_ID']?>" target="_blank" rel="noopener">Fiche A4</a><a href="schoolsafe-homework.php?assignment_id=<?=(int)$a['ASSIGNMENT_ID']?>&amp;classe=1&amp;download=1">Toute la classe</a><a href="devoirs-travaux.php?corriger=<?=(int)$a['ASSIGNMENT_ID']?>#correction">Corriger</a></div></td></tr><?php endforeach;?>
</tbody></table><?php endif;?>
</section></main>
<script>
(function(){var cp=document.getElementById('ss-cp'),ty=document.getElementById('ss-type');if(!cp||!ty)return;
function sync(){var c=cp.options[cp.selectedIndex].dataset.course,first=null;Array.prototype.forEach.call(ty.options,function(o){var ok=o.dataset.course===c;o.hidden=!ok;o.disabled=!ok;if(ok&&!first)first=o;});if(ty.selectedOptions[0]&&ty.selectedOptions[0].disabled&&first)first.selected=true;}
cp.addEventListener('change',sync);sync();})();
// Copie corrigee : photo allegee dans le navigateur puis envoyee aussitot.
(function(){async function shrink(file){if(!/^image\/(jpeg|png)/.test(file.type)||!window.createImageBitmap)return file;
 try{var bmp=await createImageBitmap(file,{imageOrientation:'from-image'});var s=Math.min(1,2200/Math.max(bmp.width,bmp.height));var c=document.createElement('canvas');c.width=Math.round(bmp.width*s);c.height=Math.round(bmp.height*s);
 var x=c.getContext('2d');x.fillStyle='#fff';x.fillRect(0,0,c.width,c.height);x.drawImage(bmp,0,0,c.width,c.height);
 var blob=await new Promise(function(r){c.toBlob(r,'image/jpeg',0.85);});return blob?new File([blob],'copie.jpg',{type:'image/jpeg'}):file;}catch(e){return file;}}
document.querySelectorAll('[data-copy]').forEach(function(input){input.addEventListener('change',async function(){var f=input.files[0];if(!f)return;input.parentNode.firstChild.textContent='⏳ ';
 var ready=await shrink(f);try{var dt=new DataTransfer();dt.items.add(ready);input.files=dt.files;}catch(e){}document.getElementById(input.getAttribute('form')).submit();});});})();
</script></body></html>
