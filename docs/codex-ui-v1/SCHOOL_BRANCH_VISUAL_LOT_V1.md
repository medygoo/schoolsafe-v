# Lot visuel Ecole V1

Date : 2026-10-11. Perimetre : fichiers Codex isoles uniquement. Aucun raccordement metier nouveau, aucune modification Rosario, SQL, authentification, Studio Card ou production.

## Couverture du menu natif

Source : C:/Users/PC/Documents/rosario/modules/School_Setup/Menu.php.

| Fonction | Route native | Presentation | Role dans Menu.php |
| --- | --- | --- | --- |
| Informations ecole | School_Setup/Schools.php | school-form.html, acces depuis espace Ecole | admin, teacher, parent |
| Calendriers | School_Setup/Calendar.php | school-workspace.html#calendar | admin, teacher, parent |
| Periodes scolaires | School_Setup/MarkingPeriods.php | marking-periods.html, acces depuis espace Ecole | admin, teacher, parent |
| Creneaux journee | School_Setup/Periods.php | school-workspace.html#periods | admin, teacher |
| Niveaux | School_Setup/GradeLevels.php | grade-levels.html, acces depuis espace Ecole | admin |
| Annonces | School_Setup/PortalNotes.php | school-workspace.html#notes | admin |
| Sondages | School_Setup/PortalPolls.php | school-workspace.html#polls | admin |
| Champs ecole | School_Setup/SchoolFields.php | school-workspace.html#fields | admin |
| Configuration | School_Setup/Configuration.php | school-workspace.html#configuration | admin |
| Copier ecole | School_Setup/CopySchool.php | school-workspace.html#copy | admin |
| Passage annee | School_Setup/Rollover.php | school-workspace.html#rollover | admin |
| Journal connexions | School_Setup/AccessLog.php | school-workspace.html#log | admin |

Ces roles sont ceux du menu, pas une preuve des droits effectifs d'une session. Le menu statique de previsualisation n'est pas un controle d'acces. Le futur affichage doit reutiliser le menu autorise et AllowUse/AllowEdit natifs. Ne pas extrapoler les droits direction/gardien/finance ni ceux des eleves a partir de cette table.

## Organisation

Ecole > Organisation : informations, calendriers, periodes, creneaux, niveaux.

Ecole > Communication : annonces, sondages.

Ecole > Parametrage : champs et configuration.

Ecole > Outils administratifs : copie, passage d'annee, journal.

Navigation par ancres, une selection active, etiquettes et pictogrammes ; accents discrets bleu, vert, violet et ambre. Les trois formulaires existants restent conserves et sont accessibles par lien, sans etre remplaces.

## Preuves de correspondance

Sources sous C:/Users/PC/Documents/rosario/ examinees en lecture seule :

- modules/School_Setup/Periods.php : TITLE 100, SHORT_NAME et BLOCK 10, SORT_ORDER -9999..9999, LENGTH entier -99999..99999, COURSE_PERIODS en lecture. Les heures debut/fin et ATTENDANCE sont commentees dans la liste native : pas de nouveaux champs actifs inventes.
- modules/School_Setup/Calendar.php : titre calendrier 100, copie, defaut, dates, jours de semaine et minutes 1..998 ; evenement titre 50, date, repetition 0..300, notes. Les affectations/devoirs et hooks sont conditionnels.
- modules/School_Setup/PortalNotes.php et ProgramFunctions/PortalPollsNotes.fnc.php : titre, contenu Markdown assaini nativement, nom court, ordre, plage de publication, profils dynamiques, fichier/lien. Aucune sanitisation native remplacee.
- modules/School_Setup/PortalPolls.php : questions 255, options, types multiple_radio/multiple, affichage des votes. Ajout de question visuel local uniquement ; pas de vote ou publication.
- modules/School_Setup/SchoolFields.php et ProgramFunctions/Fields.fnc.php : GetFieldsForm, titre 200, types, options, defaut, obligatoire, ordre ; creation potentiellement structurelle AddDBField.
- modules/School_Setup/Configuration.php : rubriques systeme, ecole, eleves, notes, presences, restauration ; modules et plugins via includes et hooks. Tous les controles sensibles de la previsualisation sont desactives, valeurs reelles absentes.
- modules/School_Setup/CopySchool.php : 8 familles de donnees, configuration forcee, titre et Prompt ; creation et changement d'ecole courante. Aucun Prompt execute.
- modules/School_Setup/Rollover.php : 12 familles de base, familles additionnelles selon modules actifs, dependances et avertissements d'ecrasement, annee par defaut. Aucun passage execute.
- modules/School_Setup/AccessLog.php : date, identifiant, profil, statut, IP, navigateur ; pagination native. Ouverture native susceptible de supprimer automatiquement les lignes de plus d'un an. Ne pas utiliser ce lien comme test de lecture sans examiner ses effets.

## Tests effectues

- node --check school-workspace.js : reussi.
- PHP index.php : lint reussi.
- Navigation des 12 entrees : titre et selection unique verifies a 889 px.
- 36 controles : 12 destinations dans l'espace Ecole a 390x844, 768x1024 et 1280x900 ; innerWidth confirme pour chaque cas ; aucun debordement horizontal de page ; aucun input/select/textarea sans label.
- Les tableaux larges defilent dans leur region propre, sans elargir la page.
- Ajout d'une question de sondage : 1 vers 2, focus sur question-2-0, sans sauvegarde.
- Captures navigateur examinees ; logo et pictogrammes rendus. Dimensions temporaires restaurees.

Les 36 controles concernent l'espace Ecole, pas les trois formulaires externes lies. Ni audit WCAG complet, ni preuve de parcours metier, ni tests de tous les roles. Aucun appel de mutation ni ouverture du journal natif effectue pendant ce lot.

## Limites et suite par fonction

Les 12 fonctions ont une destination visuelle ; cela ne signifie pas que toutes les interfaces sont fonctionnellement finalisees. Ne pas declarer le lot integre ou operationnel.

Priorite 1 : selection ecole/annee et permissions derivees de la session Rosario ; lecture des donnees autorisees ; adaptation du formulaire natif et validation de ses erreurs. Aucun second moteur d'authentification.

Priorite 2 : tableaux editables, selection d'enregistrement, modification et suppression natives ; chargement des profils de publication, periodes et calendriers ; preservation de Markdown et des pieces jointes.

Priorite 3 : calendrier mensuel/jours exceptionnels, devoirs selon role ; formulaires de champs dependants du type et traductions ; liste des modules/plugins et options ajoutees par hooks. Ces sous-parcours ne sont pas encore integralement reproduits dans le visuel.

Priorite 4 : copie et passage d'annee uniquement apres validation des dependances, sauvegarde et confirmation ; journal avec politique de conservation approuvee. Aucun bouton destructeur ajoute.

Les saisies de previsualisation sont temporaires et perdues a la navigation/recharge ; aucun stockage. Les cases desactivees ne representent pas la valeur active. Les liens natifs ne raccordent pas les champs SchoolSafe.

## Manifeste du lot

Nouveaux fichiers :
- app/modules/codex-ui-v1/rosario-view/school-workspace.html
- app/modules/codex-ui-v1/rosario-view/school-workspace.css
- app/modules/codex-ui-v1/rosario-view/school-workspace.js
- docs/codex-ui-v1/SCHOOL_BRANCH_VISUAL_LOT_V1.md

Fichier isole modifie : app/modules/codex-ui-v1/rosario-view/index.php (lien de previsualisation).

Dependances existantes conservees : account-form.css, lucide.min.js, schoolsafe-logo.png et les trois formulaires precedents. Le serveur local contient une copie des nouveaux visuels sous /opt/schoolsafe-view ; aucun fichier du moteur /var/www/html modifie.
