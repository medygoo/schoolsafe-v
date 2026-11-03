<?php
/**
 * SchoolSafe L3 — route officielle des profils d'un établissement.
 *
 *   /ecoles/{id}/profils
 *
 * Réutilise STRICTEMENT : user_profiles, profile_exceptions, staff_exceptions,
 * SchoolSafeProfileDelegable, AllowUse/AllowEdit, gardes de périmètre scolaire.
 * Distingue compte (staff) / profil (user_profiles) / fonction (titre nommé :
 * Directeur, Comptable...) / permissions (profile_exceptions + staff_exceptions) /
 * délégation (StrictlyWithin) / école (SCHOOLS). L'attribution reste native
 * (Users/Profiles.php, Users/Exceptions.php, Users/User.php).
 * Un administrateur scolaire ne peut jamais s'accorder des permissions qu'il
 * ne possède pas (SchoolSafeProfileDelegable + écrans réservés refusés).
 */

header( 'Cache-Control: no-store' );
header( 'X-Content-Type-Options: nosniff' );

chdir( __DIR__ );

require_once __DIR__ . '/Warehouse.php';
require_once __DIR__ . '/ProgramFunctions/SchoolSafeSchoolAccessView.fnc.php';

$school_id = (int) ( $_GET['school_id'] ?? 0 );

function SchoolSafeProfilsDeny( $message )
{
	http_response_code( 403 );

	exit( '<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
		. '<title>SchoolSafe — Profils</title><body style="font-family:system-ui;padding:2rem">'
		. '<p role="alert">' . htmlspecialchars( $message, ENT_QUOTES, 'UTF-8' ) . '</p>'
		. '<p><a href="Modules.php?modname=misc/Portal.php">Retour au portail</a></p></body></html>' );
}

if ( ! User( 'STAFF_ID' ) )
{
	header( 'Location: index.php' );
	exit;
}

if ( User( 'PROFILE' ) !== 'admin' )
{
	SchoolSafeProfilsDeny( 'Profils réservés aux administrateurs.' );
}

if ( $school_id < 1
	|| ! DBGetOne( "SELECT ID FROM schools WHERE ID='" . $school_id . "' AND SYEAR='" . (int) UserSyear() . "'" ) )
{
	SchoolSafeProfilsDeny( 'Établissement introuvable pour cette année.' );
}

if ( ! SchoolSafeGlobalAdmin() && ! SchoolSafeSchoolAllowed( $school_id ) )
{
	SchoolSafeProfilsDeny( 'Vous ne gérez que les établissements qui vous sont attribués.' );
}

$school_title = DBGetOne( "SELECT TITLE FROM schools WHERE ID='" . $school_id . "'" );
$profiles = DBGet( "SELECT ID,TITLE,PROFILE FROM user_profiles"
    . ( SchoolSafeSchoolProfileColumnExists() ? " WHERE SCHOOL_ID IS NULL OR SCHOOL_ID='" . $school_id . "'" : '' )
    . " ORDER BY PROFILE,ID" );

// --- L3.1 : profils propres à l'école (user_profiles.school_id, 034) --------
// Réutilise le moteur natif (DBInsert + profile_exceptions), jamais de logique
// parallèle : chaque droit demandé doit exister à l'identique chez l'acteur.
$hub_message = '';
$hub_error = '';
$profile_context = null;
if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) === 'POST' && ( $_POST['access_action'] ?? '' ) === 'save' )
{
    $result = SchoolSafeAccessSubmit( $school_id, false, 'token' );
    $hub_error = $result['status'] === 'refused' ? $result['error'] : '';
    $hub_message = $result['status'] === 'saved' ? 'Accès personnels enregistrés ; autres comptes inchangés.'
        : ( $result['status'] === 'unchanged' ? 'Accès déjà identiques ; aucune modification.' : '' );
}

// Droits effectifs de l'acteur : profil nommé, sinon exceptions individuelles.
// Table MODNAME => [ use, edit ] : un droit demandé est couvert dès que
// chaque fanion Y est possédé (demander Use seul alors qu'on a Use+Edit est
// un sous-ensemble légitime, pas un dépassement).
function SchoolSafeHubActorRights()
{
	$rights = [];
	$pid = User( 'PROFILE_ID' );

	if ( $pid !== null && $pid !== false && (string) $pid !== '' )
	{
		$rows = DBGet( "SELECT MODNAME,CAN_USE,CAN_EDIT FROM profile_exceptions WHERE PROFILE_ID='" . $pid . "'" );
	}
	else
	{
		$rows = DBGet( "SELECT MODNAME,CAN_USE,CAN_EDIT FROM staff_exceptions WHERE USER_ID='" . (int) User( 'STAFF_ID' ) . "'" );
	}

	foreach ( (array) $rows as $row )
	{
		$mod = (string) $row['MODNAME'];
		$rights[$mod] = [
			$row['CAN_USE'] === 'Y',
			$row['CAN_EDIT'] === 'Y',
		];
	}

	return $rights;
}

// Le jeu demandé est-il couvert par l'acteur, sans élargissement ?
function SchoolSafeHubRightsCovered( $actor_rights, $wanted )
{
	foreach ( $wanted as $mod => $flags )
	{
		if ( empty( $actor_rights[$mod] ) ) return false;
		if ( $flags[0] && ! $actor_rights[$mod][0] ) return false;
		if ( $flags[1] && ! $actor_rights[$mod][1] ) return false;
	}

	return true;
}

// Compatibilité du contrôleur natif : un profil propre à l'école peut
// contenir tous les droits délégables, sans retrait artificiel d'un droit.
function SchoolSafeHubRightsNarrower( $actor_rights, $wanted )
{
	return SchoolSafeHubRightsCovered( $actor_rights, $wanted );
}

if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) === 'POST'
	&& isset( $_POST['ecole_profil_action'] ) )
{
	try
	{
		if ( ! SchoolSafeSchoolProfileColumnExists() )
		{
			throw new RuntimeException( 'Profils d’école indisponibles : migration 034 non appliquée sur cette base.' );
		}

		if ( empty( $_SESSION['token'] ) || ( $_POST['profile_confirm'] ?? '' ) !== 'Y'
            || ! is_string( $_POST['token'] ?? null )
			|| ! hash_equals( (string) ( $_SESSION['token'] ?? '' ), $_POST['token'] ) )
		{
			throw new RuntimeException( 'Requête expirée. Rechargez la page.' );
		}

		// Le hub peut viser une autre école que celle de la session : vérifier
		// aussi son état avant toute création, modification ou suppression.
		if ( SchoolSafeAccountBlocksWrite()
			|| in_array( SchoolSafeAccountStateForSchool( User( 'USERNAME' ), $school_id ),
				[ 'readonly', 'suspended', 'expired' ], true ) )
		{
			SchoolSafeProfilsDeny( 'Compte en lecture seule ou accès restreint : modification des profils interdite.' );
		}

		$action = (string) $_POST['ecole_profil_action'];
        $profile_context = SchoolSafeAccessProfileBegin( $school_id,
            $action === 'creer' ? 0 : (int) ( $_POST['profil_id'] ?? 0 ),
            is_string( $_POST['profile_version'] ?? null ) ? $_POST['profile_version'] : '' );
		$reserved_mods = [ 'Users/Profiles.php', 'Users/Exceptions.php',
			'Users/User.php&category_id=1&schools', 'Users/User.php&category_id=1&user_profile' ];

		if ( $action === 'creer' )
		{
			$title = trim( (string) ( $_POST['titre'] ?? '' ) );
			$base = (string) ( $_POST['base'] ?? '' );

			if ( $title === '' || mb_strlen( $title ) > 100 )
			{
				throw new RuntimeException( 'Nom de profil requis (100 caractères maximum).' );
			}

			if ( ! in_array( $base, [ 'teacher', 'admin' ], true ) )
			{
				// Famille parent refusée : jamais rattachée à une école.
				throw new RuntimeException( 'Base autorisée : enseignant ou administrateur.' );
			}

			if ( DBGetOne( "SELECT 1 FROM user_profiles WHERE UPPER(TITLE)=UPPER('" . DBEscapeString( $title ) . "')" ) )
			{
				throw new RuntimeException( 'Un profil porte déjà ce nom.' );
			}			$actor_rights = SchoolSafeHubActorRights();
			$wanted = [];

			foreach ( (array) ( $_POST['droit'] ?? [] ) as $mod => $use )
			{
				if ( ! $use || ! is_string( $mod ) || $mod === '' ) continue;

				$wanted[$mod] = [ true, ! empty( $_POST['edit'][ $mod ] ) ];
			}

			if ( ! $wanted )
			{
				throw new RuntimeException( 'Cochez au moins un droit que vous possédez.' );
			}

			if ( ! SchoolSafeHubRightsCovered( $actor_rights, $wanted ) )
			{
				throw new RuntimeException( 'Droit hors de vos pouvoirs (use/edit comparés séparément).' );
			}

			foreach ( $wanted as $mod => $flags )
			{
				if ( in_array( $mod, $reserved_mods, true ) && ! SchoolSafeGlobalAdmin() )
				{
					throw new RuntimeException( 'Écran réservé global : ' . $mod . '.' );
				}
			}

			// L'ensemble scolaire complet est autorisé, sans dépasser l'acteur.
			if ( ! SchoolSafeHubRightsNarrower( $actor_rights, $wanted ) )
			{
				throw new RuntimeException( 'Le profil dépasse vos droits délégables.' );
			}

			$new_id = DBInsert( 'user_profiles',
				[ 'TITLE' => $title, 'PROFILE' => $base, 'SCHOOL_ID' => $school_id ], 'id' );
            if ( (int) $new_id < 1 ) throw new RuntimeException( 'Profil natif non enregistré.' );

			foreach ( $wanted as $mod => $flags )
			{
				DBInsert( 'profile_exceptions',
					[ 'PROFILE_ID' => (int) $new_id, 'MODNAME' => $mod,
						'CAN_USE' => 'Y', 'CAN_EDIT' => $flags[1] ? 'Y' : '' ] );
			}

			if ( function_exists( 'sskcAudit' ) )
			{
				sskcAudit( 'school_profile_create', (int) $new_id, 'success' );
			}

			$hub_message = 'Profil « ' . $title . ' » créé pour l’école (n°' . (int) $new_id . ').';
		}
		elseif ( $action === 'droits' || $action === 'supprimer' )
		{
			$pid = (int) ( $_POST['profil_id'] ?? 0 );

			if ( SchoolSafeProfileSchoolId( $pid ) !== $school_id )
			{
				throw new RuntimeException( 'Profil global ou d’une autre école : intouchable ici.' );
			}

			if ( $action === 'supprimer' )
			{
                if ( $profile_context['holders'] ) throw new RuntimeException( 'Profil encore attribué : réattribuez les comptes avant suppression.' );
				// Aucun détenteur : ne jamais convertir silencieusement un compte en Custom.
				DBQuery( "DELETE FROM user_profiles WHERE ID='" . $pid . "';" );
				DBQuery( "DELETE FROM staff_exceptions WHERE USER_ID IN (SELECT STAFF_ID FROM staff WHERE PROFILE_ID='" . $pid . "');" );
				DBQuery( "DELETE FROM profile_exceptions WHERE PROFILE_ID='" . $pid . "';" );
				DBQuery( "INSERT INTO staff_exceptions (USER_ID,MODNAME,CAN_USE,CAN_EDIT)
					SELECT s.STAFF_ID,e.MODNAME,e.CAN_USE,e.CAN_EDIT
					FROM staff s,profile_exceptions e
					WHERE s.PROFILE_ID='" . $pid . "' AND s.PROFILE_ID=e.PROFILE_ID" );

				if ( function_exists( 'sskcAudit' ) )
				{
					sskcAudit( 'school_profile_delete', $pid, 'success' );
				}

				$hub_message = 'Profil supprimé (droits orphelins purgés, sémantique native).';
			}
			else
				{
				$actor_rights = SchoolSafeHubActorRights();
				$wanted = [];

				foreach ( (array) ( $_POST['droit'] ?? [] ) as $mod => $use )
				{
					if ( ! $use || ! is_string( $mod ) || $mod === '' ) continue;

					$wanted[$mod] = [ true, ! empty( $_POST['edit'][ $mod ] ) ];
				}

				if ( ! SchoolSafeHubRightsCovered( $actor_rights, $wanted ) )
				{
					throw new RuntimeException( 'Élargissement refusé (use/edit comparés séparément).' );
				}

				foreach ( $wanted as $mod => $flags )
				{
					if ( in_array( $mod, $reserved_mods, true ) && ! SchoolSafeGlobalAdmin() )
					{
						throw new RuntimeException( 'Élargissement refusé : ' . $mod . '.' );
					}
				}

				if ( $wanted && ! SchoolSafeHubRightsNarrower( $actor_rights, $wanted ) )
				{
					throw new RuntimeException( 'Le profil dépasse vos droits délégables.' );
				}

				DBQuery( "DELETE FROM profile_exceptions WHERE PROFILE_ID='" . $pid . "';" );

				foreach ( $wanted as $mod => $flags )
				{
					DBInsert( 'profile_exceptions',
						[ 'PROFILE_ID' => $pid, 'MODNAME' => $mod,
							'CAN_USE' => 'Y', 'CAN_EDIT' => $flags[1] ? 'Y' : '' ] );
				}

				if ( function_exists( 'sskcAudit' ) )
				{
					sskcAudit( 'school_profile_rights', $pid, 'success' );
				}

				$hub_message = $wanted ? 'Droits mis à jour.' : 'Droits révoqués.';
			}
		}
		else
		{
			throw new RuntimeException( 'Action inconnue.' );
		}
        SchoolSafeAccessProfileFinish( $profile_context, (int) ( $action === 'creer' ? $new_id : $pid ), $action );
        $profile_context = null;
	}
	catch ( Throwable $e )
	{
        if ( $profile_context !== null ) { global $db_connection; @pg_query( $db_connection, 'ROLLBACK' ); }
        $hub_message = '';
		$hub_error = $e->getMessage();
	}
}

?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Profils — <?php echo htmlspecialchars( (string) $school_title, ENT_QUOTES, 'UTF-8' ); ?> | SchoolSafe</title>
<style>
body{font-family:system-ui,sans-serif;margin:0;background:#f4f7fa;color:#172d40}
main{max-width:56rem;margin:0 auto;padding:1.25rem}
nav.top{background:#0b3b7a;color:#fff;padding:.6rem 1.25rem}
nav.top a{color:#fff;margin-right:1rem}
.card{background:#fff;border-radius:.75rem;padding:1.25rem;margin:1rem 0}
table{border-collapse:collapse;width:100%}
th,td{border:1px solid #dfe7ef;padding:.4rem;text-align:left;font-size:.9rem}
small{color:#456}
.badge{display:inline-block;padding:.1rem .5rem;border-radius:1rem;background:#e8eef7;font-size:.8rem}
.badge.ok{background:#e8f5e9}
.badge.no{background:#fdecea}
.ss-preview{margin:.5rem 0;padding:.5rem .75rem;background:#f6f9fc;border-radius:.5rem}.ss-preview summary{cursor:pointer;color:#0b3b7a}
tr.ss-task-group th{background:#eef3f9;color:#0b3b7a;font-size:.85rem;text-transform:uppercase;letter-spacing:.03em}
</style>
</head>
<body>
<nav class="top" aria-label="Navigation SchoolSafe">
<a href="Modules.php?modname=misc/Portal.php">Portail</a>
<a href="/ecoles/<?php echo $school_id; ?>/utilisateurs">Utilisateurs</a>
<a href="/ecoles/<?php echo $school_id; ?>/utilisateurs/nouveau">Nouvel utilisateur</a>
<a href="/ecoles/<?php echo $school_id; ?>/profils">Profils</a>
<a href="/plateforme/prodeli">PRODELI</a>
</nav>
<main>
<h1>Profils — <?php echo htmlspecialchars( (string) $school_title, ENT_QUOTES, 'UTF-8' ); ?></h1>
<?php if ( $hub_message !== '' ) : ?>
<p role="status" style="background:#e8f5e9;padding:.6rem;border-radius:.5rem"><?php echo htmlspecialchars( $hub_message, ENT_QUOTES, 'UTF-8' ); ?></p>
<?php endif; ?>
<?php if ( $hub_error !== '' ) : ?>
<p role="alert" style="background:#fdecea;padding:.6rem;border-radius:.5rem"><?php echo htmlspecialchars( $hub_error, ENT_QUOTES, 'UTF-8' ); ?></p>
<?php endif; ?>
<p>Créez les profils de votre école (Économat, Secrétariat, Surveillant, Cantine…), choisissez les tâches de chacun,
puis attribuez-les à votre personnel. Vous ne pouvez donner que des tâches que vous avez vous-même.</p>

<section class="card" aria-labelledby="t-matrix">
<h2 id="t-matrix">Profils existants et ce que vous pouvez attribuer</h2>
<table>
<thead><tr><th>Profil</th><th>Base</th><th>Attribuable par vous</th></tr></thead>
<tbody>
<?php foreach ( (array) $profiles as $p ) : if ( strpos( (string) $p['TITLE'], 'Obsolète' ) === 0 ) continue; ?>
<?php
$delegable = SchoolSafeProfileDelegable( (string) $p['ID'], $p['PROFILE'] );
$reserved_hit = false;

if ( $delegable )
{
	$rows = DBGet( "SELECT MODNAME,CAN_USE,CAN_EDIT FROM profile_exceptions WHERE PROFILE_ID='" . (int) $p['ID'] . "'" );

	foreach ( (array) $rows as $row )
	{
		if ( in_array( $row['MODNAME'], [ 'Users/Profiles.php', 'Users/Exceptions.php',
			'Users/User.php&category_id=1&schools', 'Users/User.php&category_id=1&user_profile' ], true )
			&& ( $row['CAN_USE'] === 'Y' || $row['CAN_EDIT'] === 'Y' ) )
		{
			$reserved_hit = true;
			break;
		}
	}
}
?>
<tr>
<td><?php echo htmlspecialchars( $p['TITLE'], ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo htmlspecialchars( [ 'admin' => 'Administration', 'teacher' => 'Enseignant', 'parent' => 'Parent', 'student' => 'Élève' ][ $p['PROFILE'] ] ?? $p['PROFILE'], ENT_QUOTES, 'UTF-8' ); ?></td>
<td><span class="badge <?php echo $delegable && ! $reserved_hit ? 'ok' : 'no'; ?>">
<?php echo $delegable && ! $reserved_hit ? 'Oui' : 'Non — droits supérieurs aux vôtres'; ?></span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<p><small>Sécurité : un profil ne peut jamais recevoir plus de droits que les vôtres ; le serveur refuse tout élargissement.</small></p>
</section>

<?php
if ( SchoolSafeNativeCommandPermission( 'SchoolSafe/DelegationRead.php' ) )
    SchoolSafeAccessRender( $school_id, (int) UserSyear(), (int) ( $_POST['access_staff'] ?? $_GET['access_staff'] ?? 0 ),
        false, '/ecoles/' . $school_id . '/profils', 'token' );
$ecole_profiles = SchoolSafeSchoolProfileColumnExists()
	? DBGet( "SELECT ID,TITLE,PROFILE FROM user_profiles WHERE SCHOOL_ID='" . $school_id . "' AND TITLE NOT LIKE 'Obsolète%' ORDER BY ID" )
	: [];
require_once 'ProgramFunctions/SchoolSafeTaskCatalog.fnc.php';
$actor_rights_list = SchoolSafeHubActorRights();
$actor_mods = array_keys( $actor_rights_list );
sort( $actor_mods );
?>
<section class="card" aria-labelledby="t-ecole">
<h2 id="t-ecole">Les profils de votre école</h2>
<?php if ( ! $ecole_profiles ) : ?>
<p><small>Aucun profil propre à cette école pour l’instant.</small></p>
<?php endif; ?>
<?php foreach ( (array) $ecole_profiles as $ep ) : ?>
<?php $ep_rights = DBGet( "SELECT MODNAME,CAN_USE,CAN_EDIT FROM profile_exceptions WHERE PROFILE_ID='" . (int) $ep['ID'] . "'", [], [ 'MODNAME' ] ); ?>
<?php $ep_holders = DBGet( "SELECT USERNAME,SCHOOLS FROM staff WHERE PROFILE_ID='" . (int) $ep['ID'] . "' AND SYEAR='" . (int) UserSyear() . "' ORDER BY USERNAME" ); ?>
<h3><?php echo htmlspecialchars( $ep['TITLE'], ENT_QUOTES, 'UTF-8' ); ?> <small>(base <?php echo $ep['PROFILE'] === 'admin' ? 'Administration' : 'Enseignant'; ?>)</small></h3>
<p><small>Détenteurs : <?php echo $ep_holders ? htmlspecialchars( implode( ', ', array_map( function ( $h ) { return $h['USERNAME'] . ' ' . ( $h['SCHOOLS'] ?? '' ); }, (array) $ep_holders ) ), ENT_QUOTES, 'UTF-8' ) : 'aucun'; ?></small></p>
<?php echo SchoolSafeTaskPreview( (array) $ep_rights ); ?>
<form method="post" action="/ecoles/<?php echo $school_id; ?>/profils">
<input type="hidden" name="token" value="<?php echo htmlspecialchars( (string) ( $_SESSION['token'] ?? '' ), ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="ecole_profil_action" value="droits">
<input type="hidden" name="profile_version" value="<?php echo SchoolSafeAccessProfileVersion( (int) $ep['ID'] ); ?>">
<p><label><input type="checkbox" name="profile_confirm" value="Y" required> Je confirme la modification pour tous les détenteurs listés.</label></p>
<input type="hidden" name="profil_id" value="<?php echo (int) $ep['ID']; ?>">
<table>
<thead><tr><th>Tâche</th><th>Voir</th><th>Modifier</th></tr></thead>
<tbody>
<?php echo SchoolSafeTaskRows( $actor_mods,
	function( $mod ) use ( $ep_rights ) { return ! empty( $ep_rights[ $mod ] ); },
	function( $mod ) use ( $ep_rights ) { return ( $ep_rights[ $mod ][1]['CAN_EDIT'] ?? '' ) === 'Y'; } ); ?>
</tbody>
</table>
<p><input type="submit" value="Enregistrer les tâches"> <small>Seules vos propres tâches sont proposées.</small></p>
</form>
<form method="post" action="/ecoles/<?php echo $school_id; ?>/profils" onsubmit="return confirm('Supprimer ce profil ? Ses détenteurs perdront ces droits.');">
<input type="hidden" name="token" value="<?php echo htmlspecialchars( (string) ( $_SESSION['token'] ?? '' ), ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="ecole_profil_action" value="supprimer">
<input type="hidden" name="profile_version" value="<?php echo SchoolSafeAccessProfileVersion( (int) $ep['ID'] ); ?>">
<p><label><input type="checkbox" name="profile_confirm" value="Y" required> Je confirme la suppression de ce profil sans détenteur.</label></p>
<input type="hidden" name="profil_id" value="<?php echo (int) $ep['ID']; ?>">
<p><input type="submit" value="Supprimer le profil"></p>
</form>
<?php endforeach; ?>
<h3>Créer un profil pour votre école</h3>
<form method="post" action="/ecoles/<?php echo $school_id; ?>/profils">
<input type="hidden" name="token" value="<?php echo htmlspecialchars( (string) ( $_SESSION['token'] ?? '' ), ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="ecole_profil_action" value="creer">
<p><label><input type="checkbox" name="profile_confirm" value="Y" required> Je confirme la création de ce profil pour cette école.</label></p>
<p><label>Nom du profil <input type="text" name="titre" maxlength="100" required list="ss-role-ideas" placeholder="ex. Économat"></label>
<datalist id="ss-role-ideas"><option value="Économat"><option value="Secrétariat"><option value="Surveillant"><option value="Cantine"><option value="Direction pédagogique"><option value="Accueil"></datalist>
<label>Base <select name="base"><option value="admin">Administration (bureau, finances, secrétariat)</option><option value="teacher">Enseignant (classes, appel, notes)</option></select></label></p>
<table>
<thead><tr><th>Tâche</th><th>Voir</th><th>Modifier</th></tr></thead>
<tbody>
<?php echo SchoolSafeTaskRows( $actor_mods, function() { return false; }, function() { return false; } ); ?>
</tbody>
</table>
<p><input type="submit" class="button-primary" value="Créer le profil"> <small>Cochez les tâches de ce profil ; vous pourrez les modifier ensuite.</small></p>
</form>
</section>

<section class="card" aria-labelledby="t-journal">
<h2 id="t-journal">Journal des attributions</h2>
<p><small>Qui a attribué quoi, et quand (20 derniers).</small></p>
<?php echo SchoolSafeTaskJournal( $school_id, array_map( function ( $ep ) { return (int) $ep['ID']; }, (array) DBGet( "SELECT ID FROM user_profiles WHERE SCHOOL_ID='" . $school_id . "'" ) ) ); ?>
</section>

<section class="card" aria-labelledby="t-exc">
<h2 id="t-exc">Droits personnalisés d’un compte (consultation)</h2>
<p><small>Personnalisations par compte : modifiables uniquement via l’écran natif, selon les mêmes gardes.</small></p>
<?php
$exc = DBGet( "SELECT e.USER_ID,e.MODNAME,e.CAN_USE,e.CAN_EDIT,s.USERNAME
	FROM staff_exceptions e JOIN staff s ON s.STAFF_ID=e.USER_ID AND s.SYEAR='" . (int) UserSyear() . "'
	WHERE (',' || TRIM(BOTH ',' FROM COALESCE(s.SCHOOLS,'')) || ',' LIKE '%," . $school_id . ",%')
	ORDER BY s.USERNAME,e.MODNAME LIMIT 200" );
?>
<table>
<thead><tr><th>Compte</th><th>Tâche</th><th>Voir</th><th>Modifier</th></tr></thead>
<tbody>
<?php foreach ( (array) $exc as $row ) : ?>
<tr><td><?php echo htmlspecialchars( $row['USERNAME'], ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo htmlspecialchars( SchoolSafeTaskLabel( $row['MODNAME'] ), ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo htmlspecialchars( $row['CAN_USE'], ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo htmlspecialchars( $row['CAN_EDIT'], ENT_QUOTES, 'UTF-8' ); ?></td></tr>
<?php endforeach; ?>
</tbody>
</table>
<p>Pour modifier un compte de cette école, utilisez les accès personnels ci-dessus ; aucune exception ignorée par un profil nommé n’est ajoutée.</p>
</section>
</main>
</body>
</html>
