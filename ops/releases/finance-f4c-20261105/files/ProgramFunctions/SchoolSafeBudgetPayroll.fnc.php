<?php
// Lot F4c (05/11) : montants de paie et de trésorerie par monnaie.
require_once __DIR__ . '/SchoolSafeMoney.fnc.php';
function SchoolSafeBudgetPage(): void
{
    if(User('PROFILE')!=='admin'||!AllowUse('Accounting/Categories.php')){
        echo '<p role="alert">Accès non autorisé.</p>';return;
    }
    $school=(int)UserSchool();$year=(int)UserSyear();$staff=(int)User('STAFF_ID');
    if($school<1||$year<1)return;
    $e=static function($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');};
    DrawHeader('Budget annuel');
    echo '<link rel="stylesheet" href="assets/css/schoolsafe-dashboard-reference.css?v=reference-12">';
    echo '<p>Montants prévus par catégorie pour l\'école et l\'année sélectionnées. Enregistrer un budget ne cree aucune dépense ni aucun paiement.</p>';
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        $token=$_POST['token']??null;$amount=$_POST['budget_amount']??null;
        $category=filter_var($_POST['budget_category']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(!AllowEdit('Accounting/Categories.php')||!is_string($token)||empty($_SESSION['token'])||!hash_equals($_SESSION['token'],$token)){
            echo '<p role="alert">Enregistrement refuse : droit ou session invalide.</p>';
        }elseif(!$category||!is_string($amount)||!preg_match('/^\d{1,12}(\.\d{1,2})?$/D',$amount)){
            echo '<p role="alert">Choisissez une catégorie et un montant positif ou nul avec deux décimales au maximum.</p>';
        }else{
            $valid=DBGet("SELECT id FROM accounting_categories WHERE id=".(int)$category." AND school_id=$school AND type IN ('expenses','common')");
            if(!$valid)echo '<p role="alert">Catégorie non accessible dans cette école.</p>';
            else{
                DBQuery("INSERT INTO schoolsafe_accounting_budgets (school_id,syear,category_id,amount,updated_by)
                    VALUES ($school,$year,".(int)$category.",".DBEscapeString($amount).",$staff)
                    ON CONFLICT (school_id,syear,category_id) DO UPDATE SET amount=EXCLUDED.amount,updated_by=EXCLUDED.updated_by,updated_at=CURRENT_TIMESTAMP");
                echo '<p role="status">Budget enregistre.</p>';
            }
        }
    }
    if(AllowEdit('Accounting/Categories.php')){
        $categories=DBGet("SELECT id,title FROM accounting_categories WHERE school_id=$school AND type IN ('expenses','common') ORDER BY sort_order NULLS LAST,title");
        echo '<form method="post" action="Modules.php?modname=Accounting/Categories.php&amp;tab=budget&amp;modfunc=budget_save" class="ss-personnel-period"><input type="hidden" name="token" value="'.$e($_SESSION['token']).'"><label>Catégorie <select name="budget_category" required><option value="">Choisir</option>';
        foreach($categories as $category)echo '<option value="'.(int)$category['ID'].'">'.$e(ParseMLField($category['TITLE'])).'</option>';
        echo '</select></label><label>Budget annuel <input type="number" name="budget_amount" min="0" max="999999999999.99" step="0.01" required></label><button type="submit">Enregistrer</button></form>';
        if(!$categories)echo '<p>Ajoutez d\'abord une catégorie de dépenses dans les catégories comptables.</p>';
    }
    echo SchoolSafeBudgetPayrollSummary();
    echo '<a href="Modules.php?modname=Accounting/Categories.php">Categories comptables</a>';
}

function SchoolSafeBudgetPayrollSummary(string $start='',string $end=''): string
{
    if(User('PROFILE')!=='admin')return '';
    $school=(int)UserSchool();$year=(int)UserSyear();if($school<1||$year<1)return '';
    $e=static function($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');};
    $money=static function($v)use($e){return $e(html_entity_decode(strip_tags(Currency($v)),ENT_QUOTES,'UTF-8'));};
    $html='';
    if(AllowUse('Accounting/Categories.php')&&AllowUse('Accounting/DailyTransactions.php')){
        $rows=DBGet("SELECT c.id,c.title,b.amount AS planned,COALESCE(p.spent,0) AS spent FROM accounting_categories c
            LEFT JOIN schoolsafe_accounting_budgets b ON b.category_id=c.id AND b.school_id=$school AND b.syear=$year
            LEFT JOIN (SELECT category_id,SUM(amount) AS spent FROM accounting_payments WHERE school_id=$school AND syear=$year".(function_exists('SchoolSafeMoneyAccountingReady')&&SchoolSafeMoneyAccountingReady()?" AND currency='USD'":'')." GROUP BY category_id) p ON p.category_id=c.id
            WHERE c.school_id=$school AND c.type IN ('expenses','common') ORDER BY c.sort_order NULLS LAST,c.title");
        $html.='<section class="ss-budget-summary"><header class="ss-chart-heading"><h3>Budget annuel</h3><a href="Modules.php?modname=Accounting/Categories.php&amp;tab=budget">Configurer</a></header><p>Budget et dépenses cumulés de l\'année scolaire sélectionnée'.(function_exists('SchoolSafeMoneyAccountingReady')&&SchoolSafeMoneyAccountingReady()?' (en USD ; les dépenses en FC ne sont pas comptées dans ce budget)':'').'.</p><div class="ss-directory-scroll"><table><thead><tr><th>Categorie</th><th>Prevu</th><th>Depense</th><th>Disponible</th><th>Consommation</th></tr></thead><tbody>';
        foreach($rows as $row){
            $planned=$row['PLANNED'];$spent=(float)$row['SPENT'];
            $html.='<tr><th scope="row">'.$e(ParseMLField($row['TITLE'])).'</th><td>'.($planned===null?'Non defini':$money($planned)).'</td><td>'.$money($spent).'</td><td>'.($planned===null?'Non defini':$money((float)$planned-$spent)).'</td><td>';
            if($planned!==null&&(float)$planned>0)$html.=$e(round($spent/(float)$planned*100,1)).'% <progress max="'.(float)$planned.'" value="'.min((float)$planned,max(0,$spent)).'" aria-label="Consommation du budget"></progress>';
            elseif($planned!==null&&$spent>0)$html.='Depassement';
            else $html.='Non calculable';
            $html.='</td></tr>';
        }
        if(!$rows)$html.='<tr><td colspan="5">Aucune catégorie de dépenses configurée.</td></tr>';
        $html.='</tbody></table></div><p>Les dépenses sans catégorie ne sont pas réparties dans ce budget.</p></section>';
    }
    $salary=AllowUse('Accounting/Salaries.php');$payment=AllowUse('Accounting/StaffPayments.php');
    if($salary||$payment){
        $html.='<section class="ss-payroll-summary"><h3>Paie du personnel</h3><p>'.($start!==''?$e($start.' au '.$end):'Année scolaire sélectionnée').'</p><dl>';
        foreach([['accounting_salaries','assigned_date','Salaires enregistrés','Accounting/Salaries.php',$salary],['accounting_payments','payment_date','Paiements du personnel','Accounting/StaffPayments.php',$payment]] as $source){
            if(!$source[4])continue;
            $range=$start!==''&&preg_match('/^\d{4}-\d{2}-\d{2}$/D',$start)&&preg_match('/^\d{4}-\d{2}-\d{2}$/D',$end)?" AND ".$source[1].">=DATE '$start' AND ".$source[1]."<DATE '$end'+INTERVAL '1 day'":'';
            $staffFilter=$source[0]==='accounting_payments'?' AND staff_id IS NOT NULL':'';
            // Lot F4c (05/11) : total par monnaie (USD, FC), jamais additionné.
            if(function_exists('SchoolSafeMoneyAccountingReady')&&SchoolSafeMoneyAccountingReady()){
                $parts=[];
                foreach(DBGet("SELECT currency,COALESCE(SUM(amount),0) AS total FROM ".$source[0]." WHERE school_id=$school AND syear=$year$staffFilter$range GROUP BY currency ORDER BY CASE WHEN currency='USD' THEN 0 ELSE 1 END") as $r)$parts[]=$e(SchoolSafeMoneyFormat($r['TOTAL'],$r['CURRENCY']));
                $html.='<dt><a href="Modules.php?modname='.$source[3].'">'.$source[2].'</a></dt><dd>'.($parts?implode('<br>',$parts):$e(SchoolSafeMoneyFormat(0,'USD'))).'</dd>';
                continue;
            }
            $total=DBGetOne("SELECT COALESCE(SUM(amount),0) FROM ".$source[0]." WHERE school_id=$school AND syear=$year$staffFilter$range");
            $html.='<dt><a href="Modules.php?modname='.$source[3].'">'.$source[2].'</a></dt><dd>'.$money($total).'</dd>';
        }
        if(AllowUse('Accounting/StaffBalances.php'))$html.='<dt><a href="Modules.php?modname=Accounting/StaffBalances.php">Soldes du personnel</a></dt><dd>Consulter</dd>';
        $html.='</dl></section>';
    }
    return $html?'<div class="ss-budget-payroll">'.$html.'</div>':'';
}
