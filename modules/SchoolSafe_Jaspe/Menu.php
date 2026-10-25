<?php
global $RosarioPlugins;
foreach (['admin', 'teacher', 'parent'] as $jaspeProfile) {
    $menu['SchoolSafe_Jaspe'][$jaspeProfile] = [
        'title'=>'JASP', 'default'=>'SchoolSafe_Jaspe/Assistant.php',
        'SchoolSafe_Jaspe/Assistant.php'=>'Assistant JASP',
    ];
    if (!empty($RosarioPlugins['Setup_Assistant'])) {
        $menu['SchoolSafe_Jaspe'][$jaspeProfile]['misc/Portal.php'] = 'Assistant de configuration Rosario';
    }
}
unset($jaspeProfile);
