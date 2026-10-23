<?php
// Native school/year ProgramConfig owner; no billing writes or parallel ledger.
require_once __DIR__.'/SchoolSafeFeeScan.fnc.php';

function SchoolSafeFeePolicyScope(array $ctx): array
{
    $school=(int)($ctx['school']??0);$year=(int)($ctx['year']??0);
    if($school<1||$year<1)throw new RuntimeException('INVALID_CONTEXT');
    return [$school,$year];
}

function SchoolSafeFeePolicyValidModel($model): bool
{
    return is_array($model)&&is_string($model['id']??null)
        &&preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/D',$model['id'])===1
        &&is_string($model['label']??null)&&trim($model['label'])!==''&&preg_match('/^.{1,100}$/usD',$model['label'])===1
        &&is_string($model['threshold']??null)&&preg_match('/^[0-9]{1,12}(?:\.[0-9]{1,2})?$/D',$model['threshold'])===1
        &&SchoolSafeFeeCents($model['threshold'])>0&&is_int($model['tolerance']??null)
        &&$model['tolerance']>=0&&$model['tolerance']<=100&&is_bool($model['active']??null);
}

function SchoolSafeFeePolicyWhere(int $school,int $year): string
{
    return "PROGRAM='schoolsafe_scan' AND TITLE='FEE_MODELS' AND SCHOOL_ID=".$school.' AND SYEAR='.$year;
}

function SchoolSafeFeePolicyState(array $ctx,array $db,bool $lock=false): ?array
{
    [$school,$year]=SchoolSafeFeePolicyScope($ctx);
    if($lock){
        // Also protects the absent-row case against concurrent first activation.
        $scope=$db['get']('SELECT ID FROM schools WHERE ID='.$school.' AND SYEAR='.$year.' FOR SHARE');
        if(!is_array($scope)||!isset($scope[1]))throw new RuntimeException('FEE_POLICY_UNAVAILABLE');
    }
    $rows=$db['get']('SELECT VALUE FROM program_config WHERE '.SchoolSafeFeePolicyWhere($school,$year).($lock?' FOR SHARE':''));
    if(!is_array($rows)||count($rows)>1)throw new RuntimeException('FEE_POLICY_UNAVAILABLE');
    if(!$rows)return null; // Known absence of an active control is not a blue financial result.
    $value=$rows[1]['VALUE']??null;
    if(!is_string($value)||strlen($value)>1048576)throw new RuntimeException('FEE_POLICY_UNAVAILABLE');
    $state=json_decode($value,true,32);
    if(!is_array($state)||($state['version']??null)!==1||($state['school_id']??null)!==$school
        ||($state['syear']??null)!==$year||!is_array($state['models']??null)||!array_is_list($state['models']))throw new RuntimeException('FEE_POLICY_UNAVAILABLE');
    $ids=[];$active=0;
    foreach($state['models'] as $model){
        if(!SchoolSafeFeePolicyValidModel($model)||isset($ids[$model['id']]))throw new RuntimeException('FEE_POLICY_UNAVAILABLE');
        $ids[$model['id']]=true;if($model['active'])$active++;
    }
    if($active>1)throw new RuntimeException('FEE_POLICY_UNAVAILABLE');
    return $state;
}

function SchoolSafeFeePolicyActive(array $ctx,array $db,bool $lock=false): ?array
{
    $state=SchoolSafeFeePolicyState($ctx,$db,$lock);
    foreach($state['models']??[] as $model)if($model['active'])return $model;
    return null;
}

// Same exact bounds for the decision and its immutable financial observation.
function SchoolSafeFeePolicyBounds(array $model,array $ledger): array
{
    if(!SchoolSafeFeePolicyValidModel($model))throw new RuntimeException('FEE_POLICY_UNAVAILABLE');
    if(($ledger['has_data']??false)!==true||!is_int($ledger['fee_cents']??null))throw new RuntimeException('FEE_READ_UNAVAILABLE');
    $minimum=min(SchoolSafeFeeCents($model['threshold']),max(0,$ledger['fee_cents']));
    // Ceiling to the first payable cent; never round a refusal into tolerance.
    return ['minimum'=>$minimum,'tolerance_floor'=>intdiv($minimum*(100-$model['tolerance'])+99,100)];
}

function SchoolSafeFeeClassify(array $model,array $ledger): array
{
    if(!SchoolSafeFeePolicyValidModel($model))throw new RuntimeException('FEE_POLICY_UNAVAILABLE');
    if(($ledger['has_data']??false)!==true)return ['state'=>'unavailable','color'=>null,'entry_allowed'=>false,'reason'=>'no_data','policy_id'=>$model['id']];
    foreach(['fee_cents','paid_cents','balance_cents'] as $key)if(!is_int($ledger[$key]??null))throw new RuntimeException('FEE_READ_UNAVAILABLE');
    if(SchoolSafeFeeAddCents($ledger['balance_cents'],$ledger['paid_cents'])!==$ledger['fee_cents'])throw new RuntimeException('FEE_READ_UNAVAILABLE');
    // Owner-approved: do not demand a minimum beyond the actual signed net fee.
    $bounds=SchoolSafeFeePolicyBounds($model,$ledger);$minimum=$bounds['minimum'];
    $paid=$ledger['paid_cents'];$color='red';$reason='below_tolerance';
    if($paid>=$minimum||$ledger['balance_cents']<=0){$color='blue';$reason=$minimum===0?'net_obligation_zero':'threshold_met';}
    elseif($paid>0&&$paid>=$bounds['tolerance_floor']){$color='yellow';$reason='within_tolerance';}
    return ['state'=>'active','color'=>$color,'entry_allowed'=>$color!=='red','reason'=>$reason,'policy_id'=>$model['id']];
}

function SchoolSafeFeePolicyManage(array $input,array $ctx,array $db): array
{
    [$school,$year]=SchoolSafeFeePolicyScope($ctx);
    $action=$input['action']??null;
    $keys=['list'=>['action'],'save'=>['action','label','threshold','tolerance'],'activate'=>['action','id'],'stop'=>['action']];
    if(!is_string($action)||!isset($keys[$action])||array_diff(array_keys($input),$keys[$action]))throw new RuntimeException('INVALID_INPUT');
    if(($ctx['profile']??'')!=='admin'||($ctx['can_edit_billing']??false)!==true
        ||($ctx[$action==='list'?'can_read':'can_manage']??false)!==true)throw new RuntimeException('FORBIDDEN');
    if($action==='list')return SchoolSafeFeePolicyState($ctx,$db)['models']??[];
    if((int)($ctx['user']??0)<1)throw new RuntimeException('FORBIDDEN');
    $db['exec']('BEGIN');
    try{
        // Serializes create/activate/stop even before a config row exists.
        $scope=$db['get']('SELECT ID FROM schools WHERE ID='.$school.' AND SYEAR='.$year.' FOR UPDATE');
        if(!is_array($scope)||!isset($scope[1]))throw new RuntimeException('INVALID_CONTEXT');
        $state=SchoolSafeFeePolicyState($ctx,$db);
        $exists=$state!==null;
        $state=$state??['version'=>1,'school_id'=>$school,'syear'=>$year,'models'=>[]];
        if($action==='save'){
            $hex=bin2hex(random_bytes(16));$id=substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
            $model=['id'=>$id,'label'=>is_string($input['label']??null)?trim($input['label']):null,
                'threshold'=>$input['threshold']??null,'tolerance'=>$input['tolerance']??null,'active'=>false,
                'created_by'=>(int)$ctx['user'],'created_at'=>gmdate('c')];
            if(!SchoolSafeFeePolicyValidModel($model))throw new RuntimeException('INVALID_INPUT');
            $model['threshold']=SchoolSafeFeeFormatCents(SchoolSafeFeeCents($model['threshold']));
            $state['models'][]=$model; // Saving never activates a financial barrier implicitly.
        }else{
            $found=$action==='stop';
            foreach($state['models'] as &$model){
                $model['active']=$action==='activate'&&is_string($input['id']??null)&&$model['id']===$input['id'];
                if($model['active'])$found=true;
            }unset($model);
            if(!$found)throw new RuntimeException('FORBIDDEN');
        }
        $json=json_encode($state,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        if(strlen($json)>1048576)throw new RuntimeException('INVALID_INPUT');
        $quoted=$db['quote']($json);$where=SchoolSafeFeePolicyWhere($school,$year);
        $write=$exists?'UPDATE program_config SET VALUE='.$quoted.',UPDATED_AT=CURRENT_TIMESTAMP WHERE '.$where.' RETURNING TITLE'
            :"INSERT INTO program_config(SYEAR,SCHOOL_ID,PROGRAM,TITLE,VALUE) VALUES(".$year.','.$school.",'schoolsafe_scan','FEE_MODELS',".$quoted.') RETURNING TITLE';
        $written=$db['get']($write);
        if(!is_array($written)||count($written)!==1)throw new RuntimeException('WRITE_FAILED');
        $db['exec']('COMMIT');
        return ($ctx['can_read']??false)===true?$state['models']:[];
    }catch(Throwable $error){try{$db['exec']('ROLLBACK');}catch(Throwable $ignored){}throw $error;}
}
