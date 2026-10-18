<?php
// Run only with PHP CLI; synthetic menus never replace the live session menu.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require '/var/www/html/modules/misc/includes/SchoolSafeDashboard.inc.php';
$_ROSARIO = ['Menu' => [
    'Users' => ['Custom/NotifyParents.php' => 'Notify parents'],
    'School_Setup' => ['School_Setup/Configuration.php' => 'Configuration'],
]];
$checks = [
    'cross-module menu' => SchoolSafeMenuProgramTitle('Custom/NotifyParents.php') === 'Notify parents',
    'ordinary menu' => SchoolSafeMenuProgramTitle('School_Setup/Configuration.php') === 'Configuration',
    'absent teacher program' => SchoolSafeMenuProgramTitle('Grades/Grades.php') === '',
    'add-user permission distinct' => SchoolSafeMenuProgramTitle('Users/User.php&staff_id=new') === '',
];
$_ROSARIO['Menu'] = [];
$checks['empty menu'] = SchoolSafeMenuProgramTitle('Custom/NotifyParents.php') === '';
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
}
exit(in_array(false, $checks, true) ? 1 : 0);
