<?php
function SchoolSafeValidateOfficialPrintRead(array $result, string $student, string $school, string $year, string $qr): array
{
    $data=$result['data'] ?? null;
    $credential=$data['input']['credential'] ?? null;
    $uuid='/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/iD';
    if (!is_array($data) || ($data['issuanceAllowed'] ?? null)!==false
        || ($data['nativeReference']['studentId'] ?? null)!==$student
        || ($data['nativeReference']['schoolId'] ?? null)!==$school
        || ($data['nativeReference']['year'] ?? null)!==$year
        || !preg_match($uuid,$data['targetReference']['studentId'] ?? '')
        || !preg_match($uuid,$data['targetReference']['schoolId'] ?? '')
        || ($data['input']['record']['id'] ?? null)!==$data['targetReference']['studentId']
        || ($data['input']['record']['school_id'] ?? null)!==$data['targetReference']['schoolId']
        || ($data['missing'] ?? null)!==[]
        || ($credential['status'] ?? null)!=='active' || empty($credential['card_id'])
        || ($credential['qr_payload'] ?? null)!==$qr
        || $qr!=='schoolsafe://card/'.($credential['card_number'] ?? '').'/'.($credential['signature'] ?? '')
        || empty($data['input']['academicYearLabel'])) {
        throw new RuntimeException('OFFICIAL_PRINT_NOT_READY');
    }
    return $data;
}
