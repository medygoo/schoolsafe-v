<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../config.inc.php';
function collectorConnection()
{
    global $DatabaseName,$DatabaseServer,$DatabasePort,$DatabaseUsername,$DatabasePassword;
    if($DatabaseName!=='rosario_addons_test'||$DatabaseServer!=='codex-addons-db')throw new RuntimeException('ISOLATED_DATABASE_REQUIRED');
    $quote=static function($v){return "'".str_replace(["\\","'"],["\\\\","\\'"],(string)$v)."'";};
    $connection=@pg_connect('connect_timeout=3 host='.$quote($DatabaseServer).' port='.$quote($DatabasePort).' dbname='.$quote($DatabaseName).' user='.$quote($DatabaseUsername).' password='.$quote($DatabasePassword));
    if(!$connection)throw new RuntimeException('DATABASE_UNAVAILABLE');return $connection;
}
$connection=collectorConnection();
if(($argv[1]??'')==='--job'){
    $id=filter_var($argv[2]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$id)exit(2);
    $result=pg_query_params($connection,'SELECT c.actor_id,c.syear,d.school_id FROM schoolsafe_device_collectors c JOIN schoolsafe_devices d ON d.id=c.device_id WHERE c.device_id=$1 AND c.enabled=true AND d.enabled=true',[$id]);
    $job=pg_fetch_assoc($result);if(!$job)exit(0);
    ob_start(static function($buffer){return '';});session_name('RosarioSIS');session_id(bin2hex(random_bytes(24)));session_start();
    $_SESSION=['STAFF_ID'=>(int)$job['actor_id'],'UserSchool'=>(int)$job['school_id'],'UserSyear'=>(int)$job['syear'],'DefaultSyear'=>(int)$job['syear'],'locale'=>'fr_FR.utf8'];session_write_close();
    $_SERVER['REQUEST_METHOD']='GET';$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];chdir(__DIR__.'/..');require 'Warehouse.php';
    require 'ProgramFunctions/SchoolSafeDeviceCollector.fnc.php';
    $locked=false;$ok=false;
    try{
        $locked=in_array(DBGetOne("SELECT pg_try_advisory_lock(23013,$id)"),[true,'t',1,'1'],true);
        if($locked){
        SchoolSafeCollectorContext($id);
        // Drain the durable queue even while the device is offline.
        $counts=SchoolSafeCollectorDeliver($id);
        $received=SchoolSafeCollectorCapture($id);
        $more=SchoolSafeCollectorDeliver($id);
        DBQuery("UPDATE schoolsafe_device_collectors SET failures=0,last_error=NULL,last_attempt_at=now(),last_success_at=now(),next_attempt_at=now()+interval_seconds*INTERVAL '1 second' WHERE device_id=$id");
        }
        $ok=true;
    }catch(Throwable $error){
        $known=['COLLECTOR_PERMISSION_REQUIRED','COLLECTOR_CONTEXT_DENIED','COLLECTOR_EVENT_COLLISION','COLLECTOR_CONTEXT_CHANGED','COLLECTOR_CREDENTIALS_INVALID','COLLECTOR_KEY_UNAVAILABLE'];
        $code=in_array($error->getMessage(),$known,true)?$error->getMessage():'COLLECTOR_RETRY_REQUIRED';
        pg_query_params($connection,"UPDATE schoolsafe_device_collectors SET failures=LEAST(failures+1,20),last_attempt_at=now(),last_error=$2,next_attempt_at=now()+LEAST(3600,30*power(2,LEAST(failures,7))) * INTERVAL '1 second' WHERE device_id=$1",[$id,$code]);
    }finally{
        if($locked)DBGetOne("SELECT pg_advisory_unlock(23013,$id)");
        if(session_status()===PHP_SESSION_ACTIVE)session_destroy();
        while(ob_get_level())ob_end_clean();
    }
    echo json_encode(['device_id'=>$id,'ok'=>$ok],JSON_THROW_ON_ERROR)."\n";exit($ok?0:1);
}
if(($argv[1]??'')!=='--supervise')exit(2);
while(true){
    try{
        file_put_contents('/tmp/device-collector-heartbeat',(string)time());
        $result=@pg_query($connection,'SELECT c.device_id FROM schoolsafe_device_collectors c JOIN schoolsafe_devices d ON d.id=c.device_id WHERE c.enabled=true AND d.enabled=true AND c.next_attempt_at<=now() ORDER BY c.next_attempt_at LIMIT 20');
        if(!$result){$connection=collectorConnection();sleep(10);continue;}
        while($job=pg_fetch_assoc($result)){
            file_put_contents('/tmp/device-collector-heartbeat',(string)time());
            pg_query_params($connection,"UPDATE schoolsafe_device_collectors SET last_attempt_at=now(),next_attempt_at=now()+interval_seconds*INTERVAL '1 second' WHERE device_id=$1",[$job['device_id']]);
            $process=proc_open([PHP_BINARY,__FILE__,'--job',(string)$job['device_id']],[0=>['file','/dev/null','r'],1=>STDOUT,2=>STDERR],$pipes);
            if(is_resource($process))proc_close($process);
        }
    }catch(Throwable $error){echo "Collector waiting for local database.\n";}
    sleep(10);
}
