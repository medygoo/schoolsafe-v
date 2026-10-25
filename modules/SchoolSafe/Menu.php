<?php
require_once __DIR__ . '/Permissions.php';
foreach (SchoolSafePermissionCatalog() as $key => $definition) {
    foreach ($definition['profiles'] as $profile) {
        $menu['SchoolSafe'][$profile]['title'] = 'SchoolSafe';
        $menu['SchoolSafe'][$profile][$key] = $definition['title'];
    }
}
