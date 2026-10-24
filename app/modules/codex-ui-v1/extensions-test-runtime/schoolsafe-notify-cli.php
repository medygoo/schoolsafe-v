<?php
// CLI compatibility with the existing native DB function contracts, so the
// dispatcher calls SchoolSafeWaSend instead of maintaining another WA route.
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
function SchoolSafeNotifyCliInit($connection): void
{
    foreach(['DBQuery','DBGet','DBEscapeString','Config']as$name)if(function_exists($name))throw new RuntimeException('DISPATCH_DB_CONTEXT_CONFLICT');
    $GLOBALS['schoolsafe_notify_connection']=$connection;
    function DBQuery($sql){
        $result=pg_query($GLOBALS['schoolsafe_notify_connection'],$sql);
        if($result===false)throw new RuntimeException('NOTIFY_DB_UNAVAILABLE');return $result;
    }
    function DBGet($query){
        $result=is_string($query)?DBQuery($query):$query;
        if(!$result)throw new RuntimeException('NOTIFY_DB_UNAVAILABLE');
        $rows=[];while($row=pg_fetch_assoc($result))$rows[count($rows)+1]=array_change_key_case($row,CASE_UPPER);return $rows;
    }
    function DBEscapeString($value){return pg_escape_string($GLOBALS['schoolsafe_notify_connection'],(string)$value);}
    function Config($key){
        $result=pg_query_params($GLOBALS['schoolsafe_notify_connection'],'SELECT config_value FROM config WHERE school_id=0 AND title=$1',[(string)$key]);
        if($result===false||pg_num_rows($result)>1)throw new RuntimeException('NOTIFY_CONFIG_UNAVAILABLE');
        $row=pg_fetch_assoc($result);return $row?(string)$row['config_value']:'';
    }
}
