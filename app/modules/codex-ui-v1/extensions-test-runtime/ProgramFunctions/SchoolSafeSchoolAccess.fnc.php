<?php
/** Native school access administration. No new permission store or identity.
 * Controllers own HTTP/CSRF; this owner rechecks live authority and commits the
 * native profile assignment and existing account audit together.
 */
require_once dirname(__DIR__) . '/modules/SchoolSafe/Permissions.php';
function SchoolSafeAccessRightsCovered(array $ceiling, array $wanted): bool
{
    foreach ($wanted as $key => $flags) {
        if (!isset($ceiling[$key]) || ($flags[0] && !$ceiling[$key][0])
            || ($flags[1] && !$ceiling[$key][1])) return false;
    }
    return true;
}

function SchoolSafeAccessCatalog(): array
{
    $catalog = require dirname(__DIR__) . '/modules/SchoolSafe/Catalog.php';
    foreach ($catalog as $key => $definition) {
        if (!array_intersect(['admin', 'teacher'], $definition['profiles'])) unset($catalog[$key]);
    }
    // These existing native rights are prerequisites/details, never implied by Scan.
    $catalog += [
        'Users/User.php' => ['title'=>'Gérer les comptes de l’école', 'profiles'=>['admin']],
        'School_Setup/Configuration.php' => ['title'=>'Configurer mon école', 'profiles'=>['admin']],
        'Student_Billing/StudentFees.php' => ['title'=>'Consulter / gérer les frais natifs (montants)', 'profiles'=>['admin']],
        'Food_Service/Accounts.php' => ['title'=>'Consulter les comptes cantine (montants)', 'profiles'=>['admin','teacher']],
    ];
    return $catalog;
}

function SchoolSafeAccessInput(array $input, string $kind): array
{
    $catalog = SchoolSafeAccessCatalog(); $wanted = [];
    foreach ($input as $key => $flags) {
        if (!is_string($key) || !isset($catalog[$key]) || !in_array($kind, $catalog[$key]['profiles'], true)
            || !is_array($flags) || array_diff(array_keys($flags), ['use','edit'])
            || ($flags['use'] ?? null) !== 'Y' || (isset($flags['edit']) && $flags['edit'] !== 'Y')) {
            throw new InvalidArgumentException('Sélection de droits invalide.');
        }
        $wanted[$key] = [true, ($flags['edit'] ?? '') === 'Y'];
    }
    ksort($wanted); return $wanted;
}

function SchoolSafeAccessQuery(string $sql, array $params = [])
{
    global $db_connection;
    $result = @pg_query_params($db_connection, $sql, $params);
    if ($result === false) throw new RuntimeException('Gestion des accès indisponible.');
    return $result;
}

function SchoolSafeAccessRows(string $sql, array $params = []): array
{
    return DBGet(SchoolSafeAccessQuery($sql, $params)) ?: [];
}

/** Match the native resolver, including named profile zero. No permissions cache. */
function SchoolSafeAccessRights(array $staff): array
{
    $named = $staff['PROFILE_ID'] !== null && (string)$staff['PROFILE_ID'] !== '';
    $rows = SchoolSafeAccessRows('SELECT modname,can_use,can_edit FROM '
        . ($named ? 'profile_exceptions WHERE profile_id=$1' : 'staff_exceptions WHERE user_id=$1'),
        [$named ? (int)$staff['PROFILE_ID'] : (int)$staff['STAFF_ID']]);
    $rights = [];
    foreach ($rows as $row) {
        if ($row['CAN_USE'] !== 'Y' && $row['CAN_EDIT'] !== 'Y') continue;
        $rights[$row['MODNAME']] = [$row['CAN_USE'] === 'Y', $row['CAN_EDIT'] === 'Y'];
    }
    ksort($rights); return $rights;
}

/** Fingerprint for the existing shared-profile editor, including every holder. */
function SchoolSafeAccessProfileVersion(int $pid): string
{
    $profile = SchoolSafeAccessRows('SELECT id,title,profile,school_id FROM user_profiles WHERE id=$1',[$pid]);
    $rights = SchoolSafeAccessRows('SELECT modname,can_use,can_edit FROM profile_exceptions WHERE profile_id=$1 ORDER BY modname',[$pid]);
    $holders = SchoolSafeAccessRows('SELECT staff_id,syear,profile,schools FROM staff WHERE profile_id=$1 ORDER BY staff_id,syear',[$pid]);
    return hash('sha256',json_encode([$profile,$rights,$holders],JSON_THROW_ON_ERROR));
}

/** Bracket the EXISTING school profile controller's writes, not a second CRUD. */
function SchoolSafeAccessProfileBegin(int $school, int $pid, string $version): array
{
    global $db_connection;
    $started=false;
    try {
        if (pg_transaction_status($db_connection)!==PGSQL_TRANSACTION_IDLE) throw new RuntimeException('Transaction déjà ouverte.');
        SchoolSafeAccessQuery('BEGIN'); $started=true;
        SchoolSafeAccessQuery("SET LOCAL lock_timeout='5s'");
        if (!SchoolSafeAccessRows('SELECT id FROM schools WHERE id=$1 AND syear=$2 FOR UPDATE',[$school,(int)UserSyear()])) throw new RuntimeException('Établissement indisponible.');
        $rows=SchoolSafeAccessRows('SELECT staff_id,syear,username,profile,profile_id,schools FROM staff WHERE staff_id=$1 AND syear=$2 FOR UPDATE',[(int)User('STAFF_ID'),(int)UserSyear()]);
        $actor=$rows[1]??[];
        if (!$actor || $actor['PROFILE']!=='admin' || SchoolSafeScopeParse($actor['SCHOOLS'])===null
            || !SchoolSafeScopeContains(SchoolSafeScopeParse($actor['SCHOOLS']),$school)
            || (string)$actor['PROFILE_ID']!==(string)User('PROFILE_ID') || (string)$actor['SCHOOLS']!==(string)User('SCHOOLS')
            || SchoolSafeSchoolBlocked($school) || SchoolSafeAccountBlocksWrite()
            || SchoolSafeAccountStateForSchool($actor['USERNAME'],$school)!=='active') throw new RuntimeException('Autorité scolaire indisponible.');
        SchoolSafeAccessRows('SELECT id FROM user_profiles WHERE id=$1 FOR SHARE',[(int)$actor['PROFILE_ID']]);
        SchoolSafeAccessRows('SELECT profile_id FROM profile_exceptions WHERE profile_id=$1 FOR SHARE',[(int)$actor['PROFILE_ID']]);
        $ceiling=SchoolSafeAccessRights($actor);
        if (!SchoolSafeNativeCommandPermission('SchoolSafe/DelegationManage.php') || !SchoolSafeAccessRightsCovered($ceiling,['Users/User.php'=>[true,true],'SchoolSafe/DelegationManage.php'=>[true,true]])) throw new RuntimeException('Gestion des accès non autorisée.');
        $before=[];$holders=[];
        if ($pid>0) {
            $profile=SchoolSafeAccessRows('SELECT id,profile,school_id FROM user_profiles WHERE id=$1 FOR UPDATE',[$pid]);
            if (!$profile || (int)$profile[1]['SCHOOL_ID']!==$school || !in_array($profile[1]['PROFILE'],['admin','teacher'],true)) throw new RuntimeException('Profil non modifiable ici.');
            $holders=SchoolSafeAccessRows('SELECT staff_id,syear,profile,schools FROM staff WHERE profile_id=$1 ORDER BY staff_id,syear FOR UPDATE',[$pid]);
            foreach ($holders as $holder) if ((int)$holder['STAFF_ID']===(int)$actor['STAFF_ID'] || $holder['SCHOOLS']!==','.$school.',') throw new RuntimeException('Votre profil ou un détenteur hors école est protégé.');
            SchoolSafeAccessRows('SELECT profile_id FROM profile_exceptions WHERE profile_id=$1 FOR UPDATE',[$pid]);
            $before=SchoolSafeAccessRights(['PROFILE_ID'=>$pid,'STAFF_ID'=>0]);
            if (!SchoolSafeAccessRightsCovered($ceiling,$before)) throw new RuntimeException('Profil cible hors de votre autorité.');
            if (!hash_equals(SchoolSafeAccessProfileVersion($pid),$version)) throw new RuntimeException('Profil ou détenteurs modifiés. Rechargez avant de confirmer.');
        }
        return ['actor'=>$actor,'school'=>$school,'pid'=>$pid,'before'=>$before,'holders'=>$holders];
    } catch (Throwable $error) {
        if($started) @pg_query($db_connection,'ROLLBACK');
        throw $error;
    }
}

function SchoolSafeAccessProfileFinish(array $context, int $pid, string $action): void
{
    $actor=$context['actor'];
    if (SchoolSafeAccountBlocksWrite() || SchoolSafeAccountStateForSchool($actor['USERNAME'],$context['school'])!=='active') throw new RuntimeException('Autorité retirée.');
    $after=$action==='supprimer'?[]:SchoolSafeAccessRights(['PROFILE_ID'=>$pid,'STAFF_ID'=>0]);
    SchoolSafeAccessQuery('INSERT INTO schoolsafe_account_audit(person_key,school_id,action,reason,actor) VALUES($1,$2,$3,$4,$5)',
        [SchoolSafeAccountPerson($actor['USERNAME']),$context['school'],'profile_'.$action,json_encode(['profile_id'=>$pid,'before'=>$context['before'],'after'=>$after,'holders'=>$context['holders']],JSON_THROW_ON_ERROR),$actor['USERNAME']]);
    SchoolSafeAccessQuery('COMMIT');
}

function SchoolSafeAccessVersion(array $staff, array $rights): string
{
    ksort($rights);
    return hash('sha256', json_encode([(int)$staff['STAFF_ID'],(int)$staff['SYEAR'],
        $staff['PROFILE'], $staff['PROFILE_ID'], $staff['SCHOOLS'], $rights], JSON_THROW_ON_ERROR));
}

/** Read metadata only after the controller's native scope gate. */
function SchoolSafeAccessTargets(int $school, int $year): array
{
    return SchoolSafeAccessRows("SELECT staff_id,syear,username,profile,profile_id,schools
        FROM staff WHERE syear=$1 AND schools=$2 AND profile IN ('admin','teacher')
        ORDER BY username,staff_id", [$year, ','.$school.',']);
}

/** Every target change clones a school-owned NATIVE profile, preserving all
 * unrelated rights and all other holders. No staff_exceptions override of a
 * named profile, no password/identity/leadership change, no recipient quota.
 */
function SchoolSafeAccessAssign(int $school, int $targetId, array $selection, string $version,
    bool $bootstrap = false): array
{
    global $db_connection;
    $started = false;
    try {
        if ($school < 1 || $targetId < 1 || !preg_match('/^[a-f0-9]{64}$/D', $version)
            || pg_transaction_status($db_connection) !== PGSQL_TRANSACTION_IDLE) throw new RuntimeException('Requête invalide.');
        SchoolSafeAccessQuery('BEGIN'); $started = true;
        SchoolSafeAccessQuery("SET LOCAL lock_timeout='5s'");
        // Serialize this owner's school changes; two managers cannot revoke each
        // other concurrently using a stale authority or a stale target form.
        if (!SchoolSafeAccessRows('SELECT id FROM schools WHERE id=$1 AND syear=$2 FOR UPDATE', [$school,(int)UserSyear()]))
            throw new RuntimeException('Établissement indisponible.');
        $actorId = (int)User('STAFF_ID');
        $staff = SchoolSafeAccessRows('SELECT staff_id,syear,username,profile,profile_id,schools FROM staff
            WHERE syear=$1 AND staff_id IN ($2,$3) ORDER BY staff_id FOR UPDATE', [(int)UserSyear(),$actorId,$targetId]);
        $actor = $target = null;
        foreach ($staff as $row) {
            if ((int)$row['STAFF_ID'] === $actorId) $actor = $row;
            if ((int)$row['STAFF_ID'] === $targetId) $target = $row;
        }
        if (!$actor || !$target || $actorId === $targetId || $actor['PROFILE'] !== 'admin'
            || !in_array($target['PROFILE'], ['admin','teacher'], true) || $target['SCHOOLS'] !== ','.$school.',')
            throw new RuntimeException('Cible ou autorité non autorisée.');
        // Reject a session whose stored role/profile/scope changed during request.
        if ($actor['PROFILE'] !== User('PROFILE') || (string)$actor['PROFILE_ID'] !== (string)User('PROFILE_ID')
            || (string)$actor['SCHOOLS'] !== (string)User('SCHOOLS')) throw new RuntimeException('Autorité modifiée. Rechargez la page.');
        $global = SchoolSafeScopeParse($actor['SCHOOLS']) === null;
        if ($bootstrap !== $global || (!$global && !SchoolSafeScopeContains(SchoolSafeScopeParse($actor['SCHOOLS']), $school)))
            throw new RuntimeException('Périmètre non autorisé.');
        if (SchoolSafeSchoolBlocked($school) || SchoolSafeAccountBlocksWrite()
            || SchoolSafeAccountStateForSchool($actor['USERNAME'], $school) !== 'active'
            || in_array(SchoolSafeAccountStateForSchool($target['USERNAME'], $school), ['suspended','expired'], true))
            throw new RuntimeException('Compte ou établissement restreint.');
        if ($global && $target['PROFILE'] !== 'admin') throw new RuntimeException('Responsable scolaire administrateur requis.');
        // Profile rows and their exception rows remain protected until commit.
        $pids = array_values(array_unique(array_filter([$actor['PROFILE_ID'],$target['PROFILE_ID']], static fn($v)=>$v !== null && (string)$v !== '')));
        foreach ($pids as $pid) {
            SchoolSafeAccessRows('SELECT id FROM user_profiles WHERE id=$1 FOR SHARE', [(int)$pid]);
            SchoolSafeAccessRows('SELECT profile_id FROM profile_exceptions WHERE profile_id=$1 FOR SHARE', [(int)$pid]);
        }
        $ceiling = SchoolSafeAccessRights($actor);
        if (!$global && (!SchoolSafeNativeCommandPermission('SchoolSafe/DelegationManage.php')
            || !SchoolSafeAccessRightsCovered($ceiling, ['Users/User.php'=>[true,true], 'SchoolSafe/DelegationManage.php'=>[true,true]])))
            throw new RuntimeException('Gestion des accès non autorisée.');
        $before = SchoolSafeAccessRights($target);
        if (!hash_equals(SchoolSafeAccessVersion($target,$before), $version)) throw new RuntimeException('Les accès ont changé. Rechargez avant de confirmer.');
        $owner = null;
        if ($target['PROFILE_ID'] !== null && (string)$target['PROFILE_ID'] !== '') {
            if ((string)$target['PROFILE_ID'] === '0') {
                $zero = SchoolSafeAccessRows('SELECT school_id FROM user_profiles WHERE id=0');
                if ($zero && $zero[1]['SCHOOL_ID'] !== null) $owner = (int)$zero[1]['SCHOOL_ID'];
            } else {
                $definition = SchoolSafeAccessRows('SELECT profile FROM user_profiles WHERE id=$1',[(int)$target['PROFILE_ID']]);
                if (!$definition || $definition[1]['PROFILE'] !== $target['PROFILE']) throw new RuntimeException('Famille du profil cible incohérente.');
                $owner = SchoolSafeProfileSchoolId((string)$target['PROFILE_ID']);
            }
        }
        if ($owner !== null && $owner !== $school) throw new RuntimeException('Profil d’une autre école ou inconnu.');
        $wanted = SchoolSafeAccessInput($selection, $target['PROFILE']);
        if (!$global && (!SchoolSafeAccessRightsCovered($ceiling,$wanted) || !SchoolSafeAccessRightsCovered($ceiling,$before)))
            throw new RuntimeException('Droit ou profil cible hors de votre autorité.');
        $reserved = ['Users/Profiles.php','Users/Exceptions.php',
            'Users/User.php&category_id=1&schools','Users/User.php&category_id=1&user_profile'];
        foreach ($reserved as $key) {
            if (!empty($before[$key][0]) || !empty($before[$key][1])) throw new RuntimeException('Profil réservé : aucune conversion automatique.');
        }
        // Administration may change only the selected catalog envelope, not the
        // target's other native permissions, accounting data or family links.
        $after = $before;
        foreach (SchoolSafeAccessCatalog() as $key => $definition) {
            if (in_array($target['PROFILE'],$definition['profiles'],true)) unset($after[$key]);
        }
        $after += $wanted; ksort($after);
        if (!$global && !SchoolSafeAccessRightsCovered($ceiling,$after)) throw new RuntimeException('Le profil cible dépasse votre autorité.');
        if ($before === $after) {
            SchoolSafeAccessQuery('COMMIT'); $started = false;
            return ['status'=>'unchanged','profile_id'=>(int)$target['PROFILE_ID']];
        }
        $title = 'Accès école '.$school.' / compte '.$targetId.' / '.bin2hex(random_bytes(6));
        $created = SchoolSafeAccessRows('INSERT INTO user_profiles(title,profile,school_id) VALUES($1,$2,$3) RETURNING id', [$title,$target['PROFILE'],$school]);
        $pid = (int)($created[1]['ID'] ?? 0);
        if ($pid < 1) throw new RuntimeException('Profil natif non enregistré.');
        foreach ($after as $key => $flags) SchoolSafeAccessQuery('INSERT INTO profile_exceptions(profile_id,modname,can_use,can_edit)
            VALUES($1,$2,$3,$4)', [$pid,$key,$flags[0]?'Y':'',$flags[1]?'Y':'']);
        SchoolSafeAccessQuery('UPDATE staff SET profile_id=$1 WHERE staff_id=$2 AND syear=$3', [$pid,$targetId,(int)UserSyear()]);
        $audit = json_encode(['staff_id'=>$targetId,'syear'=>(int)UserSyear(),
            'from_profile'=>$target['PROFILE_ID'],'to_profile'=>$pid,'before'=>$before,'after'=>$after], JSON_THROW_ON_ERROR);
        SchoolSafeAccessQuery('INSERT INTO schoolsafe_account_audit(person_key,school_id,action,reason,actor)
            VALUES($1,$2,$3,$4,$5)', [SchoolSafeAccountPerson($target['USERNAME']),$school,$global?'access_bootstrap':'access_assign',$audit,$actor['USERNAME']]);
        // Live state wins over an already prepared form, including at commit.
        if (SchoolSafeAccountBlocksWrite() || SchoolSafeAccountStateForSchool($actor['USERNAME'],$school) !== 'active')
            throw new RuntimeException('Autorité retirée.');
        SchoolSafeAccessQuery('COMMIT'); $started = false;
        return ['status'=>'saved','profile_id'=>$pid];
    } catch (Throwable $error) {
        if ($started) @pg_query($db_connection,'ROLLBACK');
        // Controlled application errors only; SQL/provider/credentials never surfaced.
        return ['status'=>'refused','error'=>$error instanceof RuntimeException || $error instanceof InvalidArgumentException
            ? $error->getMessage() : 'Gestion des accès indisponible.'];
    }
}
