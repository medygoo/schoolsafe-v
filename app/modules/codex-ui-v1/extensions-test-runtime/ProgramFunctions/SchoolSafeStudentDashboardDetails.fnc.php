<?php
function SchoolSafeStudentDashboardDetails(array $data): string
{
    if(User('PROFILE') !== 'admin' || !AllowUse('Students/Student.php')) return '';
    $e=static function($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');};
    $html='<section class="ss-dashboard-details"><header><h3>Eleves : repartitions et suivi</h3><a href="Modules.php?modname=Students/Student.php">Consulter les dossiers</a></header><p>Effectifs actifs dans l\'ecole et l\'annee selectionnees. Les presences correspondent au jour courant.</p><div class="ss-student-gender"><h4>Effectifs filles et garcons</h4><table><thead><tr><th>Niveau</th><th>Total</th><th>Filles</th><th>Garçons</th><th>Non renseigne / autre</th></tr></thead><tbody>';
    foreach($data['gender']??[] as $row){$total=(int)$row['TOTAL'];$girls=(int)$row['GIRLS'];$boys=(int)$row['BOYS'];$label=(int)$row['IS_TOTAL']===1?'Toute l\'ecole':ParseMLField($row['TITLE']);$html.='<tr><th scope="row">'.$e($label).'</th><td>'.$total.'</td><td>'.$girls.'</td><td>'.$boys.'</td><td>'.max(0,$total-$girls-$boys).'</td></tr>';}
    if(empty($data['gender']))$html.='<tr><td colspan="5">Aucune repartition disponible.</td></tr>';
    $html.='</tbody></table></div><div class="ss-personnel-tables"><details open><summary>Effectifs par niveau</summary><table><thead><tr><th>Niveau</th><th>Élèves</th></tr></thead><tbody>';
    foreach($data['groups'] as $row)$html.='<tr><th scope="row">'.$e(ParseMLField($row['TITLE'])).'</th><td>'.(int)$row['TOTAL'].'</td></tr>';
    if(!$data['groups'])$html.='<tr><td colspan="2">Aucune inscription active.</td></tr>';
    $html.='</tbody></table></details>';
    if(AllowUse('Scheduling/Courses.php')){
        $html.='<details open><summary>Effectifs par groupe de cours</summary><table><thead><tr><th>Cours</th><th>Total</th><th>Filles</th><th>Garçons</th><th>Non renseigne / autre</th></tr></thead><tbody>';
        foreach($data['classes'] as $row){$total=(int)$row['TOTAL'];$girls=(int)($row['GIRLS']??0);$boys=(int)($row['BOYS']??0);$html.='<tr><th scope="row">'.$e(ParseMLField($row['TITLE'])).'</th><td>'.$total.'</td><td>'.$girls.'</td><td>'.$boys.'</td><td>'.max(0,$total-$girls-$boys).'</td></tr>';}
        if(!$data['classes'])$html.='<tr><td colspan="5">Aucun groupe de cours.</td></tr>';
        $html.='</tbody></table><p>Un eleve peut appartenir a plusieurs cours.</p><a href="Modules.php?modname=Scheduling/Courses.php">Consulter les cours</a></details>';
    }
    if(AllowUse('Attendance/DailySummary.php')){
        $html.='<details open><summary>Presences du jour</summary><table><thead><tr><th>Etat</th><th>Élèves</th></tr></thead><tbody>';
        foreach(['present'=>'Presents','absent'=>'Absents','partial'=>'Journees partielles','unknown'=>'Non verifies'] as $key=>$label)$html.='<tr><th scope="row">'.$label.'</th><td>'.($data[$key]===null?'Non disponible':(int)$data[$key]).'</td></tr>';
        $html.='</tbody></table><a href="Modules.php?modname=Attendance/DailySummary.php">Consulter les dates et les releves</a></details>';
    }
    $html.='<div><h4>Resultats scolaires</h4>';
    foreach(['Grades/StudentGrades.php'=>'Cotes detaillees','Grades/ReportCards.php'=>'Bulletins','Grades/FinalGrades.php'=>'Notes finales'] as $route=>$label)if(AllowUse($route))$html.='<p><a href="Modules.php?modname='.$e($route).'">'.$label.'</a></p>';
    return $html.'</div></div></section>';
}
