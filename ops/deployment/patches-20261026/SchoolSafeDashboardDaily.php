<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__));
require 'config.inc.php';
require 'ProgramFunctions/SchoolSafeDashboardNative.fnc.php';
$pdo = new PDO("pgsql:host=$DatabaseServer;port=$DatabasePort;dbname=$DatabaseName", $DatabaseUsername, $DatabasePassword,
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if ($DatabaseName !== 'rosario_addons_test') throw new RuntimeException('ISOLATED_DATABASE_REQUIRED');
function DBGet($sql) {
    global $pdo;
    return array_map(static function ($row) { return array_change_key_case($row, CASE_UPPER); }, $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
}
$pdo->exec("SET statement_timeout='10s'");
$pdo->exec("SET TIME ZONE 'Africa/Kinshasa'");
$day = (new DateTimeImmutable('today', new DateTimeZone('Africa/Kinshasa')))->format('Y-m-d');
$schools = $pdo->query("SELECT DISTINCT s.id,s.syear FROM schools s JOIN school_marking_periods mp ON mp.school_id=s.id AND mp.syear=s.syear
    WHERE CURRENT_DATE BETWEEN mp.start_date AND mp.end_date ORDER BY s.id,s.syear")->fetchAll(PDO::FETCH_ASSOC);
$processed = 0;
foreach ($schools as $school) {
    $id = (int)$school['id']; $year = (int)$school['syear'];
    $pdo->beginTransaction();
    try {
        $lock=$pdo->prepare('SELECT pg_try_advisory_xact_lock(934761,?)'); $lock->execute([$id]);
        if (!$lock->fetchColumn()) { $pdo->rollBack(); continue; }
        $settings=$pdo->prepare('SELECT title,config_value FROM config WHERE school_id=?'); $settings->execute([$id]);
        $flags=$settings->fetchAll(PDO::FETCH_KEY_PAIR);
        if (($flags['SCHOOLSAFE_BIRTHDAYS']??'')==='Y') {
            try {
                $insert=$pdo->prepare('INSERT INTO schoolsafe_birthday_alerts(school_id,syear,student_id,birthday_date,notice_date,school_open)
                    VALUES(?,?,?,?,CAST(? AS date)-7,?) ON CONFLICT DO NOTHING');
                foreach (SchoolSafeBirthdayRows($id,$year) as $row) {
                    $b=$row['BIRTHDAY'];
                    $insert->execute([$id,$year,$row['STUDENT_ID'],$b['date'],$b['date'],$b['school_open']?'true':'false']);
                }
            } catch (RuntimeException $scopeTooLarge) {
                // Plus de 200 anniversaires sur 8 jours : on saute le bloc, le job continue.
            }
        }
        if (($flags['SCHOOLSAFE_PALMARES']??'')==='Y') {
            $classes=$pdo->prepare('SELECT course_period_id FROM course_periods WHERE school_id=? AND syear=? ORDER BY course_period_id');
            $classes->execute([$id,$year]);
            $scopes=array_merge([0],array_map('intval',$classes->fetchAll(PDO::FETCH_COLUMN)));
            foreach ($scopes as $scope) {
                $run=$pdo->prepare('INSERT INTO schoolsafe_dashboard_runs(school_id,syear,scope_id,snapshot_date) VALUES(?,?,?,?)
                    ON CONFLICT(school_id,syear,scope_id,snapshot_date) DO UPDATE SET updated_at=clock_timestamp()');
                $run->execute([$id,$year,$scope,$day]);
                // Refresh only today's matrix, never previous days used for progression.
                $delete=$pdo->prepare('DELETE FROM schoolsafe_palmares_snapshots WHERE school_id=? AND syear=? AND scope_id=? AND snapshot_date=?');
                $delete->execute([$id,$year,$scope,$day]);
                $insert=$pdo->prepare("INSERT INTO schoolsafe_palmares_snapshots
                    (school_id,syear,scope_id,snapshot_date,student_id,score,position,marking_period_id,score_basis)
                    VALUES(?,?,?,?,?,?,?,?,'native_weighted')");
                foreach (SchoolSafeAcademicTopTen($id,$year,$scope?:null) as $row) {
                    $insert->execute([$id,$year,$scope,$day,$row['STUDENT_ID'],$row['SCORE'],$row['POSITION'],$row['MARKING_PERIOD_ID']]);
                }
            }
        }
        $pdo->commit(); $processed++;
    } catch (Throwable $error) { $pdo->rollBack(); throw $error; }
}
echo json_encode(['status'=>'ok','schools'=>$processed,'date'=>$day,'externalDeliveries'=>0]) . PHP_EOL;
