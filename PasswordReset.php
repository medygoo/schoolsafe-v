<?php
/**
 * Password Reset
 *
 * @since 2.9
 *
 * @package RosarioSIS
 */

require_once 'Warehouse.php';

// SchoolSafe L4 — moteur partagé de récupération (Brevo-d'abord, throttle
// des liens, OTP) : factorise l'émission du lien natif sans la reconstruire.
require_once 'ProgramFunctions/SchoolSafeRecovery.fnc.php';

/**
 * Send email with password reset link.
 * Activate debug mode for error messages.
 */
if ( isset( $_POST['email'] )
	&& ! empty( $_REQUEST['email'] ) )
{
	// SchoolSafe — anti-abus : maximum 5 demandes / 10 min par IP (journal
	// access_log, STATUS 'P'). Refus silencieux avec le message générique :
	// aucune énumération de comptes, même en cas de limitation.
	$schoolsafe_reset_ip = SchoolSafeRecoveryClientIp();
	$schoolsafe_reset_recent = SchoolSafeRecoveryRequestAttempts( $schoolsafe_reset_ip );
	if ( (int) $schoolsafe_reset_recent >= 5 )
	{
		if ( ! ROSARIO_DEBUG )
		{
			header( 'Location: index.php?modfunc=logout&reason=password_reset&token=' . $_SESSION['token'] );
			exit;
		}
		$error[] = 'Too many password reset requests. Please try again later.';
	}
	elseif ( ! filter_var( $_REQUEST['email'], FILTER_VALIDATE_EMAIL ) )
	{
		if ( ROSARIO_DEBUG )
		{
			$error[] = 'Invalid email.';
		}
	}
	else
	{
		// SQL Handle case when multiple users have same email: order by Failed Login.
		$user_RET = DBGet( "SELECT STAFF_ID AS ID,EMAIL,USERNAME,'staff' AS USER_TYPE
			FROM staff
			WHERE LOWER(EMAIL)=LOWER('" . $_REQUEST['email'] . "')
			AND SYEAR='" . Config( 'SYEAR' ) . "'
			ORDER BY FAILED_LOGIN IS NULL,FAILED_LOGIN DESC
			LIMIT 1" );

		if ( ! $user_RET
			&& Config( 'STUDENTS_EMAIL_FIELD' ) )
		{
			$email_field = Config( 'STUDENTS_EMAIL_FIELD' ) === 'USERNAME' ?
				'USERNAME' : 'CUSTOM_' . (int) Config( 'STUDENTS_EMAIL_FIELD' );

			// SQL Handle case when multiple users have same email: order by Failed Login.
			$user_RET = DBGet( "SELECT s.STUDENT_ID AS ID,s.USERNAME,'student' AS USER_TYPE,
				s." . DBEscapeIdentifier( $email_field ) . " AS EMAIL
				FROM students s,student_enrollment ssm
				WHERE LOWER(s." . DBEscapeIdentifier( $email_field ) . ")=LOWER('" . $_REQUEST['email'] . "')
				AND s.STUDENT_ID=ssm.STUDENT_ID
				AND ssm.SYEAR='" . Config( 'SYEAR' ) . "'
				AND ('" . DBDate() . "'>=ssm.START_DATE
				AND (ssm.END_DATE IS NULL
					OR '" . DBDate() . "'<=ssm.END_DATE ) )
				ORDER BY s.FAILED_LOGIN IS NULL,s.FAILED_LOGIN DESC
				LIMIT 1" );
		}

		if ( ! $user_RET )
		{
			if ( ROSARIO_DEBUG )
			{
				$error[] = 'No account with this email were found.';
			}
		}
		elseif ( ! $user_RET[1]['USERNAME'] )
		{
			if ( ROSARIO_DEBUG )
			{
				$error[] = 'No username with this account were found.';
			}
		}
		else
		{
			$email_sent = _sendPasswordResetEmail(
				$user_RET[1]['ID'],
				$user_RET[1]['USER_TYPE'],
				$user_RET[1]['EMAIL']
			);

			if ( ! $email_sent )
			{
				// SchoolSafe — panne d'envoi tracée côté serveur (journal
				// applicatif) : l'utilisateur voit le message générique,
				// l'administrateur retrouve l'échec dans les logs Docker.
				error_log( 'SchoolSafe password reset email FAILED id=' .
				(int) $user_RET[1]['ID'] . ' type=' . $user_RET[1]['USER_TYPE'] );
				if ( ROSARIO_DEBUG )
				{
					$error[] = 'Password reset email could not be sent.';
				}
			}
			elseif ( ROSARIO_DEBUG )
			{
				$note[] = 'Success!';
			}
		}
	}

	// SchoolSafe — chaque demande est journalisée (succès ou non, même
	// message) pour l'anti-abus ci-dessus ; aucun détail exposé.
	SchoolSafeRecoveryRequestLog( $schoolsafe_reset_ip );

	if ( ! ROSARIO_DEBUG )
	{
		// Redirect to login page.
		header( 'Location: index.php?modfunc=logout&reason=password_reset&token=' . $_SESSION['token'] );

		exit;
	}
}

// Password reset form.
if ( ! empty( $_REQUEST['h'] )
	&& ( mb_strlen( $_REQUEST['h'] ) == 106
		|| mb_strlen( $_REQUEST['h'] ) == 105 )
	&& mb_substr( $_REQUEST['h'], 0, 3 ) == '$6$' )
{
	// SchoolSafe L4 — limitation des tentatives sur les liens : au-delà de
	// 30 vérifications / 10 min / IP, refus générique (aucun oracle : même
	// message que le lien inconnu). Chaque vérification est journalisée.
	$schoolsafe_link_ip = SchoolSafeRecoveryClientIp();
	$schoolsafe_link_locked = SchoolSafeRecoveryLinkAttempts( $schoolsafe_link_ip ) >= 30;
	SchoolSafeRecoveryLinkLog( $schoolsafe_link_ip );
	if ( $schoolsafe_link_locked )
	{
		$error[] = _( 'Please enter your email again.' );
	}
	else
	{
	// Select Staff where last login > now.
	$staff_RET = DBGet( "SELECT STAFF_ID AS ID, USERNAME, PASSWORD, EMAIL,
		" . DisplayNameSQL() . " AS FULL_NAME, LAST_LOGIN, PROFILE_ID
		FROM staff
		WHERE LAST_LOGIN > CURRENT_TIMESTAMP
		AND SYEAR='" . Config( 'SYEAR' ) . "'" );

	$student_RET = [];

	if ( Config( 'STUDENTS_EMAIL_FIELD' ) )
	{
		$email_field = Config( 'STUDENTS_EMAIL_FIELD' ) === 'USERNAME' ?
			'USERNAME' : 'CUSTOM_' . (int) Config( 'STUDENTS_EMAIL_FIELD' );

		// Select Students where last login > now & enrolled.
		$student_RET = DBGet( "SELECT s.STUDENT_ID AS ID, s.USERNAME, s.PASSWORD,
			s." . DBEscapeIdentifier( $email_field ) . " AS EMAIL,
			" . DisplayNameSQL( 's' ) . " AS FULL_NAME, s.LAST_LOGIN
			FROM students s, student_enrollment se
			WHERE s.LAST_LOGIN > CURRENT_TIMESTAMP
			AND se.SYEAR='" . Config( 'SYEAR' ) . "'
			AND se.STUDENT_ID=s.STUDENT_ID
			AND ('" . DBDate() . "'>=se.START_DATE
				AND ('" . DBDate() . "'<=se.END_DATE
					OR se.END_DATE IS NULL ) )" );
	}

	if ( ! $staff_RET
		&& ! $student_RET )
	{
		$error[] = _( 'Please enter your email again.' );
	}
	else
	{
		$hash_matched = false;

		foreach ( (array) $staff_RET as $staff )
		{
			// Recheck at redemption, including links issued before an account changed state.
			if ( ! SchoolSafeRecoveryStaffEligible( (int) $staff['ID'] ) )
			{
				continue;
			}
			// Generate plain hash from user ID, username, name, password, email & last login.
			$plain_hash = $staff['ID'] . $staff['USERNAME'] . $staff['FULL_NAME'] .
				$staff['PASSWORD'] . $staff['EMAIL'] . $staff['LAST_LOGIN'];

			if ( match_password( $_REQUEST['h'], $plain_hash ) )
			{
				$hash_matched = true;

				$user_id = $staff['ID'];

				$user_type = 'staff';

				$user_profile = $staff['PROFILE_ID'];

				// @since 11.1 Prevent using App name, username, or email in the password
				$_ROSARIO['PasswordInput']['user_inputs'] = [
					$staff['USERNAME'],
					$staff['EMAIL'],
				];

				break;
			}
		}

		foreach ( (array) $student_RET as $student )
		{
			// Generate plain hash from user ID, username, name, password, email & last login.
			$plain_hash = $student['ID'] . $student['USERNAME'] . $student['FULL_NAME'] .
				$student['PASSWORD'] . $student['EMAIL'] . $student['LAST_LOGIN'];

			if ( match_password( $_REQUEST['h'], $plain_hash ) )
			{
				$hash_matched = true;

				$user_id = $student['ID'];

				$user_type = 'student';

				// @since 11.1 Prevent using App name, username, or email in the password
				$_ROSARIO['PasswordInput']['user_inputs'] = [
					$student['USERNAME'],
					$student['EMAIL'],
				];

				break;
			}
		}

		if ( $hash_matched )
		{
			// Verify new password if any.
			if ( isset( $_POST['PASSWORD'] )
				&& $_REQUEST['PASSWORD'] !== '' )
			{
				$new_password = $_POST['PASSWORD'];

				if ( $user_type === 'staff' )
				{
					// Preserve native all-school-years semantics, but consume the
					// matched snapshot under a row lock and revoke in the same transaction.
					if ( ! SchoolSafeRecoveryCompleteStaffReset( (int) $user_id,
						(string) $staff['PASSWORD'], (string) $staff['LAST_LOGIN'], $new_password ) )
					{
						// Expired/replayed/concurrent failure: no success or credentials exposed.
						header( 'Location: PasswordReset.php' );
						exit;
					}

					// If admin, send notification email to server admin.
					if ( $user_profile == 1 )
					{
						_notifyServerAdminPasswordReset( $user_id );
					}
				}
				elseif ( $user_type === 'student' )
				{					// Update password.
					DBUpdate(
						'students',
						[ 'PASSWORD' => encrypt_password( $new_password ) ],
						[ 'STUDENT_ID' => (int) $user_id ]
						);

					// SchoolSafe — usage unique du lien (voir personnel).
					DBQuery( "UPDATE students SET LAST_LOGIN=CURRENT_TIMESTAMP
						WHERE STUDENT_ID='" . (int) $user_id . "'" );

					// SchoolSafe L4 — révocation enregistrée (la garde de
					// session ne couvre aujourd'hui que le personnel ; le
					// lien reste à usage unique et expiré en 2 h).
					if ( function_exists( 'SchoolSafeAccountRevoke' ) )
					{
						SchoolSafeAccountRevoke( $student['USERNAME'], 0 );
					}
				}

				unset(
					$_POST['PASSWORD'],
					$_REQUEST['PASSWORD']
				);

				// Redirect to login page.
				header( 'Location: index.php' );

				exit;
			}

			$password_reset_form = true;
		}
		else
		{
			$error[] = _( 'Please enter your email again.' );
		}
	}
	} // Fin du garde L4 de limitation des tentatives sur les liens.
}

$_ROSARIO['page'] = 'password-reset';

Warehouse( 'header' );

echo '<link rel="stylesheet" href="assets/login/auth-recovery.css?v=1">'
    . '<link rel="stylesheet" href="assets/themes/SchoolSafeCodex/menu-logo.css?v=login-1">'
    . '<header class="ss-auth-recovery-brand"><a href="index.php" aria-label="SchoolSafe">'
    . '<img class="ss-animated-brand" src="schoolsafe-card-bridge/studio-validated-20261008/logo-schoolsafe.png" width="48" height="48" alt="">'
    . '<strong>School<span>Safe</span></strong></a><a class="ss-auth-recovery-back" href="index.php">'
    . _( 'Back' ) . '</a></header>';

$_ROSARIO['HeaderIcon'] = 'misc';

DrawHeader( _( 'Password help' ) );

if ( ! empty( $password_reset_form ) )
{
	_passwordResetForm( $_REQUEST['h'], $user_id );

	Warehouse( 'footer' );

	exit;
}

echo ErrorMessage( $error );

// If Student email field config option not set,
// notify that no student can use the password reset.
if ( ! Config( 'STUDENTS_EMAIL_FIELD' ) )
{
	$note[] = _( 'Password reset is not activated for students.' );
}

echo ErrorMessage( $note, 'note' );

// Forgot your password? form.
?>
<form action="PasswordReset.php" method="POST" target="_top">

	<?php PopTable( 'header', _( 'Forgot your password?' ) ); ?>

		<label><input type="email" name="email" id="email" class="width-100p" maxlength="255" autocomplete="email" inputmode="email" tabindex="1" required autofocus />
		<?php echo _( 'Email' ); ?></label>
		<br />
		<br />
		<?php echo Buttons( _( 'Send password reset instructions' ) ); ?>
		<br />
		<br />
		<p><em><?php echo _( 'Si vous ne recevez aucun email, contactez l\'administration de votre établissement : un administrateur peut régénérer un mot de passe temporaire depuis la console « Comptes et accès ».' ); ?></em></p>

	<?php PopTable( 'footer' ); ?>

</form>
<?php

Warehouse( 'footer' );


/**
 * Send Password Reset email
 *
 * @param  string $user_id   User ID (Staff or Student).
 * @param  string $user_type 'staff'|'student'.
 * @param  string $email     Email address.
 *
 * @return boolean           true if email sent, else false
 */
function _sendPasswordResetEmail( $user_id, $user_type, $email )
{
	// SchoolSafe L4 — délégation au moteur partagé : même cryptographie du
	// lien, même échéance +2 h, même mise à jour conditionnelle à l'envoi
	// réussi ; transport Brevo-d'abord avec repli natif. Rien de reconstruit.
	$out = SchoolSafeRecoveryIssueReset( $user_id, $user_type, $email );

	return ! empty( $out['sent'] );
}


function _passwordResetForm( $hash, $user_id )
{
	if ( ! $hash
		|| ! $user_id )
	{
		return;
	}

	?>
	<form action="PasswordReset.php" method="POST" target="_top">

		<input type="hidden" name="h" value="<?php echo AttrEscape( $hash ); ?>" />

		<?php PopTable( 'header', _( 'Reset your password' ) ); ?>

			<?php
			AllowEditTemporary( 'start' );

			echo PasswordInput(
				'',
				'PASSWORD',
				_( 'New Password' ),
				'strength maxlength="42" tabindex="1" required'
			);

			AllowEditTemporary( 'stop' );
			?>

			<br />
			<div class="center"><?php echo Buttons( _( 'Submit' ) ); ?></div>

		<?php PopTable( 'footer' ); ?>

	</form>
	<?php
}


// @deprecated since 12.5
function _printPageHead( $title )
{
	global $locale,
		$error,
		$note,
		$_ROSARIO;

	$_ROSARIO['page'] = 'password-reset';

	Warehouse( 'header' );

	$_ROSARIO['HeaderIcon'] = 'misc';

	DrawHeader( _( 'Password help' ) );

	echo ErrorMessage( $error );

	echo ErrorMessage( $note, 'note' );
}


function _notifyServerAdminPasswordReset( $user_id )
{
	global $RosarioNotifyAddress;

	// Notify the network admin that a new admin has been created.
	if ( ! filter_var( $RosarioNotifyAddress, FILTER_VALIDATE_EMAIL )
		|| ! $user_id )
	{
		return false;
	}

	$staff_RET = DBGet( "SELECT USERNAME," . DisplayNameSQL() . " AS FULL_NAME,PROFILE
		FROM staff
		WHERE STAFF_ID='" . (int) $user_id . "'
		AND SYEAR='" . Config( 'SYEAR' ) . "'" );

	// FJ add SendEmail function.
	require_once 'ProgramFunctions/SendEmail.fnc.php';

	$to = $RosarioNotifyAddress;

	$name = $staff_RET[1]['FULL_NAME'];

	$subject = sprintf( 'Password Reset: %s', $name );

	$profile = $staff_RET[1]['PROFILE'];

	$username = $staff_RET[1]['USERNAME'];

	$ip = ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] )
		// Filter IP, HTTP_* headers can be forged.
		&& filter_var( $_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP ) ?
		$_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'] );

	$message = sprintf( 'Password Reset for: %s
Profile: %s
Remote IP: %s', $username, $profile, $ip );

	return SendEmail( $to, $subject, $message );
}
