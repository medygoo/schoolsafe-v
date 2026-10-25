<?php
require_once __DIR__ . '/Permissions.php';
function SchoolSafeRenderPermissionProgram(string $key): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        http_response_code(405);
        echo 'Method not allowed.';
        return;
    }
    if (!SchoolSafeNativeCommandPermission($key)) {
        http_response_code(403);
        echo 'Permission refusee.';
        return;
    }
    $definition = SchoolSafePermissionCatalog()[$key];
    $title = htmlspecialchars($definition['title'], ENT_QUOTES, 'UTF-8');
    $entryUrl = $definition['entry'];
    $route = $definition['routes'][0] ?? [];
    if (in_array($entryUrl, ['family-management.php', 'scanner-native.php'], true)
        && !empty($route[2])) {
        $entryUrl .= '#' . rawurlencode($route[2]);
    }
    $entry = htmlspecialchars($entryUrl, ENT_QUOTES, 'UTF-8');
    DrawHeader($definition['title']);
    echo '<iframe class="schoolsafe-native-tool" src="' . $entry . '" title="' . $title
        . '" style="display:block;width:100%;height:75vh;min-height:480px;border:0"'
        . ' allow="camera \'self\'; microphone \'self\'"></iframe>';
    echo '<noscript><p><a href="' . $entry . '">' . $title . '</a></p></noscript>';
}
