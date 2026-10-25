<?php
function SchoolSafeDashboardTabs(): array
{
    $tabs=['overview'=>'Vue d\'ensemble'];
    if(User('PROFILE')==='admin'){
        if(AllowUse('Students/Student.php'))$tabs['students']='Élèves';
        if(AllowUse('Users/User.php'))$tabs['personnel']='Personnel';
        if(AllowUse('Users/User.php')&&AllowUse('Students/Student.php'))$tabs['parents']='Parents';
        if(AllowUse('Student_Billing/DailyTransactions.php')||AllowUse('Accounting/DailyTransactions.php'))$tabs['finance']='Finances';
    }else{
        $tabs['students']=User('PROFILE')==='parent'?'Mes enfants':'Mon suivi';
        if(in_array(User('PROFILE'),['parent','student'],true)&&(AllowUse('Student_Billing/StudentFees.php')||AllowUse('Student_Billing/StudentPayments.php')))$tabs['finance']='Frais scolaires';
    }
    if(AllowUse('School_Setup/Calendar.php'))$tabs['activities']='Activités et calendrier';
    $tabs['modules']='Synthèses Rosario';
    $requested=$_GET['dashboard_tab']??'overview';
    $active=is_string($requested)&&isset($tabs[$requested])?$requested:'overview';
    $e=static function($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');};
    $html='<nav class="ss-dashboard-tabs" aria-label="Rubriques du tableau de bord">';
    foreach($tabs as $key=>$label){
        $query=['modname'=>'misc/Portal.php','dashboard_tab'=>$key];
        foreach(['finance_start','finance_end','personnel_start','personnel_end'] as $filter)if(isset($_GET[$filter])&&is_string($_GET[$filter]))$query[$filter]=$_GET[$filter];
        $html.='<a href="Modules.php?'.$e(http_build_query($query)).'"'.($active===$key?' aria-current="page"':'').'>'.$e($label).'</a>';
    }
    return [$active,$html.'</nav>'];
}
