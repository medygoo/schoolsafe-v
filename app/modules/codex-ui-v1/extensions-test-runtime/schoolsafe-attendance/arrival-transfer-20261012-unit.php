<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/ArrivalTransferWorker.php';
function ok(bool $value): void { if (!$value) throw new RuntimeException('ASSERTION_FAILED'); }
function testTransfer(string $name, callable $fn): void { $fn(); echo "PASS $name\n"; }
function eventTransfer(string $id): array { return ['eventId' => $id, 'occurredAt' => '2026-10-12T12:00:00.000000+00:00',
    'sourceInstance' => 'codex-addons-local', 'schoolId' => '22222222-2222-4222-8222-222222222222',
    'studentId' => '33333333-3333-4333-8333-333333333333', 'eventType' => 'entry', 'decision' => 'allowed', 'source' => 'scanner_entry']; }
$activation = '2026-10-12T00:00:00Z';
testTransfer('failed event retained while later event commits', function () use ($activation) {
    $events = [eventTransfer('a'), eventTransfer('b')];
    $r = SchoolSafeArrivalTransferTick([], $activation, 2, fn() => $events,
        function ($e) { if ($e['eventId'] === 'a') throw new RuntimeException('mapping absent'); return ['result' => 'inserted']; });
    ok(isset($r['state']['pending']['a']) && !isset($r['state']['pending']['b']));
    ok($r['state']['cursor']['eventId'] === 'b' && $r['counts']['projected'] === 1);
});
testTransfer('pending is retried and removed only on confirmed commit', function () use ($activation) {
    $r = SchoolSafeArrivalTransferTick(['pending' => ['a' => eventTransfer('a')]], $activation, 2, fn() => [], fn() => ['result' => 'duplicate']);
    ok(!$r['state']['pending'] && $r['counts']['duplicates'] === 1 && $r['state']['cursor'] === null);
});
testTransfer('retry queue rotates instead of starving subsequent failures', function () use ($activation) {
    $state = ['pending' => ['a' => eventTransfer('a'), 'b' => eventTransfer('b')]];
    $r = SchoolSafeArrivalTransferTick($state, $activation, 1, fn() => [], function () { throw new RuntimeException('offline'); });
    ok(array_keys($r['state']['pending']) === ['b','a']);
});
testTransfer('historical sweeps reset independently of forward cursor', function () use ($activation) {
    $cursor = ['eventId' => 'z', 'occurredAt' => '2026-10-12T23:00:00Z'];
    $calls = 0;
    $r = SchoolSafeArrivalTransferTick(['cursor' => $cursor, 'reconciliationTicks' => 4], $activation, 2,
        function ($since, $position) use (&$calls, $activation, $cursor) {
            ok($since === $activation); ++$calls;
            if ($calls === 1) { ok($position === $cursor); return []; }
            ok($position === null); return [eventTransfer('late')];
        }, fn() => ['result' => 'inserted']);
    ok($r['counts']['projected'] === 1 && $r['state']['cursor'] === $cursor);
    ok($r['state']['reconciliationCursor'] === null && $r['state']['reconciliationTicks'] === 0);
});
testTransfer('unconfirmed projection is retained', function () use ($activation) {
    $r = SchoolSafeArrivalTransferTick([], $activation, 2, fn() => [eventTransfer('a')], fn() => ['result' => 'unknown']);
    ok(isset($r['state']['pending']['a']));
});
testTransfer('changed activation refuses processing', function () use ($activation) {
    try { SchoolSafeArrivalTransferTick(['activation' => 'old'], $activation, 2, fn() => [], fn() => []); }
    catch (RuntimeException $e) { ok($e->getMessage() === 'ACTIVATION_CHANGED'); return; }
    throw new RuntimeException('EXPECTED_REJECTION');
});
testTransfer('tick attempts at most twice batch size', function () use ($activation) {
    $calls = 0; $r = SchoolSafeArrivalTransferTick(['pending' => ['a' => eventTransfer('a'), 'b' => eventTransfer('b')]], $activation, 1,
        fn() => [eventTransfer('c')], function () use (&$calls) { ++$calls; throw new RuntimeException('offline'); });
    ok($calls === 2 && count($r['state']['pending']) === 3);
});
testTransfer('reverse mapping uses configured school timezone and event enrollment year', function () {
    $event = eventTransfer('11111111-1111-4111-8111-111111111111'); $event['occurredAt'] = '2026-10-12T23:30:00Z';
    $map = SchoolSafeArrivalTransferMapping($event,
        function ($sql, $args) use ($event) { ok($args['school'] === $event['schoolId'] && $args['student'] === $event['studentId']); return [['native_school_id' => '1', 'native_student_id' => '2']]; },
        function ($sql, $args) { ok($args['day'] === '2026-10-13'); return [['syear' => '2026']]; }, ['1' => 'Africa/Kinshasa']);
    ok($map['nativeYear'] === '2026' && SchoolSafeArrivalValidate($event, $map)['status'] === 'arrival_evidence');
});
testTransfer('mapping ambiguity and ambiguous enrollment never project', function () {
    $event = eventTransfer('11111111-1111-4111-8111-111111111111');
    foreach ([0, 2] as $count) {
        try { SchoolSafeArrivalTransferMapping($event, fn() => array_fill(0, $count, ['native_school_id' => '1', 'native_student_id' => '2']), fn() => [], []); }
        catch (RuntimeException $e) { ok($e->getMessage() === 'ARRIVAL_MAPPING_REQUIRED'); continue; }
        throw new RuntimeException('EXPECTED_REJECTION');
    }
    try { SchoolSafeArrivalTransferMapping($event, fn() => [['native_school_id' => '1', 'native_student_id' => '2']], fn() => [['syear' => '2026'], ['syear' => '2025']], ['1' => 'Africa/Kinshasa']); }
    catch (RuntimeException $e) { ok($e->getMessage() === 'UNIQUE_EVENT_ENROLLMENT_REQUIRED'); return; }
    throw new RuntimeException('EXPECTED_REJECTION');
});
testTransfer('sustained full batches reconcile without starving forward progress', function () use ($activation) {
    $state = []; $forward = 0; $history = 0;
    for ($tick = 1; $tick <= 10; ++$tick) {
        $calls = 0;
        $r = SchoolSafeArrivalTransferTick($state, $activation, 1,
            function ($since, $cursor) use (&$calls, &$forward, &$history) {
                if (++$calls === 1) {
                    ok($cursor === null || $cursor['eventId'] === 'forward' . $forward);
                    return [eventTransfer('forward' . ++$forward)];
                }
                ok($cursor === null || $cursor['eventId'] === 'history' . $history);
                return [eventTransfer('history' . ++$history)];
            }, fn() => ['result' => 'duplicate']);
        $state = json_decode(json_encode($r['state']), true);
        ok($state['cursor']['eventId'] === 'forward' . $tick);
    }
    ok($forward === 10 && $history === 2 && $state['reconciliationCursor']['eventId'] === 'history2');
});
testTransfer('old checkpoints gain reconciliation state without losing pending or cursor', function () use ($activation) {
    $cursor = ['eventId' => 'z', 'occurredAt' => '2026-10-12T23:00:00Z'];
    $r = SchoolSafeArrivalTransferTick(['activation' => $activation, 'cursor' => $cursor,
        'pending' => ['a' => eventTransfer('a')]], $activation, 1, fn() => [],
        function () { throw new RuntimeException('offline'); });
    ok($r['state']['cursor'] === $cursor && isset($r['state']['pending']['a']));
    ok($r['state']['reconciliationCursor'] === null && $r['state']['reconciliationTicks'] === 1);
});
testTransfer('reconciliation tick attempts at most three batches', function () use ($activation) {
    $attempts = 0; $fetches = 0;
    $r = SchoolSafeArrivalTransferTick(['reconciliationTicks' => 4, 'pending' => ['a' => eventTransfer('a')]],
        $activation, 1, function () use (&$fetches) { return [eventTransfer('fetch' . ++$fetches)]; },
        function () use (&$attempts) { ++$attempts; throw new RuntimeException('offline'); });
    ok($attempts === 3 && $fetches === 2 && count($r['state']['pending']) === 3);
});
echo "12 mocked worker tests passed; no connections or real events\n";
