<?php
/** The native fee must still describe this case before recording or confirming money. */
function SchoolSafeRemediationFeeScopeSql(): string
{
    return 'EXISTS(SELECT 1 FROM billing_fees fee WHERE fee.id=r.fee_id'
        . ' AND fee.student_id=r.student_id AND fee.school_id=r.school_id AND fee.syear=r.syear'
        . ' AND fee.amount=r.tariff AND fee.amount>0)';
}

/** Lock the native fee before the dossier, matching native fee deletion/FK order. */
function SchoolSafeRemediationLockFeeSql(int $case, int $school, int $year): string
{
    return 'SELECT fee.id FROM billing_fees fee JOIN schoolsafe_remediation_cases r ON r.fee_id=fee.id'
        . ' WHERE r.id=' . $case . ' AND r.school_id=' . $school . ' AND r.syear=' . $year
        . " AND r.billing_mode='paid' AND " . SchoolSafeRemediationFeeScopeSql() . ' FOR SHARE OF fee';
}
