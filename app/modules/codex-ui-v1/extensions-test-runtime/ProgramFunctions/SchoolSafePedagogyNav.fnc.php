<?php
// Pedagogie : en-tete et parcours communs a toutes les pages (04/11, demande du proprietaire : coherence et
// harmonisation, « un parcours par profil »). Chaque profil voit ses etapes dans l'ordre d'utilisation, la
// page active en surbrillance, le mode d'emploi et le retour au tableau de bord. Ce n'est pas un droit :
// chaque page verifie toujours son propre acces ; on n'affiche que les etapes que le compte peut ouvrir.

/** Profil d'usage : direction, enseignant (titulaire ou non), parent, eleve, ou '' si aucun parcours. */
function SchoolSafePedagogyRole(): string
{
    $profile = function_exists('User') ? (string) User('PROFILE') : '';
    return ['admin' => 'direction', 'teacher' => 'enseignant', 'parent' => 'parent', 'student' => 'eleve'][$profile] ?? '';
}

/** L'enseignant connecte est-il titulaire d'une classe a branches (bulletin) ? */
function SchoolSafePedagogyIsTitulaire(): bool
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $staff = (int) User('STAFF_ID');
    $cache = $staff > 0 && (bool) DBGetOne("SELECT 1 FROM course_periods cp WHERE cp.TEACHER_ID=" . $staff . " AND cp.SCHOOL_ID=" . (int) UserSchool() . " AND cp.SYEAR=" . (int) UserSyear()
        . " AND EXISTS (SELECT 1 FROM schoolsafe_class_branches b WHERE b.CLASS_COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID) LIMIT 1");
    return $cache;
}

/** Etapes du profil, dans l'ordre d'utilisation : [page => [libelle, groupe]]. */
function SchoolSafePedagogySteps(string $role): array
{
    $steps = [];
    if ($role === 'direction') {
        $config = AllowEdit('School_Setup/Configuration.php');
        $reports = $config || AllowUse('Grades/ReportCards.php');
        if ($config) $steps += ['preparer-annee.php' => ['Préparer l’année', 'Organiser'], 'branches-classe.php' => ['Branches et maxima', 'Organiser'], 'papier-ecole.php' => ['Papier de l’école', 'Organiser']];
        if ($reports) $steps += ['suivi-pedagogique.php' => ['Suivi pédagogique', 'Suivre'], 'synthese-bulletin.php' => ['Synthèse du bulletin', 'Suivre']];
        if (SchoolSafePedagogyCanRemediation()) $steps['rattrapage.php'] = ['Rattrapage', 'Suivre'];
        if (AllowUse('Students/Student.php')) $steps['courriers.php'] = ['Courriers', 'Communiquer'];
        if ($config) $steps['archives.php'] = ['Archives', 'Communiquer'];
    } elseif ($role === 'enseignant') {
        $steps = ['ma-journee.php' => ['Ma journée', 'Chaque jour'], 'devoirs-travaux.php' => ['Devoirs et travaux', 'Chaque jour'], 'notes-bulletin.php' => ['Notes du bulletin', 'Chaque période']];
        if (SchoolSafePedagogyIsTitulaire()) $steps['synthese-bulletin.php'] = ['Synthèse (titulaire)', 'Chaque période'];
        if (SchoolSafePedagogyCanRemediation()) $steps['rattrapage.php'] = ['Rattrapage', 'Accompagner'];
    } elseif ($role === 'parent' || $role === 'eleve') {
        $steps = ['travail-enfant.php' => [$role === 'eleve' ? 'Mon travail' : 'Travail de mon enfant', 'Suivre'], 'dossier-annee.php' => ['Dossier de l’année', 'Garder'], 'bulletin.php' => ['Bulletin', 'Garder']];
    }
    return $steps;
}

function SchoolSafePedagogyCanRemediation(): bool
{
    if (!function_exists('SchoolSafeNativePermission')) {
        $file = dirname(__DIR__) . '/modules/SchoolSafe/Permissions.php';
        if (is_file($file)) require_once $file;
    }
    return function_exists('SchoolSafeNativePermission') && SchoolSafeNativePermission('SchoolSafe/RemediationRead.php');
}

/** Page a mettre en surbrillance (les pages de detail se rattachent a leur etape). */
function SchoolSafePedagogyCurrent(string $page, string $role): string
{
    $parents = ['reglages-rattrapage.php' => 'rattrapage.php', 'bareme-annee.php' => 'preparer-annee.php'];
    if ($page === 'note-devoir.php') return ['parent' => 'travail-enfant.php', 'eleve' => 'travail-enfant.php', 'enseignant' => 'devoirs-travaux.php'][$role] ?? 'suivi-pedagogique.php';
    if (($page === 'dossier-annee.php' || $page === 'bulletin.php') && $role === 'direction') return 'synthese-bulletin.php';
    return $parents[$page] ?? $page;
}

/**
 * En-tete commun : marque, « Pedagogie · profil », mode d'emploi, tableau de bord, puis les etapes du profil.
 * $child : eleve choisi (parent), garde d'une page a l'autre.
 */
function SchoolSafePedagogyHeader(string $page, int $child = 0): string
{
    $e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $role = SchoolSafePedagogyRole();
    $names = ['direction' => 'Direction', 'enseignant' => 'Enseignant', 'parent' => 'Parent', 'eleve' => 'Élève'];
    $steps = $role !== '' ? SchoolSafePedagogySteps($role) : [];
    $current = SchoolSafePedagogyCurrent($page, $role);
    $html = '<header class="pg-head"><a class="pg-brand" href="Modules.php?modname=misc/Portal.php" title="Tableau de bord"><b>School</b>Safe</a>'
        . '<span class="pg-title">Pédagogie' . ($role !== '' ? ' · ' . $e($names[$role]) : '') . '</span>'
        . '<span class="pg-tools"><a href="guide-pedagogie.php' . ($role !== '' ? '#' . $role : '') . '"' . ($page === 'guide-pedagogie.php' ? ' aria-current="page"' : '') . '>❓ Mode d’emploi</a>'
        . '<a href="Modules.php?modname=misc/Portal.php">Tableau de bord</a></span></header>';
    if (!$steps) return $html;
    $html .= '<nav class="pg-steps" aria-label="Parcours ' . $e($names[$role] ?? '') . '"><ol>';
    $n = 0; $group = '';
    foreach ($steps as $target => [$label, $section]) {
        $n++;
        $url = $target;
        if ($child > 0 && in_array($target, ['travail-enfant.php', 'dossier-annee.php'], true)) $url .= '?enfant=' . $child;
        if ($child > 0 && $target === 'bulletin.php') $url .= '?eleve=' . $child;
        $html .= '<li' . ($section !== $group ? ' data-group="' . $e($section) . '"' : '') . ($target === $current ? ' class="on"' : '') . '><a href="' . $e($url) . '"'
            . ($target === $current ? ' aria-current="page"' : '') . '><span>' . $n . '</span>' . $e($label) . '</a></li>';
        $group = $section;
    }
    // L'etape en cours reste visible sur un petit ecran (la barre defile jusqu'a elle).
    return $html . '</ol></nav><script>(function(){var a=document.querySelector(".pg-steps li.on");if(a&&a.scrollIntoView)a.scrollIntoView({block:"nearest",inline:"center"});})();</script>';
}

/** Feuille de style commune (chargee en dernier pour harmoniser toutes les pages). */
function SchoolSafePedagogyStyle(): string
{
    return '<link rel="stylesheet" href="schoolsafe-card-bridge/pedagogie.css?v=4">';
}
