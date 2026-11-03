<?php
/**
 * Onglet « Famille et récupération » de la fiche élève (catégorie native Rosario,
 * include « SchoolSafe/FamilyTab »). Spec : 2026-11-03-schoolsafe-familles-recuperation-v1.1.
 *
 * Lecture côté serveur ; toute écriture passe par l'API existante
 * scanner-relay.php?operation=management (règles dans SchoolSafePickupManage.fnc.php,
 * garde-fous en base : migration 047). Aucune règle métier n'est dupliquée ici.
 */

if ( $_REQUEST['modfunc'] || ! UserStudentID() )
{
	return;
}

require_once 'modules/SchoolSafe/Gateway.php';

$ss_ft_student = (int) UserStudentID();
$ss_ft_school = (int) UserSchool();
$ss_ft_today = date( 'Y-m-d' );

// Actions autorisées pour l'utilisateur courant : même calcul que family-management.php.
$ss_ft_actions = [];

foreach ( SchoolSafePermissionCatalog() as $ss_ft_definition )
{
	foreach ( $ss_ft_definition['routes'] as $ss_ft_route )
	{
		if ( $ss_ft_route[0] === 'scanner-relay.php' && $ss_ft_route[1] === 'management'
			&& SchoolSafeEndpointGateway( 'scanner-relay.php', 'POST', 'management', [ 'action' => $ss_ft_route[2] ] ) !== 'deny' )
		{
			$ss_ft_actions[] = $ss_ft_route[2];
		}
	}
}

$ss_ft_actions = array_values( array_unique( $ss_ft_actions ) );

$ss_ft_rows = DBGet( "SELECT g.ID, g.GUARDIAN_TYPE, g.FULL_NAME, g.PHONE, g.STAFF_ID, g.IS_PRIMARY, g.IS_ACTIVE,
		g.IS_AUTHORIZED_PICKUP, g.PHOTO_PATH, g.PHOTO_ACCEPTED,
		a.ID AS AUTH_ID, a.STATUS AS AUTH_STATUS, a.SLOT_NO, a.PHOTO_VERIFIED, a.STARTS_ON, a.ENDS_ON
	FROM schoolsafe_pickup_guardians g
	LEFT JOIN LATERAL (
		SELECT ID, STATUS, SLOT_NO, PHOTO_VERIFIED, STARTS_ON, ENDS_ON
		FROM schoolsafe_pickup_authorizations x
		WHERE x.GUARDIAN_ID=g.ID AND x.STUDENT_ID=g.STUDENT_ID
		ORDER BY x.ID DESC LIMIT 1 ) a ON TRUE
	WHERE g.STUDENT_ID='" . $ss_ft_student . "' AND g.SCHOOL_ID='" . $ss_ft_school . "' AND g.IS_ACTIVE
	ORDER BY g.IS_PRIMARY DESC, g.ID" );

$ss_ft_flag = function( $value )
{
	return $value === true || $value === 't' || $value === 'true' || $value === '1' || $value === 1;
};

$ss_ft_family = [ 'pere' => null, 'mere' => null, 'tuteur' => null ];
$ss_ft_slots = [ 1 => null, 2 => null, 3 => null ];
$ss_ft_waiting = [];

foreach ( (array) $ss_ft_rows as $ss_ft_row )
{
	if ( empty( $ss_ft_row['ID'] ) )
	{
		continue;
	}

	if ( array_key_exists( $ss_ft_row['GUARDIAN_TYPE'], $ss_ft_family ) )
	{
		if ( ! $ss_ft_family[ $ss_ft_row['GUARDIAN_TYPE'] ] )
		{
			$ss_ft_family[ $ss_ft_row['GUARDIAN_TYPE'] ] = $ss_ft_row;
		}
	}
	elseif ( $ss_ft_row['GUARDIAN_TYPE'] === 'externe' )
	{
		if ( $ss_ft_row['AUTH_STATUS'] === 'active' && array_key_exists( (int) $ss_ft_row['SLOT_NO'], $ss_ft_slots )
			&& ! $ss_ft_slots[ (int) $ss_ft_row['SLOT_NO'] ] )
		{
			$ss_ft_slots[ (int) $ss_ft_row['SLOT_NO'] ] = $ss_ft_row;
		}
		elseif ( $ss_ft_row['AUTH_STATUS'] !== 'revoked' )
		{
			$ss_ft_waiting[] = $ss_ft_row;
		}
	}
}

$ss_ft_restrictions = DBGet( "SELECT r.ID, COALESCE(NULLIF(r.FULL_NAME,''), g.FULL_NAME, p.FULL_NAME) AS FULL_NAME,
		r.REASON, r.SOURCE
	FROM schoolsafe_pickup_restrictions r
	LEFT JOIN schoolsafe_pickup_guardians g ON g.ID=r.GUARDIAN_ID
	LEFT JOIN schoolsafe_pickup_persons p ON p.ID=r.PERSON_ID
	WHERE r.STUDENT_ID='" . $ss_ft_student . "' AND r.SCHOOL_ID='" . $ss_ft_school . "' AND r.IS_ACTIVE
	AND r.STARTS_ON<='" . $ss_ft_today . "' AND (r.ENDS_ON IS NULL OR r.ENDS_ON>='" . $ss_ft_today . "')
	ORDER BY r.ID" );

// Contacts d'urgence : contacts natifs Rosario marqués « urgence » (onglet Adresses et contacts).
$ss_ft_emergency = DBGet( "SELECT p.PERSON_ID, " . DisplayNameSQL( 'p' ) . " AS FULL_NAME, sjp.STUDENT_RELATION,
		(SELECT VALUE FROM people_join_contacts c WHERE c.PERSON_ID=p.PERSON_ID ORDER BY c.ID LIMIT 1) AS CONTACT
	FROM students_join_people sjp JOIN people p ON p.PERSON_ID=sjp.PERSON_ID
	WHERE sjp.STUDENT_ID='" . $ss_ft_student . "' AND sjp.EMERGENCY='Y'
	ORDER BY sjp.ID LIMIT 2" );

$ss_ft_accounts = DBGet( "SELECT s.STAFF_ID, " . DisplayNameSQL( 's' ) . " AS FULL_NAME
	FROM students_join_users j JOIN staff s ON s.STAFF_ID=j.STAFF_ID AND s.SYEAR='" . UserSyear() . "' AND s.PROFILE='parent'
	WHERE j.STUDENT_ID='" . $ss_ft_student . "'
	AND NOT EXISTS (SELECT 1 FROM schoolsafe_pickup_guardians g WHERE g.STUDENT_ID=j.STUDENT_ID AND g.STAFF_ID=s.STAFF_ID)
	ORDER BY FULL_NAME" );

$ss_ft_account_list = [];

foreach ( (array) $ss_ft_accounts as $ss_ft_account )
{
	if ( ! empty( $ss_ft_account['STAFF_ID'] ) )
	{
		$ss_ft_account_list[] = [ 'id' => (int) $ss_ft_account['STAFF_ID'], 'name' => $ss_ft_account['FULL_NAME'] ];
	}
}

$ss_ft_esc = function( $value )
{
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
};

$ss_ft_photo = function( $row ) use ( $ss_ft_esc, $ss_ft_flag )
{
	$path = (string) ( $row['PHOTO_PATH'] ?? '' );

	if ( $path === '' )
	{
		return '<div class="ssft-photo ssft-photo-empty" aria-hidden="true">?</div>';
	}

	$class = $ss_ft_flag( $row['PHOTO_ACCEPTED'] ) ? '' : ' ssft-photo-pending';

	return '<img class="ssft-photo' . $class . '" src="' . $ss_ft_esc( ltrim( $path, '/' ) ) . '" alt="Photo de '
		. $ss_ft_esc( $row['FULL_NAME'] ) . '" loading="lazy">';
};

$ss_ft_badge = function( $text, $tone ) use ( $ss_ft_esc )
{
	return '<span class="ssft-badge ssft-' . $tone . '">' . $ss_ft_esc( $text ) . '</span>';
};

$ss_ft_button = function( $action, $label, array $data ) use ( $ss_ft_actions, $ss_ft_esc )
{
	$api = [ 'photo-upload' => 'photo-set', 'link-account' => 'school-family-approve' ][ $action ] ?? $action;

	if ( ! in_array( $api, $ss_ft_actions, true ) )
	{
		return '';
	}

	$attributes = '';

	foreach ( $data as $key => $value )
	{
		$attributes .= ' data-' . $key . '="' . $ss_ft_esc( $value ) . '"';
	}

	return '<button type="button" class="ssft-action" data-action="' . $ss_ft_esc( $action ) . '"' . $attributes . '>'
		. $ss_ft_esc( $label ) . '</button>';
};

$ss_ft_person_card = function( $row, $title, $kind ) use ( $ss_ft_esc, $ss_ft_photo, $ss_ft_badge, $ss_ft_button, $ss_ft_flag, $ss_ft_today, $ss_ft_account_list )
{
	$guardian = [ 'guardian' => $row['ID'] ];
	$has_photo = trim( (string) $row['PHOTO_PATH'] ) !== '';
	$photo_ok = $has_photo && $ss_ft_flag( $row['PHOTO_ACCEPTED'] );
	$html = '<article class="ssft-card">' . $ss_ft_photo( $row ) . '<div class="ssft-body">';
	$html .= '<p class="ssft-role">' . $ss_ft_esc( $title ) . '</p>';
	$html .= '<p class="ssft-name">' . $ss_ft_esc( $row['FULL_NAME'] ) . '</p>';

	if ( trim( (string) $row['PHONE'] ) !== '' )
	{
		$html .= '<p class="ssft-meta">' . $ss_ft_esc( $row['PHONE'] ) . '</p>';
	}

	$badges = '';

	if ( $ss_ft_flag( $row['IS_PRIMARY'] ) )
	{
		$badges .= $ss_ft_badge( 'Responsable principal', 'primary' );
	}

	if ( $kind === 'family' && ! empty( $row['STAFF_ID'] ) )
	{
		$badges .= $ss_ft_badge( 'Compte parent lié', 'neutral' );
	}

	if ( ! $has_photo )
	{
		$badges .= $ss_ft_badge( 'Photo manquante', 'warn' );
	}
	elseif ( ! $photo_ok )
	{
		$badges .= $ss_ft_badge( 'Photo à valider', 'warn' );
	}

	if ( $kind === 'family' )
	{
		$badges .= $ss_ft_flag( $row['IS_AUTHORIZED_PICKUP'] )
			? $ss_ft_badge( 'Peut récupérer', 'ok' ) : $ss_ft_badge( 'Récupération non autorisée', 'muted' );
	}
	else
	{
		$status = (string) $row['AUTH_STATUS'];
		$expired = $row['ENDS_ON'] && $row['ENDS_ON'] < $ss_ft_today;
		$labels = [ 'active' => [ 'Accréditation active', 'ok' ], 'pending' => [ 'En attente de validation', 'warn' ],
			'suspended' => [ 'Suspendue par le responsable', 'muted' ] ];
		$badges .= $expired ? $ss_ft_badge( 'Expirée', 'warn' )
			: $ss_ft_badge( $labels[ $status ][0] ?? 'Sans accréditation', $labels[ $status ][1] ?? 'muted' );

		if ( $row['ENDS_ON'] )
		{
			$html .= '<p class="ssft-meta">Valable jusqu’au ' . $ss_ft_esc( date( 'd/m/Y', strtotime( $row['ENDS_ON'] ) ) ) . '</p>';
		}
	}

	$html .= '<p class="ssft-badges">' . $badges . '</p><p class="ssft-actions">';
	$html .= $ss_ft_button( 'photo-upload', $has_photo ? 'Changer la photo' : 'Ajouter la photo', $guardian );

	if ( $has_photo && ! $photo_ok )
	{
		$html .= $ss_ft_button( 'photo-review', 'Valider la photo', $guardian );
	}

	if ( $kind === 'family' && $photo_ok && ! $ss_ft_flag( $row['IS_AUTHORIZED_PICKUP'] ) )
	{
		$html .= $ss_ft_button( 'school-family-approve', 'Autoriser la récupération', $guardian );
	}

	if ( $kind === 'family' && $photo_ok && empty( $row['STAFF_ID'] ) && $ss_ft_account_list && User( 'PROFILE' ) === 'admin' )
	{
		$html .= $ss_ft_button( 'link-account', 'Lier le compte parent', $guardian );
	}

	if ( $kind === 'external' && $row['AUTH_STATUS'] === 'pending' && $photo_ok && $row['AUTH_ID'] )
	{
		$html .= $ss_ft_button( 'validate-authorization', 'Valider l’accréditation', [ 'authorization' => $row['AUTH_ID'] ] );
	}

	if ( ! $ss_ft_flag( $row['IS_PRIMARY'] ) )
	{
		$html .= $ss_ft_button( 'school-retire', 'Retirer', $guardian );
	}

	return $html . '</p></div></article>';
};

$ss_ft_empty_card = function( $title, $action, $label, array $data ) use ( $ss_ft_esc, $ss_ft_button )
{
	return '<article class="ssft-card ssft-empty"><div class="ssft-photo ssft-photo-empty" aria-hidden="true">+</div><div class="ssft-body">'
		. '<p class="ssft-role">' . $ss_ft_esc( $title ) . '</p><p class="ssft-meta">Place libre</p>'
		. '<p class="ssft-actions">' . $ss_ft_button( $action, $label, $data ) . '</p></div></article>';
};

$ss_ft_has_primary = false;

foreach ( $ss_ft_family as $ss_ft_row )
{
	$ss_ft_has_primary = $ss_ft_has_primary || ( $ss_ft_row && $ss_ft_flag( $ss_ft_row['IS_PRIMARY'] ) );
}

echo '<link rel="stylesheet" href="schoolsafe-card-bridge/family-tab.css?v=f2-2">';
echo '<script defer src="schoolsafe-card-bridge/family-tab.js?v=f2-2"></script>';
echo '<section class="ssft" id="ssft" data-student="' . $ss_ft_student . '" data-has-primary="' . ( $ss_ft_has_primary ? '1' : '0' ) . '"'
	. ' data-actions="' . $ss_ft_esc( json_encode( $ss_ft_actions ) ) . '"'
	. ' data-accounts="' . $ss_ft_esc( json_encode( $ss_ft_account_list, JSON_UNESCAPED_UNICODE ) ) . '">';

echo '<p class="ssft-intro">Toute personne est enregistrée <strong>à l’école</strong>, en sa présence : pièce d’identité vérifiée et photo prise sur place. '
	. 'Le responsable principal peut suspendre un accrédité depuis son profil.</p>';

if ( ! $ss_ft_has_primary )
{
	echo '<p class="ssft-alert">Aucun responsable principal : enregistrez d’abord le père, la mère ou le tuteur qui sera principal.</p>';
}

foreach ( (array) $ss_ft_restrictions as $ss_ft_row )
{
	if ( empty( $ss_ft_row['ID'] ) )
	{
		continue;
	}

	echo '<p class="ssft-alert ssft-restriction"><strong>Interdiction de récupération</strong> — ' . $ss_ft_esc( $ss_ft_row['FULL_NAME'] )
		. ' (' . ( $ss_ft_row['SOURCE'] === 'justice' ? 'décision de justice' : 'décision de l’école' ) . ') : '
		. $ss_ft_esc( $ss_ft_row['REASON'] ) . '</p>';
}

$ss_ft_titles = [ 'pere' => 'Père', 'mere' => 'Mère', 'tuteur' => 'Tuteur' ];

echo '<h3 class="ssft-heading">Responsables familiaux <small>père, mère, tuteur — un seul principal</small></h3><div class="ssft-grid">';

foreach ( $ss_ft_family as $ss_ft_type => $ss_ft_row )
{
	echo $ss_ft_row
		? $ss_ft_person_card( $ss_ft_row, $ss_ft_titles[ $ss_ft_type ], 'family' )
		: $ss_ft_empty_card( $ss_ft_titles[ $ss_ft_type ], 'register-family', 'Enregistrer', [ 'type' => $ss_ft_type ] );
}

echo '</div><h3 class="ssft-heading">Personnes accréditées <small>3 maximum, sans compte, récupération uniquement</small></h3><div class="ssft-grid">';

foreach ( $ss_ft_slots as $ss_ft_slot => $ss_ft_row )
{
	echo $ss_ft_row
		? $ss_ft_person_card( $ss_ft_row, 'Accrédité ' . $ss_ft_slot, 'external' )
		: $ss_ft_empty_card( 'Accrédité ' . $ss_ft_slot, 'register-external', 'Enregistrer', [] );
}

echo '</div>';

if ( $ss_ft_waiting )
{
	echo '<h3 class="ssft-heading">Accréditations en attente ou suspendues</h3><div class="ssft-grid">';

	foreach ( $ss_ft_waiting as $ss_ft_row )
	{
		echo $ss_ft_person_card( $ss_ft_row, 'Accréditation', 'external' );
	}

	echo '</div>';
}

echo '<h3 class="ssft-heading">Contacts d’urgence <small>2 maximum, ne récupèrent pas l’enfant</small></h3><div class="ssft-grid">';

for ( $ss_ft_i = 1; $ss_ft_i <= 2; $ss_ft_i++ )
{
	$ss_ft_row = $ss_ft_emergency[ $ss_ft_i ] ?? null;

	echo '<article class="ssft-card' . ( $ss_ft_row ? '' : ' ssft-empty' ) . '"><div class="ssft-body">'
		. '<p class="ssft-role">Contact d’urgence ' . $ss_ft_i . '</p>'
		. ( $ss_ft_row
			? '<p class="ssft-name">' . $ss_ft_esc( $ss_ft_row['FULL_NAME'] ) . '</p><p class="ssft-meta">'
				. $ss_ft_esc( trim( $ss_ft_row['STUDENT_RELATION'] . ' ' . $ss_ft_row['CONTACT'] ) ) . '</p>'
			: '<p class="ssft-meta">Non renseigné</p>' )
		. '<p class="ssft-actions"><a href="Modules.php?modname=Students/Student.php&amp;category_id=3&amp;student_id=' . $ss_ft_student . '">'
		. ( $ss_ft_row ? 'Modifier dans Adresses et contacts' : 'Ajouter dans Adresses et contacts' ) . '</a></p></div></article>';
}

echo '</div><div class="ssft-panel" id="ssft-panel" hidden></div><p class="ssft-status" id="ssft-status" role="status"></p></section>';
