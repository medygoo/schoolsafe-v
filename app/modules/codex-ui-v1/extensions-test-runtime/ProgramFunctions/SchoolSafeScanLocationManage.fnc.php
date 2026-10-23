<?php
// Adapter to the existing native checkpoints, not another location registry.
require_once __DIR__.'/SchoolSafeScanPassage.fnc.php';

// Context/permission flags are calculated by the native gateway from the SESSION,
// never copied from JSON. quote uses the native DBEscapeString adapter (PDO in QA).
function SchoolSafeScanLocationManage(array $input, array $ctx, array $db): array
{
    $ctx=SchoolSafePickupManageContext($ctx);
    $action=$input['action']??'';
    if (($ctx['profile']??'')!=='admin'||($ctx['can_configure']??false)!==true
        ||($action==='list'?($ctx['can_read']??false):($ctx['can_save']??false))!==true) {
        throw new RuntimeException('FORBIDDEN');
    }
    if ($action==='list') {
        if (array_diff(array_keys($input),['action'])) throw new RuntimeException('INVALID_INPUT');
        SchoolSafeScanPassageRequireScope($db);
        $rows=$db['get']('SELECT ID,TITLE,TYPE,SCHOOLSAFE_SCAN_ENABLED FROM entry_exit_checkpoints'
            .' WHERE SCHOOLSAFE_SCAN_SCHOOL_ID='.$ctx['school'].' AND SCHOOLSAFE_SCAN_SYEAR='.$ctx['year']
            .' ORDER BY SORT_ORDER NULLS LAST,TITLE,ID');
        if (!is_array($rows)) throw new RuntimeException('SCAN_LOCATIONS_UNAVAILABLE');
        $locations=[];
        foreach ($rows as $row) {
            if (!isset($row['ID'],$row['TITLE'])||!array_key_exists('TYPE',$row)
                ||!in_array($row['TYPE'],[null,'','in','out'],true)) throw new RuntimeException('SCAN_LOCATIONS_UNAVAILABLE');
            $locations[]=['id'=>(string)$row['ID'],'label'=>$row['TITLE'],
                'direction'=>in_array($row['TYPE'],['in','out'],true)?$row['TYPE']:'both',
                'is_active'=>in_array($row['SCHOOLSAFE_SCAN_ENABLED']??false,[true,'t','1',1],true)];
        }
        return ['locations'=>$locations];
    }
    if ($action!=='save'||array_diff(array_keys($input),['action','id','label','direction','is_active'])
        ||!is_string($input['label']??null)||!is_bool($input['is_active']??null)
        ||!in_array($input['direction']??null,['both','in','out'],true)) throw new RuntimeException('INVALID_INPUT');
    $label=trim($input['label']);
    if (strpos($label,"\0")!==false||preg_match('/^.{1,100}$/usD',$label)!==1) throw new RuntimeException('INVALID_INPUT');
    $id=null;
    if (array_key_exists('id',$input)) {
        if (!is_string($input['id'])||!preg_match('/^[1-9][0-9]*$/D',$input['id'])
            ||filter_var($input['id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]])===false) {
            throw new RuntimeException('INVALID_INPUT');
        }
        $id=(int)$input['id'];
    }
    SchoolSafeScanPassageRequireScope($db);
    $title=$db['quote']($label);
    $type=$input['direction']==='both'?'NULL':"'".$input['direction']."'";
    $enabled=$input['is_active']?'true':'false';
    if ($id===null) {
        $sql='INSERT INTO entry_exit_checkpoints(TITLE,TYPE,SCHOOLSAFE_SCAN_SCHOOL_ID,SCHOOLSAFE_SCAN_SYEAR,SCHOOLSAFE_SCAN_ENABLED)'
            .' VALUES('.$title.','.$type.','.$ctx['school'].','.$ctx['year'].','.$enabled.') RETURNING ID';
    } else {
        // One atomic scoped UPDATE. Preserve native hours, sort order and history.
        $sql='UPDATE entry_exit_checkpoints SET TITLE='.$title.',TYPE='.$type.',SCHOOLSAFE_SCAN_ENABLED='.$enabled
            .',UPDATED_AT=CURRENT_TIMESTAMP WHERE ID='.$id.' AND SCHOOLSAFE_SCAN_SCHOOL_ID='.$ctx['school']
            .' AND SCHOOLSAFE_SCAN_SYEAR='.$ctx['year'].' RETURNING ID';
    }
    $rows=$db['get']($sql);
    if (!is_array($rows)) throw new RuntimeException('WRITE_FAILED');
    if (!isset($rows[1]['ID'])) throw new RuntimeException($id===null?'WRITE_FAILED':'NOT_FOUND');
    return ['id'=>(string)$rows[1]['ID']];
}
