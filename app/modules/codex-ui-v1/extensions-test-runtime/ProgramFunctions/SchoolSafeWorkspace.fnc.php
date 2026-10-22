<?php
/** Shared presentation only. Native controllers still own permissions and writes. */
function SchoolSafeWorkspaceHeader(string $title, string $kind = 'school'): void
{
    global $RosarioLocales;
    $global = SchoolSafeGlobalAdmin();
    $central = $kind === 'central' && $global;
    $escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    $home = $global ? '/schoolsafe-administrators.php?home=1' : '/Modules.php?modname=misc/Portal.php';
    echo '<link rel="stylesheet" href="/assets/themes/SchoolSafeCodex/menu-logo.css?v=shared-2">'
        . '<link rel="stylesheet" href="/assets/css/schoolsafe-native-workspace.css?v=1">'
        . '<header class="ss-native-workspace-header" data-workspace="' . ($central ? 'central' : 'school') . '">'
        . '<div class="ss-native-workspace-top"><a class="ss-native-workspace-brand" href="' . $home . '" target="_self">'
        . '<span class="ss-native-workspace-emblem"><img class="ss-animated-brand" src="/schoolsafe-logo.png" alt="" width="48" height="52"></span>'
        . '<span><strong>SchoolSafe</strong><small>' . ($central ? 'ProDéli · Administration' : 'Espace scolaire') . '</small></span></a>'
        . '<nav aria-label="Retour à votre espace"><a class="ss-native-workspace-return" href="' . $home . '" target="_self">'
        . ($global ? 'Retour à ProDéli' : 'Retour au tableau de bord') . '</a>';
    if (count((array) $RosarioLocales) > 1) {
        echo '<span class="ss-native-workspace-languages" aria-label="Langue de l’interface">';
        foreach ($RosarioLocales as $locale) {
            $code = substr($locale, 0, 2);
            $label = $code === 'fr' ? 'Français' : ($code === 'en' ? 'English' : $code);
            $url = PreparePHP_SELF([], ['locale','modfunc','token','action','_ROSARIO_PDF','LO_save'], ['locale'=>$locale]);
            echo '<a href="'.$escape($url).'" hreflang="'.$escape($code).'" target="_self"'
                . (($_SESSION['locale'] ?? '') === $locale ? ' aria-current="true"' : '') . '>'.$escape($label).'</a>';
        }
        echo '</span>';
    }
    if ($global && !$central) {
        echo '<a href="/Modules.php?modname=misc/Portal.php" target="_self">Tableau de bord de l’école</a>';
    }
    echo '<a href="/index.php?modfunc=logout" target="_self">Déconnexion</a></nav></div>'
        . '<div class="ss-native-workspace-heading"><p>'
        . ($central ? 'Gestion de la plateforme' : 'École sélectionnée : ' . $escape(SchoolInfo('TITLE')))
        . '</p><h1>' . $escape($title) . '</h1></div></header>';
}
