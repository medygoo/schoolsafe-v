<?php
// Reglages du rattrapage par ecole (04/11, valide par le proprietaire) : detection, vigilance, partage, reussite.
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','POST'],true)||$_GET){http_response_code(400);exit('Requête invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeRemediationSettings.fnc.php';
$school=(int)UserSchool();$actor=(int)User('STAFF_ID');
// Meme droit que « Preparer l'annee » : administrateur autorise a configurer l'ecole.
$allowed=$actor&&User('PROFILE')==='admin'&&$school>0&&AllowEdit('School_Setup/Configuration.php');
if(empty($_SESSION['SchoolSafeScannerCsrf']))$_SESSION['SchoolSafeScannerCsrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['SchoolSafeScannerCsrf'];
$get=function($sql){return DBGet($sql);};
$s=$allowed?SchoolSafeRemediationSettings($school,$get):SchoolSafeRemediationDefaults();
$form=$s;$message='';$error='';
if($allowed&&$_SERVER['REQUEST_METHOD']==='POST'){
    $form=array_merge($s,array_map(static function($v){return is_string($v)?$v:'';},$_POST));$form['vigilance']=!empty($_POST['vigilance']);$form['other_teacher']=!empty($_POST['other_teacher']);
    try{
        if(!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf']))throw new RuntimeException('Session expirée : rechargez la page.');
        $valid=SchoolSafeRemediationSettingsValidate($_POST);
        SchoolSafeRemediationSettingsSave($school,$actor,$valid,function($sql){DBQuery($sql);});
        $s=SchoolSafeRemediationSettings($school,$get);$form=$s;
        $message='Réglages enregistrés. Ils s’appliquent aux prochaines notes et aux prochains paiements confirmés ; les paiements déjà répartis ne changent pas.';
    }catch(Throwable $failure){$error=$failure instanceof RuntimeException?$failure->getMessage():'Enregistrement impossible.';}
}
while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(403);exit('Permission requise : administrateur autorisé à configurer l’école.');}
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
// Valeur saisie (texte) telle quelle ; valeur enregistree (nombre) sans decimales inutiles.
$num=static function($v){if($v===null||$v==='')return '';if(is_string($v))return $v;return str_replace('.',',',rtrim(rtrim(number_format((float)$v,2,'.',''),'0'),'.'));};
$text=SchoolSafeRemediationRuleText($s);
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Réglages du rattrapage | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<style>.ss-rr{display:grid;gap:14px;max-width:760px}.ss-rr fieldset{border:1px solid #c9d8d2;border-radius:10px;padding:12px 14px;display:grid;gap:10px}
.ss-rr legend{font-weight:700;color:#08766b;padding:0 6px}.ss-rr label{display:grid;gap:4px;font-size:15px}.ss-rr label.inline{display:flex;gap:8px;align-items:center}
.ss-rr input[type=text]{min-height:38px;font-size:15px;max-width:140px}.ss-rr small{color:#5b6b80}
.ss-btn{min-height:44px;background:#08766b;color:#fff;border:0;border-radius:8px;font-size:15px;padding:0 16px;cursor:pointer}
.ss-legend{font-size:14px;line-height:1.6;background:#f4fbf8;border-left:4px solid #08766b;padding:10px 12px}.ss-ok{color:#08766b;font-weight:600}.ss-ko{color:#a32d2d;font-weight:600}</style><?php require_once __DIR__.'/ProgramFunctions/SchoolSafePedagogyNav.fnc.php'; echo SchoolSafePedagogyStyle(); ?></head>
<body><?php echo SchoolSafePedagogyHeader('reglages-rattrapage.php',0); ?>
<main class="ss-rr"><h1>Réglages du rattrapage</h1>
<div class="ss-legend"><strong><?=$s['saved']?'Règles actuelles de l’école':'Règles d’origine (aucun réglage enregistré)'?></strong><br>
<?=$e($text['detection'])?><br><?=$e($text['vigilance'])?><br><?=$e($text['share'])?><br><?=$e($text['teacher'])?><br><?=$e($text['pass'])?></div>
<?php if($message):?><p class="ss-ok" role="status"><?=$e($message)?></p><?php endif;?>
<?php if($error):?><p class="ss-ko" role="alert"><?=$e($error)?></p><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=$e($csrf)?>">
<fieldset><legend>1. Quand proposer un rattrapage</legend>
<label>Seuil d’échec (en %)<input type="text" inputmode="decimal" name="threshold_percent" value="<?=$e($num($form['threshold_percent']))?>" required><small>Une note strictement sous ce seuil compte comme un échec. Exemple : 50.</small></label>
<label>Nombre d’évaluations sous le seuil (2 à 6)<input type="text" inputmode="numeric" name="failures" value="<?=$e($num($form['failures']))?>" required><small>Dans une même matière, sur toute l’année.</small></label>
<label>Sortes différentes exigées (1 à 3)<input type="text" inputmode="numeric" name="min_kinds" value="<?=$e($num($form['min_kinds']))?>" required><small>Parmi devoir, interrogation et examen.</small></label>
</fieldset>
<fieldset><legend>2. Prévenir plus tôt</legend>
<label class="inline"><input type="checkbox" name="vigilance" value="1"<?=$form['vigilance']?' checked':''?>> Alerte « vigilance » dès 2 évaluations sous le seuil</label>
<small>Aucun dossier n’est ouvert : le parent est prévenu et l’enseignant voit l’élève dans la page Rattrapage.</small>
</fieldset>
<fieldset><legend>3. Partage des paiements</legend>
<label>Part de l’école (en %)<input type="text" inputmode="decimal" name="school_share_percent" value="<?=$e($num($form['school_share_percent']))?>" required><small>Le reste revient à l’enseignant. Exemple : 40 → 40 % école, 60 % enseignant.</small></label>
</fieldset>
<fieldset><legend>4. Qui donne le rattrapage</legend>
<label class="inline"><input type="checkbox" name="other_teacher" value="1"<?=!empty($form['other_teacher'])?' checked':''?>> Exiger un autre enseignant que celui qui enseigne et note la matière</label>
<small>Évite qu’un parent soupçonne un conflit d’intérêt. Décoché : la direction choisit librement parmi les enseignants de l’école.</small>
</fieldset>
<fieldset><legend>5. Réussite du rattrapage</legend>
<label>Note de réussite à la nouvelle évaluation (en %)<input type="text" inputmode="decimal" name="pass_percent" value="<?=$e($num($form['pass_percent']))?>" required></label>
<label>Ou progression d’au moins (en points de %, facultatif)<input type="text" inputmode="decimal" name="pass_gain_points" value="<?=$e($num($form['pass_gain_points']))?>" placeholder="ex. 15"><small>Comparée à la moyenne des notes du dossier. Laissez vide pour ne pas l’utiliser.</small></label>
<small>C’est une indication : la Direction pédagogique garde la décision.</small>
</fieldset>
<div><button class="ss-btn" type="submit">Enregistrer les réglages</button></div>
</form>
</main></body></html>
