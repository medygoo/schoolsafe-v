<?php
// « Notes du bulletin » (04/11, etape C validee) : l'enseignant donne, pour chaque branche qu'il enseigne,
// la note de periode ou d'examen de chaque eleve, dans sa langue (francais ou anglais). Les moyennes de ses
// devoirs sont proposees ; il garde la main. Un trimestre valide par la direction est verrouille.
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','POST'],true)){http_response_code(405);exit;}
if(array_diff(array_keys($_GET),['classe','colonne'])){http_response_code(400);exit('Requête invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeBulletin.fnc.php';
$school=(int)UserSchool();$year=(int)UserSyear();$staff=(int)User('STAFF_ID');
$allowed=$staff>0&&User('PROFILE')==='teacher'&&$school>0&&$year>0;
$get=function($sql){return DBGet($sql);};
if(empty($_SESSION['SchoolSafeScannerCsrf']))$_SESSION['SchoolSafeScannerCsrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['SchoolSafeScannerCsrf'];
$classes=[];if($allowed)foreach(SchoolSafeBulletinTeacherClasses($staff,$school,$year,$get) as $c)$classes[(int)$c['CLASS_COURSE_PERIOD_ID']]=$c;
$classCp=isset($_GET['classe'])&&ctype_digit((string)$_GET['classe'])&&isset($classes[(int)$_GET['classe']])?(int)$_GET['classe']:(int)(array_key_first($classes)??0);
$terms=$allowed?SchoolSafeBulletinTerms($school,$year,$get):[];
$slots=array_filter(SchoolSafeBulletinSlots($terms),static function($s){return in_array($s['kind'],['periode','examen'],true);});
$slotKey=is_string($_GET['colonne']??null)&&isset($slots[$_GET['colonne']])?$_GET['colonne']:'';
if($slotKey===''){ // colonne par defaut : la periode en cours
    $cur=(int)(DBGetOne("SELECT MARKING_PERIOD_ID FROM school_marking_periods WHERE SCHOOL_ID=".$school." AND SYEAR=".$year." AND MP='QTR' AND CURRENT_DATE BETWEEN START_DATE AND END_DATE LIMIT 1")?:0);
    $slotKey=isset($slots['P:'.$cur])?'P:'.$cur:(string)(array_key_first($slots)??'');
}
$slot=$slots[$slotKey]??null;
$branches=[];$students=[];$lang='fr';$locked=[];$current=[];$suggest=[];
if($classCp&&$slot){
    $lang=$classes[$classCp]['LANG'];
    foreach(SchoolSafeBulletinBranches($classCp,$get) as $b)if((int)$b[$lang==='fr'?'TEACHER_FR':'TEACHER_EN']===$staff)$branches[(int)$b['COURSE_PERIOD_ID']]=$b;
    $students=SchoolSafeBulletinStudents($classCp,$get);
    foreach(SchoolSafeBulletinStatus($classCp,$get) as $sid=>$byMp)if(($byMp[$slot['term']]['STATUS']??'')==='valide')$locked[$sid]=true;
}
$message='';$error='';$inTx=false;
if($allowed&&$slot&&$branches&&$_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf']))throw new RuntimeException('Session expirée : rechargez la page.');
        $in=is_array($_POST['m']??null)?$_POST['m']:[];
        $values=[];
        foreach($branches as $bcp=>$b){
            $max=SchoolSafeBulletinMax($b,$slot['kind']);
            foreach($students as $sid=>$n){
                if(isset($locked[$sid]))continue;
                $raw=$in[$bcp][$sid]??null;
                if($raw===null||!is_string($raw))continue;
                $values[]=[$bcp,$sid,SchoolSafeBulletinParse($raw,$max)];
            }
        }
        DBQuery('BEGIN');$inTx=true;$count=0;
        foreach($values as [$bcp,$sid,$v]){
            $exists=DBGet("SELECT POINTS FROM schoolsafe_bulletin_marks WHERE BRANCH_COURSE_PERIOD_ID=".$bcp." AND STUDENT_ID=".$sid." AND MARKING_PERIOD_ID=".$slot['mp']." AND KIND='".$slot['kind']."' AND LANG='".$lang."'");
            if(!$exists&&$v===null)continue;
            DBQuery("INSERT INTO schoolsafe_bulletin_marks(SCHOOL_ID,SYEAR,CLASS_COURSE_PERIOD_ID,BRANCH_COURSE_PERIOD_ID,STUDENT_ID,MARKING_PERIOD_ID,KIND,LANG,POINTS,ENTERED_BY) VALUES("
                .$school.",".$year.",".$classCp.",".$bcp.",".$sid.",".$slot['mp'].",'".$slot['kind']."','".$lang."',".($v===null?'NULL':$v).",".$staff.")"
                ." ON CONFLICT(BRANCH_COURSE_PERIOD_ID,STUDENT_ID,MARKING_PERIOD_ID,KIND,LANG) DO UPDATE SET POINTS=EXCLUDED.POINTS,ENTERED_BY=EXCLUDED.ENTERED_BY,UPDATED_AT=now()"
                ." WHERE schoolsafe_bulletin_marks.POINTS IS DISTINCT FROM EXCLUDED.POINTS");
            if($v!==null)$count++;
        }
        DBQuery('COMMIT');$inTx=false;
        $message='Notes enregistrées ('.$count.'). Le titulaire les voit dans la synthèse ; chaque changement est gardé dans le journal des notes.';
    }catch(Throwable $failure){
        if($inTx)DBQuery('ROLLBACK');
        $error=$failure instanceof RuntimeException?$failure->getMessage():'Enregistrement impossible.';
    }
}
if($slot&&$branches){
    foreach((array)DBGet("SELECT BRANCH_COURSE_PERIOD_ID,STUDENT_ID,POINTS FROM schoolsafe_bulletin_marks WHERE CLASS_COURSE_PERIOD_ID=".$classCp." AND MARKING_PERIOD_ID=".$slot['mp']." AND KIND='".$slot['kind']."' AND LANG='".$lang."'") as $r)
        $current[(int)$r['BRANCH_COURSE_PERIOD_ID']][(int)$r['STUDENT_ID']]=$r['POINTS'];
    foreach($branches as $bcp=>$b)$suggest[$bcp]=SchoolSafeBulletinSuggest($bcp,$staff,$slot['kind'],$slot['mp'],SchoolSafeBulletinMax($b,$slot['kind']),$get);
}
while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(403);exit('Page réservée aux enseignants.');}
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$n=fn($v)=>SchoolSafeBulletinNumber($v);
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Notes du bulletin | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<style>.nb{display:grid;gap:14px;max-width:1200px}.nb-box{border:1px solid #c9d8d2;border-radius:12px;padding:14px;display:grid;gap:10px;background:#fff}.nb-box h2{margin:0;font-size:19px;color:#08766b}
.nb-chips{display:flex;gap:6px;flex-wrap:wrap}.nb-chips a{border:1px solid #c9d8d2;border-radius:16px;padding:5px 11px;background:#f4fbf8;color:#08766b;text-decoration:none;font-size:14px}.nb-chips a.on{background:#08766b;color:#fff}
.nb-wrap{overflow-x:auto}.nb-table{border-collapse:collapse;font-size:14px}.nb-table th,.nb-table td{border:1px solid #e3ebe8;padding:4px;text-align:center}.nb-table th{background:#f4fbf8;font-size:12px;max-width:90px}.nb-table td:first-child,.nb-table th:first-child{text-align:left;position:sticky;left:0;background:#fff;min-width:150px}
.nb-table input{width:58px;min-height:32px;font-size:15px;text-align:center}.nb-btn{min-height:44px;background:#08766b;color:#fff;border:0;border-radius:8px;font-size:15px;padding:0 16px;cursor:pointer}.nb-btn.light{background:#eef7f4;color:#08766b;border:1px solid #08766b}
.nb-ok{color:#08766b;font-weight:600}.nb-ko{color:#a32d2d;font-weight:600}.nb-sub{color:#556777;font-size:13px}</style><?php require_once __DIR__.'/ProgramFunctions/SchoolSafePedagogyNav.fnc.php'; echo SchoolSafePedagogyStyle(); ?></head>
<body><?php echo SchoolSafePedagogyHeader('notes-bulletin.php',0); ?>
<main class="nb"><h1>Notes du bulletin</h1>
<?php if($message):?><p class="nb-ok" role="status"><?=$e($message)?></p><?php endif;?><?php if($error):?><p class="nb-ko" role="alert"><?=$e($error)?></p><?php endif;?>
<?php if(!$classes):?><p>Aucune branche ne vous est confiée. La direction attribue les branches dans « Branches et maxima ».</p><?php else:?>
<div class="nb-chips"><?php foreach($classes as $id=>$c):?><a class="<?=$id===$classCp?'on':''?>" href="notes-bulletin.php?<?=$e(http_build_query(['classe'=>$id,'colonne'=>$slotKey]))?>"><?=$e(ParseMLField((string)$c['CLASS_TITLE']))?> (<?=$c['LANG']==='fr'?'français':'anglais'?>)</a><?php endforeach;?></div>
<div class="nb-chips"><?php foreach($terms as $t):?><span class="nb-sub"><?=$e($t['title'])?> :</span><?php foreach($t['periods'] as $pid=>$pt):?><a class="<?=$slotKey==='P:'.$pid?'on':''?>" href="notes-bulletin.php?<?=$e(http_build_query(['classe'=>$classCp,'colonne'=>'P:'.$pid]))?>"><?=$e($pt)?></a><?php endforeach;?><a class="<?=$slotKey==='E:'.$t['id']?'on':''?>" href="notes-bulletin.php?<?=$e(http_build_query(['classe'=>$classCp,'colonne'=>'E:'.$t['id']]))?>">Examen</a><?php endforeach;?></div>
<?php if($slot):?><section class="nb-box"><h2><?=$e(($slot['kind']==='examen'?'Examen — '.$terms[$slot['term']]['title']:$slot['label']).' · notes en '.($lang==='fr'?'français':'anglais'))?></h2>
<?php if(!$branches||!$students):?><p>Aucune branche ou aucun élève pour cette classe.</p><?php else:?>
<p class="nb-sub">Gris : moyenne de vos devoirs ramenée au maxima (suggestion). Laissez vide si la note n’est pas encore donnée. 🔒 : trimestre validé par la direction.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=$e($csrf)?>">
<div class="nb-wrap"><table class="nb-table"><thead><tr><th>Élève</th><?php foreach($branches as $bcp=>$b):?><th title="<?=$e($b['TITLE'])?>"><?=$e($b['SHORT_NAME'])?><br>/<?=$e($n(SchoolSafeBulletinMax($b,$slot['kind'])))?></th><?php endforeach;?></tr></thead><tbody>
<?php foreach($students as $sid=>$name):?><tr><td><?=isset($locked[$sid])?'🔒 ':''?><?=$e($name)?></td>
<?php foreach($branches as $bcp=>$b): $s=$suggest[$bcp][$sid]??null; ?><td><input type="text" inputmode="decimal" name="m[<?=$bcp?>][<?=$sid?>]" value="<?=$e($n($current[$bcp][$sid]??''))?>"<?=$s!==null?' placeholder="'.$e($n($s)).'" data-suggest="'.$e($n($s)).'"':''?><?=isset($locked[$sid])?' disabled':''?> aria-label="<?=$e($b['TITLE'].' — '.$name)?>"></td><?php endforeach;?></tr><?php endforeach;?>
</tbody></table></div>
<div style="display:flex;gap:8px;flex-wrap:wrap"><button class="nb-btn" type="submit">Enregistrer</button><button class="nb-btn light" type="button" id="nb-fill">Reprendre les suggestions dans les cases vides</button></div></form>
<?php endif;?></section><?php endif;?>
<?php endif;?></main>
<script>(function(){var b=document.getElementById('nb-fill');if(!b)return;b.addEventListener('click',function(){document.querySelectorAll('input[data-suggest]').forEach(function(i){if(!i.disabled&&i.value==='')i.value=i.dataset.suggest;});});})();</script>
</body></html>
