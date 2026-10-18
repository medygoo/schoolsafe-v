# SchoolSafe natif : bilan consolide au 12 octobre 2026

Ce bilan est la reference actuelle. Les rapports precedents conservent l'historique des essais, notamment l'ancien seuil desktop 1200px qui n'est plus en vigueur.

## Lot 1 : implementation en place, validation partielle

- Theme SchoolSafeCodex isole, parent SchoolSafe conserve.
- Menu principal et sous-panneau independant codes des 875px ; dimensions 236/248px, variante 204/220px jusqu'a 1100px.
- Regroupement des liens autorises par Rosario selon les branches SchoolSafe. Aucun droit ajoute, aucun href metier change.
- Comparaison precedente du menu administrateur : 121 liens avant, 122 apres, aucune destination manquante ; ajout du tableau de bord via le lien natif du logo.
- Controle des frais non affiche faute de destination native distincte identifiee. Les fonctionnalites externes SchoolSafe ne sont pas inventees dans le menu Rosario.
- Navigation clavier et ouverture exclusive testees lors des campagnes precedentes. Navigation AJAX vers niveaux et horaires observee dans cette campagne.
- Synchronisation des classes ss-open et aria-expanded apres ajaxPrepare du contenu ajoutee. Tous les etats sont reinitialises avant application de la selection native.
- Syntaxe scripts.js : node --check reussi.

Reserves : panneau desktop non valide visuellement (viewport reel environ 703px malgre les demandes precedentes), autres roles, impressions, liens non parcourus et franchissement du breakpoint non verifies. Lot 1 non declare totalement valide. Ces reserves restent dans la campagne finale ; elles n'empechent pas le travail visuel du lot 2.

## Lot 2 : demarre sur les formulaires existants

Ajout de school-forms.css au theme : titres, recherche, bordure turquoise, alternance des lignes, champs natifs et debordement des periodes sur petit ecran. La feuille est ciblee par data-ss-page, renseigne a l'ouverture et apres navigation AJAX. Aucun remplacement de formulaire.

Verification dans le navigateur : GradeLevels.php puis Periods.php ; data-ss-page suit la navigation, une seule branche Ecole ouverte, accent calcule rgb(8,118,107), champs texte des horaires a 44px. Aucun enregistrement soumis. Preuve : lot2-horaires-natifs.png.

### Perimetre a traiter par ecran

| Module School_Setup | Emplacement SchoolSafe | Etat de cette campagne |
| --- | --- | --- |
| Schools.php | Ecole / Informations | Style cible ajoute ; inspection visuelle detaillee restante |
| GradeLevels.php | Ecole / Niveaux | Chargement natif et style constate |
| Periods.php | Ecole / Horaires | Chargement, accent et champs constates |
| MarkingPeriods.php | Ecole / Periodes scolaires | Debordement petit ecran code ; validation restante |
| Calendar.php | Ecole / Calendriers | Reste a harmoniser et verifier en detail |
| Configuration.php | Administration / Configuration | Reste a examiner et harmoniser |
| SchoolFields.php | Administration / Champs ecole | Reste a examiner et harmoniser |
| CopySchool.php | Administration / Copie | Presentation restante ; copie non executee |
| Rollover.php | Administration / Changement annee | Presentation restante ; aucune execution autorisee ici |
| PortalNotes.php | Communication / Annonces | Hors sous-branche Ecole ; traitement prevu lot communication |
| PortalPolls.php | Communication / Sondages | Hors sous-branche Ecole ; traitement prevu lot communication |
| AccessLog.php | Securite / Journal acces | Hors sous-branche Ecole ; traitement prevu lot securite |
| Menu.php | Generation native du menu | Ce n'est pas un ecran utilisateur ; conserve intact |

Sources examinees : modules/School_Setup/*.php (inventaire des fichiers), Schools.php, GradeLevels.php, Periods.php, MarkingPeriods.php (DrawHeader, PopTable, ListOutput), assets/js/warehouse.js (ajaxPrepare et openMenu), theme SchoolSafeCodex/scripts.js et redesign.css, modele codex-ui-v1.css et branch-presentation.css lors de la campagne precedente.

L'existence d'un fichier n'est pas une preuve d'acces pour tous les roles. Les comptes/profils/permissions Users du lot 2 restent a examiner. La fidelite complete au modele n'est pas encore acquise.

## Fichiers modifies dans cette campagne

- Theme SchoolSafeCodex/scripts.js : synchronisation menu et identification de page, presentation uniquement.
- Theme SchoolSafeCodex/stylesheet.css : import de school-forms.css.
- Theme SchoolSafeCodex/school-forms.css : nouveau style cible.
- Copies correspondantes dans native-theme-runtime/assets/themes/SchoolSafeCodex pour preview locale.
- Ce rapport et lot2-horaires-natifs.png.

Moteur PHP Rosario, authentification, droits, base, Studio Cartes, scanner, recuperation, JASPE et VPS : aucun changement dans cette campagne. Le paquet natif v1 anterieur est historique ; le paquet v2 inclut ce bilan et la nouvelle feuille.
