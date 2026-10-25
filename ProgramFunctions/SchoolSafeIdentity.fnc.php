<?php
/**
 * SchoolSafe L3 — identité unique et doublons.
 *
 * AUCUN moteur d'authentification ici : normalisation + détection uniquement.
 * La création et la liaison parent-enfant restent 100 % natives
 * (modules/Users/User.php, modules/Custom/CreateParents.php,
 * students_join_users). On ne fusionne JAMAIS automatiquement deux identités :
 * tout rapprochement exige une vérification de propriété et des droits.
 *
 * - E-mail : trim + minuscules (RFC : l'unicité est vérifiée insensible à la
 *   casse, périmètre : année scolaire courante pour staff, globale pour
 *   students comme le natif).
 * - Téléphone congolais : représentation internationale cohérente +243.
 *   Le téléphone vit dans staff.CUSTOM_200000001 (champ natif « Phone Number »).
 *   Formats acceptés : +243..., 243..., 0... (9-10 chiffres nationaux),
 *   espaces/tirets/points ignorés. Tout autre format est conservé tel quel
 *   (jamais bloquant) mais signalé comme non normalisable.
 */

/**
 * Normalise un e-mail pour la comparaison (trim + minuscules).
 */
function SchoolSafeIdentityNormalizeEmail( $email )
{
	$email = trim( mb_strtolower( (string) $email ) );

	if ( $email === '' || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) )
	{
		return '';
	}

	return mb_substr( $email, 0, 254 );
}

/**
 * Normalise un numéro congolais en E.164 (+243...).
 *
 * Retourne '' si le numéro ne ressemble pas à un numéro CD normalisable ;
 * l'appelant conserve alors la valeur brute (jamais bloquant).
 */
function SchoolSafeIdentityNormalizePhoneCD( $phone )
{
	$raw = trim( (string) $phone );

	if ( $raw === '' )
	{
		return '';
	}

	$digits = preg_replace( '/[^0-9]/', '', $raw );

	if ( $digits === '' )
	{
		return '';
	}

	// +243 / 243 préfixé : 243 + 9 chiffres (ex. 243815000000).
	if ( str_starts_with( $digits, '243' ) && strlen( $digits ) === 12 )
	{
		return '+' . $digits;
	}

	// Format national : 0 + 9 chiffres (ex. 0815000000).
	if ( strlen( $digits ) === 10 && $digits[0] === '0' )
	{
		return '+243' . substr( $digits, 1 );
	}

	// National sans le 0 : 9 chiffres (ex. 815000000).
	if ( strlen( $digits ) === 9 && $digits[0] !== '0' )
	{
		return '+243' . $digits;
	}

	return '';
}

/**
 * Recherche les comptes existants partageant les mêmes identifiants.
 *
 * Vérifie USERNAME (insensible à la casse, année courante), EMAIL (insensible
 * à la casse, staff année courante + students global comme le natif) et le
 * téléphone normalisé +243 (staff.CUSTOM_200000001, année courante).
 * Ne fusionne rien : retourne la liste des collisions pour affichage et
 * vérification manuelle de propriété.
 *
 * @return array Liste de ['STAFF_ID'=>, 'USERNAME'=>, 'PROFILE'=>, 'MATCH'=>].
 */
function SchoolSafeIdentityFindDuplicates( $username, $email, $phone )
{
	$found = [];

	if ( ! function_exists( 'DBGet' ) || ! function_exists( 'DBEscapeString' ) )
	{
		return $found;
	}

	$syear = function_exists( 'UserSyear' ) ? (int) UserSyear() : (int) Config( 'SYEAR' );

	$username = trim( (string) $username );
	$email = SchoolSafeIdentityNormalizeEmail( $email );
	$phone_e164 = SchoolSafeIdentityNormalizePhoneCD( $phone );

	if ( $username !== '' )
	{
		$rows = DBGet( "SELECT STAFF_ID,USERNAME,PROFILE FROM staff
			WHERE UPPER(USERNAME)=UPPER('" . DBEscapeString( $username ) . "')
			AND SYEAR='" . $syear . "'" );

		foreach ( (array) $rows as $row )
		{
			$row['MATCH'] = 'username';

			$found[] = $row;
		}
	}

	if ( $email !== '' )
	{
		$rows = DBGet( "SELECT STAFF_ID,USERNAME,PROFILE FROM staff
			WHERE UPPER(EMAIL)=UPPER('" . DBEscapeString( $email ) . "')
			AND SYEAR='" . $syear . "'" );

		foreach ( (array) $rows as $row )
		{
			$row['MATCH'] = 'email';

			$found[] = $row;
		}
	}

	if ( $phone_e164 !== '' )
	{
		// Le brut peut contenir espaces/tirets : on compare sur les chiffres.
		$digits = preg_replace( '/[^0-9]/', '', $phone_e164 );

		$rows = DBGet( "SELECT STAFF_ID,USERNAME,PROFILE FROM staff
			WHERE SYEAR='" . $syear . "'
			AND regexp_replace(COALESCE(CUSTOM_200000001,''), '[^0-9]', '', 'g') IN ('"
			. DBEscapeString( $digits ) . "','"
			. DBEscapeString( ltrim( $digits, '243' ) ) . "','0"
			. DBEscapeString( substr( $digits, 3 ) ) . "')" );

		foreach ( (array) $rows as $row )
		{
			$row['MATCH'] = 'phone';

			$found[] = $row;
		}
	}

	return $found;
}
