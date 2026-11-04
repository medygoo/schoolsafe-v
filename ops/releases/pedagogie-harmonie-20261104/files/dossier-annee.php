<?php
// Dossier de l'annee (04/11, idee du proprietaire) : tous les travaux de l'enfant pour une annee scolaire,
// classes par trimestre et par matiere, avec le bilan ; a consulter a l'ecran ou a garder en PDF au papier
// de l'ecole (page de garde, bilan, travaux, copies corrigees). Parent et eleve : leurs enfants seulement ;
// la direction peut l'imprimer pour une famille. Lecture seule.
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){http_response_code(405);exit;}
if(array_diff(array_keys($_GET),['enfant','annee','pdf','copies'])){http_response_code(400);exit('Requête invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeChildWork.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafePortfolio.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeGradeNotice.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeRemediationSettings.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeLetterhead.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeSchoolDocument.fnc.php';
$school=(int)UserSchool();$year=(int)UserSyear();$profile=(string)User('PROFILE');$staff=(int)User('STAFF_ID');
$get=function($sql){return DBGet($sql);};
$isStaff=$profile==='admin'&&$school>0&&(AllowEdit('School_Setup/Configuration.php')||AllowUse('Grades/ReportCards.php'));
$allowed=$school>0&&$year>0&&(($profile==='parent'||$profile==='student')&&AllowUse('Grades/StudentAssignments.php')||$isStaff);
$children=[];
if($allowed&&!$isStaff)$children=SchoolSafeChildWorkChildren($profile,$staff,(int)($_SESSION['STUDENT_ID']??0),$school,$year,$get);
if($isStaff&&isset($_GET['enfant'])&&ctype_digit((string)$_GET['enfant']))
    $children=(array)DBGet("SELECT s.STUDENT_ID,s.FIRST_NAME,s.LAST_NAME,(SELECT g.TITLE FROM school_gradelevels g WHERE g.ID=e.GRADE_ID) AS LEVEL_TITLE FROM students s JOIN student_enrollment e ON e.STUDENT_ID=s.STUDENT_ID AND e.SCHOOL_ID=".$school." AND e.SYEAR=".$year." WHERE s.STUDENT_ID=".(int)$_GET['enfant']." LIMIT 1");
$child=SchoolSafeChildWorkPick($_GET['enfant']??(UserStudentID()?:0),$children);
$sid=$child?(int)$child['STUDENT_ID']:0;
$years=$sid?SchoolSafePortfolioYears($sid,$school,$get):[];
$y=isset($_GET['annee'])&&ctype_digit((string)$_GET['annee'])&&in_array((int)$_GET['annee'],$years,true)?(int)$_GET['annee']:($years[0]??$year);
$tz=new DateTimeZone('Africa/Kinshasa');$today=(new DateTime('now',$tz))->format('Y-m-d');
$works=$sid?SchoolSafePortfolioWorks($sid,$school,$y,$today,$get):[];
$threshold=$allowed?(float)(SchoolSafeRemediationSettings($school,$get)['threshold_percent']??50):50.0;
$summary=SchoolSafePortfolioSummary($works,$threshold);
$groups=SchoolSafePortfolioGroup($works);
$presence=$sid?SchoolSafePortfolioAttendance($sid,$school,$y,$get):[];
$remediation=$sid?(int)(DBGetOne("SELECT count(*) FROM schoolsafe_remediation_cases WHERE STUDENT_ID=".$sid." AND SCHOOL_ID=".$school." AND SYEAR=".$y)?:0):0;
$schoolName=$allowed?(string)SchoolInfo('TITLE'):'';
$pdf=isset($_GET['pdf'])&&$sid;
$withCopies=($_GET['copies']??'')==='1';
$answers=[];$copies=[];$photo='';$identity=[];
if($pdf){
    $ids=array_map(static function($w){return (int)$w['ASSIGNMENT_ID'];},$works);
    if($ids)foreach((array)DBGet("SELECT ASSIGNMENT_ID,ANSWER FROM schoolsafe_assignment_keys WHERE ASSIGNMENT_ID IN (".implode(',',$ids).")") as $r)$answers[(int)$r['ASSIGNMENT_ID']]=(string)$r['ANSWER'];
    if($ids&&$withCopies)foreach((array)DBGet("SELECT ASSIGNMENT_ID,MIME,encode(CONTENT,'base64') AS B64 FROM schoolsafe_grade_copies WHERE STUDENT_ID=".$sid." AND MIME IN ('image/jpeg','image/png') AND ASSIGNMENT_ID IN (".implode(',',$ids).") ORDER BY ASSIGNMENT_ID LIMIT 40") as $r)$copies[(int)$r['ASSIGNMENT_ID']]=['mime'=>$r['MIME'],'b64'=>str_replace(["\n","\r"],'',(string)$r['B64'])];
    foreach([$y,$year] as $py){$f=__DIR__.'/assets/StudentPhotos/'.$py.'/'.$sid.'.jpg';if(is_file($f)&&filesize($f)<3000000){$photo='data:image/jpeg;base64,'.base64_encode((string)file_get_contents($f));break;}}
    $identity=SchoolSafeLetterheadIdentity($school);
}
while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(403);exit('Page réservée aux parents, aux élèves et à la direction.');}
if($isStaff&&!$sid){http_response_code(400);exit('Choisissez un élève : dossier-annee.php?enfant=…');}
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$t=fn($v)=>SchoolSafePortfolioTitle((string)$v);
$pct=fn($v)=>$v===null?'—':str_replace('.',',',(string)$v).' %';
$arrow=['hausse'=>'📈 en progrès','baisse'=>'📉 en baisse','stable'=>'➡️ stable',''=>''];
$name=$child?trim($child['FIRST_NAME'].' '.$child['LAST_NAME']):'';
$classTitle=$child?$t($child['LEVEL_TITLE']??''):'';
$yearLabel=SchoolSafePortfolioYearLabel($y);
$graded=0;foreach($summary['courses'] as $c)$graded+=$c['graded'];

/* Bilan commun a l'ecran et au PDF. */
$bilan='<table class="pf-table"><thead><tr><th>Matière</th><th>Travaux</th><th>Notés</th><th>Moyenne</th><th>Progression</th></tr></thead><tbody>';
foreach($summary['courses'] as $course=>$c){
    $cls=$c['average']===null?'':($c['average']<$threshold?'pf-ko':($c['average']<$threshold+10?'pf-mid':'pf-ok'));
    $bilan.='<tr><td><b>'.$e($course).'</b></td><td>'.(int)$c['works'].'</td><td>'.(int)$c['graded'].'</td><td class="'.$cls.'">'.$e($pct($c['average'])).'</td><td>'.SchoolSafePortfolioSpark($c['series']).' '.$e($arrow[$c['direction']]).'</td></tr>';
}
$bilan.='</tbody></table>';
$strengths='<p><b>Points forts :</b> '.($summary['strong']?$e(implode(', ',$summary['strong'])):'—').'<br><b>À travailler :</b> '.($summary['weak']?$e(implode(', ',$summary['weak'])):'—').'</p>';
$presenceText='Absences : '.(int)($presence['Absent']??0).' · absences excusées : '.(int)($presence['Absence excusée']??0).' · retards : '.(int)($presence['Retard']??0).' · rattrapages suivis : '.$remediation;

if($pdf){
    $html=SchoolSafePortfolioPdfHtml(compact('e','t','pct','photo','name','classTitle','yearLabel','schoolName','works','graded','summary','bilan','strengths','presenceText','groups','answers','copies','threshold','today','identity'));
    try{$bytes=SchoolSafeDocumentPdf($html);}
    catch(Throwable $failure){http_response_code(503);exit('Le PDF n’a pas pu être préparé. Réessayez sans les copies corrigées, ou plus tard.');}
    $file=preg_replace('/[^A-Za-z0-9-]+/','-',iconv('UTF-8','ASCII//TRANSLIT//IGNORE','dossier-'.$yearLabel.'-'.$name));
    header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.trim($file,'-').'.pdf"');echo $bytes;exit;
}
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dossier de l’année | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<style>.pf{display:grid;gap:14px;max-width:980px}.pf-card{border:1px solid #c9d8d2;border-radius:12px;padding:14px;display:grid;gap:8px;background:#fff}.pf-card h2{margin:0;font-size:19px;color:#08766b}.pf-card h3{margin:6px 0 0;font-size:16px}
.pf-kpi{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px}.pf-kpi div{border:1px solid #e3ebe8;border-radius:10px;padding:10px;background:#f4fbf8}.pf-kpi b{display:block;font-size:22px;color:#08766b}
.pf-table{width:100%;border-collapse:collapse;font-size:14px}.pf-table th,.pf-table td{border-bottom:1px solid #e3ebe8;padding:6px;text-align:left;vertical-align:middle}.pf-ok{color:#08766b;font-weight:600}.pf-mid{color:#a86200;font-weight:600}.pf-ko{color:#a32d2d;font-weight:600}
.pf-chips{display:flex;gap:6px;flex-wrap:wrap}.pf-chips a{border:1px solid #c9d8d2;border-radius:16px;padding:4px 10px;background:#f4fbf8;color:#08766b;text-decoration:none;font-size:13px}.pf-chips a.on{background:#08766b;color:#fff}
.pf-btn{min-height:44px;background:#08766b;color:#fff;border:0;border-radius:8px;font-size:15px;padding:0 16px;text-decoration:none;display:inline-flex;align-items:center}.pf-btn.light{background:#eef7f4;color:#08766b;border:1px solid #08766b}
.pf-sub{color:#556777;font-size:13px}details summary{cursor:pointer;font-weight:600;color:#08766b;font-size:17px}@media(max-width:640px){.pf-table .hide-sm{display:none}}</style><?php require_once __DIR__.'/ProgramFunctions/SchoolSafePedagogyNav.fnc.php'; echo SchoolSafePedagogyStyle(); ?></head>
<body><?php echo SchoolSafePedagogyHeader('dossier-annee.php',(int)($sid??0)); ?>
<main class="pf"><h1>Dossier de l’année</h1>
<?php if(!$child):?><p>Aucun enfant inscrit n’est lié à ce compte.</p><?php else:?>
<?php if(count($children)>1):?><div class="pf-chips"><?php foreach($children as $c):?><a class="<?=(int)$c['STUDENT_ID']===$sid?'on':''?>" href="dossier-annee.php?enfant=<?=(int)$c['STUDENT_ID']?>"><?=$e($c['FIRST_NAME'])?></a><?php endforeach;?></div><?php endif;?>
<?php if(count($years)>1):?><div class="pf-chips"><span class="pf-sub">Année :</span><?php foreach($years as $yy):?><a class="<?=$yy===$y?'on':''?>" href="dossier-annee.php?<?=$e(http_build_query(['enfant'=>$sid,'annee'=>$yy]))?>"><?=$e(SchoolSafePortfolioYearLabel($yy))?></a><?php endforeach;?></div><?php endif;?>
<section class="pf-card"><h2><?=$e($name)?> — <?=$e($classTitle)?> — <?=$e($yearLabel)?></h2>
<div class="pf-kpi"><div><b><?=count($works)?></b>travaux</div><div><b><?=$graded?></b>notes</div><div><b><?=$e($pct($summary['average']))?></b>moyenne des matières</div><div><b><?=(int)($presence['Absent']??0)+(int)($presence['Absence excusée']??0)?></b>absence(s)</div><div><b><?=(int)($presence['Retard']??0)?></b>retard(s)</div></div>
<div class="pf-chips"><a class="pf-btn" href="dossier-annee.php?<?=$e(http_build_query(['enfant'=>$sid,'annee'=>$y,'pdf'=>1]))?>">Télécharger le dossier (PDF)</a><a class="pf-btn light" href="dossier-annee.php?<?=$e(http_build_query(['enfant'=>$sid,'annee'=>$y,'pdf'=>1,'copies'=>1]))?>">PDF avec les copies corrigées</a><a class="pf-btn light" href="bulletin.php?eleve=<?=$sid?>">📄 Bulletin</a></div>
<p class="pf-sub">Le dossier se complète tout seul à chaque devoir et à chaque note. Il reste consultable les années suivantes.</p></section>
<section class="pf-card"><h2>Bilan de l’année</h2><?php if(!$summary['courses']):?><p class="pf-sub">Aucun travail enregistré pour l’instant.</p><?php else:?><?=$bilan?><?=$strengths?><?php endif;?><p class="pf-sub"><?=$e($presenceText)?></p></section>
<?php foreach($groups as $term=>$courses):?><section class="pf-card"><details open><summary><?=$e($term)?></summary>
<?php foreach($courses as $course=>$list):?><h3><?=$e($course)?></h3><table class="pf-table"><thead><tr><th>Travail</th><th class="hide-sm">Date</th><th>Note</th><th class="hide-sm">Appréciation</th><th></th></tr></thead><tbody>
<?php foreach($list as $w): $a=(int)$w['ASSIGNMENT_ID']; $p=SchoolSafePortfolioPercent($w['POINTS'],$w['MAXIMUM']); ?>
<tr><td><b><?=$e(($w['TYPE_TITLE']?$w['TYPE_TITLE'].' — ':'').$w['TITLE'])?></b></td><td class="hide-sm"><?=$e(SchoolSafeLetterheadDate((string)(($w['DUE_DATE']??'')?:($w['ASSIGNED_DATE']??'')),false))?></td>
<td class="<?=$p===null?'':($p<$threshold?'pf-ko':'pf-ok')?>"><?=$e(SchoolSafeGradeNoticeMark($w['POINTS'],$w['MAXIMUM'])?:'non noté')?></td><td class="hide-sm"><?=$e((string)$w['COMMENT'])?></td>
<td><div class="pf-chips"><a href="schoolsafe-homework.php?assignment_id=<?=$a?>&amp;student=<?=$sid?>&amp;download=1">Fiche</a><a href="note-devoir.php?devoir=<?=$a?>&amp;eleve=<?=$sid?>">Correction<?=$w['HAS_COPY']?' 📷':''?></a></div></td></tr>
<?php endforeach;?></tbody></table><?php endforeach;?></details></section><?php endforeach;?>
<?php endif;?>
<p class="pf-sub"><?=$e($schoolName)?> · SchoolSafe · Un enfant protégé, un parent informé</p></main><script src="pwa-client.js" defer></script></body></html>
