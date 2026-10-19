<?php
/**
 * SchoolSafe — les deux écrans bleus, intégrés à Rosario.
 *
 * Écran 1 : accueil (slogan, bouton « Commencer »).
 * Écran 2 : connexion par NOM D'UTILISATEUR et MOT DE PASSE.
 *
 * Le formulaire est celui de Rosario : mêmes champs (USERNAME, PASSWORD),
 * même action (index.php), même redirection. Aucune authentification
 * parallèle n'est introduite, et aucun code d'authentification n'est modifié.
 *
 * Attendu du parent : $ss_blue_messages (erreurs/notes déjà rendues),
 * $default_username / $default_password (préremplissage Rosario),
 * $RosarioLocales, $locale.
 */

if ( ! isset( $ss_blue_messages ) ) {
	$ss_blue_messages = '';
}

/**
 * Feuilles de style des deux écrans bleus, copiées fidèlement du prototype
 * validé et chargées dans son ordre d'origine : jetons, système, composants,
 * écran, soi-disant JASPE, variante « companion », puis notre adaptation
 * Rosario (blue.css) en dernier pour que ses rares exceptions gagnent.
 */
$ss_blue_styles = array(
	'design-tokens',
	'design-system',
	'components',
	'entry',
	'auth',
	'entree-visuel',
	'jaspe2d',
	'auth-companion',
	'auth-models',
	'visuel-global',
	'dashboard-liquid-glass',
	'blue',
);

foreach ( $ss_blue_styles as $ss_blue_style ) :

	?>
	<link rel="stylesheet" href="assets/login/blue/<?php echo $ss_blue_style; ?>.css?v=1">
	<?php

endforeach;

?>

<div id="ss-blue" data-start="<?php echo $ss_blue_messages !== '' ? 'auth' : 'splash'; ?>">

	<?php // Écran 1 — accueil. ?>
	<section class="screen splash" id="splash" aria-label="<?php echo AttrEscape( _( 'Bienvenue sur SchoolSafe' ) ); ?>">
		<div class="splash-bubbles" aria-hidden="true"><i class="b1"></i><i class="b2"></i><i class="b3"></i><i class="b4"></i></div>
		<p class="splash-note" aria-hidden="true"><i data-lucide="heart"></i><span><?php echo _( 'Des enfants plus en sécurité' ); ?><br><?php echo _( 'pour un meilleur demain' ); ?></span></p>
		<div class="splash-center">
			<img class="brand-shield splash-shield" src="schoolsafe-logo.png" alt="SchoolSafe">
			<p class="splash-tagline" aria-hidden="true"><i></i><?php echo _( "L'école en toute confiance" ); ?><i></i></p>
			<h1 class="splash-slogan" tabindex="-1"><span class="line"><?php echo _( 'Chaque enfant' ); ?> <em><?php echo _( 'protégé' ); ?></em>.</span><span class="line"><?php echo _( 'Chaque parent' ); ?> <em><?php echo _( 'informé' ); ?></em>.</span></h1>
			<p class="splash-description"><?php echo _( 'Présences, suivi scolaire et échanges avec les familles : votre école réunie dans un même espace.' ); ?></p>
			<button class="splash-tap" id="enterSplash" type="button"><span><?php echo _( 'Commencer' ); ?></span> <i data-lucide="arrow-right"></i></button>
			<div class="splash-country"><i></i><i data-lucide="shield"></i> <?php echo _( 'République démocratique du Congo' ); ?><i></i></div>
		</div>
	</section>

	<?php // Écran 2 — connexion par nom d'utilisateur et mot de passe. ?>
	<section class="screen auth-screen" id="auth" data-auth-view="presentation" data-login-layout="companion" aria-label="Connexion SchoolSafe">
		<header class="auth-topbar">
			<button class="brand-button" id="backToSplash" type="button" aria-label="Revenir à l'accueil">
				<span class="auth-shield" aria-hidden="true"><img src="schoolsafe-logo.png" alt=""></span>
				<span class="auth-wordmark">School<strong>Safe</strong></span>
			</button>
			<p class="auth-motto"><?php echo _( 'Ensemble pour une école' ); ?><br><?php echo _( 'plus sûre et plus connectée' ); ?></p>
			<div class="language-switch" role="group" aria-label="<?php echo AttrEscape( _( 'Language' ) ); ?>">
				<?php if ( isset( $RosarioLocales ) && count( $RosarioLocales ) > 1 ) : ?>
					<?php foreach ( $RosarioLocales as $ss_loc ) :
						$ss_language = function_exists( 'locale_get_display_language' ) ?
							ucfirst( locale_get_display_language( $ss_loc, $locale ) ) :
							str_replace( '.utf8', '', $ss_loc );
						$ss_current = mb_substr( $ss_loc, 0, 2 ) === mb_substr( (string) $locale, 0, 2 ); ?>
						<a href="index.php?locale=<?php echo AttrEscape( $ss_loc ); ?>" title="<?php echo AttrEscape( $ss_language ); ?>"<?php echo $ss_current ? ' class="selected" aria-pressed="true"' : ' aria-pressed="false"'; ?>>
							<img src="locale/<?php echo AttrEscape( $ss_loc ); ?>/flag.png" width="28" alt="<?php echo AttrEscape( $ss_language ); ?>">
						</a>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</header>

		<div class="auth-layout">
			<div class="auth-message">
				<h1><?php echo _( 'Bienvenue sur SchoolSafe' ); ?></h1>
				<p><?php echo _( 'Un espace plus sûr et plus connecté pour toute la communauté scolaire.' ); ?></p>
			</div>

			<aside class="auth-values" aria-label="Les engagements SchoolSafe">
				<ul>
					<li><span class="auth-value-icon"><i data-lucide="shield-check" aria-hidden="true"></i></span><div><strong><?php echo _( 'Protéger' ); ?></strong><p><?php echo _( 'Les élèves en sécurité à chaque étape' ); ?></p></div></li>
					<li><span class="auth-value-icon"><i data-lucide="graduation-cap" aria-hidden="true"></i></span><div><strong><?php echo _( 'Éduquer' ); ?></strong><p><?php echo _( 'Des outils pour apprendre et grandir' ); ?></p></div></li>
					<li><span class="auth-value-icon"><i data-lucide="users" aria-hidden="true"></i></span><div><strong><?php echo _( 'Connecter' ); ?></strong><p><?php echo _( 'Une école plus proche des familles' ); ?></p></div></li>
					<li><span class="auth-value-icon"><i data-lucide="heart" aria-hidden="true"></i></span><div><strong><?php echo _( 'Pour demain' ); ?></strong><p><?php echo _( 'Un avenir meilleur ensemble' ); ?></p></div></li>
				</ul>
				<p class="auth-signature"><?php echo _( 'Avec vous,' ); ?><br><?php echo _( 'pour leur avenir' ); ?></p>
			</aside>

			<div class="auth-jaspe" aria-hidden="true"><div class="jaspe2d"><img src="assets/login/jaspe-backpack-v2/pose-01.png" alt=""></div></div>

			<form class="login-panel" id="loginform" name="loginform" method="post" action="index.php">
				<div class="panel-brand">
					<span class="auth-panel-person" aria-hidden="true"><i data-lucide="user-round"></i></span>
					<span class="auth-panel-logo" aria-hidden="true"><span class="auth-shield" aria-hidden="true"><img src="schoolsafe-logo.png" alt=""></span><span class="auth-wordmark">School<strong>Safe</strong></span></span>
					<div><b><?php echo _( 'Connexion' ); ?></b><span><?php echo _( "Accédez à votre établissement" ); ?></span></div>
				</div>

				<?php if ( $ss_blue_messages !== '' ) : ?>
					<div class="ss-blue-messages"><?php echo $ss_blue_messages; ?></div>
				<?php endif; ?>

				<div class="ss-field">
					<label class="ss-label" for="USERNAME"><?php echo _( "Nom d'utilisateur" ); ?></label>
					<div class="ss-input-shell">
						<i data-lucide="user-round"></i>
						<input class="ss-input" id="USERNAME" name="USERNAME" type="text" size="20" maxlength="100" autocomplete="username" value="<?php echo AttrEscape( $default_username ); ?>" required<?php echo $default_username ? '' : ' autofocus'; ?>>
					</div>
				</div>

				<div class="label-row">
					<label class="ss-label" for="PASSWORD"><?php echo _( 'Mot de passe' ); ?></label>
					<a class="text-button" href="PasswordReset.php" rel="nofollow"><?php echo _( 'Mot de passe oublié ?' ); ?></a>
				</div>

				<div class="ss-input-shell">
					<i data-lucide="lock-keyhole"></i>
					<input class="ss-input" id="PASSWORD" name="PASSWORD" type="password" size="20" maxlength="42" autocomplete="current-password" value="<?php echo AttrEscape( $default_password ); ?>" required>
					<button class="ss-icon-button" id="ssBlueEye" type="button" aria-label="<?php echo AttrEscape( _( 'Afficher le mot de passe' ) ); ?>" aria-pressed="false" title="<?php echo AttrEscape( _( 'Afficher le mot de passe' ) ); ?>"><i data-lucide="eye"></i></button>
				</div>

				<button class="ss-button ss-button--primary login-button" type="submit"><?php echo _( 'Continuer' ); ?> <i data-lucide="arrow-right"></i></button>

				<p class="ss-help-text"><?php echo _( "Votre nom d'utilisateur vous est remis par votre établissement." ); ?></p>

				<?php
				// @since 7.6 Login form link action hook.
				do_action( 'index.php|login_form_link' );

				if ( Config( 'CREATE_USER_ACCOUNT' ) || Config( 'CREATE_STUDENT_ACCOUNT' ) ) : ?>

					<div class="ss-blue-secondary">
						<?php if ( Config( 'CREATE_USER_ACCOUNT' ) ) : ?>
							<a href="index.php?create_account=user&amp;staff_id=new" rel="nofollow"><?php echo _( 'Create User Account' ); ?></a>
						<?php endif; ?>
						<?php if ( Config( 'CREATE_STUDENT_ACCOUNT' ) ) : ?>
							<a href="index.php?create_account=student&amp;student_id=new" rel="nofollow"><?php echo _( 'Create Student Account' ); ?></a>
						<?php endif; ?>
					</div>

				<?php endif; ?>

				<?php if ( ! empty( $_REQUEST['redirect_to'] ) ) : ?>
					<input type="hidden" name="redirect_to" value="<?php echo URLEscape( $_REQUEST['redirect_to'] ); ?>">
				<?php endif; ?>
			</form>
		</div>

		<footer class="auth-footer">
			<span><i data-lucide="shield-check"></i> <?php echo _( 'Accès protégé' ); ?></span>
			<span>SchoolSafe</span>
		</footer>

		<div class="ss-blue-legal">
			<details class="about-schoolsafe">
				<summary>SchoolSafe</summary>
				<p>
					<?php
					echo sprintf(
						_( 'This is a restricted network. Use of this network, its equipment, and resources is monitored at all times and requires explicit permission from the network administrator and %s. If you do not have this permission in writing, you are violating the regulations of this network and can and will be prosecuted to the full extent of the law. By continuing into this system, you are acknowledging that you are aware of and agree to these terms.'),
						SchoolSafeBrandText( ParseMLField( Config( 'TITLE' ) ) )
					);
					?>
				</p>
			</details>
			<?php
			// Original notices: Copyright 2004-2009 The Miller Group & Learners Circle.
			// Copyright 2012-2026 RosarioSIS, https://www.rosariosis.org.
			?>
			<p><?php echo _( 'SchoolSafe est fourni sans garantie. Redistribution selon la GPL v2.' ); ?>
				<a href="schoolsafe-licenses.php"><?php echo _( 'Licences et mentions légales' ); ?></a></p>
		</div>
	</section>
</div>

<script src="assets/login/lucide.min.js"></script>
<script src="assets/login/blue/blue.js?v=1"></script>
