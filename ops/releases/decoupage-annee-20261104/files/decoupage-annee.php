<?php
// Decoupage de l'annee (04/11, valide par le proprietaire) : chaque ecole choisit son modele
// (trimestres, semestres, periodes...), ajuste les dates sur un apercu, puis confirme.
// L'application reutilise les periodes existantes (aucune donnee cassee) ; « libre » = ecran natif.
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','POST'],true)||$_GET){http_response_code(400);exit('Requête invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeYearSetup.fnc.php';
$school=(int)UserSchool();$year=(int)UserSyear();
// Meme droit que l'ecran natif des periodes : administrateur qui peut les modifier.
$allowed=User('STAFF_ID')&&User('PROFILE')==='admin'&&$school>0&&$year>0&&AllowEdit('School_Setup/MarkingPeriods.php');
if(empty($_SESSION['SchoolSafeScannerCsrf']))$_SESSION['SchoolSafeScannerCsrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['SchoolSafeScannerCsrf'];
$get=function($sql){return DBGet(DBQuery($sql));};$exec=function($sql){DBQuery($sql);};
$models=SchoolSafeYearModels();
$model=(string)($_POST['model']??'trimestres_periodes');if(!isset($models[$model]))$model='trimestres_periodes';
$start=(string)($_POST['start']??($year.'-09-01'));$end=(string)($_POST['end']??(($year+1).'-07-02'));
$action=(string)($_POST['action']??'');$plan=null;$message='';$error='';$summary=null;
if($allowed&&$_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf']))throw new RuntimeException('Session expirée : rechargez la page.');
        $dates=[];
        foreach((array)($_POST['dates']??[]) as $i=>$pair)if(is_array($pair))$dates[(int)$i]=[(string)($pair[0]??''),(string)($pair[1]??'')];
        $plan=SchoolSafeYearPlan($model,$start,$end,in_array($action,['appliquer','ajuster'],true)?$dates:[]);
        if($action==='appliquer'){
            if(empty($_POST['confirm']))throw new RuntimeException('Cochez la confirmation avant d’appliquer.');
            $summary=SchoolSafeYearApply($school,$year,$plan,$get,$exec);
            unset($_SESSION['UserMP']); // la periode courante est recalculee au prochain ecran
            $message='Découpage appliqué : '.$summary['renamed'].' période(s) mise(s) à jour, '.$summary['created'].' créée(s), '.$summary['deleted'].' retirée(s).';
            $plan=null;
        }
    }catch(Throwable $failure){$error=$failure instanceof RuntimeException?$failure->getMessage():'Opération impossible.';}
}
$current=$allowed?$get("SELECT MP,TITLE,START_DATE,END_DATE FROM school_marking_periods WHERE SCHOOL_ID=$school AND SYEAR=$year ORDER BY CASE MP WHEN 'FY' THEN 0 WHEN 'SEM' THEN 1 WHEN 'QTR' THEN 2 ELSE 3 END,START_DATE"):[];
while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(403);exit('Permission requise : administrateur autorisé à modifier les périodes.');}
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$levels=['FY'=>'Année','SEM'=>'Bloc','QTR'=>'Période de notes','PRO'=>'Suivi'];
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Découpage de l’année | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<style>.ss-cut{display:grid;gap:14px;max-width:880px}.ss-cut fieldset{border:1px solid #c9d8d2;border-radius:10px;padding:12px 14px;display:grid;gap:8px}
.ss-cut legend{font-weight:700;color:#08766b;padding:0 6px}.ss-cut label.model{display:flex;gap:10px;align-items:flex-start;padding:8px;border-radius:8px;cursor:pointer}
.ss-cut label.model:hover{background:#f4fbf8}.ss-cut label.model small{display:block;color:#5b6b80}.ss-cut .row{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}
.ss-cut input[type=date]{min-height:40px;font-size:15px}.ss-btn{min-height:44px;background:#08766b;color:#fff;border:0;border-radius:8px;font-size:15px;padding:0 16px;cursor:pointer}
.ss-btn.alt{background:#fff;color:#08766b;border:1px solid #08766b}.ss-cut table{border-collapse:collapse;width:100%;font-size:14px}.ss-cut th,.ss-cut td{border-bottom:1px solid #e3ebe8;padding:6px 8px;text-align:left}
.ss-cut td.l1{font-weight:700}.ss-cut td.l2{padding-left:26px}.ss-legend{font-size:14px;line-height:1.6;background:#f4fbf8;border-left:4px solid #08766b;padding:10px 12px}
.ss-ok{color:#08766b;font-weight:600}.ss-ko{color:#a32d2d;font-weight:600}.ss-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}</style></head>
<body><header><strong>SchoolSafe</strong><a href="preparer-annee.php">Préparer l’année</a></header>
<main class="ss-cut"><h1>Découpage de l’année <?=$e($year)?>-<?=$e($year+1)?></h1>
<p class="ss-legend">Choisissez le découpage qui correspond à votre école. Un aperçu s’affiche avant tout changement ; vous pouvez y ajuster chaque date.
Les périodes existantes sont réutilisées et renommées : les cours, emplois du temps et notes déjà saisis restent rattachés. Rien n’est supprimé si des données s’y trouvent.</p>
<?php if($message):?><p class="ss-ok" role="status"><?=$e($message)?> <a href="preparer-annee.php">Revenir à « Préparer l’année »</a></p><?php endif;?>
<?php if($error):?><p class="ss-ko" role="alert"><?=$e($error)?></p><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=$e($csrf)?>">
<fieldset><legend>1. Votre découpage</legend>
<?php foreach($models as $id=>$m):?><label class="model"><input type="radio" name="model" value="<?=$e($id)?>"<?=$id===$model?' checked':''?>><span><b><?=$e($m['label'])?></b><small><?=$e($m['desc'])?></small></span></label><?php endforeach;?>
<label class="model"><span><b>Autre découpage</b><small>Découpez vous-même dans <a href="Modules.php?modname=School_Setup/MarkingPeriods.php">l’écran des périodes</a>.</small></span></label>
</fieldset>
<fieldset><legend>2. Dates de l’année</legend><div class="row">
<label>Premier jour<input type="date" name="start" value="<?=$e($start)?>" required></label>
<label>Dernier jour<input type="date" name="end" value="<?=$e($end)?>" required></label></div></fieldset>
<div class="ss-actions"><button class="ss-btn" type="submit" name="action" value="apercu">Voir l’aperçu</button></div>
<?php if($plan):?>
<fieldset><legend>3. Aperçu — ajustez les dates si besoin</legend>
<table><thead><tr><th>Période</th><th>Début</th><th>Fin</th></tr></thead><tbody>
<tr><td class="l1"><?=$e($plan['fy']['title'])?></td><td><?=$e($plan['fy']['start'])?></td><td><?=$e($plan['fy']['end'])?></td></tr>
<?php $i=0;foreach($plan['sems'] as $sem):?>
<tr><td class="l1"><?=$e($sem['title'])?></td><td><?=$e($sem['start'])?></td><td><?=$e($sem['end'])?></td></tr>
<?php foreach($sem['qtrs'] as $qtr):?><tr><td class="l2"><?=$e($qtr['title'])?></td>
<td><input type="date" name="dates[<?=$i?>][0]" value="<?=$e($qtr['start'])?>"></td><td><input type="date" name="dates[<?=$i?>][1]" value="<?=$e($qtr['end'])?>"></td></tr>
<?php $i++;endforeach;endforeach;?></tbody></table>
<div class="ss-actions"><button class="ss-btn alt" type="submit" name="action" value="ajuster">Recalculer avec ces dates</button></div>
<label><input type="checkbox" name="confirm" value="1"> Je confirme ce découpage pour l’école (les notes se saisiront sur les périodes en retrait).</label>
<div class="ss-actions"><button class="ss-btn" type="submit" name="action" value="appliquer">Appliquer ce découpage</button></div>
</fieldset>
<?php endif;?>
</form>
<fieldset><legend>Découpage actuel</legend><table><thead><tr><th>Niveau</th><th>Nom</th><th>Début</th><th>Fin</th></tr></thead><tbody>
<?php foreach((array)$current as $row):?><tr><td><?=$e($levels[$row['MP']]??$row['MP'])?></td><td><?=$e($row['TITLE'])?></td><td><?=$e($row['START_DATE'])?></td><td><?=$e($row['END_DATE'])?></td></tr><?php endforeach;?>
</tbody></table></fieldset>
</main></body></html>
