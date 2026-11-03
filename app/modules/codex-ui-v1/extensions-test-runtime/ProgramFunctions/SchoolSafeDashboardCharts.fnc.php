<?php
function SchoolSafeDashboardLineChart(array $series,array $labels): string
{
    $e=static function($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');};
    if(!$series)return '<svg class="ss-native-line-chart ss-chart-empty" viewBox="0 0 560 260" role="img" aria-label="Aucune transaction pour la periode selectionnee"><path d="M65 25V205H540 M65 70H540 M65 115H540 M65 160H540" fill="none" stroke="#deebf8"/><text x="300" y="125" text-anchor="middle">Aucune transaction pour cette periode.</text></svg>';
    $colors=['fees'=>'#ac49d7','payments'=>'#087bff','income'=>'#087bff','expense'=>'#ac49d7'];
    $min=0;$max=1;
    foreach($series as $values)foreach($values as $v){$min=min($min,$v);$max=max($max,$v);}
    $html='<svg class="ss-native-line-chart" viewBox="0 0 560 260" role="img" aria-label="Evolution mensuelle des montants"><title>Evolution mensuelle des montants</title>';
    for($i=0;$i<=4;$i++){
        $y=25+$i*45;$v=$max-($max-$min)*$i/4;
        $html.='<line x1="65" x2="540" y1="'.$y.'" y2="'.$y.'" stroke="#deebf8"/><text x="58" y="'.($y+4).'" text-anchor="end">'.$e(round($v,2)).'</text>';
    }
    $count=count($series);
    foreach($labels as $kind=>$label){$points=[];$i=0;
        foreach($series as $month=>$values){$x=$count===1?302:65+$i*475/($count-1);$y=205-(($values[$kind]??0)-$min)/($max-$min)*180;$points[]=$x.','.$y;
            $html.='<circle cx="'.$x.'" cy="'.$y.'" r="4" fill="'.$colors[$kind].'"><title>'.$e($label.' '.$month.' : '.($values[$kind]??0)).'</title></circle>';
            $i++;
        }
        $html.='<polyline points="'.implode(' ',$points).'" fill="none" stroke="'.$colors[$kind].'" stroke-width="2.5"/>';
    }
    $i=0;foreach($series as $month=>$values){$x=$count===1?302:65+$i*475/($count-1);if($count<=8||$i%2===0)$html.='<text x="'.$x.'" y="235" text-anchor="middle">'.$e(substr($month,2)).'</text>';$i++;}
    $html.='</svg><div class="ss-finance-legend">';
    foreach($labels as $kind=>$label)$html.='<span data-finance-kind="'.$kind.'">'.$e($label).'</span>';
    $html.='</div><details><summary>Voir les montants mensuels</summary><div class="sscx-table-scroll"><table><thead><tr><th>Mois</th>';
    foreach($labels as $label)$html.='<th>'.$e($label).'</th>';
    $html.='</tr></thead><tbody>';
    foreach($series as $month=>$values){$html.='<tr><th scope="row">'.$e($month).'</th>';foreach($labels as $kind=>$label)$html.='<td>'.$e(html_entity_decode(strip_tags(Currency($values[$kind]??0)),ENT_QUOTES,'UTF-8')).'</td>';$html.='</tr>';}
    return $html.'</tbody></table></div></details>';
}

function SchoolSafeDashboardExpenseChart(array $rows): string
{
    $e=static function($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');};
    $total=array_sum(array_column(array_values($rows),'TOTAL'));
    if($total<=0)return '<div class="ss-expense-breakdown"><svg viewBox="0 0 200 200" role="img" aria-label="Aucune dépense positive enregistrée"><circle cx="100" cy="100" r="70" fill="none" stroke="#e6eff9" stroke-width="28"/><text x="100" y="104" text-anchor="middle">Sans données</text></svg><p class="sscx-empty">Aucune dépense positive enregistrée.</p></div>';
    $palette=['#087bff','#00bda5','#ffb400','#ed583e','#ac49d7','#2584a6'];$offset=0;$i=0;
    $html='<div class="ss-expense-breakdown"><svg viewBox="0 0 200 200" role="img" aria-label="Repartition des depenses positives"><title>Repartition des depenses positives</title>';
    foreach($rows as $row){$share=(float)$row['TOTAL']/$total*100;$color=$palette[$i++%count($palette)];$html.='<circle cx="100" cy="100" r="70" fill="none" stroke="'.$color.'" stroke-width="28" pathLength="100" stroke-dasharray="'.$share.' '.(100-$share).'" stroke-dashoffset="'.(-$offset).'" transform="rotate(-90 100 100)"><title>'.$e($row['TITLE'].' : '.html_entity_decode(strip_tags(Currency($row['TOTAL'])),ENT_QUOTES,'UTF-8')).'</title></circle>';$offset+=$share;}
    $html.='<text x="100" y="100" text-anchor="middle">'.round($total,2).'</text><text x="100" y="122" text-anchor="middle">Depenses</text></svg><ul>';
    foreach($rows as $row)$html.='<li><strong>'.$e(ParseMLField($row['TITLE'])).'</strong><span>'.round((float)$row['TOTAL']/$total*100,1).'%</span><small>'.$e(html_entity_decode(strip_tags(Currency($row['TOTAL'])),ENT_QUOTES,'UTF-8')).'</small></li>';
    return $html.'</ul></div>';
}
