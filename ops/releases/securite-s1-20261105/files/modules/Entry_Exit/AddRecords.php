<?php
// Pointage manuel du personnel (oubli de badge, correction).
// SchoolSafe lot S1 (S2, 05/11/2026) : la page ne fonctionnait pas (requête 6 colonnes / 5 valeurs,
// bouton hors du formulaire, heure jamais reçue, point de passage fixé à 1). Réécrite : date, heure,
// sens (entrée = point 1, sortie = point 2), motif obligatoire ; l'auteur est noté avec le motif ;
// seul un membre du personnel de l'école en cours peut être pointé.

DrawHeader( ProgramTitle() );

$staffs = DBGet( "SELECT STAFF_ID, FIRST_NAME || ' ' || LAST_NAME AS FULL_NAME
	FROM staff WHERE SYEAR='" . UserSyear() . "' AND PROFILE IN ('admin','teacher')
	AND (CURRENT_SCHOOL_ID='" . (int) UserSchool() . "' OR POSITION('," . (int) UserSchool() . ",' IN SCHOOLS) > 0)
	ORDER BY FULL_NAME" );

$options = [];

foreach ( (array) $staffs as $staff )
{
	$options[ $staff['STAFF_ID'] ] = $staff['FULL_NAME'];
}

// Valeurs brutes du formulaire ($_REQUEST est déjà échappé par le logiciel d'origine).
$values = isset( $_POST['values'] ) && is_array( $_POST['values'] ) ? $_POST['values'] : [];

if ( $values && AllowEdit() )
{
	$staff_id = (int) ( $values['staff_id'] ?? 0 );
	$type = ( $values['type'] ?? '' ) === 'out' ? 'out' : 'in';
	$date = (string) ( $values['date'] ?? '' );
	$hour = (string) ( $values['hour'] ?? '' );
	$reason = trim( (string) ( $values['reason'] ?? '' ) );

	if ( ! isset( $options[ $staff_id ] ) )
	{
		$error[] = 'Choisissez un membre du personnel de l’école.';
	}
	elseif ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! VerifyDate( $date )
		|| ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $hour ) )
	{
		$error[] = 'Indiquez une date et une heure valides.';
	}
	elseif ( strtotime( $date . ' ' . $hour ) > time() + 300 )
	{
		$error[] = 'Un pointage ne peut pas être dans le futur.';
	}
	elseif ( $reason === '' )
	{
		$error[] = 'Indiquez le motif du pointage manuel (oubli de badge, correction…).';
	}
	else
	{
		DBQuery( "INSERT INTO entry_exit_staff_records
			(STAFF_ID, CHECKPOINT_ID, TYPE, UNAUTHORIZED, COMMENTS, RECORDED_AT)
			VALUES ('" . $staff_id . "', '" . ( $type === 'out' ? 2 : 1 ) . "', '" . $type . "', '',
			'" . DBEscapeString( mb_substr( 'Pointage manuel par ' . User( 'NAME' ) . ' : ' . $reason, 0, 250 ) ) . "',
			'" . DBEscapeString( $date . ' ' . $hour . ':00' ) . "')" );

		$note[] = button( 'check' ) . '&nbsp;Pointage enregistré : ' . AttrEscape( $options[ $staff_id ] )
			. ', ' . ( $type === 'out' ? 'sortie' : 'entrée' ) . ' le ' . AttrEscape( $date ) . ' à ' . AttrEscape( $hour ) . '.';

		$values = [];
	}
}

echo ErrorMessage( $note, 'note' );
echo ErrorMessage( $error );

echo '<form action="' . URLEscape( 'Modules.php?modname=' . $_REQUEST['modname'] ) . '" method="POST">';

PopTable( 'header', 'Pointage manuel' );

echo '<p class="legend-gray">Pour un membre du personnel qui a oublié son badge. Le pointage est enregistré avec votre nom et le motif.</p>';

echo '<table class="width-100p"><tr class="st"><td>';
echo SelectInput( $values['staff_id'] ?? '', 'values[staff_id]', 'Personnel', $options, 'N/D', 'required' );
echo '</td><td>';
echo SelectInput( $values['type'] ?? 'in', 'values[type]', 'Sens', [ 'in' => 'Entrée', 'out' => 'Sortie' ], false );
echo '</td></tr><tr class="st"><td>';
echo '<label>Date<br><input type="date" name="values[date]" required max="' . date( 'Y-m-d' ) . '" value="'
	. AttrEscape( $values['date'] ?? date( 'Y-m-d' ) ) . '"></label>';
echo '</td><td>';
echo '<label>Heure<br><input type="time" name="values[hour]" required value="'
	. AttrEscape( $values['hour'] ?? date( 'H:i' ) ) . '"></label>';
echo '</td></tr><tr class="st"><td colspan="2">';
echo '<label>Motif<br><input type="text" name="values[reason]" required maxlength="150" size="40" value="'
	. AttrEscape( $values['reason'] ?? '' ) . '" placeholder="Oubli de badge, correction…"></label>';
echo '</td></tr></table>';

PopTable( 'footer' );

echo '<div class="center">' . SubmitButton( 'Enregistrer le pointage' ) . '</div>';

echo '</form>';
