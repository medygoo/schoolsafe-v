<?php
/**
 * SchoolSafe L3 — centre officiel de gestion des utilisateurs d'un établissement.
 *
 * Routes officielles (rewrite .htaccess, mêmes écrans) :
 *   /ecoles/{id}/utilisateurs            (liste + nouvel utilisateur)
 *   /ecoles/{id}/utilisateurs/nouveau    (formulaire adaptatif)
 *   /ecoles/{id}/utilisateurs/{util}     (fiche consultative + actions)
 *
 * AUCUN moteur parallèle : consulter/créer/modifier passent par les contrôleurs
 * natifs (modules/Users/User.php, modules/Custom/CreateParents.php pour le lien
 * parent-enfant). Cette page contrôle le périmètre (SchoolSafeSchoolAllowed),
 * les permissions (AllowUse/AllowEdit) et la délégation (SchoolSafeProfileDelegable),
 * détecte les doublons (SchoolSafeIdentity*, +243, sans fusion automatique) puis
 * transmet au natif via un formulaire auto-posté vers Modules.php.
 * Anciennes routes internes Rosario : préservées, jamais supprimées.
 * Dossiers élèves : gérés séparément (modules/Students) ; une inscription
 * d'élève ne crée aucun compte ici.
 */

header( 'Cache-Control: no-store' );
header( 'X-Content-Type-Options: nosniff' );

chdir( __DIR__ );

require_once __DIR__ . '/Warehouse.php';

$school_id = (int) ( $_GET['school_id'] ?? $_REQUEST['school_id'] ?? 0 );
$view_user = (int) ( $_GET['utilisateur'] ?? 0 );
$is_new = isset( $_GET['nouveau'] ) || (string) ( $_GET['vue'] ?? '' ) === 'nouveau';

function SchoolSafeUsersHubDeny( $message )
{
	http_response_code( 403 );

	exit( '<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
		. '<title>SchoolSafe — Utilisateurs</title><body style="font-family:system-ui;padding:2rem">'
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
	SchoolSafeUsersHubDeny( 'Gestion des utilisateurs réservée aux administrateurs.' );
}

if ( $school_id < 1
	|| ! DBGetOne( "SELECT ID FROM schools WHERE ID='" . $school_id . "' AND SYEAR='" . (int) UserSyear() . "'" ) )
{
	SchoolSafeUsersHubDeny( 'Établissement introuvable pour cette année.' );
}

if ( ! SchoolSafeGlobalAdmin() && ! SchoolSafeSchoolAllowed( $school_id ) )
{
	SchoolSafeUsersHubDeny( 'Vous ne gérez que les établissements qui vous sont attribués.' );
}

$token = (string) ( $_SESSION['token'] ?? '' );

// Catégories du formulaire adaptatif -> profils Rosario réels (033 + 015 + natifs).
$categories = [
	'direction' => [ 'label' => 'Direction', 'title' => 'Directeur', 'kind' => 'admin',
		'note' => 'Responsable d’école : profil Directeur, au moins une école. Ne peut pas nommer un pair.' ],
	'enseignant' => [ 'label' => 'Enseignant / professeur', 'title' => null, 'kind' => 'teacher',
		'note' => 'Profil Teacher natif. Affectation aux classes après création (Teacher Programs natif).' ],
	'admin_scolaire' => [ 'label' => 'Administrateur scolaire', 'title' => 'Administrateur scolaire', 'kind' => 'admin',
		'note' => 'Gère les comptes de son école. Ne peut pas créer de GlobalAdmin ni gérer les profils.' ],
	'parent' => [ 'label' => 'Parent / tuteur', 'title' => null, 'kind' => 'parent',
		'note' => 'Jamais rattaché à une école : ses enfants le lient (Custom/CreateParents après création).' ],
	'gardien' => [ 'label' => 'Gardien', 'title' => 'Gardien', 'kind' => 'parent',
		'note' => 'Personne de récupération autorisée à se connecter (base parent).' ],
	'personnel' => [ 'label' => 'Personnel administratif', 'title' => 'Personnel administratif', 'kind' => 'teacher',
		'note' => 'Fonctions administratives, multi-écoles autorisé, sans gestion d’utilisateurs.' ],
	'cantine' => [ 'label' => 'Cantine et autres fonctions', 'title' => 'Cantine', 'kind' => 'teacher',
		'note' => 'Fonctions Food_Service existantes, sans gestion d’utilisateurs ni d’écoles.' ],
];

$can_create = AllowUse( 'Users/User.php' ) && AllowEdit( 'Users/User.php' );
$message = '';
$error = '';
$confirm = null;

if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) === 'POST' && isset( $_POST['l3_create'] ) )
{
	if ( ! $can_create )
	{
		$error = 'Création non autorisée pour votre profil.';
	}
	elseif ( ! is_string( $_POST['token'] ?? null ) || ! hash_equals( $token, $_POST['token'] ) )
	{
		$error = 'Requête expirée. Rechargez la page.';
	}
	else
	{
		$cat_key = (string) ( $_POST['categorie'] ?? '' );
		$cat = $categories[$cat_key] ?? null;

		$first = trim( (string) ( $_POST['first_name'] ?? '' ) );
		$last = trim( (string) ( $_POST['last_name'] ?? '' ) );
		$username = trim( (string) ( $_POST['username'] ?? '' ) );
		$email = trim( (string) ( $_POST['email'] ?? '' ) );
		$phone = trim( (string) ( $_POST['phone'] ?? '' ) );
		$verified = ( $_POST['doublon_verifie'] ?? '' ) === '1';

		if ( ! $cat || $first === '' || $last === '' || $username === '' )
		{
			$error = 'Renseignez la catégorie, le nom, le prénom et l’identifiant.';
		}
		elseif ( $email !== '' && ! filter_var( $email, FILTER_VALIDATE_EMAIL ) )
		{
			$error = 'Adresse e-mail non valide.';
		}
		else
		{
			$profile_id = null;
			$kind = $cat['kind'];

			if ( $cat['title'] !== null )
			{
				$profile_id = DBGetOne( "SELECT ID FROM user_profiles WHERE TITLE='"
					. DBEscapeString( $cat['title'] ) . "'" );
			}

			if ( $cat['title'] !== null && ! $profile_id )
			{
				$error = 'Profil « ' . $cat['title'] . ' » indisponible (migration 033 requise).';
			}
			elseif ( ! SchoolSafeProfileDelegable( $profile_id, $kind ) )
			{
				// Un profil NULL (Enseignant, Parent natifs) se résout en profil
				// par défaut 2/3 dans la fonction, comme le contrôle natif :
				// passer la chaîne 'teacher'/'parent' refuserait toujours.
				$error = 'Vous ne pouvez pas attribuer ce profil (élévation refusée).';
			}			if ( $error === '' && ( $_POST['profil_ecole'] ?? '' ) !== '' )
			{
				// L3.1 — profil personnalisé de l'établissement : remplace le
				// profil de catégorie après les mêmes contrôles, plus
				// l'appartenance à l'école de la route. Cibles forcées à
				// cette école (le natif refuse sinon, cf. User.php).
				$ecole_pid = $_POST['profil_ecole'];
				$ecole_prof = ctype_digit( (string) $ecole_pid )
					? DBGet( "SELECT PROFILE FROM user_profiles WHERE ID='" . (int) $ecole_pid . "'" ) : [];

				if ( ! $ecole_prof
					|| SchoolSafeProfileSchoolId( $ecole_pid ) !== $school_id
					|| (string) $ecole_prof[1]['PROFILE'] !== $kind
					|| ! SchoolSafeProfileDelegable( (string) $ecole_pid, $kind ) )
				{
					$error = 'Profil d’école non attribuable (appartenance, famille ou délégation).';
				}
				else
				{
					$profile_id = (int) $ecole_pid;
					$targets = [ $school_id ];
					$skip_targets = true;
				}
			}

			if ( $error === '' )
			{
				// Écoles cibles : cochées parmi le périmètre de l'acteur.
				// (profil d'école : cibles déjà forcées, on ne recalcule pas).
				$actor_scope = SchoolSafeScopeParse( User( 'SCHOOLS' ) );

				if ( empty( $skip_targets ) ) $targets = [];

				foreach ( (array) ( $_POST['schools'] ?? [] ) as $sid => $yes )
				{
					if ( $yes && ctype_digit( (string) $sid ) && (int) $sid > 0 )
					{
						$targets[] = (int) $sid;
					}
				}

				if ( $kind === 'parent' )
				{
					$targets = []; // Parent : jamais rattaché à une école.
				}
				elseif ( ! $targets )
				{
					$targets = [ $school_id ];
				}

				foreach ( $targets as $sid )
				{
					if ( ! SchoolSafeGlobalAdmin() && ! SchoolSafeSchoolAllowed( $sid ) )
					{
						$error = 'École hors de votre périmètre.';
						break;
					}
				}

				if ( $error === '' && $kind !== 'parent' )
				{
					// Délégation stricte : les écoles cibles doivent être incluses
					// dans le périmètre de l'acteur (le global délègue tout).
					// SchoolSafeScopeDelegates attend des périmètres PARSES
					// (tableaux), comme tous les appelants natifs.
					if ( ! SchoolSafeScopeDelegates( $actor_scope, $targets ?: null ) )
					{
						$error = 'Affectation hors de votre périmètre.';
					}
				}

				if ( $error === '' )
				{
					$dupes = SchoolSafeIdentityFindDuplicates( $username, $email, $phone );

					if ( $dupes && ! $verified )
					{
						$error = 'Doublon possible : un compte partage déjà cet identifiant, cet e-mail ou ce téléphone. '
							. 'Vérifiez la propriété avec la personne concernée, puis cochez la confirmation — '
							. 'aucune fusion automatique ne sera effectuée.';
					}
					else
					{
						// Transmission au moteur natif (mêmes contrôles serveur) :
						// le hub ne crée RIEN lui-même. Mot de passe temporaire
						// généré ici, affiché UNE SEULE FOIS pour remise en mains
						// propres ; le natif le hache (encrypt_password) et impose
						// le changement à la première connexion (LAST_LOGIN NULL).
						$alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
						$temp = '';

						for ( $i = 0; $i < 12; $i++ )
						{
							$temp .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
						}

						$confirm = [
							'first' => $first, 'last' => $last, 'username' => $username,
							'email' => $email, 'phone' => $phone, 'kind' => $kind,
							'profile_id' => $profile_id, 'targets' => $targets,
							'category' => $cat['label'], 'temp' => $temp,
						];

						$message = 'Vérifications OK. Confirmez pour créer via le moteur natif.';
					}
				}
			}
		}
	}
}

// --- Données d'affichage (lecture seule, périmètre école) --------------------
$school_title = DBGetOne( "SELECT TITLE FROM schools WHERE ID='" . $school_id . "'" );

$staff_rows = DBGet( "SELECT s.STAFF_ID,s.USERNAME,s.FIRST_NAME,s.LAST_NAME,s.PROFILE,s.PROFILE_ID,s.SCHOOLS,u.TITLE AS PROFILE_TITLE
	FROM staff s LEFT JOIN user_profiles u ON u.ID=s.PROFILE_ID
	WHERE s.SYEAR='" . (int) UserSyear() . "'
	AND (',' || TRIM(BOTH ',' FROM COALESCE(s.SCHOOLS,'')) || ',' LIKE '%," . $school_id . ",%' OR s.SCHOOLS IS NULL)
	ORDER BY s.LAST_NAME,s.FIRST_NAME" );

if ( ! SchoolSafeGlobalAdmin() )
{
	// Un directeur ne voit jamais les comptes globaux (propriétaires plateforme).
	$staff_rows = array_values( array_filter( (array) $staff_rows, function ( $row ) {
		return isset( $row['SCHOOLS'] ) && $row['SCHOOLS'] !== null && $row['SCHOOLS'] !== '';
	} ) );
}

$parent_rows = DBGet( "SELECT DISTINCT s.STAFF_ID,s.USERNAME,s.FIRST_NAME,s.LAST_NAME
	FROM staff s JOIN students_join_users j ON j.STAFF_ID=s.STAFF_ID
	JOIN student_enrollment se ON se.STUDENT_ID=j.STUDENT_ID
	WHERE s.SYEAR='" . (int) UserSyear() . "' AND se.SYEAR='" . (int) UserSyear() . "'
	AND se.SCHOOL_ID='" . $school_id . "' ORDER BY s.LAST_NAME" );

$profile_rows = DBGet( "SELECT ID,TITLE,PROFILE FROM user_profiles ORDER BY PROFILE,ID" );
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Utilisateurs — <?php echo htmlspecialchars( (string) $school_title, ENT_QUOTES, 'UTF-8' ); ?> | SchoolSafe</title>
<style>
body{font-family:system-ui,sans-serif;margin:0;background:#f4f7fa;color:#172d40}
main{max-width:56rem;margin:0 auto;padding:1.25rem}
nav.top{background:#0b3b7a;color:#fff;padding:.6rem 1.25rem}
nav.top a{color:#fff;margin-right:1rem}
.card{background:#fff;border-radius:.75rem;padding:1.25rem;margin:1rem 0}
table{border-collapse:collapse;width:100%}
th,td{border:1px solid #dfe7ef;padding:.4rem;text-align:left;font-size:.9rem}
.status{background:#e8f5e9;border-radius:.5rem;padding:.7rem}
.alert{background:#fdecea;border-radius:.5rem;padding:.7rem}
label{display:block;margin:.5rem 0}
input,select{padding:.5rem;font-size:1rem;max-width:100%}
small{color:#456}
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
<h1>Utilisateurs — <?php echo htmlspecialchars( (string) $school_title, ENT_QUOTES, 'UTF-8' ); ?></h1>
<p><small>Centre officiel de gestion des comptes de l’établissement. Création et modification via le moteur natif
(Users/User.php) ; dossiers élèves séparés ; parents liés via Custom/CreateParents après création.</small></p>
<?php if ( $message !== '' ) : ?>
<p class="status" role="status"><?php echo htmlspecialchars( $message, ENT_QUOTES, 'UTF-8' ); ?></p>
<?php endif; ?>
<?php if ( $error !== '' ) : ?>
<p class="alert" role="alert"><?php echo htmlspecialchars( $error, ENT_QUOTES, 'UTF-8' ); ?></p>
<?php endif; ?>

<?php if ( $confirm ) : ?>
<section class="card" aria-labelledby="t-confirm">
<h2 id="t-confirm">Confirmer la création (moteur natif)</h2>
<dl>
<dt>Catégorie</dt><dd><?php echo htmlspecialchars( $confirm['category'], ENT_QUOTES, 'UTF-8' ); ?></dd>
<dt>Personne</dt><dd><?php echo htmlspecialchars( $confirm['first'] . ' ' . $confirm['last'], ENT_QUOTES, 'UTF-8' ); ?></dd>
<dt>Identifiant</dt><dd><?php echo htmlspecialchars( $confirm['username'], ENT_QUOTES, 'UTF-8' ); ?></dd>
<dt>Mot de passe temporaire (affiché une seule fois)</dt><dd><code><?php echo htmlspecialchars( $confirm['temp'], ENT_QUOTES, 'UTF-8' ); ?></code> — à remettre en mains propres, jamais par e-mail.</dd>
</dl>
<p><small>Le mot de passe temporaire sera affiché UNE SEULE FOIS par le moteur natif et remis en mains propres.
Première connexion : changement obligatoire (LAST_LOGIN NULL). Aucun secret envoyé par e-mail.</small></p>
<form method="post" action="Modules.php?modname=Users/User.php&amp;modfunc=update&amp;staff_id=new">
<input type="hidden" name="token" value="<?php echo htmlspecialchars( $token, ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="staff[FIRST_NAME]" value="<?php echo htmlspecialchars( $confirm['first'], ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="staff[LAST_NAME]" value="<?php echo htmlspecialchars( $confirm['last'], ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="staff[USERNAME]" value="<?php echo htmlspecialchars( $confirm['username'], ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="staff[EMAIL]" value="<?php echo htmlspecialchars( $confirm['email'], ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="staff[CUSTOM_200000001]" value="<?php echo htmlspecialchars( $confirm['phone'], ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="staff[PROFILE]" value="<?php echo htmlspecialchars( $confirm['kind'], ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="staff[PASSWORD]" value="<?php echo htmlspecialchars( $confirm['temp'], ENT_QUOTES, 'UTF-8' ); ?>">
<?php if ( $confirm['profile_id'] ) : ?>
<input type="hidden" name="staff[PROFILE_ID]" value="<?php echo (int) $confirm['profile_id']; ?>">
<?php endif; ?>
<?php foreach ( $confirm['targets'] as $sid ) : ?>
<input type="hidden" name="staff[SCHOOLS][<?php echo (int) $sid; ?>]" value="Y">
<?php endforeach; ?>
<p><button type="submit">Créer le compte (natif)</button>
<a href="/ecoles/<?php echo $school_id; ?>/utilisateurs">Annuler</a></p>
</form>
</section>
<?php endif; ?>

<?php if ( $view_user > 0 ) : ?>
<section class="card" aria-labelledby="t-fiche">
<h2 id="t-fiche">Fiche consultative n°<?php echo (int) $view_user; ?></h2>
<p><a href="Modules.php?modname=Users/User.php&amp;staff_id=<?php echo (int) $view_user; ?>">Ouvrir dans Users/User.php natif (modifier selon permissions)</a></p>
<p><a href="/plateforme/prodeli">Désactiver / réactiver via la console (même application)</a></p>
<p><a href="/ecoles/<?php echo $school_id; ?>/utilisateurs">Retour à la liste</a></p>
</section>
<?php endif; ?>

<?php if ( $is_new || $confirm ) : ?>
<section class="card" aria-labelledby="t-new">
<h2 id="t-new">Nouvel utilisateur — formulaire adaptatif</h2>
<?php if ( ! $can_create ) : ?>
<p role="alert">Votre profil ne permet pas la création (lecture seule).</p>
<?php else : ?>
<form method="post" action="/ecoles/<?php echo $school_id; ?>/utilisateurs/nouveau">
<input type="hidden" name="token" value="<?php echo htmlspecialchars( $token, ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="l3_create" value="1">
<label>Catégorie
<select name="categorie" required>
<option value="">Choisir…</option>
<?php foreach ( $categories as $key => $cat ) : ?>
<option value="<?php echo $key; ?>"><?php echo htmlspecialchars( $cat['label'], ENT_QUOTES, 'UTF-8' ); ?></option>
<?php endforeach; ?>
</select></label>
<p><small>Direction → Directeur · Enseignant → Teacher natif · Administrateur scolaire → profil 033 ·
Parent → Parent natif (sans école) · Gardien → Gardien 015 · Personnel → Personnel administratif 033 ·
Cantine → Cantine 033. Champs et permissions affichés selon la catégorie.</small></p>
<?php $ecole_prof_options = DBGet( "SELECT ID,TITLE FROM user_profiles WHERE SCHOOL_ID='" . $school_id . "' ORDER BY ID" ); ?>
<?php if ( $ecole_prof_options ) : ?>
<label>Profil personnalisé de l’école (optionnel, remplace le profil de catégorie)
<select name="profil_ecole">
<option value="">— profil de catégorie —</option>
<?php foreach ( (array) $ecole_prof_options as $epo ) : ?>
<option value="<?php echo (int) $epo['ID']; ?>"><?php echo htmlspecialchars( $epo['TITLE'], ENT_QUOTES, 'UTF-8' ); ?></option>
<?php endforeach; ?>
</select></label>
<?php endif; ?>
<label>Prénom <input required name="first_name" maxlength="50"></label>
<label>Nom <input required name="last_name" maxlength="50"></label>
<label>Identifiant <input required name="username" maxlength="100" autocomplete="off"></label>
<label>E-mail <input type="email" name="email" maxlength="255"></label>
<label>Téléphone <input name="phone" placeholder="+243…" autocomplete="off"><br>
<small>Numéros congolais normalisés en +243 pour la détection. Vérification de propriété exigée avant tout rapprochement.</small></label>
<fieldset><legend>Écoles (défaut : cet établissement ; parents : aucune)</legend>
<?php
$school_options = DBGet( "SELECT ID,TITLE FROM schools WHERE SYEAR='" . (int) UserSyear() . "' ORDER BY TITLE" );
foreach ( (array) $school_options as $opt )
{
	if ( ! SchoolSafeGlobalAdmin() && ! SchoolSafeSchoolAllowed( (int) $opt['ID'] ) ) continue;
	$checked = (int) $opt['ID'] === $school_id ? ' checked' : '';
	echo '<label><input type="checkbox" name="schools[' . (int) $opt['ID'] . ']" value="Y"' . $checked . '> '
		. htmlspecialchars( $opt['TITLE'], ENT_QUOTES, 'UTF-8' ) . '</label>';
}
?>
</fieldset>
<label><input type="checkbox" name="doublon_verifie" value="1"> J’ai vérifié avec la personne qu’aucun compte existant ne lui appartient (aucune fusion automatique).</label>
<p><button type="submit">Vérifier et préparer (natif)</button></p>
</form>
<?php endif; ?>
</section>
<?php endif; ?>

<section class="card" aria-labelledby="t-list">
<h2 id="t-list">Comptes de l’établissement (<?php echo count( (array) $staff_rows ); ?> + <?php echo count( (array) $parent_rows ); ?> parents liés)</h2>
<table>
<thead><tr><th>Compte</th><th>Profil / fonction</th><th>Écoles</th><th>Actions natives</th></tr></thead>
<tbody>
<?php foreach ( (array) $staff_rows as $row ) : ?>
<tr>
<td><?php echo htmlspecialchars( $row['FIRST_NAME'] . ' ' . $row['LAST_NAME'] . ' (' . $row['USERNAME'] . ')', ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo htmlspecialchars( ( $row['PROFILE_TITLE'] ?? '' ) !== '' ? $row['PROFILE_TITLE'] . ' — ' . $row['PROFILE'] : $row['PROFILE'], ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo htmlspecialchars( (string) ( $row['SCHOOLS'] ?? '' ) === '' ? '—' : (string) $row['SCHOOLS'], ENT_QUOTES, 'UTF-8' ); ?></td>
<td><a href="/ecoles/<?php echo $school_id; ?>/utilisateurs/<?php echo (int) $row['STAFF_ID']; ?>">Consulter</a>
· <a href="Modules.php?modname=Users/User.php&amp;staff_id=<?php echo (int) $row['STAFF_ID']; ?>">Modifier (natif)</a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<h3>Parents liés à au moins un enfant de l’école (comptes sans école, lien natif conservé)</h3>
<ul>
<?php foreach ( (array) $parent_rows as $row ) : ?>
<li><?php echo htmlspecialchars( $row['FIRST_NAME'] . ' ' . $row['LAST_NAME'] . ' (' . $row['USERNAME'] . ')', ENT_QUOTES, 'UTF-8' ); ?>
— <a href="Modules.php?modname=Users/User.php&amp;staff_id=<?php echo (int) $row['STAFF_ID']; ?>">Fiche native</a></li>
<?php endforeach; ?>
</ul>
<p><small>Anciens écrans natifs (Users/Search, AddStudents, Associate Students with Parents) : préservés.
La navigation visible converge ici. Désactivation / réactivation : <a href="/plateforme/prodeli">console PRODELI (même application)</a>.</small></p>
</section>
</main>
</body>
</html>
