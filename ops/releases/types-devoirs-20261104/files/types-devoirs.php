<?php
// Types de devoirs (04/11, valide par le proprietaire) : l'ecole coche ses types (Devoir, Interrogation,
// Examen...), modifie les noms, ajoute les siens, choisit les cours ; chaque type recoit sa sorte pour le rattrapage.
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','POST'],true)||$_GET){http_response_code(400);exit('Requête invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeYearSetup.fnc.php';
$school=(int)UserSchool();$year=(int)UserSyear();$mp=(int)UserMP();$actor=(int)User('STAFF_ID');
// Meme droit que l'ecran natif « Creer des devoirs en masse ».
$allowed=$actor&&User('PROFILE')==='admin'&&$school>0&&$year>0&&AllowEdit('Grades/MassCreateAssignments.php');
if(empty($_SESSION['SchoolSafeScannerCsrf']))$_SESSION['SchoolSafeScannerCsrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['SchoolSafeScannerCsrf'];
$get=function($sql){return DBGet(DBQuery($sql));};$exec=function($sql){DBQuery($sql);};
$kinds=SchoolSafeTypeKinds();
$rows=[];foreach(SchoolSafeTypeModels() as $m)$rows[]=['on'=>$m[2],'title'=>$m[0],'kind'=>$m[1],'percent'=>''];
$rows[]=['on'=>false,'title'=>'','kind'=>'homework','percent'=>''];$rows[]=['on'=>false,'title'=>'','kind'=>'homework','percent'=>''];
$courses=$allowed?SchoolSafeTypeCourses($school,$year,$get):[];
$chosen=array_keys($courses);$message='';$error='';
if($allowed&&$_SERVER['REQUEST_METHOD']==='POST'){
    $rows=[];foreach((array)($_POST['t']??[]) as $r)if(is_array($r))$rows[]=['on'=>!empty($r['on']),'title'=>(string)($r['title']??''),'kind'=>(string)($r['kind']??''),'percent'=>(string)($r['percent']??'')];
    $chosen=array_map('intval',(array)($_POST['courses']??[]));
    try{
        if(!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf']))throw new RuntimeException('Session expirée : rechargez la page.');
        if(empty($_POST['confirm']))throw new RuntimeException('Cochez la confirmation avant de créer les types.');
        $types=SchoolSafeTypeValidate($rows);
        $done=SchoolSafeTypeApply($school,$year,$mp,$actor,$types,$chosen,$get,$exec);
        $message=$done['created'].' type(s) créé(s) dans '.$done['courses'].' cours'.($done['kept']?', '.$done['kept'].' déjà présent(s) conservé(s)':'').'.';
    }catch(Throwable $failure){$error=$failure instanceof RuntimeException?$failure->getMessage():'Opération impossible.';}
}
$existing=$allowed?$get("SELECT DISTINCT TRIM(t.TITLE) AS TITLE,r.KIND FROM gradebook_assignment_types t LEFT JOIN schoolsafe_remediation_types r ON r.ASSIGNMENT_TYPE_ID=t.ASSIGNMENT_TYPE_ID WHERE t.COURSE_ID IN (SELECT COURSE_ID FROM course_periods WHERE SCHOOL_ID=$school AND SYEAR=$year) ORDER BY 1"):[];
while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(403);exit('Permission requise : administrateur autorisé à créer des devoirs en masse.');}
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Types de devoirs | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<style>.ss-types{display:grid;gap:14px;max-width:880px}.ss-types fieldset{border:1px solid #c9d8d2;border-radius:10px;padding:12px 14px;display:grid;gap:8px}
.ss-types legend{font-weight:700;color:#08766b;padding:0 6px}.ss-types table{border-collapse:collapse;width:100%;font-size:14px}.ss-types th,.ss-types td{border-bottom:1px solid #e3ebe8;padding:6px 8px;text-align:left}
.ss-types input[type=text],.ss-types select{min-height:36px;font-size:15px;box-sizing:border-box;width:100%}.ss-types input.pct{width:70px}
.ss-btn{min-height:44px;background:#08766b;color:#fff;border:0;border-radius:8px;font-size:15px;padding:0 16px;cursor:pointer}
.ss-legend{font-size:14px;line-height:1.6;background:#f4fbf8;border-left:4px solid #08766b;padding:10px 12px}.ss-ok{color:#08766b;font-weight:600}.ss-ko{color:#a32d2d;font-weight:600}
.ss-types label.course{display:flex;gap:8px;align-items:center}</style></head>
<body><header><strong>SchoolSafe</strong><a href="preparer-annee.php">Préparer l’année</a></header>
<main class="ss-types"><h1>Types de devoirs <?=$e($year)?>-<?=$e($year+1)?></h1>
<p class="ss-legend">Cochez les types de devoirs de votre école ; vous pouvez changer leur nom ou ajouter les vôtres.
Chaque type est créé pour chaque cours choisi et chacun de ses enseignants. La « sorte » sert au rattrapage
(trois évaluations sous 50 %, d’au moins deux sortes). Un type déjà présent n’est jamais créé deux fois.</p>
<?php if($message):?><p class="ss-ok" role="status"><?=$e($message)?> <a href="preparer-annee.php">Revenir à « Préparer l’année »</a></p><?php endif;?>
<?php if($error):?><p class="ss-ko" role="alert"><?=$e($error)?></p><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=$e($csrf)?>">
<fieldset><legend>1. Vos types de devoirs</legend>
<table><thead><tr><th>Garder</th><th>Nom</th><th>Sorte pour le rattrapage</th><th>Part de la note (facultatif)</th></tr></thead><tbody>
<?php foreach($rows as $i=>$r):?><tr>
<td><input type="checkbox" name="t[<?=$i?>][on]" value="1"<?=$r['on']?' checked':''?> aria-label="Garder ce type"></td>
<td><input type="text" name="t[<?=$i?>][title]" value="<?=$e($r['title'])?>" maxlength="60" placeholder="Autre type…"></td>
<td><select name="t[<?=$i?>][kind]"><?php foreach($kinds as $k=>$label):?><option value="<?=$e($k)?>"<?=$k===$r['kind']?' selected':''?>><?=$e($label)?></option><?php endforeach;?></select></td>
<td><input class="pct" type="text" inputmode="decimal" name="t[<?=$i?>][percent]" value="<?=$e($r['percent'])?>" placeholder="%"></td></tr><?php endforeach;?>
</tbody></table>
<p style="margin:0;font-size:13px;color:#5b6b80">La part de la note ne compte que si l’enseignant choisit un carnet pondéré ; laissez vide sinon.</p></fieldset>
<fieldset><legend>2. Cours concernés</legend>
<?php if(!$courses):?><p class="ss-ko">Aucun cours n’a d’enseignant : créez d’abord les classes (étape « Matières, cours et classes »).</p><?php endif;?>
<?php foreach($courses as $id=>$c):?><label class="course"><input type="checkbox" name="courses[]" value="<?=$id?>"<?=in_array($id,$chosen,true)?' checked':''?>> <?=$e($c[0])?> <small>(<?=count(array_unique($c[1]))?> enseignant(s))</small></label><?php endforeach;?>
</fieldset>
<fieldset><legend>3. Confirmer</legend>
<label><input type="checkbox" name="confirm" value="1"> Je confirme la création de ces types pour les cours cochés.</label>
<div><button class="ss-btn" type="submit">Créer les types de devoirs</button></div></fieldset>
</form>
<fieldset><legend>Types déjà présents</legend>
<?php if(!$existing):?><p style="margin:0">Aucun pour le moment.</p><?php else:?><ul style="margin:0"><?php foreach($existing as $x):?><li><?=$e($x['TITLE'])?> — <?=$e($kinds[$x['KIND']??'']??'Hors rattrapage')?></li><?php endforeach;?></ul><?php endif;?>
</fieldset>
</main></body></html>
