<?php
/**
 * SchoolSafe — parcours officiel d'ouverture d'etablissement (/demarrer).
 *
 * LOT L2 : porte d'entree unique des demandes d'ouverture. AUCUN moteur
 * metier ici : les demandes et les codes vivent dans les tables 019/032 et
 * transitent par le meme service officiel que les ecrans centraux
 * (schoolsafe_opening_requests, SchoolSafeOpeningCode*). La creation de
 * l'ecole reste reservee au parcours autorise (assistant -> CopySchool
 * natif) et l'approbation manuelle centrale reste la procedure de secours.
 *
 * - Deposer une demande SANS code : enregistree a « requested », examinee
 *   manuellement par l'equipe centrale. Jamais approuvee automatiquement.
 * - Presenter un code VALIDE : la demande liee passe a « approved »
 *   (consommation atomique, usage unique). Aucune ecole ni aucun compte
 *   n'est cree ici.
 *
 * Public : aucune session requise pour presenter un code (le code EST
 * l'autorisation). Aucun secret n'est reenvoye ni journalise.
 */

header( 'Cache-Control: no-store' );
header( 'X-Content-Type-Options: nosniff' );

chdir( __DIR__ );

require_once __DIR__ . '/Warehouse.php';

// Annee scolaire de l'application (contexte anonyme : pas de UserSyear()).
// Config('SYEAR') reste resolu avant toute session (comme sur index.php).
$demarrer_syear = (int) Config( 'SYEAR' );

if ( $demarrer_syear < 1 )
{
	$demarrer_syear = (int) ( $DefaultSyear ?? 2026 );
}

$token = (string) ( $_SESSION['token'] ?? '' );

$message = '';

$error = '';

if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) === 'POST' )
{
	if ( ! is_string( $_POST['token'] ?? null )
		|| ! hash_equals( $token, $_POST['token'] ) )
	{
		$error = 'Requête expirée. Rechargez la page.';
	}
	else
	{
		$posted_action = (string) ( $_POST['demarrer_action'] ?? '' );

		if ( $posted_action === 'authorize' )
		{
			$result = SchoolSafeOpeningCodeVerify(
				(int) ( $_POST['request_id'] ?? 0 ),
				(string) ( $_POST['email'] ?? '' ),
				(string) ( $_POST['code'] ?? '' )
			);

			if ( ! empty( $result['ok'] ) )
			{
				$message = 'Demande n°' . (int) $result['request_id']
					. ' autorisée. L’équipe SchoolSafe prépare votre école et '
					. 'remettra l’accès à son responsable en mains propres. '
					. 'Conservez votre numéro de demande.';
			}
			else
			{
				$labels = [
					'CODE_INVALID' => 'Code non reconnu.',
					'CODE_EXPIRED' => 'Code expiré. Demandez un nouveau code à l’équipe SchoolSafe.',
					'CODE_LOCKED' => 'Trop de tentatives. Demandez un nouveau code à l’équipe SchoolSafe.',
					'CODE_CONSUMED' => 'Code déjà utilisé.',
					'EMAIL_MISMATCH' => 'Demande introuvable avec ces informations.',
					'REQUEST_NOT_FOUND' => 'Demande introuvable avec ces informations.',
					'ALREADY_DECIDED' => 'Cette demande a déjà été traitée.',
					'NO_ACTIVE_CODE' => 'Aucun code actif pour cette demande. Demandez un code à l’équipe SchoolSafe.',
				];

				$error = $labels[ $result['error'] ?? '' ]
					?? 'Autorisation indisponible. Vérifiez vos informations.';

				if ( ( $result['error'] ?? '' ) === 'CODE_INVALID'
					&& isset( $result['remaining'] ) )
				{
					$error .= ' Tentatives restantes : ' . (int) $result['remaining'] . '.';
				}
			}
		}
		elseif ( $posted_action === 'request' )
		{
			$values = [];

			foreach (
				[
					'school_name' => 150,
					'person_name' => 150,
					'email' => 254,
				] as $field => $limit
			)
			{
				$value = trim( (string) ( $_POST[ $field ] ?? '' ) );

				if ( $value === '' || mb_strlen( $value ) > $limit )
				{
					$error = 'Renseignez les informations demandées.';

					break;
				}

				$values[ $field ] = $value;
			}

			if ( $error === ''
				&& ! filter_var( $values['email'], FILTER_VALIDATE_EMAIL ) )
			{
				$error = 'Adresse e-mail non valide.';
			}

			if ( $error === '' )
			{
				// Idempotence : un renvoi identique (meme ecole, meme e-mail,
				// moins de 24 h) retrouve la demande au lieu de la dupliquer.
				$duplicate = DBGetOne( "SELECT ID FROM schoolsafe_opening_requests
					WHERE UPPER(SCHOOL_NAME)=UPPER('" . DBEscapeString( $values['school_name'] ) . "')
					AND UPPER(EMAIL)=UPPER('" . DBEscapeString( $values['email'] ) . "')
					AND STATE='requested'
					AND CREATED_AT>CURRENT_TIMESTAMP - INTERVAL '1 day'
					ORDER BY ID DESC" );

				if ( $duplicate )
				{
					$message = 'Demande déjà enregistrée sous le n°' . (int) $duplicate
						. '. L’équipe SchoolSafe l’examine manuellement. '
						. 'Aucune approbation automatique sans code valide.';
				}
				else
				{
					$new_id = (int) DBInsert( 'schoolsafe_opening_requests', [
						'SYEAR' => $demarrer_syear,
						'SCHOOL_NAME' => $values['school_name'],
						'PERSON_NAME' => $values['person_name'],
						'EMAIL' => $values['email'],
					], 'id' );

					if ( $new_id > 0 )
					{
						DBQuery( "INSERT INTO schoolsafe_opening_events(request_id,action)
							VALUES(" . $new_id . ",'record')" );

						$message = 'Demande n°' . $new_id . ' enregistrée. '
							. 'L’équipe SchoolSafe l’examine manuellement. '
							. 'Aucune approbation automatique sans code valide.';
					}
					else
					{
						$error = 'Enregistrement indisponible. Réessayez plus tard.';
					}
				}
			}
		}
		else
		{
			$error = 'Requête expirée. Rechargez la page.';
		}
	}
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ouvrir une école | SchoolSafe</title>
<style>
body{font-family:system-ui,sans-serif;margin:0;background:#0b3b7a;color:#fff}
main{max-width:34rem;margin:0 auto;padding:1.25rem}
.card{background:#fff;color:#111;border-radius:.75rem;padding:1.25rem;margin:1rem 0}
label{display:block;margin:.6rem 0}
input{width:100%;box-sizing:border-box;padding:.6rem;font-size:1rem}
button{background:#0b3b7a;color:#fff;border:0;border-radius:.5rem;padding:.7rem 1.2rem;font-size:1rem}
.status{background:#e8f5e9;color:#111;border-radius:.5rem;padding:.7rem}
.alert{background:#fdecea;color:#111;border-radius:.5rem;padding:.7rem}
small{color:#cfe0ff}
.card small{color:#444}
</style>
</head>
<body>
<main>
<h1>Ouvrir une école avec SchoolSafe</h1>
<p><small>Parcours officiel d’ouverture. La création de l’école et de son
responsable est réalisée par l’équipe SchoolSafe après autorisation — aucun
compte ni mot de passe n’est remis sur cette page.</small></p>
<?php if ( $message !== '' ) : ?>
<p class="status" role="status"><?php echo htmlspecialchars( $message, ENT_QUOTES, 'UTF-8' ); ?></p>
<?php endif; ?>
<?php if ( $error !== '' ) : ?>
<p class="alert" role="alert"><?php echo htmlspecialchars( $error, ENT_QUOTES, 'UTF-8' ); ?></p>
<?php endif; ?>
<section class="card" aria-labelledby="t-authorize">
<h2 id="t-authorize">J’ai un code d’autorisation</h2>
<form method="post" action="/demarrer">
<input type="hidden" name="token" value="<?php echo htmlspecialchars( $token, ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="demarrer_action" value="authorize">
<label>Numéro de demande <input required inputmode="numeric" name="request_id" autocomplete="off"></label>
<label>E-mail de contact <input required type="email" name="email" autocomplete="email"></label>
<label>Code d’autorisation <input required name="code" autocomplete="off" placeholder="XXXXXX-XXXXXX-XXXXXX-XXXXXX"></label>
<p><button type="submit">Valider mon autorisation</button></p>
</form>
</section>
<section class="card" aria-labelledby="t-request">
<h2 id="t-request">Déposer une demande sans code</h2>
<p><small>Votre demande sera examinée manuellement. Sans code valide, aucune
approbation automatique n’est possible.</small></p>
<form method="post" action="/demarrer">
<input type="hidden" name="token" value="<?php echo htmlspecialchars( $token, ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="demarrer_action" value="request">
<label>Nom de l’établissement <input required maxlength="150" name="school_name"></label>
<label>Nom du futur responsable <input required maxlength="150" name="person_name"></label>
<label>E-mail de contact <input required type="email" maxlength="254" name="email"></label>
<p><button type="submit">Déposer ma demande</button></p>
</form>
</section>
<p><a style="color:#fff" href="/connexion">Déjà membre ? Se connecter</a></p>
</main>
</body>
</html>
