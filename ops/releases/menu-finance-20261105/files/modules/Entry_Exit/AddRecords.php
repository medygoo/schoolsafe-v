<?php
// Pointage manuel du personnel (oublie de badge, correction).

DrawHeader(ProgramTitle());

if ($_REQUEST['values'] && AllowEdit()) {
    $staffId = (int) $_REQUEST['values']['staff_id'];
    $type = $_REQUEST['values']['type'] === 'out' ? 'out' : 'in';
    $when = $_REQUEST['values']['when'];
    if ($staffId && $when && VerifyDate($when)) {
        $time = date('Y-m-d H:i:s', strtotime($when));
        DBQuery("INSERT INTO entry_exit_staff_records (staff_id, checkpoint_id, type, unauthorized, comments, recorded_at)
            VALUES (" . $staffId . ", 1, '" . $type . "', '', '" . $time . "')");
        $note[] = button('check') . '&nbsp;' . 'Pointage enregistré.';
    }
}

DrawHeader('', SubmitButton(_('Enregistrer'), 'submit'));
PopTable('header', _('Pointage manuel'));

$staffs = DBGet("SELECT staff_id, first_name || ' ' || last_name AS FULL_NAME
    FROM staff WHERE SYEAR='" . UserSyear() . "' AND PROFILE IN ('admin','teacher')
    AND (CURRENT_SCHOOL_ID='" . (int) UserSchool() . "' OR POSITION('," . (int) UserSchool() . ",' IN SCHOOLS) > 0)
    ORDER BY FULL_NAME");

$options = [];
foreach ($staffs as $staff) $options[$staff['STAFF_ID']] = $staff['FULL_NAME'];

echo '<form action="' . URLEscape('Modules.php?modname=' . $_REQUEST['modname']) . '" method="POST">';
echo SelectInput($staffId, 'values[staff_id]', 'Personnel', $options);
echo DateInput($when, 'values[when]', 'Date et heure');
echo SelectInput($type, 'values[type]', 'Type', ['in' => 'Entrée', 'out' => 'Sortie']);
echo '</form>';

PopTable('footer');
