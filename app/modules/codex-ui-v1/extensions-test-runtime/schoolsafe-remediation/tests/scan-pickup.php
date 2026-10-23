<?php
// SchoolSafe — Lot PICKUP : test RED/GREEN du scan de récupération (base simulée).
// Usage : php schoolsafe-remediation/tests/scan-pickup.php (conteneur L1).
// Sortie 0 si et seulement si tous les cas passent.
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(2); }
chdir(dirname(__DIR__, 2));
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafePickupScan.fnc.php';

$failures = 0;
function check($label, $actual, $expected) {
    global $failures;
    if ($actual === $expected) { echo "ok   $label\n"; return; }
    $failures++;
    echo "FAIL $label : attendu " . var_export($expected, true)
        . ", obtenu " . var_export($actual, true) . "\n";
}

$secretHex = 'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90';
$secret = hex2bin($secretHex);
$sign = function ($n) use ($secret) {
    return substr(strtr(base64_encode(hash_hmac('sha256', $n, $secret, true)), '+/', '-_'), 0, 32);
};
$qr = function ($n) use ($sign) { return 'schoolsafe://card/' . $n . '/' . $sign($n); };

// --- Base simulée ------------------------------------------------------------
$state = [];
function resetState() {
    global $state;
    $state = [
        'cards' => [
            'P-ACTIVE' => ['school_id' => 1, 'student_id' => 11, 'status' => 'active', 'enrolled' => true,
                'first_name' => 'Awa', 'last_name' => 'Diallo', 'class_name' => '6e A'],
        ],
        'checkpoints' => [7 => ['ID' => 7, 'TITLE' => 'Portail sortie']],
        'principals' => [
            ['ID' => 21, 'GUARDIAN_TYPE' => 'mere', 'FULL_NAME' => 'Maman Diallo',
             'PHOTO_PATH' => '/assets/pickup/21.jpg', 'STAFF_ID' => null, 'IS_PRIMARY' => 't'],
        ],
        'externals' => [
            ['AUTH_ID' => 31, 'ID' => 22, 'GUARDIAN_TYPE' => 'externe', 'FULL_NAME' => 'Tonton Ali',
             'PHOTO_PATH' => '/assets/pickup/22.jpg', 'STATUS' => 'active', 'PHOTO_VERIFIED' => true,
             'STARTS_ON' => null, 'ENDS_ON' => null],
        ],
        'proofs' => [], 'records' => [], 'queries' => [], 'commands' => [], 'transaction' => false,
    ];
}
resetState();
$writes = 0;
$db = [
    'get' => function ($sql) {
        global $state, $sign, $writes;
        $state['queries'][] = $sql;
        if (strpos($sql, 'to_regclass') !== false) return [1 => ['T' => 'public.schoolsafe_student_cards']];
        if (strpos($sql, 'FROM schoolsafe_student_cards') !== false) {
            preg_match("/card_number='((?:[^']|'')+)'/", $sql, $m);
            $n = str_replace("''", "'", $m[1]);
            preg_match('/c.school_id=(\d+)/', $sql, $s);
            if (!isset($state['cards'][$n]) || (int) $state['cards'][$n]['school_id'] !== (int) $s[1]) return [];
            $c = $state['cards'][$n];
            return [1 => ['STUDENT_ID' => $c['student_id'], 'SIGNATURE' => $sign($n), 'STATUS' => $c['status'],
                'FIRST_NAME' => $c['first_name'], 'LAST_NAME' => $c['last_name'],
                'CLASS_NAME' => $c['class_name'], 'ENROLLED' => $c['enrolled'] ? 't' : 'f']];
        }
        if (strpos($sql, 'FROM entry_exit_checkpoints') !== false) {
            preg_match('/ID=(\d+)/', $sql, $m);
            return isset($state['checkpoints'][(int) $m[1]]) ? [1 => $state['checkpoints'][(int) $m[1]]] : [];
        }
        if (strpos($sql, 'SELECT ID FROM schoolsafe_pickup_guardians') !== false) {
            preg_match('/WHERE ID=(\d+)/', $sql, $m);
            foreach (array_merge($state['principals'], $state['externals']) as $person) {
                if ((int) $person['ID'] === (int) $m[1]) return [1=>['ID'=>$person['ID']]];
            }
            return [];
        }
        if (strpos($sql, 'SELECT ID FROM schoolsafe_pickup_authorizations') !== false) {
            preg_match('/WHERE ID=(\d+)/', $sql, $m);
            foreach ($state['externals'] as $person) {
                if ((int) $person['AUTH_ID'] === (int) $m[1]) return [1=>['ID'=>$person['AUTH_ID']]];
            }
            return [];
        }
        if (strpos($sql, 'FROM schoolsafe_pickup_guardians') !== false && strpos($sql, 'IS_PRIMARY') !== false) {
            $out = [];
            foreach ($state['principals'] as $p) $out[] = $p;
            $rows = [];
            foreach ($out as $i => $p) $rows[$i + 1] = $p;
            return $rows;
        }
        if (strpos($sql, 'FROM students_join_users') !== false) return [];
        if (strpos($sql, 'FROM schoolsafe_pickup_authorizations') !== false) {
            $rows = [];
            foreach ($state['externals'] as $i => $e) {
                if (($e['STATUS'] ?? '') === 'active') $rows[$i + 1] = $e;
            }
            return $rows;
        }
        if (strpos($sql, 'FROM schoolsafe_pickup_proofs') !== false && strpos($sql, 'INSERT') === false) {
            preg_match("/TOKEN='([a-f0-9]+)'/", $sql, $m);
            foreach ($state['proofs'] as $p) {
                if ($p['token'] === ($m[1] ?? '')) {
                    return [1 => ['ID' => $p['id'], 'SCHOOL_ID' => 1, 'STUDENT_ID' => 11,
                        'GUARDIAN_ID' => $p['gid'], 'AUTHORIZATION_ID' => $p['aid'],
                        'IS_PRINCIPAL' => $p['principal'], 'LOCATION_ID' => 7, 'PREPARED_BY' => $p['prepared_by'],
                        'RECORD_ID' => $p['record'], 'EXPIRES_AT' => $p['expires'], 'USED_AT' => $p['used']]];
                }
            }
            return [];
        }
        if (strpos($sql, 'INSERT INTO schoolsafe_pickup_proofs') !== false) {
            preg_match("/VALUES\\('([a-f0-9]+)',(\\d+),(\\d+),(\\d+),/", $sql, $m);
            preg_match('/(TRUE|FALSE),(\d+),(\d+),\'([0-9-]+\s[0-9:]+)\'/', $sql, $x);
            $id = 50 + count($state['proofs']);
            $state['proofs'][] = ['id' => $id, 'token' => $m[1], 'gid' => (int) $m[4],
                'aid' => strpos($sql, ',NULL,') !== false ? null : 31,
                'principal' => $x[1] === 'TRUE', 'record' => null,
                'expires' => $x[4], 'used' => null, 'prepared_by' => (int) $x[3]];
            $writes++;
            return [1 => ['ID' => $id]];
        }
        if (strpos($sql, 'INSERT INTO entry_exit_student_records') !== false) {
            $id = 100 + count($state['records']);
            $state['records'][] = ['id' => $id, 'at' => '2026-05-04 16:30:00'];
            $writes++;
            return [1 => ['ID' => $id, 'RECORDED_AT' => '2026-05-04 16:30:00']];
        }
        if (strpos($sql, 'FROM entry_exit_student_records WHERE ID=') !== false) {
            preg_match('/ID=(\d+)/', $sql, $m);
            foreach ($state['records'] as $r) {
                if ($r['id'] === (int) $m[1]) return [1 => ['ID' => $r['id'], 'RECORDED_AT' => $r['at']]];
            }
            return [];
        }
        return [];
    },
    'exec' => function ($sql) {
        global $state;
        $state['commands'][] = $sql;
        if ($sql === 'BEGIN') $state['transaction'] = true;
        if (in_array($sql, ['COMMIT', 'ROLLBACK'], true)) $state['transaction'] = false;
        if (strpos($sql, 'UPDATE schoolsafe_pickup_proofs') !== false) {
            preg_match('/RECORD_ID=(\d+) WHERE ID=(\d+)/', $sql, $m);
            foreach ($state['proofs'] as &$p) {
                if ($p['id'] === (int) $m[2]) { $p['record'] = (int) $m[1]; $p['used'] = '2026-05-04 16:30:00'; }
            }
            unset($p);
        }
    },
];
$ctx = ['user' => 9, 'school' => 1, 'year' => 2026];
$afternoon = mktime(16, 20, 0, 5, 4, 2026);

// 1. Lecture : PICKUP_CHOICE + 2 personnes, photo distinguée de l'autorisation.
$r = SchoolSafePickupScan($qr('P-ACTIVE'), '7', $secretHex, $ctx, $db, [], $afternoon);
check('liste => PICKUP_CHOICE', $r['result'], 'PICKUP_CHOICE');
check('liste => 2 personnes', count($r['people']), 2);
check('liste => rôle mère', $r['people'][0]['role'], 'Mère (principale)');
check('liste => photo présente', $r['people'][1]['photo_path'], '/assets/pickup/22.jpg');
check('liste => pickup_ready', $r['people'][1]['pickup_ready'], true);
check('liste => aucune sortie', count($state['records']), 0);

// 2. Choix : preuve exigée, toujours aucune sortie.
$r = SchoolSafePickupScan($qr('P-ACTIVE'), '7', $secretHex, $ctx, $db, ['authorized_person_id' => 'a:31'], $afternoon);
check('choix => NEED_HUMAN_CONFIRMATION', $r['result'], 'NEED_HUMAN_CONFIRMATION');
check('choix => preuve 64 hex', (bool) preg_match('/^[a-f0-9]{64}$/D', $r['proof_token']), true);
check('préparation => aucune sortie', count($state['records']), 0);
$token = $r['proof_token'];

// 3. Confirmation : sortie 'out' enregistrée avec la personne et le staff.
$r = SchoolSafePickupScan($qr('P-ACTIVE'), '7', $secretHex, $ctx, $db,
    ['proof_token' => $token, 'human_confirmed' => true], $afternoon);
check('confirm => CONFIRMED', $r['result'], 'CONFIRMED');
check('confirm => 1 sortie', count($state['records']), 1);
check('confirm => non idempotent', $r['idempotent'], false);
$proofReads = array_values(array_filter($state['queries'], function($sql) { return strpos($sql, 'FROM schoolsafe_pickup_proofs') !== false; }));
check('confirmation locks proof before consuming', strpos(end($proofReads), 'FOR UPDATE') !== false, true);
check('confirmation closes transaction', $state['transaction'], false);

// 4. Rejeu : idempotent, aucun doublon.
$r = SchoolSafePickupScan($qr('P-ACTIVE'), '7', $secretHex, $ctx, $db,
    ['proof_token' => $token, 'human_confirmed' => true], $afternoon);
check('rejeu => CONFIRMED', $r['result'], 'CONFIRMED');
check('rejeu => idempotent', $r['idempotent'], true);
check('rejeu => toujours 1 sortie', count($state['records']), 1);

// 5. Preuve sans confirmation humaine : refus.
$r2 = SchoolSafePickupScan($qr('P-ACTIVE'), '7', $secretHex, $ctx, $db, ['authorized_person_id' => 'g:21'], $afternoon);
check('2e preuve => NEED_HUMAN', $r2['result'], 'NEED_HUMAN_CONFIRMATION');
$r = SchoolSafePickupScan($qr('P-ACTIVE'), '7', $secretHex, $ctx, $db,
    ['proof_token' => $r2['proof_token'], 'human_confirmed' => false], $afternoon);
check('sans humain => DENIED', $r['result'], 'DENIED');
check('sans humain => motif', $r['reason'], 'CONFIRMATION_REQUIRED');

// 6. Personne inconnue / preuve inconnue / lieu inconnu : refus, rien d'écrit.
$before = count($state['records']);
$r = SchoolSafePickupScan($qr('P-ACTIVE'), '7', $secretHex, $ctx, $db, ['authorized_person_id' => 'a:999'], $afternoon);
check('inconnue => DENIED', $r['result'], 'DENIED');
check('inconnue => NOT_DELEGATED', $r['reason'], 'NOT_DELEGATED');
$r = SchoolSafePickupScan($qr('P-ACTIVE'), '7', $secretHex, $ctx, $db,
    ['proof_token' => str_repeat('a', 64), 'human_confirmed' => true], $afternoon);
check('preuve inconnue => DENIED', $r['reason'], 'PROOF_UNKNOWN');
$r = SchoolSafePickupScan($qr('P-ACTIVE'), '999', $secretHex, $ctx, $db, [], $afternoon);
check('lieu inconnu => DENIED', $r['reason'], 'LOCATION_DENIED');
check('refus => aucune sortie de plus', count($state['records']), $before);

// 7. Preuve expirée (2 min) : refus.
resetState();
$writes = 0;
$r = SchoolSafePickupScan($qr('P-ACTIVE'), '7', $secretHex, $ctx, $db, ['authorized_person_id' => 'g:21'], $afternoon);
$late = $afternoon + 180;
$r = SchoolSafePickupScan($qr('P-ACTIVE'), '7', $secretHex, $ctx, $db,
    ['proof_token' => $r['proof_token'], 'human_confirmed' => true], $late);
check('expirée => DENIED', $r['result'], 'DENIED');
check('expirée => motif', $r['reason'], 'PROOF_EXPIRED');
check('expirée => aucune sortie', count($state['records']), 0);

// 8. Autorisation révoquée entre préparation et confirmation : refus.
resetState();
$r = SchoolSafePickupScan($qr('P-ACTIVE'), '7', $secretHex, $ctx, $db, ['authorized_person_id' => 'a:31'], $afternoon);
$state['externals'] = [];
$r = SchoolSafePickupScan($qr('P-ACTIVE'), '7', $secretHex, $ctx, $db,
    ['proof_token' => $r['proof_token'], 'human_confirmed' => true], $afternoon);
check('révoquée => DENIED', $r['result'], 'DENIED');
check('révoquée => motif', $r['reason'], 'NOT_DELEGATED');
check('révoquée => aucune sortie', count($state['records']), 0);

// 9. Carte falsifiée / sans secret : refus / repli.
$r = SchoolSafePickupScan('schoolsafe://card/P-ACTIVE/' . str_repeat('B', 32), '7', $secretHex, $ctx, $db, [], $afternoon);
check('falsifiée => INVALID', $r['result'], 'INVALID');
$r = SchoolSafePickupScan($qr('P-ACTIVE'), '7', 'pas-un-secret', $ctx, $db, [], $afternoon);
check('sans secret => null', $r, null);

// 10. Non-principal familial authorized person is not an external slot.
resetState();
$state['principals'][0]['GUARDIAN_TYPE'] = 'pere';
$state['principals'][0]['IS_PRIMARY'] = 'f';
$r = SchoolSafePickupScan($qr('P-ACTIVE'), '7', $secretHex, $ctx, $db, [], $afternoon);
check('familial non principal => rôle père', $r['people'][0]['role'], 'Père');
check('familial non principal => drapeau false', $r['people'][0]['is_principal'], false);
$familyReads = array_values(array_filter($state['queries'], function($sql) { return strpos($sql, 'FROM schoolsafe_pickup_guardians g') !== false; }));
check('family query includes authorized non principals', strpos($familyReads[0], 'AND g.IS_PRIMARY AND') === false, true);
$externalReads = array_values(array_filter($state['queries'], function($sql) { return strpos($sql, 'FROM schoolsafe_pickup_authorizations a') !== false; }));
check('external join binds school and student', strpos($externalReads[0], 'g.SCHOOL_ID=a.SCHOOL_ID') !== false && strpos($externalReads[0], 'g.STUDENT_ID=a.STUDENT_ID') !== false, true);

// 11. A proof cannot be borrowed by another scanner operator, even when used.
resetState();
$prepared = SchoolSafePickupScan($qr('P-ACTIVE'), '7', $secretHex, $ctx, $db, ['authorized_person_id'=>'g:21'], $afternoon);
$r = SchoolSafePickupScan($qr('P-ACTIVE'), '7', $secretHex, array_replace($ctx, ['user'=>18]), $db,
    ['proof_token'=>$prepared['proof_token'], 'human_confirmed'=>true], $afternoon);
check('other operator => DENIED', $r['result'], 'DENIED');
check('other operator => scope refusal', $r['reason'] ?? '', 'OUT_OF_SCOPE');
check('other operator => no handover record', count($state['records']), 0);
check('scope refusal closes transaction', $state['transaction'], false);

// 12. Never display a successful preparation if storing the proof failed.
resetState();
$noProofDb = $db;
$noProofDb['get'] = function($sql) use ($db) {
    return strpos($sql, 'INSERT INTO schoolsafe_pickup_proofs') !== false ? [] : $db['get']($sql);
};
try {
    SchoolSafePickupScan($qr('P-ACTIVE'), '7', $secretHex, $ctx, $noProofDb, ['authorized_person_id'=>'g:21'], $afternoon);
    check('missing stored proof refuses preparation', 'NO_ERROR', 'PICKUP_PROOF_WRITE_FAILED');
} catch (RuntimeException $error) { check('missing stored proof refuses preparation', $error->getMessage(), 'PICKUP_PROOF_WRITE_FAILED'); }
check('failed preparation rolls back', end($state['commands']), 'ROLLBACK');
check('failed preparation never records exit', count($state['records']), 0);

echo $failures === 0 ? "GREEN : tous les cas passent.\n" : "STOP : $failures échec(s).\n";
exit($failures === 0 ? 0 : 1);
