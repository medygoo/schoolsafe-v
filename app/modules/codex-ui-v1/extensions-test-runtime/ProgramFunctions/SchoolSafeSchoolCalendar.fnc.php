<?php
/**
 * SchoolSafe — cohérence du calendrier scolaire d'une école.
 *
 * Le natif attend une hiérarchie de périodes : l'année (FY) n'a pas de parent,
 * le semestre (SEM) dépend de l'année, le trimestre (QTR) dépend du semestre et
 * la période de progrès (PRO) dépend du trimestre. Quand cette hiérarchie est
 * incomplète — cas réel d'une école créée sans parents — les écrans qui listent
 * les périodes produisent des valeurs vides et PostgreSQL refuse la requête.
 *
 * La règle est écrite UNE SEULE FOIS ici, pour deux usages :
 *   - la création d'une école refuse de recopier un gabarit incohérent ;
 *   - la console du propriétaire (PRODELI) affiche l'état réel de chaque école.
 *
 * Lecture seule : aucune table, aucune donnée n'est modifiée.
 */

/**
 * Périodes d'une école pour une année scolaire.
 *
 * @param int $school_id École.
 * @param int $syear     Année scolaire.
 *
 * @return array Lignes (MARKING_PERIOD_ID, MP, PARENT_ID, TITLE).
 */
function SchoolSafeSchoolCalendarPeriods( $school_id, $syear )
{
	return DBGet( "SELECT MARKING_PERIOD_ID,MP,PARENT_ID,TITLE
		FROM school_marking_periods
		WHERE SCHOOL_ID='" . (int) $school_id . "'
		AND SYEAR='" . (int) $syear . "'
		ORDER BY SORT_ORDER IS NULL,SORT_ORDER,START_DATE" );
}

/**
 * Parent attendu pour chaque type de période.
 *
 * @return array Type => type du parent.
 */
function SchoolSafeSchoolCalendarParents()
{
	return [
		'SEM' => 'FY',
		'QTR' => 'SEM',
		'PRO' => 'QTR',
	];
}

/**
 * Anomalies d'un ensemble de périodes.
 *
 * @param array $periods Lignes telles que renvoyées par SchoolSafeSchoolCalendarPeriods().
 *
 * @return array Liste d'anomalies : [ 'code', 'label' ].
 */
function SchoolSafeSchoolCalendarPeriodIssues( $periods )
{
	$issues = [];

	if ( ! $periods )
	{
		return [ [
			'code' => 'no_periods',
			'label' => 'Aucune période (année, semestres, trimestres) : les écrans scolaires resteront vides.',
		] ];
	}

	$by_id = [];

	foreach ( $periods as $period )
	{
		$by_id[(int) $period['MARKING_PERIOD_ID']] = $period;
	}

	$parents = SchoolSafeSchoolCalendarParents();

	foreach ( $periods as $period )
	{
		$id = (int) $period['MARKING_PERIOD_ID'];
		$mp = (string) $period['MP'];
		$title = trim( (string) $period['TITLE'] ) !== '' ? trim( (string) $period['TITLE'] ) : ( 'période ' . $id );
		$parent_id = (int) $period['PARENT_ID'];

		if ( $mp === 'FY' )
		{
			if ( $parent_id > 0 )
			{
				$issues[] = [
					'code' => 'unexpected_parent',
					'label' => '« ' . $title . ' » (Année) ne doit pas avoir de période parente.',
				];
			}

			continue;
		}

		if ( ! isset( $parents[ $mp ] ) )
		{
			// Type inconnu du coeur natif : rien à vérifier ici.
			continue;
		}

		if ( $parent_id < 1 )
		{
			$issues[] = [
				'code' => 'parent_missing',
				'label' => '« ' . $title . ' » (' . SchoolSafeSchoolCalendarTypeName( $mp )
					. ') n’a pas de période parente de type '
					. SchoolSafeSchoolCalendarTypeName( $parents[ $mp ] ) . '.',
			];

			continue;
		}

		if ( ! isset( $by_id[ $parent_id ] ) )
		{
			$issues[] = [
				'code' => 'parent_foreign',
				'label' => '« ' . $title . ' » (' . SchoolSafeSchoolCalendarTypeName( $mp )
					. ') dépend d’une période absente de cette école.',
			];

			continue;
		}

		if ( (string) $by_id[ $parent_id ]['MP'] !== $parents[ $mp ] )
		{
			$issues[] = [
				'code' => 'parent_type',
				'label' => '« ' . $title . ' » (' . SchoolSafeSchoolCalendarTypeName( $mp )
					. ') dépend d’une période de type '
					. SchoolSafeSchoolCalendarTypeName( (string) $by_id[ $parent_id ]['MP'] )
					. ' ; type attendu : ' . SchoolSafeSchoolCalendarTypeName( $parents[ $mp ] ) . '.',
			];
		}
	}

	return $issues;
}

/**
 * Nom lisible d'un type de période.
 *
 * @param string $mp Type natif (FY, SEM, QTR, PRO).
 *
 * @return string
 */
function SchoolSafeSchoolCalendarTypeName( $mp )
{
	$names = [
		'FY' => 'Année',
		'SEM' => 'Semestre',
		'QTR' => 'Trimestre',
		'PRO' => 'Période de progrès',
	];

	return $names[ $mp ] ?? 'Période';
}

/**
 * Anomalies d'une école.
 *
 * @param int $school_id École.
 * @param int $syear     Année scolaire.
 *
 * @return array Liste d'anomalies : [ 'code', 'label' ].
 */
function SchoolSafeSchoolCalendarIssues( $school_id, $syear )
{
	return SchoolSafeSchoolCalendarPeriodIssues(
		SchoolSafeSchoolCalendarPeriods( $school_id, $syear )
	);
}

/**
 * État du calendrier pour plusieurs écoles, en une seule requête.
 *
 * @param array $school_ids Identifiants d'écoles.
 * @param int   $syear      Année scolaire.
 *
 * @return array ID => [ 'ok' => bool, 'issues' => array ].
 */
function SchoolSafeSchoolCalendarStatuses( $school_ids, $syear )
{
	$ids = [];

	foreach ( (array) $school_ids as $school_id )
	{
		if ( (int) $school_id > 0 )
		{
			$ids[ (int) $school_id ] = true;
		}
	}

	if ( ! $ids )
	{
		return [];
	}

	$rows = DBGet( "SELECT SCHOOL_ID,MARKING_PERIOD_ID,MP,PARENT_ID,TITLE
		FROM school_marking_periods
		WHERE SYEAR='" . (int) $syear . "'
		AND SCHOOL_ID IN(" . implode( ',', array_keys( $ids ) ) . ")
		ORDER BY SCHOOL_ID,SORT_ORDER IS NULL,SORT_ORDER,START_DATE" );

	$grouped = [];

	foreach ( (array) $rows as $row )
	{
		$grouped[ (int) $row['SCHOOL_ID'] ][] = $row;
	}

	$statuses = [];

	foreach ( array_keys( $ids ) as $school_id )
	{
		$issues = SchoolSafeSchoolCalendarPeriodIssues( $grouped[ $school_id ] ?? [] );

		$statuses[ $school_id ] = [
			'ok' => ! $issues,
			'issues' => $issues,
		];
	}

	return $statuses;
}
