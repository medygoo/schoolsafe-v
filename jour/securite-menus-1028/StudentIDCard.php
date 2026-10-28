<?php
/**
 * Student_ID_Card/StudentIDCard.php — le programme natif derriere le droit
 * « Student_ID_Card » (AllowUse). Ce fichier rendait jadis les cartes ;
 * SchoolSafe remplace son rendu par le Studio de cartes valide. La passerelle
 * redevient thus reelle : le droit coche dans Profiles protège ce redirect.
 */
if ( ! AllowUse( 'Student_ID_Card/StudentIDCard.php' ) )
{
	http_response_code( 403 );
	echo 'Permission requise : cartes d\'eleve.';
	return;
}
DrawHeader( 'Cartes d\'eleve — SchoolSafe' );
echo '<p>Le rendu et l\'emission des cartes se font dans le Studio de cartes SchoolSafe (droit identique « Student_ID_Card »).</p>';
echo '<p><a class="button" href="Modules.php?modname=' . rawurlencode( 'SchoolSafe/Studio' ) . '">Ouvrir le Studio de cartes</a></p>';
echo '<p><a class="button" href="studio-native.php">Studio natif (session requise)</a></p>';
