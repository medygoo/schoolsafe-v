<?php
require_once __DIR__ . '/SchoolSafeSchoolAccess.fnc.php';

/** Shared by the existing central console and native school profile hub. */
function SchoolSafeAccessSubmit(int $school, bool $bootstrap, string $tokenField): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_SESSION['token'])
        || !is_string($_POST[$tokenField] ?? null) || !hash_equals((string)$_SESSION['token'], $_POST[$tokenField])
        || ($_POST['access_confirm'] ?? '') !== 'Y' || ($_POST['access_present'] ?? '') !== '1'
        || !is_string($_POST['access_staff'] ?? null) || !preg_match('/^[1-9][0-9]*$/D',$_POST['access_staff'])
        || !is_string($_POST['access_version'] ?? null) || !is_array($_POST['access_rights'] ?? [])) {
        return ['status'=>'refused','error'=>'Confirmation requise ou requête expirée. Rechargez la page.'];
    }
    return SchoolSafeAccessAssign($school,(int)$_POST['access_staff'],$_POST['access_rights'] ?? [],$_POST['access_version'],$bootstrap);
}

function SchoolSafeAccessRender(int $school, int $year, int $selected, bool $bootstrap, string $url, string $tokenField): void
{
    $esc = static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
    $targets = SchoolSafeAccessTargets($school,$year);
    $current = null;
    echo '<section class="card" id="school-native-access"><h2>'.($bootstrap?'Responsabilités et accès de l’établissement':'Accès personnels de l’école').'</h2>';
    echo '<p>Un même ensemble peut être donné à plusieurs personnes, sans quota ni exclusivité par tâche. Les comptes restent personnels.</p>';
    echo '<p>'.($bootstrap?'Administration des accès uniquement : ProDéli n’entre pas dans l’espace scolaire. Choisissez un administrateur scolaire existant.':'Choisissez une personne de votre école. Chaque modification conserve les autres titulaires et les droits natifs sans rapport.').'</p>';
    echo '<ul>';
    foreach ($targets as $target) {
        if ($bootstrap && $target['PROFILE'] !== 'admin') continue;
        if ((int)$target['STAFF_ID'] === (int)User('STAFF_ID')) continue;
        if ((int)$target['STAFF_ID'] === $selected) $current=$target;
        echo '<li><a href="'.$esc($url.(strpos($url,'?')===false?'?':'&').'access_staff='.(int)$target['STAFF_ID']).'#school-native-access">'.$esc($target['USERNAME']).'</a> — '.$esc($target['PROFILE']).'</li>';
    }
    echo '</ul>';
    if (!$current) { echo '<p>Choisissez le compte à administrer dans cette école.</p></section>'; return; }
    $rights = SchoolSafeAccessRights($current);
    $actor = SchoolSafeAccessRows('SELECT staff_id,profile_id FROM staff WHERE staff_id=$1 AND syear=$2',[(int)User('STAFF_ID'),$year]);
    $ceiling = $bootstrap ? [] : SchoolSafeAccessRights($actor[1]);
    echo '<h3>Compte : '.$esc($current['USERNAME']).'</h3><p>Une nouvelle version de profil natif, propre à ce compte et à cette école, sera enregistrée si les droits changent. Le profil partagé d’origine et ses autres titulaires ne seront pas modifiés.</p>';
    echo '<form method="post" action="'.$esc($url).'#school-native-access" data-native-access-form>';
    echo '<input type="hidden" name="'.$esc($tokenField).'" value="'.$esc($_SESSION['token'] ?? '').'">';
    echo '<input type="hidden" name="'.($bootstrap?'console_action':'access_action').'" value="'.($bootstrap?'school_access_save':'save').'">';
    echo '<input type="hidden" name="access_school" value="'.$school.'"><input type="hidden" name="access_present" value="1">';
    echo '<input type="hidden" name="access_staff" value="'.$selected.'"><input type="hidden" name="access_version" value="'.$esc(SchoolSafeAccessVersion($current,$rights)).'">';
    $writable = !SchoolSafeAccountBlocksWrite() && SchoolSafeAccountStateForSchool((string)User('USERNAME'),$school)==='active'
        && ($bootstrap || (SchoolSafeNativeCommandPermission('SchoolSafe/DelegationManage.php')
            && SchoolSafeAccessRightsCovered($ceiling,['Users/User.php'=>[true,true]])));
    if (!$writable) echo '<p>Consultation seule : votre compte ne peut pas modifier ces accès.</p>';
    echo '<fieldset style="min-width:0"'.($writable?'':' disabled').'><legend>Droits du compte sélectionné</legend>';
    echo '<p><button type="button" data-access-all>Tous les droits proposés</button> <button type="button" data-access-clear>Retirer les droits proposés</button></p>';
    echo '<p><small>Use = accès ; Edit = modification ou exécution des commandes administratives. Les montants détaillés, la configuration et la gestion des accès sont des droits séparés. Une case non cochée retire ce droit proposé au compte choisi.</small></p>';
    echo '<table><thead><tr><th>Fonction</th><th style="white-space:nowrap">Use</th><th style="white-space:nowrap">Edit</th></tr></thead><tbody>';
    foreach (SchoolSafeAccessCatalog() as $key=>$definition) {
        if (!in_array($current['PROFILE'],$definition['profiles'],true)) continue;
        $flags=$rights[$key] ?? [false,false]; $allowed=$bootstrap?[true,true]:($ceiling[$key] ?? [false,false]);
        // Non-delegable existing rights are preserved rather than silently lost
        // merely because their checkbox cannot be edited by this actor.
        if (!$allowed[0] || ($flags[1] && !$allowed[1])) {
            if ($flags[0] || $flags[1]) echo '<tr><td>'.$esc($definition['title']).'</td><td colspan="2">Hors de votre plafond : modification refusée côté serveur.</td></tr>';
            continue;
        }
        echo '<tr><td>'.$esc($definition['title']).'<br><small style="overflow-wrap:anywhere">'.$esc($key).'</small></td><td><input aria-label="'.$esc($definition['title'].' — Use').'" type="checkbox" data-access-use name="access_rights['.$esc($key).'][use]" value="Y"'.($flags[0]?' checked':'').'></td>';
        echo '<td><input aria-label="'.$esc($definition['title'].' — Edit').'" type="checkbox" data-access-edit name="access_rights['.$esc($key).'][edit]" value="Y"'.($flags[1]?' checked':'').(!$allowed[1]?' disabled':'').'></td></tr>';
    }
    echo '</tbody></table><p><label><input type="checkbox" name="access_confirm" value="Y" required> Je confirme ces accès pour ce compte dans cette école. Les autres comptes restent inchangés.</label></p>';
    echo '<button type="submit">Confirmer les accès</button></fieldset></form>';
    echo '<script>(()=>{const f=document.currentScript.previousElementSibling;if(!f||!f.matches("[data-native-access-form]"))return;f.querySelector("[data-access-all]").onclick=()=>{f.querySelectorAll("input[data-access-use],input[data-access-edit]").forEach(x=>{if(!x.disabled)x.checked=true})};f.querySelector("[data-access-clear]").onclick=()=>{f.querySelectorAll("input[data-access-use],input[data-access-edit]").forEach(x=>{if(!x.disabled)x.checked=false})};f.querySelectorAll("tr").forEach(r=>{const u=r.querySelector("[data-access-use]"),e=r.querySelector("[data-access-edit]");if(u&&e){u.onchange=()=>{if(!u.checked)e.checked=false};e.onchange=()=>{if(e.checked)u.checked=true}}})})();</script></section>';
}
