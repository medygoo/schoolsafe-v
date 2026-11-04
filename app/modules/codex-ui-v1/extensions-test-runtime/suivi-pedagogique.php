<?php
// Suivi pedagogique de la direction (04/11, lot 9 valide) : ou en est chaque classe, chaque enseignant,
// qui relancer, et le journal des notes modifiees. Lecture seule.
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){http_response_code(405);exit;}
if(array_diff(array_keys($_GET),['du','au','vue'])){http_response_code(400);exit('Requête invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafePedagogyFollowup.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeRemediationSettings.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeLetterhead.fnc.php';
$school=(int)UserSchool();$year=(int)UserSyear();$staff=(int)User('STAFF_ID');
$allowed=$staff>0&&User('PROFILE')==='admin'&&$school>0&&$year>0&&(AllowEdit('School_Setup/Configuration.php')||AllowUse('Grades/ReportCards.php'));
$today=date('Y-m-d');
[$from,$to]=SchoolSafeFollowupRange(is_string($_GET['du']??null)?$_GET['du']:null,is_string($_GET['au']??null)?$_GET['au']:null,$today);
$view=($_GET['vue']??'')==='journal'?'journal':'classes';
$rows=[];$journal=[];$pass=50.0;
if($allowed){
    $settings=SchoolSafeRemediationSettings($school,'DBGet');$pass=(float)($settings['threshold_percent']??50);
    if($view==='classes')$rows=SchoolSafeFollowupRows($school,$year,$from,$to,$today,'DBGet');
    else $journal=SchoolSafeFollowupJournal($school,$year,'DBGet',150);
}
while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(403);exit('Page réservée à la direction de l’école.');}
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$teachers=[];$tot=['students'=>0,'exp'=>0,'done'=>0,'missing'=>0,'abs'=>0,'rem'=>0,'hw'=>0];
foreach($rows as $r){
    $al=SchoolSafeFollowupAlerts($r);
    $name=trim(($r['FIRST_NAME']??'').' '.($r['LAST_NAME']??''))?:'Sans enseignant';
    if($al)$teachers[$name][]=ParseMLField($r['COURSE_TITLE']).' ('.$r['TITLE'].') : '.implode(', ',$al);
    $tot['students']+=(int)$r['STUDENTS'];$tot['missing']+=(int)$r['MISSING_GRADES'];$tot['abs']+=(int)$r['ABSENCES'];$tot['hw']+=(int)$r['HOMEWORK'];$tot['rem']+=(int)$r['REMEDIATION'];
    if(in_array($r['TAKES_ROLL'],[true,'t','Y',1,'1'],true)){$tot['exp']+=(int)$r['EXPECTED_ROLLS'];$tot['done']+=(int)$r['DONE_ROLLS'];}
}
$labels=['ajout'=>'Ajout','modification'=>'Modification','retrait'=>'Retrait'];
$link=fn($v)=>'suivi-pedagogique.php?'.http_build_query(['du'=>$from,'au'=>$to]+($v==='journal'?['vue'=>'journal']:[]));
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Suivi pédagogique | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<style>.ss-sp{display:grid;gap:16px;max-width:1180px}.ss-box{border:1px solid #c9d8d2;border-radius:12px;padding:14px;display:grid;gap:10px;background:#fff}.ss-box h2{margin:0;font-size:19px;color:#08766b}
.ss-kpi{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px}.ss-kpi div{border:1px solid #e3ebe8;border-radius:10px;padding:10px;background:#f4fbf8}.ss-kpi b{display:block;font-size:22px;color:#08766b}
.ss-tabs{display:flex;gap:8px;flex-wrap:wrap}.ss-tabs a{border:1px solid #c9d8d2;border-radius:16px;padding:6px 12px;background:#f4fbf8;color:#08766b;text-decoration:none}.ss-tabs a.on{background:#08766b;color:#fff}
.ss-form{display:flex;gap:10px;flex-wrap:wrap;align-items:end}.ss-form label{display:grid;gap:4px;font-size:14px}.ss-form input{min-height:38px;font-size:15px}
.ss-btn{min-height:40px;background:#08766b;color:#fff;border:0;border-radius:8px;font-size:15px;padding:0 16px;cursor:pointer}
.ss-wrap{overflow-x:auto}.ss-table{width:100%;border-collapse:collapse;font-size:14px}.ss-table td,.ss-table th{border-bottom:1px solid #e3ebe8;padding:6px;text-align:left;vertical-align:top}.ss-table td.n{text-align:center}
.ss-sub{color:#556777;font-size:13px}.ok{color:#08766b;font-weight:600}.mid{color:#a86200;font-weight:600}.ko{color:#a32d2d;font-weight:600}.ss-alert{background:#fff4f2;border:1px solid #f0c8c0}</style></head>
<body><header><strong>SchoolSafe</strong><a href="ma-journee.php">Ma journée</a></header>
<main class="ss-sp"><h1>Suivi pédagogique</h1>
<div class="ss-tabs"><a class="<?=$view==='classes'?'on':''?>" href="<?=$e($link('classes'))?>">Classes et enseignants</a><a class="<?=$view==='journal'?'on':''?>" href="<?=$e($link('journal'))?>">Journal des notes</a></div>
<?php if($view==='classes'):?>
<form method="get" class="ss-form"><label>Du<input type="date" name="du" value="<?=$e($from)?>" max="<?=$e($today)?>"></label><label>Au<input type="date" name="au" value="<?=$e($to)?>" max="<?=$e($today)?>"></label><button class="ss-btn" type="submit">Afficher</button>
<span class="ss-sub">Période du <?=$e(SchoolSafeLetterheadDate($from,false))?> au <?=$e(SchoolSafeLetterheadDate($to,false))?> (3 mois au plus).</span></form>
<div class="ss-kpi"><div><b><?=$tot['exp']?round($tot['done']*100/$tot['exp']).' %':'—'?></b>appels faits (<?=$tot['done']?> sur <?=$tot['exp']?>)</div><div><b><?=$tot['hw']?></b>devoirs donnés</div>
<div><b class="<?=$tot['missing']?'ko':''?>"><?=$tot['missing']?></b>copies non notées</div><div><b><?=$tot['abs']?></b>absences en classe</div><div><b><?=$tot['rem']?></b>rattrapages en cours</div><div><b><?=count($teachers)?></b>enseignant(s) à relancer</div></div>
<?php if($teachers):?><section class="ss-box ss-alert"><h2>À relancer</h2><ul style="margin:0"><?php foreach($teachers as $name=>$items):?><li><b><?=$e($name)?></b><ul><?php foreach($items as $it):?><li><?=$e($it)?></li><?php endforeach;?></ul></li><?php endforeach;?></ul></section>
<?php elseif($rows):?><p class="ok" role="status">Tout est à jour sur la période : appels, cahiers de textes et copies.</p><?php endif;?>
<section class="ss-box"><h2>Classes et matières</h2>
<?php if(!$rows):?><p class="ss-sub">Aucune classe pour cette année scolaire.</p><?php else:?>
<div class="ss-wrap"><table class="ss-table"><thead><tr><th>Matière / classe</th><th>Enseignant</th><th>Élèves</th><th>Appels faits</th><th>Cahier de textes</th><th>Devoirs</th><th>Non notées</th><th>Moyenne</th><th>Absences</th><th>Rattrapage</th></tr></thead><tbody>
<?php foreach($rows as $r):$roll=in_array($r['TAKES_ROLL'],[true,'t','Y',1,'1'],true);$lv=SchoolSafeFollowupLevel($r['AVERAGE'],$pass);?>
<tr><td><b><?=$e(ParseMLField($r['COURSE_TITLE']))?></b><br><span class="ss-sub"><?=$e($r['TITLE'])?></span></td><td><?=$e(trim(($r['FIRST_NAME']??'').' '.($r['LAST_NAME']??''))?:'—')?></td><td class="n"><?=(int)$r['STUDENTS']?></td>
<td class="n <?=$roll&&(int)$r['DONE_ROLLS']<(int)$r['EXPECTED_ROLLS']?'ko':''?>"><?=$roll?(int)$r['DONE_ROLLS'].' / '.(int)$r['EXPECTED_ROLLS']:'<span class="ss-sub">pas d’appel</span>'?></td>
<td class="n <?=(int)$r['STUDENTS']&&(int)$r['DIARY_DAYS']<(int)$r['EXPECTED_DAYS']?'ko':''?>"><?=(int)$r['DIARY_DAYS']?> / <?=(int)$r['EXPECTED_DAYS']?> j</td><td class="n"><?=(int)$r['HOMEWORK']?></td>
<td class="n <?=(int)$r['MISSING_GRADES']?'ko':''?>"><?=(int)$r['MISSING_GRADES']?></td><td class="n <?=$lv?>"><?=$r['AVERAGE']===null||$r['AVERAGE']===''?'—':$e(str_replace('.',',',(string)$r['AVERAGE'])).' %'?></td>
<td class="n"><?=(int)$r['ABSENCES']?></td><td class="n"><?=(int)$r['REMEDIATION']?></td></tr><?php endforeach;?>
</tbody></table></div><p class="ss-sub">Moyenne : notes enregistrées de l’année, en rouge sous le seuil d’échec de l’école (<?=$e(str_replace('.',',',(string)$pass))?> %). Copies non notées : devoirs dont la date de remise est passée.</p><?php endif;?></section>
<?php else:?>
<section class="ss-box"><h2>Journal des modifications de notes</h2><p class="ss-sub">Chaque note ajoutée, changée ou retirée (devoirs et bulletins), avec l’auteur et l’heure. Ce journal ne peut pas être modifié.</p>
<?php if(!$journal):?><p class="ss-sub">Aucune note enregistrée depuis la mise en place du journal.</p><?php else:?>
<div class="ss-wrap"><table class="ss-table"><thead><tr><th>Date</th><th>Élève</th><th>Matière</th><th>Note</th><th>Changement</th><th>Par</th></tr></thead><tbody>
<?php foreach($journal as $j):?><tr><td><?=$e(substr((string)$j['CHANGED_AT'],0,16))?></td><td><?=$e(trim(($j['FIRST_NAME']??'').' '.($j['LAST_NAME']??'')))?></td>
<td><?=$e(ParseMLField($j['COURSE_TITLE']))?><br><span class="ss-sub"><?=$e($j['SOURCE']==='bulletin'?'Bulletin':($j['ASSIGNMENT']?:'Devoir'))?></span></td>
<td class="<?=$j['ACTION']==='ajout'?'':'mid'?>"><?=$e($labels[$j['ACTION']]??$j['ACTION'])?></td>
<td><?=$j['OLD_VALUE']!==null&&$j['OLD_VALUE']!==''?$e($j['OLD_VALUE']==='-1.00'?'dispensé':$j['OLD_VALUE']).' → ':''?><?=$e($j['NEW_VALUE']==='-1.00'?'dispensé':($j['NEW_VALUE']??'—'))?></td>
<td><?=$e(trim(($j['BY_FIRST']??'').' '.($j['BY_LAST']??''))?:'système')?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section>
<?php endif;?></main></body></html>
