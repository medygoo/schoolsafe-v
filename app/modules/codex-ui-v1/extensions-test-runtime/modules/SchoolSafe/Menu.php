<?php
/**
 * Menu SchoolSafe — regroupé en sections (2026-10-28, GO du propriétaire).
 * Les sections utilisent le format natif RosarioSIS (sous-tableaux, comme le
 * menu Notes > Reports/Setup). Les clés du catalogue absentes du regroupement
 * restent affichées au premier niveau (extensible sans toucher ce fichier).
 */
require_once __DIR__ . '/Permissions.php';

$ss_sections = [
	'Présence & Scans' => [
		'ArrivalAttendance', 'ScanArrival', 'ScanPickup', 'ScanFees', 'ScanCanteen',
		'ScanVerify', 'ScanLocationsRead', 'ScanLocationsManage',
		'DelegationRead', 'DelegationManage',
	],
	'Familles & Récupération' => [
		'FamilyRead', 'FamilyRegister', 'FamilyApprove', 'FamilyReplace', 'ParentToggle',
	],
	'Cartes & Impressions' => [
		'Studio', 'IssueCard', 'PrintRequest',
	],
	'Notifications' => [
		'Notifications',
	],
	'Rattrapage' => [
		'RemediationRead', 'RemediationMap', 'RemediationDecide', 'RemediationSchedule',
		'RemediationFollowup', 'RemediationReassess',
	],
	'Frais & Paiements' => [
		'FeeModelsRead', 'FeeModelsManage', 'RemediationBilling',
		'RemediationPaymentsRead', 'RemediationPaymentsRecord', 'RemediationPaymentsManage',
	],
	'Équipements' => [
		'DevicesRead', 'DevicesManage',
	],
];

$ss_catalog = SchoolSafePermissionCatalog();

$ss_section_of = [];
foreach ( $ss_sections as $ss_label => $ss_keys )
{
	foreach ( $ss_keys as $ss_key )
	{
		$ss_section_of[$ss_key] = $ss_label;
	}
}

$ss_placed = [];

// D'abord les sections, dans l'ordre voulu.
foreach ( $ss_sections as $ss_label => $ss_keys )
{
	foreach ( $ss_keys as $ss_key )
	{
		if ( ! isset( $ss_catalog[$ss_key] ) ) continue;
		foreach ( $ss_catalog[$ss_key]['profiles'] as $ss_profile )
		{
			$menu['SchoolSafe'][$ss_profile]['title'] = 'SchoolSafe';
			$menu['SchoolSafe'][$ss_profile][$ss_label]['SchoolSafe/' . $ss_key . '.php'] = $ss_catalog[$ss_key]['title'];
			$ss_placed[$ss_profile][$ss_key] = true;
		}
	}
}

// Puis les clés hors regroupement (au premier niveau, comportement d'origine).
foreach ( $ss_catalog as $ss_key => $ss_definition )
{
	foreach ( $ss_definition['profiles'] as $ss_profile )
	{
		$menu['SchoolSafe'][$ss_profile]['title'] = 'SchoolSafe';
		if ( isset( $ss_placed[$ss_profile][$ss_key] ) ) continue;
		$menu['SchoolSafe'][$ss_profile]['SchoolSafe/' . $ss_key . '.php'] = $ss_definition['title'];
	}
}
