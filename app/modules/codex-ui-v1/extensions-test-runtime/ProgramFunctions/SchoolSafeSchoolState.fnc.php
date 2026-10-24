<?php
/**
 * SchoolSafe — état d'un ÉTABLISSEMENT (école cliente entière).
 *
 * Trois états, appliqués à la connexion et à chaque requête :
 *   active    : comportement natif inchangé (défaut quand aucune ligne n'existe)
 *   suspended : accès coupé pour tous les utilisateurs de l'école, données
 *               conservées ; une date de fin rend l'accès automatiquement
 *   inactive  : établissement désactivé, accès coupé, réactivation manuelle
 *
 * L'état porte sur l'ÉCOLE, jamais sur la personne : une personne affectée à
 * deux écoles perd l'accès à l'école coupée et garde l'autre. Le propriétaire
 * de la plateforme (aucune école assignée) garde l'accès pour administrer.
 *
 * Aucune table native n'est modifiée et rien n'est supprimé. Si la migration
 * 016 n'a pas été appliquée, tout est « active » : la fonctionnalité est
 * inerte, jamais bloquante.
 *
 * Lot additif — voir schoolsafe-remediation/016-school-state.sql.
 */

/**
 * La table d'état d'école existe-t-elle ? (mémoïsé par requête)
 */
function SchoolSafeSchoolStateReady()
{
	static $ready = null;

	if ( $ready !== null )
	{
		return $ready;
	}

	$ready = false;

	try
	{
		$ready = (bool) DBGetOne( "SELECT 1 FROM information_schema.tables
			WHERE table_name='schoolsafe_school_state'" );
	}
	catch ( Throwable $error )
	{
		$ready = false;
	}

	return $ready;
}

/**
 * Cache mémoire par requête (invalidé après écriture).
 */
function &SchoolSafeSchoolStateCache()
{
	if ( ! isset( $GLOBALS['SchoolSafeSchoolStateCache'] ) )
	{
		$GLOBALS['SchoolSafeSchoolStateCache'] = [];
	}

	return $GLOBALS['SchoolSafeSchoolStateCache'];
}

/**
 * Toutes les lignes d'état, indexées par identifiant d'école.
 */
function SchoolSafeSchoolStateRows()
{
	$cache = &SchoolSafeSchoolStateCache();

	if ( isset( $cache['rows'] ) )
	{
		return $cache['rows'];
	}

	$rows = [];

	if ( SchoolSafeSchoolStateReady() )
	{
		try
		{
			$RET = DBGet( "SELECT SCHOOL_ID,STATE,REASON,UNTIL,CHANGED_BY,CHANGED_AT
				FROM schoolsafe_school_state" );

			foreach ( (array) $RET as $row )
			{
				$rows[(int) $row['SCHOOL_ID']] = $row;
			}
		}
		catch ( Throwable $error )
		{
			$rows = [];
		}
	}

	return $cache['rows'] = $rows;
}

/**
 * Applique la date de fin : une suspension temporaire expirée rend l'accès.
 */
function SchoolSafeSchoolStateApplyUntil( $state, $until )
{
	if ( ! $until || $state !== 'suspended' )
	{
		return $state;
	}

	$timestamp = strtotime( (string) $until );

	if ( ! $timestamp || $timestamp >= time() )
	{
		return $state;
	}

	return 'active';
}

/**
 * État effectif d'une école (active par défaut).
 */
function SchoolSafeSchoolStateEffective( $school_id )
{
	$school_id = (int) $school_id;

	$rows = SchoolSafeSchoolStateRows();

	if ( $school_id < 1 || ! isset( $rows[$school_id] ) )
	{
		return 'active';
	}

	return SchoolSafeSchoolStateApplyUntil( $rows[$school_id]['STATE'], $rows[$school_id]['UNTIL'] );
}

/**
 * Accès coupé pour cette école ?
 */
function SchoolSafeSchoolBlocked( $school_id )
{
	return SchoolSafeSchoolStateEffective( $school_id ) !== 'active';
}

/**
 * Enregistre un état d'école et journalise le changement.
 *
 * @param int         $school_id École visée.
 * @param string      $state     active|suspended|inactive.
 * @param string|null $reason    Motif lisible.
 * @param string|null $until     Date de fin (Y-m-d) pour une suspension, sinon null.
 *
 * @return bool true si enregistré.
 */
function SchoolSafeSchoolStateSet( $school_id, $state, $reason = null, $until = null )
{
	$states = [ 'active', 'suspended', 'inactive' ];

	$school_id = (int) $school_id;

	if ( $school_id < 1
		|| ! in_array( $state, $states, true )
		|| ! SchoolSafeSchoolStateReady() )
	{
		return false;
	}

	// Une date civile invalide ne doit jamais se transformer en 1970 avec succès.
	if ( $state === 'suspended' && $until !== null && $until !== '' )
	{
		if ( ! is_string( $until ) || ! preg_match( '/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $until, $parts )
			|| ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) )
		{
			return false;
		}
		$until .= ' 00:00:00';
	}
	else
	{
		$until = null;
	}

	$reason = $reason ? mb_substr( trim( (string) $reason ), 0, 500 ) : null;

	$actor = function_exists( 'User' ) ? (string) User( 'USERNAME' ) : '';

	// Le setter possède la transaction : ne jamais committer celle d'un appelant.
	global $db_connection, $DatabaseType;
	if ( $DatabaseType !== 'postgresql' || ! isset( $db_connection )
		|| pg_transaction_status( $db_connection ) !== PGSQL_TRANSACTION_IDLE ) return false;
	$query = static function ( $sql ) {
		$result = @db_query( $sql, false );
		if ( $result === false ) throw new RuntimeException( 'SCHOOL_STATE_WRITE_FAILED' );
		return $result;
	};
	$started = false;
	try
	{
		$query( 'BEGIN' );
		$started = true;
		$query( "SET LOCAL lock_timeout='5s'" );
		$query( 'SELECT pg_advisory_xact_lock(493016,' . $school_id . ')' );
		$row = pg_fetch_assoc( $query( 'SELECT STATE FROM schoolsafe_school_state WHERE SCHOOL_ID=' . $school_id . ' FOR UPDATE' ) );
		$from = $row === false ? null : (string) $row['state'];
		if ( $row !== false )
		{
			$query( "UPDATE schoolsafe_school_state
				SET STATE='" . DBEscapeString( $state ) . "',
				REASON=" . ( $reason === null ? 'NULL' : "'" . DBEscapeString( $reason ) . "'" ) . ",
				UNTIL=" . ( $until === null ? 'NULL' : "'" . DBEscapeString( $until ) . "'" ) . ",
				CHANGED_BY='" . DBEscapeString( $actor ) . "',
				CHANGED_AT=CURRENT_TIMESTAMP
				WHERE SCHOOL_ID='" . $school_id . "'" );
		}
		else
		{
			$query( "INSERT INTO schoolsafe_school_state
				(SCHOOL_ID,STATE,REASON,UNTIL,CHANGED_BY,CHANGED_AT)
				VALUES ('" . $school_id . "',
				'" . DBEscapeString( $state ) . "',
				" . ( $reason === null ? 'NULL' : "'" . DBEscapeString( $reason ) . "'" ) . ",
				" . ( $until === null ? 'NULL' : "'" . DBEscapeString( $until ) . "'" ) . ",
				'" . DBEscapeString( $actor ) . "',CURRENT_TIMESTAMP)" );
		}

		$query( "INSERT INTO schoolsafe_school_audit
			(SCHOOL_ID,ACTION,FROM_STATE,TO_STATE,REASON,ACTOR)
			VALUES ('" . $school_id . "','state',
			" . ( $from === null ? 'NULL' : "'" . DBEscapeString( $from ) . "'" ) . ",
			'" . DBEscapeString( $state ) . "',
			" . ( $reason === null ? 'NULL' : "'" . DBEscapeString( $reason ) . "'" ) . ",
			'" . DBEscapeString( $actor ) . "')" );
		$query( 'COMMIT' );
		$started = false;
	}
	catch ( Throwable $error )
	{
		if ( $started ) @db_query( 'ROLLBACK', false );
		return false;
	}

	SchoolSafeSchoolStateForget();

	return true;
}

/**
 * Vide le cache mémoire (après une écriture dans la même requête).
 */
function SchoolSafeSchoolStateForget()
{
	$cache = &SchoolSafeSchoolStateCache();

	$cache = [];

	return true;
}

/**
 * Établissements non actifs (identifiants), pour l'affichage et les tests.
 */
function SchoolSafeSchoolBlockedIds()
{
	$blocked = [];

	foreach ( SchoolSafeSchoolStateRows() as $school_id => $row )
	{
		if ( SchoolSafeSchoolStateApplyUntil( $row['STATE'], $row['UNTIL'] ) !== 'active' )
		{
			$blocked[] = (int) $school_id;
		}
	}

	return $blocked;
}

/**
 * Une personne peut-elle encore se connecter compte tenu des écoles ?
 *
 * Portée plateforme (aucune école assignée) : jamais bloquée.
 * Sinon : bloquée seulement si TOUTES ses écoles sont coupées. Renvoie l'état
 * fautif ('suspended' ou 'inactive') ou '' si au moins une école reste ouverte.
 *
 * @param array $school_ids Écoles de la personne.
 *
 * @return string
 */
function SchoolSafeSchoolLoginBlock( $school_ids )
{
	$ids = array_values( array_filter( array_map( 'intval', (array) $school_ids ) ) );

	if ( ! $ids )
	{
		// Aucune école assignée : portée plateforme, jamais coupée par une école.
		return '';
	}

	$blocked_state = '';

	foreach ( $ids as $school_id )
	{
		$state = SchoolSafeSchoolStateEffective( $school_id );

		if ( $state === 'active' )
		{
			return '';
		}

		if ( $blocked_state === '' )
		{
			$blocked_state = $state;
		}
	}

	return $blocked_state;
}

/**
 * Message affiché à la connexion quand toutes les écoles sont coupées.
 */
function SchoolSafeSchoolLoginMessage( $state )
{
	if ( $state === 'inactive' )
	{
		return "L'acces de votre etablissement est desactive (ferme ou hors service). "
			. "Aucune donnee n'est supprimee : eleves, notes et paiements sont conserves. "
			. "Contactez le proprietaire de la plateforme.";
	}

	return "L'acces de votre etablissement est suspendu, temporairement. "
		. "Aucune donnee n'est supprimee : eleves, notes et paiements sont conserves. "
		. "Contactez le proprietaire de la plateforme.";
}

/**
 * Écoles d'un élève (inscriptions de l'année courante).
 *
 * @param int $student_id Élève.
 *
 * @return array Identifiants d'école.
 */
function SchoolSafeStudentSchools( $student_id )
{
	$student_id = (int) $student_id;

	if ( $student_id < 1 )
	{
		return [];
	}

	$schools = [];

	try
	{
		$syear = function_exists( 'UserSyear' ) ? (int) UserSyear() : 0;

		if ( ! $syear )
		{
			$syear = (int) Config( 'SYEAR' );
		}

		$RET = DBGet( "SELECT DISTINCT SCHOOL_ID FROM student_enrollment
			WHERE STUDENT_ID='" . $student_id . "' AND SYEAR='" . $syear . "'" );

		foreach ( (array) $RET as $row )
		{
			$schools[] = (int) $row['SCHOOL_ID'];
		}
	}
	catch ( Throwable $error )
	{
		$schools = [];
	}

	return array_values( array_unique( $schools ) );
}

/**
 * Écoles des enfants d'un parent (staff sans école assignée).
 *
 * @param int $staff_id Compte parent.
 *
 * @return array Identifiants d'école.
 */
function SchoolSafeParentSchools( $staff_id )
{
	$staff_id = (int) $staff_id;

	if ( $staff_id < 1 )
	{
		return [];
	}

	$schools = [];

	try
	{
		$syear = function_exists( 'UserSyear' ) ? (int) UserSyear() : 0;

		if ( ! $syear )
		{
			$syear = (int) Config( 'SYEAR' );
		}

		$RET = DBGet( "SELECT DISTINCT e.SCHOOL_ID
			FROM students_join_users u
			JOIN student_enrollment e ON e.STUDENT_ID=u.STUDENT_ID
			WHERE u.STAFF_ID='" . $staff_id . "' AND e.SYEAR='" . $syear . "'" );

		foreach ( (array) $RET as $row )
		{
			$schools[] = (int) $row['SCHOOL_ID'];
		}
	}
	catch ( Throwable $error )
	{
		$schools = [];
	}

	return array_values( array_unique( $schools ) );
}

/**
 * Écoles à considérer pour une personne.
 *
 * Une personne d'école porte ses écoles dans staff.SCHOOLS. Un parent n'en a
 * aucune : ses écoles sont celles de ses enfants. Une liste vide (aucune école
 * assignée, ou rattachement illisible) signifie « pas de restriction d'école »
 * et n'est jamais bloquante.
 *
 * @param string $profile       admin|teacher|parent|student.
 * @param string $schools_value staff.SCHOOLS.
 * @param int    $staff_id      Compte (pour un parent).
 *
 * @return array Identifiants d'école.
 */
function SchoolSafePersonSchools( $profile, $schools_value, $staff_id = 0 )
{
	$schools = array_values( array_filter( array_map( 'intval', explode( ',', (string) $schools_value ) ) ) );

	if ( $schools )
	{
		return $schools;
	}

	if ( (string) $profile === 'parent' )
	{
		return SchoolSafeParentSchools( $staff_id );
	}

	return [];
}

/**
 * Bandeau d'information sur l'état de l'établissement, pour la session courante.
 *
 * Destiné au portail : une famille dont une école est coupée mais qui garde une
 * autre école ouverte comprend ce qui se passe, sans perdre l'accès. Ne renvoie
 * qu'un texte lisible : aucun état, aucun identifiant de compte.
 *
 * @return string HTML, vide quand tout est actif.
 */
function SchoolSafeSchoolNoticeHtml()
{
	if ( ! SchoolSafeSchoolStateReady() || ! function_exists( 'User' ) )
	{
		return '';
	}

	$is_staff = ! empty( $_SESSION['STAFF_ID'] );
	$is_student = ! empty( $_SESSION['STUDENT_ID'] );

	if ( ! $is_staff && ! $is_student )
	{
		return '';
	}

	// Le proprietaire administre l'ecole coupee : pas de bandeau pour lui.
	if ( $is_staff && SchoolSafeGlobalAdmin() )
	{
		return '';
	}

	$schools = $is_staff
		? SchoolSafePersonSchools( User( 'PROFILE' ), User( 'SCHOOLS' ), (int) $_SESSION['STAFF_ID'] )
		: SchoolSafeStudentSchools( (int) $_SESSION['STUDENT_ID'] );

	$blocked = [];

	foreach ( (array) $schools as $school_id )
	{
		$state = SchoolSafeSchoolStateEffective( $school_id );

		if ( $state === 'active' )
		{
			continue;
		}

		$title = DBGetOne( "SELECT TITLE FROM schools WHERE ID='" . (int) $school_id . "'" );

		$blocked[] = [
			'title' => $title ? (string) $title : 'Etablissement ' . (int) $school_id,
			'state' => $state,
		];
	}

	if ( ! $blocked )
	{
		return '';
	}

	$html = '<div style="background:#fff8e6;border:1px solid #e6c96a;border-radius:10px;'
		. 'padding:14px 18px;margin:0 0 20px;color:#3f2f00">'
		. '<strong>Information sur votre etablissement</strong>';

	foreach ( $blocked as $item )
	{
		$html .= '<p style="margin:8px 0 0">'
			. htmlspecialchars( $item['title'], ENT_QUOTES, 'UTF-8' ) . ' : '
			. htmlspecialchars( SchoolSafeSchoolLoginMessage( $item['state'] ), ENT_QUOTES, 'UTF-8' )
			. '</p>';
	}

	return $html . '</div>';
}

/**
 * Écoles ouvertes d'une liste, dans l'ordre reçu.
 */
function SchoolSafeSchoolActiveSchools( $school_ids )
{
	$active = [];

	foreach ( (array) $school_ids as $school_id )
	{
		$school_id = (int) $school_id;

		if ( $school_id > 0 && ! SchoolSafeSchoolBlocked( $school_id ) )
		{
			$active[] = $school_id;
		}
	}

	return $active;
}

/**
 * Garde de session : école courante suspendue ou désactivée.
 *
 * Appelée à chaque requête depuis Warehouse.php, après la garde d'état de
 * compte. Le propriétaire de la plateforme garde l'accès (il administre), un
 * utilisateur d'école est basculé sur une de ses écoles ouvertes, et s'il n'en
 * a plus aucune, sa session est fermée avec un motif explicite.
 */
function SchoolSafeSchoolSessionGuard()
{
	if ( ! SchoolSafeSchoolStateReady() || ! function_exists( 'User' ) )
	{
		return;
	}

	$is_staff = ! empty( $_SESSION['STAFF_ID'] );
	$is_student = ! empty( $_SESSION['STUDENT_ID'] );

	if ( ! $is_staff && ! $is_student )
	{
		return;
	}

	// Portée plateforme : le proprietaire doit pouvoir intervenir sur l'ecole.
	if ( $is_staff && SchoolSafeGlobalAdmin() )
	{
		return;
	}

	$scope = $is_staff
		? SchoolSafePersonSchools( User( 'PROFILE' ), User( 'SCHOOLS' ), (int) $_SESSION['STAFF_ID'] )
		: SchoolSafeStudentSchools( (int) $_SESSION['STUDENT_ID'] );

	if ( ! $scope )
	{
		// Pas de perimetre exploitable : le natif tranche (echec ouvert assume).
		return;
	}

	$open = SchoolSafeSchoolActiveSchools( $scope );

	$current = (int) UserSchool();

	if ( $current && ! SchoolSafeSchoolBlocked( $current ) )
	{
		return;
	}

	// Aucune ecole de la personne n'est coupee : comportement natif inchange.
	if ( $current === 0 && count( $open ) === count( $scope ) )
	{
		return;
	}

	if ( $open )
	{
		// Bascule silencieuse sur une ecole encore ouverte : l'utilisateur
		// garde son travail, la seule ecole coupee disparait de son perimetre.
		$_SESSION['UserSchool'] = (int) $open[0];

		return;
	}

	// Aucune ecole ouverte : fin de session.
	$state = $current ? SchoolSafeSchoolStateEffective( $current ) : 'suspended';

	SchoolSafeAccountEndSession();

	$reason = $state === 'inactive' ? 'school_inactive' : 'school_suspended';

	if ( ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] )
		|| ( isset( $_SERVER['HTTP_ACCEPT'] )
			&& mb_strpos( (string) $_SERVER['HTTP_ACCEPT'], 'application/json' ) !== false ) )
	{
		http_response_code( 401 );

		header( 'Content-Type: application/json; charset=utf-8' );

		exit( json_encode( [ 'ok' => false, 'error' => mb_strtoupper( $reason ) ] ) );
	}

	header( 'Location: index.php?reason=' . $reason );

	exit;
}
