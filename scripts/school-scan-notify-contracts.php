<?php
// Pure synthetic adapter only: no native Warehouse, credentials, DB or transport.
$root=getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT');if(!$root)exit(2);
require_once $root.'/ProgramFunctions/SchoolSafeNotify.fnc.php';
$pass=0;$fail=0;
function scanNotifyCheck($name,$ok){global $pass,$fail;if($ok){$pass++;echo "PASS $name\n";}else{$fail++;echo "FAIL $name\n";}}
function scanNotifyError(callable $call){try{$call();return 'NO_REFUSAL';}catch(Throwable $error){return $error->getMessage();}}
$base=['school_id'=>3,'syear'=>2026,'student_id'=>42,'actor_staff_id'=>9,'event_type'=>'security.entry','dedup_key'=>'synthetic:entry:17','payload'=>['student_name'=>'Synthetic child']];
scanNotifyCheck('financial control has a dedicated native event type',scanNotifyError(fn()=>SchoolSafeNotifyValidateEvent(array_replace($base,['event_type'=>'financial.control'])))==='NO_REFUSAL');
scanNotifyCheck('real payment receipt type remains available',SchoolSafeNotifyValidateEvent(array_replace($base,['event_type'=>'payment.receipt']))['event_type']==='payment.receipt');
$ops=[];$deliveryOk=true;$eventOk=true;$available=true;
$db=['exec'=>function($sql)use(&$ops){$ops[]=$sql;},'get'=>function($sql)use(&$deliveryOk,&$eventOk,&$available){
 if(strpos($sql,'to_regclass')!==false)return [1=>['T'=>$available?'schoolsafe_notify_events':null]];
 if(strpos($sql,'INSERT INTO schoolsafe_notify_events')===0)return $eventOk?[1=>['ID'=>17]]:false;
 if(strpos($sql,'INSERT INTO schoolsafe_notify_deliveries')===0)return $deliveryOk?[1=>['ID'=>23]]:false;
 if(strpos($sql,'COUNT(*)')!==false)return [1=>['C'=>1]];
 return [1=>['ID'=>17,'SYEAR'=>2026,'EVENT_TYPE'=>'security.entry','STUDENT_ID'=>42]];
}];
$recipients=[['audience'=>'parent','staff_id'=>27,'channel'=>'feed']];
SchoolSafeNotifyEmit($base,$recipients,$db,false);
scanNotifyCheck('enclosing native business transaction is never nested or committed by emitter',$ops===[]);
$ops=[];SchoolSafeNotifyEmit($base,$recipients,$db);
scanNotifyCheck('standalone legacy emitter retains transaction ownership',$ops===['BEGIN','COMMIT']);
$ops=[];$deliveryOk=false;
scanNotifyCheck('unconfirmed outbox write refuses rather than claiming queued',scanNotifyError(fn()=>SchoolSafeNotifyEmit($base,$recipients,$db,false))==='NOTIFY_WRITE_FAILED');
scanNotifyCheck('enclosing transaction alone owns rollback after outbox failure',$ops===[]);
$deliveryOk=true;$eventOk=false;
scanNotifyCheck('failed event insert is not misread as a dedup replay',scanNotifyError(fn()=>SchoolSafeNotifyEmit($base,$recipients,$db,false))==='NOTIFY_WRITE_FAILED');
$eventOk=true;$available=false;
scanNotifyCheck('missing outbox is explicit failure for atomic scan caller',scanNotifyError(fn()=>SchoolSafeNotifyEmit($base,$recipients,$db,false))==='NOTIFY_UNAVAILABLE');
scanNotifyCheck('standalone legacy caller preserves unavailable null contract',SchoolSafeNotifyEmit($base,$recipients,$db)===null);
$noticeFile=$root.'/ProgramFunctions/SchoolSafeScanNotice.fnc.php';
if(is_file($noticeFile)){
 require_once $noticeFile;require_once $root.'/ProgramFunctions/SchoolSafeFeeSnapshot.fnc.php';require_once $root.'/ProgramFunctions/SchoolSafeMailTemplate.fnc.php';
 $ctx=['school'=>3,'year'=>2026,'user'=>9];
 $model=['id'=>'11111111-1111-4111-8111-111111111111','label'=>'Contrôle synthétique annuel','threshold'=>'80.00','tolerance'=>10,'active'=>true];
 $ledger=['has_data'=>true,'fee_cents'=>7500,'paid_cents'=>7000,'balance_cents'=>500,'status'=>'partial','fee_count'=>2,'overdue_count'=>0,
 'billed_positive_cents'=>10000,'waiver_adjustment_cents'=>-2500,'payments_positive_cents'=>8000,'refund_adjustment_cents'=>-1000,
 'fee_unlinked_adjustment_cents'=>0,'payment_unlinked_adjustment_cents'=>0,'nominal_due_from'=>'2026-10-24','nominal_due_to'=>'2026-10-31'];
 $observation=SchoolSafeFeeControlSnapshot(['student_id'=>42,'student_name'=>'Synthetic child','class_name'=>'Synthetic class'],$ctx,$ledger,$model,SchoolSafeFeeClassify($model,$ledger),['school_name'=>'Synthetic school','currency'=>'SYN','currency_state'=>'known'],'entry');
 $observation['recorded_at']='2026-10-23T10:30:00+00:00';$text=SchoolSafeScanFinancialNotice($observation);
 scanNotifyCheck('financial copy names real observed scope and control',str_contains($text,'Synthetic school')&&str_contains($text,'Synthetic child')&&str_contains($text,'Synthetic class')&&str_contains($text,'2026')&&str_contains($text,$model['label'])&&str_contains($text,$observation['recorded_at']));
 scanNotifyCheck('financial copy retains exact net waiver refund balance and bounds',str_contains($text,'100.00 SYN')&&str_contains($text,'-25.00 SYN')&&str_contains($text,'-10.00 SYN')&&str_contains($text,'75.00 SYN')&&str_contains($text,'70.00 SYN')&&str_contains($text,'5.00 SYN')&&str_contains($text,'67.50 SYN'));
 scanNotifyCheck('financial copy never turns control into a payment receipt',str_contains($text,'pas un reçu')&&!str_contains($text,'Paiement confirmé'));
 scanNotifyCheck('deadline is actual nominal range not invented allocation',str_contains($text,'2026-10-24')&&str_contains($text,'2026-10-31')&&str_contains($text,'ne sont pas affectés par frais'));
 $missing=$observation;$missing['ledger']['currency']=null;$missing['ledger']['currency_state']='missing';$unknown=SchoolSafeScanFinancialNotice($missing);
 scanNotifyCheck('missing currency never defaults to USD or a conversion',str_contains($unknown,'devise non renseignée')&&!str_contains($unknown,'USD')&&!str_contains($unknown,'CDF'));
 $unknownLedger=array_replace($ledger,['has_data'=>false,'status'=>'no_data']);
 $missing=SchoolSafeFeeControlSnapshot(['student_id'=>42],$ctx,$unknownLedger,$model,SchoolSafeFeeClassify($model,$unknownLedger),['school_name'=>'Synthetic school','currency'=>null,'currency_state'=>'missing'],'consult');
 $unknown=SchoolSafeScanFinancialNotice($missing);
 scanNotifyCheck('unavailable money is never transformed into zero debt or yellow',str_contains($unknown,'Total net facturé : non disponible')&&str_contains($unknown,'aucune présomption')&&!str_contains($unknown,'Jaune')&&!str_contains($unknown,'Restant dû observé : 0.00'));
 $mail=SchoolSafeMailBuild(['event_type'=>'financial.control','payload'=>['message'=>$text]],['school_name'=>'Synthetic school','locale'=>'fr']);
 scanNotifyCheck('native email template recognizes control without payment branding',str_contains($mail['subject'],'Contrôle financier')&&!str_contains($mail['subject'],'Paiement confirmé')&&str_contains($mail['text'],'67.50 SYN'));
}else scanNotifyCheck('native financial content adapter exists',false);
require_once $root.'/ProgramFunctions/SchoolSafeScanNotify.fnc.php';
foreach(['cases','absence']as$source){
 $reads=0;$filtered=SchoolSafeScanNotifyRecover($source,['get'=>function()use(&$reads){$reads++;throw new RuntimeException('UNEXPECTED_SCAN_READ');}]);
 scanNotifyCheck('scan reconciliation does not run for unrelated source '.$source,$reads===0&&array_sum($filtered)===0);
}
foreach(['action','student']as$key){
 $imported=$observation;
 if($key==='action')$imported['action']=['qr_payload'=>'SYNTHETIC-NESTED-SECRET'];
 else $imported['student']['name']=['qr_payload'=>'SYNTHETIC-NESTED-SECRET'];
 $audit=['ID'=>17,'STUDENT_ID'=>42,'CONSULTED_BY'=>9,'CONTEXT_SNAPSHOT'=>$imported];
 scanNotifyCheck('nested imported '.$key.' data cannot enter notification payload',scanNotifyError(fn()=>SchoolSafeScanNotifyRecord('financial.control',17,[],$ctx,['get'=>fn()=>[1=>$audit]],false))==='NOTIFY_CONTEXT_UNAVAILABLE');
}
$imported=$observation;$imported['qr_payload']='SYNTHETIC-UNKNOWN-SECRET';$imported['ledger']['phone']='SYNTHETIC-UNKNOWN-SECRET';
$audit=['ID'=>17,'STUDENT_ID'=>42,'CONSULTED_BY'=>9,'CONTEXT_SNAPSHOT'=>$imported];$published='';
$publishDb=['exec'=>static function(){throw new RuntimeException('UNEXPECTED_TRANSACTION');},'get'=>function($sql)use($audit,&$published){
 if(strpos($sql,'FROM schoolsafe_fee_consults')!==false)return [1=>$audit];
 if(strpos($sql,'FROM student_enrollment')!==false)return [1=>['OK'=>1]];
 if(strpos($sql,'FROM students_join_users')!==false)return [];
 if(strpos($sql,'to_regclass')!==false)return [1=>['T'=>'schoolsafe_notify_events']];
 if(strpos($sql,'INSERT INTO schoolsafe_notify_events')===0){$published=$sql;return [1=>['ID'=>17]];}
 if(strpos($sql,'COUNT(*)')!==false)return [1=>['C'=>0]];
 throw new RuntimeException('UNEXPECTED_NOTIFICATION_QUERY');
}];
SchoolSafeScanNotifyRecord('financial.control',17,[],$ctx,$publishDb,false);
scanNotifyCheck('unknown audit JSON properties are removed rather than forwarded',$published!==''&&!str_contains($published,'SYNTHETIC-UNKNOWN-SECRET'));
scanNotifyCheck('unavailable enrollment read never becomes zero recipients',scanNotifyError(fn()=>SchoolSafeNotifyResolveRecipients('financial.control',3,2026,42,['get'=>fn()=>false]))==='NOTIFY_RECIPIENT_UNAVAILABLE');
require_once $root.'/ProgramFunctions/SchoolSafeNotifyRecipient.fnc.php';
scanNotifyCheck('unavailable account metadata is not an active legacy account',scanNotifyError(fn()=>SchoolSafeNotifyAccountReadable('qa-parent',3,['get'=>fn()=>false]))==='NOTIFY_RECIPIENT_UNAVAILABLE');
echo json_encode(['pass'=>$pass,'fail'=>$fail,'scope'=>'Pure synthetic emitter/content, not PostgreSQL, authenticated HTTP, provider or production'])."\n";exit($fail?1:0);
