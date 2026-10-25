<?php
/**
 * SchoolSafe L1 — preuve directe de la garde d'écriture.
 *
 * Amorce l'application réelle (Warehouse.php + PostgreSQL) avec une session
 * fabriquée pour un compte donné, puis affiche le résultat de AllowEdit(),
 * le point d'entrée unique de toutes les écritures natives.
 *
 * Usage (conteneur applicatif, base réelle) :
 *   docker exec schoolsafe-l1-test php /var/www/html/schoolsafe-remediation/tests/account-state-allow-edit.php <STAFF_ID>
 *
 * Sortie : une ligne JSON { username, profile, school, allow_edit, allow_edit_other }.
 * Aucune écriture, aucun envoi de courriel : lecture seule.
 */

$staff_id = (int) ( $argv[1] ?? 0 );

// Chaîne de requête à rejouer (optionnelle) : permet de vérifier AllowUse(),
// qui normalise le MODNAME à partir de $_REQUEST et de QUERY_STRING.
$query = (string) ( $argv[2] ?? '' );

$_SERVER['REQUEST_METHOD'] = $query ? 'POST' : 'GET';
$_SERVER['QUERY_STRING'] = $query;
$_SERVER['REQUEST_URI'] = '/Modules.php' . ( $query ? '?' . $query : '?modname=Users/User.php' );

if ( $query )
{
	parse_str( $query, $_REQUEST );
}
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'qa-l1-allow-edit';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SCRIPT_NAME'] = '/Modules.php';
$_SERVER['SERVER_PORT'] = '80';

chdir( dirname( __DIR__, 2 ) );

// Les avertissements « session déjà active » sont attendus : Warehouse.php
// reconfigure une session que ce harnais a déjà ouverte pour la fabriquer.
error_reporting( 0 );

// Session fabriquée : c'est le seul écart avec une requête HTTP réelle.
session_start();

$_SESSION['STAFF_ID'] = $staff_id;
$_SESSION['UserSyear'] = '2026';
$_SESSION['DefaultSyear'] = '2026';
$_SESSION['token'] = 'qa-l1';

// UserSchool n'est PAS forcé : l'application choisit l'école du compte, comme
// lors d'une connexion réelle (l'état est porté par personne ET par école).

ob_start();

require 'Warehouse.php';

ob_end_clean();

$result = [
	'staff_id' => $staff_id,
	'username' => User( 'USERNAME' ),
	'profile' => User( 'PROFILE' ),
	'profile_id' => User( 'PROFILE_ID' ),
	'school' => UserSchool(),
	'allow_edit_own_screen' => AllowEdit( 'Users/User.php' ),
	'allow_edit_other_screen' => AllowEdit( 'Students/Student.php' ),
];

if ( $query )
{
	$result['query_string'] = $_SERVER['QUERY_STRING'];
	$result['allow_use'] = AllowUse();
	$result['program_loaded'] = $_ROSARIO['ProgramLoaded'] ?? null;
}

echo json_encode( $result, JSON_UNESCAPED_UNICODE ) . "\n";
