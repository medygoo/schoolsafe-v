<?php
require_once 'ProgramFunctions/PortalPollsNotes.fnc.php';
require_once 'ProgramFunctions/FileUpload.fnc.php';
require_once 'ProgramFunctions/MarkDownHTML.fnc.php';

DrawHeader( ProgramTitle() );

// Add eventual Dates to $_REQUEST['values'].
AddRequestedDates( 'values', 'post' );

if ( $_REQUEST['modfunc'] === 'update'
	&& AllowEdit() )
{
	$_REQUEST['values'] = issetVal( $_REQUEST['values'], [] );

	foreach ( (array) $_REQUEST['values'] as $id => $columns )
	{
		if ( isset( $columns['PUBLISHED_PROFILES'] ) )
		{
			$published_profiles = '';

			foreach ( (array) $columns['PUBLISHED_PROFILES'] as $profile )
			{
				if ( $profile != '' )
				{
					$published_profiles .= ',' . $profile;
				}
			}

			$columns['PUBLISHED_PROFILES'] = '';

			if ( $published_profiles )
			{
				$columns['PUBLISHED_PROFILES'] = $published_profiles . ',';
			}
		}

		// FJ fix SQL bug invalid sort order.

		if ( empty( $columns['SORT_ORDER'] ) || is_numeric( $columns['SORT_ORDER'] ) )
		{
			// FJ textarea fields MarkDown sanitize.

			if ( isset( $columns['CONTENT'] ) )
			{
				$columns['CONTENT'] = DBEscapeString( SanitizeMarkDown( $_POST['values'][$id]['CONTENT'] ) );
			}

			if ( $id !== 'new' )
			{
				DBUpdate(
					'portal_notes',
					$columns,
					[
						'ID' => (int) $id,
						'SCHOOL_ID' => UserSchool(),
						'SYEAR' => UserSyear(),
					]
				);

				//hook
				do_action( 'School_Setup/PortalNotes.php|update_portal_note' );
			}

			// New: check for Title.
			elseif ( $columns['TITLE'] )
			{
				// @since 10.9 Fix security issue, unset any FILE_ATTACHED column first.
				$columns['FILE_ATTACHED'] = '';

				if ( ! empty( $_FILES['FILE_ATTACHED_FILE']['name'] ) )
				{
					// @since 12.0 Use $FileUploadsPath . 'PortalNotes/' instead of $PortalNotesFilesPath
					$portal_notes_files_path = ! empty( $PortalNotesFilesPath ) ?
						$PortalNotesFilesPath :
						$FileUploadsPath . 'PortalNotes/';

					// File attached to portal notes
					$columns['FILE_ATTACHED'] = FileUpload(
						'FILE_ATTACHED_FILE',
						$portal_notes_files_path,
						FileExtensionWhiteList(),
						0,
						$error,
						'',
						FileNameTimestamp( $_FILES['FILE_ATTACHED_FILE']['name'] )
					);
				}
				elseif ( filter_var( $columns['FILE_ATTACHED_EMBED'], FILTER_VALIDATE_URL ) !== false )
				{
					$columns['FILE_ATTACHED'] = $columns['FILE_ATTACHED_EMBED'];
				}

				unset( $columns['FILE_ATTACHED_EMBED'] );

				$insert_columns = [
					'SCHOOL_ID' => UserSchool(),
					'SYEAR' => UserSyear(),
					'PUBLISHED_USER' => User( 'STAFF_ID' ),
				];

				if ( empty( $error ) )
				{
					// Global var used by Moodle plugin.
					$portal_note_id = DBInsert(
						'portal_notes',
						$insert_columns + $columns,
						'id'
					);

					//hook
					do_action( 'School_Setup/PortalNotes.php|create_portal_note' );

					// Lot K2a (05/11) : « Prévenir les parents » sur une NOUVELLE annonce seulement
					// (une modification ne renvoie jamais rien). Case décochée par défaut + confirmation exigée.
					if ( ! empty( $portal_note_id )
						&& User( 'PROFILE' ) === 'admin'
						&& ! empty( $_REQUEST['schoolsafe_notify_parents'] ) )
					{
						if ( empty( $_REQUEST['schoolsafe_notify_confirm'] ) )
						{
							$error[] = 'Annonce enregistrée, mais les parents n’ont pas été prévenus : la confirmation n’était pas cochée.';
						}
						elseif ( ! _schoolSafePortalNoteForParents( issetVal( $columns['PUBLISHED_PROFILES'], '' ) ) )
						{
							$error[] = 'Annonce enregistrée, mais les parents n’ont pas été prévenus : elle n’est pas visible par le profil Parent.';
						}
						else
						{
							$schoolsafe_notified = _schoolSafePortalNoteNotifyParents(
								(int) $portal_note_id,
								issetVal( $_POST['values']['new']['TITLE'], '' ),
								issetVal( $_POST['values']['new']['CONTENT'], '' )
							);

							$note[] = sprintf(
								'Parents prévenus : %d famille(s), pour %d élève(s), dans l’application, et sur WhatsApp pour les parents qui ont donné leur accord.',
								$schoolsafe_notified['families'],
								$schoolsafe_notified['students']
							);

							if ( $schoolsafe_notified['capped'] )
							{
								$note[] = 'Limite de 800 élèves atteinte : les élèves suivants n’ont pas été prévenus.';
							}
						}
					}
				}
			}
		}
		else
		{
			$error[] = _( 'Please enter a valid Sort Order.' );
		}
	}

	// Unset modfunc & values & profiles & redirect URL.
	// Lot K2a (05/11) : les deux cases « Prévenir les parents » sont aussi retirées de l'URL.
	RedirectURL( [ 'modfunc', 'values', 'profiles', 'schoolsafe_notify_parents', 'schoolsafe_notify_confirm' ] );
}

if ( $_REQUEST['modfunc'] === 'remove'
	&& AllowEdit() )
{
	if ( DeletePrompt( _( 'Note' ) ) )
	{
		// FJ file attached to portal notes.
		$file_to_remove = DBGetOne( "SELECT FILE_ATTACHED
			FROM portal_notes
			WHERE ID='" . (int) $_REQUEST['id'] . "'
			AND SYEAR='" . UserSyear() . "'
			AND SCHOOL_ID='" . UserSchool() . "'" );

		// Security: use FileDelete() instead of unlink()
		FileDelete( $file_to_remove );

		DBQuery( "DELETE FROM portal_notes
			WHERE ID='" . (int) $_REQUEST['id'] . "'
			AND SYEAR='" . UserSyear() . "'
			AND SCHOOL_ID='" . UserSchool() . "'" );

		//hook
		do_action( 'School_Setup/PortalNotes.php|delete_portal_note' );

		// Unset modfunc & ID & redirect URL.
		RedirectURL( [ 'modfunc', 'id' ] );
	}
}

echo ErrorMessage( $error );

// Lot K2a (05/11) : compte rendu de l'envoi aux familles.
if ( ! empty( $note ) )
{
	echo ErrorMessage( $note, 'note' );
}

if ( ! $_REQUEST['modfunc'] )
{
	// File attached to portal notes.
	$notes_RET = DBGet( "SELECT ID,SORT_ORDER,TITLE,CONTENT,START_DATE,END_DATE,PUBLISHED_PROFILES,FILE_ATTACHED,
		CASE WHEN END_DATE IS NOT NULL AND END_DATE<CURRENT_DATE THEN 'Y' ELSE NULL END AS EXPIRED
	FROM portal_notes
	WHERE SCHOOL_ID='" . UserSchool() . "'
	AND SYEAR='" . UserSyear() . "'
	ORDER BY EXPIRED DESC,SORT_ORDER IS NULL,SORT_ORDER,CREATED_AT DESC", [
		'TITLE' => '_makeTextInput',
		'CONTENT' => '_makeContentInput',
		'SORT_ORDER' => '_makeTextInput',
		'FILE_ATTACHED' => 'makeFileAttached',
		'START_DATE' => 'makePublishing'
	] );

	$columns = [
		'TITLE' => _( 'Title' ),
		'CONTENT' => _( 'Note' ),
		'SORT_ORDER' => _( 'Sort Order' ),
		'FILE_ATTACHED' => _( 'File Attached' ),
		'START_DATE' => _( 'Publishing Options' ),
	];

	//,'START_TIME' => 'Start Time','END_TIME' => 'End Time'
	$link['add']['html'] = [
		'TITLE' => _makeTextInput( '', 'TITLE' ),
		'CONTENT' => _makeContentInput( '', 'CONTENT' ),
		'SHORT_NAME' => _makeTextInput( '', 'SHORT_NAME' ),
		'SORT_ORDER' => _makeTextInput( '', 'SORT_ORDER' ),
		'FILE_ATTACHED' => makeFileAttached( '', 'FILE_ATTACHED' ),
		'START_DATE' => makePublishing( '', 'START_DATE' ),
	];

	// Lot K2a (05/11) : option « Prévenir les parents » pour une nouvelle annonce (administrateur avec droit de modification).
	if ( User( 'PROFILE' ) === 'admin'
		&& AllowEdit() )
	{
		$link['add']['html']['START_DATE'] .= _schoolSafePortalNoteNotifyInput();
	}

	$link['remove']['link'] = 'Modules.php?modname=' . $_REQUEST['modname'] . '&modfunc=remove';
	$link['remove']['variables'] = [ 'id' => 'ID' ];

	if ( empty( $_REQUEST['_ROSARIO_PDF'] ) )
	{
		echo '<header class="ss-f01 ss-rise">';
		echo '<p class="ss-f01-kicker"><img src="assets/themes/SchoolSafe/icons/note.svg" alt="" width="20" height="20"> ' . SchoolSafeT( 'Notes', 'Annonces' ) . '</p>';
		echo '<h2>' . SchoolSafeT( 'Portal notes', 'Annonces du portail' ) . '</h2>';
		echo '<p>' . SchoolSafeT( 'This page is where you write the messages shown on the school portal, attach a file, and choose who can see them and on which dates.', 'Cette page permet de rédiger les messages affichés sur le portail de l’école, de joindre un fichier, et de choisir qui peut les voir et pendant quelles dates.' ) . '</p>';
		echo '</header>';
	}

	echo '<form action="' . URLEscape( 'Modules.php?modname=' . $_REQUEST['modname'] . '&modfunc=update' ) . '" method="POST" enctype="multipart/form-data">';

	DrawHeader( '', SubmitButton() );

	ListOutput( $notes_RET, $columns, 'Note', 'Notes', $link );

	echo '<br /><div class="center">' . SubmitButton() . '</div>';
	echo '</form>';
}

/**
 * @param $value
 * @param $name
 */
function _makeTextInput( $value, $name )
{
	global $THIS_RET;

	$id = ! empty( $THIS_RET['ID'] ) ? $THIS_RET['ID'] : 'new';

	$extra = '';

	if ( $name === 'SORT_ORDER' )
	{
		$extra = ' type="number" min="-9999" max="9999"';
	}
	elseif ( $name !== 'TITLE' )
	{
		$extra = 'size=5 maxlength=10';
	}
	elseif ( $id !== 'new' )
	{
		$extra = 'required';
	}

	return TextInput(
		( $name == 'TITLE' && ! empty( $THIS_RET['EXPIRED'] ) ?
			[ $value, '<span style="color:red">' . $value . '</span>' ] :
			$value ),
		'values[' . $id . '][' . $name . ']',
		'',
		$extra
	);
}

/**
 * @param $value
 * @param $name
 * @return mixed
 */
function _makeContentInput( $value, $name )
{
	global $THIS_RET;

	$id = ! empty( $THIS_RET['ID'] ) ? $THIS_RET['ID'] : 'new';

	$return = '<div id="divNoteContent' . $id . '" class="rt2colorBox">' .
		TextAreaInput( $value, "values[" . $id . "][" . $name . "]", '', 'rows=5' ) .
		'</div>';

	return $return;
}

/**
 * Lot K2a (05/11) : cases « Prévenir les parents » + confirmation (décochées par défaut).
 *
 * @return string HTML
 */
function _schoolSafePortalNoteNotifyInput()
{
	return '<div class="ss-portal-note-notify" style="margin-top:8px">'
		. '<label><input type="checkbox" name="schoolsafe_notify_parents" value="Y"'
		. ' onchange="var c=this.form.elements.schoolsafe_notify_confirm;c.disabled=!this.checked;c.required=this.checked;if(!this.checked)c.checked=false;"> '
		. 'Prévenir les parents (application et WhatsApp)</label><br>'
		. '<label><input type="checkbox" name="schoolsafe_notify_confirm" value="Y" disabled> '
		. 'Je confirme l’envoi de cette annonce à toutes les familles de l’école</label><br>'
		. '<small>Seulement à l’enregistrement d’une nouvelle annonce visible par le profil Parent. '
		. 'WhatsApp seulement pour les parents qui ont donné leur accord.</small>'
		. '</div>';
}

/**
 * Lot K2a (05/11) : l'annonce est-elle publiée pour le profil Parent ?
 *
 * @param string $published_profiles Liste ",parent,3," enregistrée.
 *
 * @return bool
 */
function _schoolSafePortalNoteForParents( $published_profiles )
{
	$profiles = array_filter( explode( ',', (string) $published_profiles ), 'strlen' );

	if ( in_array( 'parent', $profiles, true ) )
	{
		return true;
	}

	$profile_ids = [];

	foreach ( $profiles as $profile )
	{
		if ( ctype_digit( (string) $profile ) )
		{
			$profile_ids[] = (int) $profile;
		}
	}

	if ( ! $profile_ids )
	{
		return false;
	}

	return (bool) DBGetOne( "SELECT 1 FROM user_profiles
		WHERE PROFILE='parent'
		AND ID IN (" . implode( ',', $profile_ids ) . ")
		LIMIT 1" );
}

/**
 * Lot K2a (05/11) : texte brut d'une annonce (sans MarkDown ni HTML).
 *
 * @param string $text Texte saisi.
 * @param int    $max  Longueur maximale.
 *
 * @return string
 */
function _schoolSafePortalNotePlain( $text, $max )
{
	$text = str_replace( [ "\r\n", "\r" ], "\n", (string) $text );
	// Images retirées, liens gardés sous la forme « texte (adresse) ».
	$text = preg_replace( '/!\[([^\]]*)\]\([^)]*\)/u', '$1', $text );
	$text = preg_replace( '/\[([^\]]+)\]\(([^)\s]+)[^)]*\)/u', '$1 ($2)', (string) $text );
	$text = strip_tags( (string) $text );
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	// Titres, citations, puces, gras, code.
	$text = preg_replace( '/^[ \t]{0,3}(#{1,6}|>+)[ \t]*/mu', '', $text );
	$text = preg_replace( '/^[ \t]*[*+][ \t]+/mu', '- ', (string) $text );
	$text = str_replace( [ '**', '__', '~~', '`' ], '', (string) $text );
	$text = preg_replace( '/[ \t]+/u', ' ', $text );
	$text = preg_replace( "/\n{3,}/u", "\n\n", (string) $text );

	return mb_substr( trim( (string) $text ), 0, (int) $max );
}

/**
 * Lot K2a (05/11) : un événement « school.announcement » par élève de l'école courante,
 * vers ses parents liés (chemin commun ResolveRecipients + Emit : application, WhatsApp si accord).
 * Clé de déduplication par annonce et par élève : jamais deux envois.
 *
 * @param int    $note_id Annonce créée.
 * @param string $title   Titre saisi.
 * @param string $content Texte saisi (MarkDown).
 *
 * @return array families, students, capped
 */
function _schoolSafePortalNoteNotifyParents( $note_id, $title, $content )
{
	require_once 'ProgramFunctions/SchoolSafeNotify.fnc.php';

	$school = (int) UserSchool();
	$year = (int) UserSyear();
	$note_id = (int) $note_id;
	$result = [ 'families' => 0, 'students' => 0, 'capped' => false ];

	if ( $note_id < 1 || $school < 1 || $year < 1 )
	{
		return $result;
	}

	$db = [
		'get' => function( $sql ) { return DBGet( DBQuery( $sql ) ); },
		'exec' => function( $sql ) { DBQuery( $sql ); },
	];

	$students_RET = DBGet( "SELECT DISTINCT STUDENT_ID
		FROM student_enrollment
		WHERE SCHOOL_ID=" . $school . "
		AND SYEAR=" . $year . "
		AND (START_DATE IS NULL OR START_DATE<=CURRENT_DATE)
		AND (END_DATE IS NULL OR END_DATE>=CURRENT_DATE)
		ORDER BY STUDENT_ID
		LIMIT 801" );

	$student_ids = array_map( 'intval', array_column( (array) $students_RET, 'STUDENT_ID' ) );

	if ( count( $student_ids ) > 800 )
	{
		$result['capped'] = true;
		$student_ids = array_slice( $student_ids, 0, 800 );
	}

	$plain_title = _schoolSafePortalNotePlain( $title, 150 );
	$plain_content = _schoolSafePortalNotePlain( $content, 1500 );
	$message = $plain_title . ( $plain_content !== '' ? "\n\n" . $plain_content : '' );
	$families = [];

	foreach ( $student_ids as $student_id )
	{
		if ( $student_id < 1 )
		{
			continue;
		}

		try
		{
			$recipients = SchoolSafeNotifyResolveRecipients( 'school.announcement', $school, $year, $student_id, $db );

			if ( ! $recipients )
			{
				continue;
			}

			$out = SchoolSafeNotifyEmit( [
				'school_id' => $school,
				'syear' => $year,
				'student_id' => $student_id,
				'actor_staff_id' => (int) User( 'STAFF_ID' ),
				'event_type' => 'school.announcement',
				'dedup_key' => 'portal-note:' . $note_id . ':' . $student_id,
				'payload' => [
					'source' => 'portal_note',
					'note_id' => $note_id,
					'title' => $plain_title,
					'message' => $message,
				],
			], $recipients, $db, true );

			if ( is_array( $out ) && ! empty( $out['created'] ) )
			{
				$result['students']++;

				foreach ( $recipients as $recipient )
				{
					if ( $recipient['audience'] === 'parent' )
					{
						$families[ (int) $recipient['staff_id'] ] = true;
					}
				}
			}
		}
		catch ( Throwable $x )
		{
			error_log( 'SchoolSafe portal note notify: ' . $x->getMessage() );
		}
	}

	$result['families'] = count( $families );

	return $result;
}
