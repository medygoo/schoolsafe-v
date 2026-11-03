<?php
// Lot Frais (03/11) : calcul de la couleur par type, part echue a la date du jour, paiements du plus ancien au plus recent.
// Usage (image candidate, sans reseau) : php test-fee-types.php /var/www/html
require_once ($argv[1] ?? '/var/www/html') . '/ProgramFunctions/SchoolSafeFeeTypes.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }

$types = SchoolSafeFeeTypesValidate(['types' => [
    ['id' => 'scolarite', 'label' => 'Scolarité', 'keywords' => ['Scolarité', 'minerval'], 'validated' => 100, 'tolerated' => 70, 'block_service' => false],
    ['id' => 'examen', 'label' => 'Examens', 'keywords' => ['examen'], 'validated' => 100, 'tolerated' => 100, 'block_service' => true],
]])['types'];
// Trois tranches de scolarite (100 000 chacune) + un examen ; aujourd'hui = 2026-11-03.
$fees = [
    ['id' => 1, 'title' => 'Scolarité – tranche 1', 'cents' => 10000000, 'due' => '2026-09-15'],
    ['id' => 2, 'title' => 'SCOLARITE tranche 2', 'cents' => 10000000, 'due' => '2027-01-15'],
    ['id' => 3, 'title' => 'Minerval tranche 3', 'cents' => 10000000, 'due' => '2027-04-15'],
    ['id' => 4, 'title' => 'Frais d’examen', 'cents' => 2000000, 'due' => '2026-10-30'],
    ['id' => 5, 'title' => 'Uniforme', 'cents' => 1500000, 'due' => null],
];
$day = '2026-11-03';

$r = SchoolSafeFeeTypesClassify($fees, 0, $types, $day);
$by = fn($r) => array_column($r['types'], null, 'id');
check('rien paye : scolarite rouge', $by($r)['scolarite']['color'], 'red');
check('rien paye : seule la tranche echue est due', $by($r)['scolarite']['due_cents'], 10000000);
check('rien paye : prochaine echeance', $by($r)['scolarite']['next_due'], '2027-01-15');
check('rien paye : examen rouge et service bloque', [$by($r)['examen']['color'], $by($r)['examen']['block_service']], ['red', true]);
check('frais non classe -> Autres frais', $by($r)['autres']['label'], 'Autres frais');
check('couleur generale = la pire', $r['color'], 'red');

// Paiement de 80 000 : couvre d'abord l'uniforme (sans date = le plus ancien), puis la tranche 1.
$r = SchoolSafeFeeTypesClassify($fees, 8000000, $types, $day);
check('80 000 : uniforme couvert en premier (vert)', $by($r)['autres']['color'], 'green');
check('80 000 : tranche 1 a 65 % -> rouge (< 70 %)', [$by($r)['scolarite']['percent'], $by($r)['scolarite']['color']], [65, 'red']);

// Paiement de 90 000 : tranche 1 a 75 % -> tolere.
$r = SchoolSafeFeeTypesClassify($fees, 9000000, $types, $day);
check('90 000 : tranche 1 a 75 % -> tolere', [$by($r)['scolarite']['percent'], $by($r)['scolarite']['color']], [75, 'yellow']);

// Paiement de 135 000 : uniforme + tranche 1 + examen (echeance 30/10) payes -> tout vert, tranches futures non exigees.
$r = SchoolSafeFeeTypesClassify($fees, 13500000, $types, $day);
check('135 000 : scolarite verte', $by($r)['scolarite']['color'], 'green');
check('135 000 : examen vert, service ouvert', [$by($r)['examen']['color'], $by($r)['examen']['block_service']], ['green', false]);
check('135 000 : couleur generale verte', $r['color'], 'green');

// Aucune tranche echue : vert (rien n'est encore du).
$r = SchoolSafeFeeTypesClassify([['id' => 9, 'title' => 'Scolarité tranche 2', 'cents' => 10000000, 'due' => '2027-01-15']], 0, $types, $day);
check('rien d echu : vert', $by($r)['scolarite']['color'], 'green');

// Remise totale (frais net a zero) : ignore.
$r = SchoolSafeFeeTypesClassify([['id' => 1, 'title' => 'Scolarité tranche 1', 'cents' => 0, 'due' => '2026-09-15']], 0, $types, $day);
check('frais remis en totalite : aucune ligne', $r['types'], []);

// Validation des reglages.
$err = null; try { SchoolSafeFeeTypesValidate(['types' => [['id' => 'x', 'label' => 'X', 'keywords' => ['x'], 'validated' => 50, 'tolerated' => 80]]]); } catch (RuntimeException $e) { $err = $e->getMessage(); }
check('tolere > valide refuse', is_string($err) && strpos($err, 'toléré') !== false, true);
$err = null; try { SchoolSafeFeeTypesValidate(['types' => [['id' => 'x', 'label' => 'X', 'keywords' => [], 'validated' => 100, 'tolerated' => 50]]]); } catch (RuntimeException $e) { $err = $e->getMessage(); }
check('mot-cle obligatoire', is_string($err), true);

// Cantine.
$canteen = SchoolSafeFeeTypesValidate(['canteen' => ['validated' => '5000', 'tolerated' => '-2000']])['canteen'];
check('cantine solde 6 000 : vert', SchoolSafeCanteenColor(600000, $canteen), 'green');
check('cantine solde 0 : tolere', SchoolSafeCanteenColor(0, $canteen), 'yellow');
check('cantine solde -3 000 : rouge', SchoolSafeCanteenColor(-300000, $canteen), 'red');
check('cantine sans seuil : rien', SchoolSafeCanteenColor(-300000, null), null);

// Tracabilite : une ligne par type, montants formates.
$trace = SchoolSafeFeeTypesTraceRows(SchoolSafeFeeTypesClassify($fees, 9000000, $types, $day), $day);
$tr = array_column($trace['types'], null, 'id');
check('trace : jour et couleur generale', [$trace['day'], $trace['color']], [$day, 'red']);
check('trace : scolarite 75 % reste 25000.00', [$tr['scolarite']['percent'], $tr['scolarite']['remaining']], [75, '25000.00']);

echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
