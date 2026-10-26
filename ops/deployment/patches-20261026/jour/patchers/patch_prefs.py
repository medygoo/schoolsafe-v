import io

p = r'C:/Users/PC/AppData/Local/Temp/users-extraction/Preferences.php'
src = io.open(p, encoding='utf-8').read()

TAB, NL, Q = chr(9), chr(10), chr(39)

# ---------- A : explication contextuelle par onglet ----------
old_a = '''DrawHeader( ProgramTitle() );

SchoolSafeCard(
\t'settings',
\t'Settings',
\t'Réglages',
\t'My preferences',
\t'Mes préférences',
\t'This page sets the display, the printing, the password and the list columns for the signed-in user.',
\t'Cette page règle l’affichage, l’impression, le mot de passe et les colonnes des listes pour l’utilisateur connecté.'
);

if ( empty( $_REQUEST['tab'] )
\t|| User( 'PROFILE' ) === 'student' )
{
\t$_REQUEST['tab'] = 'password';
}'''

new_a = '''DrawHeader( ProgramTitle() );

if ( empty( $_REQUEST['tab'] )
\t|| User( 'PROFILE' ) === 'student' )
{
\t$_REQUEST['tab'] = 'password';
}

// SchoolSafe : explication contextuelle concrete PAR onglet (demande du
// propriétaire) — le texte du haut decrit l'onglet choisi, avec repli sur
// le texte general. Motif existant : bandeau theme verrouille (onglet Affichage).
$ss_tab_explanations = [
\t'password' => [
\t\t'Changes your account password. It is updated for every school year of your username: your next sign-in, here and in every other school where you are registered, will use the new password. The current password is required to confirm your identity.',
\t\t'Change le mot de passe de votre compte. Il est mis à jour pour toutes les années scolaires de votre nom d’utilisateur : votre prochaine connexion, ici comme dans les autres écoles où vous êtes inscrit, utilisera le nouveau mot de passe. L’ancien mot de passe est demandé pour confirmer votre identité.',
\t],
\t'display_options' => [
\t\t'Sets what YOU see on screen, with no effect on other users: the highlight color of list rows, the format of every displayed date, and the option to hide the last sign-in reminder. The theme is locked by SchoolSafe: the presentation is unique and identical for everyone.',
\t\t'Règle ce que VOUS voyez à l’écran, sans effet pour les autres utilisateurs : la couleur de surbrillance des lignes des listes, le format de toutes les dates affichées, et la possibilité de masquer le rappel de dernière connexion. Le thème est verrouillé par SchoolSafe : la présentation est unique et identique pour tous.',
\t],
\t'print_options' => [
\t\t'Sets the PDF documents you print: paper size, the header band color of printed lists, and the envelope window side for mailing labels. These settings apply to you only.',
\t\t'Règle les documents PDF que vous imprimez : format du papier, couleur de la bande d’en-tête des listes, et côté de la fenêtre des enveloppes pour les étiquettes postales. Ces réglages ne concernent que vous.',
\t],
\t'student_listing' => [
\t\t'Sets your student lists: the default sort order, the downloaded file format (and its dates), and whether the search screen shows before each list.',
\t\t'Règle vos listes d’élèves : l’ordre de tri par défaut, le format du fichier exporté (et de ses dates), et l’affichage de l’écran de recherche avant chaque liste.',
\t],
\t'student_fields' => [
\t\t'Choose the student information YOU see: Search column = criteria offered in the search screen; Detail View = blocks shown on the student page. Unchecking hides the field for you only — other users are not affected. The Addresses category chooses the displayed address: none, residence, mailing, bus pickup or bus drop-off.',
\t\t'Choisissez les informations d’élève que VOUS voyez : colonne Recherche = critères proposés dans l’écran de recherche ; Vue détaillée = blocs affichés sur la fiche élève. Décocher vous masque le champ à vous seul — les autres utilisateurs ne sont pas affectés. La catégorie Adresses choisit l’adresse affichée : aucune, résidence, courrier, montée ou descente du bus.',
\t],
\t'widgets' => [
\t\t'Each checked widget adds ONE criteria line to your student search screen: calendar, next year, course, request, days absent, GPA, rank, letter grade, eligibility, activity, cafeteria balance/status/barcode, discipline (reporter, date, fields), billing balance. No other effect: check only what you use.',
\t\t'Chaque widget coché ajoute UNE ligne de critère dans votre écran de recherche d’élèves : calendrier, année suivante, cours, demande, jours d’absence, moyenne, rang, note, éligibilité, activité, solde/statut/code-barres cafeteria, discipline (rapporteur, date, champs), solde de facturation. Aucun autre effet : ne cochez que ce que vous utilisez.',
\t],
\t'staff_fields' => [
\t\t'Same as student fields, but for USER records (staff, parents): Search = criteria of the user search screen; Detail View = blocks of the user page.',
\t\t'Identique aux champs élève, mais pour les fiches UTILISATEURS (personnel, parents) : Recherche = critères de l’écran de recherche des utilisateurs ; Vue détaillée = blocs de la fiche utilisateur.',
\t],
\t'staff_widgets' => [
\t\t'Each checked widget adds a criteria to your user search: Permissions (rights filter), staff cafeteria balance/status/barcode, payroll balance.',
\t\t'Chaque widget coché ajoute un critère dans votre recherche d’utilisateurs : Permissions (filtre par droits), solde/statut/code-barres cafeteria du personnel, solde de paie.',
\t],
];

$ss_generic_explanation = [
\t'This page sets the display, the printing, the password and the list columns for the signed-in user.',
\t'Cette page règle l’affichage, l’impression, le mot de passe et les colonnes des listes pour l’utilisateur connecté.',
];

$ss_explanation = isset( $ss_tab_explanations[ $_REQUEST['tab'] ] ) ?
\t$ss_tab_explanations[ $_REQUEST['tab'] ] :
\t$ss_generic_explanation;

SchoolSafeCard(
\t'settings',
\t'Settings',
\t'Réglages',
\t'My preferences',
\t'Mes préférences',
\t$ss_explanation[0],
\t$ss_explanation[1]
);'''

assert src.count(old_a) == 1, 'A motif: %d' % src.count(old_a)
src = src.replace(old_a, new_a)

# ---------- B1 : garde profil sur l'onglet widgets ----------
old_b1 = "\tif ( $_REQUEST['tab'] === 'widgets' )"
new_b1 = ("\tif ( $_REQUEST['tab'] === 'widgets'\n"
          "\t\t&& in_array( User( 'PROFILE' ), [ 'admin', 'teacher' ], true ) )")
assert src.count(old_b1) == 1, 'B1 motif: %d' % src.count(old_b1)
src = src.replace(old_b1, new_b1)

# ---------- B2 : hidden StudentFieldsSearch (miroir ligne 752) ----------
# Construction sans ambiguite d'echappement : chr(NL)/chr(TAB) explicites.
old_b2 = ('\tif ( $_REQUEST[' + Q + 'tab' + Q + '] === ' + Q + 'student_fields' + Q + ' )'
          + NL + '\t{' + NL
          + '\t\t$custom_fields_sql = "SELECT sfc.TITLE AS CATEGORY,cf.ID,cf.TITLE,cf.TYPE,'
          + NL + TAB * 4 + "'' AS SEARCH,'' AS DISPLAY")

new_b2 = ('\tif ( $_REQUEST[' + Q + 'tab' + Q + '] === ' + Q + 'student_fields' + Q + ' )'
          + NL + '\t{' + NL
          + '\t\t// SchoolSafe B2 : hidden indispensable — si toutes les cases Recherche sont'
          + NL + "\t\t// decochees, aucune cle values[StudentFieldsSearch] n'est postee sinon."
          + NL + "\t\techo '<input type=\"hidden\" name=\"values[StudentFieldsSearch]\" />';"
          + NL + NL
          + '\t\t$custom_fields_sql = "SELECT sfc.TITLE AS CATEGORY,cf.ID,cf.TITLE,cf.TYPE,'
          + NL + TAB * 4 + "'' AS SEARCH,'' AS DISPLAY")

assert src.count(old_b2) == 1, 'B2 motif: %d' % src.count(old_b2)
src = src.replace(old_b2, new_b2)

# ---------- B2-purge + B3 : apres le controle PAGE_SIZE, avant le foreach ----------
old_b23 = '''\t\t\tunset( $_REQUEST['values']['Preferences']['PAGE_SIZE'] );
\t\t}
'''

new_b23 = '''\t\t\tunset( $_REQUEST['values']['Preferences']['PAGE_SIZE'] );
\t\t}

\t\t// SchoolSafe B2 : tout decocher doit pouvoir tout effacer. Les cases
\t\t// decochees ne postent rien et ProgramUserConfig n'efface jamais :
\t\t// sans cles array pour un programme de l'onglet courant, on purge
\t\t// ses lignes (celles de l'utilisateur connecte uniquement).
\t\t$ss_tab_programs = [
\t\t\t'student_fields' => [ 'StudentFieldsSearch', 'StudentFieldsView' ],
\t\t\t'widgets' => [ 'WidgetsSearch' ],
\t\t\t'staff_fields' => [ 'StaffFieldsSearch', 'StaffFieldsView' ],
\t\t\t'staff_widgets' => [ 'StaffWidgetsSearch' ],
\t\t];

\t\tif ( isset( $ss_tab_programs[ $_REQUEST['tab'] ] ) )
\t\t{
\t\t\tforeach ( $ss_tab_programs[ $_REQUEST['tab'] ] as $ss_program )
\t\t\t{
\t\t\t\tif ( ! isset( $_REQUEST['values'][ $ss_program ] )
\t\t\t\t\t|| ! is_array( $_REQUEST['values'][ $ss_program ] ) )
\t\t\t\t{
\t\t\t\t\tDBQuery( "DELETE FROM program_user_config
\t\t\t\t\t\tWHERE PROGRAM='" . $ss_program . "'
\t\t\t\t\t\tAND USER_ID='" . (int) User( 'STAFF_ID' ) . "'" );
\t\t\t\t}
\t\t\t}
\t\t}

\t\t// SchoolSafe B3 : valeurs de preferences validees — couleurs en hex
\t\t// (#rrggbb) et longueur plafonnee, comme le controle PAGE_SIZE ci-dessus.
\t\tif ( isset( $_REQUEST['values']['Preferences'] ) && is_array( $_REQUEST['values']['Preferences'] ) )
\t\t{
\t\t\tforeach ( [ 'HIGHLIGHT', 'HEADER' ] as $ss_color_key )
\t\t\t{
\t\t\t\tif ( isset( $_REQUEST['values']['Preferences'][ $ss_color_key ] )
\t\t\t\t\t&& ! preg_match( '/^#[0-9a-fA-F]{6}$/', (string) $_REQUEST['values']['Preferences'][ $ss_color_key ] ) )
\t\t\t\t{
\t\t\t\t\tunset( $_REQUEST['values']['Preferences'][ $ss_color_key ] );
\t\t\t\t}
\t\t\t}

\t\t\tforeach ( $_REQUEST['values']['Preferences'] as $ss_key => $ss_value )
\t\t\t{
\t\t\t\tif ( ! is_string( $ss_value ) || mb_strlen( $ss_value ) > 100 )
\t\t\t\t{
\t\t\t\t\tunset( $_REQUEST['values']['Preferences'][ $ss_key ] );
\t\t\t\t}
\t\t\t}
\t\t}
'''

assert src.count(old_b23) == 1, 'B23 motif: %d' % src.count(old_b23)
src = src.replace(old_b23, new_b23)

io.open(r'C:/Users/PC/AppData/Local/Temp/users-extraction/patched/Preferences.php', 'w', encoding='utf-8', newline='\n').write(src)
print('A + B1 + B2 + B3 OK')
