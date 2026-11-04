<?php
// Ecoute de JASPE (04/11) : la voix enregistree sur le telephone est transcrite par Whisper (Groq, offre
// gratuite, ~1 s), dans tous les navigateurs. Meme controle d'acces que la voix de JASPE (session, droit,
// jeton). L'audio n'est pas garde ; le texte obtenu passe ensuite par la conversation, ou les noms d'eleves
// sont masques avant l'IA. Langues : francais par defaut, lingala, swahili, anglais.
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('Content-Type: application/json');
function SchoolSafeListenFail(int $code,string $error): void{http_response_code($code);exit(json_encode(['error'=>$error]));}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')SchoolSafeListenFail(405,'INVALID_METHOD');
if(array_diff(array_keys($_GET),['langue']))SchoolSafeListenFail(400,'INVALID_REQUEST');
$lang=is_string($_GET['langue']??null)&&in_array($_GET['langue'],['fr','ln','sw','en'],true)?$_GET['langue']:'fr';
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];$level=ob_get_level();ob_start();require_once 'Warehouse.php';
while(ob_get_level()>$level)ob_end_clean();
if(!User('STAFF_ID')&&!UserStudentID())SchoolSafeListenFail(401,'AUTH_REQUIRED');
if(empty($RosarioModules['SchoolSafe_Jaspe'])||!AllowUse('SchoolSafe_Jaspe/Assistant.php'))SchoolSafeListenFail(403,'LISTEN_ACCESS_DENIED');
$token=$_SERVER['HTTP_X_JASPE_TOKEN']??'';
if(!is_string($token)||empty($_SESSION['schoolsafe_jaspe_token'])||!hash_equals($_SESSION['schoolsafe_jaspe_token'],$token))SchoolSafeListenFail(403,'INVALID_TOKEN');
$now=time();$hits=array_values(array_filter($_SESSION['jaspe_listen_hits']??[],static fn($hit)=>is_int($hit)&&$now-$hit<60));
if(count($hits)>=15)SchoolSafeListenFail(429,'LISTEN_RATE_LIMITED');$hits[]=$now;$_SESSION['jaspe_listen_hits']=$hits;
session_write_close();
$type=strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]));
$ext=['audio/webm'=>'webm','audio/ogg'=>'ogg','audio/mp4'=>'m4a','audio/mpeg'=>'mp3','audio/wav'=>'wav','audio/x-wav'=>'wav'][$type]??null;
if($ext===null)SchoolSafeListenFail(415,'AUDIO_TYPE');
$audio=file_get_contents('php://input',false,null,0,3000001);
if(!is_string($audio)||strlen($audio)<800)SchoolSafeListenFail(400,'AUDIO_TOO_SHORT');
if(strlen($audio)>3000000)SchoolSafeListenFail(413,'AUDIO_TOO_LONG');
// Le service est celui de la conversation de JASPE (fichiers prives du serveur) ; seul Groq est accepte.
$dir='/var/lib/schoolsafe-jaspe';
$url=is_file($dir.'/fallback-url')?trim((string)file_get_contents($dir.'/fallback-url')):'';
$key=is_file($dir.'/fallback-key')?trim((string)file_get_contents($dir.'/fallback-key')):'';
if($key===''||!preg_match('#^https://api\.groq\.com/#',$url))SchoolSafeListenFail(503,'LISTEN_NOT_CONFIGURED');
$tmp=tempnam(sys_get_temp_dir(),'jaspe-ecoute-');file_put_contents($tmp,$audio);
try{
    $curl=curl_init('https://api.groq.com/openai/v1/audio/transcriptions');
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>20,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key],
        CURLOPT_POSTFIELDS=>['file'=>new CURLFile($tmp,$type,'voix.'.$ext),'model'=>'whisper-large-v3-turbo','language'=>$lang,'response_format'=>'json','temperature'=>'0']]);
    $out=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
}finally{@unlink($tmp);}
if($out===false)SchoolSafeListenFail(503,'LISTEN_UNAVAILABLE');
if($status===429)SchoolSafeListenFail(429,'LISTEN_BUSY');
if($status!==200)SchoolSafeListenFail(503,'LISTEN_PROVIDER_ERROR');
$text=trim((string)(json_decode((string)$out,true)['text']??''));
echo json_encode(['text'=>mb_substr($text,0,2000)],JSON_UNESCAPED_UNICODE);
