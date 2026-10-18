<?php
/**
 * SchoolSafe — état de compte et révocation de session.
 *
 * Quatre états seulement, appliqués à la connexion, à l'écriture et aux routes :
 *   active    : comportement natif inchangé (défaut quand aucune ligne n'existe)
 *   readonly  : consultation seule, toute écriture est refusée
 *   suspended : connexion refusée, données conservées
 *   expired   : idem suspended, atteint automatiquement à la date de fin
 *
 * L'état est porté par la PERSONNE (nom d'utilisateur en minuscules, l'identité
 * que le natif utilise déjà d'une année sur l'autre) et par ECOLE
 * (school_id = 0 : toutes les écoles de la personne). Il survit donc au
 * rollover, et une personne présente dans deux écoles peut être suspendue dans
 * l'une sans perdre l'accès à l'autre.
 *
 * Aucune table native n'est modifiée. Si la migration n'a pas été appliquée,
 * tout est « actif » : la fonctionnalité est inerte, jamais bloquante.
 */

/**
 * La table d'état existe-t-elle ? (mémoïsé par requête)
 */
function SchoolSafeAccountReady()
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
			WHERE table_name='schoolsafe_account_state'" );
	}
	catch ( Throwable $error )
	{
		$ready = false;
	}

	return $ready;
}

/**
 * Clé de personne : nom d'utilisateur normalisé.
 */
function SchoolSafeAccountPerson( $username )
{
	return mb_substr( mb_strtolower( trim( (string) $username ) ), 0, 100 );
}

/**
 * Liste d'identifiants d'école valides (0 = toutes les écoles).
 */
function SchoolSafeAccountSchoolIds( $school_ids )
{
	$ids = [];

	foreach ( (array) $school_ids as $school_id )
	{
		$school_id = (int) $school_id;

		if ( $school_id >= 0 && ! in_array( $school_id, $ids, true ) )
		{
			$ids[] = $school_id;
		}
	}

	return $ids ?: [ 0 ];
}

/**
 * Toutes les lignes d'état d'une personne, indexées par école.
 */
function SchoolSafeAccountRows( $username )
{
	$cache = &SchoolSafeAccountCache();

	$person = SchoolSafeAccountPerson( $username );

	if ( ! $person || ! SchoolSafeAccountReady() )
	{
		return [];
	}

	if ( isset( $cache[$person] ) )
	{
		return $cache[$person];
	}

	$rows = [];

	try
	{
		$RET = DBGet( "SELECT SCHOOL_ID,STATE,REASON,UNTIL,SESSION_EPOCH
			FROM schoolsafe_account_state
			WHERE PERSON_KEY='" . DBEscapeString( $person ) . "'" );

		foreach ( (array) $RET as $row )
		{
			$rows[(int) $row['SCHOOL_ID']] = $row;
		}
	}
	catch ( Throwable $error )
	{
		$rows = [];
	}

	return $cache[$person] = $rows;
}

/**
 * Cache mémoire par requête, invalidable après écriture.
 */
function &SchoolSafeAccountCache()
{
	if ( ! isset( $GLOBALS['SchoolSafeAccountCache'] ) )
	{
		$GLOBALS['SchoolSafeAccountCache'] = [];
	}

	return $GLOBALS['SchoolSafeAccountCache'];
}

/**
 * Applique la date de fin à un état.
 *
 * - active + date passée   => expired   (fin de contrat atteinte)
 * - readonly/suspended + date passée => active (mesure temporaire terminée)
 */
function SchoolSafeAccountApplyUntil( $state, $until )
{
	if ( ! $until )
	{
		return $state;
	}

	$timestamp = strtotime( (string) $until );

	if ( ! $timestamp || $timestamp >= time() )
	{
		return $state;
	}

	if ( $state === 'active' )
	{
		return 'expired';
	}

	if ( $state === 'readonly' || $state === 'suspended' )
	{
		return 'active';
	}

	return $state;
}

/**
 * État effectif pour une personne, dans l'ordre des écoles fournies.
 *
 * L'état de l'école demandée prime ; à défaut, la ligne « toutes les écoles ».
 */
function SchoolSafeAccountEffective( $username, $school_ids = [ 0 ] )
{
	$rows = SchoolSafeAccountRows( $username );

	if ( ! $rows )
	{
		return 'active';
	}

	foreach ( SchoolSafeAccountSchoolIds( $school_ids ) as $school_id )
	{
		if ( isset( $rows[$school_id] ) )
		{
			return SchoolSafeAccountApplyUntil( $rows[$school_id]['STATE'], $rows[$school_id]['UNTIL'] );
		}
	}

	if ( isset( $rows[0] ) )
	{
		return SchoolSafeAccountApplyUntil( $rows[0]['STATE'], $rows[0]['UNTIL'] );
	}

	return 'active';
}

/**
 * Époque de session de la personne : la plus haute de toutes ses lignes.
 */
function SchoolSafeAccountEpoch( $username )
{
	$epoch = 0;

	foreach ( SchoolSafeAccountRows( $username ) as $row )
	{
		$epoch = max( $epoch, (int) $row['SESSION_EPOCH'] );
	}

	return $epoch;
}

/**
 * École courante de la session (0 = toutes les écoles).
 */
function SchoolSafeAccountCurrentSchool()
{
	if ( function_exists( 'UserSchool' ) )
	{
		$school = (int) UserSchool();

		if ( $school > 0 )
		{
			return $school;
		}
	}

	if ( function_exists( 'SchoolSafeScopeParse' ) && function_exists( 'User' ) )
	{
		$scope = SchoolSafeScopeParse( User( 'SCHOOLS' ) );

		if ( is_array( $scope ) && $scope )
		{
			return (int) $scope[0];
		}
	}

	return 0;
}

/**
 * Écritures autorisées pour l'utilisateur courant ?
 *
 * Suspendu / expiré / lecture seule => false. Utilisé par AllowEdit() et par
 * les routes SchoolSafe qui modifient des données.
 */
function SchoolSafeAccountBlocksWrite()
{
	static $blocks = null;

	if ( $blocks !== null )
	{
		return $blocks;
	}

	$blocks = false;

	if ( empty( $_SESSION['STAFF_ID'] ) || ! function_exists( 'User' ) )
	{
		return $blocks;
	}

	$username = User( 'USERNAME' );

	if ( ! $username )
	{
		return $blocks;
	}

	$state = SchoolSafeAccountEffective( $username, [ SchoolSafeAccountCurrentSchool() ] );

	$blocks = in_array( $state, [ 'readonly', 'suspended', 'expired' ], true );

	return $blocks;
}

/**
 * Enregistre un état et journalise le changement.
 *
 * @param string      $username  Compte cible.
 * @param int         $school_id École visée (0 = toutes les écoles).
 * @param string      $state     active|readonly|suspended|expired.
 * @param string|null $reason    Motif lisible.
 * @param string|null $until     Date de fin (Y-m-d ou Y-m-d H:i:s) ou null.
 * @param bool        $revoke    Incrémenter l'époque de session (déconnecte).
 *
 * @return bool true si enregistré.
 */
function SchoolSafeAccountSet( $username, $school_id, $state, $reason = null, $until = null, $revoke = false )
{
	$states = [ 'active', 'readonly', 'suspended', 'expired' ];

	$person = SchoolSafeAccountPerson( $username );

	$school_id = (int) $school_id;

	if ( $school_id < 0 )
	{
		$school_id = 0;
	}

	if ( ! $person || ! in_array( $state, $states, true ) || ! SchoolSafeAccountReady() )
	{
		return false;
	}

	$until = $until ? date( 'Y-m-d H:i:s', strtotime( (string) $until ) ) : null;

	$rows = SchoolSafeAccountRows( $username );

	$current = $rows[$school_id]['STATE'] ?? null;

	$epoch = (int) ( $rows[$school_id]['SESSION_EPOCH'] ?? 0 );

	// Seuls la suspension, l'expiration et la révocation coupent les sessions
	// ouvertes : passer en lecture seule prend effet à la requête suivante.
	if ( $revoke || in_array( $state, [ 'suspended', 'expired' ], true ) )
	{
		$epoch++;
	}

	$reason = $reason ? mb_substr( trim( (string) $reason ), 0, 500 ) : null;

	$actor = function_exists( 'User' ) ? (string) User( 'USERNAME' ) : '';

	try
	{
		if ( isset( $rows[$school_id] ) )
		{
			DBQuery( "UPDATE schoolsafe_account_state
				SET STATE='" . DBEscapeString( $state ) . "',
				REASON=" . ( $reason === null ? 'NULL' : "'" . DBEscapeString( $reason ) . "'" ) . ",
				UNTIL=" . ( $until === null ? 'NULL' : "'" . DBEscapeString( $until ) . "'" ) . ",
				SESSION_EPOCH='" . $epoch . "',
				CHANGED_BY='" . DBEscapeString( $actor ) . "',
				CHANGED_AT=CURRENT_TIMESTAMP
				WHERE PERSON_KEY='" . DBEscapeString( $person ) . "'
				AND SCHOOL_ID='" . $school_id . "'" );
		}
		else
		{
			DBQuery( "INSERT INTO schoolsafe_account_state
				(PERSON_KEY,SCHOOL_ID,STATE,REASON,UNTIL,SESSION_EPOCH,CHANGED_BY,CHANGED_AT)
				VALUES ('" . DBEscapeString( $person ) . "','" . $school_id . "',
				'" . DBEscapeString( $state ) . "',
				" . ( $reason === null ? 'NULL' : "'" . DBEscapeString( $reason ) . "'" ) . ",
				" . ( $until === null ? 'NULL' : "'" . DBEscapeString( $until ) . "'" ) . ",
				'" . $epoch . "',
				'" . DBEscapeString( $actor ) . "',CURRENT_TIMESTAMP)" );
		}

		DBQuery( "INSERT INTO schoolsafe_account_audit
			(PERSON_KEY,SCHOOL_ID,ACTION,FROM_STATE,TO_STATE,REASON,ACTOR)
			VALUES ('" . DBEscapeString( $person ) . "','" . $school_id . "',
			'" . ( $revoke && $current === $state ? 'revoke' : 'state' ) . "',
			" . ( $current === null ? 'NULL' : "'" . DBEscapeString( $current ) . "'" ) . ",
			'" . DBEscapeString( $state ) . "',
			" . ( $reason === null ? 'NULL' : "'" . DBEscapeString( $reason ) . "'" ) . ",
			'" . DBEscapeString( $actor ) . "')" );
	}
	catch ( Throwable $error )
	{
		return false;
	}

	// L'état vient de changer : la prochaine lecture doit le voir.
	SchoolSafeAccountForget( $person );

	return true;
}

/**
 * Révoque les sessions ouvertes (époque +1), sans changer l'état.
 */
function SchoolSafeAccountRevoke( $username, $school_id = 0 )
{
	$person = SchoolSafeAccountPerson( $username );

	if ( ! $person || ! SchoolSafeAccountReady() )
	{
		return false;
	}

	$school_id = max( 0, (int) $school_id );

	$rows = SchoolSafeAccountRows( $username );

	$current = $rows[$school_id]['STATE'] ?? 'active';

	$epoch = (int) ( $rows[$school_id]['SESSION_EPOCH'] ?? 0 ) + 1;

	$actor = function_exists( 'User' ) ? (string) User( 'USERNAME' ) : '';

	try
	{
		if ( isset( $rows[$school_id] ) )
		{
			DBQuery( "UPDATE schoolsafe_account_state
				SET SESSION_EPOCH='" . $epoch . "',
				CHANGED_BY='" . DBEscapeString( $actor ) . "',CHANGED_AT=CURRENT_TIMESTAMP
				WHERE PERSON_KEY='" . DBEscapeString( $person ) . "'
				AND SCHOOL_ID='" . $school_id . "'" );
		}
		else
		{
			DBQuery( "INSERT INTO schoolsafe_account_state
				(PERSON_KEY,SCHOOL_ID,STATE,SESSION_EPOCH,CHANGED_BY,CHANGED_AT)
				VALUES ('" . DBEscapeString( $person ) . "','" . $school_id . "','active','" . $epoch . "',
				'" . DBEscapeString( $actor ) . "',CURRENT_TIMESTAMP)" );
		}

		DBQuery( "INSERT INTO schoolsafe_account_audit
			(PERSON_KEY,SCHOOL_ID,ACTION,FROM_STATE,TO_STATE,ACTOR)
			VALUES ('" . DBEscapeString( $person ) . "','" . $school_id . "','revoke',
			'" . DBEscapeString( $current ) . "','" . DBEscapeString( $current ) . "',
			'" . DBEscapeString( $actor ) . "')" );
	}
	catch ( Throwable $error )
	{
		return false;
	}

	SchoolSafeAccountForget();

	return true;
}

/**
 * Vide le cache mémoire (après une écriture dans la même requête).
 */
function SchoolSafeAccountForget( $username = null )
{
	$cache = &SchoolSafeAccountCache();

	if ( $username === null )
	{
		$cache = [];
	}
	else
	{
		unset( $cache[SchoolSafeAccountPerson( $username )] );
	}

	return true;
}

/**
 * Garde de session : suspension, expiration ou révocation.
 *
 * Appelée à chaque requête depuis Warehouse.php, après session_start() et le
 * chargement des fonctions, avant tout écran ou route.
 */
function SchoolSafeAccountSessionGuard()
{
	if ( empty( $_SESSION['STAFF_ID'] ) || ! is_numeric( $_SESSION['STAFF_ID'] ) )
	{
		return;
	}

	if ( ! function_exists( 'DBGetOne' ) || ! SchoolSafeAccountReady() )
	{
		return;
	}

	$syear = isset( $_SESSION['UserSyear'] ) ? (int) $_SESSION['UserSyear'] : (int) Config( 'SYEAR' );

	$username = DBGetOne( "SELECT USERNAME FROM staff
		WHERE STAFF_ID='" . (int) $_SESSION['STAFF_ID'] . "'
		AND SYEAR='" . $syear . "'" );

	if ( ! $username )
	{
		// Ligne absente (année changée, compte supprimé) : le natif tranche.
		return;
	}

	$state = SchoolSafeAccountEffective( $username, [ SchoolSafeAccountCurrentSchool() ] );

	$revoked = (int) ( $_SESSION['SchoolSafeEpoch'] ?? 0 ) !== SchoolSafeAccountEpoch( $username );

	if ( $state !== 'suspended' && $state !== 'expired' && ! $revoked )
	{
		return;
	}

	SchoolSafeAccountEndSession();

	$reason = $state === 'suspended' ? 'suspended'
		: ( $state === 'expired' ? 'expired' : 'revoked' );

	if ( ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] )
		|| ( isset( $_SERVER['HTTP_ACCEPT'] )
			&& mb_strpos( (string) $_SERVER['HTTP_ACCEPT'], 'application/json' ) !== false ) )
	{
		http_response_code( 401 );

		header( 'Content-Type: application/json; charset=utf-8' );

		exit( json_encode( [ 'ok' => false, 'error' => 'ACCOUNT_' . mb_strtoupper( $reason ) ] ) );
	}

	header( 'Location: index.php?reason=account_' . $reason );

	exit;
}

/**
 * Ferme proprement la session courante.
 */
function SchoolSafeAccountEndSession()
{
	$_SESSION = [];

	if ( ini_get( 'session.use_cookies' ) )
	{
		$params = session_get_cookie_params();

		setcookie( session_name(), '', time() - 42000,
			$params['path'], $params['domain'],
			! empty( $params['secure'] ), ! empty( $params['httponly'] ) );
	}

	if ( session_status() === PHP_SESSION_ACTIVE )
	{
		session_destroy();
	}
}

/**
 * Message affiché à la connexion quand l'accès est fermé.
 */
function SchoolSafeAccountLoginMessage( $state )
{
	if ( $state === 'suspended' )
	{
		return "Ce compte est suspendu. Contactez l'administration de votre etablissement.";
	}

	if ( $state === 'expired' )
	{
		return "Ce compte a expire. Contactez l'administration de votre etablissement.";
	}

	return '';
}
