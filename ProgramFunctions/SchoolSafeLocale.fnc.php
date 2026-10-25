<?php
/**
 * SchoolSafe phrases that are not in the Rosario catalog.
 *
 * Rosario translates its own labels through the locale.
 * These phrases follow the same choice: French or English.
 *
 * @package RosarioSIS
 */

/**
 * @param string $english English phrase.
 * @param string $french  French phrase.
 *
 * @return string
 */
function SchoolSafeT( $english, $french )
{
	$locale = isset( $_SESSION['locale'] ) ? (string) $_SESSION['locale'] : '';

	if ( strncmp( $locale, 'fr', 2 ) === 0 )
	{
		return $french;
	}

	return $english;
}

/**
 * Page card: one title and the explanation. Rosario's own title stays hidden by the theme.
 *
 * @param string $icon      Icon file name, without the path or extension.
 * @param string $kicker_en Short label, English.
 * @param string $kicker_fr Short label, French.
 * @param string $title_en  Page title, English.
 * @param string $title_fr  Page title, French.
 * @param string $text_en   Explanation, English.
 * @param string $text_fr   Explanation, French.
 */
function SchoolSafeCard( $icon, $kicker_en, $kicker_fr, $title_en, $title_fr, $text_en, $text_fr )
{
	if ( ! empty( $_REQUEST['_ROSARIO_PDF'] ) )
	{
		return;
	}

	echo '<header class="ss-f13 ss-rise">';
	echo '<p class="ss-f13-kicker"><img src="assets/themes/SchoolSafe/icons/' . AttrEscape( $icon ) . '.svg" alt="" width="20" height="20"> ' .
		SchoolSafeT( $kicker_en, $kicker_fr ) . '</p>';
	echo '<h2>' . SchoolSafeT( $title_en, $title_fr ) . '</h2>';
	echo '<p>' . SchoolSafeT( $text_en, $text_fr ) . '</p>';
	echo '</header>';
}
