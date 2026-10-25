<?php
function SchoolSafePermissionCatalog(): array
{
    static $catalog;
    if ($catalog === null) $catalog = require __DIR__ . '/Catalog.php';
    return $catalog;
}

/**
 * Exact native permission resolution, including for non-admin users.
 * This gate supplements business capabilities, principal authority and tenant scope.
 */
function SchoolSafeNativePermission(string $key, string $access = 'use'): bool
{
    global $RosarioModules;
    $catalog = SchoolSafePermissionCatalog();
    if (!isset($catalog[$key]) || !in_array($access, ['use', 'edit'], true)
        || empty($RosarioModules['SchoolSafe']) || !function_exists('User')
        || !function_exists('DBGetOne')) return false;
    $staff = (string) User('STAFF_ID');
    $profile = (string) User('PROFILE');
    if (!preg_match('/^[1-9][0-9]*$/D', $staff)
        || !in_array($profile, $catalog[$key]['profiles'], true)) return false;
    // PROFILE_ID=0 is an assigned profile; only an absent ID uses staff_exceptions.
    $profileId = User('PROFILE_ID');
    if ($profileId !== null && $profileId !== false && (string) $profileId !== '') {
        if (!preg_match('/^[0-9]+$/D', (string) $profileId)) return false;
        $where = "FROM profile_exceptions WHERE PROFILE_ID='" . (string) $profileId . "'";
    } else {
        $where = "FROM staff_exceptions WHERE USER_ID='" . $staff . "'";
    }
    // Catalog-owned keys only; callers cannot inject arbitrary MODNAME SQL.
    $where .= " AND MODNAME='" . $key . "' AND CAN_USE='Y'";
    if ($access === 'edit') $where .= " AND CAN_EDIT='Y'";
    try {
        return (bool) DBGetOne('SELECT 1 ' . $where);
    } catch (Throwable $error) {
        return false;
    }
}

function SchoolSafeNativeCommandPermission(string $key): bool
{
    $definition = SchoolSafePermissionCatalog()[$key] ?? null;
    if (!$definition || !SchoolSafeNativePermission($key)) return false;
    // A read-only (or suspended/expired) account never mutates, whatever its
    // native grants say. Same rule as AllowEdit(), applied to the routes.
    if ($definition['mutates'] && function_exists('SchoolSafeAccountBlocksWrite')
        && SchoolSafeAccountBlocksWrite()) return false;
    // Native role screens expose edit checkboxes for admins; other profiles use
    // separate command programs, never ambient AllowEdit() state.
    return !$definition['mutates'] || User('PROFILE') !== 'admin'
        || SchoolSafeNativePermission($key, 'edit');
}

function SchoolSafePermissionForRequest(string $endpoint, string $operation = '', string $action = ''): ?string
{
    foreach (SchoolSafePermissionCatalog() as $key => $definition) {
        foreach ($definition['routes'] as $route) {
            if ($route === [$endpoint, $operation, $action]) return $key;
        }
    }
    return null;
}

function SchoolSafeNativeRequestPermission(string $endpoint, string $operation = '', string $action = ''): bool
{
    $key = SchoolSafePermissionForRequest($endpoint, $operation, $action);
    return $key !== null && SchoolSafeNativeCommandPermission($key);
}
