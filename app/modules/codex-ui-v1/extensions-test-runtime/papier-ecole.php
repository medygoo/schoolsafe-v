<?php
// Papier de l'ecole (04/11, lot 3 valide) : disposition par defaut, ligne du ministere et de la province,
// apercu ; le logo, la devise, l'adresse et les contacts viennent de la fiche de l'ecole.
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','POST'],true)){http_response_code(405);exit;}
if(array_diff(array_keys($_GET),['apercu'])){http_response_code(400);exit('Requête invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeLetterhead.fnc.php';
$school=(int)UserSchool();$actor=(int)User('STAFF_ID');
// Meme droit que « Preparer l'annee » : administrateur autorise a configurer l'ecole.
$allowed=$actor&&User('PROFILE')==='admin'&&$school>0&&AllowEdit('School_Setup/Configuration.php');
if(empty($_SESSION['SchoolSafeScannerCsrf']))$_SESSION['SchoolSafeScannerCsrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['SchoolSafeScannerCsrf'];
$message='';$error='';
if($allowed&&$_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf']))throw new RuntimeException('Session expirée : rechargez la page.');
        $valid=SchoolSafeLetterheadValidate(array_map(static function($v){return is_string($v)?$v:'';},$_POST));
        DBQuery('BEGIN');
        foreach(['SCHOOLSAFE_DOC_LAYOUT'=>$valid['layout'],'SCHOOLSAFE_DOC_MINISTRY'=>$valid['ministry'],'SCHOOLSAFE_DOC_PROVINCE'=>$valid['province']] as $title=>$value){
            $sql="'".DBEscapeString($value)."'";
            $done=DBGet(DBQuery("UPDATE config SET CONFIG_VALUE=".$sql.",UPDATED_AT=CURRENT_TIMESTAMP WHERE SCHOOL_ID=".$school." AND TITLE='".$title."' RETURNING TITLE"));
            if(!$done)DBQuery("INSERT INTO config(SCHOOL_ID,TITLE,CONFIG_VALUE) VALUES(".$school.",'".$title."',".$sql.")");
        }
        DBQuery('COMMIT');
        $message='Papier de l’école enregistré. Les prochains documents l’utilisent ; chaque enseignant peut encore choisir une autre disposition.';
    }catch(Throwable $failure){$error=$failure instanceof RuntimeException?$failure->getMessage():'Enregistrement impossible.';}
}
$identity=$allowed?SchoolSafeLetterheadIdentity($school):[];
while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(403);exit('Permission requise : administrateur autorisé à configurer l’école.');}
$layouts=SchoolSafeLetterheadLayouts();
$e='SchoolSafeLetterheadEscape';
if(isset($_GET['apercu'])){
    $layout=is_string($_GET['apercu'])&&isset($layouts[$_GET['apercu']])?$_GET['apercu']:$identity['layout'];
    header('Content-Type: text/html; charset=UTF-8');
    echo SchoolSafeLetterheadPage($identity,$layout,['title'=>'Interrogation — Exemple de mise en page',
        'meta'=>[['Matière','Mathématiques'],['Enseignant','Nom de l’enseignant'],['Donné le',SchoolSafeLetterheadDate(date('Y-m-d'))]],
        'body'=>'<p style="text-align:center"><strong>Lisez attentivement chaque question.</strong></p><ol><li>Calculez 3/4 + 1/8.</li><li>Recopiez et complétez le tableau.</li></ol>'
            .'<table><tr><th>Fraction</th><th>Décimal</th></tr><tr><td>1/2</td><td></td></tr><tr><td>3/4</td><td></td></tr></table>',
        'student_box'=>$layout==='devoir'||$layout==='officielle','points'=>'20','answer_lines'=>$layout==='devoir'?6:0]);
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
<div><button class="ss-btn" type="submit">Enregistrer le papier de l’école</button></div>
</form>
<fieldset><legend>Aperçu</legend><iframe class="ss-preview" id="ss-preview" title="Aperçu du papier de l’école" src="papier-ecole.php?apercu=<?=$e($identity['layout'])?>"></iframe></fieldset>
</main>
<script>document.querySelectorAll('[data-preview]').forEach(function(r){r.addEventListener('change',function(){document.getElementById('ss-preview').src='papier-ecole.php?apercu='+encodeURIComponent(r.value);});});</script>
</body></html>
