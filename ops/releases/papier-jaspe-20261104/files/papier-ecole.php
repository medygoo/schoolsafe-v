<?php
// Papier de l'ecole (04/11, lot 3 valide) : disposition par defaut, ligne du ministere et de la province,
// apercu ; le logo, la devise, l'adresse et les contacts viennent de la fiche de l'ecole.
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','POST'],true)){http_response_code(405);exit;}
if(array_diff(array_keys($_GET),['apercu','style'])){http_response_code(400);exit('Requête invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeLetterhead.fnc.php';
$school=(int)UserSchool();$actor=(int)User('STAFF_ID');
// Meme droit que « Preparer l'annee » : administrateur autorise a configurer l'ecole.
$allowed=$actor&&User('PROFILE')==='admin'&&$school>0&&AllowEdit('School_Setup/Configuration.php');
if(empty($_SESSION['SchoolSafeScannerCsrf']))$_SESSION['SchoolSafeScannerCsrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['SchoolSafeScannerCsrf'];
$message='';$error='';$proposal=null;
if($allowed&&$_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf']))throw new RuntimeException('Session expirée : rechargez la page.');
        $post=array_map(static function($v){return is_string($v)?$v:'';},$_POST);
        if(($post['action']??'')==='jaspe'){
            // JASPE propose une disposition (reglages permis seulement) ; rien n'est enregistre avant validation.
            if(trim($post['demande']??'')==='')throw new RuntimeException('Écrivez votre demande à JASPE.');
            $now=time();$hits=array_values(array_filter($_SESSION['jaspe_ai_hits']??[],static function($h)use($now){return is_int($h)&&$now-$h<60;}));
            $ask=count($hits)<10?SchoolSafeLetterheadJaspeTransport():null;
            if($ask)$_SESSION['jaspe_ai_hits']=array_merge($hits,[$now]);
            $current=SchoolSafeLetterheadValidate($post);
            $proposal=SchoolSafeLetterheadAsk(mb_substr($post['demande'],0,300),$current['layout'],SchoolSafeLetterheadStyleDecode($current['style']),$ask);
            $proposal+=['ministry'=>$current['ministry'],'province'=>$current['province']];
            throw new RuntimeException('');
        }
        $valid=SchoolSafeLetterheadValidate($post);
        DBQuery('BEGIN');
        foreach(['SCHOOLSAFE_DOC_LAYOUT'=>$valid['layout'],'SCHOOLSAFE_DOC_MINISTRY'=>$valid['ministry'],'SCHOOLSAFE_DOC_PROVINCE'=>$valid['province'],'SCHOOLSAFE_DOC_STYLE'=>$valid['style']] as $title=>$value){
            $sql="'".DBEscapeString($value)."'";
            $done=DBGet(DBQuery("UPDATE config SET CONFIG_VALUE=".$sql.",UPDATED_AT=CURRENT_TIMESTAMP WHERE SCHOOL_ID=".$school." AND TITLE='".$title."' RETURNING TITLE"));
            if(!$done)DBQuery("INSERT INTO config(SCHOOL_ID,TITLE,CONFIG_VALUE) VALUES(".$school.",'".$title."',".$sql.")");
        }
        DBQuery('COMMIT');
        $message='Papier de l’école enregistré. Les prochains documents l’utilisent ; chaque enseignant peut encore choisir une autre disposition.';
    }catch(Throwable $failure){$error=$failure instanceof RuntimeException?$failure->getMessage():'Enregistrement impossible.';}
}
$identity=$allowed?SchoolSafeLetterheadIdentity($school):[];
if($proposal){$identity['layout']=$proposal['layout'];$identity['style']=SchoolSafeLetterheadStyleEncode($proposal['style']);$identity['ministry']=$proposal['ministry'];$identity['province']=$proposal['province'];
    $message=$proposal['by']==='jaspe'?'JASPE propose cette disposition. Regardez l’aperçu puis enregistrez si elle vous convient.':($proposal['by']==='consignes'?'Disposition proposée par les consignes simples (JASPE indisponible). Regardez l’aperçu puis enregistrez.':'Demande non comprise : rien n’a changé. Essayez par exemple « logo à droite, titre centré ».');}
while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(403);exit('Permission requise : administrateur autorisé à configurer l’école.');}
$layouts=SchoolSafeLetterheadLayouts();
$e='SchoolSafeLetterheadEscape';
if(isset($_GET['apercu'])){
    $layout=is_string($_GET['apercu'])&&isset($layouts[$_GET['apercu']])?$_GET['apercu']:$identity['layout'];
    $previewStyle=isset($_GET['style'])&&is_string($_GET['style'])?SchoolSafeLetterheadStyleEncode(SchoolSafeLetterheadStyleDecode($_GET['style'])):$identity['style'];
    header('Content-Type: text/html; charset=UTF-8');
    echo SchoolSafeLetterheadPage($identity,$layout,['title'=>'Interrogation — Exemple de mise en page',
        'meta'=>[['Matière','Mathématiques'],['Enseignant','Nom de l’enseignant'],['Donné le',SchoolSafeLetterheadDate(date('Y-m-d'))]],
        'body'=>'<p style="text-align:center"><strong>Lisez attentivement chaque question.</strong></p><ol><li>Calculez 3/4 + 1/8.</li><li>Recopiez et complétez le tableau.</li></ol>'
            .'<table><tr><th>Fraction</th><th>Décimal</th></tr><tr><td>1/2</td><td></td></tr><tr><td>3/4</td><td></td></tr></table>',
        'student_box'=>$layout==='devoir'||$layout==='officielle','points'=>'20','answer_lines'=>$layout==='devoir'?6:0,'style'=>$previewStyle]);
    exit;
}
$missing=[];
foreach(['logo'=>'logo','address'=>'adresse','phone'=>'téléphone','email'=>'e-mail','slogan'=>'devise'] as $key=>$label)if(trim((string)($identity[$key]??''))==='')$missing[]=$label;
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Papier de l’école | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<style>.ss-pe{display:grid;gap:14px;max-width:980px}.ss-pe fieldset{border:1px solid #c9d8d2;border-radius:10px;padding:12px 14px;display:grid;gap:10px}
.ss-pe legend{font-weight:700;color:#08766b;padding:0 6px}.ss-pe label{display:grid;gap:4px;font-size:15px}.ss-pe input[type=text]{min-height:38px;font-size:15px}
.ss-choices{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px}.ss-choice{border:1px solid #c9d8d2;border-radius:8px;padding:10px;display:flex!important;gap:8px;align-items:flex-start}
.ss-choice small{display:block;color:#5b6b80}.ss-btn{min-height:44px;background:#08766b;color:#fff;border:0;border-radius:8px;font-size:15px;padding:0 16px;cursor:pointer}
.ss-preview{width:100%;height:760px;border:1px solid #c9d8d2;border-radius:8px;background:#eef2f5}.ss-ok{color:#08766b;font-weight:600}.ss-ko{color:#a32d2d;font-weight:600}
.ss-legend{font-size:14px;line-height:1.6;background:#f4fbf8;border-left:4px solid #08766b;padding:10px 12px}</style></head>
<body><header><strong>SchoolSafe</strong><a href="preparer-annee.php">Préparer l’année</a></header>
<main class="ss-pe"><h1>Papier de l’école</h1>
<p class="ss-legend">Le haut et le bas de chaque document (devoirs, interrogations, convocations, courriers) sont construits avec les informations
de l’école : <strong><?=$e($identity['school'])?></strong>. Le logo, la devise, l’adresse, le téléphone, l’e-mail et le site se modifient dans la
<a href="Modules.php?modname=School_Setup/Schools.php">fiche de l’école</a>.<?php if($missing):?><br><span class="ss-ko">À compléter : <?=$e(implode(', ',$missing))?>.</span><?php endif;?></p>
<?php if($message):?><p class="ss-ok" role="status"><?=$e($message)?></p><?php endif;?>
<?php if($error):?><p class="ss-ko" role="alert"><?=$e($error)?></p><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=$e($csrf)?>">
<fieldset><legend>1. Disposition par défaut</legend><div class="ss-choices">
<?php foreach($layouts as $key=>$label):?><label class="ss-choice"><input type="radio" name="layout" value="<?=$e($key)?>"<?=$key===$identity['layout']?' checked':''?> data-preview="<?=$e($key)?>"><span><strong><?=$e($label[0])?></strong><small><?=$e($label[1])?></small></span></label><?php endforeach;?>
</div></fieldset>
<fieldset><legend>2. En-tête officiel (disposition « Officielle RDC »)</legend>
<label>Ministère<input type="text" name="ministry" maxlength="120" value="<?=$e($identity['ministry'])?>"></label>
<label>Province éducationnelle et sous-division (facultatif)<input type="text" name="province" maxlength="160" value="<?=$e($identity['province'])?>" placeholder="ex. PROVINCE ÉDUCATIONNELLE KINSHASA-FUNA • SOUS-DIVISION …"></label>
</fieldset>
<fieldset><legend>3. Disposition personnalisée par JASPE</legend>
<p style="margin:0">Disposition actuelle : <strong><?=$e(SchoolSafeLetterheadStyleText(SchoolSafeLetterheadStyleDecode($identity['style'])))?></strong>. JASPE change seulement la disposition : les informations de l’école, le logo, le pied de page et le contenu restent toujours présents.</p>
<input type="hidden" name="style" value="<?=$e($identity['style'])?>">
<label>Votre demande<input type="text" name="demande" maxlength="300" placeholder="ex. logo centré, en-tête en bandeau bleu, titre encadré, texte plus grand"></label>
<div><button class="ss-btn" type="submit" name="action" value="jaspe" style="background:#5b3c99">Demander à JASPE</button>
<button class="ss-btn" type="submit" name="action" value="style-reset" onclick="this.form.style.value=''">Revenir à la disposition d’origine</button></div>
</fieldset>
<div><button class="ss-btn" type="submit" name="action" value="save">Enregistrer le papier de l’école</button></div>
</form>
<fieldset><legend>Aperçu</legend><iframe class="ss-preview" id="ss-preview" title="Aperçu du papier de l’école" src="papier-ecole.php?apercu=<?=$e($identity['layout'])?>&amp;style=<?=$e(rawurlencode($identity['style']))?>"></iframe></fieldset>
</main>
<script>document.querySelectorAll('[data-preview]').forEach(function(r){r.addEventListener('change',function(){document.getElementById('ss-preview').src='papier-ecole.php?apercu='+encodeURIComponent(r.value)+'&style='+encodeURIComponent(document.querySelector('[name=style]').value);});});</script>
</body></html>
