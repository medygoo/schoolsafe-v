<?php
// « Synthèse du bulletin » (04/11, etape C validee) : le titulaire voit la classe calculee (notes finales
// francais + anglais, totaux, pourcentages, places, notes manquantes), donne l'application et la conduite,
// la decision de fin d'annee, puis propose ; la direction valide (le trimestre est alors verrouille) ou rouvre.
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','POST'],true)){http_response_code(405);exit;}
if(array_diff(array_keys($_GET),['classe','trimestre','eleve'])){http_response_code(400);exit('Requête invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeBulletin.fnc.php';
$school=(int)UserSchool();$year=(int)UserSyear();$staff=(int)User('STAFF_ID');$profile=(string)User('PROFILE');
$isDirection=$profile==='admin'&&$school>0&&(AllowEdit('School_Setup/Configuration.php')||AllowUse('Grades/ReportCards.php'));
$get=function($sql){return DBGet($sql);};
$classes=[];
if($school>0&&$year>0&&($isDirection||$profile==='teacher'))
    foreach((array)DBGet("SELECT cp.COURSE_PERIOD_ID,cp.TEACHER_ID,c.TITLE FROM course_periods cp JOIN courses c ON c.COURSE_ID=cp.COURSE_ID WHERE cp.SCHOOL_ID=".$school." AND cp.SYEAR=".$year
        ." AND EXISTS (SELECT 1 FROM schoolsafe_class_branches b WHERE b.CLASS_COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID)".($isDirection?'':" AND cp.TEACHER_ID=".$staff)." ORDER BY c.TITLE") as $r)$classes[(int)$r['COURSE_PERIOD_ID']]=$r;
$allowed=$staff>0&&($isDirection||$classes);
if(empty($_SESSION['SchoolSafeScannerCsrf']))$_SESSION['SchoolSafeScannerCsrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['SchoolSafeScannerCsrf'];
$classCp=isset($_GET['classe'])&&ctype_digit((string)$_GET['classe'])&&isset($classes[(int)$_GET['classe']])?(int)$_GET['classe']:(int)(array_key_first($classes)??0);
$terms=$allowed?SchoolSafeBulletinTerms($school,$year,$get):[];
$fy=$allowed?(int)(DBGetOne("SELECT MARKING_PERIOD_ID FROM school_marking_periods WHERE SCHOOL_ID=".$school." AND SYEAR=".$year." AND MP='FY' LIMIT 1")?:0):0;
$termReq=$_GET['trimestre']??'';
$term=$termReq==='annee'?'annee':(ctype_digit((string)$termReq)&&isset($terms[(int)$termReq])?(int)$termReq:(int)(array_key_first($terms)??0));
$mp=$term==='annee'?$fy:(int)$term;
$isTitulaire=$classCp&&(int)($classes[$classCp]['TEACHER_ID']??0)===$staff;
$message='';$error='';$inTx=false;
$students=$classCp?SchoolSafeBulletinStudents($classCp,$get):[];
if($allowed&&$classCp&&$mp&&$_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf']))throw new RuntimeException('Session expirée : rechargez la page.');
        $action=is_string($_POST['action']??null)?$_POST['action']:'';
        $status=SchoolSafeBulletinStatus($classCp,$get);
        $q=static function($v){return $v===null||$v===''?'NULL':"'".DBEscapeString((string)$v)."'";};
        $upsert=static function(int $sid,array $set)use($school,$year,$classCp,$mp,$staff){
            $cols=implode(',',array_keys($set));$vals=implode(',',array_values($set));
            $upd=implode(',',array_map(static function($k){return $k.'=EXCLUDED.'.$k;},array_keys($set)));
            DBQuery("INSERT INTO schoolsafe_bulletin_status(SCHOOL_ID,SYEAR,CLASS_COURSE_PERIOD_ID,STUDENT_ID,MARKING_PERIOD_ID,UPDATED_BY,".$cols.") VALUES(".$school.",".$year.",".$classCp.",".$sid.",".$mp.",".$staff.",".$vals.")"
                ." ON CONFLICT(CLASS_COURSE_PERIOD_ID,STUDENT_ID,MARKING_PERIOD_ID) DO UPDATE SET UPDATED_BY=EXCLUDED.UPDATED_BY,UPDATED_AT=now(),".$upd);
        };
        DBQuery('BEGIN');$inTx=true;
        if($action==='enregistrer'||$action==='proposer'){
            if(!$isTitulaire&&!$isDirection)throw new RuntimeException('Seul le titulaire de la classe ou la direction peut remplir la synthèse.');
            $decisions=SchoolSafeBulletinDecisions();
            foreach($students as $sid=>$name){
                if(($status[$sid][$mp]['STATUS']??'')==='valide'&&!$isDirection)continue;
                $f=static function($k)use($sid){$v=$_POST[$k][$sid]??'';return is_string($v)?trim($v):'';};
                $app=mb_substr($f('application'),0,40);$con=mb_substr($f('conduite'),0,40);
                $set=['APPLICATION'=>$q($app),'CONDUITE'=>$q($con)];
                if($term==='annee'){
                    $d=$f('decision');if($d!==''&&!isset($decisions[$d]))throw new RuntimeException('Décision inconnue pour '.$name.'.');
                    $set+=['DECISION'=>$q($d),'DECISION_NOTE'=>$q(mb_substr($f('decision_note'),0,300)),'TITULAIRE_NOTE'=>$q(mb_substr($f('titulaire_note'),0,600))];
                }
                if($action==='proposer'&&($status[$sid][$mp]['STATUS']??'brouillon')==='brouillon')$set+=['STATUS'=>"'propose'",'PROPOSED_BY'=>$staff,'PROPOSED_AT'=>'now()'];
                $upsert($sid,$set);
            }
            $message=$action==='proposer'?'Synthèse proposée à la direction.':'Synthèse enregistrée.';
        }elseif($action==='valider'||$action==='rouvrir'){
            if(!$isDirection)throw new RuntimeException('Seule la direction valide ou rouvre un bulletin.');
            foreach($students as $sid=>$name)$upsert($sid,$action==='valider'?['STATUS'=>"'valide'",'VALIDATED_BY'=>$staff,'VALIDATED_AT'=>'now()']:['STATUS'=>"'brouillon'",'VALIDATED_BY'=>'NULL','VALIDATED_AT'=>'NULL']);
            $message=$action==='valider'?'Bulletins validés : les notes de ce trimestre sont verrouillées.':'Bulletins rouverts : le titulaire et les enseignants peuvent de nouveau modifier.';
        }else throw new RuntimeException('Action inconnue.');
        DBQuery('COMMIT');$inTx=false;
    }catch(Throwable $failure){
        if($inTx)DBQuery('ROLLBACK');
        $error=$failure instanceof RuntimeException?$failure->getMessage():'Enregistrement impossible.';
    }
}
$branches=$classCp?SchoolSafeBulletinBranches($classCp,$get):[];
$calc=$classCp?SchoolSafeBulletinCompute($branches,$students,SchoolSafeBulletinMarks($classCp,$get),$terms,SchoolSafeBulletinLangMode($school,$get)):['slots'=>[],'students'=>[],'count'=>0];
$status=$classCp?SchoolSafeBulletinStatus($classCp,$get):[];
$rawMarks=$classCp?SchoolSafeBulletinMarks($classCp,$get):[];
while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(403);exit('Page réservée aux titulaires et à la direction.');}
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$n=fn($v)=>SchoolSafeBulletinNumber($v);
$key=$term==='annee'?'TG':'T:'.$term;
$detail=isset($_GET['eleve'])&&ctype_digit((string)$_GET['eleve'])&&isset($calc['students'][(int)$_GET['eleve']])?(int)$_GET['eleve']:0;
$labels=['brouillon'=>'Brouillon','propose'=>'Proposé','valide'=>'Validé 🔒'];
$mentions=['Élite','Très bien','Bien','Assez bien','Médiocre','Mauvais'];
$readOnly=static function($sid)use($status,$mp,$isDirection,$isTitulaire){return !$isDirection&&(!$isTitulaire||($status[$sid][$mp]['STATUS']??'')==='valide');};
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Synthèse du bulletin | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<style>.sb{display:grid;gap:14px;max-width:1200px}.sb-box{border:1px solid #c9d8d2;border-radius:12px;padding:14px;display:grid;gap:10px;background:#fff}.sb-box h2{margin:0;font-size:19px;color:#08766b}
.sb-chips{display:flex;gap:6px;flex-wrap:wrap}.sb-chips a{border:1px solid #c9d8d2;border-radius:16px;padding:5px 11px;background:#f4fbf8;color:#08766b;text-decoration:none;font-size:14px}.sb-chips a.on{background:#08766b;color:#fff}
.sb-wrap{overflow-x:auto}.sb-table{border-collapse:collapse;font-size:14px;width:100%}.sb-table th,.sb-table td{border:1px solid #e3ebe8;padding:5px;text-align:center;vertical-align:middle}.sb-table th{background:#f4fbf8;font-size:12px}.sb-table td.l{text-align:left}
.sb-table input,.sb-table select{min-height:32px;font-size:14px;max-width:130px}.sb-btn{min-height:44px;background:#08766b;color:#fff;border:0;border-radius:8px;font-size:15px;padding:0 16px;cursor:pointer}.sb-btn.light{background:#eef7f4;color:#08766b;border:1px solid #08766b}
.sb-ok{color:#08766b;font-weight:600}.sb-ko{color:#a32d2d;font-weight:600}.sb-sub{color:#556777;font-size:13px}.sb-miss{color:#a86200;font-weight:600}</style></head>
<body><header><strong>SchoolSafe</strong><a href="notes-bulletin.php">Notes du bulletin</a></header>
<main class="sb"><h1>Synthèse du bulletin</h1>
<?php if($message):?><p class="sb-ok" role="status"><?=$e($message)?></p><?php endif;?><?php if($error):?><p class="sb-ko" role="alert"><?=$e($error)?></p><?php endif;?>
<?php if(!$classes):?><p>Aucune classe avec des branches. La direction les crée dans « Branches et maxima ».</p><?php else:?>
<div class="sb-chips"><?php foreach($classes as $id=>$c):?><a class="<?=$id===$classCp?'on':''?>" href="synthese-bulletin.php?<?=$e(http_build_query(['classe'=>$id,'trimestre'=>$term]))?>"><?=$e(ParseMLField((string)$c['TITLE']))?></a><?php endforeach;?></div>
<div class="sb-chips"><?php foreach($terms as $t):?><a class="<?=$term===$t['id']?'on':''?>" href="synthese-bulletin.php?<?=$e(http_build_query(['classe'=>$classCp,'trimestre'=>$t['id']]))?>"><?=$e($t['title'])?></a><?php endforeach;?><a class="<?=$term==='annee'?'on':''?>" href="synthese-bulletin.php?<?=$e(http_build_query(['classe'=>$classCp,'trimestre'=>'annee']))?>">Année et décision</a></div>
<section class="sb-box"><h2><?=$e($term==='annee'?'Année — total général et décision':($terms[$term]['title']??''))?> · <?=count($students)?> élève(s)</h2>
<p class="sb-sub"><?=$isDirection?'Direction : vous pouvez tout modifier, valider (verrouille les notes) ou rouvrir.':($isTitulaire?'Titulaire : remplissez l’application, la conduite'.($term==='annee'?' et la décision':'').', puis proposez à la direction.':'Lecture seule.')?> Les totaux, pourcentages et places se calculent à partir des notes des enseignants.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=$e($csrf)?>">
<div class="sb-wrap"><table class="sb-table"><thead><tr><th>Élève</th><th>Total</th><th>%</th><th>Place</th><th>Manquantes</th><th>Application</th><th>Conduite</th><?php if($term==='annee'):?><th>Décision</th><th>Précision</th><th>Mot du titulaire</th><?php endif;?><th>Statut</th></tr></thead><tbody>
<?php foreach($calc['students'] as $sid=>$r): $t=$r['totals'][$key]??[0,0,false]; $st=$status[$sid][$mp]??[]; $ro=$readOnly($sid); $miss=$term==='annee'?array_sum($r['missing']):($r['missing'][$term]??0); ?>
<tr><td class="l"><a href="synthese-bulletin.php?<?=$e(http_build_query(['classe'=>$classCp,'trimestre'=>$term,'eleve'=>$sid]))?>#detail"><?=$e($r['name'])?></a></td>
<td><?=$t[2]?$e($n($t[0]).' / '.$n($t[1])):'—'?></td><td><?=$e($r['pct'][$key]!==null?$n($r['pct'][$key]).' %':'—')?></td><td><?=isset($r['rank'][$key])?(int)$r['rank'][$key].' / '.$calc['count']:'—'?></td>
<td class="<?=$miss?'sb-miss':''?>"><?=(int)$miss?></td>
<td><input type="text" name="application[<?=$sid?>]" value="<?=$e($st['APPLICATION']??'')?>" maxlength="40" list="sb-mentions"<?=$ro?' disabled':''?>></td>
<td><input type="text" name="conduite[<?=$sid?>]" value="<?=$e($st['CONDUITE']??'')?>" maxlength="40" list="sb-mentions"<?=$ro?' disabled':''?>></td>
<?php if($term==='annee'):?><td><select name="decision[<?=$sid?>]"<?=$ro?' disabled':''?>><option value="">—</option><?php foreach(SchoolSafeBulletinDecisions() as $k=>$lab):?><option value="<?=$e($k)?>"<?=($st['DECISION']??'')===$k?' selected':''?>><?=$e($lab)?></option><?php endforeach;?></select></td>
<td><input type="text" name="decision_note[<?=$sid?>]" value="<?=$e($st['DECISION_NOTE']??'')?>" maxlength="300" placeholder="ex. repêchage en calcul"<?=$ro?' disabled':''?>></td>
<td><input type="text" name="titulaire_note[<?=$sid?>]" value="<?=$e($st['TITULAIRE_NOTE']??'')?>" maxlength="600"<?=$ro?' disabled':''?>></td><?php endif;?>
<td><?=$e($labels[$st['STATUS']??'brouillon'])?></td></tr><?php endforeach;?>
</tbody></table></div>
<datalist id="sb-mentions"><?php foreach($mentions as $m):?><option value="<?=$e($m)?>"><?php endforeach;?></datalist>
<div style="display:flex;gap:8px;flex-wrap:wrap"><?php if($isTitulaire||$isDirection):?><button class="sb-btn light" name="action" value="enregistrer" type="submit">Enregistrer</button><button class="sb-btn" name="action" value="proposer" type="submit">Proposer à la direction</button><?php endif;?>
<?php if($isDirection):?><button class="sb-btn" name="action" value="valider" type="submit">Valider (verrouiller)</button><button class="sb-btn light" name="action" value="rouvrir" type="submit">Rouvrir</button><?php endif;?></div></form></section>
<?php if($detail): $r=$calc['students'][$detail]; ?>
<section class="sb-box" id="detail"><h2>Détail — <?=$e($r['name'])?></h2>
<div class="sb-wrap"><table class="sb-table"><thead><tr><th>Branche</th><?php foreach($calc['slots'] as $k=>$s): if($term!=='annee'&&$s['term']!==$term) continue; ?><th><?=$e($s['label'])?></th><?php endforeach;?></tr></thead><tbody>
<?php foreach($branches as $b): $bcp=(int)$b['COURSE_PERIOD_ID']; ?><tr><td class="l"><?=$e($b['TITLE'])?></td>
<?php foreach($calc['slots'] as $k=>$s): if($term!=='annee'&&$s['term']!==$term) continue; $c=$r['cells'][$bcp][$k]??[null,0]; $raw=$rawMarks[$bcp][$detail][$k]??[]; ?>
<td><b><?=$c[0]===null?'—':$e($n($c[0]))?></b><span class="sb-sub">/<?=$e($n($c[1]))?></span><?php if(isset($raw['fr'])||isset($raw['en'])):?><br><span class="sb-sub"><?=isset($raw['fr'])?'FR '.$e($n($raw['fr'])):''?><?=isset($raw['fr'],$raw['en'])?' · ':''?><?=isset($raw['en'])?'EN '.$e($n($raw['en'])):''?></span><?php endif;?></td>
<?php endforeach;?></tr><?php endforeach;?>
<tr><td class="l"><b>Totaux</b></td><?php foreach($calc['slots'] as $k=>$s): if($term!=='annee'&&$s['term']!==$term) continue; $t=$r['totals'][$k]??[0,0,false]; ?><td><b><?=$t[2]?$e($n($t[0])):'—'?></b><span class="sb-sub">/<?=$e($n($t[1]))?></span><br><span class="sb-sub"><?=$r['pct'][$k]!==null?$e($n($r['pct'][$k])).' %':''?><?=isset($r['rank'][$k])?' · '.(int)$r['rank'][$k].'e':''?></span></td><?php endforeach;?></tr>
</tbody></table></div></section><?php endif;?>
<?php endif;?></main></body></html>
