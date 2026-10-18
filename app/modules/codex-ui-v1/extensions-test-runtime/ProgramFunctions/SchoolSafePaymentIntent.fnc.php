<?php
function SchoolSafePaymentIntentQuery(string $sql,array $params=[]){
 global $db_connection;
 $result=@pg_query_params($db_connection,$sql,$params);
 if(!$result)throw new RuntimeException('PAYMENT_STORAGE_UNAVAILABLE',503);
 return $result;
}
function SchoolSafePaymentIntentScope(int $student): array{
 global $DatabaseType;
 if($DatabaseType!=='postgresql'||User('PROFILE')!=='admin'||!AllowUse('Student_Billing/StudentPayments.php')||!AllowEdit('Student_Billing/StudentPayments.php'))throw new RuntimeException('PAYMENT_ACCESS_DENIED',403);
 $user=(int)User('STAFF_ID');$school=(int)UserSchool();$year=(int)UserSyear();$scope=['WHERE'=>' AND s.STUDENT_ID='.$student];
 if($user<1||$student<1||$school<1||$year<1||!GetStuList($scope)
  ||!DBGetOne("SELECT 1 FROM student_enrollment WHERE student_id=$student AND school_id=$school AND syear=$year LIMIT 1"))throw new RuntimeException('STUDENT_SCOPE_REQUIRED',403);
 return [$user,$school,$year];
}
function SchoolSafePaymentDraftId($id): string{
 if(!is_string($id)||!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD',$id))throw new RuntimeException('INVALID_DRAFT_ID',400);
 return strtolower($id);
}
function SchoolSafePaymentIntentStatus(array $intent): array{
 if(!$intent['payment_id'])return ['state'=>'prepared'];
 $payment=pg_fetch_assoc(SchoolSafePaymentIntentQuery('SELECT p.id,(p.amount*100)::bigint AS amount_minor,p.payment_date,p.comments,r.status FROM billing_payments p LEFT JOIN schoolsafe_payment_receipts r ON r.id=$2 WHERE p.id=$1',[$intent['payment_id'],$intent['receipt_id']]));
 if(!$payment||$payment['status']!=='valid')return ['state'=>'altered','paymentId'=>(int)$intent['payment_id'],'receiptId'=>(int)$intent['receipt_id']];
 return ['state'=>'confirmed','paymentId'=>(int)$intent['payment_id'],'receiptId'=>(int)$intent['receipt_id'],
  'confirmedAmountMinor'=>(int)$payment['amount_minor'],'confirmedDate'=>$payment['payment_date'],
  'confirmedComment'=>$payment['comments'],'currency'=>$intent['currency']];
}
function SchoolSafeConfirmPaymentIntent(string $id,array $raw): array{
 global $db_connection;
 $student=(int)UserStudentID();[$user,$school,$year]=SchoolSafePaymentIntentScope($student);$id=SchoolSafePaymentDraftId($id);
 if(array_diff(array_keys($raw),['AMOUNT','PAYMENT_DATE','COMMENTS','LUNCH_PAYMENT','FILE_ATTACHED'])
   ||!is_string($raw['AMOUNT']??null)||!preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D',$raw['AMOUNT'])
   ||!is_string($raw['PAYMENT_DATE']??null)||!is_string($raw['COMMENTS']??null)||mb_strlen($raw['COMMENTS'])>250
   ||trim($raw['COMMENTS'])===''||preg_match('/[\x00-\x1f\x7f]/',$raw['COMMENTS'])
   ||!in_array($raw['LUNCH_PAYMENT']??'',['','N'],true)||!empty($raw['FILE_ATTACHED']))throw new RuntimeException('INVALID_PAYMENT_FIELDS',400);
 foreach($_FILES as $file){if(!empty($file['name']))throw new RuntimeException('DRAFT_ATTACHMENT_UNSUPPORTED',400);}
 [$whole,$fraction]=array_pad(explode('.',$raw['AMOUNT'],2),2,'');$minor=(int)$whole*100+(int)str_pad($fraction,2,'0');
 if($minor<1||$minor>999999999)throw new RuntimeException('INVALID_AMOUNT',400);
 $amount=intdiv($minor,100).'.'.str_pad((string)($minor%100),2,'0',STR_PAD_LEFT);
 $date=DateTimeImmutable::createFromFormat('!Y-m-d',$raw['PAYMENT_DATE'],new DateTimeZone('Africa/Kinshasa'));
 if(!$date||$date->format('Y-m-d')!==$raw['PAYMENT_DATE']||$date->format('Y')<'1900'||$date>new DateTimeImmutable('today',new DateTimeZone('Africa/Kinshasa')))throw new RuntimeException('INVALID_PAYMENT_DATE',400);
 $comment=$raw['COMMENTS'];$currency=(string)Config('CURRENCY');
 $hash=hash('sha256',json_encode([$student,$amount,$date->format('Y-m-d'),$comment,$currency],JSON_THROW_ON_ERROR));
 if(!pg_query($db_connection,'BEGIN'))throw new RuntimeException('PAYMENT_STORAGE_UNAVAILABLE',503);
 try{
  $intent=pg_fetch_assoc(SchoolSafePaymentIntentQuery('SELECT * FROM schoolsafe_pwa_payment_intents WHERE staff_id=$1 AND school_id=$2 AND syear=$3 AND draft_id=$4 FOR UPDATE',[$user,$school,$year,$id]));
  if(!$intent||(int)$intent['student_id']!==$student||$intent['currency']!==$currency)throw new RuntimeException('PAYMENT_INTENT_REQUIRED',403);
  if($intent['payment_id']){
   if(!hash_equals($intent['confirmed_hash'],$hash))throw new RuntimeException('PAYMENT_REPLAY_CONFLICT',409);
   $result=SchoolSafePaymentIntentStatus($intent);
   if($result['state']!=='confirmed')throw new RuntimeException('PAYMENT_ALREADY_ALTERED',409);
  }else{
   $prepared=$_SESSION['schoolsafe_payment_draft']??[];
   if(($prepared['draft_id']??'')!==$id||($prepared['expires']??0)<time())throw new RuntimeException('PAYMENT_REVIEW_EXPIRED',409);
   SchoolSafePaymentIntentQuery('SELECT student_id FROM students WHERE student_id=$1 FOR UPDATE',[$student]);
   $payment=pg_fetch_assoc(SchoolSafePaymentIntentQuery('INSERT INTO billing_payments(school_id,syear,student_id,amount,payment_date,comments,created_by) VALUES($1,$2,$3,$4,$5,$6,$7) RETURNING id',[$school,$year,$student,$amount,$date->format('Y-m-d'),$comment,(string)User('NAME')]));
   $receipt=pg_fetch_assoc(SchoolSafePaymentIntentQuery("SELECT id FROM schoolsafe_payment_receipts WHERE source='billing' AND source_id=$1 AND status='valid' ORDER BY revision DESC LIMIT 1",[$payment['id']]));
   if(!$receipt)throw new RuntimeException('PAYMENT_RECEIPT_UNAVAILABLE',503);
   SchoolSafePaymentIntentQuery('UPDATE schoolsafe_pwa_payment_intents SET confirmed_hash=$5,payment_id=$6,receipt_id=$7,confirmed_at=CURRENT_TIMESTAMP WHERE staff_id=$1 AND school_id=$2 AND syear=$3 AND draft_id=$4',[$user,$school,$year,$id,$hash,$payment['id'],$receipt['id']]);
   $result=['state'=>'confirmed','paymentId'=>(int)$payment['id'],'receiptId'=>(int)$receipt['id']];
  }
  if(!pg_query($db_connection,'COMMIT'))throw new RuntimeException('PAYMENT_STORAGE_UNAVAILABLE',503);
  return $result;
 }catch(Throwable $error){pg_query($db_connection,'ROLLBACK');throw $error;}
}
