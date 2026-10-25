<?php
// Exactly two real connections; disposable runner only, never a business database.
if (getenv('SS_REMEDIATION_DISPOSABLE') !== 'yes') throw new RuntimeException('Disposable runner required');
$a=pg_connect('host=127.0.0.1 dbname=rosario_addons_test user=postgres', PGSQL_CONNECT_FORCE_NEW);
$b=pg_connect('host=127.0.0.1 dbname=rosario_addons_test user=postgres', PGSQL_CONNECT_FORCE_NEW);
if (!$a || !$b) throw new RuntimeException('Two PostgreSQL connections required');
$checks=0;
function sql($connection, string $sql) {
    $result=@pg_query($connection,$sql);
    if (!$result) throw new RuntimeException(pg_last_error($connection));
    return $result;
}
function checkRace(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: '.$label);
    echo 'PASS: '.$label.PHP_EOL; $checks++;
}
function fixture($connection, int $student): array {
    sql($connection,"INSERT INTO students VALUES($student,'Synthetic','Race')");
    $case=pg_fetch_result(sql($connection,"INSERT INTO schoolsafe_remediation_cases(student_id,course_id,school_id,syear,evidence,status,billing_mode,tariff) VALUES($student,1,1,2026,ARRAY[1,2,3],'approved','paid',200) RETURNING id"),0,0);
    $fee=pg_fetch_result(sql($connection,"INSERT INTO billing_fees(student_id,school_id,syear,title,amount) VALUES($student,1,2026,'Synthetic race',200) RETURNING id"),0,0);
    sql($connection,"UPDATE schoolsafe_remediation_cases SET fee_id=$fee WHERE id=$case");
    $payment=pg_fetch_result(sql($connection,"INSERT INTO billing_payments(student_id,school_id,syear,amount,payment_date) VALUES($student,1,2026,100,CURRENT_DATE) RETURNING id"),0,0);
    sql($connection,"INSERT INTO schoolsafe_remediation_payment_purpose(payment_id,fee_id,recorded_by,receipt_reference) VALUES($payment,$fee,2,'SYNTHETIC-RACE-$student')");
    return compact('case','fee','payment','student');
}
function allocation(array $f): string {
    return "INSERT INTO schoolsafe_remediation_payments(case_id,payment_id,amount,school_share,teacher_share,confirmed_by) VALUES({$f['case']},{$f['payment']},100,40,60,2)";
}
function beginRace($connection): void {
    sql($connection,"BEGIN; SET LOCAL lock_timeout='4s'; SET LOCAL statement_timeout='8s'");
}
function blocked($observer,$waiting,string $label): void {
    $pid=pg_get_pid($waiting); $deadline=microtime(true)+3;
    do {
        $locked=pg_fetch_result(sql($observer,"SELECT EXISTS(SELECT 1 FROM pg_stat_activity WHERE pid=$pid AND wait_event_type='Lock')"),0,0);
        if ($locked==='t') { checkRace(true,$label.': lock wait observed'); return; }
        usleep(10000);
    } while(microtime(true)<$deadline);
    throw new RuntimeException('No lock wait observed: '.$label);
}
function finish($connection, ?string $expected=null): void {
    $deadline=microtime(true)+9;
    while(pg_connection_busy($connection)) {
        if(microtime(true)>$deadline) throw new RuntimeException('Async PostgreSQL timeout');
        usleep(10000);
    }
    $result=pg_get_result($connection);
    if (!$result) throw new RuntimeException('Missing async result');
    $state=pg_result_error_field($result,PGSQL_DIAG_SQLSTATE);
    if ($expected===null && $state!==null) throw new RuntimeException(pg_result_error($result));
    if ($expected!==null && $state!==$expected) throw new RuntimeException('Expected '.$expected.', got '.($state??'success'));
    while(pg_get_result($connection)) {}
}
function active($connection,array $f): int {
    return (int)pg_fetch_result(sql($connection,"SELECT count(*) FROM schoolsafe_remediation_payments WHERE case_id={$f['case']} AND invalidated_at IS NULL"),0,0);
}
try {
    $f=fixture($a,10);
    beginRace($a); sql($a,"UPDATE schoolsafe_remediation_payment_purpose SET fee_id=NULL WHERE payment_id={$f['payment']}");
    beginRace($b); pg_send_query($b,allocation($f)); blocked($a,$b,'purpose correction before allocation');
    sql($a,'COMMIT'); finish($b,'P0001'); sql($b,'ROLLBACK');
    checkRace(active($a,$f)===0,'corrected purpose cannot produce an active allocation');

    $f=fixture($a,11);
    beginRace($a); sql($a,allocation($f));
    beginRace($b); pg_send_query($b,"UPDATE schoolsafe_remediation_payment_purpose SET fee_id=NULL WHERE payment_id={$f['payment']}");
    blocked($a,$b,'allocation before purpose correction'); sql($a,'COMMIT'); finish($b); sql($b,'COMMIT');
    checkRace(active($a,$f)===0,'purpose correction after allocation invalidates committed allocation');

    $f=fixture($a,12);
    beginRace($a); sql($a,"DELETE FROM billing_fees WHERE id={$f['fee']}");
    beginRace($b); pg_send_query($b,allocation($f)); blocked($a,$b,'fee deletion before allocation');
    sql($a,'COMMIT'); finish($b,'P0001'); sql($b,'ROLLBACK');
    checkRace(active($a,$f)===0,'fee deletion and confirmation do not deadlock or allocate');

    $f=fixture($a,13);
    beginRace($a); sql($a,allocation($f));
    beginRace($b); pg_send_query($b,"DELETE FROM billing_fees WHERE id={$f['fee']}");
    blocked($a,$b,'allocation before fee deletion'); sql($a,'COMMIT'); finish($b); sql($b,'COMMIT');
    checkRace(active($a,$f)===0,'fee deletion invalidates newly committed allocation');

    $f=fixture($a,14);
    beginRace($a); sql($a,"UPDATE billing_payments SET payment_date=NULL WHERE id={$f['payment']}");
    beginRace($b); pg_send_query($b,allocation($f)); blocked($a,$b,'payment correction before allocation');
    sql($a,'COMMIT'); finish($b,'P0001'); sql($b,'ROLLBACK');
    checkRace(active($a,$f)===0,'missing payment date after concurrent correction refused');

    $f=fixture($a,15);
    beginRace($a); sql($a,allocation($f));
    beginRace($b); pg_send_query($b,"INSERT INTO billing_payments(student_id,school_id,syear,amount,payment_date,refunded_payment_id) VALUES(15,1,2026,-1,CURRENT_DATE,{$f['payment']})");
    blocked($a,$b,'allocation before refund'); sql($a,'COMMIT'); finish($b); sql($b,'COMMIT');
    checkRace(active($a,$f)===0,'refund sees and invalidates newly committed allocation');
    $f=fixture($a,19);
    beginRace($a); sql($a,"INSERT INTO billing_payments(student_id,school_id,syear,amount,payment_date,refunded_payment_id) VALUES(19,1,2026,-1,CURRENT_DATE,{$f['payment']})");
    beginRace($b); pg_send_query($b,allocation($f)); blocked($a,$b,'refund before allocation');
    sql($a,'COMMIT'); finish($b,'P0001'); sql($b,'ROLLBACK');
    checkRace(active($a,$f)===0,'confirmation rechecks refund after native payment lock wait');

    $f=fixture($a,16);
    beginRace($a); sql($a,allocation($f));
    beginRace($b); pg_send_query($b,allocation($f)); blocked($a,$b,'two confirmations');
    sql($a,'COMMIT'); finish($b,'23505'); sql($b,'ROLLBACK');
    checkRace(active($a,$f)===1,'two confirmations preserve one active allocation');

    $f=fixture($a,17);
    beginRace($a); sql($a,"UPDATE schoolsafe_remediation_payment_purpose SET fee_id=NULL WHERE payment_id={$f['payment']}");
    sql($b,"BEGIN; SET LOCAL lock_timeout='150ms'; SET LOCAL statement_timeout='2s'");
    pg_send_query($b,allocation($f)); finish($b,'55P03'); sql($b,'ROLLBACK'); sql($a,'ROLLBACK');
    checkRace(active($a,$f)===0,'lock timeout rolls back confirmation without allocation');
    $f=fixture($a,18);
    sql($a,allocation($f)); sql($a,"UPDATE billing_payments SET payment_date=CURRENT_DATE-1 WHERE id={$f['payment']}");
    beginRace($a); sql($a,allocation($f));
    beginRace($b); pg_send_query($b,allocation($f)); blocked($a,$b,'two reconfirmations');
    sql($a,'COMMIT'); finish($b,'23505'); sql($b,'ROLLBACK');
    checkRace(active($a,$f)===1,'two reconfirmations preserve one active version');
    checkRace((int)pg_fetch_result(sql($a,"SELECT count(*) FROM schoolsafe_remediation_payments WHERE case_id={$f['case']}"),0,0)===2,'concurrent reconfirmation retains original plus one new version');
    $event=pg_fetch_result(sql($a,"SELECT id FROM schoolsafe_remediation_events WHERE case_id={$f['case']} ORDER BY id LIMIT 1"),0,0);
    pg_send_query($b,"INSERT INTO schoolsafe_remediation_notifications(event_id,audience,recipient_id) VALUES($event,'principal_parent',3)");
    finish($b,'P0001');
    checkRace((int)pg_fetch_result(sql($a,"SELECT count(*) FROM schoolsafe_remediation_notifications WHERE event_id=$event AND audience='principal_parent' AND recipient_id IS NOT NULL"),0,0)===0,'later transaction cannot bind current principal to old event');
    $f=fixture($a,20);
    sql($b,'BEGIN ISOLATION LEVEL REPEATABLE READ'); pg_send_query($b,allocation($f)); finish($b,'P0001'); sql($b,'ROLLBACK');
    checkRace(active($a,$f)===0,'unsupported stale-snapshot isolation refuses confirmation');
    echo 'Concurrent assertions passed: '.$checks.PHP_EOL;
} finally {
    @pg_query($a,'ROLLBACK'); @pg_query($b,'ROLLBACK');
    pg_close($a); pg_close($b);
}
