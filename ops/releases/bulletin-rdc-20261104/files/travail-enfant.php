<?php
// « Travail de mon enfant » (04/11, lot 6 valide) : une seule page pour le parent et l'eleve : aujourd'hui,
// devoirs (fiche a son nom, rendu en ligne), notes, presences, rattrapage, liens. Lecture seule.
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){http_response_code(405);exit;}
if(array_diff(array_keys($_GET),['enfant'])){http_response_code(400);exit('Requête invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeChildWork.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeLetterhead.fnc.php';
$school=(int)UserSchool();$year=(int)UserSyear();$profile=(string)User('PROFILE');
$allowed=in_array($profile,['parent','student'],true)&&$school>0&&$year>0&&AllowUse('Grades/StudentAssignments.php');
$get=function($sql){return DBGet($sql);};
$children=$allowed?SchoolSafeChildWorkChildren($profile,(int)User('STAFF_ID'),(int)($_SESSION['STUDENT_ID']??0),$school,$year,$get):[];
$child=SchoolSafeChildWorkPick($_GET['enfant']??(UserStudentID()?:0),$children);
if($child&&$profile==='parent'&&(int)UserStudentID()!==(int)$child['STUDENT_ID'])SetUserStudentID((int)$child['STUDENT_ID']);
$sid=$child?(int)$child['STUDENT_ID']:0;
$classes=$sid?SchoolSafeChildWorkClasses($sid,$school,$year,$get):[];
$homework=$sid?SchoolSafeChildWorkHomework($sid,$classes,$get):[];
$grades=$sid?SchoolSafeChildWorkGrades($sid,$school,$year,$get):[];
$attendance=$sid?SchoolSafeChildWorkAttendance($sid,$school,$year,$get):[];
$diary=$sid?SchoolSafeChildWorkDiary($classes,$get):[];
$remediation=$sid?(int)(DBGetOne("SELECT count(*) FROM schoolsafe_remediation_cases WHERE STUDENT_ID=".$sid." AND SCHOOL_ID=".$school." AND SYEAR=".$year." AND STATUS IN ('pending','approved','repeat')")?:0):0;
$schoolName=$allowed?(string)SchoolInfo('TITLE'):'';
while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(403);exit('Page réservée aux parents et aux élèves.');}
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$t=fn($v)=>ParseMLField((string)$v);
$tz=new DateTimeZone('Africa/Kinshasa');$today=(new DateTime('now',$tz))->format('Y-m-d');
$toDo=array_values(array_filter($homework,static function($h)use($today){return ($h['DUE_DATE']??'')>=$today&&!$h['HANDED_IN'];}));
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Travail de mon enfant | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<style>.ss-kid{display:grid;gap:14px;max-width:860px}.ss-card{border:1px solid #c9d8d2;border-radius:14px;padding:14px;background:#fff;display:grid;gap:8px}
.ss-card h2{margin:0;font-size:18px;color:#08766b;display:flex;gap:8px;align-items:center}.ss-kids{display:flex;gap:8px;flex-wrap:wrap}
.ss-kids a{border:1px solid #c9d8d2;border-radius:20px;padding:6px 14px;text-decoration:none;color:#08766b;background:#f4fbf8}.ss-kids a.on{background:#08766b;color:#fff;border-color:#08766b}
.ss-sum{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:8px}.ss-sum div{background:#f4fbf8;border-radius:10px;padding:10px;text-align:center}.ss-sum b{display:block;font-size:22px;color:#08766b}
.ss-item{border-top:1px solid #e3ebe8;padding-top:8px;display:grid;gap:3px}.ss-item:first-of-type{border-top:0;padding-top:0}.ss-sub{color:#556777;font-size:13px}
.ss-tag{font-size:12px;border-radius:10px;padding:2px 8px;display:inline-block}.t-ok{background:#e6f4ef;color:#08766b}.t-warn{background:#fff4e0;color:#8a4a10}.t-bad{background:#fbeaea;color:#a32d2d}
.ss-act{display:flex;gap:8px;flex-wrap:wrap}.ss-act a{background:#08766b;color:#fff;border-radius:8px;padding:8px 12px;text-decoration:none;font-size:14px}.ss-act a.light{background:#eef7f4;color:#08766b;border:1px solid #08766b}
.ss-empty{color:#556777;margin:0}</style></head>
<body><header><strong>SchoolSafe</strong><a href="Modules.php?modname=misc/Portal.php">Tableau de bord</a></header>
<main class="ss-kid"><h1>Travail de <?=$child?$e($child['FIRST_NAME']):'mon enfant'?></h1>
<?php if($child):?><div class="ss-act"><a href="dossier-annee.php?enfant=<?=$sid?>">📚 Dossier de l’année (tous les travaux, bilan, PDF)</a><a class="light" href="bulletin.php?eleve=<?=$sid?>">📄 Bulletin</a></div><?php endif;?>
<?php if(count($children)>1):?><nav class="ss-kids" aria-label="Choisir l’enfant"><?php foreach($children as $c):?><a href="travail-enfant.php?enfant=<?=(int)$c['STUDENT_ID']?>" class="<?=(int)$c['STUDENT_ID']===$sid?'on':''?>"><?=$e($c['FIRST_NAME'])?></a><?php endforeach;?></nav><?php endif;?>
<?php if(!$child):?><p class="ss-empty">Aucun enfant inscrit cette année dans cette école n’est rattaché à votre compte.</p><?php else:?>
<p class="ss-sub"><?=$e($child['FIRST_NAME'].' '.$child['LAST_NAME'])?><?=$child['LEVEL_TITLE']?' · '.$e($t($child['LEVEL_TITLE'])):''?> · <?=$e($schoolName)?> · <?=$e(ucfirst(SchoolSafeLetterheadDate($today)))?></p>
<section class="ss-sum" aria-label="En bref"><div><b><?=count($toDo)?></b>devoir(s) à faire</div><div><b><?=count($grades)?></b>note(s) ce mois</div>
<div><b><?=count($attendance)?></b>absence(s) ou retard(s)</div><div><b><?=$remediation?></b>rattrapage(s) en cours</div></section>

<section class="ss-card"><h2>📒 En classe ces derniers jours</h2>
<?php if(!$diary):?><p class="ss-empty">Le cahier de textes n’a pas encore été rempli.</p><?php endif;?>
<?php foreach($diary as $d):?><div class="ss-item"><b><?=$e($t($d['COURSE_TITLE']))?></b><span class="ss-sub"><?=$e(ucfirst(SchoolSafeLetterheadDate($d['SCHOOL_DATE'])))?></span>
<?php if($d['DONE']!==''):?><span>Fait en classe : <?=nl2br($e($d['DONE']))?></span><?php endif;?><?php if($d['TODO']!==''):?><span><b>À faire :</b> <?=nl2br($e($d['TODO']))?></span><?php endif;?></div><?php endforeach;?></section>

<section class="ss-card"><h2>📘 Devoirs</h2>
<?php if(!$homework):?><p class="ss-empty">Aucun devoir en ce moment.</p><?php endif;?>
<?php foreach($homework as $h): $late=($h['DUE_DATE']??'')<$today; $mark=SchoolSafeChildWorkMark($h['GRADE'],$h['POINTS']); ?>
<div class="ss-item"><b><?=$e(($h['TYPE_TITLE']?$h['TYPE_TITLE'].' — ':'').$h['TITLE'])?></b><span class="ss-sub"><?=$e($t($h['COURSE_TITLE']))?> · à rendre le <?=$e(SchoolSafeLetterheadDate($h['DUE_DATE']??'',false))?></span>
<span><?php if($mark!==''):?><span class="ss-tag t-ok">noté : <?=$e($mark)?></span><?php elseif($h['HANDED_IN']):?><span class="ss-tag t-ok">rendu en ligne</span><?php elseif($late):?><span class="ss-tag t-bad">date passée</span><?php else:?><span class="ss-tag t-warn">à faire</span><?php endif;?></span>
<div class="ss-act"><a href="schoolsafe-homework.php?assignment_id=<?=(int)$h['ASSIGNMENT_ID']?>&amp;student=<?=$sid?>&amp;download=1">Fiche à imprimer</a>
<?php if(($h['SUBMISSION']??'')==='Y'&&!$late):?><a class="light" href="Modules.php?modname=Grades/StudentAssignments.php&amp;assignment_id=<?=(int)$h['ASSIGNMENT_ID']?>">Rendre en ligne (photo)</a><?php endif;?></div></div>
<?php endforeach;?></section>

<section class="ss-card"><h2>📝 Notes récentes</h2>
<?php if(!$grades):?><p class="ss-empty">Aucune note ce mois-ci.</p><?php endif;?>
<?php foreach($grades as $g): $m=SchoolSafeChildWorkMark($g['POINTS'],$g['MAXIMUM']); $pct=(float)$g['MAXIMUM']>0&&(float)$g['POINTS']>=0?(float)$g['POINTS']*100/(float)$g['MAXIMUM']:null; ?>
<div class="ss-item"><b><?=$e(($g['TYPE_TITLE']?$g['TYPE_TITLE'].' — ':'').$g['TITLE'])?></b><span class="ss-sub"><?=$e($t($g['COURSE_TITLE']))?></span>
<span><span class="ss-tag <?=$pct===null?'t-ok':($pct>=50?'t-ok':'t-bad')?>"><?=$e($m)?></span><?=$g['COMMENT']?' · '.$e($g['COMMENT']):''?></span>
<div class="ss-act"><a href="note-devoir.php?devoir=<?=(int)$g['ASSIGNMENT_ID']?>&amp;eleve=<?=$sid?>">Voir la correction</a></div></div><?php endforeach;?></section>

<section class="ss-card"><h2>🕘 Présence (30 derniers jours)</h2>
<?php if(!$attendance):?><p class="ss-empty">Aucune absence ni aucun retard noté. 👏</p><?php endif;?>
<?php foreach($attendance as $a):?><div class="ss-item"><span><span class="ss-tag <?=SchoolSafeChildWorkCode((string)$a['TITLE'],(string)$a['STATE_CODE'],(string)$a['TYPE'])==='Retard'?'t-warn':'t-bad'?>"><?=$e(SchoolSafeChildWorkCode((string)$a['TITLE'],(string)$a['STATE_CODE'],(string)$a['TYPE']))?></span> <?=$e(ucfirst(SchoolSafeLetterheadDate($a['SCHOOL_DATE'])))?></span></div><?php endforeach;?>
<?php if($profile==='parent'):?><div class="ss-act"><a class="light" href="signaler-absence.php">Signaler une absence ou un retard</a></div><?php endif;?></section>

<?php if($remediation>0):?><section class="ss-card"><h2>🤝 Rattrapage</h2><p style="margin:0">Un accompagnement est en cours. Le plan, les séances et votre réponse se trouvent dans la page Rattrapage.</p><div class="ss-act"><a href="rattrapage.php">Voir le plan d’accompagnement</a></div></section><?php endif;?>

<section class="ss-card"><h2>📚 Ses classes</h2>
<?php if(!$classes):?><p class="ss-empty">Pas encore placé dans une classe.</p><?php endif;?>
<?php foreach($classes as $c):?><div class="ss-item"><b><?=$e($t($c['COURSE_TITLE']))?></b><span class="ss-sub">Enseignant : <?=$e(trim(($c['T_FIRST']??'').' '.($c['T_LAST']??'')))?></span></div><?php endforeach;?>
<div class="ss-act"><a class="light" href="Modules.php?modname=Grades/ReportCards.php">Bulletins</a><a class="light" href="Modules.php?modname=Scheduling/Schedule.php">Emploi du temps</a><a class="light" href="Modules.php?modname=SchoolSafe/Notifications.php">Notifications</a></div></section>
<?php endif;?></main></body></html>
