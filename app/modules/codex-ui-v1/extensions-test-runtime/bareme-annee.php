<?php
// Choix du bareme (04/11, valide par le proprietaire) : chaque ecole choisit son bareme (pourcentage
// avec mentions, sur 20, sur 10) ou regle le sien dans l'ecran natif. Refus si des notes l'utilisent deja.
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','POST'],true)||$_GET){http_response_code(400);exit('Requête invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeYearSetup.fnc.php';
$school=(int)UserSchool();$year=(int)UserSyear();
// Meme droit que l'ecran natif des baremes.
$allowed=User('STAFF_ID')&&User('PROFILE')==='admin'&&$school>0&&$year>0&&AllowEdit('Grades/ReportCardGrades.php');
if(empty($_SESSION['SchoolSafeScannerCsrf']))$_SESSION['SchoolSafeScannerCsrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['SchoolSafeScannerCsrf'];
$get=function($sql){return DBGet(DBQuery($sql));};$exec=function($sql){DBQuery($sql);};
$models=SchoolSafeScaleModels();
$model=(string)($_POST['model']??'rdc');if(!isset($models[$model]))$model='rdc';
$action=(string)($_POST['action']??'');
// Mentions modifiables : celles du modele choisi, ou celles saisies par l'ecole.
$rows=[];foreach($models[$model]['grades'] as $g)$rows[]=[$g[0],$g[1],$g[2]];
if($action==='appliquer'&&is_array($_POST['g']??null)){$rows=[];foreach($_POST['g'] as $r)if(is_array($r))$rows[]=[(string)($r[0]??''),(string)($r[1]??''),(string)($r[2]??'')];}
$message='';$error='';
if($allowed&&$_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf']))throw new RuntimeException('Session expirée : rechargez la page.');
        if($action!=='appliquer')throw new RuntimeException('');
        if(empty($_POST['confirm']))throw new RuntimeException('Cochez la confirmation avant d’appliquer.');
        $done=SchoolSafeScaleApply($school,$year,$model,!empty($_POST['link']),$get,$exec,$rows);
        $message='Barème « '.$models[$model]['label'].' » appliqué : '.$done['grades'].' mention(s)'.($done['linked']?', '.$done['linked'].' classe(s) reliée(s)':'').'.';
    }catch(Throwable $failure){$error=$failure instanceof RuntimeException?$failure->getMessage():'Opération impossible.';}
    if($action==='modifier')$error='';
}
$current=$allowed?$get("SELECT s.TITLE AS SCALE,g.TITLE,g.COMMENT,g.BREAK_OFF FROM report_card_grade_scales s LEFT JOIN report_card_grades g ON g.GRADE_SCALE_ID=s.ID WHERE s.SCHOOL_ID=$school AND s.SYEAR=$year ORDER BY s.SORT_ORDER,g.SORT_ORDER"):[];
$noScale=$allowed?(int)($get("SELECT COUNT(*) AS N FROM course_periods WHERE SCHOOL_ID=$school AND SYEAR=$year AND GRADE_SCALE_ID IS NULL")[1]['N']??0):0;
while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(403);exit('Permission requise : administrateur autorisé à modifier les barèmes.');}
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Barème de notation | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<style>.ss-scale{display:grid;gap:14px;max-width:880px}.ss-scale fieldset{border:1px solid #c9d8d2;border-radius:10px;padding:12px 14px;display:grid;gap:8px}
.ss-scale legend{font-weight:700;color:#08766b;padding:0 6px}.ss-scale label.model{display:grid;grid-template-columns:auto 1fr;gap:4px 10px;padding:10px;border-radius:8px;cursor:pointer;border:1px solid transparent}
.ss-scale label.model:hover{background:#f4fbf8}.ss-scale label.model:has(input:checked){border-color:#08766b;background:#f4fbf8}.ss-scale label.model small{color:#5b6b80}
.ss-scale .grades{grid-column:2;display:flex;flex-wrap:wrap;gap:6px;margin-top:4px}.ss-scale .grades span{font-size:12.5px;padding:3px 8px;border-radius:12px;background:#e7f3ef;color:#14233a}
.ss-btn.alt{background:#fff;color:#08766b;border:1px solid #08766b}.ss-scale td input{min-height:36px;font-size:15px;box-sizing:border-box}.ss-btn{min-height:44px;background:#08766b;color:#fff;border:0;border-radius:8px;font-size:15px;padding:0 16px;cursor:pointer}.ss-scale table{border-collapse:collapse;width:100%;font-size:14px}
.ss-scale th,.ss-scale td{border-bottom:1px solid #e3ebe8;padding:6px 8px;text-align:left}.ss-legend{font-size:14px;line-height:1.6;background:#f4fbf8;border-left:4px solid #08766b;padding:10px 12px}
.ss-ok{color:#08766b;font-weight:600}.ss-ko{color:#a32d2d;font-weight:600}</style><?php require_once __DIR__.'/ProgramFunctions/SchoolSafePedagogyNav.fnc.php'; echo SchoolSafePedagogyStyle(); ?></head>
<body><?php echo SchoolSafePedagogyHeader('bareme-annee.php',0); ?>
<main class="ss-scale"><h1>Barème de notation <?=$e($year)?>-<?=$e($year+1)?></h1>
<p class="ss-legend">Choisissez le barème de votre école. Il transforme les notes en mentions sur les bulletins et fixe le seuil de réussite.
Le rattrapage reste déclenché par trois évaluations sous 50 %. Si des notes de bulletin utilisent déjà le barème actuel, rien n’est modifié.</p>
<?php if($message):?><p class="ss-ok" role="status"><?=$e($message)?> <a href="preparer-annee.php">Revenir à « Préparer l’année »</a></p><?php endif;?>
<?php if($error):?><p class="ss-ko" role="alert"><?=$e($error)?></p><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=$e($csrf)?>">
<fieldset><legend>1. Votre barème</legend>
<?php foreach($models as $id=>$m):?><label class="model"><input type="radio" name="model" value="<?=$e($id)?>"<?=$id===$model?' checked':''?>>
<span><b><?=$e($m['label'])?></b> <small><?=$e($m['desc'])?></small></span>
<span class="grades"><?php foreach($m['grades'] as $g):?><span><?=$e($g[0])?> · <?=$e($g[1])?><?=$g[2]>0?' · ≥ '.$e($g[2]).' %':''?></span><?php endforeach;?><span>NE · Non évalué</span></span></label><?php endforeach;?>
<label class="model"><span></span><span><b>Autre barème</b> <small>Partez d’un modèle et modifiez les mentions ci-dessous, ou réglez tout dans <a href="Modules.php?modname=Grades/ReportCardGrades.php">l’écran des barèmes</a>.</small></span></label>
<div><button class="ss-btn alt" type="submit" name="action" value="modifier">Afficher ce modèle pour le modifier</button></div>
</fieldset>
<fieldset><legend>2. Mentions — modifiables (<?=$e($models[$model]['label'])?>)</legend>
<p style="margin:0;font-size:13px;color:#5b6b80">Du plus haut au plus bas ; la dernière commence à 0 %. Videz une ligne pour la retirer ; remplissez une ligne vide pour ajouter une mention. « NE – Non évalué » est ajouté automatiquement.</p>
<table><thead><tr><th>Code</th><th>Libellé</th><th>À partir de (%)</th></tr></thead><tbody>
<?php $show=array_merge($rows,[['','',''],['','','']]);foreach($show as $i=>$r):?><tr>
<td><input name="g[<?=$i?>][0]" value="<?=$e($r[0])?>" maxlength="5" size="5"></td>
<td><input name="g[<?=$i?>][1]" value="<?=$e($r[1])?>" maxlength="80" style="width:100%"></td>
<td><input name="g[<?=$i?>][2]" value="<?=$e($r[2])?>" inputmode="decimal" size="6"></td></tr><?php endforeach;?>
</tbody></table></fieldset>
<fieldset><legend>3. Confirmer</legend>
<?php if($noScale):?><label><input type="checkbox" name="link" value="1" checked> Utiliser ce barème pour les <?=$noScale?> classe(s) qui n’en ont pas (sinon leurs notes n’iront pas au bulletin).</label><?php endif;?>
<label><input type="checkbox" name="confirm" value="1"> Je confirme ce barème pour l’école.</label>
<div><button class="ss-btn" type="submit" name="action" value="appliquer">Appliquer ce barème</button></div></fieldset>
</form>
<fieldset><legend>Barème actuel</legend><table><thead><tr><th>Barème</th><th>Mention</th><th>Seuil</th><th>Libellé</th></tr></thead><tbody>
<?php foreach((array)$current as $row):?><tr><td><?=$e($row['SCALE'])?></td><td><?=$e($row['TITLE'])?></td><td><?=$row['BREAK_OFF']!==null&&$row['BREAK_OFF']!==''?$e(rtrim(rtrim((string)$row['BREAK_OFF'],'0'),'.')).' %':''?></td><td><?=$e($row['COMMENT'])?></td></tr><?php endforeach;?>
</tbody></table></fieldset>
</main></body></html>
