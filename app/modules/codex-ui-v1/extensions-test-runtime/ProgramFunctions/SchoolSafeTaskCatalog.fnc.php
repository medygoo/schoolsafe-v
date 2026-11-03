<?php
/**
 * Catalogue des tâches pour l'écran « Profils de l'école » : présentation seulement.
 * Les clés (MODNAME natifs de profile_exceptions) et les contrôles serveur sont
 * inchangés ; on traduit chaque clé en tâche lisible, regroupée par métier.
 * Libellés : titres des menus natifs Rosario (traduits par gettext), catalogue SchoolSafe.
 */

// Clés techniques sans module (« ArrivalAttendance », « ArrivalAttendance.php ») :
// restes d'un ancien affichage, jamais consultés par les contrôles de droits.
function SchoolSafeTaskIsReal( $mod )
{
	return strpos( (string) $mod, '/' ) !== false;
}

function SchoolSafeTaskNativeTitles()
{
	static $titles = null;

	if ( $titles !== null )
	{
		return $titles;
	}

	$titles = [];
	$collect = function( $file )
	{
		global $RosarioModules;
		$menu = [];
		try { @include $file; } catch ( Throwable $e ) { return []; }
		return $menu;
	};

	foreach ( glob( 'modules/*/Menu.php' ) as $file )
	{
		foreach ( (array) $collect( $file ) as $profiles )
		{
			foreach ( (array) $profiles as $items )
			{
				foreach ( (array) $items as $key => $value )
				{
					if ( is_string( $key ) && $key !== 'title' && $key !== 'default' && is_string( $value ) && ! isset( $titles[ $key ] ) )
					{
						$titles[ $key ] = $value;
					}
					elseif ( is_array( $value ) )
					{
						foreach ( $value as $sub_key => $sub_value )
						{
							if ( is_string( $sub_key ) && is_string( $sub_value ) && ! isset( $titles[ $sub_key ] ) )
							{
								$titles[ $sub_key ] = $sub_value;
							}
						}
					}
				}
			}
		}
	}

	return $titles;
}

function SchoolSafeTaskLabel( $mod )
{
	static $categories = null;
	$mod = (string) $mod;
	$special = [
		'Users/User.php&category_id=1&schools' => 'Modifier les écoles d’un utilisateur',
		'Users/User.php&category_id=1&user_profile' => 'Changer le profil d’un utilisateur',
		'Entry_Exit/Checkpoints.php' => 'Points de passage (portails)',
		'Entry_Exit/EveningLeave.php' => 'Sorties du soir',
		'Entry_Exit/MassAddEveningLeaves.php' => 'Sorties du soir en groupe',
		'Entry_Exit/PackageDelivery.php' => 'Colis déposés pour un élève',
		'Student_Billing/Fees.php' => 'Frais des élèves (accès particulier)',
		'Discipline/ReferralForm.php' => 'Formulaire de rapport disciplinaire',
		'Grades/FixGPA.php' => 'Recalculer les moyennes',
		'Student_ID_Card/StudentIDCard.php' => 'Carte d’identité scolaire',
		'Messaging/Messages.php' => 'Messagerie interne — lire',
		'Messaging/Write.php' => 'Messagerie interne — écrire',
	];

	if ( isset( $special[ $mod ] ) )
	{
		return $special[ $mod ];
	}

	if ( function_exists( 'SchoolSafePermissionCatalog' ) )
	{
		$catalog = SchoolSafePermissionCatalog();

		if ( isset( $catalog[ $mod ]['title'] ) )
		{
			return $catalog[ $mod ]['title'];
		}
	}

	$titles = SchoolSafeTaskNativeTitles();
	$base = preg_replace( '/&.*$/', '', $mod );
	$suffix = '';

	if ( strpos( $mod, '&modfunc=remove' ) !== false )
	{
		$suffix = ' — supprimer';
	}

	if ( preg_match( '/^(Students\/Student|Users\/User)\.php&category_id=([0-9]+)$/', $mod, $m ) )
	{
		if ( $categories === null )
		{
			$categories = [];

			foreach ( (array) DBGet( "SELECT 'S' AS T,ID,TITLE FROM student_field_categories UNION ALL SELECT 'U',ID,TITLE FROM staff_field_categories" ) as $row )
			{
				$categories[ $row['T'] . $row['ID'] ] = ParseMLField( $row['TITLE'] );
			}
		}

		$key = ( $m[1] === 'Students/Student' ? 'S' : 'U' ) . $m[2];

		return ( $m[1] === 'Students/Student' ? 'Fiche élève' : 'Fiche utilisateur' ) . ' — onglet « '
			. ( $categories[ $key ] ?? $m[2] ) . ' »';
	}

	if ( isset( $titles[ $mod ] ) )
	{
		return $titles[ $mod ] . $suffix;
	}

	if ( isset( $titles[ $base ] ) )
	{
		return $titles[ $base ] . ( $suffix ?: ( $base !== $mod ? ' (accès particulier)' : '' ) );
	}

	return $mod;
}

function SchoolSafeTaskGroup( $mod )
{
	$mod = (string) $mod;
	$program = preg_replace( '/^SchoolSafe\//', '', preg_replace( '/[.&].*$/', '', $mod ) );
	$module = strtok( $mod, '/' );

	if ( $module === 'SchoolSafe' )
	{
		if ( preg_match( '/^(Family|ParentToggle)/', $program ) ) return 'Élèves et familles';
		if ( preg_match( '/^(Studio|IssueCard|PrintRequest)/', $program ) ) return 'Cartes';
		if ( preg_match( '/^(FeeModels|RemediationBilling|RemediationPayments)/', $program ) ) return 'Finances';
		if ( $program === 'ScanCanteen' ) return 'Cantine';
		if ( preg_match( '/^Remediation/', $program ) ) return 'Scolarité';
		if ( $program === 'Notifications' ) return 'Communication';
		return 'Présence et sécurité';
	}

	if ( $mod === 'School_Setup/PortalNotes.php' || $mod === 'School_Setup/PortalPolls.php' ) return 'Communication';
	if ( $mod === 'Custom/AttendanceSummary.php' ) return 'Présence et sécurité';
	if ( in_array( $mod, [ 'Custom/CreateParents.php', 'Custom/NotifyParents.php', 'Custom/Registration.php' ], true ) ) return 'Élèves et familles';
	if ( $mod === 'Custom/RemoveAccess.php' ) return 'Personnel et comptes';

	$groups = [
		'Students' => 'Élèves et familles', 'Medical_Report' => 'Élèves et familles',
		'Users' => 'Personnel et comptes',
		'Attendance' => 'Présence et sécurité', 'Entry_Exit' => 'Présence et sécurité',
		'Student_Billing' => 'Finances', 'Accounting' => 'Finances',
		'Food_Service' => 'Cantine',
		'Grades' => 'Scolarité', 'Scheduling' => 'Scolarité', 'Eligibility' => 'Scolarité', 'Discipline' => 'Scolarité',
		'SchoolSafe_Jaspe' => 'Scolarité',
		'Student_ID_Card' => 'Cartes',
		'Resources' => 'Communication', 'Messaging' => 'Communication',
		'School_Setup' => 'Paramètres de l’école',
	];

	return $groups[ $module ] ?? 'Autres tâches';
}

// Rendu des lignes groupées. Les noms de champs restent droit[MODNAME] / edit[MODNAME].
function SchoolSafeTaskRows( array $mods, callable $use_checked, callable $edit_checked )
{
	$order = [ 'Élèves et familles', 'Personnel et comptes', 'Présence et sécurité', 'Finances', 'Cantine',
		'Scolarité', 'Cartes', 'Communication', 'Paramètres de l’école', 'Autres tâches' ];
	$grouped = array_fill_keys( $order, [] );

	foreach ( $mods as $mod )
	{
		if ( SchoolSafeTaskIsReal( $mod ) )
		{
			$grouped[ SchoolSafeTaskGroup( $mod ) ][] = [ $mod, SchoolSafeTaskLabel( $mod ) ];
		}
	}

	$esc = function( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); };
	$html = '';

	foreach ( $grouped as $group => $rows )
	{
		if ( ! $rows )
		{
			continue;
		}

		usort( $rows, function( $a, $b ) { return strcoll( $a[1], $b[1] ); } );
		$html .= '<tr class="ss-task-group"><th colspan="3">' . $esc( $group ) . '</th></tr>';

		foreach ( $rows as $row )
		{
			$html .= '<tr><td title="' . $esc( $row[0] ) . '">' . $esc( $row[1] ) . '</td>'
				. '<td><input type="checkbox" name="droit[' . $esc( $row[0] ) . ']" value="Y" aria-label="Voir : ' . $esc( $row[1] ) . '"'
				. ( $use_checked( $row[0] ) ? ' checked' : '' ) . '></td>'
				. '<td><input type="checkbox" name="edit[' . $esc( $row[0] ) . ']" value="Y" aria-label="Modifier : ' . $esc( $row[1] ) . '"'
				. ( $edit_checked( $row[0] ) ? ' checked' : '' ) . '></td></tr>';
		}
	}

	return $html;
}

// Aperçu : ce que verra un détenteur du profil (tâches accordées, groupées).
function SchoolSafeTaskPreview( array $rights )
{
	$esc = function( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); };
	$grouped = [];

	foreach ( $rights as $mod => $row )
	{
		$use = is_array( $row ) && isset( $row[1]['CAN_USE'] ) ? $row[1]['CAN_USE'] === 'Y' : ! empty( $row );
		$edit = is_array( $row ) && isset( $row[1]['CAN_EDIT'] ) && $row[1]['CAN_EDIT'] === 'Y';

		if ( SchoolSafeTaskIsReal( $mod ) && $use )
		{
			$grouped[ SchoolSafeTaskGroup( $mod ) ][] = SchoolSafeTaskLabel( $mod ) . ( $edit ? '' : ' (consultation)' );
		}
	}

	if ( ! $grouped )
	{
		return '<p><small>Aperçu : ce profil ne donne encore accès à aucune tâche.</small></p>';
	}

	$html = '<details class="ss-preview"><summary>Aperçu : ce que verra une personne avec ce profil</summary>';

	foreach ( $grouped as $group => $tasks )
	{
		sort( $tasks );
		$html .= '<p><strong>' . $esc( $group ) . '</strong> — ' . $esc( implode( ' · ', $tasks ) ) . '</p>';
	}

	return $html . '</details>';
}

// Journal : attributions d'accès et modifications de profils de cette école (20 derniers).
function SchoolSafeTaskJournal( $school_id, array $profile_ids )
{
	$esc = function( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); };
	$events = [];

	foreach ( (array) DBGet( "SELECT to_char(created_at,'DD/MM/YYYY HH24:MI') AS AT, created_at AS SORT_AT, action, actor, reason
		FROM schoolsafe_account_audit WHERE school_id='" . (int) $school_id . "' AND action LIKE 'access_%'
		ORDER BY created_at DESC LIMIT 20" ) as $row )
	{
		$detail = json_decode( (string) $row['REASON'], true );
		$who = '';

		if ( is_array( $detail ) && ! empty( $detail['staff_id'] ) )
		{
			$who = DBGetOne( "SELECT " . DisplayNameSQL( 's' ) . " FROM staff s WHERE s.STAFF_ID='" . (int) $detail['staff_id'] . "' LIMIT 1" );
		}

		$events[] = [ $row['SORT_AT'], $row['AT'], $row['ACTOR'], 'Accès attribué' . ( $who ? ' à ' . $who : '' ) ];
	}

	if ( $profile_ids )
	{
		$labels = [ 'school_profile_create' => 'Profil créé', 'school_profile_rights' => 'Tâches du profil modifiées',
			'school_profile_delete' => 'Profil supprimé' ];

		foreach ( (array) DBGet( "SELECT to_char(a.created_at,'DD/MM/YYYY HH24:MI') AS AT, a.created_at AS SORT_AT, a.action, a.result,
			s.USERNAME AS ACTOR, p.TITLE AS PROFILE
			FROM schoolsafe_identity_audit a
			LEFT JOIN staff s ON s.STAFF_ID=a.actor_staff_id
			LEFT JOIN user_profiles p ON p.ID=a.target_staff_id
			WHERE a.action LIKE 'school_profile_%' AND a.target_staff_id IN (" . implode( ',', array_map( 'intval', $profile_ids ) ) . ")
			ORDER BY a.created_at DESC LIMIT 20" ) as $row )
		{
			$events[] = [ $row['SORT_AT'], $row['AT'], $row['ACTOR'],
				( $labels[ $row['ACTION'] ] ?? $row['ACTION'] ) . ( $row['PROFILE'] ? ' : ' . $row['PROFILE'] : '' )
				. ( $row['RESULT'] !== 'success' ? ' (refusé)' : '' ) ];
		}
	}

	usort( $events, function( $a, $b ) { return strcmp( (string) $b[0], (string) $a[0] ); } );
	$events = array_slice( $events, 0, 20 );

	if ( ! $events )
	{
		return '<p><small>Aucune attribution enregistrée pour l’instant.</small></p>';
	}

	$html = '<table><thead><tr><th>Date</th><th>Par</th><th>Action</th></tr></thead><tbody>';

	foreach ( $events as $event )
	{
		$html .= '<tr><td>' . $esc( $event[1] ) . '</td><td>' . $esc( $event[2] ) . '</td><td>' . $esc( $event[3] ) . '</td></tr>';
	}

	return $html . '</tbody></table>';
}
