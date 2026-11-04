<?php
// Dossier de l'annee de l'eleve (04/11, idee du proprietaire) : chaque annee, le parent garde tous les
// travaux faits par son enfant (fiche, note, appreciation, corrige, copie corrigee), classes par trimestre
// et par matiere, avec un bilan (progression, points forts, points a travailler, presences) et un PDF au
// papier de l'ecole. Lecture seule ; rien n'est recopie : tout vient des registres deja remplis.

/** Annees scolaires de l'eleve dans cette ecole (la plus recente d'abord). */
function SchoolSafePortfolioYears(int $student, int $school, callable $get): array
{
    if ($student < 1 || $school < 1) return [];
    $years = [];
    foreach ((array) $get("SELECT DISTINCT SYEAR FROM student_enrollment WHERE STUDENT_ID=" . $student . " AND SCHOOL_ID=" . $school . " ORDER BY SYEAR DESC") as $row) $years[] = (int) $row['SYEAR'];
    return $years;
}

/** Libelle d'une annee scolaire : 2026 -> « 2026-2027 ». */
function SchoolSafePortfolioYearLabel(int $year): string
{
    return $year . '-' . ($year + 1);
}

/**
 * Tous les travaux de l'annee : donnes pendant l'inscription de l'enfant dans le cours, deja notes ou dont la
 * date de remise est passee. Avec trimestre, note, appreciation, corrige et copie corrigee.
 */
function SchoolSafePortfolioWorks(int $student, int $school, int $year, string $today, callable $get): array
{
    if ($student < 1 || $school < 1 || $year < 1) return [];
    return (array) $get("SELECT DISTINCT ON (ga.ASSIGNMENT_ID) ga.ASSIGNMENT_ID,ga.TITLE,ga.ASSIGNED_DATE,ga.DUE_DATE,ga.POINTS AS MAXIMUM,cp.COURSE_PERIOD_ID,c.TITLE AS COURSE_TITLE,"
        . "t.TITLE AS TYPE_TITLE,gg.POINTS,gg.COMMENT,q.TITLE AS PERIOD_TITLE,q.SORT_ORDER AS PERIOD_SORT,COALESCE(sm.TITLE,q.TITLE) AS TERM_TITLE,COALESCE(sm.SORT_ORDER,q.SORT_ORDER) AS TERM_SORT,"
        . "(SELECT 1 FROM schoolsafe_grade_copies gc WHERE gc.ASSIGNMENT_ID=ga.ASSIGNMENT_ID AND gc.STUDENT_ID=sc.STUDENT_ID) AS HAS_COPY,"
        . "(SELECT 1 FROM schoolsafe_assignment_keys k WHERE k.ASSIGNMENT_ID=ga.ASSIGNMENT_ID) AS HAS_KEY,"
        . "(SELECT 1 FROM student_assignments sa WHERE sa.ASSIGNMENT_ID=ga.ASSIGNMENT_ID AND sa.STUDENT_ID=sc.STUDENT_ID LIMIT 1) AS HANDED_IN"
        . " FROM schedule sc JOIN course_periods cp ON cp.COURSE_PERIOD_ID=sc.COURSE_PERIOD_ID AND cp.SCHOOL_ID=" . $school . " AND cp.SYEAR=" . $year
        . " JOIN gradebook_assignments ga ON (ga.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID) OR (ga.COURSE_PERIOD_ID IS NULL AND ga.COURSE_ID=cp.COURSE_ID AND ga.STAFF_ID=cp.TEACHER_ID)"
        . " JOIN courses c ON c.COURSE_ID=cp.COURSE_ID LEFT JOIN gradebook_assignment_types t ON t.ASSIGNMENT_TYPE_ID=ga.ASSIGNMENT_TYPE_ID"
        . " LEFT JOIN gradebook_grades gg ON gg.ASSIGNMENT_ID=ga.ASSIGNMENT_ID AND gg.STUDENT_ID=sc.STUDENT_ID"
        . " LEFT JOIN school_marking_periods q ON q.MARKING_PERIOD_ID=ga.MARKING_PERIOD_ID LEFT JOIN school_marking_periods sm ON sm.MARKING_PERIOD_ID=q.PARENT_ID"
        . " WHERE sc.STUDENT_ID=" . $student . " AND COALESCE(ga.DUE_DATE,ga.ASSIGNED_DATE)>=sc.START_DATE"
        . " AND (sc.END_DATE IS NULL OR COALESCE(ga.ASSIGNED_DATE,ga.DUE_DATE)<=sc.END_DATE)"
        . " AND (gg.POINTS IS NOT NULL OR ga.DUE_DATE<'" . $today . "')"
        . " ORDER BY ga.ASSIGNMENT_ID LIMIT 2000");
}

/** Pourcentage d'une note, null si non notee ou dispense. */
function SchoolSafePortfolioPercent($points, $maximum): ?float
{
    if ($points === null || $points === '' || (float) $points < 0 || (float) $maximum <= 0) return null;
    return (float) $points * 100 / (float) $maximum;
}

/** Classement : trimestre -> matiere -> travaux (par date). */
function SchoolSafePortfolioGroup(array $works): array
{
    usort($works, static function ($a, $b) {
        $k = static function ($w) { return [(int) ($w['TERM_SORT'] ?? 0), SchoolSafePortfolioTitle((string) $w['COURSE_TITLE']), (string) (($w['DUE_DATE'] ?? '') ?: ($w['ASSIGNED_DATE'] ?? '')), (int) $w['ASSIGNMENT_ID']]; };
        return $k($a) <=> $k($b);
    });
    $groups = [];
    foreach ($works as $w) {
        $term = trim((string) ($w['TERM_TITLE'] ?? '')) ?: 'Sans trimestre';
        $groups[$term][SchoolSafePortfolioTitle((string) $w['COURSE_TITLE'])][] = $w;
    }
    return $groups;
}

/** Titre lisible meme hors de RosarioSIS (tests). */
function SchoolSafePortfolioTitle(string $title): string
{
    if (function_exists('ParseMLField')) return (string) ParseMLField($title);
    if (strpos($title, '|') === false) return trim($title);
    if (preg_match('/\|fr_FR\.utf8:([^|]*)/', $title, $m) && trim($m[1]) !== '') return trim($m[1]);
    return trim(explode('|', $title)[0]);
}

/**
 * Bilan par matiere : nombre de travaux, notes, moyenne, premiere et derniere note, sens de la progression.
 * Points forts : moyenne >= seuil + 20 ; a travailler : moyenne sous le seuil.
 */
function SchoolSafePortfolioSummary(array $works, float $threshold): array
{
    $courses = [];
    usort($works, static function ($a, $b) { return [(string) (($a['DUE_DATE'] ?? '') ?: ($a['ASSIGNED_DATE'] ?? '')), (int) $a['ASSIGNMENT_ID']] <=> [(string) (($b['DUE_DATE'] ?? '') ?: ($b['ASSIGNED_DATE'] ?? '')), (int) $b['ASSIGNMENT_ID']]; });
    foreach ($works as $w) {
        $name = SchoolSafePortfolioTitle((string) $w['COURSE_TITLE']);
        $courses[$name] = $courses[$name] ?? ['works' => 0, 'graded' => 0, 'sum' => 0.0, 'series' => []];
        $courses[$name]['works']++;
        $p = SchoolSafePortfolioPercent($w['POINTS'] ?? null, $w['MAXIMUM'] ?? 0);
        if ($p !== null) { $courses[$name]['graded']++; $courses[$name]['sum'] += $p; $courses[$name]['series'][] = round($p); }
    }
    $out = ['courses' => [], 'strong' => [], 'weak' => [], 'average' => null];
    $all = 0.0; $n = 0;
    ksort($courses);
    foreach ($courses as $name => $c) {
        $avg = $c['graded'] ? round($c['sum'] / $c['graded'], 1) : null;
        $s = $c['series'];
        $dir = '';
        if (count($s) >= 2) {
            $half = intdiv(count($s), 2);
            $first = array_sum(array_slice($s, 0, $half)) / $half;
            $last = array_sum(array_slice($s, -$half)) / $half;
            $dir = $last - $first >= 5 ? 'hausse' : ($first - $last >= 5 ? 'baisse' : 'stable');
        }
        $out['courses'][$name] = ['works' => $c['works'], 'graded' => $c['graded'], 'average' => $avg, 'series' => $s, 'direction' => $dir];
        if ($avg !== null) {
            $all += $avg; $n++;
            if ($avg >= $threshold + 20) $out['strong'][] = $name;
            elseif ($avg < $threshold) $out['weak'][] = $name;
        }
    }
    $out['average'] = $n ? round($all / $n, 1) : null;
    return $out;
}

/** Presences de l'annee : absences, absences excusees, retards (codes de l'appel des enseignants). */
function SchoolSafePortfolioAttendance(int $student, int $school, int $year, callable $get): array
{
    $out = ['Absent' => 0, 'Absence excusée' => 0, 'Retard' => 0];
    if ($student < 1) return $out;
    foreach ((array) $get("SELECT ac.TITLE,ac.STATE_CODE,ac.TYPE,count(*) AS N FROM attendance_period ap JOIN attendance_codes ac ON ac.ID=ap.ATTENDANCE_CODE"
        . " WHERE ap.STUDENT_ID=" . $student . " AND ac.SCHOOL_ID=" . $school . " AND ac.SYEAR=" . $year . " AND COALESCE(ac.DEFAULT_CODE,'')<>'Y'"
        . " GROUP BY ac.TITLE,ac.STATE_CODE,ac.TYPE") as $r) {
        $label = function_exists('SchoolSafeChildWorkCode') ? SchoolSafeChildWorkCode((string) $r['TITLE'], (string) $r['STATE_CODE'], (string) $r['TYPE']) : (string) $r['TITLE'];
        if (isset($out[$label])) $out[$label] += (int) $r['N'];
    }
    return $out;
}

/** Petite courbe SVG d'une serie de pourcentages (ecran et PDF, sans script ni reseau). */
function SchoolSafePortfolioSpark(array $series): string
{
    if (count($series) < 2) return '';
    $w = 120; $h = 28; $n = count($series);
    $pts = [];
    foreach (array_values($series) as $i => $v) $pts[] = round($i * ($w - 4) / ($n - 1) + 2, 1) . ',' . round($h - 2 - max(0, min(100, (float) $v)) * ($h - 4) / 100, 1);
    return '<svg width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="Progression"><polyline fill="none" stroke="#08766b" stroke-width="2" points="' . implode(' ', $pts) . '"/></svg>';
}

/** PDF du dossier au papier de l'ecole : page de garde, bilan, travaux par trimestre et matiere, copies corrigees. */
function SchoolSafePortfolioPdfHtml(array $v): string
{
    extract($v);
    require_once __DIR__ . '/SchoolSafeLetterhead.fnc.php';
    require_once __DIR__ . '/SchoolSafeGradeNotice.fnc.php';
    $body='<style>.pf-table{width:100%;max-width:100%;table-layout:fixed;box-sizing:border-box;border-collapse:collapse;font-size:10.5pt;margin:2mm 0 4mm}.pf-table th,.pf-table td{border:0.3mm solid #c9d8d2;padding:1.5mm 2mm;text-align:left;vertical-align:top}.pf-table th{background:#eef7f4}'
        .'.pf-ok{color:#08766b;font-weight:bold}.pf-mid{color:#a86200;font-weight:bold}.pf-ko{color:#a32d2d;font-weight:bold}.pf-cover{display:flex;gap:6mm;align-items:center;margin:2mm 0 5mm}.pf-cover img{width:32mm;height:40mm;object-fit:cover;border:0.3mm solid #c9d8d2;border-radius:2mm}'
        .'.pf-kpi{display:flex;gap:4mm;flex-wrap:wrap}.pf-kpi div{border:0.3mm solid #c9d8d2;border-radius:2mm;padding:2mm 3mm}.pf-kpi b{display:block;font-size:14pt;color:#08766b}h2{font-size:13pt;color:#176b52;margin:5mm 0 2mm}h3{font-size:11.5pt;margin:4mm 0 1mm}'
        .'.pf-answer{font-size:9pt;color:#334;white-space:pre-wrap;margin:1mm 0 0}.pf-break{page-break-before:always}.pf-copy{page-break-inside:avoid;margin:4mm 0}.pf-copy img{max-width:100%;max-height:230mm;border:0.3mm solid #c9d8d2}</style>';
    $body.='<div class="pf-cover">'.($photo?'<img src="'.$photo.'" alt="">':'').'<div><div style="font-size:16pt;font-weight:bold">'.$e($name).'</div><div>'.$e($classTitle).'</div><div>Année scolaire '.$e($yearLabel).'</div><div>'.$e($schoolName).'</div></div></div>';
    $body.='<div class="pf-kpi"><div><b>'.count($works).'</b>travaux</div><div><b>'.$graded.'</b>notes</div><div><b>'.$e($pct($summary['average'])).'</b>moyenne des matières</div></div>';
    $body.='<h2>Bilan de l’année</h2>'.$bilan.$strengths.'<p>'.$e($presenceText).'</p>';
    foreach($groups as $term=>$courses){
        $body.='<h2 class="pf-break">'.$e($term).'</h2>';
        foreach($courses as $course=>$list){
            $body.='<h3>'.$e($course).'</h3><table class="pf-table"><thead><tr><th style="width:27mm">Date</th><th>Travail</th><th style="width:27mm">Note</th><th>Appréciation et corrigé</th></tr></thead><tbody>';
            foreach($list as $w){
                $a=(int)$w['ASSIGNMENT_ID'];$p=SchoolSafePortfolioPercent($w['POINTS'],$w['MAXIMUM']);
                $cls=$p===null?'':($p<$threshold?'pf-ko':'pf-ok');
                $answer=isset($answers[$a])&&SchoolSafeGradeNoticeAnswerVisible($w['DUE_DATE'],$today,false)?'<div class="pf-answer"><b>Corrigé :</b> '.$e(mb_substr($answers[$a],0,1200)).'</div>':'';
                $body.='<tr><td>'.$e(SchoolSafeLetterheadDate((string)(($w['DUE_DATE']??'')?:($w['ASSIGNED_DATE']??'')),false)).'</td><td>'.$e(($w['TYPE_TITLE']?$w['TYPE_TITLE'].' — ':'').$w['TITLE']).'</td>'
                    .'<td class="'.$cls.'">'.$e(SchoolSafeGradeNoticeMark($w['POINTS'],$w['MAXIMUM'])?:'non noté').'</td><td>'.$e((string)$w['COMMENT']).$answer.(isset($copies[$a])?'<div class="pf-answer">Copie corrigée en fin de dossier.</div>':'').'</td></tr>';
            }
            $body.='</tbody></table>';
        }
    }
    if($copies){
        $body.='<h2 class="pf-break">Copies corrigées</h2>';
        $byId=[];foreach($works as $w)$byId[(int)$w['ASSIGNMENT_ID']]=$w;
        foreach($copies as $a=>$c){$w=$byId[$a]??null;if(!$w)continue;
            $body.='<div class="pf-copy"><h3>'.$e($t($w['COURSE_TITLE']).' — '.$w['TITLE'].' ('.(SchoolSafeGradeNoticeMark($w['POINTS'],$w['MAXIMUM'])?:'non noté').')').'</h3><img src="data:'.$c['mime'].';base64,'.$c['b64'].'" alt=""></div>';}
    }
    if(!$works)$body.='<p>Aucun travail enregistré pour cette année scolaire.</p>';
    $html=SchoolSafeLetterheadPage($identity,(string)($identity['layout']??'classique'),['title'=>'Dossier de l’année '.$yearLabel,'meta'=>[['Élève',$name],['Classe',$classTitle],['Établi le',SchoolSafeLetterheadDate($today,false)]],'body'=>$body,'style'=>$identity['style']??'']);
    return $html;
}
