<?php
function SchoolSafeCanteenAccountResult(array $rows, string $name): array
{
    $base = ['result'=>'CANTEEN_STATUS','student_name'=>$name,'read_only'=>true];
    if (!$rows) return $base + ['status'=>'no_account','label'=>'Aucun compte cantine renseigne.'];
    if (count($rows)!==1) throw new RuntimeException('NATIVE_CANTEEN_UNAVAILABLE');
    $row = reset($rows);
    if (empty($row['ACCOUNT_ID']) || !isset($row['BALANCE'])) return $base + ['status'=>'no_data','label'=>'Donnees du compte cantine indisponibles.'];
    $balance = (string)$row['BALANCE'];
    if (!preg_match('/^-?[0-9]+(?:\.[0-9]{1,2})?$/D', $balance)) throw new RuntimeException('NATIVE_CANTEEN_UNAVAILABLE');
    $state = (string)($row['STATUS'] ?? '');
    if (!in_array($state, ['', 'Active'], true)) return $base + ['status'=>'inactive','label'=>'Compte cantine inactif.'];
    $status = (float)$balance > 0 ? 'credit_available' : ((float)$balance < 0 ? 'negative_balance' : 'zero_balance');
    $labels = ['credit_available'=>'Credit cantine disponible','negative_balance'=>'Solde cantine negatif','zero_balance'=>'Aucun credit cantine disponible'];
    $amount=html_entity_decode(strip_tags(Currency($balance)),ENT_QUOTES,'UTF-8');
    return $base + ['status'=>$status,'balance'=>$balance,'label'=>$labels[$status].' : '.$amount.'. Verification seulement ; aucun repas debite.'];
}

function SchoolSafeCanteenScanRead(int $student, int $school, int $year): array
{
    global $RosarioModules;
    if (empty($RosarioModules['Food_Service']) || !SchoolSafeNativeCommandPermission('SchoolSafe/ScanCanteen.php')) throw new RuntimeException('NATIVE_PERMISSION_REQUIRED');
    if ($student<1 || $school<1 || $year<1) throw new RuntimeException('INVALID_INPUT');
    $today=(new DateTimeImmutable('today',new DateTimeZone('Africa/Kinshasa')))->format('Y-m-d');
    $people=DBGet("SELECT s.first_name,s.last_name FROM students s WHERE s.student_id=$student
        AND EXISTS(SELECT 1 FROM student_enrollment e WHERE e.student_id=s.student_id
        AND e.school_id=$school AND e.syear=$year AND e.start_date<='$today'
        AND (e.end_date IS NULL OR e.end_date>='$today'))");
    if (count($people)!==1) throw new RuntimeException('NATIVE_STUDENT_NOT_ACCESSIBLE');
    $person=reset($people);
    $rows=DBGet("SELECT a.account_id,a.status,b.balance FROM food_service_student_accounts a
        LEFT JOIN food_service_accounts b ON b.account_id=a.account_id WHERE a.student_id=$student");
    return SchoolSafeCanteenAccountResult(array_values($rows),$person['FIRST_NAME'].' '.$person['LAST_NAME']);
}
