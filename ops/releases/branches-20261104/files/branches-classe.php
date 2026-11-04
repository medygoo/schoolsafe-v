<?php
// « Branches et maxima » (04/11, etape B validee) : la direction regle la liste des branches de chaque niveau
// (domaine, branche, maxima de periode et d'examen), puis l'applique a chaque classe avec le titulaire et,
// si la classe en a un, le professeur d'anglais. Aperçu avant toute creation ; rien n'est jamais efface.
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','POST'],true)){http_response_code(405);exit;}
if(array_diff(array_keys($_GET),['niveau','liste'])){http_response_code(400);exit('Requête invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeBranches.fnc.php';
$school=(int)UserSchool();$year=(int)UserSyear();$staff=(int)User('STAFF_ID');
$allowed=$staff>0&&User('PROFILE')==='admin'&&$school>0&&$year>0&&AllowEdit('School_Setup/Configuration.php');
if(empty($_SESSION['SchoolSafeScannerCsrf']))$_SESSION['SchoolSafeScannerCsrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['SchoolSafeScannerCsrf'];
$get=function($sql){return DBGet($sql);};
$db=['get'=>$get,'exec'=>function($sql){DBQuery($sql);}];
$levels=[];
if($allowed)foreach((array)DBGet("SELECT ID,TITLE FROM school_gradelevels WHERE SCHOOL_ID=".$school." ORDER BY SORT_ORDER") as $r)$levels[(int)$r['ID']]=(string)$r['TITLE'];
$lvl=isset($_GET['niveau'])&&ctype_digit((string)$_GET['niveau'])&&isset($levels[(int)$_GET['niveau']])?(int)$_GET['niveau']:(int)(array_key_first($levels)??0);
$message='';$error='';$preview=null;$inTx=false;
if($allowed&&$_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf']))throw new RuntimeException('Session expirée : rechargez la page.');
        $action=is_string($_POST['action']??null)?$_POST['action']:'';
        $postLevel=isset($_POST['niveau'])&&is_string($_POST['niveau'])&&ctype_digit($_POST['niveau'])&&isset($levels[(int)$_POST['niveau']])?(int)$_POST['niveau']:0;
        if($action==='liste'){
            if(!$postLevel)throw new RuntimeException('Choisissez un niveau.');
            $rows=SchoolSafeBranchValidateRows($_POST);
            DBQuery('BEGIN');$inTx=true;
            SchoolSafeBranchSave($school,$postLevel,$rows,$staff,$db);
            DBQuery('COMMIT');$inTx=false;
            $lvl=$postLevel;
            $message='Liste enregistrée pour '.$levels[$postLevel].' : '.count($rows).' branche(s). Les classes déjà équipées reçoivent les nouvelles branches quand vous les appliquez de nouveau.';
        }elseif($action==='apercu'||$action==='appliquer'){
            $classCp=(int)($_POST['classe']??0);
            if(!$postLevel)throw new RuntimeException('Choisissez la liste (niveau) à appliquer.');
            $fr=(int)($_POST['titulaire']??0);$en=(int)($_POST['anglais']??0);
            $existing=[];foreach((array)DBGet("SELECT MODEL_ID FROM schoolsafe_class_branches WHERE CLASS_COURSE_PERIOD_ID=".$classCp) as $r)$existing[(int)$r['MODEL_ID']]=true;
            $plan=SchoolSafeBranchPlan(SchoolSafeBranchModels($school,$postLevel,$get,true),$existing);
            if($action==='apercu'){$preview=['classe'=>$classCp,'niveau'=>$postLevel,'fr'=>$fr,'en'=>$en,'plan'=>$plan];$lvl=$postLevel;}
            else{
                DBQuery('BEGIN');$inTx=true;
                $out=SchoolSafeBranchApply($school,$year,$classCp,$postLevel,$fr,$en,$staff,$db);
                DBQuery('COMMIT');$inTx=false;
                $message='Branches appliquées : '.$out['created'].' créée(s), '.$out['kept'].' déjà en place, '.$out['enrolled'].' inscription(s) d’élèves. Les enseignants les trouvent dans « Devoirs et travaux ».';
            }
        }else throw new RuntimeException('Action inconnue.');
    }catch(Throwable $failure){
        if($inTx)DBQuery('ROLLBACK');
        $error=$failure instanceof RuntimeException?$failure->getMessage():'Enregistrement impossible.';
    }
}
$models=$allowed&&$lvl?SchoolSafeBranchModels($school,$lvl,$get):[];
$proposed=isset($_GET['liste'])&&in_array($_GET['liste'],['primaire','humanites'],true)?$_GET['liste']:'';
if($proposed)$models=array_map(static function($d,$i){return ['ID'=>'','DOMAIN'=>$d[0],'TITLE'=>$d[1],'SHORT_NAME'=>$d[2],'MAX_PERIOD'=>$d[3],'MAX_EXAM'=>'','ACTIVE'=>'t','SORT_ORDER'=>$i];},SchoolSafeBranchDefaults($proposed),array_keys(SchoolSafeBranchDefaults($proposed)));
$classes=$allowed?SchoolSafeBranchClasses($school,$year,$get):[];
$teachers=$allowed?SchoolSafeBranchTeachers($school,$year,$get):[];
while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(403);exit('Permission requise : administrateur autorisé à configurer l’école.');}
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$num=fn($v)=>$v===null||$v===''?'':str_replace('.',',',rtrim(rtrim((string)$v,'0'),'.'));
$isOn=fn($v)=>in_array((string)$v,['t','1','true','Y'],true);
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Branches et maxima | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<style>.br{display:grid;gap:16px;max-width:1100px}.br-box{border:1px solid #c9d8d2;border-radius:12px;padding:14px;display:grid;gap:10px;background:#fff}.br-box h2{margin:0;font-size:19px;color:#08766b}
.br-chips{display:flex;gap:6px;flex-wrap:wrap}.br-chips a{border:1px solid #c9d8d2;border-radius:16px;padding:5px 11px;background:#f4fbf8;color:#08766b;text-decoration:none;font-size:14px}.br-chips a.on{background:#08766b;color:#fff}
.br-table{width:100%;border-collapse:collapse;font-size:14px}.br-table th,.br-table td{border-bottom:1px solid #e3ebe8;padding:4px;text-align:left;vertical-align:middle}.br-table input[type=text]{min-height:34px;font-size:14px;width:100%;box-sizing:border-box}
.br-btn{min-height:44px;background:#08766b;color:#fff;border:0;border-radius:8px;font-size:15px;padding:0 16px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center}.br-btn.light{background:#eef7f4;color:#08766b;border:1px solid #08766b}
.br-ok{color:#08766b;font-weight:600}.br-ko{color:#a32d2d;font-weight:600}.br-sub{color:#556777;font-size:13px}.br-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;align-items:end}.br-grid label{display:grid;gap:4px;font-size:14px}.br-grid select{min-height:38px}
.br-prev{background:#f4fbf8;border:1px solid #08766b}</style></head>
<body><header><strong>SchoolSafe</strong><a href="preparer-annee.php">Préparer l’année</a></header>
<main class="br"><h1>Branches et maxima du bulletin</h1>
<p class="br-sub">Chaque branche devient un cours de la classe : le titulaire y donne ses notes en français ; le professeur d’anglais, s’il y en a un, y donne les siennes. Le titulaire fera la synthèse du bulletin. Rien n’est jamais effacé.</p>
<?php if($message):?><p class="br-ok" role="status"><?=$e($message)?></p><?php endif;?><?php if($error):?><p class="br-ko" role="alert"><?=$e($error)?></p><?php endif;?>
<section class="br-box"><h2>1. Liste des branches par niveau</h2>
<div class="br-chips"><?php foreach($levels as $id=>$t):?><a class="<?=$id===$lvl?'on':''?>" href="branches-classe.php?niveau=<?=$id?>"><?=$e($t)?></a><?php endforeach;?></div>
<div class="br-chips"><span class="br-sub">Partir d’une liste proposée (à vérifier avec un ancien bulletin de l’école) :</span><a href="branches-classe.php?niveau=<?=$lvl?>&amp;liste=primaire">Primaire</a><a href="branches-classe.php?niveau=<?=$lvl?>&amp;liste=humanites">Humanités</a></div>
<?php if($proposed):?><p class="br-ok">Liste proposée affichée : corrigez-la puis enregistrez. Rien n’est enregistré tant que vous n’avez pas cliqué sur « Enregistrer la liste ».</p><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=$e($csrf)?>"><input type="hidden" name="action" value="liste"><input type="hidden" name="niveau" value="<?=$lvl?>">
<table class="br-table"><thead><tr><th>Domaine</th><th>Branche</th><th>Abrégé</th><th>Maxima période</th><th>Maxima examen<br><span class="br-sub">vide = double</span></th><th>Désactiver</th></tr></thead><tbody id="br-rows">
<?php $rows=array_values($models);for($i=0;$i<count($rows)+3;$i++):$m=$rows[$i]??null;?>
<tr><td><input type="hidden" name="b_id[]" value="<?=$e($m['ID']??'')?>"><input type="text" name="b_domain[]" value="<?=$e($m['DOMAIN']??'')?>" maxlength="80" list="br-domains"></td>
<td><input type="text" name="b_title[]" value="<?=$e($m['TITLE']??'')?>" maxlength="80"></td><td><input type="text" name="b_short[]" value="<?=$e($m['SHORT_NAME']??'')?>" maxlength="20" style="width:80px"></td>
<td><input type="text" name="b_max[]" value="<?=$e($num($m['MAX_PERIOD']??''))?>" inputmode="decimal" style="width:70px"></td><td><input type="text" name="b_exam[]" value="<?=$e($num($m['MAX_EXAM']??''))?>" inputmode="decimal" style="width:70px" placeholder="<?=$m?$e($num(SchoolSafeBranchExamMax($m['MAX_PERIOD'],null))):''?>"></td>
<td><input type="checkbox" name="b_off[]" value="<?=$i?>"<?=$m&&!$isOn($m['ACTIVE'])?' checked':''?> aria-label="Désactiver"></td></tr>
<?php endfor;?></tbody></table>
<datalist id="br-domains"><?php foreach(['Langues','Mathématiques','Sciences et technologie','Univers social','Arts','Développement personnel','Sciences','Sciences humaines','Formation générale'] as $d):?><option value="<?=$e($d)?>"><?php endforeach;?></datalist>
<div style="display:flex;gap:8px;flex-wrap:wrap"><button class="br-btn" type="submit">Enregistrer la liste de <?=$e($levels[$lvl]??'')?></button><button class="br-btn light" type="button" id="br-add">+ Ajouter une ligne</button></div>
<p class="br-sub">Laissez la branche vide pour ignorer une ligne. Une branche décochée puis désactivée reste dans les bulletins déjà faits.</p></form></section>
<section class="br-box"><h2>2. Appliquer aux classes</h2>
<?php if(!$classes):?><p>Aucune classe cette année.</p><?php endif;?>
<?php foreach($classes as $c): $cid=(int)$c['COURSE_PERIOD_ID']; $clsLevel=(int)($c['GRADE_LEVEL']?:$lvl); $isPrev=$preview&&$preview['classe']===$cid; ?>
<form method="post" class="br-box<?=$isPrev?' br-prev':''?>"><input type="hidden" name="csrf" value="<?=$e($csrf)?>"><input type="hidden" name="classe" value="<?=$cid?>">
<div><b><?=$e(ParseMLField((string)$c['COURSE_TITLE']))?></b> <span class="br-sub">· <?=(int)$c['STUDENTS']?> élève(s) · <?=(int)$c['BRANCHES']?> branche(s) en place</span></div>
<div class="br-grid"><label>Liste (niveau)<select name="niveau"><?php foreach($levels as $id=>$t):?><option value="<?=$id?>"<?=($isPrev?$preview['niveau']:$clsLevel)===$id?' selected':''?>><?=$e($t)?></option><?php endforeach;?></select></label>
<label>Titulaire (notes en français)<select name="titulaire"><option value="0">— choisir —</option><?php foreach($teachers as $id=>$n):?><option value="<?=$id?>"<?=($isPrev?$preview['fr']:(int)$c['TEACHER_ID'])===$id?' selected':''?>><?=$e($n)?></option><?php endforeach;?></select></label>
<label>Professeur d’anglais (facultatif)<select name="anglais"><option value="0">— aucun —</option><?php foreach($teachers as $id=>$n):?><option value="<?=$id?>"<?=($isPrev?$preview['en']:(int)($c['TEACHER_EN']?:0))===$id?' selected':''?>><?=$e($n)?></option><?php endforeach;?></select></label>
<div style="display:flex;gap:8px"><button class="br-btn light" name="action" value="apercu" type="submit">Aperçu</button><?php if($isPrev):?><button class="br-btn" name="action" value="appliquer" type="submit">Créer et appliquer</button><?php endif;?></div></div>
<?php if($isPrev): $cr=$preview['plan']['create']; $kp=$preview['plan']['keep']; ?>
<p><b><?=count($cr)?></b> branche(s) à créer<?=$cr?' : '.$e(implode(', ',array_map(static function($m){return $m['TITLE'];},$cr))):''?>. <b><?=count($kp)?></b> déjà en place. Les <?=(int)$c['STUDENTS']?> élève(s) de la classe y seront inscrits ; les enseignants choisis seront mis à jour sur toutes les branches.</p>
<?php if(!$cr&&!$kp):?><p class="br-ko">Aucune branche active pour cette liste : enregistrez d’abord la liste du niveau.</p><?php endif;?>
<?php endif;?></form>
<?php endforeach;?></section></main>
<script>(function(){var b=document.getElementById('br-add'),t=document.getElementById('br-rows');if(!b||!t)return;b.addEventListener('click',function(){var r=t.rows[t.rows.length-1].cloneNode(true);
r.querySelectorAll('input[type=text],input[type=hidden]').forEach(function(i){i.value='';});var c=r.querySelector('input[type=checkbox]');c.checked=false;c.value=String(t.rows.length);t.appendChild(r);});})();</script>
</body></html>
