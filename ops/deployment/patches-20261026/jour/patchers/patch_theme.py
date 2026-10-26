import io

p = r'C:/Users/PC/AppData/Local/Temp/users-extraction/theme-scripts.js'
src = io.open(p, encoding='utf-8').read()
T, N = chr(9), chr(10)

anchor = T * 2 + "var heading = body.querySelector('.ss-page-heading');"

block = (
    T * 2 + "// SchoolSafe : explication concrete PAR onglet de Mes preferences" + N
    + T * 2 + "if (body.dataset.ssPage === 'Users/Preferences') {" + N
    + T * 3 + "var ssTabExplanations = {" + N
    + T * 4 + "password: 'Change le mot de passe de votre compte. Il est mis à jour pour toutes les années scolaires de votre nom d’utilisateur : votre prochaine connexion utilisera le nouveau mot de passe. L’ancien mot de passe est demandé pour confirmer votre identité.'," + N
    + T * 4 + "display_options: 'Règle ce que VOUS voyez à l’écran, sans effet pour les autres : la couleur de surbrillance des listes, le format des dates affichées, et l’option de masquer le rappel de dernière connexion. Le thème est verrouillé par SchoolSafe : la présentation est unique et identique pour tous.'," + N
    + T * 4 + "print_options: 'Règle les documents PDF que vous imprimez : format du papier, couleur de la bande d’en-tête des listes, et côté de la fenêtre des enveloppes pour les étiquettes postales. Ces réglages ne concernent que vous.'," + N
    + T * 4 + "student_listing: 'Règle vos listes d’élèves : l’ordre de tri par défaut, le format du fichier exporté (et de ses dates), et l’affichage de l’écran de recherche avant chaque liste.'," + N
    + T * 4 + "student_fields: 'Choisissez les informations d’élève que VOUS voyez : Recherche = critères proposés dans l’écran de recherche ; Vue détaillée = blocs affichés sur la fiche élève. Décocher vous masque le champ à vous seul. La catégorie Adresses choisit l’adresse affichée : aucune, résidence, courrier, montée ou descente du bus.'," + N
    + T * 4 + "widgets: 'Chaque widget coché ajoute UNE ligne de critère dans votre recherche d’élèves : calendrier, année suivante, cours, absences, moyenne, rang, note, éligibilité, activité, cantine, discipline, facturation. Aucun autre effet : ne cochez que ce que vous utilisez.'," + N
    + T * 4 + "staff_fields: 'Identique aux champs élève, mais pour les fiches UTILISATEURS (personnel, parents) : Recherche = critères de recherche ; Vue détaillée = blocs de la fiche utilisateur.'," + N
    + T * 4 + "staff_widgets: 'Chaque widget coché ajoute un critère dans votre recherche d’utilisateurs : Permissions (filtre par droits), solde/statut/code-barres cafeteria du personnel, solde de paie.'" + N
    + T * 3 + "};" + N
    + T * 3 + "var ssTab = new URL(location.href).searchParams.get('tab');" + N
    + T * 3 + "if (!ssTab) ssTab = 'password'; // le serveur force password par defaut" + N
    + T * 3 + "if (ssTabExplanations[ssTab]) pageHelp['Users/Preferences'] = ssTabExplanations[ssTab];" + N
    + T * 2 + "}" + N
)

assert src.count(anchor) == 1
src = src.replace(anchor, block + anchor)

io.open(p, 'w', encoding='utf-8', newline='\n').write(src)
print('bloc Users/Preferences ajoute au theme')
