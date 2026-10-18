<?php
/**
 * SchoolSafe — Console d'administration des comptes (intégrée)
 *
 * Page de gestion des comptes administrateurs, reservee aux administrateurs
 * GLOBAUX de la plateforme (staff.SCHOOLS vide = toutes les ecoles).
 * Reutilise strictement les mecanismes natifs :
 *  - encrypt_password() pour le hachage $6$ ;
 *  - meme INSERT staff que modules/Users/User.php (PROFILE_ID=1) ;
 *  - LAST_LOGIN NULL + FORCE_PASSWORD_CHANGE_ON_FIRST_LOGIN =>
 *    changement de mot de passe FORCE a la premiere connexion ;
 *  - etat de compte natif SchoolSafe : active / readonly / suspended / expired
 *    (table schoolsafe_account_state, voir schoolsafe-remediation/014) ;
 *  - revocation des sessions ouvertes (session_epoch) ;
 *  - suspension historique par PROFILE 'none' toujours reconnue a l'affichage.
 *
 * Perimetre : administrateur de la plateforme = toutes les ecoles ; directeur
 * (administrateur delegue) = uniquement les personnes de ses ecoles.
 *
 * Session Rosario requise + jeton CSRF de session sur chaque action.
 * Le mot de passe temporaire est affiche UNE SEULE FOIS (flash de session).
 * Lot additif : aucune modification du coeur Rosario.
 */

require_once __DIR__ . '/Warehouse.php';

header( 'Content-Type: text/html; charset=utf-8' );
header( 'X-Content-Type-Options: nosniff' );

if ( ! User( 'STAFF_ID' )
	|| User( 'PROFILE' ) !== 'admin' )
{
	http_response_code( 403 );
	exit( '<!doctype html><html lang="fr"><meta charset="utf-8"><title>SchoolSafe</title>'
		. '<body style="font-family:system-ui;background:#f4f7fa;color:#172d40;padding:48px;text-align:center">'
		. '<h2>Connexion requise</h2><p><a href="index.php" style="color:#126cac">Aller a la connexion</a></p></body></html>' );
}

/**
 * SchoolSafe — perimetre de la console.
 *
 * Administrateur de la plateforme (aucune ecole assignee) : toutes les ecoles.
 * Directeur (administrateur delegue) : uniquement les personnes de ses ecoles.
 * Perimetre illisible : refus (echec ferme).
 */
$schoolsafe_global_admin = SchoolSafeGlobalAdmin();

$schoolsafe_scope = $schoolsafe_global_admin ? null : SchoolSafeScopeParse( User( 'SCHOOLS' ) );

if ( ! $schoolsafe_global_admin
	&& ( ! is_array( $schoolsafe_scope ) || ! $schoolsafe_scope ) )
{
	http_response_code( 403 );
	exit( '<!doctype html><html lang="fr"><meta charset="utf-8"><title>SchoolSafe</title>'
		. '<body style="font-family:system-ui;background:#f4f7fa;color:#172d40;padding:48px;text-align:center">'
		. '<h2>Perimetre illisible : acces refuse</h2>'
		. '<p><a href="Modules.php?modname=misc/Portal.php" style="color:#126cac">Retour au portail</a></p></body></html>' );
}

/**
 * Restriction SQL : un directeur ne voit que ses ecoles, jamais les comptes
 * globaux (proprietaires de la plateforme).
 */
$schoolsafe_scope_sql = '';

if ( ! $schoolsafe_global_admin )
{
	$schoolsafe_like = implode( ",' IN s.SCHOOLS)>0 OR position(',",
		array_map( 'intval', $schoolsafe_scope ) );

	$schoolsafe_scope_sql = " AND s.SCHOOLS IS NOT NULL AND (position('," . $schoolsafe_like . ",' IN s.SCHOOLS)>0)";
}

/**
 * Compte cible autorise ? Renvoie la ligne staff ou false.
 */
function SchoolSafeConsoleTarget( $staff_id, $syear, $scoped )
{
	$target = DBGet( DBQuery( "SELECT STAFF_ID,USERNAME,PROFILE,SCHOOLS
		FROM staff WHERE STAFF_ID='" . (int) $staff_id . "'
		AND SYEAR='" . (int) $syear . "'" ) );

	if ( ! $target )
	{
		return false;
	}

	if ( $scoped && ! SchoolSafeStaffWithinScope( (int) $staff_id ) )
	{
		return false;
	}

	return $target[1];
}

/**
 * Ecole sur laquelle porte le changement d'etat.
 *
 * Plateforme : 0 (toutes les ecoles de la personne).
 * Directeur : l'ecole du compte qui appartient a son perimetre, sinon la sienne.
 */
function SchoolSafeConsoleSchoolsFor( $target, $global_admin, $scope )
{
	if ( $global_admin )
	{
		return 0;
	}

	$target_schools = array_filter( array_map( 'intval', explode( ',', (string) $target['SCHOOLS'] ) ) );

	$in_scope = array_values( array_intersect( $target_schools, (array) $scope ) );

	return $in_scope ? (int) $in_scope[0] : (int) $scope[0];
}

$syear = (int) UserSyear();

/**
 * Mot de passe temporaire lisible : 3 groupes de 4, sans caracteres ambigus.
 */
function SchoolSafeConsoleTempPassword()
{
	$alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
	$groups = [];

	for ( $g = 0; $g < 3; $g++ )
	{
		$group = '';

		for ( $i = 0; $i < 4; $i++ )
		{
			$group .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
		}

		$groups[] = $group;
	}

	return implode( '-', $groups );
}

$flash = $_SESSION['schoolsafe_console_flash'] ?? null;
unset( $_SESSION['schoolsafe_console_flash'] );

$error = '';

// --- Traitements (POST + CSRF de session) ---
if ( $_SERVER['REQUEST_METHOD'] === 'POST' )
{
	$csrf_ok = isset( $_POST['console_token'], $_SESSION['token'] )
		&& is_string( $_POST['console_token'] )
		&& hash_equals( (string) $_SESSION['token'], (string) $_POST['console_token'] );

	if ( SchoolSafeAccountBlocksWrite() )
	{
		// Meme regle que AllowEdit() : un compte en lecture seule n'ecrit rien,
		// y compris depuis la console d'administration.
		$error = 'Votre compte est en lecture seule : aucune modification possible.';
	}
	elseif ( ! $csrf_ok )
	{
		$error = 'Session expiree, veuillez reessayer.';
	}
	else
	{
		$action = $_POST['console_action'] ?? '';

		if ( $action === 'create' && ! $schoolsafe_global_admin )
		{
			$error = "Seul le proprietaire de la plateforme peut creer un administrateur de plateforme. Creez vos comptes dans l'ecran Utilisateurs.";
		}
		elseif ( $action === 'create' )
		{
			$first_name = trim( (string) ( $_POST['first_name'] ?? '' ) );
			$last_name = trim( (string) ( $_POST['last_name'] ?? '' ) );
			$username = trim( (string) ( $_POST['username'] ?? '' ) );
			$email = trim( (string) ( $_POST['email'] ?? '' ) );
			$school_ids = array_filter( array_map( 'intval', (array) ( $_POST['school_ids'] ?? [] ) ) );

			if ( $first_name === '' || mb_strlen( $first_name ) > 50
				|| $last_name === '' || mb_strlen( $last_name ) > 50 )
			{
				$error = 'Prenom et nom obligatoires (50 caracteres max).';
			}
			elseif ( $username === '' || mb_strlen( $username ) > 100
				|| preg_match( '/[\x00-\x1f\x7f]/', $username ) )
			{
				$error = "Nom d'utilisateur invalide.";
			}
			elseif ( $email !== '' && ! filter_var( $email, FILTER_VALIDATE_EMAIL ) )
			{
				$error = 'Email invalide.';
			}
			elseif ( count( $school_ids ) === 0 )
			{
				$error = 'Choisissez au moins une ecole.';
			}
			else
			{
				$exists = DBGet( DBQuery( "SELECT 1 FROM staff
					WHERE LOWER(USERNAME)='" . DBEscapeString( mb_strtolower( $username ) ) . "'
					AND SYEAR='" . $syear . "'
					UNION
					SELECT 1 FROM students
					WHERE LOWER(USERNAME)='" . DBEscapeString( mb_strtolower( $username ) ) . "'" ) );

				if ( $exists )
				{
					$error = "Ce nom d'utilisateur est deja pris.";
				}
				else
				{
					$valid = DBGet( DBQuery( "SELECT ID FROM schools
						WHERE SYEAR='" . $syear . "' AND ID IN (" . implode( ',', $school_ids ) . ")" ) );

					if ( count( $valid ) !== count( $school_ids ) )
					{
						$error = 'Ecole inexistante.';
					}
					else
					{
						$temp_password = SchoolSafeConsoleTempPassword();

						$staff_id = DBInsert( 'staff', [
							'SYEAR' => $syear,
							'FIRST_NAME' => $first_name,
							'LAST_NAME' => $last_name,
							'USERNAME' => $username,
							'PASSWORD' => encrypt_password( $temp_password ),
							'EMAIL' => $email,
							'PROFILE' => 'admin',
							'PROFILE_ID' => 1,
							'SCHOOLS' => ',' . implode( ',', $school_ids ) . ',',
						], 'id' );

						$_SESSION['schoolsafe_console_flash'] = [
							'type' => 'created',
							'username' => $username,
							'temp' => $temp_password,
						];

						header( 'Location: admin-console.php' );
						exit;
					}
				}
			}
		}
		elseif ( $action === 'reset' || $action === 'revoke' || $action === 'state'
			|| $action === 'suspend' || $action === 'activate' )
		{
			$staff_id = (int) ( $_POST['staff_id'] ?? 0 );

			$target = SchoolSafeConsoleTarget( $staff_id, $syear, ! $schoolsafe_global_admin );

			if ( ! $target )
			{
				$error = 'Compte introuvable ou hors de votre perimetre.';
			}
			elseif ( (int) $target['STAFF_ID'] === (int) User( 'STAFF_ID' )
				&& $action !== 'reset' )
			{
				$error = 'Vous ne pouvez pas modifier l\'acces de votre propre compte.';
			}
			elseif ( $action === 'reset' )
			{
				$temp_password = SchoolSafeConsoleTempPassword();

				DBQuery( "UPDATE staff
					SET PASSWORD='" . DBEscapeString( encrypt_password( $temp_password ) ) . "',
					LAST_LOGIN=NULL,FAILED_LOGIN=0
					WHERE STAFF_ID='" . $staff_id . "' AND SYEAR='" . $syear . "'" );

				// Un nouveau mot de passe temporaire coupe les sessions ouvertes.
				SchoolSafeAccountRevoke( $target['USERNAME'],
					SchoolSafeConsoleSchoolsFor( $target, $schoolsafe_global_admin, $schoolsafe_scope ) );

				$_SESSION['schoolsafe_console_flash'] = [
					'type' => 'created',
					'username' => $target['USERNAME'],
					'temp' => $temp_password,
				];

				header( 'Location: admin-console.php' );
				exit;
			}
			elseif ( $action === 'revoke' )
			{
				SchoolSafeAccountRevoke( $target['USERNAME'],
					SchoolSafeConsoleSchoolsFor( $target, $schoolsafe_global_admin, $schoolsafe_scope ) );

				header( 'Location: admin-console.php' );
				exit;
			}
			else
			{
				// Etat demande : 'state' explicite, ou anciens libelles suspend /
				// activate conserves (le PROFILE natif n'est JAMAIS modifie ici).
				$requested = (string) ( $_POST['state'] ?? '' );

				if ( $action === 'suspend' )
				{
					$requested = 'suspended';
				}
				elseif ( $action === 'activate' )
				{
					$requested = 'active';
				}

				$reason = trim( (string) ( $_POST['reason'] ?? '' ) );

				$until = trim( (string) ( $_POST['until'] ?? '' ) );

				if ( ! in_array( $requested, [ 'active', 'readonly', 'suspended', 'expired' ], true ) )
				{
					$error = 'Etat inconnu.';
				}
				elseif ( $until !== '' && ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $until ) )
				{
					$error = 'Date de fin invalide (AAAA-MM-JJ).';
				}
				elseif ( $requested === 'active' && $target['PROFILE'] === 'none' )
				{
					$error = "Ce compte a ete ferme par le mecanisme natif (profil No Access). Retablissez son profil dans l'ecran Utilisateurs : un etat ne peut pas inventer un profil.";
				}
				elseif ( ! SchoolSafeAccountSet( $target['USERNAME'],
					SchoolSafeConsoleSchoolsFor( $target, $schoolsafe_global_admin, $schoolsafe_scope ),
					$requested, $reason ?: null, $until ?: null ) )
				{
					$error = 'Etat non enregistre (migration 014 appliquee ?).';
				}
				else
				{
					header( 'Location: admin-console.php' );
					exit;
				}
			}
		}
		elseif ( $action === 'school_state' )
		{
			// Etat d'un ETABLISSEMENT entier : reserve au proprietaire de la
			// plateforme. Rien n'est supprime, seul l'accès est coupe.
			if ( ! $schoolsafe_global_admin )
			{
				$error = "Seul le proprietaire de la plateforme peut suspendre, desactiver ou reactiver un etablissement.";
			}
			else
			{
				$school_id = (int) ( $_POST['school_id'] ?? 0 );
				$requested = (string) ( $_POST['school_state'] ?? '' );
				$reason = trim( (string) ( $_POST['school_reason'] ?? '' ) );
				$until = trim( (string) ( $_POST['school_until'] ?? '' ) );

				if ( ! in_array( $requested, [ 'active', 'suspended', 'inactive' ], true ) )
				{
					$error = "Etat d'etablissement inconnu.";
				}
				elseif ( $until !== '' && ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $until ) )
				{
					$error = 'Date de fin invalide (AAAA-MM-JJ).';
				}
				elseif ( ! DBGetOne( "SELECT 1 FROM schools
					WHERE SYEAR='" . $syear . "' AND ID='" . $school_id . "'" ) )
				{
					$error = 'Etablissement introuvable.';
				}
				elseif ( ! SchoolSafeSchoolStateSet( $school_id, $requested, $reason ?: null, $until ?: null ) )
				{
					$error = "Etat d'etablissement non enregistre (migration 016 appliquee ?).";
				}
				else
				{
					header( 'Location: admin-console.php' );
					exit;
				}
			}
		}
	}
}

// --- Donnees ---
// Tous les comptes du perimetre : le directeur gere ses enseignants, parents,
// surveillants et comptables, pas seulement les administrateurs.
$accounts = DBGet( DBQuery( "SELECT s.STAFF_ID,s.USERNAME,s.FIRST_NAME,s.LAST_NAME,s.EMAIL,
	s.PROFILE,s.PROFILE_ID,s.SCHOOLS,s.LAST_LOGIN
	FROM staff s
	WHERE s.SYEAR='" . $syear . "'" . $schoolsafe_scope_sql . "
	ORDER BY s.LAST_NAME,s.FIRST_NAME" ) );

/**
 * Etat effectif et libelle de chaque compte.
 */
function SchoolSafeConsoleState( $account )
{
	$schools = array_filter( array_map( 'intval', explode( ',', (string) $account['SCHOOLS'] ) ) );

	$state = function_exists( 'SchoolSafeAccountEffective' )
		? SchoolSafeAccountEffective( $account['USERNAME'], $schools ?: [ 0 ] )
		: 'active';

	if ( $account['PROFILE'] === 'none' && $state === 'active' )
	{
		// Suspension historique (profil No Access).
		$state = 'suspended';
	}

	return $state;
}

/**
 * Libelle metier lisible : proprietaire, directeur, enseignant, parent...
 */
function SchoolSafeConsoleProfileLabel( $account )
{
	$profile = (string) $account['PROFILE'];

	if ( $profile === 'none' )
	{
		return 'No Access (ferme) — a retablir dans Utilisateurs';
	}

	if ( ! empty( $account['PROFILE_ID'] ) )
	{
		$title = DBGetOne( "SELECT TITLE FROM user_profiles
			WHERE ID='" . (int) $account['PROFILE_ID'] . "'" );

		if ( $title )
		{
			return $title;
		}
	}

	$labels = [
		'admin' => trim( (string) $account['SCHOOLS'] ) === ''
			? 'Proprietaire de la plateforme'
			: 'Directeur',
		'teacher' => 'Enseignant',
		'parent' => 'Parent',
		'student' => 'Eleve',
	];

	return $labels[$profile] ?? $profile;
}

function SchoolSafeConsoleStateLabel( $state )
{
	$labels = [
		'active' => 'Actif',
		'readonly' => 'Lecture seule',
		'suspended' => 'Suspendu',
		'expired' => 'Expire',
	];

	return $labels[$state] ?? $state;
}

/**
 * Libelle de l'etat d'un etablissement.
 */
function SchoolSafeConsoleSchoolStateLabel( $state )
{
	$labels = [
		'active' => 'Actif',
		'suspended' => 'Suspendu',
		'inactive' => 'Desactive',
	];

	return $labels[$state] ?? $state;
}

$schools = $schoolsafe_global_admin
	? DBGet( DBQuery( "SELECT ID,TITLE FROM schools
		WHERE SYEAR='" . $syear . "' ORDER BY TITLE" ) )
	: DBGet( DBQuery( "SELECT ID,TITLE FROM schools
		WHERE SYEAR='" . $syear . "' AND ID IN (" . implode( ',', array_map( 'intval', $schoolsafe_scope ) ) . ")
		ORDER BY TITLE" ) );

?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width">
<title>Console d'administration | SchoolSafe</title>
<style>
body{margin:0;background:#f4f7fa;color:#172d40;font:16px/1.6 system-ui,sans-serif}
main{max-width:1100px;margin:auto;padding:32px 20px}
a{color:#126cac}
h1{font-size:26px;margin:0 0 4px}
.sub{color:#5b6b7b;margin:0 0 28px}
.card{background:#fff;border:1px solid #dbe4ec;border-radius:10px;padding:20px 22px;margin-bottom:24px}
table.list{width:100%;border-collapse:collapse;font-size:15px}
table.list th{background:#eef3f7;text-align:left;padding:9px 10px;border-bottom:2px solid #dbe4ec}
table.list td{padding:9px 10px;border-bottom:1px solid #e8eef3}
.badge{display:inline-block;padding:2px 10px;border-radius:12px;font-size:13px}
.badge.on{background:#e3f4e8;color:#1d7a3e}
.badge.off{background:#fbe6e4;color:#b3261e}
.badge.ro{background:#fff3d6;color:#8a6100}
.list select,.list input[type=text]{width:auto;padding:5px 6px;font-size:14px;margin-right:4px}
.list button{padding:6px 10px;font-size:14px}
button{background:#126cac;color:#fff;border:0;border-radius:7px;padding:8px 14px;font-size:15px;cursor:pointer}
button.secondary{background:#eef3f7;color:#172d40;border:1px solid #c8d6e0}
button.danger{background:#b3261e}
label.fl{display:block;font-weight:600;margin:10px 0 4px;font-size:14px}
input[type=text],input[type=email]{width:100%;max-width:420px;padding:9px 10px;border:1px solid #c8d6e0;border-radius:7px;font-size:15px;box-sizing:border-box}
.inline{display:flex;gap:16px;flex-wrap:wrap}
.inline>div{flex:1;min-width:200px}
.schools{display:flex;gap:14px;flex-wrap:wrap;margin-top:6px}
.schools label{font-weight:400;font-size:15px}
.flash{background:#fff8e6;border:1px solid #e6c96a;border-radius:10px;padding:18px 20px;margin-bottom:24px}
.flash code{font-size:20px;background:#172d40;color:#ffd66b;padding:4px 12px;border-radius:6px;display:inline-block;margin-top:6px}
.err{background:#fbe6e4;border:1px solid #e0a9a5;color:#8c1d18;border-radius:10px;padding:14px 18px;margin-bottom:24px}
.top{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
table.schools-list form.school-action{display:block;margin:0 0 6px}
table.schools-list form.school-action:last-child{margin-bottom:0}
table.schools-list input[type=text]{display:block;width:100%;max-width:220px;padding:5px 6px;font-size:14px;margin:0 0 4px}
table.schools-list button{padding:6px 12px;font-size:14px}
.mut{color:#5b6b7b;font-size:14px}
@media(max-width:760px){.inline{flex-direction:column}.inline>div{min-width:100%}}
</style>
</head>
<body>
<main>
	<div class="top">
		<div>
			<h1>SchoolSafe — Console d'administration</h1>
			<p class="sub">Comptes administrateurs — annee scolaire <?php echo (int) $syear; ?></p>
		</div>
		<a href="Modules.php?modname=misc/Portal.php">&larr; Portail SchoolSafe</a>
	</div>

	<?php
	if ( $flash && $flash['type'] === 'created' )
	{
		echo '<div class="flash"><strong>Compte pret :</strong> ' . htmlspecialchars( $flash['username'], ENT_QUOTES, 'UTF-8' )
			. '<br>Mot de passe temporaire (affiche une seule fois, a communiquer maintenant) :<br>'
			. '<code>' . htmlspecialchars( $flash['temp'], ENT_QUOTES, 'UTF-8' ) . '</code>'
			. '<p class="mut">A sa premiere connexion, cet administrateur sera oblige de choisir son propre mot de passe.</p></div>';
	}

	if ( $error )
	{
		echo '<div class="err">' . htmlspecialchars( $error, ENT_QUOTES, 'UTF-8' ) . '</div>';
	}
	?>

	<div class="card">
		<h2 style="font-size:19px;margin-top:0">Comptes et acces<?php
			echo $schoolsafe_global_admin
				? ' — plateforme entiere'
				: ' — vos ecoles uniquement';
		?></h2>
		<p class="mut">Quatre etats : <strong>Actif</strong> (acces normal), <strong>Lecture seule</strong> (consulte, n'ecrit plus), <strong>Suspendu</strong> (connexion refusee, donnees conservees), <strong>Expire</strong> (fin de validite atteinte). Une date de fin optionnelle : avec <em>Actif</em> elle expire le compte ce jour-la, avec <em>Suspendu</em> ou <em>Lecture seule</em> elle retablit l'acces a cette date.</p>
		<div style="overflow-x:auto">
		<table class="list">
			<tr><th>ID</th><th>Utilisateur</th><th>Nom</th><th>Profil</th><th>Etat</th><th>Derniere connexion</th><th>Actions</th></tr>
			<?php
			foreach ( $accounts as $account )
			{
				$state = SchoolSafeConsoleState( $account );

				$is_self = (int) $account['STAFF_ID'] === (int) User( 'STAFF_ID' );

				$badge = $state === 'active' ? 'on' : ( $state === 'readonly' ? 'ro' : 'off' );

				echo '<tr>';
				echo '<td>' . (int) $account['STAFF_ID'] . '</td>';
				echo '<td><strong>' . htmlspecialchars( $account['USERNAME'], ENT_QUOTES, 'UTF-8' ) . '</strong></td>';
				echo '<td>' . htmlspecialchars( $account['FIRST_NAME'] . ' ' . $account['LAST_NAME'], ENT_QUOTES, 'UTF-8' ) . '</td>';
				echo '<td>' . htmlspecialchars( SchoolSafeConsoleProfileLabel( $account ), ENT_QUOTES, 'UTF-8' ) . '</td>';
				echo '<td><span class="badge ' . $badge . '">' . htmlspecialchars( SchoolSafeConsoleStateLabel( $state ), ENT_QUOTES, 'UTF-8' ) . '</span></td>';
				echo '<td>' . ( $account['LAST_LOGIN'] ? htmlspecialchars( $account['LAST_LOGIN'], ENT_QUOTES, 'UTF-8' ) : '<em class="mut">jamais connecte</em>' ) . '</td>';
				echo '<td style="white-space:nowrap">';

				// Changement d'etat.
				echo '<form method="post" style="display:inline-block;margin:0 6px 0 0">';
				echo '<input type="hidden" name="console_token" value="' . htmlspecialchars( (string) $_SESSION['token'], ENT_QUOTES, 'UTF-8' ) . '">';
				echo '<input type="hidden" name="console_action" value="state">';
				echo '<input type="hidden" name="staff_id" value="' . (int) $account['STAFF_ID'] . '">';
				echo '<select name="state" aria-label="Etat du compte"' . ( $is_self ? ' disabled' : '' ) . '>';

				foreach ( [ 'active', 'readonly', 'suspended', 'expired' ] as $option )
				{
					echo '<option value="' . $option . '"' . ( $option === $state ? ' selected' : '' ) . '>'
						. htmlspecialchars( SchoolSafeConsoleStateLabel( $option ), ENT_QUOTES, 'UTF-8' ) . '</option>';
				}

				echo '</select>';
				echo '<input type="text" name="reason" size="10" maxlength="200" placeholder="motif" aria-label="Motif">';
				echo '<input type="text" name="until" size="10" maxlength="10" placeholder="AAAA-MM-JJ" aria-label="Date de fin">';
				echo '<button type="submit"' . ( $is_self ? ' disabled' : '' ) . '>Appliquer</button>';
				echo '</form>';

				// Deconnexion immediate des sessions ouvertes.
				echo '<form method="post" style="display:inline-block;margin:0 6px 0 0">';
				echo '<input type="hidden" name="console_token" value="' . htmlspecialchars( (string) $_SESSION['token'], ENT_QUOTES, 'UTF-8' ) . '">';
				echo '<input type="hidden" name="console_action" value="revoke">';
				echo '<input type="hidden" name="staff_id" value="' . (int) $account['STAFF_ID'] . '">';
				echo '<button type="submit" class="secondary"' . ( $is_self ? ' disabled' : '' ) . '>Deconnecter</button>';
				echo '</form>';

				// Nouveau mot de passe temporaire.
				echo '<form method="post" style="display:inline-block">';
				echo '<input type="hidden" name="console_token" value="' . htmlspecialchars( (string) $_SESSION['token'], ENT_QUOTES, 'UTF-8' ) . '">';
				echo '<input type="hidden" name="console_action" value="reset">';
				echo '<input type="hidden" name="staff_id" value="' . (int) $account['STAFF_ID'] . '">';
				echo '<button type="submit" class="secondary">Mot de passe</button>';
				echo '</form>';

				echo '</td></tr>';
			}
			?>
		</table>
		</div>
		<p class="mut">« Mot de passe » genere un mot de passe temporaire (affiche une seule fois) et deconnecte les sessions ouvertes : la personne choisira le sien a sa prochaine connexion.</p>
	</div>

	<?php if ( $schoolsafe_global_admin ) {
		$school_states = SchoolSafeSchoolStateRows();

		// Controle de coherence du calendrier (annee, semestres, trimestres) :
		// une ecole dont les periodes sont a plat casse les ecrans de son
		// directeur. Le proprietaire de la plateforme le voit ici, avant qu'un
		// directeur ne tombe sur une erreur technique.
		$school_calendars = SchoolSafeSchoolCalendarStatuses(
			array_map( static function ( $school ) { return (int) $school['ID']; }, (array) $schools ),
			$syear
		);
		?>
	<div class="card">
		<h2 style="font-size:19px;margin-top:0">Etablissements</h2>
		<p class="mut">Trois etats, aucun effacement : <strong>Actif</strong> (en service), <strong>Suspendu</strong> (acces coupe pour tous ses utilisateurs, donnees conservees ; une date de fin rend l'acces tout seul a cette date), <strong>Desactive</strong> (etablissement ferme ou hors service, reactivation manuelle). Une ecole coupee ne perd rien : eleves, notes, paiements, comptes et historiques restent en place. Une personne affectee a plusieurs ecoles perd l'ecole coupee et garde les autres ; le proprietaire de la plateforme conserve l'acces pour administrer.</p>
		<p class="mut"><strong>Calendrier</strong> : chaque ecole doit avoir une annee, des semestres rattaches a l'annee, des trimestres rattaches a un semestre. Un calendrier en anomalie est signale ici ; la creation d'une ecole refuse desormais de recopier un gabarit incoherent plutot que de livrer une ecole dont les ecrans echouent.</p>
		<div style="overflow-x:auto">
		<table class="list schools-list">
			<tr><th>ID</th><th>Etablissement</th><th>Etat</th><th>Actions</th></tr>
			<?php
			foreach ( $schools as $school )
			{
				$school_state = SchoolSafeSchoolStateEffective( $school['ID'] );

				$school_row = $school_states[(int) $school['ID']] ?? [];

				$calendar = $school_calendars[(int) $school['ID']] ?? [
					'ok' => false,
					'issues' => [ [ 'label' => 'Calendrier illisible.' ] ],
				];

				$calendar_labels = array_column( $calendar['issues'], 'label' );

				echo '<tr>';
				echo '<td>' . (int) $school['ID'] . '</td>';
				echo '<td><strong>' . htmlspecialchars( $school['TITLE'], ENT_QUOTES, 'UTF-8' ) . '</strong>';

				// Calendrier sous le nom : signale l'anomalie sans elargir le tableau
				// (une cinquieme colonne rendait la console illisible sur telephone).
				echo ' <span class="badge ' . ( $calendar['ok'] ? 'on' : 'off' ) . '" data-calendar="'
					. ( $calendar['ok'] ? 'ok' : 'issues' ) . '">'
					. ( $calendar['ok'] ? 'Coherent' : ( count( $calendar_labels ) . ' anomalie(s)' ) ) . '</span>';

				foreach ( array_slice( $calendar_labels, 0, 3 ) as $calendar_label )
				{
					echo '<br><span class="mut">' . htmlspecialchars( (string) $calendar_label, ENT_QUOTES, 'UTF-8' ) . '</span>';
				}

				if ( count( $calendar_labels ) > 3 )
				{
					echo '<br><span class="mut">et ' . ( count( $calendar_labels ) - 3 ) . ' autre(s).</span>';
				}

				echo '</td>';
				echo '<td><span class="badge ' . ( $school_state === 'active' ? 'on' : 'off' ) . '">'
					. htmlspecialchars( SchoolSafeConsoleSchoolStateLabel( $school_state ), ENT_QUOTES, 'UTF-8' ) . '</span>';

				if ( ! empty( $school_row['REASON'] ) || ! empty( $school_row['UNTIL'] ) )
				{
					echo '<br><span class="mut">' . htmlspecialchars( (string) $school_row['REASON'], ENT_QUOTES, 'UTF-8' );

					if ( ! empty( $school_row['UNTIL'] ) )
					{
						echo ' &middot; jusqu\'au ' . htmlspecialchars( mb_substr( (string) $school_row['UNTIL'], 0, 10 ), ENT_QUOTES, 'UTF-8' );
					}

					echo '</span>';
				}

				echo '</td>';
				echo '<td>';

				if ( $school_state !== 'active' )
				{
					echo '<form method="post" class="school-action">';
					echo '<input type="hidden" name="console_token" value="' . htmlspecialchars( (string) $_SESSION['token'], ENT_QUOTES, 'UTF-8' ) . '">';
					echo '<input type="hidden" name="console_action" value="school_state">';
					echo '<input type="hidden" name="school_id" value="' . (int) $school['ID'] . '">';
					echo '<input type="hidden" name="school_state" value="active">';
					echo '<button type="submit">Reactiver</button></form>';
				}

				if ( $school_state !== 'suspended' )
				{
					echo '<form method="post" class="school-action">';
					echo '<input type="hidden" name="console_token" value="' . htmlspecialchars( (string) $_SESSION['token'], ENT_QUOTES, 'UTF-8' ) . '">';
					echo '<input type="hidden" name="console_action" value="school_state">';
					echo '<input type="hidden" name="school_id" value="' . (int) $school['ID'] . '">';
					echo '<input type="hidden" name="school_state" value="suspended">';
					echo '<input type="text" name="school_reason" maxlength="200" placeholder="motif" aria-label="Motif de suspension">';
					echo '<input type="text" name="school_until" maxlength="10" placeholder="AAAA-MM-JJ (fin facultative)" aria-label="Date de fin facultative">';
					echo '<button type="submit">Suspendre</button></form>';
				}

				if ( $school_state !== 'inactive' )
				{
					echo '<form method="post" class="school-action">';
					echo '<input type="hidden" name="console_token" value="' . htmlspecialchars( (string) $_SESSION['token'], ENT_QUOTES, 'UTF-8' ) . '">';
					echo '<input type="hidden" name="console_action" value="school_state">';
					echo '<input type="hidden" name="school_id" value="' . (int) $school['ID'] . '">';
					echo '<input type="hidden" name="school_state" value="inactive">';
					echo '<button type="submit" class="secondary">Desactiver</button></form>';
				}

				echo '</td></tr>';
			}
			?>
		</table>
		</div>
		<p class="mut">Suspendre une ecole coupe l'acces de tous ses utilisateurs des la requete suivante : une session ouverte est fermee si l'ecole est la seule de la personne, sinon elle continue dans ses autres ecoles. Aucun enregistrement n'est supprime ni modifie dans les tables natives.</p>
	</div>
	<?php } ?>

	<?php if ( $schoolsafe_global_admin ) { ?>
	<div class="card">
		<h2 style="font-size:19px;margin-top:0">Creer un administrateur de plateforme</h2>
		<form method="post">
			<input type="hidden" name="console_token" value="<?php echo htmlspecialchars( (string) $_SESSION['token'], ENT_QUOTES, 'UTF-8' ); ?>">
			<input type="hidden" name="console_action" value="create">
			<div class="inline">
				<div><label class="fl" for="cf">Prenom</label><input type="text" id="cf" name="first_name" maxlength="50" required></div>
				<div><label class="fl" for="cl">Nom</label><input type="text" id="cl" name="last_name" maxlength="50" required></div>
			</div>
			<div class="inline">
				<div><label class="fl" for="cu">Nom d'utilisateur</label><input type="text" id="cu" name="username" maxlength="100" required></div>
				<div><label class="fl" for="ce">Email (recommande)</label><input type="email" id="ce" name="email" maxlength="255"></div>
			</div>
			<label class="fl">Ecoles gerees par ce compte</label>
			<div class="schools">
				<?php
				foreach ( $schools as $school )
				{
					echo '<label><input type="checkbox" name="school_ids[]" value="' . (int) $school['ID'] . '"> '
						. htmlspecialchars( $school['TITLE'], ENT_QUOTES, 'UTF-8' ) . ' (ID ' . (int) $school['ID'] . ')</label>';
				}
				?>
			</div>
			<p><button type="submit">Creer l'administrateur</button></p>
			<p class="mut">Un mot de passe temporaire sera genere : communiquez-le a la personne, elle choisira le sien a sa premiere connexion.</p>
		</form>
	</div>
	<?php } ?>
</main>
</body>
</html>
