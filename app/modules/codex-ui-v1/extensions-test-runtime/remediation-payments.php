<?php
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'POST'], true)) {
 header('Allow: GET, POST');http_response_code(405);exit('Methode non autorisee.');
}
header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
chdir(__DIR__); $_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level(); ob_start(); require_once __DIR__.'/Warehouse.php';
$allowed=User('STAFF_ID') && User('PROFILE')==='admin' && AllowEdit('Student_Billing/StudentPayments.php');
while(ob_get_level()>$level) ob_end_clean();
if(!$allowed){http_response_code(403);exit('Permission de gestion des paiements requise.');}
require_once __DIR__.'/modules/SchoolSafe/Gateway.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST'
 && SchoolSafeEndpointGateway('remediation-payments.php', $_SERVER['REQUEST_METHOD'], '', $_GET) === 'deny') {
 http_response_code(403);exit('Acces SchoolSafe non autorise.');
}
$canRecord=SchoolSafeEndpointGateway('remediation-payments.php','POST','',['action'=>'record']) !== 'deny';
$canConfirm=SchoolSafeEndpointGateway('remediation-payments.php','POST','',['action'=>'confirm']) !== 'deny';
$canRead=empty($RosarioModules['SchoolSafe']) || SchoolSafeNativeCommandPermission('SchoolSafe/RemediationPaymentsRead.php');
$user=(int)User('STAFF_ID'); $school=(int)UserSchool(); $year=(int)UserSyear();
$_SESSION['ss_remediation_finance_csrf']=$_SESSION['ss_remediation_finance_csrf']??bin2hex(random_bytes(32));
$token=$_SESSION['ss_remediation_finance_csrf'];
$escape=static function($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');};
$message='';
require_once __DIR__.'/schoolsafe-remediation/PaymentScope.fnc.php';
$versionSchema=DBGet("SELECT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='schoolsafe_remediation_payments' AND column_name='confirmation_version') AS ready");
$canReconfirm=($versionSchema[1]['READY']??'f')==='t';
$usedPaymentScope=$canReconfirm?' AND (used.invalidated_at IS NULL OR used.case_id<>r.id)':'';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!is_string($_POST['csrf']??null)||!hash_equals($token,$_POST['csrf'])){http_response_code(403);exit('Requete expiree.');}
 foreach($_POST as $value){if(!is_string($value)){http_response_code(400);exit('Formulaire invalide.');}}
 if(!in_array($_POST['action']??'confirm',['record','confirm'],true)){http_response_code(400);exit('Operation invalide.');}
 if(SchoolSafeEndpointGateway('remediation-payments.php','POST','',$_POST)==='deny'){http_response_code(403);exit('Operation SchoolSafe non autorisee.');}
 $case=(int)($_POST['case_id']??0);$payment=(int)($_POST['payment_id']??0);
 if(($_POST['action']??'confirm')==='record'){
  $amount=$_POST['amount']??'';$reference=trim($_POST['receipt_reference']??'');$date=$_POST['payment_date']??'';
  $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
  if(!preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/D',$amount)||(float)$amount<=0
    ||$reference===''||mb_strlen($reference)>100||!$parsed||$parsed->format('Y-m-d')!==$date){http_response_code(400);exit('Paiement incomplet.');}
  DBQuery('BEGIN ISOLATION LEVEL READ COMMITTED');
  DBQuery("SET LOCAL lock_timeout='5s'");DBQuery("SET LOCAL statement_timeout='30s'");
  $feeLocked=DBGet(SchoolSafeRemediationLockFeeSql($case,$school,$year));
  $dossier=$feeLocked?DBGet('SELECT r.* FROM schoolsafe_remediation_cases r WHERE r.id='.$case.' AND r.school_id='.$school.' AND r.syear='.$year
   ." AND r.billing_mode='paid' AND ".SchoolSafeRemediationFeeScopeSql()." AND NOT EXISTS(SELECT 1 FROM schoolsafe_remediation_payment_purpose purpose WHERE purpose.fee_id=r.fee_id AND purpose.receipt_reference='".DBEscapeString($reference)."') AND ".$amount.'<=r.tariff-COALESCE((SELECT sum(p.amount) FROM schoolsafe_remediation_payment_purpose purpose JOIN billing_payments p ON p.id=purpose.payment_id WHERE purpose.fee_id=r.fee_id AND p.refunded_payment_id IS NULL AND NOT EXISTS(SELECT 1 FROM billing_payments ref WHERE ref.refunded_payment_id=p.id)),0) FOR UPDATE'):[];
  if($dossier){
   $r=$dossier[1];
   $saved=DBGet(DBQuery('INSERT INTO billing_payments(student_id,school_id,syear,amount,payment_date,comments,created_by) VALUES('
    .(int)$r['STUDENT_ID'].','.$school.','.$year.','.$amount.",'".DBEscapeString($date)."','Rattrapage - dossier ".$case."','".DBEscapeString(User('NAME'))."') RETURNING id"));
   DBQuery('INSERT INTO schoolsafe_remediation_payment_purpose(payment_id,fee_id,recorded_by,receipt_reference) VALUES('
    .(int)$saved[1]['ID'].','.(int)$r['FEE_ID'].','.$user.",'".DBEscapeString($reference)."')");
   DBQuery("INSERT INTO schoolsafe_remediation_events(case_id,kind,actor_id) VALUES(".$case.",'payment_recorded',".$user.')');
  }
  DBQuery('COMMIT');
  $message=$dossier?'Paiement de rattrapage enregistre dans SchoolSafe. Confirmation et repartition restent a effectuer.':'Dossier indisponible ou montant superieur au solde.';
 }else{
 DBQuery('BEGIN ISOLATION LEVEL READ COMMITTED');
 DBQuery("SET LOCAL lock_timeout='5s'");DBQuery("SET LOCAL statement_timeout='30s'");
 $feeLocked=DBGet(SchoolSafeRemediationLockFeeSql($case,$school,$year));
 $locked=$feeLocked?DBGet("SELECT r.id FROM schoolsafe_remediation_cases r WHERE r.id=".$case.' AND r.school_id='.$school.' AND r.syear='.$year." AND r.billing_mode='paid' AND ".SchoolSafeRemediationFeeScopeSql().' FOR UPDATE'):[];
 $paymentLocked=$locked?DBGet('SELECT p.id FROM billing_payments p WHERE p.id='.$payment.' AND p.school_id='.$school.' AND p.syear='.$year.' FOR UPDATE OF p'):[];
 $purposeLocked=$paymentLocked?DBGet('SELECT id FROM schoolsafe_remediation_payment_purpose WHERE payment_id='.$payment.' AND fee_id=(SELECT fee_id FROM schoolsafe_remediation_cases WHERE id='.$case.') FOR SHARE'):[];
 $source=$purposeLocked?DBGet('SELECT p.id,p.amount FROM billing_payments p JOIN schoolsafe_remediation_cases r ON r.student_id=p.student_id AND r.school_id=p.school_id AND r.syear=p.syear JOIN schoolsafe_remediation_payment_purpose purpose ON purpose.payment_id=p.id AND purpose.fee_id=r.fee_id WHERE r.id='.$case.' AND p.id='.$payment.' AND p.amount>0 AND p.payment_date IS NOT NULL AND p.refunded_payment_id IS NULL AND NOT EXISTS(SELECT 1 FROM billing_payments ref WHERE ref.refunded_payment_id=p.id) AND NOT EXISTS(SELECT 1 FROM schoolsafe_remediation_payments used WHERE used.payment_id=p.id'.$usedPaymentScope.') AND p.amount<=r.tariff-COALESCE((SELECT sum(amount) FROM schoolsafe_remediation_payments a WHERE a.case_id=r.id AND a.invalidated_at IS NULL),0)'):[];
 if($source){
  $previous=$canReconfirm?DBGet('SELECT id FROM schoolsafe_remediation_payments WHERE case_id='.$case.' AND payment_id='.$payment.' AND invalidated_at IS NOT NULL LIMIT 1'):[];
  DBQuery('INSERT INTO schoolsafe_remediation_payments(case_id,payment_id,amount,school_share,teacher_share,confirmed_by) SELECT '.$case.',id,amount,round(amount*0.4,2),amount-round(amount*0.4,2),'.$user.' FROM billing_payments WHERE id='.$payment);
  DBQuery("INSERT INTO schoolsafe_remediation_events(case_id,kind,actor_id) VALUES(".$case.",'".($previous?'payment_reconfirmed':'payment_confirmed')."',".$user.')');
 }
 DBQuery('COMMIT');
 $message=$source?'Paiement associe et confirme. Repartition comptable 40/60 enregistree.':'Paiement indisponible, rembourse, deja associe, frais modifie ou montant superieur au solde du rattrapage.';
 }
 $_SESSION['ss_remediation_finance_csrf']=bin2hex(random_bytes(32));$token=$_SESSION['ss_remediation_finance_csrf'];
}
$rows=DBGet('SELECT r.id,r.student_id,r.fee_id,r.tariff,s.first_name,s.last_name,c.title AS course_title FROM schoolsafe_remediation_cases r JOIN students s ON s.student_id=r.student_id JOIN courses c ON c.course_id=r.course_id WHERE r.school_id='.$school.' AND r.syear='.$year." AND r.billing_mode='paid' ORDER BY r.id DESC LIMIT 200");
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Paiements de rattrapage | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-remediation/remediation.css"></head>
<body><header><strong>School<span>Safe</span></strong><a href="Modules.php?modname=misc/Portal.php">Tableau de bord</a></header><main><nav>Finance / Paiements de rattrapage</nav><h1>Paiements de rattrapage</h1>
<p>Associer uniquement un paiement SchoolSafe effectivement confirm&eacute; pour ce rattrapage. La r&eacute;partition est comptable ; aucun transfert bancaire n'est effectu&eacute;.</p>
<?php if($message):?><p role="status"><?= $escape($message) ?></p><?php endif;?>
<?php if(!$rows):?><p>Aucun dossier de rattrapage payant dans cette &eacute;cole.</p><?php endif;?>
<?php foreach($rows as $r):?><section id="remediation-case-<?= (int)$r['ID'] ?>"><h2><?= $escape($r['FIRST_NAME'].' '.$r['LAST_NAME'].' / '.ParseMLField($r['COURSE_TITLE'])) ?></h2><p>Dossier <?= (int)$r['ID'] ?> · Tarif <?= $escape($r['TARIFF']) ?></p>
<?php if(SchoolSafeEndpointGateway('rattrapage.php','GET')!=='deny' && AllowUse('Grades/StudentGrades.php')):?><p><a href="rattrapage.php#remediation-case-<?= (int)$r['ID'] ?>">Dossier pedagogique</a></p><?php endif;?>
<?php if($canRecord && $r['FEE_ID']):?><form method="post"><input type="hidden" name="csrf" value="<?= $escape($token) ?>"><input type="hidden" name="case_id" value="<?= (int)$r['ID'] ?>"><input type="hidden" name="action" value="record"><label>Montant re&ccedil;u<input name="amount" type="number" min="0.01" step="0.01" required></label><label>Date du paiement<input name="payment_date" type="date" required></label><label>R&eacute;f&eacute;rence du re&ccedil;u<input name="receipt_reference" maxlength="100" required></label><button>Enregistrer le paiement de rattrapage</button></form><?php endif;?>
<?php $payments=$canConfirm?DBGet('SELECT p.id,p.amount,p.payment_date FROM billing_payments p JOIN schoolsafe_remediation_payment_purpose purpose ON purpose.payment_id=p.id WHERE purpose.fee_id='.(int)$r['FEE_ID'].' AND p.student_id='.(int)$r['STUDENT_ID'].' AND p.school_id='.$school.' AND p.syear='.$year.' AND p.amount>0 AND p.payment_date IS NOT NULL AND p.refunded_payment_id IS NULL AND NOT EXISTS(SELECT 1 FROM billing_payments ref WHERE ref.refunded_payment_id=p.id) AND NOT EXISTS(SELECT 1 FROM schoolsafe_remediation_payments used WHERE used.payment_id=p.id'.($canReconfirm?' AND (used.invalidated_at IS NULL OR used.case_id<>'.(int)$r['ID'].')':'').') ORDER BY p.payment_date DESC'):[]; ?>
<?php if($payments):?><form method="post"><input type="hidden" name="csrf" value="<?= $escape($token) ?>"><input type="hidden" name="action" value="confirm"><input type="hidden" name="case_id" value="<?= (int)$r['ID'] ?>"><label>Paiement natif<select name="payment_id" required><?php foreach($payments as $p):?><option value="<?= (int)$p['ID'] ?>">#<?= (int)$p['ID'] ?> · <?= $escape($p['PAYMENT_DATE']) ?> · <?= $escape($p['AMOUNT']) ?></option><?php endforeach;?></select></label><button>Associer et confirmer le paiement</button></form><?php elseif($canConfirm):?><p>Aucun paiement natif disponible.</p><?php endif;?>
<?php $allocations=$canRead?DBGet('SELECT * FROM schoolsafe_remediation_payments WHERE case_id='.(int)$r['ID'].' ORDER BY confirmed_at,id'):[];foreach($allocations as $a):?><p>Paiement #<?= (int)($a['SOURCE_PAYMENT_ID']??$a['PAYMENT_ID']) ?><?php if(isset($a['CONFIRMATION_VERSION'])):?> · Version <?= (int)$a['CONFIRMATION_VERSION'] ?><?php endif;?> : <?= $escape($a['AMOUNT']) ?> · &Eacute;cole <?= $escape($a['SCHOOL_SHARE']) ?> · Enseignant <?= $escape($a['TEACHER_SHARE']) ?><?php if($a['INVALIDATED_AT']):?> · Version invalid&eacute;e<?php else:?> · Confirm&eacute;<?php endif;?></p><?php endforeach;?></section><?php endforeach;?>
</main></body></html>
