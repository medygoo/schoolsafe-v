<?php
// Test ciblé : restrictions exclues des candidats, alertes, refus tracés.
require __DIR__ . '/../../ProgramFunctions/SchoolSafePickupScan.fnc.php';
$fail = 0; $ok = function ($l, $c) use (&$fail) { echo ($c ? 'ok   ' : 'FAIL ') . $l . "\n"; if (!$c) $fail++; };
$log = [];
$db = ['get' => function ($sql) {
    if (strpos($sql, 'FROM schoolsafe_pickup_restrictions') !== false)
        return [1 => ['GUARDIAN_ID' => 21, 'NAME' => 'Maman Test', 'REASON' => 'Ordonnance', 'SOURCE' => 'justice'],
                2 => ['GUARDIAN_ID' => null, 'NAME' => 'Inconnu X', 'REASON' => 'Signalé', 'SOURCE' => 'ecole']];
    if (strpos($sql, "GUARDIAN_TYPE IN ('pere','mere','tuteur')") !== false)
        return [1 => ['ID' => 21, 'GUARDIAN_TYPE' => 'mere', 'FULL_NAME' => 'Maman Test', 'PHOTO_PATH' => '/p/21.jpg', 'STAFF_ID' => null, 'IS_PRIMARY' => 't'],
                2 => ['ID' => 22, 'GUARDIAN_TYPE' => 'pere', 'FULL_NAME' => 'Papa Test', 'PHOTO_PATH' => '/p/22.jpg', 'STAFF_ID' => null, 'IS_PRIMARY' => 'f']];
    if (strpos($sql, 'FROM schoolsafe_pickup_authorizations') !== false)
        return [1 => ['AUTH_ID' => 31, 'ID' => 23, 'GUARDIAN_TYPE' => 'externe', 'FULL_NAME' => 'Oncle Test', 'PHOTO_PATH' => '/p/23.jpg']];
    return [];
}, 'exec' => function ($sql) use (&$log) { $log[] = $sql; }];
$people = SchoolSafePickupCandidates(1, 11, '2026-11-03', $db);
$ids = array_map(function ($p) { return $p['guardian_id']; }, $people);
$ok('mere sous restriction exclue', !in_array(21, $ids, true));
$ok('pere et accredite restent', $ids === [22, 23]);
$r = SchoolSafePickupRestrictions(1, 11, '2026-11-03', $db);
$ok('2 alertes pour le gardien', count($r['notices']) === 2);
$ok('alerte justice libellee', $r['notices'][0]['source'] === 'décision de justice');
SchoolSafePickupLogRefusal(1, 2026, 11, 7, null, 'UNKNOWN_PERSON', 5, $db);
$ok('refus trace', count($log) === 1 && strpos($log[0], "INSERT INTO schoolsafe_pickup_refusals") === 0 && strpos($log[0], "'UNKNOWN_PERSON'") !== false);
echo $fail ? "ECHEC : $fail\n" : "GREEN : restrictions et refus\n";
