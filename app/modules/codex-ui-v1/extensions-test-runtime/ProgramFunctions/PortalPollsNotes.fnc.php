<?php
/**
 * Portal Polls and Portal Notes functions.
 *
 * @package RosarioSIS
 * @subpackage ProgramFunctions
 */

// Portal Notes Files upload path global.
if ( ! isset( $PortalNotesFilesPath )
	&& is_dir( 'assets/PortalNotesFiles/' )
	// Check if dir has at least 1 uploaded file (apart from README).
	&& count( glob( 'assets/PortalNotesFiles/*.*' ) ) >= 1 )
{
	// @deprecated since 12.0 Portal Notes Files upload path $PortalNotesFilesPath global var
	$PortalNotesFilesPath = 'assets/PortalNotesFiles/';
}

//FJ Portal Polls functions

function PortalPollsVote( $poll_id, $votes_array )
{
	// Get poll:
	$poll_RET = DBGet( "SELECT EXCLUDED_USERS, VOTES_NUMBER, DISPLAY_VOTES
		FROM portal_polls
		WHERE ID='" . (int) $poll_id . "'" );

	$poll_questions_RET = DBGet( "SELECT ID, QUESTION, OPTIONS, VOTES
		FROM portal_poll_questions
		WHERE PORTAL_POLL_ID='" . (int) $poll_id . "'
		ORDER BY ID" );

	// Lot K1 (C3, 05/11) : le sondage doit être visible pour cette personne (école, année, dates, profil),
	// comme sur le tableau de bord ; sinon, aucun vote n'est compté.
	if ( ! $poll_RET || ! $poll_questions_RET || ! SchoolSafePollVisible( $poll_id ) )
	{
		return ErrorMessage( [ 'Ce sondage n’est pas disponible.' ] );
	}

	// Add user to excluded users list, format = '|[profile_id]:[user_id]'.
	$excluded_user = GetPortalPollUser();

	if ( ! $excluded_user
		|| mb_strpos( $poll_RET[1]['EXCLUDED_USERS'] . '|', $excluded_user . '|' ) !== false )
	{
		return ErrorMessage( [ 'Vous avez déjà répondu à ce sondage.' ] );
	}

	// Lot K1 (C3) : seuls des choix existants, une seule fois chacun par question.
	$votes_array = SchoolSafePollCleanVotes( $poll_questions_RET, (array) $votes_array );

	if ( ! $votes_array )
	{
		return ErrorMessage( [ 'Choisissez au moins une réponse.' ] );
	}

	$excluded_users = $poll_RET[1]['EXCLUDED_USERS'] . $excluded_user;

	$poll_questions_updated = PortalPollsSaveVotes( $poll_questions_RET, $votes_array );

	// Submit query.
	DBQuery( "UPDATE portal_polls
		SET EXCLUDED_USERS='" . $excluded_users . "',
		VOTES_NUMBER=" . db_case( [ 'VOTES_NUMBER', "''", '1', 'VOTES_NUMBER+1' ] ) . "
		WHERE ID='" . (int) $poll_id . "'" );

	return PortalPollsVotesDisplay(
		$poll_id,
		$poll_RET[1]['DISPLAY_VOTES'],
		$poll_questions_updated,
		( empty( $poll_RET[1]['VOTES_NUMBER'] ) ? 1 : $poll_RET[1]['VOTES_NUMBER'] + 1 ),
		true
	);
}

/**
 * Lot K1 (C3, 05/11/2026) : le sondage est-il visible pour la personne connectée ?
 * Mêmes conditions que l'affichage du tableau de bord (misc/Portal.php) : année, dates, école, profil
 * (et, pour un élève, enseignant ciblé).
 *
 * @param int $poll_id Sondage.
 *
 * @return bool
 */
function SchoolSafePollVisible( $poll_id )
{
	$base = "pp.ID='" . (int) $poll_id . "'
		AND pp.SYEAR='" . UserSyear() . "'
		AND (pp.START_DATE<=CURRENT_DATE OR pp.START_DATE IS NULL)
		AND (pp.END_DATE>=CURRENT_DATE OR pp.END_DATE IS NULL)";

	if ( User( 'STAFF_ID' ) )
	{
		$profile = in_array( User( 'PROFILE' ), [ 'admin', 'teacher', 'parent' ], true ) ? User( 'PROFILE' ) : 'none';

		return (bool) DBGetOne( "SELECT 1 FROM portal_polls pp,staff st
			WHERE " . $base . "
			AND st.STAFF_ID='" . (int) User( 'STAFF_ID' ) . "'
			AND (st.SCHOOLS IS NULL OR position(CONCAT(',', pp.SCHOOL_ID, ',') IN st.SCHOOLS)>0)
			AND (st.PROFILE_ID IS NULL AND position('," . $profile . ",' IN pp.PUBLISHED_PROFILES)>0
				OR st.PROFILE_ID IS NOT NULL AND position(CONCAT(',', st.PROFILE_ID, ',') IN pp.PUBLISHED_PROFILES)>0)
			LIMIT 1" );
	}

	if ( UserStudentID() )
	{
		return (bool) DBGetOne( "SELECT 1 FROM portal_polls pp
			WHERE " . $base . "
			AND pp.SCHOOL_ID='" . UserSchool() . "'
			AND position(',0,' IN pp.PUBLISHED_PROFILES)>0
			AND (pp.STUDENTS_TEACHER_ID IS NULL OR pp.STUDENTS_TEACHER_ID IN (SELECT cp.TEACHER_ID
				FROM schedule sch,course_periods cp
				WHERE sch.SYEAR='" . UserSyear() . "' AND sch.SCHOOL_ID='" . UserSchool() . "'
				AND sch.STUDENT_ID='" . (int) UserStudentID() . "' AND sch.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID))
			LIMIT 1" );
	}

	return false;
}

/**
 * Lot K1 (C3, 05/11/2026) : garde seulement des choix qui existent, une seule fois chacun par question.
 *
 * @param array $questions Questions du sondage (ID, OPTIONS).
 * @param array $votes     Votes envoyés : [question => choix] ou [question => [choix, ...]].
 *
 * @return array Votes nettoyés.
 */
function SchoolSafePollCleanVotes( $questions, $votes )
{
	$clean = [];

	foreach ( (array) $questions as $question )
	{
		$id = $question['ID'];

		if ( ! isset( $votes[ $id ] ) )
		{
			continue;
		}

		$count = count( explode( "\r", str_replace( [ "\r\n", "\n" ], "\r", (string) $question['OPTIONS'] ) ) );

		if ( is_array( $votes[ $id ] ) )
		{
			$set = [];

			foreach ( $votes[ $id ] as $choice )
			{
				if ( ctype_digit( (string) $choice ) && (int) $choice < $count )
				{
					$set[ (int) $choice ] = (int) $choice;
				}
			}

			if ( $set )
			{
				$clean[ $id ] = array_values( $set );
			}
		}
		elseif ( ctype_digit( (string) $votes[ $id ] ) && (int) $votes[ $id ] < $count )
		{
			$clean[ $id ] = (int) $votes[ $id ];
		}
	}

	return $clean;
}

/**
 * function called by PortalPollsVote()
 * save Votes to portal_poll_questions table
 *
 * @return $poll_questions_RET array updated with Votes
 */
function PortalPollsSaveVotes( $poll_questions_RET, $votes_array )
{
	// Add votes.
	$voted_array = [];

	foreach ( (array) $poll_questions_RET as $key => $question )
	{
		if ( ! empty( $question['VOTES'] ) )
		{
			$voted_array[$question['ID']] = explode( '||', $question['VOTES'] );

			if ( isset( $votes_array[$question['ID']] )
				&& is_array( $votes_array[$question['ID']] ) ) // Multiple.
			{
				foreach ( $votes_array[$question['ID']] as $checked_box )
				{
					$voted_array[$question['ID']][$checked_box]++;
				}
			}
			elseif ( isset( $votes_array[$question['ID']] ) ) // Multiple_radio.
			{
				$voted_array[$question['ID']][$votes_array[$question['ID']]]++;
			}
		}
		else // First vote.
		{
			$voted_array[$question['ID']] = [];

			$options_array = explode( "\r", str_replace( [ "\r\n", "\n" ], "\r", $question['OPTIONS'] ) );

			if ( isset( $votes_array[$question['ID']] )
				&& is_array( $votes_array[$question['ID']] ) ) // Multiple.
			{
				foreach ( $options_array as $option_nb => $option_label )
				{
					$voted_array[$question['ID']][$option_nb] = 0;
				}

				foreach ( $votes_array[$question['ID']] as $checked_box )
				{
					$voted_array[$question['ID']][$checked_box]++;
				}
			}
			else // Multiple_radio.
			{
				foreach ( $options_array as $option_nb => $option_label )
				{
					$voted_array[$question['ID']][$option_nb] = ( isset( $votes_array[$question['ID']] ) && $votes_array[$question['ID']] == $option_nb ? 1 : 0 );
				}
			}
		}

		$voted_array[$question['ID']] = implode( '||', $voted_array[$question['ID']] );

		// Submit query.
		DBUpdate(
			'portal_poll_questions',
			[ 'VOTES' => $voted_array[$question['ID']] ],
			[ 'ID' => (int) $question['ID'] ]
		);

		// Update the $poll_questions_RET array with Votes.
		$poll_questions_RET[$key]['VOTES'] = $voted_array[$question['ID']];
	}

	return $poll_questions_RET;
}

/**
 * @param $value
 * @param $name
 */
function PortalPollsDisplay( $value, $name )
{
	global $THIS_RET;

	$poll_id = $THIS_RET['ID'];

	// Get poll:
	$poll_RET = DBGet( "SELECT EXCLUDED_USERS,VOTES_NUMBER,DISPLAY_VOTES
		FROM portal_polls
		WHERE ID='" . (int) $poll_id . "'" );

	require_once 'ProgramFunctions/Linkify.fnc.php';

	$poll_questions_RET = DBGet( "SELECT ID,QUESTION,OPTIONS,TYPE,VOTES
		FROM portal_poll_questions
		WHERE PORTAL_POLL_ID='" . (int) $poll_id . "'
		ORDER BY ID", [ 'OPTIONS' => 'Linkify' ] );

	if ( ! $poll_RET || ! $poll_questions_RET )
	{
		// Should never be displayed, so do not translate.
		return ErrorMessage( [ 'Poll does not exist' ] );
	}

	$excluded_user = GetPortalPollUser();

	if ( ! $excluded_user )
	{
		// Should never be displayed, so do not translate.
		return ErrorMessage( [ 'User not logged in' ] );
	}

	// Check if user is in excluded users list (format = '|[profile_id]:[user_id]').

	if ( mb_strpos( $poll_RET[1]['EXCLUDED_USERS'] . '|', $excluded_user . '|' ) !== false )
	{
		// User already voted, display votes.
		return PortalPollsVotesDisplay(
			$poll_id,
			$poll_RET[1]['DISPLAY_VOTES'],
			$poll_questions_RET,
			$poll_RET[1]['VOTES_NUMBER']
		);
	}

	return PortalPollForm(
		$poll_id,
		$poll_questions_RET
	);
}

/**
 * Get poll user, using the excluded users format:
 * |[profile_id]:[user_id]
 *
 * @since 3.4
 *
 * @return string User, empty if no user ID.
 */
function GetPortalPollUser()
{
	$profile_id = User( 'PROFILE_ID' );

	$user_id = User( 'STAFF_ID' ) ? User( 'STAFF_ID' ) : UserStudentID();

	if ( ! $user_id )
	{
		return '';
	}

	return '|' . $profile_id . ':' . $user_id;
}

/**
 * Function called by PortalPollsDisplay()
 * generates the Portal Poll's HTML form
 *
 * @param  integer $poll_id            Poll ID.
 * @param  array   $poll_questions_RET Poll questions.
 * @return string  Poll HTML form.
 */
function PortalPollForm( $poll_id, $poll_questions_RET )
{
	$poll_form = '';

	//FJ responsive rt td too large

	if ( ! isset( $_REQUEST['_ROSARIO_PDF'] ) )
	{
		$poll_form .= '<div id="divPortalPoll' . $poll_id . '" class="divPortalPoll rt2colorBox">';
	}

	$poll_form .= '<form method="POST" id="formPortalPoll' . $poll_id . '"
		action="Modules.php?modname=misc/Portal.php&modfunc=poll_vote"
		target="divPortalPoll' . $poll_id . '">
	<table class="width-100p widefat">';

	foreach ( (array) $poll_questions_RET as $question )
	{
		$poll_form .= '<tr><td class="valign-top"><b>' . $question['QUESTION'] . '</b></td>
		<td><table class="width-100p cellspacing-0">';

		$options_array = explode( "\r", str_replace( [ "\r\n", "\n" ], "\r", $question['OPTIONS'] ) );

		$checked = true;

		foreach ( $options_array as $option_nb => $option_label )
		{
			if ( $question['TYPE'] == 'multiple_radio' )
			{
				$name = 'votes[' . $poll_id . '][' . $question['ID'] . ']';

				$poll_form .= '<tr><td>
					<label>
					<input type="radio" name="' . AttrEscape( $name ) . '" value="' .
					AttrEscape( $option_nb ) . '" ' . ( $checked ? 'checked' : '' ) . '>&nbsp;' .
					$option_label .
					'</label>
					</td></tr>' . "\n";
			}
			else // Multiple.
			{
				$name = 'votes[' . $poll_id . '][' . $question['ID'] . '][]';

				$poll_form .= '<tr><td>
					<label>
					<input type="checkbox" name="' . AttrEscape( $name ) . '" value="' .
					AttrEscape( $option_nb ) . '">&nbsp;' . $option_label .
					'</label>
					</td></tr>' . "\n";
			}

			$checked = false;
		}

		$poll_form .= '</table></td></tr>';
	}

	$poll_form .= '</td></tr></table><p>' . Buttons( _( 'Submit' ) ) . '</p></form>';

	if ( ! isset( $_REQUEST['_ROSARIO_PDF'] ) )
	{
		$poll_form .= '</div>';
	}

	return $poll_form;
}

/**
 * @param $poll_id
 * @param $display_votes
 * @param $poll_questions_RET
 * @param $votes_number
 * @param $js_included_is_voting
 * @return mixed
 */
function PortalPollsVotesDisplay( $poll_id, $display_votes, $poll_questions_RET, $votes_number, $js_included_is_voting = false )
{
	if ( ! $display_votes )
	{
		return button( 'check' ) .
		'&nbsp;' . _( 'Poll completed' );
	}

	$votes_display = '';

	// FJ responsive rt td too large.

	if ( ! $js_included_is_voting )
	{
		$votes_display .= '<div id="divPortalPoll' . $poll_id . '" class="divPortalPoll rt2colorBox">' . "\n";
	}

	foreach ( (array) $poll_questions_RET as $question )
	{
		$total_votes = 0;

		// Question.
		$votes_display .= '<p><b>' . $question['QUESTION'] . '</b></p>
			<table class="widefat col1-align-right">' . "\n";

		// Votes.
		$votes_array = explode( '||', $question['VOTES'] );

		foreach ( $votes_array as $votes )
		{
			$total_votes += $votes;
		}

		// Options.
		$options_array = explode( "\r", str_replace( [ "\r\n", "\n" ], "\r", $question['OPTIONS'] ) );

		$options_array_count = count( $options_array );

		for ( $i = 0; $i < $options_array_count; $i++ )
		{
			$percent = 0;

			if ( $total_votes )
			{
				$percent = round( ( $votes_array[$i] / $total_votes ) * 100 );
			}

			$votes_display .= '<tr>
				<td>' . $options_array[$i] . '</td>
				<td><div class="bar" style="width:' . $percent . 'px;">' . $percent . '</div></td>
				<td><b> ' . $percent . '%</b></td>
			</tr>' . "\n";
		}

		$votes_display .= '</table>' . "\n";
	}

	$votes_display .= '<p>' . _( 'Total Participants' ) . ': ' . $votes_number . '</p>';

	if ( ! $js_included_is_voting )
	{
		$votes_display .= '</div>';
	}

	return $votes_display;
}

/**
 * @param $value
 * @param $name
 * @return mixed
 */
function makePublishing( $value, $name )
{
	global $THIS_RET;
	static $profiles = null;

	$id = ! empty( $THIS_RET['ID'] ) ? $THIS_RET['ID'] : 'new';

	//FJ responsive rt td too large
	$return = '<div id="divPublishing' . $id . '" class="rt2colorBox">' . "\n";

	$return .= '<table class="widefat width-100p"><tr><td>';
	$return .= '<div style="display: inline-block">' . DateInput( $value, 'values[' . $id . '][' . $name . ']' ) . '</div>';
	$return .= '<div style="display: inline-block">&nbsp;' . _( 'to' ) . '&nbsp;</div>';
	$return .= '<div style="display: inline-block">' . DateInput( issetVal( $THIS_RET['END_DATE'] ), 'values[' . $id . '][END_DATE]' ) . '</div>';
	$return .= FormatInputTitle( _( 'Visible Between' ) ) . '</td></tr>';

	if ( is_null( $profiles ) )
	{
		$profiles_RET = DBGet( "SELECT ID,TITLE FROM user_profiles ORDER BY ID" );

		$custom_permissions = [];

		$there_is_user_with_custom = function( $profile )
		{
			return (bool) DBGetOne( "SELECT 1 FROM staff
				WHERE PROFILE='" . DBEscapeString( $profile ) . "'
				AND PROFILE_ID IS NULL
				AND SYEAR='" . UserSyear() . "'" );
		};

		if ( $there_is_user_with_custom( 'admin' ) )
		{
			$custom_permissions[] = [ 'ID' => 'admin', 'TITLE' => _( 'Administrator w/Custom' ) ];
		}

		if ( $there_is_user_with_custom( 'teacher' ) )
		{
			$custom_permissions[] = [ 'ID' => 'teacher', 'TITLE' => _( 'Teacher w/Custom' ) ];
		}

		if ( $there_is_user_with_custom( 'parent' ) )
		{
			$custom_permissions[] = [ 'ID' => 'parent', 'TITLE' => _( 'Parent w/Custom' ) ];
		}

		// Add Profiles with Custom permissions to profiles list.
		$profiles = array_merge( $custom_permissions, $profiles_RET );
	}

	$return .= makePublishingVisibleTo( $profiles, $THIS_RET, $id );

	$return .= '</table>';

	if ( ! isset( $_REQUEST['_ROSARIO_PDF'] ) )
	{
		$return .= '</div>';
	}

	return $return;
}

/**
 * Function called by makePublishing()
 * generates the "Visible To" part of the Publishing options
 *
 * @since 12.2 Use multiple Select2 instead of checkboxes
 *
 * @param  array  $profiles User Profiles.
 * @param  array  $THIS_RET Current row from DB.
 * @param  string $id       Portal Poll/Note ID.
 *
 * @return string HTML form
 */
function makePublishingVisibleTo( $profiles, $THIS_RET, $id )
{
	$value = issetVal( $THIS_RET['PUBLISHED_PROFILES'], '' );

	$profile_options = [];

	foreach ( (array) $profiles as $profile )
	{
		$profile_options[ $profile['ID'] ] = _( $profile['TITLE'] );
	}

	$value_array = explode( ',', trim( (string) $value, ',' ) );

	$visibleTo = '<tr><td>' . Select2Input(
		$value_array,
		'values[' . $id . '][PUBLISHED_PROFILES][]',
		_( 'Visible To' ),
		$profile_options,
		'N/A', // Save when none selected, add hidden empty input
		'multiple style="width: 240px" autocomplete="off"', // Multiple select inputs.
		true
	) . '</td></tr>';

	if ( $_REQUEST['modname'] == 'School_Setup/PortalPolls.php'
		&& mb_strpos( $value, ',0,' ) !== false )
	{
		// Portal Polls add students teacher.
		// @since 9.2.1 SQL replace use of STRPOS() with LIKE, compatible with MySQL.
		$teachers_RET = DBGet( "SELECT STAFF_ID," . DisplayNameSQL() . " AS FULL_NAME
		FROM staff
		WHERE (SCHOOLS IS NULL OR position('," . UserSchool() . ",' IN SCHOOLS)>0)
		AND SYEAR='" . UserSyear() . "'
		AND PROFILE='teacher'
		ORDER BY FULL_NAME" );

		$teachers = [];

		foreach ( (array) $teachers_RET as $teacher )
		{
			$teachers[$teacher['STAFF_ID']] = $teacher['FULL_NAME'];
		}

		// Portal Poll, has Student selected.
		$visibleTo .= '<tr><td>' . SelectInput(
			issetVal( $THIS_RET['STUDENTS_TEACHER_ID'] ),
			'values[' . $id . '][STUDENTS_TEACHER_ID]',
			_( 'Student' ) . ' &mdash; ' . _( 'Limit to Teacher' ),
			$teachers,
			'N/A',
			'',
			true
		) . '</td></tr>';
	}

	if ( $_REQUEST['modname'] == 'School_Setup/PortalNotes.php' )
	{
		// Action hook
		do_action( 'School_Setup/PortalNotes.php|portal_note_field', [ $id ] );
	}

	return $visibleTo;
}

//FJ file attached to portal notes
/**
 * @param $value
 * @param $name
 * @return mixed
 */
function makeFileAttached( $value, $name )
{
	global $THIS_RET,
		$PortalNotesFilesPath,
		$FileUploadsPath;

	static $filesAttachedCount = 0;

	// @since 12.0 Use $FileUploadsPath . 'PortalNotes/' instead of $PortalNotesFilesPath
	$portal_notes_files_path = $PortalNotesFilesPath ?
		$PortalNotesFilesPath :
		$FileUploadsPath . 'PortalNotes/';

	if ( ! empty( $THIS_RET['ID'] ) )
	{
		$id = $THIS_RET['ID'];

		if ( empty( $value ) )
		{
			return '&nbsp;';
		}

		$filesAttachedCount++;

		//FJ colorbox
		$view_online = '<img src="assets/themes/' . Preferences( 'THEME' ) . '/btn/visualize.png" class="button bigger"> <b>' . _( 'View Online' ) . '</b>';

		$download = '<img src="assets/themes/' . Preferences( 'THEME' ) . '/btn/download.png" class="button bigger"> <b>' . _( 'Download' ) . '</b>';

		if ( filter_var( $value, FILTER_VALIDATE_URL ) !== false ) //embed link
		{
			return '<a href="' . URLEscape( $value ) . '" title="' . AttrEscape( $value ) . '" class="colorboxiframe">' . $view_online . '</a>';
		}

		return '<a href="' . URLEscape( $value ) . '" title="' . AttrEscape( str_replace( $portal_notes_files_path, '', $value ) ) . '" target="_blank">' . $download . '</a>';
	}

	$id = 'new';

	$return = '<div id="divFileAttached' . $id . '" class="rt2colorBox"><div>';

	$return .= FileInput( $name . '_FILE', _( 'File Attached' ) );

	$return .= '<br>' . TextInput(
		'',
		'values[' . $id . '][' . $name . '_EMBED]',
		_( 'Embed Link' ),
		'size="22" placeholder="https://"'
	) . '</div></div>';

	return $return;
}
