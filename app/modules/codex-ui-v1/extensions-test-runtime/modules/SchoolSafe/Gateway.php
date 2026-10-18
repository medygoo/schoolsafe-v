<?php
require_once __DIR__ . '/Permissions.php';

function SchoolSafeScanPermissionKeys(): array
{
    return ['entry' => 'SchoolSafe/ScanArrival.php', 'pickup' => 'SchoolSafe/ScanPickup.php',
        'fees' => 'SchoolSafe/ScanFees.php', 'canteen' => 'SchoolSafe/ScanCanteen.php', 'verify' => 'SchoolSafe/ScanVerify.php'];
}

// Returns alternatives for bootstrap/views, or one exact action key. No body
// validation, CSRF, business authorization or business execution happens here.
function SchoolSafeEndpointPermissionKeys(string $endpoint, string $method, string $operation = '', array $input = []): array
{
    if (!in_array($method, ['GET', 'POST'], true)) return [];
    if ($endpoint === 'scanner-relay.php') {
        if ($method === 'GET') {
            if ($input) return [];
            if ($operation === 'locations') return ['SchoolSafe/ScanArrival.php', 'SchoolSafe/ScanPickup.php'];
            if ($operation !== 'capabilities') return [];
            $keys = [];
            foreach (SchoolSafePermissionCatalog() as $key => $definition) {
                foreach ($definition['routes'] as $route) {
                    if ($route[0] === $endpoint) { $keys[] = $key; break; }
                }
            }
            return $keys;
        }
        if ($operation === 'scan') {
            if (array_key_exists('action', $input) || !isset($input['mode']) || !is_string($input['mode'])) return [];
            $key = SchoolSafeScanPermissionKeys()[$input['mode']] ?? null;
        } elseif (in_array($operation, ['management', 'fees', 'location-management'], true)) {
            if (array_key_exists('mode', $input) || !isset($input['action']) || !is_string($input['action'])) return [];
            $key = SchoolSafePermissionForRequest($endpoint, $operation, $input['action']);
        } else return [];
        return $key === null ? [] : [$key];
    }
    if ($operation !== '') return [];
    if ($method === 'POST') {
        if ($endpoint === 'rattrapage.php') {
            if (!isset($input['action']) || !is_string($input['action']) || $input['action'] === '') return [];
            $key = SchoolSafePermissionForRequest($endpoint, '', $input['action']);
        } elseif ($endpoint === 'remediation-payments.php') {
            // The existing confirmation form omits action; mirror its explicit
            // server default, never use it for unknown or malformed actions.
            $action = array_key_exists('action', $input) ? $input['action'] : 'confirm';
            if (!is_string($action) || !in_array($action, ['record', 'confirm'], true)) return [];
            $key = SchoolSafePermissionForRequest($endpoint, '', $action);
        } elseif ($endpoint === 'card-bridge-issue.php') {
            if (array_key_exists('action', $input) || array_key_exists('mode', $input)) return [];
            $key = 'SchoolSafe/IssueCard.php';
        } else return [];
        return $key === null ? [] : [$key];
    }
    if (array_key_exists('action', $input) || array_key_exists('mode', $input)) return [];
    $views = [
        'scan-locations.php' => ['SchoolSafe/ScanLocationsRead.php', 'SchoolSafe/ScanLocationsManage.php'],
        'arrival-attendance.php' => ['SchoolSafe/ArrivalAttendance.php'],
        'studio-native.php' => ['SchoolSafe/Studio.php'],
        'card-bridge-read.php' => ['SchoolSafe/Studio.php'],
        'card-bridge-prepare.php' => ['SchoolSafe/Studio.php'],
        'parent-notifications.php' => ['SchoolSafe/Notifications.php'],
        'parent-notifications-view.php' => ['SchoolSafe/Notifications.php'],
        'scanner-native.php' => array_values(SchoolSafeScanPermissionKeys()),
        'rattrapage.php' => ['SchoolSafe/RemediationRead.php'],
        'remediation-payments.php' => ['SchoolSafe/RemediationPaymentsRead.php',
            'SchoolSafe/RemediationPaymentsRecord.php', 'SchoolSafe/RemediationPaymentsManage.php'],
        'fee-control.php' => ['SchoolSafe/FeeModelsRead.php', 'SchoolSafe/FeeModelsManage.php']
    ];
    if ($endpoint === 'family-management.php') {
        $keys = [];
        foreach (SchoolSafePermissionCatalog() as $key => $definition) {
            if ($definition['entry'] === $endpoint) $keys[] = $key;
        }
        return $keys;
    }
    return $views[$endpoint] ?? [];
}

/**
 * Caller must branch explicitly: legacy = run existing checks; allow = run
 * existing checks plus this native grant; deny = stop before any business call.
 * No endpoint may interpret legacy as unconditional permission.
 */
function SchoolSafeEndpointGateway(string $endpoint, string $method, string $operation = '', array $input = []): string
{
    global $RosarioModules;
    $keys = SchoolSafeEndpointPermissionKeys($endpoint, $method, $operation, $input);
    if (!$keys) return 'deny';
    if (empty($RosarioModules['SchoolSafe'])) return 'legacy';
    foreach ($keys as $key) {
        if (SchoolSafeNativeCommandPermission($key)) return 'allow';
    }
    return 'deny';
}

function SchoolSafeFilterScanCapabilities(array $capabilities): array
{
    global $RosarioModules;
    if (empty($RosarioModules['SchoolSafe'])) return $capabilities;
    $mapping = ['SCAN_ENTRY' => 'entry', 'SCAN_PICKUP' => 'pickup', 'SCAN_FEES' => 'fees', 'SCAN_CANTEEN' => 'canteen', 'SCAN_VERIFY' => 'verify'];
    $allowed = [];
    foreach ($capabilities as $capability) {
        if (!is_string($capability)) continue;
        if (isset($mapping[$capability])) {
            if (SchoolSafeNativeCommandPermission(SchoolSafeScanPermissionKeys()[$mapping[$capability]])) $allowed[] = $capability;
        } elseif (strpos($capability, 'SCAN_') !== 0) {
            $allowed[] = $capability;
        }
    }
    return array_values(array_unique($allowed));
}
