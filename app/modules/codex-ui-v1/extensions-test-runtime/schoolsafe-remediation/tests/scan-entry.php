<?php
// SchoolSafe — Lot ENTRY : test RED/GREEN du scan d'entrée (base simulée).
// Usage : php schoolsafe-remediation/tests/scan-entry.php (conteneur L1).
// Sortie 0 si et seulement si tous les cas passent.
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(2); }
chdir(dirname(__DIR__, 2));
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeEntryScan.fnc.php';

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
            'E-ACTIVE' => ['school_id' => 1, 'student_id' => 11, 'status' => 'active', 'enrolled' => true,
                'first_name' => 'Awa', 'last_name' => 'Diallo', 'class_name' => '6e A'],
            'E-LOST' => ['school_id' => 1, 'student_id' => 12, 'status' => 'lost', 'enrolled' => true,
                'first_name' => 'Moussa', 'last_name' => 'Keita', 'class_name' => '6e A'],
        ],
        'checkpoints' => [
            7 => ['ID' => 7, 'TITLE' => 'Portail principal', 'TYPE' => null],
            8 => ['ID' => 8, 'TITLE' => 'Sortie bus', 'TYPE' => 'out'],
        ],
        'records' => [], 'attendance' => [], 'period_start' => '08:00:00', 'mp' => 3,
    ];
}
resetState();
$writes = 0;
$db = [
    'get' => function ($sql) {
        global $state, $sign, $writes;
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
        if (strpos($sql, 'FROM entry_exit_student_records WHERE') !== false) {
            preg_match('/STUDENT_ID=(\d+)/', $sql, $m);
            foreach ($state['records'] as $r) {
                if ($r['student'] === (int) $m[1] && $r['type'] === 'in' && $r['unauth'] === null) return [1 => ['ID' => $r['id']]];
            }
            return [];
        }
        if (strpos($sql, 'FROM school_periods') !== false) {
            return $state['period_start'] === null ? [] : [1 => ['START_TIME' => $state['period_start']]];
        }
        if (strpos($sql, 'FROM school_marking_periods') !== false) {
            return $state['mp'] === null ? [] : [1 => ['MARKING_PERIOD_ID' => $state['mp']]];
        }
        if (strpos($sql, 'INSERT INTO entry_exit_student_records') !== false) {
            $writes++;
            $id = 100 + count($state['records']);
            $unauth = strpos($sql, "'Y'") !== false ? 'Y' : null;
            preg_match('/VALUES\((\d+),(\d+),/', $sql, $m);
            preg_match('/CURRENT_TIMESTAMP/', $sql, $t);
            $state['records'][] = ['id' => $id, 'student' => (int) $m[1], 'cp' => (int) $m[2],
                'type' => 'in', 'unauth' => $unauth, 'at' => '2026-05-04 07:50:00'];
            return [1 => ['ID' => $id, 'RECORDED_AT' => '2026-05-04 07:50:00']];
        }
        if (strpos($sql, 'INSERT INTO attendance_day') !== false) {
            preg_match('/VALUES\((\d+),/', $sql, $m);
            foreach ($state['attendance'] as $a) { if ($a === (int) $m[1]) return []; } // DO NOTHING : déjà présent.
            $state['attendance'][] = (int) $m[1];
            $writes++;
            return [];
        }
        return [];
    },
    'exec' => function ($sql) {
        global $state;
        if (strpos($sql, 'INSERT INTO entry_exit_student_records') !== false) {
            preg_match('/VALUES\((\d+),(\d+),/', $sql, $m);
            $state['records'][] = ['id' => 100 + count($state['records']), 'student' => (int) $m[1],
                'cp' => (int) $m[2], 'type' => 'in',
                'unauth' => strpos($sql, "'Y'") !== false ? 'Y' : null, 'at' => '2026-05-04 07:50:00'];
        }
    },
];
$ctx = ['user' => 9, 'school' => 1, 'year' => 2026];
$morning = mktime(7, 50, 0, 5, 4, 2026);
$late = mktime(8, 30, 0, 5, 4, 2026);

// 1. Arrivée sur site : record uniquement, jamais de présence de classe implicite.
$r = SchoolSafeEntryScan($qr('E-ACTIVE'), '7', $secretHex, $ctx, $db, $morning);
check('nominal => PRESENT', $r['result'], 'PRESENT');
check('nominal => pas de retard', $r['late'], false);
check('nominal => checkpoint', $r['checkpoint']['label'], 'Portail principal');
check('nominal => identité', $r['student_name'], 'Awa Diallo');
check('nominal => 1 écriture de passage', $writes, 1);
check('arrivée ne renseigne pas la présence pédagogique', $state['attendance'], []);

// 2. Double scan : ALREADY_PRESENT, aucune écriture de plus.
$r = SchoolSafeEntryScan($qr('E-ACTIVE'), '7', $secretHex, $ctx, $db, $morning);
check('double => ALREADY_PRESENT', $r['result'], 'ALREADY_PRESENT');
check('double => aucune écriture', $writes, 1);

// 3. Retard : PRESENT + late.
resetState();
$r = SchoolSafeEntryScan($qr('E-ACTIVE'), '7', $secretHex, $ctx, $db, $late);
check('retard => PRESENT', $r['result'], 'PRESENT');
check('retard => late', $r['late'], true);

// 4. Portail inconnu : DENIED, aucun record.
resetState();
$before = $writes = 0;
$r = SchoolSafeEntryScan($qr('E-ACTIVE'), '999', $secretHex, $ctx, $db, $morning);
check('lieu inconnu => DENIED', $r['result'], 'DENIED');
check('lieu inconnu => motif', $r['reason'], 'LOCATION_DENIED');
check('lieu inconnu => aucun record', count($state['records']), 0);

// 5. Portail sens unique (sortie) : DENIED + tentative tracée unauthorized.
resetState();
$r = SchoolSafeEntryScan($qr('E-ACTIVE'), '8', $secretHex, $ctx, $db, $morning);
check('mauvais sens => DENIED', $r['result'], 'DENIED');
check('mauvais sens => tracé Y', $state['records'][0]['unauth'], 'Y');

// 6. Carte falsifiée / perdue / mauvaise école : refus, aucune écriture.
resetState();
$r = SchoolSafeEntryScan('schoolsafe://card/E-ACTIVE/' . str_repeat('B', 32), '7', $secretHex, $ctx, $db, $morning);
check('falsifiée => INVALID', $r['result'], 'INVALID');
$r = SchoolSafeEntryScan($qr('E-LOST'), '7', $secretHex, $ctx, $db, $morning);
check('perdue => LOST', $r['result'], 'LOST');
$r = SchoolSafeEntryScan($qr('E-ACTIVE'), '7', $secretHex, array_merge($ctx, ['school' => 2]), $db, $morning);
check('mauvaise école => card_not_found', $r['reason'], 'card_not_found');
check('refus => aucune écriture', count($state['records']) + count($state['attendance']), 0);

// 7. Sans secret : repli (null), jamais de faux positif.
$r = SchoolSafeEntryScan($qr('E-ACTIVE'), '7', 'pas-un-secret', $ctx, $db, $morning);
check('sans secret => null', $r, null);

// 8. Présence déjà saisie par l'enseignant : PRESENT mais attendance intacte.
resetState();
$state['attendance'][] = 11;
$writes = 0;
$r = SchoolSafeEntryScan($qr('E-ACTIVE'), '7', $secretHex, $ctx, $db, $morning);
check('présence existante => PRESENT', $r['result'], 'PRESENT');
check('présence existante => 1 écriture (record seul)', $writes, 1);
check('présence enseignante inchangée', $state['attendance'], [11]);

echo $failures === 0 ? "GREEN : tous les cas passent.\n" : "STOP : $failures échec(s).\n";
exit($failures === 0 ? 0 : 1);
