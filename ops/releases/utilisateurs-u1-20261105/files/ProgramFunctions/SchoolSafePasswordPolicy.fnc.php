<?php
/**
 * SchoolSafe — règle de mot de passe vérifiée sur le serveur (lot U1, 05/11/2026).
 *
 * Avant ce lot, la robustesse n'était contrôlée que dans le navigateur
 * (functions/Inputs.php) : un envoi direct pouvait la contourner.
 * Règle : au moins 8 caractères avec une lettre et un chiffre, ou au moins
 * 12 caractères ; jamais égal à l'identifiant. Le mot de passe temporaire de
 * l'assistant (12 caractères) la respecte toujours.
 *
 * @see docs/qualification/ANALYSE_UTILISATEURS_20261105.md (A1, A4)
 */

/**
 * Raison du refus, ou chaîne vide si le mot de passe est accepté.
 *
 * @param string $password Mot de passe en clair.
 * @param string $username Identifiant du compte (facultatif).
 *
 * @return string
 */
function SchoolSafePasswordProblem( $password, $username = '' )
{
	$password = (string) $password;
	$length = function_exists( 'mb_strlen' ) ? mb_strlen( $password, 'UTF-8' ) : strlen( $password );

	if ( $length < 8 )
	{
		return 'Le mot de passe doit contenir au moins 8 caractères.';
	}

	if ( $username !== '' && mb_strtolower( $password, 'UTF-8' ) === mb_strtolower( (string) $username, 'UTF-8' ) )
	{
		return 'Le mot de passe ne doit pas être identique au nom d’utilisateur.';
	}

	$has_letter = (bool) preg_match( '/\pL/u', $password );
	$has_digit = (bool) preg_match( '/\d/', $password );

	if ( $length < 12 && ! ( $has_letter && $has_digit ) )
	{
		return 'Le mot de passe doit contenir une lettre et un chiffre (ou au moins 12 caractères).';
	}

	return '';
}
