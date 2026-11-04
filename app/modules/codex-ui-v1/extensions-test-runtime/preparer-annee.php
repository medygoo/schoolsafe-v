<?php
// Preparer l'annee (03/11, valide par le proprietaire) : les etapes de la preparation de l'annee scolaire
// dans l'ordre, avec leur etat reel et un bouton vers l'ecran natif qui les realise. Lecture seule.
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'||$_GET){http_response_code(400);exit('Requête invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeYearSetup.fnc.php';
$school=(int)UserSchool();$year=(int)UserSyear();
// Meme droit que la configuration de l'ecole : administrateur de l'ecole.
$allowed=User('STAFF_ID')&&User('PROFILE')==='admin'&&$school>0&&$year>0&&AllowEdit('School_Setup/Configuration.php');
$steps=$allowed?SchoolSafeYearSetupSteps($school,$year,function($sql){return DBGet(DBQuery($sql));}):[];
$schoolTitle=$allowed?(string)SchoolInfo('TITLE'):'';
while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(403);exit('Permission requise : administrateur de l’école.');}
[$done,$total]=SchoolSafeYearSetupProgress($steps);
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$badge=['ok'=>['✅','Fait'],'warn'=>['⚠️','À corriger'],'todo'=>['⭕','À faire']];
$next=null;foreach($steps as $st)if($st['state']!=='ok'){$next=$st['id'];break;}
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Préparer l’année | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<style>.ss-year{display:grid;gap:12px;max-width:900px}.ss-year .bar{height:10px;border-radius:5px;background:#e3ebe8;overflow:hidden}
.ss-year .bar span{display:block;height:100%;background:#08766b}.ss-step{display:grid;grid-template-columns:auto 1fr auto;gap:6px 14px;align-items:start;border:1px solid #c9d8d2;border-radius:10px;padding:12px 14px;background:#fff}
.ss-step.next{border-color:#08766b;box-shadow:0 0 0 2px rgba(8,118,107,.15)}.ss-step .num{width:30px;height:30px;border-radius:50%;background:#08766b;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700}
.ss-step.ok .num{background:#2e8b57}.ss-step.warn .num{background:#c78a00}.ss-step.todo .num{background:#a32d2d}
.ss-step h2{margin:0;font-size:16px;color:#14233a}.ss-step .state{font-size:13px;font-weight:600;margin-left:6px}.ss-step p{margin:3px 0 0;font-size:14px;line-height:1.45}
.ss-step .help{color:#5b6b80;font-size:13px}.ss-step a.go{align-self:center;white-space:nowrap;min-height:40px;display:inline-flex;align-items:center;padding:0 14px;border-radius:8px;background:#08766b;color:#fff;text-decoration:none;font-size:14px}
.ss-step.ok a.go{background:#fff;color:#08766b;border:1px solid #08766b}.ss-legend{font-size:14px;line-height:1.6;background:#f4fbf8;border-left:4px solid #08766b;padding:10px 12px}
@media(max-width:620px){.ss-step{grid-template-columns:auto 1fr}.ss-step a.go{grid-column:1/-1;justify-content:center}}</style></head>
<body><header><strong>SchoolSafe</strong><a href="Modules.php?modname=misc/Portal.php">Tableau de bord</a></header>
<main class="ss-year"><h1>Préparer l’année <?=$e($year)?>-<?=$e($year+1)?></h1>
<p class="ss-legend"><b><?=$e($schoolTitle)?></b> — <?=$done?> étape(s) terminée(s) sur <?=$total?>.<br>
Suivez les étapes dans l’ordre : chacune prépare la suivante. Le bouton ouvre l’écran où l’étape se fait ; revenez ici pour voir l’état mis à jour.</p>
<div class="bar" role="progressbar" aria-valuemin="0" aria-valuemax="<?=$total?>" aria-valuenow="<?=$done?>"><span style="width:<?=$total?round($done*100/$total):0?>%"></span></div>
<?php foreach($steps as $i=>$st):[$icon,$label]=$badge[$st['state']];?>
<section class="ss-step <?=$e($st['state'])?><?=$st['id']===$next?' next':''?>" id="etape-<?=$e($st['id'])?>">
<span class="num"><?=$i+1?></span>
<div><h2><?=$e($st['title'])?><span class="state"><?=$icon?> <?=$e($label)?></span></h2>
<p><?=$e($st['detail'])?></p><p class="help"><?=$e($st['help'])?></p></div>
<a class="go" href="<?=$e($st['url'])?>"><?=$st['state']==='ok'?'Voir':'Ouvrir'?></a>
</section>
<?php endforeach;?>
</main></body></html>
