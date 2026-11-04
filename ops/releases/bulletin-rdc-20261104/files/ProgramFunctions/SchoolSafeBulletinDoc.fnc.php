<?php
// Bulletin RDC (04/11, etape D validee) : document au modele RDC (en-tete officiel, branches groupees par
// maxima, periodes, examen et total de chaque trimestre, total general, maxima generaux, totaux,
// pourcentage, place, application, conduite, decision, signatures, sceau), au format A4 paysage.
// Visible par le parent quand le trimestre est valide et que les frais dus sont payes (interrupteur de
// l'ecole, ouverture exceptionnelle par la direction). Les alertes de securite ne dependent jamais des frais.
require_once __DIR__ . '/SchoolSafeBulletin.fnc.php';
require_once __DIR__ . '/SchoolSafeLetterhead.fnc.php';
require_once __DIR__ . '/SchoolSafeFeeScan.fnc.php';

/** Interrupteur de l'ecole : bulletin lie aux frais (actif par defaut). */
function SchoolSafeBulletinFeesRequired(int $school, callable $get): bool
{
    $r = $get("SELECT CONFIG_VALUE FROM config WHERE SCHOOL_ID=" . $school . " AND TITLE='SCHOOLSAFE_BULLETIN_FEES'");
    return !(is_array($r) && isset($r[1]) && strtoupper(trim((string) $r[1]['CONFIG_VALUE'])) === 'N');
}

/** Reste a payer sur les frais dus a la date (centimes). Les frais du rattrapage ne comptent pas. */
function SchoolSafeBulletinFeesRemaining(int $student, int $school, int $year, string $day, callable $get): int
{
    require_once __DIR__ . '/SchoolSafeFeeTypes.fnc.php';
    $ledger = SchoolSafeFeeTypesLedger($student, $school, $year, $get);
    $due = 0;
    foreach ($ledger['fees'] as $fee) if ((int) $fee['cents'] > 0 && ($fee['due'] === null || strcmp($fee['due'], $day) <= 0)) $due += (int) $fee['cents'];
    return max(0, $due - (int) $ledger['paid_cents']);
}

/**
 * Ce que le parent peut voir pour un eleve : classe a bulletin, trimestres valides, frais, ouverture.
 * 'open' : le bulletin s'ouvre ; sinon 'reason' explique pourquoi.
 */
function SchoolSafeBulletinAccess(int $student, int $school, int $year, string $day, callable $get): array
{
    $class = $get("SELECT DISTINCT b.CLASS_COURSE_PERIOD_ID FROM schoolsafe_class_branches b JOIN schedule sc ON sc.COURSE_PERIOD_ID=b.CLASS_COURSE_PERIOD_ID AND sc.STUDENT_ID=" . $student
        . " WHERE b.SCHOOL_ID=" . $school . " AND b.SYEAR=" . $year . " LIMIT 1");
    if (!$class) return ['class' => 0, 'open' => false, 'validated' => [], 'reason' => 'none', 'remaining' => 0];
    $classCp = (int) $class[1]['CLASS_COURSE_PERIOD_ID'];
    $validated = [];
    foreach ((array) $get("SELECT MARKING_PERIOD_ID FROM schoolsafe_bulletin_status WHERE CLASS_COURSE_PERIOD_ID=" . $classCp . " AND STUDENT_ID=" . $student . " AND STATUS='valide'") as $r) $validated[] = (int) $r['MARKING_PERIOD_ID'];
    if (!$validated) return ['class' => $classCp, 'open' => false, 'validated' => [], 'reason' => 'pending', 'remaining' => 0];
    if (!SchoolSafeBulletinFeesRequired($school, $get)) return ['class' => $classCp, 'open' => true, 'validated' => $validated, 'reason' => '', 'remaining' => 0];
    $unlocked = (bool) $get("SELECT 1 FROM schoolsafe_bulletin_unlocks WHERE CLASS_COURSE_PERIOD_ID=" . $classCp . " AND STUDENT_ID=" . $student . " AND MARKING_PERIOD_ID IN (" . implode(',', $validated) . ") LIMIT 1");
    $remaining = SchoolSafeBulletinFeesRemaining($student, $school, $year, $day, $get);
    if ($remaining === 0 || $unlocked) return ['class' => $classCp, 'open' => true, 'validated' => $validated, 'reason' => $unlocked && $remaining ? 'unlocked' : '', 'remaining' => $remaining];
    return ['class' => $classCp, 'open' => false, 'validated' => $validated, 'reason' => 'fees', 'remaining' => $remaining];
}

/** Branches groupees par maxima de periode (croissants), ordre de la liste conserve dans chaque groupe. */
function SchoolSafeBulletinGroups(array $branches): array
{
    $groups = [];
    foreach ($branches as $b) $groups[SchoolSafeBulletinNumber($b['MAX_PERIOD'])][] = $b;
    uksort($groups, static function ($a, $b) { return (float) str_replace(',', '.', $a) <=> (float) str_replace(',', '.', $b); });
    return $groups;
}

/**
 * HTML du bulletin. $v : identity, student (FIRST_NAME, LAST_NAME, SEXE, NAISSANCE, MATRICULE), class_title,
 * year_label, branches, calc (SchoolSafeBulletinCompute), terms, status [mp => row], visible (mp des trimestres
 * montres), fy (mp de l'annee), sid, count, draft (bool), titulaire (nom), today.
 */
function SchoolSafeBulletinHtml(array $v): string
{
    $e = static fn($x) => htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8');
    $n = static fn($x) => SchoolSafeBulletinNumber($x);
    $id = $v['identity']; $s = $v['student']; $sid = (int) $v['sid'];
    $r = $v['calc']['students'][$sid] ?? ['cells' => [], 'totals' => [], 'pct' => [], 'rank' => []];
    $slots = $v['calc']['slots'];
    $visible = array_flip($v['visible']);
    $showTG = isset($visible[(int) $v['fy']]);
    $shown = static function ($slot) use ($visible, $showTG) { return $slot['kind'] === 'general' ? $showTG : isset($visible[$slot['term']]); };
    $cell = static function ($pair) use ($n) { return $pair[0] === null ? '' : $n($pair[0]); };
    $cols = 1 + count($slots);
    $head1 = '<tr><th rowspan="2" class="br">BRANCHES</th>';
    foreach ($v['terms'] as $t) $head1 .= '<th colspan="' . (count($t['periods']) + 2) . '">' . $e(mb_strtoupper($t['title'])) . '</th>';
    $head1 .= '<th rowspan="2">T.G.</th></tr>';
    $head2 = '<tr>';
    foreach ($slots as $slot) if ($slot['kind'] !== 'general') $head2 .= '<th>' . $e($slot['kind'] === 'periode' ? $slot['label'] : ($slot['kind'] === 'examen' ? 'Exam.' : 'Tot.')) . '</th>';
    $head2 .= '</tr>';
    $body = '';
    foreach (SchoolSafeBulletinGroups($v['branches']) as $max => $list) {
        $first = $list[0];
        $body .= '<tr class="mx"><td class="br">MAXIMA</td>';
        foreach ($slots as $key => $slot) {
            $c = $r['cells'][(int) $first['COURSE_PERIOD_ID']][$key] ?? [null, 0];
            $body .= '<td>' . $e($n($c[1])) . '</td>';
        }
        $body .= '</tr>';
        foreach ($list as $b) {
            $bcp = (int) $b['COURSE_PERIOD_ID'];
            $body .= '<tr><td class="br">' . $e($b['TITLE']) . '</td>';
            foreach ($slots as $key => $slot) $body .= '<td>' . ($shown($slot) ? $e($cell($r['cells'][$bcp][$key] ?? [null, 0])) : '') . '</td>';
            $body .= '</tr>';
        }
    }
    $row = static function ($label, callable $f) use ($slots, $e) { $h = '<tr class="ft"><td class="br">' . $e($label) . '</td>'; foreach ($slots as $k => $sl) $h .= '<td>' . $f($k, $sl) . '</td>'; return $h . '</tr>'; };
    $foot = $row('MAXIMA GÉNÉRAUX', fn($k, $sl) => $e($n($r['totals'][$k][1] ?? 0)));
    $foot .= $row('TOTAUX', fn($k, $sl) => $shown($sl) && ($r['totals'][$k][2] ?? false) ? $e($n($r['totals'][$k][0])) : '');
    $foot .= $row('POURCENTAGE', fn($k, $sl) => $shown($sl) && ($r['pct'][$k] ?? null) !== null ? $e($n($r['pct'][$k])) : '');
    $foot .= $row('PLACE / NBRE ÉLÈVES', fn($k, $sl) => $shown($sl) && isset($r['rank'][$k]) ? (int) $r['rank'][$k] . '/' . (int) $v['count'] : '');
    foreach (['APPLICATION' => 'APPLICATION', 'CONDUITE' => 'CONDUITE'] as $field => $label) {
        $h = '<tr class="ft"><td class="br">' . $label . '</td>';
        foreach ($v['terms'] as $t) $h .= '<td colspan="' . (count($t['periods']) + 2) . '">' . (isset($visible[$t['id']]) ? $e($v['status'][$t['id']][$field] ?? '') : '') . '</td>';
        $h .= '<td>' . ($showTG ? $e($v['status'][(int) $v['fy']][$field] ?? '') : '') . '</td></tr>';
        $foot .= $h;
    }
    $foot .= '<tr class="ft"><td class="br">SIGN. DU RESPONSABLE</td><td colspan="' . ($cols - 1) . '" style="height:6mm"></td></tr>';
    $year = $v['status'][(int) $v['fy']] ?? [];
    $decision = $showTG ? (string) ($year['DECISION'] ?? '') : '';
    $box = static fn($k) => $decision === $k ? '☒' : '☐';
    $decisions = '<div class="dec"><div>' . $box('passe') . ' L’élève passe dans la classe supérieure</div><div>' . $box('double') . ' L’élève double sa classe</div>'
        . '<div>' . $box('repechage') . ' L’élève ne pourra passer dans la classe supérieure s’il ne subit avec succès un examen de repêchage en : ' . ($decision === 'repechage' ? $e($year['DECISION_NOTE'] ?? '') : '…………') . '</div>'
        . '<div>' . $box('echoue') . ' L’élève a échoué</div><div>' . $box('oriente') . ' L’élève a échoué et est orienté vers : ' . ($decision === 'oriente' ? $e($year['DECISION_NOTE'] ?? '') : '…………') . '</div>'
        . ($showTG && trim((string) ($year['TITULAIRE_NOTE'] ?? '')) !== '' ? '<div class="mot"><b>Mot du titulaire :</b> ' . $e($year['TITULAIRE_NOTE']) . '</div>' : '') . '</div>';
    $dots = '……………………';
    $sexe = trim((string) ($s['SEXE'] ?? ''));
    $birth = trim((string) ($s['NAISSANCE'] ?? ''));
    $birthTxt = $birth !== '' ? date('d/m/Y', strtotime($birth)) : $dots;
    $city = trim((string) ($id['city'] ?? ''));
    return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Bulletin</title><style>'
        . '@page{size:A4 landscape;margin:6mm}body{font:7.6pt Arial,sans-serif;color:#111;margin:0}'
        . '.top{display:grid;grid-template-columns:1fr 1fr;gap:4mm;border:0.4mm solid #111;padding:1.2mm 3mm}.top b{font-size:8.6pt}.id div{margin:0.2mm 0}'
        . '.title{text-align:center;font-weight:bold;font-size:10pt;margin:1.2mm 0;border:0.4mm solid #111;padding:0.8mm}'
        . 'table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{border:0.3mm solid #111;padding:0.25mm 0.6mm;text-align:center;vertical-align:middle;line-height:1.15}th{font-size:6.8pt;background:#eee}'
        . 'td.br,th.br{text-align:left;width:54mm;white-space:nowrap;overflow:hidden}tr.mx td{background:#eef7f4;font-weight:bold;font-size:6.8pt}tr.ft td{font-weight:bold}'
        . '.bottom{display:grid;grid-template-columns:1.4fr 1fr;gap:3mm;margin-top:1.2mm}.dec{border:0.4mm solid #111;padding:1.2mm 2mm;line-height:1.3}.mot{margin-top:1mm;font-weight:normal}'
        . '.sign{border:0.4mm solid #111;padding:1.2mm;display:grid;grid-template-columns:1fr 1fr;gap:1mm;text-align:center}.sign div{min-height:13mm}'
        . '.note{font-size:6.8pt;margin-top:0.8mm;display:flex;justify-content:space-between}.draft{position:fixed;top:40%;left:15%;font-size:60pt;color:rgba(200,0,0,0.12);transform:rotate(-20deg);font-weight:bold}'
        . '</style></head><body>' . (!empty($v['draft']) ? '<div class="draft">PROJET — NON VALIDÉ</div>' : '')
        . '<div class="top"><div class="id"><b>RÉPUBLIQUE DÉMOCRATIQUE DU CONGO</b><div><b>' . $e($id['ministry'] ?? 'MINISTÈRE DE L’ÉDUCATION NATIONALE ET NOUVELLE CITOYENNETÉ') . '</b></div>'
        . '<div>N° ID : ' . $dots . '</div><div>PROVINCE : ' . $e(($id['province'] ?? '') ?: $dots) . '</div><div>VILLE : ' . $e($city ?: $dots) . '</div><div>COMMUNE : ' . $dots . '</div>'
        . '<div>ÉCOLE : <b>' . $e($id['school'] ?? '') . '</b></div><div>CODE : ' . $dots . '</div></div>'
        . '<div class="id"><div>ÉLÈVE : <b>' . $e(mb_strtoupper(trim(($s['LAST_NAME'] ?? '') . ' ' . ($s['FIRST_NAME'] ?? '')))) . '</b></div><div>SEXE : ' . $e($sexe ?: $dots) . '</div>'
        . '<div>NÉ(E) À : ' . $dots . ' LE : ' . $e($birthTxt) . '</div><div>CLASSE : <b>' . $e($v['class_title']) . '</b></div><div>N° PERM. : ' . $e(($s['MATRICULE'] ?? '') ?: $dots) . '</div>'
        . '<div>TITULAIRE : ' . $e($v['titulaire'] ?? '') . '</div></div></div>'
        . '<div class="title">BULLETIN DE LA ' . $e(mb_strtoupper($v['class_title'])) . ' — ANNÉE SCOLAIRE ' . $e($v['year_label']) . '</div>'
        . '<table><thead>' . $head1 . $head2 . '</thead><tbody>' . $body . $foot . '</tbody></table>'
        . '<div class="bottom">' . $decisions . '<div class="sign"><div>Fait à ' . $e($city ?: $dots) . ', le ' . $e(SchoolSafeLetterheadDate($v['today'], false)) . '<br><br>Le Titulaire<br><br>' . $e($v['titulaire'] ?? '') . '</div>'
        . '<div><br><br>Le Chef d’Établissement<br>Sceau de l’école<br><br>' . $e($id['principal'] ?? '') . '</div><div style="grid-column:1/3;min-height:10mm">Signature du parent : ' . $dots . '</div></div></div>'
        . '<div class="note"><span>Note importante : le bulletin est sans valeur s’il est raturé ou surchargé.</span><span><b>SchoolSafe</b> · <i>Un enfant protégé, un parent informé</i></span></div>'
        . '</body></html>';
}

/** Message aux parents quand la direction valide un trimestre. */
function SchoolSafeBulletinNotice(string $first, string $term, string $link, int $remaining, bool $feesRequired, string $school): array
{
    $who = $first !== '' ? $first : 'votre enfant';
    $body = $feesRequired && $remaining > 0
        ? '📄 Le bulletin du ' . $term . ' de ' . $who . ' est prêt. Il s’ouvrira dès la régularisation des frais scolaires (reste à payer : ' . SchoolSafeFeeFormatCents($remaining) . ').'
        : '📄 Le bulletin du ' . $term . ' de ' . $who . ' est disponible : ' . $link;
    return ['Bulletin du ' . $term, $body . "\n— " . ($school !== '' ? $school . ' · ' : '') . 'SchoolSafe · Un enfant protégé, un parent informé'];
}
