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
    if($billing){$sources['fees']=['billing_fees','assigned_date','Frais factures'];$sources['payments']=['billing_payments','payment_date','Frais encaisses'];}
    if($accounting){$sources['income']=['accounting_incomes','assigned_date','Autres revenus'];$sources['expense']=['accounting_payments','payment_date','Depenses et personnel'];}
    foreach($sources as $kind=>$source){
        $range=$start!==''?" AND ".$source[1].">=DATE '$start' AND ".$source[1]."<DATE '$end'+INTERVAL '1 day'":'';
        $rows=DBGet("SELECT CAST(".$source[1]." AS char(7)) AS month,SUM(amount) AS total FROM ".$source[0]." WHERE school_id=$school AND syear=$year$range GROUP BY month ORDER BY month");
        $totals[$kind]=0;
        foreach($rows as $row){$series[$row['MONTH']][$kind]=(float)$row['TOTAL'];$totals[$kind]+=(float)$row['TOTAL'];}
    }
    ksort($series);
    $html='<section class="ss-finance-overview"><h3>Finances et operations</h3><div class="ss-finance-metrics">';
    foreach($sources as $kind=>$source){
        $route=in_array($kind,['fees','payments'],true)?'Student_Billing/DailyTransactions.php':'Accounting/DailyTransactions.php';
        $html.='<article data-finance-kind="'.$kind.'"><span>'.$source[2].'</span><strong>'.$escape($money($totals[$kind])).'</strong><small>'.($start!==''?$escape($start.' au '.$end):'Année scolaire selectionnee').'</small><details class="ss-finance-indicator-detail"><summary>Detail mensuel</summary><table><thead><tr><th>Mois</th><th>Montant</th></tr></thead><tbody>';
        $hasRows=false;
        foreach($series as $month=>$values)if(array_key_exists($kind,$values)){$hasRows=true;$html.='<tr><th scope="row">'.$escape($month).'</th><td>'.$escape($money($values[$kind])).'</td></tr>';}
        if(!$hasRows)$html.='<tr><td colspan="2">Aucune transaction enregistree.</td></tr>';
        $html.='</tbody></table><a href="Modules.php?modname='.$route.'">Consulter les operations</a></details><a class="ss-finance-metric-link" href="Modules.php?modname=misc/Portal.php&amp;dashboard_tab=finance&amp;finance_kind='.$kind.'">Details</a></article>';
    }
    $html.='</div><div class="ss-finance-analysis"><section><header class="ss-chart-heading"><h3>Encaissements et dépenses</h3><a href="Modules.php?modname=misc/Portal.php&amp;dashboard_tab=finance">Periode</a></header>';
    $cashSeries=[];$labels=[];
    if($billing||$accounting)$labels['income']='Encaissements';
    if($accounting)$labels['expense']='Depenses';
    foreach($series as $month=>$values){
        // Invoices are not cash receipts. Keep them out of the treasury curve.
        if(isset($values['payments'])||isset($values['income'])||isset($values['expense']))$cashSeries[$month]=['income'=>($values['payments']??0)+($values['income']??0),'expense'=>$values['expense']??0];
    }
    $html.=SchoolSafeDashboardLineChart($cashSeries,$labels).'</section>';
    if($accounting){
        $range=$start!==''?" AND p.payment_date>=DATE '$start' AND p.payment_date<DATE '$end'+INTERVAL '1 day'":'';
        $categories=DBGet("SELECT COALESCE(c.title,'Sans categorie') AS title,SUM(p.amount) AS total FROM accounting_payments p LEFT JOIN accounting_categories c ON c.id=p.category_id WHERE p.school_id=$school AND p.syear=$year AND p.amount>0$range GROUP BY c.title ORDER BY total DESC");
        $html.='<section><h3>Repartition des depenses</h3>'.SchoolSafeDashboardExpenseChart($categories).'</section>';
    }
    $html.='<section><h3>Volumes enregistres</h3><div class="ss-native-volume-bars">';
    $maximum=max(1,max(array_map('abs',array_values($totals))));
    foreach($sources as $kind=>$source)$html.='<div data-finance-kind="'.$kind.'"><strong>'.$escape($source[2]).'</strong><span>'.$escape($money($totals[$kind])).'</span><progress max="'.$maximum.'" value="'.abs($totals[$kind]).'" aria-label="'.$escape($source[2]).'"></progress></div>';
    $html.='</div><ul class="sscx-teacher-list">';
    if($billing)$html.='<li><a href="Modules.php?modname=Student_Billing/DailyTransactions.php">Frais et paiements des eleves</a></li>';
    if($accounting)$html.='<li><a href="Modules.php?modname=Accounting/DailyTransactions.php">Revenus et depenses</a></li>';
    $html.='</ul></section></div>';
    if($billing&&AllowUse('Students/Student.php')){
        // Aggregate fees and payments separately to avoid multiplying amounts through joins.
        $balances=DBGet("SELECT s.student_id,s.first_name,s.last_name,COALESCE(f.total,0) AS fees,COALESCE(p.total,0) AS paid,COALESCE(f.total,0)-COALESCE(p.total,0) AS balance FROM students s
            LEFT JOIN (SELECT student_id,SUM(amount) AS total FROM billing_fees WHERE school_id=$school AND syear=$year GROUP BY student_id) f ON f.student_id=s.student_id
            LEFT JOIN (SELECT student_id,SUM(amount) AS total FROM billing_payments WHERE school_id=$school AND syear=$year GROUP BY student_id) p ON p.student_id=s.student_id
            WHERE COALESCE(f.total,0)>COALESCE(p.total,0) ORDER BY balance DESC,s.last_name,s.first_name LIMIT 10");
        $html.='<section class="ss-finance-balances"><header class="ss-chart-heading"><h3>Soldes scolaires a regler</h3><a href="Modules.php?modname=misc/Portal.php&amp;dashboard_tab=finance">Voir tout</a></header><p>Solde annuel, independant du filtre des transactions.</p><div class="sscx-table-scroll"><table><thead><tr><th>Eleve</th><th>Facture</th><th>Paye</th><th>Solde</th></tr></thead><tbody>';
        foreach($balances as $row)$html.='<tr><th scope="row"><a href="Modules.php?modname=Students/Student.php&amp;student_id='.(int)$row['STUDENT_ID'].'">'.$escape($row['FIRST_NAME'].' '.$row['LAST_NAME']).'</a></th><td>'.$escape($money($row['FEES'])).'</td><td>'.$escape($money($row['PAID'])).'</td><td>'.$escape($money($row['BALANCE'])).'</td></tr>';
        if(!$balances)$html.='<tr><td colspan="4">Aucun solde positif pour cette annee.</td></tr>';
        $html.='</tbody></table></div><a href="Modules.php?modname=Student_Billing/DailyTransactions.php">Voir toutes les transactions</a></section>';
    }
    $html.='<form class="ss-finance-period ss-personnel-period" method="get" action="Modules.php"><input type="hidden" name="modname" value="misc/Portal.php"><input type="hidden" name="dashboard_tab" value="finance"><label>Finances du <input type="date" name="finance_start" value="'.$escape($start).'" required></label><label>au <input type="date" name="finance_end" value="'.$escape($end).'" required></label><button type="submit">Filtrer</button><a href="Modules.php?modname=misc/Portal.php&amp;dashboard_tab=finance">Toute l\'annee</a></form>';
    if($periodError)$html.='<p class="ss-finance-period" role="alert">Periode invalide. La synthese annuelle est affichee.</p>';
    if(($_GET['dashboard_tab']??'overview')==='finance'){
        $selected=is_string($_GET['finance_kind']??null)?$_GET['finance_kind']:'';
        if(!isset($sources[$selected]))$selected=array_key_first($sources);
        $html.='<section class="ss-finance-balances"><h3>Operations par rubrique</h3><nav class="ss-directory-groups" aria-label="Rubriques financieres">';
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
        $operations=DBGet("SELECT id,".$source[1]." AS operation_date,$label AS title,amount FROM ".$source[0]." WHERE school_id=$school AND syear=$year$range ORDER BY ".$source[1]." DESC,id DESC LIMIT 101 OFFSET $offset");
        $more=count($operations)>100;
        $html.='<div class="ss-directory-scroll"><table><thead><tr><th>Reference</th><th>Date</th><th>Operation</th><th>Montant</th></tr></thead><tbody>';
        foreach(array_slice(array_values($operations),0,100) as $operation)$html.='<tr><th scope="row">'.(int)$operation['ID'].'</th><td>'.$escape($operation['OPERATION_DATE']).'</td><td>'.$escape(ParseMLField($operation['TITLE'])).'</td><td>'.$escape($money($operation['AMOUNT'])).'</td></tr>';
        if(!$operations)$html.='<tr><td colspan="4">Aucune operation pour cette periode.</td></tr>';
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
