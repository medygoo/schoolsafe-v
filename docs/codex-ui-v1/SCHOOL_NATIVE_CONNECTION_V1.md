# Raccordement natif Ecole : verification locale

Date : 2026-10-11.

## Implementation

school-live.php, school-live.css : espace SchoolSafe isole reutilisant Warehouse.php et Menu.php en lecture ; session STAFF_ID/STUDENT_ID et AllowUse(route) Rosario. Liste blanche des 12 destinations School_Setup. Aucune recreation d'authentification, de validation, d'API ou de requete SQL.

Neuf fonctions affichent leur vrai module /Modules.php dans une iframe de meme origine. Les formulaires et scripts natifs restent responsables des mutations et controles. Le lien d'ouverture separe conserve une solution si l'iframe ne convient pas.

Copie, passage d'annee et journal : avertissement et lien natif explicite, sans chargement automatique du module. Le journal peut purger des donnees a l'ouverture ; aucun test d'ouverture effectue.

Le raccordement realise est un acces aux ecrans natifs dans une enveloppe SchoolSafe. Ce n'est PAS le raccordement des champs des anciennes pages HTML de previsualisation : celles-ci restent des brouillons. Le style interieur natif reste visible, avec sa navigation. Ne pas presenter cette etape comme une finalisation du design integre.

## Verification

- PHP lint valide.
- Sans cookie de session : HTTP 302 vers /index.php.
- Destination inconnue et view tableau : HTTP 400.
- Parametre modfunc injecte dans le shell : HTTP 405.
- Session admin actuelle : 12 entrees autorisees observees.
- Neuf contenus natifs observes via frameLocator('#body'), sans Fatal error : Schools, Calendar, MarkingPeriods, Periods, GradeLevels, PortalNotes, PortalPolls, SchoolFields, Configuration.
- Formulaires natifs observes : 2 pour chacun sauf SchoolFields (1). Le comptage comprend les formulaires de navigation et ne prouve pas la sauvegarde.
- Trois pages sensibles : zero iframe, avertissement visible ; aucune operation declenchee.

La premiere lecture par contentDocument n'a pas etabli le chargement ; seule la verification ulterieure par frameLocator est retenue.

## Validation encore requise

Sauvegarde et relecture de donnees de test autorisees ; permissions teacher/parent/student et profils personnalises ; isolation entre ecoles ; erreurs et validations ; calendriers/devoirs ; activation/configuration des plugins ; parcours sensibles apres validation et sauvegarde ; responsive des contenus natifs.

Ces points ne sont pas declares tous testes. Une couverture de neuf ouvertures ne vaut pas validation complete de la branche.

## Essai autorise de sauvegarde et retablissement

Le proprietaire a autorise explicitement un essai reversible sur le nom court de Default School, dans Rosario local sur le port 18082.

- Valeur initiale lue dans #valuesSHORT_NAME : chaine vide.
- Valeur temporaire : SS-TEST-LOCAL, soumise par le bouton natif Enregistrer.
- Rechargement complet du shell : SS-TEST-LOCAL affiche par Rosario. Persistance confirmee.
- Edition native du nom court, retablissement de la chaine vide, Enregistrer, puis rechargement complet.
- Valeur finale relue dans #valuesSHORT_NAME : chaine vide. Retablissement confirme.
- Aucun compte, droit, mot de passe ou autre champ volontairement modifie. Aucune suppression executee.

Ce test valide un seul champ de la fiche ecole avec le compte admin courant. Il ne valide pas tous les champs, les autres formulaires ou les autres profils.

## Responsive natif de la fiche ecole

Trois dimensions de viewport reellement observees : 390, 768 et 1280 pixels, hauteur 900. Aucun debordement horizontal du document externe ou du document natif ; bouton Enregistrer visible aux trois tailles.

| Viewport | Largeur externe client/scroll | Largeur native client/scroll |
| --- | --- | --- |
| 390 | 390 / 375 | 326 / 326 |
| 768 | 768 / 753 | 470 / 470 |
| 1280 | 1280 / 1265 | 946 / 946 |

Cela ne constitue pas une verification visuelle exhaustive de tous les controles ou des huit autres modules. Le viewport temporaire a ete reinitialise et l'onglet QA ferme. Preuve apres retablissement : school-save-restored.jpg dans les outputs de ce chat.

## Manifeste

## Verification complementaire des modules natifs

Le proprietaire a autorise deux essais reversibles supplementaires. GradeLevels : niveau Kindergarten, SHORT_NAME KG -> TST -> KG. Periods : creneau Full Day, SHORT_NAME FD -> SS-TST -> FD. Dans les deux cas, sauvegarde par Enregistrer natif, rechargement complet, attente de la valeur affichee, puis restauration et relecture confirmees. Aucun ajout ni suppression, aucune modification volontaire de date, de compte ou de droit.

Une premiere mesure count() faite immediatement apres navigation retournait zero avant chargement. Elle n'est pas retenue comme preuve ; le test du niveau a ete repete avec attente explicite du texte visible, puis restauration confirmee.

Les neuf modules non sensibles ont ete ouverts aux trois largeurs reelles 390, 768 et 1280 pixels : 27 controles. Contenu natif non vide pour chacun, aucun Fatal error / Uncaught / SQLSTATE dans le texte du contenu, aucun debordement horizontal du document externe ou natif. Ce controle de geometrie ne valide pas les tableaux remplis, modales ouvertes ou parcours complets.

Configuration : six onglets effectivement ouverts sans soumission (RosarioSIS, Ecole, Eleves, Notes, Presence, Cantine). Chacun affiche du contenu et un formulaire ; aucune erreur fatale detectee. Modules et Plugins non ouverts dans ce complement, car la decouverte native peut renommer des repertoires ; aucune activation testee.

Preuves dans les outputs : school-native-checks.json, school-grade-restored.jpg, school-period-restored.jpg.

Etat : acces natif aux neuf modules confirme, sauvegarde/restauration d'un champ dans trois modules confirmee au total. La branche n'est PAS validee integralement. Restent les autres operations, validations, profils, isolation multi-ecole et l'adaptation des champs des previsualisations SchoolSafe. Un lien natif ou une iframe ne raccorde pas les champs HTML des brouillons.

### Fichiers du raccordement

- Nouveau app/modules/codex-ui-v1/rosario-view/school-live.php
- Nouveau app/modules/codex-ui-v1/rosario-view/school-live.css
- Modifie app/modules/codex-ui-v1/rosario-view/index.php : acces Ecole natif, dans l'espace isole.
- Nouveau docs/codex-ui-v1/SCHOOL_NATIVE_CONNECTION_V1.md

Copie locale dans /opt/schoolsafe-view. Source Rosario /var/www/html toujours montee en lecture seule. Aucune modification Cursor, PostgreSQL, authentification, Studio Card, scanner, recuperation ou VPS.
