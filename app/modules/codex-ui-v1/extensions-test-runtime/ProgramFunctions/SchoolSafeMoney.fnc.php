<?php
/**
 * Contrôles communs des pages d'argent (lot F1, 05/11/2026, docs/qualification/ANALYSE_FINANCE_20261105.md).
 * Utilisés par Student_Billing et Accounting. Lecture seule : aucune donnée n'est modifiée ici.
 */

/**
 * F3 : ne garder que les colonnes que le formulaire a le droit d'écrire.
 * L'école, l'année, l'élève, l'auteur ou le remboursement ne viennent jamais du navigateur.
 *
 * @param array $columns Colonnes envoyées.
 * @param array $allowed Noms de colonnes autorisés (majuscules).
 *
 * @return array Colonnes autorisées seulement.
 */
function SchoolSafeMoneyColumns( $columns, $allowed )
{
	$kept = [];

	foreach ( (array) $columns as $name => $value )
	{
		if ( in_array( strtoupper( (string) $name ), $allowed, true ) )
		{
			$kept[ strtoupper( (string) $name ) ] = $value;
		}
	}

	return $kept;
}

/**
 * F4 : montant normalisé (virgule acceptée comme séparateur décimal, espaces retirés).
 */
function SchoolSafeMoneyAmount( $amount )
{
	return trim( str_replace( [ ' ', ',' ], [ '', '.' ], (string) $amount ) );
}

/**
 * F4 : un montant doit être un nombre strictement positif, avec au plus deux décimales.
 *
 * @return string Message d'erreur, ou '' si le montant est valable.
 */
function SchoolSafeMoneyAmountProblem( $amount )
{
	$amount = SchoolSafeMoneyAmount( $amount );

	if ( ! preg_match( '/^\d{1,12}(\.\d{1,2})?$/', $amount ) || (float) $amount <= 0 )
	{
		return 'Montant refusé : indiquez un nombre positif (au plus deux chiffres après la virgule).';
	}

	return '';
}

/**
 * F11 : la même opération vient-elle d'être enregistrée (double clic, renvoi du formulaire) ?
 * Même table, mêmes valeurs, créée il y a moins de 60 secondes.
 *
 * @param string $table  Table (nom fixe dans le code, jamais venu du navigateur).
 * @param array  $fields Colonne => valeur déjà sûre (entier ou texte échappé).
 */
function SchoolSafeMoneyRecentDuplicate( $table, $fields )
{
	$where = [];

	foreach ( $fields as $column => $value )
	{
		$where[] = $value === null ?
			$column . ' IS NULL' :
			$column . "='" . $value . "'";
	}

	return (bool) DBGetOne( "SELECT 1 FROM " . $table . "
		WHERE " . implode( ' AND ', $where ) . "
		AND CREATED_AT > CURRENT_TIMESTAMP - INTERVAL '60 seconds'
		LIMIT 1" );
}

/**
 * F2 / F9 : l'élève est-il inscrit dans l'école et l'année en cours ?
 */
function SchoolSafeMoneyStudentInSchool( $student_id )
{
	return (bool) DBGetOne( "SELECT 1 FROM student_enrollment
		WHERE STUDENT_ID='" . (int) $student_id . "'
		AND SCHOOL_ID='" . UserSchool() . "'
		AND SYEAR='" . UserSyear() . "'
		LIMIT 1" );
}

/**
 * F12 : reste dû d'un élève dans l'école et l'année en cours (frais moins paiements).
 */
function SchoolSafeMoneyStudentBalance( $student_id )
{
	$fees = (float) DBGetOne( "SELECT COALESCE(SUM(AMOUNT),0) FROM billing_fees
		WHERE STUDENT_ID='" . (int) $student_id . "'
		AND SCHOOL_ID='" . UserSchool() . "'
		AND SYEAR='" . UserSyear() . "'" );

	$payments = (float) DBGetOne( "SELECT COALESCE(SUM(AMOUNT),0) FROM billing_payments
		WHERE STUDENT_ID='" . (int) $student_id . "'
		AND SCHOOL_ID='" . UserSchool() . "'
		AND SYEAR='" . UserSyear() . "'" );

	return round( $fees - $payments, 2 );
}
