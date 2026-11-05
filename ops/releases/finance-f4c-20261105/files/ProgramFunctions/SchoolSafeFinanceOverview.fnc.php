<?php
function SchoolSafeFinanceOverview(): string
{
    require_once __DIR__.'/SchoolSafeDashboardCharts.fnc.php';
    if(User('PROFILE')!=='admin')return '';
    $school=(int)UserSchool();$year=(int)UserSyear();
    if($school<1||$year<1)return '';
    $billing=AllowUse('Student_Billing/DailyTransactions.php');
    $accounting=AllowUse('Accounting/DailyTransactions.php');
    if(!$billing&&!$accounting)return '';
    $escape=static function($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');};
    $money=static function($v){return html_entity_decode(strip_tags(Currency($v)),ENT_QUOTES,'UTF-8');};
    $start='';$end='';$periodError='';
    if(isset($_GET['finance_start'])||isset($_GET['finance_end'])){
        require_once __DIR__.'/SchoolSafePersonnelStats.fnc.php';
        [$start,$end,$periodError]=SchoolSafePersonnelPeriod(['personnel_start'=>$_GET['finance_start']??'', 'personnel_end'=>$_GET['finance_end']??'']);
        if($periodError){$start='';$end='';}
    }
    $series=[];$totals=[];
    $sources=[];
    if($billing){$sources['fees']=['billing_fees','assigned_date','Frais facturés'];$sources['payments']=['billing_payments','payment_date','Frais encaissés'];}
    if($accounting){$sources['income']=['accounting_incomes','assigned_date','Autres revenus'];$sources['expense']=['accounting_payments','payment_date','Dépenses et personnel'];}
    // Lot F4b (05/11) : frais et paiements des élèves comptés par monnaie (USD, FC), jamais additionnés.
    // $totals et $series gardent le montant USD seul (courbe, barres) ; $byCurrency garde le détail affiché.
    require_once __DIR__.'/SchoolSafeMoney.fnc.php';
    $multiCurrency=SchoolSafeMoneyCurrencyReady();
    $byCurrency=[];$monthByCurrency=[];
    // Lot F4c (05/11) : revenus, dépenses et paiements du personnel aussi par monnaie (script F4c appliqué).
    $accountingMulti=SchoolSafeMoneyAccountingReady();
    $currencyKinds=$accountingMulti?['fees','payments','income','expense']:['fees','payments'];
    foreach($sources as $kind=>$source){
        $range=$start!==''?" AND ".$source[1].">=DATE '$start' AND ".$source[1]."<DATE '$end'+INTERVAL '1 day'":'';
        $totals[$kind]=0;
        if($multiCurrency&&in_array($kind,$currencyKinds,true)){
            $rows=DBGet("SELECT CAST(".$source[1]." AS char(7)) AS month,currency,SUM(amount) AS total FROM ".$source[0]." WHERE school_id=$school AND syear=$year$range GROUP BY month,currency ORDER BY month,CASE WHEN currency='USD' THEN 0 ELSE 1 END");
            $byCurrency[$kind]=[];
            foreach($rows as $row){
                $currency=SchoolSafeMoneyCurrency($row['CURRENCY']);$usd=$currency==='USD'?(float)$row['TOTAL']:0;
                $series[$row['MONTH']][$kind]=($series[$row['MONTH']][$kind]??0)+$usd;$totals[$kind]+=$usd;
                $byCurrency[$kind][$currency]=($byCurrency[$kind][$currency]??0)+(float)$row['TOTAL'];
                $monthByCurrency[$kind][$row['MONTH']][$currency]=(float)$row['TOTAL'];
            }
            uksort($byCurrency[$kind],static function($a,$b){return $a==='USD'?-1:($b==='USD'?1:0);});
            if(!$byCurrency[$kind])$byCurrency[$kind]=['USD'=>0];
            continue;
        }
        $rows=DBGet("SELECT CAST(".$source[1]." AS char(7)) AS month,SUM(amount) AS total FROM ".$source[0]." WHERE school_id=$school AND syear=$year$range GROUP BY month ORDER BY month");
        foreach($rows as $row){$series[$row['MONTH']][$kind]=(float)$row['TOTAL'];$totals[$kind]+=(float)$row['TOTAL'];}
    }
    ksort($series);
    $html='<section class="ss-finance-overview"><h3>Finances et opérations</h3><div class="ss-finance-metrics">';
    foreach($sources as $kind=>$source){
        $route=in_array($kind,['fees','payments'],true)?'Student_Billing/DailyTransactions.php':'Accounting/DailyTransactions.php';
        // Lot F4b (05/11) : total par monnaie pour les frais et paiements des élèves.
        $totalText=isset($byCurrency[$kind])?SchoolSafeMoneyBalancesText($byCurrency[$kind]):$money($totals[$kind]);
        $html.='<article data-finance-kind="'.$kind.'"><span>'.$source[2].'</span><strong>'.$escape($totalText).'</strong><small>'.($start!==''?$escape($start.' au '.$end):'Année scolaire sélectionnée').'</small><details class="ss-finance-indicator-detail"><summary>Détail mensuel</summary><table><thead><tr><th>Mois</th><th>Montant</th></tr></thead><tbody>';
        $hasRows=false;
        foreach($series as $month=>$values)if(array_key_exists($kind,$values)){$hasRows=true;$html.='<tr><th scope="row">'.$escape($month).'</th><td>'.$escape(isset($monthByCurrency[$kind][$month])?SchoolSafeMoneyBalancesText($monthByCurrency[$kind][$month]):$money($values[$kind])).'</td></tr>';}
        if(!$hasRows)$html.='<tr><td colspan="2">Aucune transaction enregistrée.</td></tr>';
        $html.='</tbody></table><a href="Modules.php?modname='.$route.'">Consulter les operations</a></details><a class="ss-finance-metric-link" href="Modules.php?modname=misc/Portal.php&amp;dashboard_tab=finance&amp;finance_kind='.$kind.'">Détails</a></article>';
    }
    $html.='</div><div class="ss-finance-analysis"><section><header class="ss-chart-heading"><h3>Encaissements et dépenses</h3><a href="Modules.php?modname=misc/Portal.php&amp;dashboard_tab=finance">Periode</a></header>';
    $cashSeries=[];$labels=[];
    if($billing||$accounting)$labels['income']='Encaissements';
    if($accounting)$labels['expense']='Dépenses';
    foreach($series as $month=>$values){
        // Invoices are not cash receipts. Keep them out of the treasury curve.
        if(isset($values['payments'])||isset($values['income'])||isset($values['expense']))$cashSeries[$month]=['income'=>($values['payments']??0)+($values['income']??0),'expense'=>$values['expense']??0];
    }
    // Lot F4b (05/11) : la courbe additionne paiements et revenus, elle reste donc en USD seulement.
    // Lot F4c (05/11) : revenus et dépenses en FC exclus de la courbe aussi.
    if($accountingMulti&&($billing||$accounting))$html.='<p>Montants en USD seulement : les opérations en FC n’y figurent pas (voir les totaux par monnaie ci-dessus).</p>';
    elseif($multiCurrency&&$billing)$html.='<p>Montants en USD seulement : les paiements des élèves en FC n’y figurent pas.</p>';
    $html.=SchoolSafeDashboardLineChart($cashSeries,$labels).'</section>';
    if($accounting){
        $range=$start!==''?" AND p.payment_date>=DATE '$start' AND p.payment_date<DATE '$end'+INTERVAL '1 day'":'';
        // Lot F4c (05/11) : la répartition additionne les montants, elle porte donc sur les dépenses en USD seulement.
        $usdExpenses=$accountingMulti?" AND p.currency='USD'":'';
        $categories=DBGet("SELECT COALESCE(c.title,'Sans catégorie') AS title,SUM(p.amount) AS total FROM accounting_payments p LEFT JOIN accounting_categories c ON c.id=p.category_id WHERE p.school_id=$school AND p.syear=$year AND p.amount>0$range$usdExpenses GROUP BY c.title ORDER BY total DESC");
        $html.='<section><h3>Répartition des dépenses'.($accountingMulti?' (USD)':'').'</h3>'.SchoolSafeDashboardExpenseChart($categories).'</section>';
    }
    $html.='<section><h3>Volumes enregistrés</h3><div class="ss-native-volume-bars">';
    $maximum=max(1,max(array_map('abs',array_values($totals))));
    // Lot F4b (05/11) : texte par monnaie ; la barre compare les montants USD seulement.
    foreach($sources as $kind=>$source)$html.='<div data-finance-kind="'.$kind.'"><strong>'.$escape($source[2]).'</strong><span>'.$escape(isset($byCurrency[$kind])?SchoolSafeMoneyBalancesText($byCurrency[$kind]):$money($totals[$kind])).'</span><progress max="'.$maximum.'" value="'.abs($totals[$kind]).'" aria-label="'.$escape($source[2]).'"></progress></div>';
    $html.='</div><ul class="sscx-teacher-list">';
    if($billing)$html.='<li><a href="Modules.php?modname=Student_Billing/DailyTransactions.php">Frais et paiements des eleves</a></li>';
    if($accounting)$html.='<li><a href="Modules.php?modname=Accounting/DailyTransactions.php">Revenus et depenses</a></li>';
    $html.='</ul></section></div>';
    if($billing&&AllowUse('Students/Student.php')&&$multiCurrency){
        // Lot F4b (05/11) : solde par élève ET par monnaie ; un élève doit s'il doit dans au moins une monnaie.
        // Classement : reste dû en USD, puis en FC (les deux monnaies ne sont jamais additionnées).
        $balances=DBGet("SELECT s.student_id,s.first_name,s.last_name,
            SUM(CASE WHEN t.currency='USD' THEN t.fees ELSE 0 END) AS usd_fees,SUM(CASE WHEN t.currency='USD' THEN t.paid ELSE 0 END) AS usd_paid,
            SUM(CASE WHEN t.currency='FC' THEN t.fees ELSE 0 END) AS fc_fees,SUM(CASE WHEN t.currency='FC' THEN t.paid ELSE 0 END) AS fc_paid,
            MAX(CASE WHEN t.currency='USD' THEN 1 ELSE 0 END) AS has_usd,MAX(CASE WHEN t.currency='FC' THEN 1 ELSE 0 END) AS has_fc,
            SUM(CASE WHEN t.currency='USD' THEN t.fees-t.paid ELSE 0 END) AS usd_balance,SUM(CASE WHEN t.currency='FC' THEN t.fees-t.paid ELSE 0 END) AS fc_balance
            FROM students s JOIN (SELECT student_id,currency,SUM(fees) AS fees,SUM(paid) AS paid FROM (
                SELECT student_id,currency,amount AS fees,0 AS paid FROM billing_fees WHERE school_id=$school AND syear=$year
                UNION ALL SELECT student_id,currency,0,amount FROM billing_payments WHERE school_id=$school AND syear=$year
            ) m GROUP BY student_id,currency) t ON t.student_id=s.student_id
            GROUP BY s.student_id,s.first_name,s.last_name HAVING MAX(CASE WHEN t.fees>t.paid THEN 1 ELSE 0 END)=1
            ORDER BY usd_balance DESC,fc_balance DESC,s.last_name,s.first_name LIMIT 10");
        $cell=static function($row,$field)use($escape){
            $parts=[];
            foreach(['USD','FC'] as $currency){
                if($row['HAS_'.$currency]||($currency==='USD'&&!$row['HAS_FC']))$parts[]=$escape(SchoolSafeMoneyFormat($row[$currency.'_'.$field],$currency));
            }
            return implode('<br>',$parts);
        };
        $html.='<section class="ss-finance-balances"><header class="ss-chart-heading"><h3>Soldes scolaires a regler</h3><a href="Modules.php?modname=misc/Portal.php&amp;dashboard_tab=finance">Voir tout</a></header><p>Solde annuel, independant du filtre des transactions. Chaque monnaie (USD, FC) a son propre solde.</p><div class="sscx-table-scroll"><table><thead><tr><th>Eleve</th><th>Facture</th><th>Paye</th><th>Solde</th></tr></thead><tbody>';
        foreach($balances as $row)$html.='<tr><th scope="row"><a href="Modules.php?modname=Students/Student.php&amp;student_id='.(int)$row['STUDENT_ID'].'">'.$escape($row['FIRST_NAME'].' '.$row['LAST_NAME']).'</a></th><td>'.$cell($row,'FEES').'</td><td>'.$cell($row,'PAID').'</td><td>'.$cell($row,'BALANCE').'</td></tr>';
        if(!$balances)$html.='<tr><td colspan="4">Aucun solde positif pour cette année.</td></tr>';
        $html.='</tbody></table></div><a href="Modules.php?modname=Student_Billing/DailyTransactions.php">Voir toutes les transactions</a></section>';
    }elseif($billing&&AllowUse('Students/Student.php')){
        // Aggregate fees and payments separately to avoid multiplying amounts through joins.
        $balances=DBGet("SELECT s.student_id,s.first_name,s.last_name,COALESCE(f.total,0) AS fees,COALESCE(p.total,0) AS paid,COALESCE(f.total,0)-COALESCE(p.total,0) AS balance FROM students s
            LEFT JOIN (SELECT student_id,SUM(amount) AS total FROM billing_fees WHERE school_id=$school AND syear=$year GROUP BY student_id) f ON f.student_id=s.student_id
            LEFT JOIN (SELECT student_id,SUM(amount) AS total FROM billing_payments WHERE school_id=$school AND syear=$year GROUP BY student_id) p ON p.student_id=s.student_id
            WHERE COALESCE(f.total,0)>COALESCE(p.total,0) ORDER BY balance DESC,s.last_name,s.first_name LIMIT 10");
        $html.='<section class="ss-finance-balances"><header class="ss-chart-heading"><h3>Soldes scolaires a regler</h3><a href="Modules.php?modname=misc/Portal.php&amp;dashboard_tab=finance">Voir tout</a></header><p>Solde annuel, independant du filtre des transactions.</p><div class="sscx-table-scroll"><table><thead><tr><th>Eleve</th><th>Facture</th><th>Paye</th><th>Solde</th></tr></thead><tbody>';
        foreach($balances as $row)$html.='<tr><th scope="row"><a href="Modules.php?modname=Students/Student.php&amp;student_id='.(int)$row['STUDENT_ID'].'">'.$escape($row['FIRST_NAME'].' '.$row['LAST_NAME']).'</a></th><td>'.$escape($money($row['FEES'])).'</td><td>'.$escape($money($row['PAID'])).'</td><td>'.$escape($money($row['BALANCE'])).'</td></tr>';
        if(!$balances)$html.='<tr><td colspan="4">Aucun solde positif pour cette année.</td></tr>';
        $html.='</tbody></table></div><a href="Modules.php?modname=Student_Billing/DailyTransactions.php">Voir toutes les transactions</a></section>';
    }
    $html.='<form class="ss-finance-period ss-personnel-period" method="get" action="Modules.php"><input type="hidden" name="modname" value="misc/Portal.php"><input type="hidden" name="dashboard_tab" value="finance"><label>Finances du <input type="date" name="finance_start" value="'.$escape($start).'" required></label><label>au <input type="date" name="finance_end" value="'.$escape($end).'" required></label><button type="submit">Filtrer</button><a href="Modules.php?modname=misc/Portal.php&amp;dashboard_tab=finance">Toute l\'annee</a></form>';
    if($periodError)$html.='<p class="ss-finance-period" role="alert">Période invalide. La synthèse annuelle est affichée.</p>';
    if(($_GET['dashboard_tab']??'overview')==='finance'){
        $selected=is_string($_GET['finance_kind']??null)?$_GET['finance_kind']:'';
        if(!isset($sources[$selected]))$selected=array_key_first($sources);
        $html.='<section class="ss-finance-balances"><h3>Opérations par rubrique</h3><nav class="ss-directory-groups" aria-label="Rubriques financieres">';
        foreach($sources as $kind=>$source){
            $params=['modname'=>'misc/Portal.php','dashboard_tab'=>'finance','finance_kind'=>$kind];
            if($start!==''){$params['finance_start']=$start;$params['finance_end']=$end;}
            $html.='<a'.($kind===$selected?' aria-current="true"':'').' href="Modules.php?'.$escape(http_build_query($params)).'">'.$escape($source[2]).'</a>';
        }
        $html.='</nav>';
        $source=$sources[$selected];
        $page=filter_var($_GET['finance_page']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]])?:1;
        $offset=($page-1)*100;
        $range=$start!==''?" AND ".$source[1].">=DATE '$start' AND ".$source[1]."<DATE '$end'+INTERVAL '1 day'":'';
        $label=$selected==='payments'?"'Paiement scolaire'":'title';
        // Lot F4b (05/11) : chaque frais ou paiement d'élève est affiché dans sa monnaie.
        // Lot F4c (05/11) : revenus et dépenses aussi, avec le script F4c.
        $withCurrency=$multiCurrency&&in_array($selected,$currencyKinds,true);
        $operations=DBGet("SELECT id,".$source[1]." AS operation_date,$label AS title,amount".($withCurrency?',currency':'')." FROM ".$source[0]." WHERE school_id=$school AND syear=$year$range ORDER BY ".$source[1]." DESC,id DESC LIMIT 101 OFFSET $offset");
        $more=count($operations)>100;
        $html.='<div class="ss-directory-scroll"><table><thead><tr><th>Référence</th><th>Date</th><th>Opération</th><th>Montant</th></tr></thead><tbody>';
        foreach(array_slice(array_values($operations),0,100) as $operation)$html.='<tr><th scope="row">'.(int)$operation['ID'].'</th><td>'.$escape($operation['OPERATION_DATE']).'</td><td>'.$escape(ParseMLField($operation['TITLE'])).'</td><td>'.$escape($withCurrency?SchoolSafeMoneyFormat($operation['AMOUNT'],$operation['CURRENCY']):$money($operation['AMOUNT'])).'</td></tr>';
        if(!$operations)$html.='<tr><td colspan="4">Aucune opération pour cette période.</td></tr>';
        $html.='</tbody></table></div><nav class="ss-directory-pages" aria-label="Pages des operations"><span>Page '.$page.'</span>';
        foreach(['Precedente'=>$page-1,'Suivante'=>$page+1] as $label=>$target){
            if(($target<$page&&$page>1)||($target>$page&&$more)){
                $params=['modname'=>'misc/Portal.php','dashboard_tab'=>'finance','finance_kind'=>$selected,'finance_page'=>$target];
                if($start!==''){$params['finance_start']=$start;$params['finance_end']=$end;}
                $html.='<a href="Modules.php?'.$escape(http_build_query($params)).'">'.$label.'</a>';
            }
        }
        $html.='</nav></section>';
    }
    require_once __DIR__.'/SchoolSafeBudgetPayroll.fnc.php';
    return $html.SchoolSafeBudgetPayrollSummary($start,$end).'</section>';
}
