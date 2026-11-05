<?php
// Controle des frais par type (03/11) : l'administrateur ou l'economat regle, pour chaque type de frais,
// le seuil valide et le seuil tolere (en % de la part echue a la date du jour), l'option de blocage du
// service, les seuils de la cantine, et verrouille le bareme. Lecture seule de la facturation native.
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)||$_GET){http_response_code(400);exit('Requête invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/modules/SchoolSafe/Gateway.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeFeeTypes.fnc.php';
$school=(int)UserSchool();$year=(int)UserSyear();
// Lot F3 (B1, 05/11) : consulter le barème demande le droit de voir les frais ; le modifier, le droit de les modifier.
$allowed=User('STAFF_ID')&&User('PROFILE')==='admin'&&$school>0&&$year>0&&AllowUse('Student_Billing/StudentFees.php')
    &&SchoolSafeEndpointGateway('fee-control.php','GET')!=='deny';
$canManage=$allowed&&AllowEdit('Student_Billing/StudentFees.php')&&SchoolSafeEndpointGateway('scanner-relay.php','POST','fees',['action'=>'save'])!=='deny';
if(empty($_SESSION['SchoolSafeScannerCsrf']))$_SESSION['SchoolSafeScannerCsrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['SchoolSafeScannerCsrf'];
$get=function($sql){return DBGet(DBQuery($sql));};
$message='';$error='';
$slug=function(string $label):string{
    $folded=SchoolSafeFeeTypesFold($label);
    $id=trim(preg_replace('/[^a-z0-9]+/','-',$folded),'-');
    return substr($id!==''?$id:'type',0,32);
};
if($canManage&&$_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf']))throw new RuntimeException('Session expirée : rechargez la page.');
        $action=(string)($_POST['action']??'');
        // Lot F3 (B2, 05/11) : historique du barème (qui, quand, quoi, ancienne valeur), 30 dernières modifications.
        $history=function(string $what)use($school,$year,$get){
            $before=SchoolSafeFeeTypesRead($school,$year,$get);
            $where="SYEAR=".$year." AND SCHOOL_ID=".$school." AND PROGRAM='schoolsafe_scan' AND TITLE='FEE_TYPES_HISTORY'";
            $old=$get('SELECT VALUE FROM program_config WHERE '.$where);
            $list=json_decode((string)($old[1]['VALUE']??'[]'),true);if(!is_array($list))$list=[];
            array_unshift($list,['at'=>date('Y-m-d H:i'),'by'=>(string)User('NAME'),'what'=>$what,'before'=>$before]);
            $sql="'".str_replace("'","''",json_encode(array_slice($list,0,30),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))."'";
            $done=$get('UPDATE program_config SET VALUE='.$sql.',UPDATED_AT=CURRENT_TIMESTAMP WHERE '.$where.' RETURNING TITLE');
            if(!is_array($done)||!isset($done[1]))$get("INSERT INTO program_config(SYEAR,SCHOOL_ID,PROGRAM,TITLE,VALUE) VALUES(".$year.','.$school.",'schoolsafe_scan','FEE_TYPES_HISTORY',".$sql.') RETURNING TITLE');
        };
        if($action==='lock'||$action==='unlock'){
            $history($action==='lock'?'Barème verrouillé':'Barème déverrouillé');
            SchoolSafeFeeTypesWrite($school,$year,null,$action==='lock',$get);
            $message=$action==='lock'?'Barème verrouillé : il s’applique automatiquement à chaque scan.':'Barème déverrouillé.';
        }elseif($action==='save'){
            $types=[];$used=[];
            foreach((array)($_POST['types']??[]) as $row){
                if(!is_array($row)||!empty($row['delete']))continue;
                $label=trim((string)($row['label']??''));
                if($label===''&&trim((string)($row['keywords']??''))==='')continue; // carte « nouveau type » laissee vide
                $id=preg_match('/^[a-z0-9-]{1,40}$/D',(string)($row['id']??''))?(string)$row['id']:$slug($label);
                while(isset($used[$id]))$id=substr($id,0,36).'-'.random_int(10,99);
                $used[$id]=true;
                $types[]=['id'=>$id,'label'=>$label,'keywords'=>preg_split('/[,;]+/',(string)($row['keywords']??'')),
                    'validated'=>(int)($row['validated']??100),'tolerated'=>(int)($row['tolerated']??0),'block_service'=>!empty($row['block_service'])];
            }
            $canteen=null;
            if(trim((string)($_POST['canteen_validated']??''))!==''||trim((string)($_POST['canteen_tolerated']??''))!=='')
                $canteen=['validated'=>trim((string)$_POST['canteen_validated']),'tolerated'=>trim((string)$_POST['canteen_tolerated'])];
            $history('Barème modifié');
            SchoolSafeFeeTypesWrite($school,$year,['types'=>$types,'canteen'=>$canteen],null,$get);
            $message='Barème enregistré ('.count($types).' type(s) de frais).';
        }else throw new RuntimeException('Action inconnue.');
    }catch(Throwable $failure){$error=$failure instanceof RuntimeException?$failure->getMessage():'Enregistrement impossible.';}
}
$config=$allowed?SchoolSafeFeeTypesRead($school,$year,$get):SchoolSafeFeeTypesDefault();
$changes=[];
if($allowed){$h=$get("SELECT VALUE FROM program_config WHERE SYEAR=".$year." AND SCHOOL_ID=".$school." AND PROGRAM='schoolsafe_scan' AND TITLE='FEE_TYPES_HISTORY'");$changes=json_decode((string)($h[1]['VALUE']??'[]'),true);if(!is_array($changes))$changes=[];}
while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(403);exit('Permission administrative et financière requise.');}
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$locked=$config['locked'];$off=$locked||!$canManage?' disabled':'';
$rows=$config['types'];
if(!$locked)$rows[]=['id'=>'','label'=>'','keywords'=>[],'validated'=>100,'tolerated'=>70,'block_service'=>false];
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Contrôle des frais | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<style>.ss-fees{display:grid;gap:14px}.ss-fees fieldset{border:1px solid #a9beb5;border-radius:8px;padding:12px;display:grid;gap:10px;min-width:0}
.ss-fees legend{font-weight:700;color:#08766b;padding:0 6px}.ss-fees .row{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px}
.ss-fees label{display:grid;gap:4px;font-size:14px}.ss-fees input[type=text],.ss-fees input[type=number]{min-height:40px;font-size:16px;padding:4px 6px}
.ss-fees label.check{display:flex;align-items:center;gap:8px}.ss-btn{min-height:46px;background:#08766b;color:#fff;border:0;border-radius:6px;font-size:16px;padding:0 16px}
.ss-btn.alt{background:#fff;color:#08766b;border:1px solid #08766b}.ss-legend{font-size:14px;line-height:1.6;background:#f4fbf8;border-left:4px solid #08766b;padding:10px 12px}
.ss-ok{color:#08766b;font-weight:600}.ss-ko{color:#a32d2d}.ss-lock{display:flex;gap:10px;align-items:center;flex-wrap:wrap}</style></head>
<body><header><strong>SchoolSafe</strong><a href="scanner-native.php">Scanner</a></header>
<main><h1>Contrôle des frais</h1>
<p class="ss-legend">Pour chaque type de frais, la part <b>échue à la date du jour</b> (tranches dont l’échéance est passée) est comparée aux paiements.<br>
🟢 <b>Validé</b> : payé au moins le % validé · 🟡 <b>Toléré</b> : au moins le % toléré · 🔴 <b>Rouge</b> : en dessous.<br>
L’enfant <b>entre toujours</b> et le repas est <b>toujours servi</b> : 🟡 envoie un rappel au parent, 🔴 prévient le parent et l’économat (au plus une fois par semaine).<br>
Les tranches se créent dans la facturation (frais avec date d’échéance) ; le titre du frais doit contenir un mot-clé du type.</p>
<?php if($message):?><p class="ss-ok" role="status"><?=$e($message)?></p><?php endif;?>
<?php if($error):?><p class="ss-ko" role="alert"><?=$e($error)?></p><?php endif;?>
<?php if($canManage):?><form method="post" class="ss-lock"><input type="hidden" name="csrf" value="<?=$e($csrf)?>">
<input type="hidden" name="action" value="<?=$locked?'unlock':'lock'?>"><span><?=$locked?'🔒 Barème verrouillé : il s’applique automatiquement.':'🔓 Barème modifiable.'?></span>
<button class="ss-btn alt" type="submit"><?=$locked?'Déverrouiller le barème':'Verrouiller le barème'?></button></form><?php endif;?>
<form method="post" class="ss-fees"><input type="hidden" name="csrf" value="<?=$e($csrf)?>"><input type="hidden" name="action" value="save">
<?php foreach($rows as $i=>$type): $new=$type['id']===''; ?>
<fieldset><legend><?=$new?'➕ Nouveau type de frais':$e($type['label'])?></legend>
<input type="hidden" name="types[<?=$i?>][id]" value="<?=$e($type['id'])?>">
<div class="row"><label>Nom du type<input type="text" name="types[<?=$i?>][label]" maxlength="60" value="<?=$e($type['label'])?>" placeholder="ex. Scolarité"<?=$off?>></label>
<label>Mots-clés du titre des frais<input type="text" name="types[<?=$i?>][keywords]" value="<?=$e(implode(', ',$type['keywords']))?>" placeholder="ex. Scolarité, minerval"<?=$off?>></label></div>
<div class="row"><label>🟢 Validé à partir de (%)<input type="number" min="0" max="100" step="1" name="types[<?=$i?>][validated]" value="<?=(int)$type['validated']?>"<?=$off?>></label>
<label>🟡 Toléré à partir de (%)<input type="number" min="0" max="100" step="1" name="types[<?=$i?>][tolerated]" value="<?=(int)$type['tolerated']?>"<?=$off?>></label></div>
<label class="check"><input type="checkbox" name="types[<?=$i?>][block_service]" value="1"<?=$type['block_service']?' checked':''?><?=$off?>> En rouge, suspendre ce service (examens, sorties — jamais l’entrée ni le repas)</label>
<?php if(!$new):?><label class="check"><input type="checkbox" name="types[<?=$i?>][delete]" value="1"<?=$off?>> Supprimer ce type</label><?php endif;?>
</fieldset><?php endforeach;?>
<?php // Lot F4c (05/11) : les comptes de la cantine sont en FC (une seule monnaie par école) ; les seuils aussi. ?>
<fieldset><legend>🍽️ Cantine (solde du compte cantine, en FC)</legend>
<div class="row"><label>🟢 Validé si le solde est au moins (FC)<input type="text" inputmode="decimal" name="canteen_validated" value="<?=$e($config['canteen']['validated']??'')?>" placeholder="ex. 5000"<?=$off?>></label>
<label>🟡 Toléré si le solde est au moins (FC)<input type="text" inputmode="decimal" name="canteen_tolerated" value="<?=$e($config['canteen']['tolerated']??'')?>" placeholder="ex. -2000"<?=$off?>></label></div>
<p style="margin:0;font-size:13px">Laissez vide pour ne pas contrôler la cantine. En dessous du seuil toléré : 🔴, le repas est servi et l’économat est prévenu.</p></fieldset>
<?php if($canManage&&!$locked):?><button class="ss-btn" type="submit">Enregistrer le barème</button><?php endif;?>
<?php if(!$canManage):?><p class="ss-legend">Consultation seulement : le barème se modifie avec le droit de modifier les frais.</p><?php endif;?>
</form>
<section class="ss-fees"><h2>Dernières modifications du barème</h2>
<?php if(!$changes):?><p>Aucune modification enregistrée depuis la mise en place de l’historique.</p><?php else:?><ul>
<?php foreach(array_slice($changes,0,10) as $c):?><li><?=$e(($c['at']??'').' — '.($c['what']??'').' — '.($c['by']??''))?></li><?php endforeach;?>
</ul><?php endif;?></section></main></body></html>
