<?php
// Archives de l'ecole (04/11, lot 8 valide) : deposer un document par photo ou en PDF, le classer,
// le retrouver a tout moment. Les courriers de l'ecole apparaissent aussi dans la recherche.
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','POST'],true)){http_response_code(405);exit;}
if(array_diff(array_keys($_GET),['q','cat','annee','fichier','fiche'])){http_response_code(400);exit('Requête invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeArchive.fnc.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeLetterhead.fnc.php';
$school=(int)UserSchool();$year=(int)UserSyear();$staff=(int)User('STAFF_ID');
// Direction de l'ecole (meme droit que la configuration de l'ecole) : documents financiers et sensibles.
$allowed=$staff>0&&User('PROFILE')==='admin'&&$school>0&&$year>0&&AllowEdit('School_Setup/Configuration.php');
$cats=SchoolSafeArchiveCategories();
// Fichier : affiche dans le navigateur, jamais execute.
if(isset($_GET['fichier'])&&ctype_digit((string)$_GET['fichier'])){
    $f=$allowed?DBGet("SELECT REFERENCE,MIME,encode(CONTENT,'base64') AS B64 FROM schoolsafe_archive_documents WHERE ID=".(int)$_GET['fichier']." AND SCHOOL_ID=".$school):[];
    while(ob_get_level()>$level)ob_end_clean();
    if(!$f){http_response_code($allowed?404:403);exit('Document non accessible.');}
    $ext=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'][$f[1]['MIME']]??'bin';
    header('Content-Type: '.$f[1]['MIME']);header("Content-Security-Policy: sandbox; default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'");
    header('Content-Disposition: inline; filename="'.$f[1]['REFERENCE'].'.'.$ext.'"');
    echo base64_decode(str_replace(["\n","\r"],'',(string)$f[1]['B64']));exit;
}
if(empty($_SESSION['SchoolSafeScannerCsrf']))$_SESSION['SchoolSafeScannerCsrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['SchoolSafeScannerCsrf'];
$message='';$error='';$inTx=false;$newId=0;
$students=[];
if($allowed)foreach((array)DBGet("SELECT s.STUDENT_ID,s.FIRST_NAME,s.LAST_NAME FROM students s JOIN student_enrollment e ON e.STUDENT_ID=s.STUDENT_ID AND e.SCHOOL_ID=".$school." AND e.SYEAR=".$year." ORDER BY s.LAST_NAME,s.FIRST_NAME") as $r)$students[(int)$r['STUDENT_ID']]=$r;
$q=static function($x){return "'".DBEscapeString((string)$x)."'";};
if($allowed&&$_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf']))throw new RuntimeException('Session expirée : rechargez la page.');
        foreach($_POST as $v)if(!is_string($v))throw new RuntimeException('Formulaire invalide.');
        if(($_POST['action']??'')==='deposer'){
            $file=$_FILES['document']??null;
            if(!is_array($file)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file((string)$file['tmp_name']))
                throw new RuntimeException(($file['error']??0)===UPLOAD_ERR_INI_SIZE||($file['error']??0)===UPLOAD_ERR_FORM_SIZE?'Fichier trop lourd (8 Mo au plus).':'Choisissez une photo ou un PDF.');
            $v=SchoolSafeArchiveValidate($_POST,array_fill_keys(array_keys($students),true));
            [$mime,$bytes]=SchoolSafeArchiveFile((string)$file['tmp_name']);
            $sha=hash('sha256',$bytes);
            $twin=DBGet("SELECT REFERENCE FROM schoolsafe_archive_documents WHERE SCHOOL_ID=".$school." AND SHA256='".$sha."' AND WITHDRAWN_AT IS NULL LIMIT 1");
            if($twin)throw new RuntimeException('Ce document est déjà archivé sous la référence '.$twin[1]['REFERENCE'].'.');
            DBQuery('BEGIN');$inTx=true;
            DBQuery('LOCK TABLE schoolsafe_archive_documents IN SHARE ROW EXCLUSIVE MODE');
            $n=(int)(DBGetOne("SELECT count(*) FROM schoolsafe_archive_documents WHERE SCHOOL_ID=".$school." AND REFERENCE LIKE 'ARC-".$year."-%'")?:0)+1;
            $ref=SchoolSafeArchiveReference($year,$n);
            $row=DBGet("INSERT INTO schoolsafe_archive_documents(SCHOOL_ID,SYEAR,REFERENCE,CATEGORY,TITLE,ISSUER,DOC_DATE,AMOUNT,CURRENCY,TAGS,NOTES,STUDENT_ID,MIME,SIZE_BYTES,SHA256,CONTENT,CREATED_BY) VALUES("
                .$school.",".$year.",".$q($ref).",".$q($v['category']).",".$q($v['title']).",".$q($v['issuer']).",".($v['doc_date']?$q($v['doc_date']):'NULL').","
                .($v['amount']??'NULL').",".($v['currency']?$q($v['currency']):'NULL').",".$q($v['tags']).",".$q($v['notes']).",".($v['student']?:'NULL').","
                .$q($mime).",".strlen($bytes).",'".$sha."',decode('".base64_encode($bytes)."','base64'),".$staff.") RETURNING ID");
            DBQuery('COMMIT');$inTx=false;
            $newId=(int)$row[1]['ID'];
            $message='Document archivé sous la référence '.$ref.' ('.($mime==='application/pdf'?'PDF':'photo').', '.number_format(strlen($bytes)/1024,0,',',' ').' Ko).';
            // Lot 11 : le texte du document est lu sur le serveur, pour le retrouver par n'importe quel mot.
            $ocr=SchoolSafeArchiveOcr($mime,$bytes);
            if($ocr!==null){DBQuery("UPDATE schoolsafe_archive_documents SET OCR_TEXT=".$q($ocr).",OCR_AT=now() WHERE ID=".$newId);$message.=' Texte lu : '.count(preg_split('/\s+/u',$ocr)).' mots, retrouvables par la recherche.';}
        }elseif(($_POST['action']??'')==='lire'){
            // Lot 11 : lecture du texte d'un document deja archive.
            $doc=DBGet("SELECT ID,REFERENCE,MIME,encode(CONTENT,'base64') AS B64 FROM schoolsafe_archive_documents WHERE ID=".(int)($_POST['id']??0)." AND SCHOOL_ID=".$school);
            if(!$doc)throw new RuntimeException('Document introuvable.');
            $ocr=SchoolSafeArchiveOcr((string)$doc[1]['MIME'],base64_decode(str_replace(["
",""],'',(string)$doc[1]['B64'])));
            if($ocr===null)throw new RuntimeException('Aucun texte lisible dans ce document (photo floue, écriture à la main ou image sans texte).');
            DBQuery("UPDATE schoolsafe_archive_documents SET OCR_TEXT=".$q($ocr).",OCR_AT=now() WHERE ID=".(int)$doc[1]['ID']);
            $newId=(int)$doc[1]['ID'];
            $message='Texte de '.$doc[1]['REFERENCE'].' lu : '.count(preg_split('/\s+/u',$ocr)).' mots, retrouvables par la recherche.';
        }elseif(($_POST['action']??'')==='retirer'){
            $reason=trim((string)($_POST['reason']??''));
            if($reason===''||mb_strlen($reason)>300)throw new RuntimeException('Indiquez la raison du retrait (300 caractères au plus).');
            $done=DBGet("UPDATE schoolsafe_archive_documents SET WITHDRAWN_AT=now(),WITHDRAWN_BY=".$staff.",WITHDRAWN_REASON=".$q($reason)." WHERE ID=".(int)($_POST['id']??0)." AND SCHOOL_ID=".$school." AND WITHDRAWN_AT IS NULL RETURNING REFERENCE");
            if(!$done)throw new RuntimeException('Document introuvable.');
            $message='Document '.$done[1]['REFERENCE'].' retiré des recherches. Il reste conservé, avec la raison du retrait.';
        }else throw new RuntimeException('Action inconnue.');
    }catch(Throwable $failure){
        if($inTx)DBQuery('ROLLBACK');
        $error=$failure instanceof RuntimeException?$failure->getMessage():'Enregistrement impossible.';
    }
}
$text=is_string($_GET['q']??null)?$_GET['q']:'';$cat=is_string($_GET['cat']??null)&&isset($cats[$_GET['cat']])?$_GET['cat']:'';
$ayear=isset($_GET['annee'])&&ctype_digit((string)$_GET['annee'])?(int)$_GET['annee']:0;
$docs=$allowed?(array)DBGet("SELECT d.OCR_TEXT,d.ID,d.REFERENCE,d.CATEGORY,d.TITLE,d.ISSUER,d.DOC_DATE,d.AMOUNT,d.CURRENCY,d.TAGS,d.MIME,d.SIZE_BYTES,d.CREATED_AT,s.FIRST_NAME,s.LAST_NAME FROM schoolsafe_archive_documents d LEFT JOIN students s ON s.STUDENT_ID=d.STUDENT_ID"
    ." WHERE ".SchoolSafeArchiveWhere($school,$text,$cat,$ayear,$q)." ORDER BY COALESCE(d.DOC_DATE,d.CREATED_AT::date) DESC,d.ID DESC LIMIT 200"):[];
$letters=[];
if($allowed&&$cat===''&&($text!==''||$ayear>0)){
    $lw="l.SCHOOL_ID=".$school.($ayear>1900?" AND EXTRACT(YEAR FROM l.CREATED_AT)=".$ayear:'');
    foreach(preg_split('/\s+/u',trim(mb_substr($text,0,100)),-1,PREG_SPLIT_NO_EMPTY) as $w){$like=$q('%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$w).'%');$lw.=" AND (l.SUBJECT ILIKE ".$like." OR l.REFERENCE ILIKE ".$like." OR l.BODY ILIKE ".$like.")";}
    $letters=(array)DBGet("SELECT l.ID,l.REFERENCE,l.SUBJECT,l.CREATED_AT FROM schoolsafe_letters l WHERE ".$lw." ORDER BY l.CREATED_AT DESC LIMIT 50");
}
$counts=[];if($allowed)foreach((array)DBGet("SELECT CATEGORY,count(*) AS N FROM schoolsafe_archive_documents WHERE SCHOOL_ID=".$school." AND WITHDRAWN_AT IS NULL GROUP BY CATEGORY") as $c)$counts[$c['CATEGORY']]=(int)$c['N'];
$total=array_sum($counts);
$fiche=isset($_GET['fiche'])&&ctype_digit((string)$_GET['fiche'])&&$allowed?DBGet("SELECT d.*,s.FIRST_NAME,s.LAST_NAME,st.FIRST_NAME AS BY_FIRST,st.LAST_NAME AS BY_LAST FROM schoolsafe_archive_documents d LEFT JOIN students s ON s.STUDENT_ID=d.STUDENT_ID LEFT JOIN staff st ON st.STAFF_ID=d.CREATED_BY WHERE d.ID=".(int)$_GET['fiche']." AND d.SCHOOL_ID=".$school):[];
$fiche=$fiche[1]??null;
while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(403);exit('Page réservée à la direction de l’école.');}
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$money=fn($a,$c)=>$a===null||$a===''?'':number_format((float)$a,2,',',' ').' '.$c;
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Archives de l’école | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<style>.ss-ar{display:grid;gap:16px;max-width:1040px}.ss-box{border:1px solid #c9d8d2;border-radius:12px;padding:14px;display:grid;gap:10px;background:#fff}.ss-box h2{margin:0;font-size:19px;color:#08766b}
.ss-ar label{display:grid;gap:4px;font-size:14px}.ss-ar input[type=text],.ss-ar input[type=date],.ss-ar input[type=search],.ss-ar select,.ss-ar textarea{min-height:38px;font-size:15px;box-sizing:border-box;width:100%}
.ss-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}.ss-cats{display:flex;gap:8px;flex-wrap:wrap}.ss-cats a{border:1px solid #c9d8d2;border-radius:16px;padding:5px 11px;background:#f4fbf8;color:#08766b;text-decoration:none;font-size:14px}.ss-cats a.on{background:#08766b;color:#fff}
.ss-btn{min-height:44px;background:#08766b;color:#fff;border:0;border-radius:8px;font-size:15px;padding:0 16px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px}.ss-btn.light{background:#eef7f4;color:#08766b;border:1px solid #08766b}
.ss-drop{border:2px dashed #08766b;border-radius:12px;padding:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;background:#f4fbf8}.ss-drop input[type=file]{display:none}
.ss-table{width:100%;border-collapse:collapse;font-size:14px}.ss-table td,.ss-table th{border-bottom:1px solid #e3ebe8;padding:6px;text-align:left;vertical-align:top}.ss-sub{color:#556777;font-size:13px}.ss-ok{color:#08766b;font-weight:600}.ss-ko{color:#a32d2d;font-weight:600}
.ss-preview{max-width:100%;max-height:70vh;border:1px solid #c9d8d2;border-radius:8px}@media(max-width:640px){.ss-table .hide-sm{display:none}}</style></head>
<body><header><strong>SchoolSafe</strong><a href="courriers.php">Courriers</a></header>
<main class="ss-ar"><h1>Archives de l’école</h1>
<p class="ss-sub"><?=$total?> document(s) archivé(s). Chaque document est numéroté, classé et sauvegardé chaque nuit, chiffré, hors du serveur.</p>
<?php if($message):?><p class="ss-ok" role="status"><?=$e($message)?><?php if($newId):?> <a href="archives.php?fiche=<?=$newId?>">Voir la fiche</a><?php endif;?></p><?php endif;?>
<?php if($error):?><p class="ss-ko" role="alert"><?=$e($error)?></p><?php endif;?>
<?php if($fiche):?>
<section class="ss-box"><h2><?=$e($cats[$fiche['CATEGORY']][1].' '.$fiche['REFERENCE'].' — '.$fiche['TITLE'])?></h2>
<div class="ss-grid"><div><b>Catégorie</b><br><?=$e($cats[$fiche['CATEGORY']][0])?></div><div><b>Organisme / fournisseur</b><br><?=$e($fiche['ISSUER']?:'—')?></div>
<div><b>Date du document</b><br><?=$e($fiche['DOC_DATE']?SchoolSafeLetterheadDate($fiche['DOC_DATE'],false):'—')?></div><div><b>Montant</b><br><?=$e($money($fiche['AMOUNT'],$fiche['CURRENCY'])?:'—')?></div>
<div><b>Élève concerné</b><br><?=$e($fiche['FIRST_NAME']?$fiche['FIRST_NAME'].' '.$fiche['LAST_NAME']:'—')?></div><div><b>Archivé par</b><br><?=$e(trim(($fiche['BY_FIRST']??'').' '.($fiche['BY_LAST']??'')))?>, le <?=$e(SchoolSafeLetterheadDate(substr((string)$fiche['CREATED_AT'],0,10),false))?></div></div>
<?php if(trim((string)($fiche['OCR_TEXT']??''))!==''):?><details><summary>📄 Texte lu dans le document</summary><p style="white-space:pre-wrap;font-size:14px"><?=$e($fiche['OCR_TEXT'])?></p></details>
<?php else:?><form method="post" style="margin:0"><input type="hidden" name="csrf" value="<?=$e($csrf)?>"><input type="hidden" name="action" value="lire"><input type="hidden" name="id" value="<?=(int)$fiche['ID']?>"><button class="ss-btn light" type="submit">📄 Lire le texte du document</button> <span class="ss-sub">pour le retrouver par n’importe quel mot</span></form><?php endif;?>
<?php if($fiche['TAGS']):?><p style="margin:0"><b>Mots-clés :</b> <?=$e($fiche['TAGS'])?></p><?php endif;?><?php if($fiche['NOTES']):?><p style="margin:0"><b>Note :</b> <?=nl2br($e($fiche['NOTES']))?></p><?php endif;?>
<?php if($fiche['WITHDRAWN_AT']):?><p class="ss-ko">Retiré le <?=$e(substr((string)$fiche['WITHDRAWN_AT'],0,10))?> : <?=$e($fiche['WITHDRAWN_REASON'])?></p><?php endif;?>
<?php if($fiche['MIME']!=='application/pdf'):?><img class="ss-preview" src="archives.php?fichier=<?=(int)$fiche['ID']?>" alt="<?=$e($fiche['TITLE'])?>"><?php endif;?>
<div style="display:flex;gap:8px;flex-wrap:wrap"><a class="ss-btn" href="archives.php?fichier=<?=(int)$fiche['ID']?>" target="_blank" rel="noopener">Ouvrir le document</a><a class="ss-btn light" href="archives.php">Retour</a></div>
<?php if(!$fiche['WITHDRAWN_AT']):?><form method="post" class="ss-grid"><input type="hidden" name="csrf" value="<?=$e($csrf)?>"><input type="hidden" name="action" value="retirer"><input type="hidden" name="id" value="<?=(int)$fiche['ID']?>">
<label>Retirer des recherches (le document reste conservé) — raison<input type="text" name="reason" maxlength="300" required placeholder="ex. doublon, document erroné"></label><div style="align-self:end"><button class="ss-btn light" type="submit">Retirer</button></div></form><?php endif;?>
</section>
<?php endif;?>
<section class="ss-box"><h2>Archiver un document</h2>
<form method="post" enctype="multipart/form-data" id="ss-deposit"><input type="hidden" name="csrf" value="<?=$e($csrf)?>"><input type="hidden" name="action" value="deposer"><input type="hidden" name="MAX_FILE_SIZE" value="8388608">
<div class="ss-drop"><label class="ss-btn" style="display:inline-flex">📷 Prendre une photo<input type="file" name="photo_capture" accept="image/*" capture="environment" data-target></label>
<label class="ss-btn light" style="display:inline-flex">📄 Choisir un PDF ou une image<input type="file" name="document" accept="application/pdf,image/jpeg,image/png" data-target></label>
<span class="ss-sub" id="ss-file">Aucun fichier choisi.</span></div>
<div class="ss-grid">
<label>Catégorie<select name="category" required><option value="">— choisir —</option><?php foreach($cats as $k=>$c):?><option value="<?=$e($k)?>"><?=$e($c[1].' '.$c[0])?></option><?php endforeach;?></select></label>
<label>Titre<input type="text" name="title" maxlength="200" required placeholder="ex. Police d’assurance incendie 2026"></label>
<label>Organisme ou fournisseur<input type="text" name="issuer" maxlength="200" placeholder="ex. SONAS, Librairie Saint-Paul"></label>
<label>Date du document<input type="date" name="doc_date"></label>
<label>Montant (facultatif)<input type="text" name="amount" inputmode="decimal" placeholder="ex. 150000"></label>
<label>Devise<select name="currency"><option value="CDF">CDF</option><option value="USD">USD</option><option value="EUR">EUR</option></select></label>
<label>Élève concerné (facultatif)<select name="student"><option value="0">—</option><?php foreach($students as $id=>$s):?><option value="<?=$id?>"><?=$e($s['LAST_NAME'].' '.$s['FIRST_NAME'])?></option><?php endforeach;?></select></label>
<label>Mots-clés<input type="text" name="tags" maxlength="300" placeholder="ex. tableaux, mobilier, salle 2"></label></div>
<label>Note (facultatif)<textarea name="notes" maxlength="2000" rows="2"></textarea></label>
<div><button class="ss-btn" type="submit">Archiver</button> <span class="ss-sub">Photo ou PDF, 8 Mo au plus. Une photo est allégée automatiquement en gardant sa lisibilité.</span></div></form></section>
<section class="ss-box"><h2>Retrouver un document</h2>
<form method="get" class="ss-grid"><label>Rechercher<input type="search" name="q" value="<?=$e($text)?>" placeholder="titre, organisme, mot-clé, référence"></label>
<label>Année<input type="text" name="annee" inputmode="numeric" value="<?=$ayear?:''?>" placeholder="ex. 2026"></label><input type="hidden" name="cat" value="<?=$e($cat)?>"><div style="align-self:end"><button class="ss-btn" type="submit">Rechercher</button></div></form>
<div class="ss-cats"><a class="<?=$cat===''?'on':''?>" href="archives.php?<?=$e(http_build_query(['q'=>$text]+($ayear?['annee'=>$ayear]:[])))?>">Tout (<?=$total?>)</a><?php foreach($cats as $k=>$c):?><a class="<?=$k===$cat?'on':''?>" href="archives.php?<?=$e(http_build_query(['cat'=>$k,'q'=>$text]+($ayear?['annee'=>$ayear]:[])))?>"><?=$e($c[1].' '.$c[0])?> (<?=$counts[$k]??0?>)</a><?php endforeach;?></div>
<?php if(!$docs&&!$letters):?><p class="ss-sub">Aucun document trouvé.</p><?php else:?>
<table class="ss-table"><thead><tr><th>Référence</th><th>Document</th><th class="hide-sm">Date</th><th class="hide-sm">Montant</th><th></th></tr></thead><tbody>
<?php foreach($docs as $d):?><tr><td><b><?=$e($d['REFERENCE'])?></b><br><span class="ss-sub"><?=$e($cats[$d['CATEGORY']][1].' '.$cats[$d['CATEGORY']][0])?></span></td>
<td><?=$e($d['TITLE'])?><?php $snip=SchoolSafeArchiveSnippet($d['OCR_TEXT']??'',$text); if($snip!==''):?><br><span class="ss-sub">📄 Dans le texte : « <?=$e($snip)?> »</span><?php endif;?><?=$d['ISSUER']?'<br><span class="ss-sub">'.$e($d['ISSUER']).'</span>':''?><?=$d['FIRST_NAME']?'<br><span class="ss-sub">Élève : '.$e($d['FIRST_NAME'].' '.$d['LAST_NAME']).'</span>':''?></td>
<td class="hide-sm"><?=$e($d['DOC_DATE']?SchoolSafeLetterheadDate($d['DOC_DATE'],false):'—')?></td><td class="hide-sm"><?=$e($money($d['AMOUNT'],$d['CURRENCY']))?></td>
<td><div class="ss-cats"><a href="archives.php?fiche=<?=(int)$d['ID']?>">Fiche</a><a href="archives.php?fichier=<?=(int)$d['ID']?>" target="_blank" rel="noopener"><?=$d['MIME']==='application/pdf'?'PDF':'Photo'?></a></div></td></tr><?php endforeach;?>
<?php foreach($letters as $l):?><tr><td><b><?=$e($l['REFERENCE'])?></b><br><span class="ss-sub">✉️ Courrier de l’école</span></td><td><?=$e($l['SUBJECT'])?></td><td class="hide-sm"><?=$e(SchoolSafeLetterheadDate(substr((string)$l['CREATED_AT'],0,10),false))?></td><td class="hide-sm"></td>
<td><div class="ss-cats"><a href="courriers.php?voir=<?=(int)$l['ID']?>" target="_blank" rel="noopener">Voir</a></div></td></tr><?php endforeach;?>
</tbody></table><?php endif;?></section></main>
<script>
(function(){var form=document.getElementById('ss-deposit');if(!form)return;var main=form.querySelector('input[name=document]'),note=document.getElementById('ss-file');
// Photo du telephone : allegee et redressee dans le navigateur avant l'envoi (lisible, legere).
async function shrink(file){if(!/^image\/(jpeg|png)/.test(file.type)||!window.createImageBitmap)return file;
 try{var bmp=await createImageBitmap(file,{imageOrientation:'from-image'});var s=Math.min(1,2200/Math.max(bmp.width,bmp.height));var c=document.createElement('canvas');c.width=Math.round(bmp.width*s);c.height=Math.round(bmp.height*s);
 var x=c.getContext('2d');x.fillStyle='#fff';x.fillRect(0,0,c.width,c.height);x.drawImage(bmp,0,0,c.width,c.height);
 var blob=await new Promise(function(r){c.toBlob(r,'image/jpeg',0.85);});return blob?new File([blob],(file.name||'photo').replace(/\.\w+$/,'')+'.jpg',{type:'image/jpeg'}):file;}catch(e){return file;}}
form.querySelectorAll('[data-target]').forEach(function(input){input.addEventListener('change',async function(){var f=input.files[0];if(!f)return;note.textContent='Préparation…';var ready=await shrink(f);
 try{var dt=new DataTransfer();dt.items.add(ready);main.files=dt.files;}catch(e){if(input!==main){note.textContent='Choisissez le fichier avec « Choisir un PDF ou une image ».';return;}}
 if(input!==main)input.value='';note.textContent=ready.name+' — '+Math.round(ready.size/1024)+' Ko';});});
})();
</script></body></html>
