<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function SchoolSafePushReply(int $status, array $data): void {
    http_response_code($status); echo json_encode($data, JSON_THROW_ON_ERROR); exit;
}
$level=ob_get_level(); ob_start();
try {
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET','POST'], true) || $_GET || $_POST) SchoolSafePushReply(400,['error'=>'INVALID_REQUEST']);
    chdir(__DIR__); $_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
    require_once __DIR__.'/Warehouse.php';
    require_once __DIR__.'/modules/SchoolSafe/Gateway.php';
    if (!User('STAFF_ID')) throw new RuntimeException('SESSION');
    if (User('PROFILE')!=='parent' || SchoolSafeEndpointGateway('parent-notifications.php','GET','',[])==='deny') throw new RuntimeException('DENIED');
    if (getenv('SCHOOLSAFE_NATIVE_WEBPUSH_ENABLED')!=='1') throw new RuntimeException('DISABLED');
    $key=getenv('SCHOOLSAFE_VAPID_PUBLIC_KEY');
    if (!is_string($key) || !preg_match('/^[A-Za-z0-9_-]{87}$/D',$key)) throw new RuntimeException('DISABLED');
    $user=(int)User('STAFF_ID'); $school=(int)UserSchool(); $year=(int)UserSyear();
    if ($user<1 || $school<1 || $year<1 || $DatabaseType!=='postgresql') throw new RuntimeException('DENIED');
    if ($_SERVER['REQUEST_METHOD']==='GET') {
        $_SESSION['schoolsafe_notification_push_csrf']=$_SESSION['schoolsafe_notification_push_csrf']??bin2hex(random_bytes(32));
        $result=['enabled'=>true,'publicKey'=>$key,'csrf'=>$_SESSION['schoolsafe_notification_push_csrf']];
    } else {
        $csrf=$_SERVER['HTTP_X_SCHOOLSAFE_CSRF']??'';
        if (!is_string($csrf) || empty($_SESSION['schoolsafe_notification_push_csrf']) || !hash_equals($_SESSION['schoolsafe_notification_push_csrf'],$csrf)) throw new RuntimeException('DENIED');
        if (strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json') throw new RuntimeException('INPUT');
        $raw=file_get_contents('php://input',false,null,0,8193);
        if ($raw===false || strlen($raw)>8192) throw new RuntimeException('INPUT');
        $input=json_decode($raw,true,8,JSON_THROW_ON_ERROR);
        if (!is_array($input) || array_keys($input)!==['subscription'] || !is_array($input['subscription'])) throw new RuntimeException('INPUT');
        $s=$input['subscription'];
        if (array_diff(array_keys($s),['endpoint','expirationTime','keys']) || !is_string($s['endpoint']??null) || strlen($s['endpoint'])>2048
            || !is_array($s['keys']??null) || count($s['keys'])!==2 || !preg_match('/^[A-Za-z0-9_-]{87}$/D',$s['keys']['p256dh']??'')
            || !preg_match('/^[A-Za-z0-9_-]{22}$/D',$s['keys']['auth']??'') || ($s['expirationTime']??null)!==null) throw new RuntimeException('INPUT');
        $url=parse_url($s['endpoint']);
        if (($url['scheme']??'')!=='https' || isset($url['user']) || isset($url['pass']) || isset($url['port']) || isset($url['fragment'])
            || !in_array($url['host']??'',['fcm.googleapis.com','updates.push.services.mozilla.com','web.push.apple.com'],true)) throw new RuntimeException('INPUT');
        // Parameterized SQL uses the existing Rosario PostgreSQL connection only.
        $query=@pg_query_params($db_connection,'INSERT INTO schoolsafe_notification_push_subscriptions
            (staff_id,school_id,syear,endpoint,p256dh,auth) VALUES ($1,$2,$3,$4,$5,$6)
            ON CONFLICT (endpoint) DO UPDATE SET p256dh=EXCLUDED.p256dh,auth=EXCLUDED.auth,updated_at=clock_timestamp()
            WHERE schoolsafe_notification_push_subscriptions.staff_id=EXCLUDED.staff_id
              AND schoolsafe_notification_push_subscriptions.school_id=EXCLUDED.school_id
              AND schoolsafe_notification_push_subscriptions.syear=EXCLUDED.syear RETURNING staff_id',
            [$user,$school,$year,$s['endpoint'],$s['keys']['p256dh'],$s['keys']['auth']]);
        if ($query===false || pg_num_rows($query)!==1) throw new RuntimeException('DISABLED');
        $result=['saved'=>true];
    }
    while(ob_get_level()>$level) ob_end_clean();
    SchoolSafePushReply(200,$result);
} catch (Throwable $error) {
    while(ob_get_level()>$level) ob_end_clean();
    $status=['SESSION'=>401,'DENIED'=>403,'INPUT'=>400][$error->getMessage()]??503;
    SchoolSafePushReply($status,['error'=>'PUSH_UNAVAILABLE']);
}
