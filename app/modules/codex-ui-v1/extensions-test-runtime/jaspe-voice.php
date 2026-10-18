<?php
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
function SchoolSafeVoiceFail(int $code,string $error): void{http_response_code($code);header('Content-Type: application/json');exit(json_encode(['error'=>$error]));}
$method=$_SERVER['REQUEST_METHOD']??'';
if(!in_array($method,['GET','POST'],true)||$_GET)SchoolSafeVoiceFail(405,'INVALID_METHOD');
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];$level=ob_get_level();ob_start();require_once 'Warehouse.php';
while(ob_get_level()>$level)ob_end_clean();
if(!User('STAFF_ID')&&!UserStudentID())SchoolSafeVoiceFail(401,'AUTH_REQUIRED');
if(empty($RosarioModules['SchoolSafe_Jaspe'])||!AllowUse('SchoolSafe_Jaspe/Assistant.php'))SchoolSafeVoiceFail(403,'VOICE_ACCESS_DENIED');
$token=$_SERVER['HTTP_X_JASPE_TOKEN']??'';
if(!is_string($token)||empty($_SESSION['schoolsafe_jaspe_token'])||!hash_equals($_SESSION['schoolsafe_jaspe_token'],$token))SchoolSafeVoiceFail(403,'INVALID_TOKEN');
$key=@file_get_contents('/var/lib/schoolsafe-voice.token');
if(!is_string($key)||!preg_match('/^[a-f0-9]{64}$/D',trim($key)))SchoolSafeVoiceFail(503,'VOICE_NOT_CONFIGURED');
$body=null;
if($method==='POST'){
 if(strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json')SchoolSafeVoiceFail(415,'JSON_REQUIRED');
 $raw=file_get_contents('php://input',false,null,0,2049);$input=$raw===false?null:json_decode($raw,true);
 if(strlen($raw?:'')>2048||!is_array($input)||array_keys($input)!==['text']||!is_string($input['text'])||trim($input['text'])===''||mb_strlen($input['text'])>220||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$input['text']))SchoolSafeVoiceFail(400,'INVALID_TEXT');
 $now=time();$hits=array_values(array_filter($_SESSION['jaspe_voice_hits']??[],static fn($hit)=>is_int($hit)&&$now-$hit<60));
 if(count($hits)>=12)SchoolSafeVoiceFail(429,'VOICE_RATE_LIMITED');$hits[]=$now;$_SESSION['jaspe_voice_hits']=$hits;
 $body=json_encode(['text'=>SchoolSafeBrandText($input['text'])],JSON_THROW_ON_ERROR);
}
session_write_close();
$curl=curl_init('http://host.docker.internal:18893/'.($method==='GET'?'health':'synthesize'));$output='';
curl_setopt_array($curl,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>$method==='GET'?5:120,CURLOPT_PROTOCOLS=>CURLPROTO_HTTP,
 CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.trim($key)],
 CURLOPT_WRITEFUNCTION=>static function($handle,$chunk)use(&$output){if(strlen($output)+strlen($chunk)>8000000)return 0;$output.=$chunk;return strlen($chunk);}]);
if($method==='POST')curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body]);
$ok=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
if($ok===false)SchoolSafeVoiceFail(503,'VOICE_LOCAL_UNAVAILABLE');
if($status!==200)SchoolSafeVoiceFail($status===429?429:503,'VOICE_NOT_READY');
if($method==='GET'){
 $health=json_decode($output,true);header('Content-Type: application/json');echo json_encode(['enabled'=>($health['ready']??false)===true,'engine'=>'local-reference']);exit;
}
if(substr($output,0,4)!=='RIFF'||substr($output,8,4)!=='WAVE')SchoolSafeVoiceFail(503,'INVALID_AUDIO');
header('Content-Type: audio/wav');header('Content-Length: '.strlen($output));echo $output;
