<?php
/** Read native profile flags and hand off edits to their existing school owner.
 * No permission write, profile conversion, account creation or bridge fallback.
 */
function SchoolSafeScanDelegation(array $input, array $ctx, array $db): array
{
    $action=$input['action']??null;
    if (array_keys($input)!==['action'] || !in_array($action,['grants','delegate'],true)) {
        throw new RuntimeException('INVALID_INPUT');
    }
    $school=filter_var($ctx['school']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if ($school===false || ($ctx['profile']??null)!=='admin'
        || empty($ctx[$action==='grants'?'can_read':'can_manage'])) {
        throw new RuntimeException('FORBIDDEN');
    }
    $result=['status'=>'native_editor_required','scope'=>'school_profiles',
        'editor_url'=>'/ecoles/'.$school.'/profils'];
    if ($action==='delegate') return $result;
    $mapping=['SchoolSafe/ScanArrival.php'=>'SCAN_ENTRY','SchoolSafe/ScanPickup.php'=>'SCAN_PICKUP',
        'SchoolSafe/ScanFees.php'=>'SCAN_FEES','SchoolSafe/ScanCanteen.php'=>'SCAN_CANTEEN',
        'SchoolSafe/ScanVerify.php'=>'SCAN_VERIFY'];
    // The school_id column belongs to existing migration034. Its absence is an
    // unavailable reader, not permission to list global/foreign profiles.
    $keys="'".implode("','",array_keys($mapping))."'";
    $rows=$db['get']("SELECT p.ID,p.TITLE,p.PROFILE,e.MODNAME,e.CAN_USE,e.CAN_EDIT
        FROM user_profiles p JOIN profile_exceptions e ON e.PROFILE_ID=p.ID
        WHERE p.SCHOOL_ID=".$school." AND p.PROFILE IN ('admin','teacher')
        AND e.MODNAME IN (".$keys.") AND e.CAN_USE='Y'
        ORDER BY p.ID,e.MODNAME");
    if (!is_array($rows)) throw new RuntimeException('DELEGATION_UNAVAILABLE');
    $result['profiles']=[];
    foreach ($rows as $row) {
        if (!isset($row['ID'],$row['TITLE'],$row['PROFILE'],$row['MODNAME'],$row['CAN_USE'])
            || !preg_match('/^[1-9][0-9]*$/D',(string)$row['ID'])
            || !isset($mapping[$row['MODNAME']]) || $row['CAN_USE']!=='Y'
            || !in_array($row['PROFILE'],['admin','teacher'],true)) {
            throw new RuntimeException('DELEGATION_UNAVAILABLE');
        }
        $result['profiles'][]=['profile_id'=>(string)$row['ID'],'profile_title'=>(string)$row['TITLE'],
            'capability'=>$mapping[$row['MODNAME']],'can_use'=>true,'can_edit'=>($row['CAN_EDIT']??null)==='Y'];
    }
    return $result;
}
