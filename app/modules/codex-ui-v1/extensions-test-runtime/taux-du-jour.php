<?php
// Taux du jour (lot F4a, 05/11/2026) : décision du propriétaire, deux monnaies FC et USD (docs/DECISIONS.md).
// La direction ou l'économat saisit chaque jour combien de francs congolais vaut 1 dollar. Un paiement reçu dans
// l'autre monnaie que celle du compte réglé est converti à ce taux ; le taux appliqué reste gardé avec le paiement,
// une correction du taux ne change donc jamais un paiement déjà enregistré.
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','POST'],true)||$_GET){http_response_code(400);exit('Requête invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafeMoney.fnc.php';
$school=(int)UserSchool();$actor=(int)User('STAFF_ID');
// Voir : droit de voir les paiements ; saisir : droit de modifier les paiements.
$allowed=$actor&&User('PROFILE')==='admin'&&$school>0&&AllowUse('Student_Billing/StudentPayments.php');
$canEdit=$allowed&&AllowEdit('Student_Billing/StudentPayments.php');
if(empty($_SESSION['SchoolSafeScannerCsrf']))$_SESSION['SchoolSafeScannerCsrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['SchoolSafeScannerCsrf'];
$message='';$error='';$ready=$allowed&&SchoolSafeMoneyCurrencyReady();
$today=date('Y-m-d');
if($ready&&$canEdit&&$_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf']))throw new RuntimeException('Session expirée : rechargez la page.');
        $date=is_string($_POST['date']??null)?$_POST['date']:'';
        $rate=SchoolSafeMoneyAmount(is_string($_POST['rate']??null)?$_POST['rate']:'');
        $check=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if(!$check||$check->format('Y-m-d')!==$date||$date>$today)throw new RuntimeException('Indiquez une date valide, aujourd’hui ou avant.');
        if(!preg_match('/^\d{1,9}(\.\d{1,4})?$/',$rate)||(float)$rate<=0)throw new RuntimeException('Indiquez le taux : combien de francs congolais pour 1 dollar (par exemple 2850).');
        $by=DBEscapeString((string)User('NAME'));
        DBQuery("INSERT INTO schoolsafe_exchange_rates(SCHOOL_ID,RATE_DATE,RATE,CREATED_BY) VALUES(".$school.",'".$date."','".$rate."','".$by."')
            ON CONFLICT (school_id, rate_date) DO UPDATE SET RATE=EXCLUDED.RATE, CREATED_BY=EXCLUDED.CREATED_BY, CREATED_AT=CURRENT_TIMESTAMP");
        $message='Taux enregistré : 1 USD = '.number_format((float)$rate,2,',',"\u{00A0}").' FC le '.$check->format('d/m/Y').'. Les paiements déjà enregistrés gardent leur taux.';
    }catch(Throwable $failure){$error=$failure instanceof RuntimeException?$failure->getMessage():'Enregistrement impossible.';}
}
$rows=$ready?DBGet("SELECT RATE_DATE,RATE,CREATED_BY,CREATED_AT FROM schoolsafe_exchange_rates WHERE SCHOOL_ID=".$school." ORDER BY RATE_DATE DESC LIMIT 30"):[];
$todayRate=$ready?SchoolSafeMoneyRate($today):0;
while(ob_get_level()>$level)ob_end_clean();
if(!$allowed){http_response_code(403);exit('Permission de voir les paiements requise.');}
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Taux du jour | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<style>.ss-rate{display:grid;gap:14px;max-width:760px}.ss-rate fieldset{border:1px solid #c9d8d2;border-radius:10px;padding:12px 14px;display:grid;gap:10px}
.ss-rate legend{font-weight:700;color:#08766b;padding:0 6px}.ss-rate .row{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}
.ss-rate label{display:grid;gap:4px;font-size:14px}.ss-rate input{min-height:40px;font-size:16px;padding:4px 8px;border:1px solid #b9c6d4;border-radius:8px}
.ss-btn{min-height:46px;background:#08766b;color:#fff;border:0;border-radius:8px;font-size:16px;padding:0 18px;justify-self:start}
.ss-legend{font-size:14px;line-height:1.6;background:#f4fbf8;border-left:4px solid #08766b;padding:10px 12px;margin:0}
.ss-ok{color:#08766b;font-weight:600}.ss-ko{color:#a32d2d;font-weight:600}.ss-now{font-size:20px;font-weight:700;color:#172333}
.ss-rate table{border-collapse:collapse;width:100%}.ss-rate th,.ss-rate td{text-align:left;padding:8px;border-bottom:1px solid #e3e9f0}</style></head>
<body><main class="ss-rate"><h1>Taux du jour</h1>
<p class="ss-legend">Combien de <b>francs congolais (FC) pour 1 dollar (USD)</b>. Ce taux sert quand une famille paie dans l’autre
monnaie que celle du frais : par exemple un frais en USD payé en FC. Le taux appliqué est gardé avec chaque paiement et
figure sur le reçu. Sans taux du jour, un paiement dans l’autre monnaie est refusé.</p>
<?php if(!$ready):?><p class="ss-ko">Les deux monnaies ne sont pas encore installées sur ce serveur.</p><?php else:?>
<p class="ss-now"><?=$todayRate>0?'Aujourd’hui : 1 USD = '.$e(number_format($todayRate,2,',',"\u{00A0}")).' FC':'Aucun taux saisi pour aujourd’hui.'?></p>
<?php if($message):?><p class="ss-ok" role="status"><?=$e($message)?></p><?php endif;?>
<?php if($error):?><p class="ss-ko" role="alert"><?=$e($error)?></p><?php endif;?>
<?php if($canEdit):?><form method="post"><fieldset><legend>Saisir le taux</legend><input type="hidden" name="csrf" value="<?=$e($csrf)?>">
<div class="row"><label>Date<input type="date" name="date" required max="<?=$e($today)?>" value="<?=$e($today)?>"></label>
<label>FC pour 1 USD<input type="text" name="rate" inputmode="decimal" required placeholder="ex. 2850"></label></div>
<button class="ss-btn" type="submit">Enregistrer le taux</button></fieldset></form>
<?php else:?><p class="ss-legend">Consultation seulement : le taux se saisit avec le droit de modifier les paiements.</p><?php endif;?>
<section><h2>Derniers taux</h2><?php if(!$rows):?><p>Aucun taux saisi.</p><?php else:?>
<table><thead><tr><th>Date</th><th>1 USD =</th><th>Saisi par</th></tr></thead><tbody>
<?php foreach($rows as $row):?><tr><td><?=$e(date('d/m/Y',strtotime($row['RATE_DATE'])))?></td><td><?=$e(number_format((float)$row['RATE'],2,',',"\u{00A0}"))?> FC</td><td><?=$e($row['CREATED_BY'])?></td></tr><?php endforeach;?>
</tbody></table><?php endif;?></section>
<?php endif;?></main></body></html>
