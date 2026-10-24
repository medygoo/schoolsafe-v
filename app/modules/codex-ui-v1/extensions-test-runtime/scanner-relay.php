<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function SchoolSafeScanReply($status,$body) { http_response_code($status); echo json_encode($body,JSON_THROW_ON_ERROR); exit; }
$method=$_SERVER['REQUEST_METHOD'];
$operation=$_GET['operation']??'capabilities';
if(array_diff(array_keys($_GET),['operation']) || !in_array($operation,['capabilities','locations','scan','management','fees','location-management'],true)
    || (in_array($operation,['scan','management','fees','location-management'],true)?$method!=='POST':$method!=='GET')) SchoolSafeScanReply(400,['error'=>'INVALID_REQUEST']);
chdir(__DIR__); $_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level(); ob_start();
try {
 require_once __DIR__.'/Warehouse.php';
 require_once __DIR__.'/modules/SchoolSafe/Gateway.php';
 if(!User('STAFF_ID')) {
   while(ob_get_level()>$level)ob_end_clean();
   SchoolSafeScanReply(401,['error'=>'NATIVE_SESSION_REQUIRED']);
 }
 $user=(int)User('STAFF_ID');$school=(int)UserSchool();$year=(int)UserSyear();
 if($user<1||$school<1||$year<1) throw new RuntimeException('INVALID_CONTEXT');
 if(empty($_SESSION['SchoolSafeScannerCsrf'])) $_SESSION['SchoolSafeScannerCsrf']=bin2hex(random_bytes(32));
 $input=new stdClass();
 if(in_array($operation,['scan','management','fees','location-management'],true)) {
   $csrf=$_SERVER['HTTP_X_SCHOOLSAFE_CSRF']??'';
   if(!is_string($csrf)||!hash_equals($_SESSION['SchoolSafeScannerCsrf'],$csrf)) throw new RuntimeException('CSRF_REQUIRED');
   if(stripos($_SERVER['CONTENT_TYPE']??'','application/json')!==0) throw new RuntimeException('INVALID_INPUT');
   $raw=file_get_contents('php://input',false,null,0,20001);
   if(strlen($raw)>20000) throw new RuntimeException('INVALID_INPUT');
   $input=json_decode($raw,false,16,JSON_THROW_ON_ERROR);
   if(!is_object($input)) throw new RuntimeException('INVALID_INPUT');
   if(SchoolSafeEndpointGateway('scanner-relay.php',$method,$operation,(array)$input)==='deny') throw new RuntimeException('NATIVE_PERMISSION_REQUIRED');
   if($operation==='location-management' && (User('PROFILE')!=='admin'||!AllowEdit('School_Setup/Configuration.php')||!in_array($input->action??'',['list','save'],true))) throw new RuntimeException('NATIVE_PERMISSION_REQUIRED');
   if($operation==='fees' && (User('PROFILE')!=='admin'||!AllowEdit('Student_Billing/StudentFees.php')||!in_array($input->action??'',['list','save','activate','stop'],true))) throw new RuntimeException('NATIVE_STUDENT_NOT_ACCESSIBLE');
   if($operation==='management' && isset($input->nativeStudentId)) {
     if(($input->action??'')==='print-request'&&!AllowUse('Student_ID_Card/StudentIDCard.php')) throw new RuntimeException('NATIVE_STUDENT_NOT_ACCESSIBLE');
     $student=filter_var($input->nativeStudentId,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
     if($student===false) throw new RuntimeException('INVALID_INPUT');
     $sql="SELECT 1 FROM student_enrollment e WHERE e.STUDENT_ID=".(int)$student." AND e.SCHOOL_ID=".$school." AND e.SYEAR=".$year;
     if(User('PROFILE')==='parent') $sql.=" AND EXISTS(SELECT 1 FROM students_join_users u WHERE u.STUDENT_ID=e.STUDENT_ID AND u.STAFF_ID=".$user.")";
     if(!DBGet($sql)) throw new RuntimeException('NATIVE_STUDENT_NOT_ACCESSIBLE');
     $input->nativeStudentId=(string)$student;
   }
 }
 if($method==='GET'&&SchoolSafeEndpointGateway('scanner-relay.php',$method,$operation)==='deny') throw new RuntimeException('NATIVE_PERMISSION_REQUIRED');
 // Lots A1/ENTRY : d'abord en local (Rosario moteur principal). Sinon repli bridge inchangé.
 require_once __DIR__.'/ProgramFunctions/SchoolSafeScanBackend.fnc.php';
 $enrichPortrait=function(array $data) {
   if(isset($data['nativeStudentId'])&&AllowUse('Student_ID_Card/StudentIDCard.php')){
     require_once __DIR__.'/schoolsafe-card-bridge/RosarioCardRead.fnc.php';
     try{$portrait=SchoolSafeReadNativeCardStudent($data['nativeStudentId']);
       $data['photo_url']=$portrait['verified']['photoUrl']??'';
     }catch(RuntimeException $ignored){$data['photo_url']='';}
   }
   return $data;
 };
 if($operation==='scan'&&($input->mode??'')==='verify') {
   if(!is_string($input->qr_payload??null)||strlen($input->qr_payload)>2000) throw new RuntimeException('INVALID_INPUT');
   require_once __DIR__.'/ProgramFunctions/SchoolSafeCardVerify.fnc.php';
   $localSecret=getenv('SCHOOLSAFE_CARD_HMAC_SECRET')?:'';
   $local=null;
   if(preg_match('/^[a-f0-9]{64}$/D',$localSecret)) {
     $localQuery=function($number,$school)use($year){ return SchoolSafeCardVerifyQuery($number,$school,$year); };
     $local=SchoolSafeCardVerifyQr($input->qr_payload,$localSecret,$school,$year,$localQuery);
   }
   if(is_array($local)&&($local['result']!=='INVALID'||($local['reason']??'')!=='card_not_found')) {
     $data=$enrichPortrait($local);
     while(ob_get_level()>$level)ob_end_clean();
     SchoolSafeScanReply(200,['data'=>$data]);
   }
 }
 // Lot ENTRY : sans bridge, capabilities/lieux/entree servis en local (contrats identiques).
 if(!SchoolSafeScanBridgeAvailable()) {
   if($operation==='fees') {
     require_once __DIR__.'/ProgramFunctions/SchoolSafeFeePolicy.fnc.php';
     $db=['get'=>function($sql){return DBGet(DBQuery($sql));},'exec'=>function($sql){DBQuery($sql);},
       'quote'=>function($value){return "'".DBEscapeString($value)."'";}];
     try{$data=SchoolSafeFeePolicyManage((array)$input,
       ['user'=>$user,'school'=>$school,'year'=>$year,'profile'=>User('PROFILE'),
        'can_edit_billing'=>(bool)AllowEdit('Student_Billing/StudentFees.php'),
        'can_read'=>SchoolSafeNativeCommandPermission('SchoolSafe/FeeModelsRead.php'),
        'can_manage'=>SchoolSafeNativeCommandPermission('SchoolSafe/FeeModelsManage.php')],$db);
     }catch(RuntimeException $error){
       $status=['FORBIDDEN'=>403,'INVALID_INPUT'=>400,'INVALID_CONTEXT'=>400][$error->getMessage()]??503;
       while(ob_get_level()>$level)ob_end_clean();SchoolSafeScanReply($status,['error'=>'FEE_CONTROL_UNAVAILABLE']);
     }
     while(ob_get_level()>$level)ob_end_clean();SchoolSafeScanReply(200,['data'=>$data]);
   }
   if($operation==='location-management') {
     require_once __DIR__.'/ProgramFunctions/SchoolSafeScanLocationManage.fnc.php';
     $db=['get'=>function($sql){return DBGet(DBQuery($sql));},
       'quote'=>function($value){return "'".DBEscapeString($value)."'";}];
     try {
       $data=SchoolSafeScanLocationManage((array)$input,
         ['user'=>$user,'school'=>$school,'year'=>$year,'profile'=>User('PROFILE'),
          'can_configure'=>(bool)AllowEdit('School_Setup/Configuration.php'),
          'can_read'=>SchoolSafeNativeCommandPermission('SchoolSafe/ScanLocationsRead.php'),
          'can_save'=>SchoolSafeNativeCommandPermission('SchoolSafe/ScanLocationsManage.php')],$db);
     } catch(RuntimeException $error) {
       $status=['FORBIDDEN'=>403,'INVALID_INPUT'=>400,'NOT_FOUND'=>404][$error->getMessage()]??503;
       while(ob_get_level()>$level)ob_end_clean();
       SchoolSafeScanReply($status,['error'=>'LOCATION_CONFIGURATION_UNAVAILABLE']);
     }
     while(ob_get_level()>$level)ob_end_clean();
     SchoolSafeScanReply(200,['data'=>$data]);
   }
   if($operation==='capabilities') {
     $caps=[];
     if(SchoolSafeNativeCommandPermission('SchoolSafe/ScanArrival.php'))$caps[]='SCAN_ENTRY';
     if(SchoolSafeNativeCommandPermission('SchoolSafe/ScanPickup.php'))$caps[]='SCAN_PICKUP';
     if(SchoolSafeNativeCommandPermission('SchoolSafe/ScanFees.php'))$caps[]='SCAN_FEES';
     if(!empty($RosarioModules['Food_Service'])&&SchoolSafeNativeCommandPermission('SchoolSafe/ScanCanteen.php'))$caps[]='SCAN_CANTEEN';
     if(SchoolSafeNativeCommandPermission('SchoolSafe/ScanVerify.php'))$caps[]='SCAN_VERIFY';
     require_once __DIR__.'/ProgramFunctions/SchoolSafeFeePolicy.fnc.php';
     $activePolicy=SchoolSafeFeePolicyActive(['school'=>$school,'year'=>$year],['get'=>function($sql){return DBGet(DBQuery($sql));}]);
     // A permitted consultation is not proof that a financial barrier is active.
     $result=['data'=>['capabilities'=>SchoolSafeFilterScanCapabilities($caps),'fee_policy_active'=>$activePolicy!==null,
       'fee_consult_available'=>in_array('SCAN_FEES',$caps,true),
       'request_key_modes'=>['entry','fees'],'open_campaign_id'=>null,'schoolId'=>(string)$school]];
     $result['csrfToken']=$_SESSION['SchoolSafeScannerCsrf'];
     while(ob_get_level()>$level)ob_end_clean();
     SchoolSafeScanReply(200,$result);
   }
   if($operation==='locations') {
     require_once __DIR__.'/ProgramFunctions/SchoolSafeScanPassage.fnc.php';
     $modes=[];
     if(SchoolSafeNativeCommandPermission('SchoolSafe/ScanArrival.php'))$modes[]='entry';
     if(SchoolSafeNativeCommandPermission('SchoolSafe/ScanPickup.php'))$modes[]='pickup';
     $list=SchoolSafeScanLocations($school,$year,$modes,['get'=>function($sql){return DBGet($sql);}]);
     while(ob_get_level()>$level)ob_end_clean();
     SchoolSafeScanReply(200,['data'=>['locations'=>$list]]);
   }
   if($operation==='scan'&&($input->mode??'')==='canteen') {
     if(array_diff(array_keys((array)$input),['mode','qr_payload'])||!is_string($input->qr_payload??null)||strlen($input->qr_payload)>2000)throw new RuntimeException('INVALID_INPUT');
     require_once __DIR__.'/ProgramFunctions/SchoolSafeCanteenScan.fnc.php';
     $data=SchoolSafeCanteenScan($input->qr_payload,getenv('SCHOOLSAFE_CARD_HMAC_SECRET')?:'',
       ['user'=>$user,'school'=>$school,'year'=>$year,'detail'=>AllowUse('Food_Service/Accounts.php')===true],
       ['get'=>function($sql){return DBGet(DBQuery($sql));}]);
     if(!is_array($data))throw new RuntimeException('NATIVE_CANTEEN_UNAVAILABLE');
     while(ob_get_level()>$level)ob_end_clean();SchoolSafeScanReply(200,['data'=>$enrichPortrait($data)]);
   }
   if($operation==='scan'&&($input->mode??'')==='pickup') {
     $allowedPickupKeys=['mode','qr_payload','location_id','authorized_person_id','proof_token','human_confirmed'];
     if(!is_string($input->qr_payload??null)||strlen($input->qr_payload)>2000
       ||!is_string($input->location_id??null)
       ||array_diff(array_keys((array)$input),$allowedPickupKeys)) throw new RuntimeException('INVALID_INPUT');
     if(isset($input->authorized_person_id)&&!is_string($input->authorized_person_id)) throw new RuntimeException('INVALID_INPUT');
     if(isset($input->proof_token)&&!is_string($input->proof_token)) throw new RuntimeException('INVALID_INPUT');
     if(isset($input->human_confirmed)&&!is_bool($input->human_confirmed)) throw new RuntimeException('INVALID_INPUT');
     require_once __DIR__.'/ProgramFunctions/SchoolSafePickupScan.fnc.php';
     $pickupSecret=getenv('SCHOOLSAFE_CARD_HMAC_SECRET')?:'';
     require_once __DIR__.'/ProgramFunctions/SchoolSafeScanNotify.fnc.php';
     $db=SchoolSafeScanNotifyAdapter(['get'=>function($sql){ return DBGet(DBQuery($sql)); },'exec'=>function($sql){ DBQuery($sql); }],
       ['user'=>$user,'school'=>$school,'year'=>$year]);
     $done=SchoolSafePickupScan($input->qr_payload,$input->location_id,$pickupSecret,
       ['user'=>$user,'school'=>$school,'year'=>$year],$db,
       ['authorized_person_id'=>$input->authorized_person_id??null,
        'proof_token'=>$input->proof_token??null,
        'human_confirmed'=>$input->human_confirmed??null]);
     if($done===null) throw new RuntimeException('CONFIG_REQUIRED');
     $data=$enrichPortrait($done);
     while(ob_get_level()>$level)ob_end_clean();
     SchoolSafeScanReply(200,['data'=>$data]);
   }
   if($operation==='management'&&in_array($input->action??'',
     ['school-list','parent-list','register-family','register-external','school-family-approve',
      'photo-set','photo-review','validate-authorization','school-retire','parent-suspend','parent-activate'],true)) {
     require_once __DIR__.'/ProgramFunctions/SchoolSafePickupManage.fnc.php';
     $db=['get'=>function($sql){ return DBGet(DBQuery($sql)); },'exec'=>function($sql){ DBQuery($sql); },
       'quote'=>function($value){return "'".DBEscapeString($value)."'";}];
     $isParent=User('PROFILE')==='parent';
     $mctx=['user'=>$user,'school'=>$school,'year'=>$year,'profile'=>User('PROFILE'),'parent'=>$isParent,'root'=>'.'];
     $map=['FORBIDDEN'=>403,'NOT_FOUND'=>404,'PRIMARY_SELECTION_REQUIRED'=>409,'PRIMARY_EXISTS'=>409,
       'LIMIT_REACHED'=>409,'INVALID_STATE'=>409,'PRIMARY_TRANSFER_REQUIRED'=>409,'PRIMARY_PARENT_REQUIRED'=>409,'WRITE_FAILED'=>503,'INVALID_INPUT'=>400,
       'REASON_REQUIRED'=>400,'CONSENT_REQUIRED'=>400,'INVALID_PERSON'=>400,'INVALID_PHOTO'=>400,'INVALID_DECISION'=>400];
     try {
       $action=$input->action;
       $student=(int)($input->nativeStudentId??0);
       if($student<1) throw new RuntimeException('INVALID_INPUT');
       // Les parents sont déjà restreints à leurs enfants (contrôle natif ci-dessus) ;
       // le personnel lit et gère via ses permissions Gateway (contrôle ci-dessus).
       if($action==='school-list'||$action==='parent-list') {
         if($action==='parent-list'&&!$isParent) throw new RuntimeException('FORBIDDEN');
         $data=SchoolSafePickupList($student,$mctx,$db);
       } elseif($action==='register-family') {
         $data=SchoolSafePickupRegisterFamily($student,(array)($input), $mctx,$db);
       } elseif($action==='register-external') {
         $data=SchoolSafePickupRegisterExternal($student,(array)($input),$mctx,$db);
       } elseif($action==='school-family-approve') {
         $data=SchoolSafePickupApproveFamily($student,$input->guardianId??0,
           $input->reason??'',$input->consent??'',$mctx,$db,$input->parentStaffId??null);
       } elseif($action==='photo-set') {
         $data=SchoolSafePickupPhotoSet($student,$input->guardianId??0,$input->photoPath??'',$mctx,$db);
       } elseif($action==='photo-review') {
         $data=SchoolSafePickupPhotoReview($student,$input->guardianId??0,
           $input->decision??'',$input->reason??'',$mctx,$db);
       } elseif($action==='validate-authorization') {
         $data=SchoolSafePickupValidateAuthorization($student,$input->authorizationId??0,$mctx,$db);
       } elseif($action==='school-retire') {
         $data=SchoolSafePickupRetire($student,$input->guardianId??0,
           $input->reason??'',$input->consent??'',$mctx,$db);
       } elseif($action==='parent-suspend') {
         $data=SchoolSafePickupParentSuspend($student,$input->authorizationId??0,$input->reason??'',$mctx,$db);
       } else {
         $data=SchoolSafePickupParentActivate($student,$input->authorizationId??0,$input->reason??'',$mctx,$db);
       }
     } catch(RuntimeException $error) {
       while(ob_get_level()>$level)ob_end_clean();
       $code=$map[$error->getMessage()]??503;
       if($code===400) SchoolSafeScanReply(400,['error'=>'INVALID_REQUEST']);
       $conflict=in_array($error->getMessage(),['PRIMARY_SELECTION_REQUIRED','PRIMARY_PARENT_REQUIRED','PRIMARY_TRANSFER_REQUIRED'],true)?$error->getMessage():'CONFLICT';
       SchoolSafeScanReply($code,['error'=>$code===409?$conflict:($code===404?'NOT_FOUND':'NATIVE_SCANNER_UNAVAILABLE')]);
     }
     while(ob_get_level()>$level)ob_end_clean();
     SchoolSafeScanReply(200,['data'=>$data]);
   }
   // Lot FEES : consultation strictly consultative (billing_fees /
   // billing_payments lus, jamais écrits). Seule écriture : l'historique
   // schoolsafe_fee_consults. Gateway SCAN_FEES déjà contrôlé ci-dessus
   // (403 sans droit) ; le détail montants exige Student_Billing, sinon
   // orientation FEE_STATUS sans montant. Ne touche ni entrée, ni présence,
   // ni pickup (aucun appel, aucune écriture vers ces chemins).
   if($operation==='scan'&&($input->mode??'')==='fees') {
     if(!is_string($input->qr_payload??null)||strlen($input->qr_payload)>2000
       ||!is_string($input->request_key??null)
       ||array_diff(array_keys((array)$input),['mode','qr_payload','request_key'])) throw new RuntimeException('INVALID_INPUT');
     require_once __DIR__.'/ProgramFunctions/SchoolSafeFeeScan.fnc.php';
     $feeSecret=getenv('SCHOOLSAFE_CARD_HMAC_SECRET')?:'';
     require_once __DIR__.'/ProgramFunctions/SchoolSafeScanNotify.fnc.php';
     $db=SchoolSafeScanNotifyAdapter(['get'=>function($sql){ return DBGet(DBQuery($sql)); },'exec'=>function($sql){ DBQuery($sql); }],
       ['user'=>$user,'school'=>$school,'year'=>$year]);
     $detail=AllowUse('Student_Billing/StudentFees.php');
     $done=SchoolSafeFeeScan($input->qr_payload,$feeSecret,
       ['user'=>$user,'school'=>$school,'year'=>$year,'detail'=>$detail,'request_key'=>$input->request_key],$db);
     if($done===null) throw new RuntimeException('CONFIG_REQUIRED');
     $data=$enrichPortrait($done);
     while(ob_get_level()>$level)ob_end_clean();
     SchoolSafeScanReply(200,['data'=>$data]);
   }
   if($operation==='scan'&&($input->mode??'')==='entry') {
     if(array_diff(array_keys((array)$input),['mode','qr_payload','location_id','request_key'])
       ||!is_string($input->request_key??null)||!is_string($input->qr_payload??null)||strlen($input->qr_payload)>2000
       ||!is_string($input->location_id??null)) throw new RuntimeException('INVALID_INPUT');
     require_once __DIR__.'/ProgramFunctions/SchoolSafeEntryScan.fnc.php';
     $entrySecret=getenv('SCHOOLSAFE_CARD_HMAC_SECRET')?:'';
     // DBGet() n'exécute que les SELECT : passer par DBQuery pour INSERT...RETURNING.
     require_once __DIR__.'/ProgramFunctions/SchoolSafeScanNotify.fnc.php';
     $db=SchoolSafeScanNotifyAdapter(['get'=>function($sql){ return DBGet(DBQuery($sql)); },'exec'=>function($sql){ DBQuery($sql); }],
       ['user'=>$user,'school'=>$school,'year'=>$year]);
     $done=SchoolSafeEntryScan($input->qr_payload,$input->location_id,$entrySecret,
       ['user'=>$user,'school'=>$school,'year'=>$year,'request_key'=>$input->request_key],$db);
     if($done===null) throw new RuntimeException('CONFIG_REQUIRED');
     $data=$enrichPortrait($done);
     while(ob_get_level()>$level)ob_end_clean();
     SchoolSafeScanReply(200,['data'=>$data]);
   }
   throw new RuntimeException('CONFIG_REQUIRED');
 }
 $config=json_decode(file_get_contents('/var/lib/schoolsafe-card-bridge/relay-config.json'),true,16,JSON_THROW_ON_ERROR);
 if(($config['environment']??'')!=='local-isolated-test-only'||($config['sourceInstance']??'')!=='codex-addons-local'
   ||!preg_match('/^[a-f0-9]{64}$/D',$config['secretHex']??'')) throw new RuntimeException('CONFIG_REQUIRED');
 $relay=function($operation,$input)use($config,$user,$school,$year){
 $now=time();
 $body=json_encode(['version'=>1,'audience'=>'schoolsafe-scan-bridge','sourceInstance'=>'codex-addons-local',
   'nativeUserId'=>(string)$user,'nativeSchoolId'=>(string)$school,'nativeYear'=>(string)$year,
   'operation'=>$operation,'input'=>$input,'issuedAt'=>$now,'expiresAt'=>$now+30,'nonce'=>bin2hex(random_bytes(32))],JSON_THROW_ON_ERROR);
 $curl=curl_init('http://codex-card-bridge-http:8080/bridge/scanner/'.$operation);
 $response='';
 curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,
   CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>15,CURLOPT_PROTOCOLS=>CURLPROTO_HTTP,
   CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
   CURLOPT_POSTFIELDS=>json_encode(['body'=>$body,'signature'=>hash_hmac('sha256',$body,hex2bin($config['secretHex']))],JSON_THROW_ON_ERROR),
   CURLOPT_WRITEFUNCTION=>function($handle,$chunk)use(&$response){$response.=$chunk;return strlen($response)<=300000?strlen($chunk):0;}]);
 try {
   $ok=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
   if($ok===false||!in_array($status,[200,400,401,403,409,503],true)) throw new RuntimeException('RELAY_UNAVAILABLE');
   $result=json_decode($response,true,32,JSON_THROW_ON_ERROR);
   return [$status,$result];
 } finally {curl_close($curl);}
 };
 if($operation==='scan'&&($input->mode??'')==='canteen'){
   if(array_diff(array_keys((array)$input),['mode','qr_payload'])||!is_string($input->qr_payload??null)||strlen($input->qr_payload)>2000)throw new RuntimeException('INVALID_INPUT');
   require_once __DIR__.'/ProgramFunctions/SchoolSafeCanteenScan.fnc.php';
   if(empty($RosarioModules['Food_Service'])||!SchoolSafeNativeCommandPermission('SchoolSafe/ScanCanteen.php'))throw new RuntimeException('NATIVE_PERMISSION_REQUIRED');
   [$status,$result]=$relay('scan',$input);
   if($status===200&&($result['data']['result']??'')==='CANTEEN_IDENTITY'){
     $student=filter_var($result['data']['nativeStudentId']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
     if($student===false)throw new RuntimeException('NATIVE_STUDENT_NOT_ACCESSIBLE');
     $result=['data'=>SchoolSafeCanteenScanRead($student,$school,$year,[],AllowUse('Food_Service/Accounts.php')===true)];
     $result['data']['nativeStudentId']=$student;
   }
 }elseif($operation==='scan'&&($input->mode??'')==='fees'){
   if(array_diff(array_keys((array)$input),['mode','qr_payload'])||!is_string($input->qr_payload??null))throw new RuntimeException('INVALID_INPUT');
   [$status,$result]=$relay('fees',(object)['action'=>'resolve','qr_payload'=>$input->qr_payload]);
   if($status===200&&isset($result['data']['nativeStudentId'])){
     $student=filter_var($result['data']['nativeStudentId'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
     if($student===false||!DBGet("SELECT 1 FROM student_enrollment WHERE STUDENT_ID=".$student." AND SCHOOL_ID=".$school." AND SYEAR=".$year))throw new RuntimeException('NATIVE_STUDENT_NOT_ACCESSIBLE');
     $where=" WHERE STUDENT_ID=".$student." AND SCHOOL_ID=".$school." AND SYEAR=".$year;
     $billing=DBGet("SELECT (SELECT COUNT(*) FROM billing_fees".$where.") AS FEE_COUNT,(SELECT COALESCE(SUM(AMOUNT),0) FROM billing_fees".$where.") AS FEE_TOTAL,(SELECT COALESCE(SUM(AMOUNT),0)::numeric(14,2) FROM billing_payments".$where.") AS PAID");
     if(!isset($billing[1]['FEE_COUNT'],$billing[1]['FEE_TOTAL'],$billing[1]['PAID']))throw new RuntimeException('NATIVE_BILLING_UNAVAILABLE');
     [$status,$result]=$relay('fees',(object)['action'=>'result','qr_payload'=>$input->qr_payload,'modelId'=>$result['data']['modelId'],'paid'=>(string)$billing[1]['PAID'],'hasData'=>(int)$billing[1]['FEE_COUNT']>0&&(float)$billing[1]['FEE_TOTAL']>0]);
     if($status===200)$result['data']['nativeStudentId']=$student;
   }
 }else{[$status,$result]=$relay($operation,$input);}
 if($status===200&&$operation==='scan'&&isset($result['data']['nativeStudentId'])
    && AllowUse('Student_ID_Card/StudentIDCard.php')){
   // The native card provider rechecks enrollment, school, year and photo access.
   require_once __DIR__.'/schoolsafe-card-bridge/RosarioCardRead.fnc.php';
   try{$portrait=SchoolSafeReadNativeCardStudent($result['data']['nativeStudentId']);
     $result['data']['photo_url']=$portrait['verified']['photoUrl']??'';
   }catch(RuntimeException $ignored){$result['data']['photo_url']='';}
 }
 if($status===200&&$operation==='capabilities'){
   if(!isset($result['data']['capabilities'])||!is_array($result['data']['capabilities']))throw new RuntimeException('RELAY_UNAVAILABLE');
   $result['data']['capabilities']=SchoolSafeFilterScanCapabilities($result['data']['capabilities']);
   if(empty($RosarioModules['Food_Service']))$result['data']['capabilities']=array_values(array_diff($result['data']['capabilities'],['SCAN_CANTEEN']));
   $result['csrfToken']=$_SESSION['SchoolSafeScannerCsrf'];
 }
 // Mutation rights do not implicitly grant the model list returned by the engine.
 if($status===200&&$operation==='fees'&&SchoolSafeEndpointGateway('scanner-relay.php','POST','fees',['action'=>'list'])==='deny')$result['data']=[];
 while(ob_get_level()>$level)ob_end_clean();
 SchoolSafeScanReply($status,$result);
} catch(Throwable $error) {
 while(ob_get_level()>$level)ob_end_clean();
 $status=in_array($error->getMessage(),['CSRF_REQUIRED','NATIVE_STUDENT_NOT_ACCESSIBLE','NATIVE_PERMISSION_REQUIRED'],true)?403:($error->getMessage()==='INVALID_INPUT'?400:($error->getMessage()==='OPERATION_KEY_CONFLICT'?409:503));
 SchoolSafeScanReply($status,['error'=>'NATIVE_SCANNER_UNAVAILABLE']);
}
