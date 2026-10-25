<?php
/** Catalog publication for native rights editors only; never changes activation or grants. */
function SchoolSafeRightsEditorMenu(array $nativeMenu, string $screen): array
{
    if (!in_array($screen, ['Users/Profiles.php', 'Users/Exceptions.php'], true)) {
        throw new InvalidArgumentException('INVALID_RIGHTS_EDITOR');
    }
    // Include in function-local scope so ordinary navigation/session menus are untouched.
    $menu = $nativeMenu;
    unset($menu['SchoolSafe']);
    include __DIR__ . '/Menu.php';
    return $menu;
}
