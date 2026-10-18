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
 *  - suspension = PROFILE 'none' (mecanisme natif), PROFILE_ID conserve.
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

$schools_raw = (string) User( 'SCHOOLS' );

if ( trim( $schools_raw, ',' ) !== '' )
{
	http_response_code( 403 );
	exit( '<!doctype html><html lang="fr"><meta charset="utf-8"><title>SchoolSafe</title>'
		. '<body style="font-family:system-ui;background:#f4f7fa;color:#172d40;padding:48px;text-align:center">'
		. '<h2>Console reservee a l\'administrateur de la plateforme</h2>'
		. '<p><a href="Modules.php?modname=misc/Portal.php" style="color:#126cac">Retour au portail</a></p></body></html>' );
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

	if ( ! $csrf_ok )
	{
		$error = 'Session expiree, veuillez reessayer.';
	}
	else
	{
		$action = $_POST['console_action'] ?? '';

		if ( $action === 'create' )
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
		elseif ( $action === 'reset' || $action === 'suspend' || $action === 'activate' )
		{
			$staff_id = (int) ( $_POST['staff_id'] ?? 0 );

			$target = DBGet( DBQuery( "SELECT STAFF_ID,USERNAME,PROFILE
				FROM staff WHERE STAFF_ID='" . $staff_id . "' AND SYEAR='" . $syear . "'" ) );

			if ( ! $target )
			{
				$error = 'Compte introuvable.';
			}
			elseif ( (int) $target[1]['STAFF_ID'] === (int) User( 'STAFF_ID' )
				&& $action !== 'reset' )
			{
				$error = 'Vous ne pouvez pas suspendre votre propre compte.';
			}
			elseif ( $action === 'reset' )
			{
				if ( $target[1]['PROFILE'] === 'none' )
				{
					$error = 'Compte suspendu : reactiver avant de reinitialiser.';
				}
				else
				{
					$temp_password = SchoolSafeConsoleTempPassword();

					DBQuery( "UPDATE staff
						SET PASSWORD='" . DBEscapeString( encrypt_password( $temp_password ) ) . "',
						LAST_LOGIN=NULL,FAILED_LOGIN=0
						WHERE STAFF_ID='" . $staff_id . "' AND SYEAR='" . $syear . "'" );

					$_SESSION['schoolsafe_console_flash'] = [
						'type' => 'created',
						'username' => $target[1]['USERNAME'],
						'temp' => $temp_password,
					];

					header( 'Location: admin-console.php' );
					exit;
				}
			}
			elseif ( $action === 'suspend' )
			{
				if ( $target[1]['PROFILE'] === 'none' )
				{
					$error = 'Compte deja suspendu.';
				}
				else
				{
					DBQuery( "UPDATE staff SET PROFILE='none'
						WHERE STAFF_ID='" . $staff_id . "' AND SYEAR='" . $syear . "'" );

					header( 'Location: admin-console.php' );
					exit;
				}
			}
			elseif ( $action === 'activate' )
			{
				if ( $target[1]['PROFILE'] !== 'none' )
				{
					$error = 'Compte deja actif.';
				}
				else
				{
					DBQuery( "UPDATE staff SET PROFILE='admin'
						WHERE STAFF_ID='" . $staff_id . "' AND SYEAR='" . $syear . "'" );

					header( 'Location: admin-console.php' );
					exit;
				}
			}
		}
	}
}

// --- Donnees ---
$admins = DBGet( DBQuery( "SELECT STAFF_ID,USERNAME,FIRST_NAME,LAST_NAME,EMAIL,PROFILE,SCHOOLS,LAST_LOGIN
	FROM staff
	WHERE SYEAR='" . $syear . "'
	AND ( (PROFILE='admin' AND PROFILE_ID=1) OR PROFILE='none' )
	ORDER BY LAST_NAME,FIRST_NAME" ) );

$schools = DBGet( DBQuery( "SELECT ID,TITLE FROM schools
	WHERE SYEAR='" . $syear . "' ORDER BY TITLE" ) );

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
		<h2 style="font-size:19px;margin-top:0">Comptes administrateurs</h2>
		<table class="list">
			<tr><th>ID</th><th>Utilisateur</th><th>Nom</th><th>Email</th><th>Statut</th><th>Derniere connexion</th><th>Actions</th></tr>
			<?php
			foreach ( $admins as $admin )
			{
				$suspended = $admin['PROFILE'] === 'none';
				echo '<tr>';
				echo '<td>' . (int) $admin['STAFF_ID'] . '</td>';
				echo '<td><strong>' . htmlspecialchars( $admin['USERNAME'], ENT_QUOTES, 'UTF-8' ) . '</strong></td>';
				echo '<td>' . htmlspecialchars( $admin['FIRST_NAME'] . ' ' . $admin['LAST_NAME'], ENT_QUOTES, 'UTF-8' ) . '</td>';
				echo '<td>' . htmlspecialchars( (string) $admin['EMAIL'], ENT_QUOTES, 'UTF-8' ) . '</td>';
				echo '<td><span class="badge ' . ( $suspended ? 'off' : 'on' ) . '">' . ( $suspended ? 'Suspendu' : 'Actif' ) . '</span></td>';
				echo '<td>' . ( $admin['LAST_LOGIN'] ? htmlspecialchars( $admin['LAST_LOGIN'], ENT_QUOTES, 'UTF-8' ) : '<em class="mut">jamais connecte</em>' ) . '</td>';
				echo '<td style="white-space:nowrap">';
				echo '<form method="post" style="display:inline">';
				echo '<input type="hidden" name="console_token" value="' . htmlspecialchars( (string) $_SESSION['token'], ENT_QUOTES, 'UTF-8' ) . '">';
				echo '<input type="hidden" name="console_action" value="reset">';
				echo '<input type="hidden" name="staff_id" value="' . (int) $admin['STAFF_ID'] . '">';
				echo '<button type="submit"' . ( $suspended ? ' disabled' : '' ) . '>Reinitialiser</button> ';
				echo '</form>';
				if ( $suspended )
				{
					echo '<form method="post" style="display:inline">';
					echo '<input type="hidden" name="console_token" value="' . htmlspecialchars( (string) $_SESSION['token'], ENT_QUOTES, 'UTF-8' ) . '">';
					echo '<input type="hidden" name="console_action" value="activate">';
					echo '<input type="hidden" name="staff_id" value="' . (int) $admin['STAFF_ID'] . '">';
					echo '<button type="submit" class="secondary">Reactiver</button>';
					echo '</form>';
				}
				else
				{
					echo '<form method="post" style="display:inline">';
					echo '<input type="hidden" name="console_token" value="' . htmlspecialchars( (string) $_SESSION['token'], ENT_QUOTES, 'UTF-8' ) . '">';
					echo '<input type="hidden" name="console_action" value="suspend">';
					echo '<input type="hidden" name="staff_id" value="' . (int) $admin['STAFF_ID'] . '">';
					echo '<button type="submit" class="danger"' . ( (int) $admin['STAFF_ID'] === (int) User( 'STAFF_ID' ) ? ' disabled' : '' ) . '>Suspendre</button>';
					echo '</form>';
				}
				echo '</td></tr>';
			}
			?>
		</table>
		<p class="mut">« Reinitialiser » donne un nouveau mot de passe temporaire : l'administrateur devra choisir le sien a sa prochaine connexion.</p>
	</div>

	<div class="card">
		<h2 style="font-size:19px;margin-top:0">Creer un administrateur</h2>
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
</main>
</body>
</html>
