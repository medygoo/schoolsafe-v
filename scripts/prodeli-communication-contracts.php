<?php
/** Execute real page renderers; Warehouse, reads, actor and writes are substituted. No network. */
declare(strict_types=1);
$root=getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT')?:'/var/www/html';
if (($argv[1]??'')==='--render') {
    $kind=$argv[2];$mode=$argv[3];$GLOBALS['queries']=[];
    function SchoolSafeGlobalAdmin(){return ($GLOBALS['argv'][3]??'')!=='denied';}
    function User($key){return 'fixture';}
    function Config($key){return 'N';}
    function DBEscapeString($s){return str_replace("'","''",$s);}
    function DBQuery($q){$GLOBALS['queries'][]=$q;return true;}
    function DBGet($q){
        if(str_contains($q,'schoolsafe_sms_lines'))return [1=>['ID'=>1,'OPERATOR'=>'vodacom','PHONE_E164'=>'+243810000001','DEVICE_ID'=>'fixture-device','SIM_NUMBER'=>2,'QUOTA_TOTAL'=>87,'QUOTA_USED'=>7,'LEFT_Q'=>80,'ALERT_THRESHOLD'=>9,'STATUS'=>'disabled','CONN_STATE'=>'unknown'],2=>['ID'=>2,'OPERATOR'=>'airtel','PHONE_E164'=>'+243990000002','DEVICE_ID'=>'fixture-other','SIM_NUMBER'=>1,'QUOTA_TOTAL'=>63,'QUOTA_USED'=>3,'LEFT_Q'=>60,'ALERT_THRESHOLD'=>6,'STATUS'=>'disabled','CONN_STATE'=>'unknown']];
        if(str_contains($q,'schoolsafe_wa_sessions'))return [1=>['ID'=>1,'ENGINE'=>'waha','SESSION_NAME'=>'fixture-main','PHONE_E164'=>'+243810000001','STATUS'=>'disabled','CONN_STATE'=>'unknown'],2=>['ID'=>2,'ENGINE'=>'evolution','SESSION_NAME'=>'fixture-backup','PHONE_E164'=>'+243990000002','STATUS'=>'disabled','CONN_STATE'=>'unknown']];
        return [];
    }
    $_SERVER['REQUEST_METHOD']=$mode==='csrf'?'POST':'GET';$_SESSION=['token'=>'fixture-only-token'];$_POST=$mode==='csrf'?['token'=>'wrong','confirm'=>'1']:[];$_GET=[$kind==='sms'?'line':'session'=>'2'];
    $file=$root.'/plateforme-prodeli-'.$kind.'.php';$code=file_get_contents($file);
    $code=str_replace("require_once __DIR__ . '/Warehouse.php';",'', $code);
    $code=str_replace('__DIR__',var_export($root,true),$code);
    ob_start();eval('?>'.$code);$html=ob_get_clean();
    echo json_encode(['html'=>$html,'writes'=>$GLOBALS['queries']]);exit;
}
$pass=0;$fail=0;
function check($name,$condition){global $pass,$fail;echo ($condition?'PASS ':'FAIL ').$name."\n";$condition?$pass++:$fail++;}
foreach(['sms','whatsapp'] as $kind){
    $cmd=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' --render '.$kind.' ';
    $raw=shell_exec($cmd.'get');$result=json_decode($raw??'',true);$html=$result['html']??'';
    check($kind.' page renders',is_array($result)&&str_contains($html,'<html'));
    check($kind.' original logo',str_contains($html,'src="/schoolsafe-logo.png"'));
    check($kind.' direct dashboard return',str_contains($html,'href="/schoolsafe-administrators.php?home=1#sskc-communications-title"'));
    check($kind.' scoped SchoolSafe workspace',str_contains($html,'class="sskc-shell ss-communication"'));
    check($kind.' shared styling',str_contains($html,'/assets/css/prodeli-communication.css?v=1'));
    check($kind.' scrollable keyboard tables',str_contains($html,'class="sskc-table" tabindex="0"'));
    check($kind.' GET never writes',($result['writes']??null)===[]);
    check($kind.' existing number prefilled',str_contains($html,'name="phone" value="+243990000002"'));
    check($kind.' row edit links',str_contains($html,$kind==='sms'?'?line=2#t-edit':'?session=2#t-edit'));
    check($kind.' selected record locked in save form',str_contains($html,$kind==='sms'?'name="line_id" value="2"':'name="session_id" value="2"'));
    $denied=shell_exec($cmd.'denied');check($kind.' scoped actor denied',str_contains($denied??'','Acces reserve')&&!str_contains($denied??'','name="phone"'));
    $csrf=json_decode(shell_exec($cmd.'csrf')??'',true);check($kind.' invalid CSRF no writes',($csrf['writes']??null)===[]&&str_contains($csrf['html']??'','Requete expiree'));
    check($kind.' clear configuration-only notice',str_contains($html,'ne change pas les destinataires'));
    if($kind==='sms')check('SMS quota and device retained',str_contains($html,'name="quota_total" inputmode="numeric" value="63"')&&str_contains($html,'name="device_id" value="fixture-other"'));
    else check('WhatsApp existing session retained',str_contains($html,'name="session_name" value="fixture-backup"'));
}
echo json_encode(['passed'=>$pass,'failed'=>$fail])."\n";exit($fail?1:0);
