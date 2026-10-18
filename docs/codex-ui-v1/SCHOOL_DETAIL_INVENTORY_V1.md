# Repertoire des details Ecole

Date : 2026-10-11. Ce rapport complete SCHOOL_BRANCH_VISUAL_LOT_V1.md et ne le transforme pas en validation metier.

## Inventaire par fonction

Sources sous C:/Users/PC/Documents/rosario/ en lecture seule. Les permissions effectives et les hooks doivent etre resolves par Rosario, pas par les visuels statiques.

| Fonction | Sous-fonctions reperees | Etat du visuel | Suite necessaire |
| --- | --- | --- | --- |
| Informations ecole | Identite, adresse, direction, echelle, champs personnalises | Formulaire conserve | Valeurs et champs dynamiques a charger |
| Calendriers | Mois precedent/suivant, calendrier courant, jours/minutes, blocs, rotation, copie/creation, defaut, evenement et repetition, devoirs lies, edition/suppression | Mois navigable et selection de jour ajoutes, formulaires de base conserves, details de jour en attente | Evenements/devoirs, calendrier courant et droits non charges ; mutations non raccordees |
| Periodes scolaires | FY/SEM/QTR/PRO, parents, dates, plages de saisie, notes/commentaires, creation/edition/suppression et avertissements | Formulaire conserve | Arbre reel et validations natives a adapter |
| Creneaux | Nom, nom court, ordre, duree, bloc, cours associes, ajout/edition/suppression | Formulaire et tableau vide presents | Liste editable et relations reelles non chargees |
| Niveaux | Nom, abreviation, ordre, niveau suivant, protection des niveaux utilises | Formulaire conserve | Liste editable et dependances reelles non chargees |
| Annonces | Contenu Markdown, periode de publication, profils, fichier/lien, edition et suppression | Champs presents et apercu de publication ajoute | Markdown assaini natif, fichiers et profils non raccordes |
| Sondages | Questions multiples, types choix unique/multiple, options, dates, profils, affichage et comptage des votes, exclusion des votants repetes | Ajout local de question conserve et apercu de publication ajoute | Votes et exclusions restent natifs ; aucun resultat fictif |
| Champs ecole | Types, options conditionnelles, valeur par defaut, obligatoire, ordre, multilingue, limitations de changement de type, ajout/suppression de colonne | Selecteur 10 types et apercu conditionnel ajoutes | Pas de creation de colonne ; traductions et restrictions des champs existants non chargees |
| Configuration | Application, inscription, securite, ecole, eleves, notes, presences, restauration, extensions et hooks | 9 onglets accessibles ajoutes, controles sensibles desactives | Valeurs, droits par ecole, themes et hooks non charges |
| Modules/plugins | Liste active/inactive, README, activer/desactiver, supprimer, importer ZIP, configuration plugin, statistiques installation | Tables et import desactive presents dans Extensions | Liste et actions reelles non chargees ; aucune installation executee |
| Copier ecole | Titre, 8 familles, configuration forcee, Prompt, creation et changement de contexte | Apercu d'operation ajoute | Aucune copie ; dependances et confirmation natives requises |
| Passage annee | Annee par defaut, 12 familles, familles conditionnelles, dependances, remplacement, sauvegarde conditionnelle et mise a jour DefaultSyear | Apercu d'operation ajoute | Aucune reprise ; dependances et sauvegarde non verifiees |
| Journal | Plage de dates, statut, profil, lien utilisateur/eleve, IP, navigateur, pagination, export, purge | Tableau vide et apercu de suppression presents | Donnees non chargees ; purge auto native a surveiller |

La ligne Modules/plugins detaille Configuration : il reste 12 entrees de menu, pas 13 fonctions principales.

## Preuves supplementaires

- modules/School_Setup/includes/CalendarDay.inc.php:132 : minutes et jour complet (valeur native 999), min 1/max 998. :218 blocs depuis school_periods et contexte ecole/annee. :293 evenements. :349 devoirs. :457 rotation.
- ProgramFunctions/Fields.fnc.php:315 GetFieldsForm ; types natifs : select/autos/exports/multiple/text/textarea/radio/numeric/date/files. Les options concernent les quatre premiers. Les types des champs existants sont restreints ou figes selon leur type.
- modules/School_Setup/includes/Modules.inc.php:322 liste, scan repertoires, README et activation ; import ZIP conditionne a ZipArchive, permissions d'ecriture et ROSARIO_DISABLE_ADDON_UPLOAD. La consultation peut renommer certains repertoires -master/-main si accessibles en ecriture : aucune ouverture native faite pour ce test.
- modules/School_Setup/includes/Plugins.inc.php:311 liste/configuration, meme comportement de scan/import ; configuration deleguee au plugin, pas de schema universel invente.

## Ajouts et limites

- school-details-model.js : donnees de calendrier generees avec Date JavaScript, aucune regle de presence calculee ; dictionnaire des types natifs.
- school-details.js : enrichissements de presentation, dates et apercu de champ locaux, onglets clavier, fenetres d'apercu sans confirmation metier.
- test-school-details.cjs : 9 assertions pour 42 dates distinctes, mois bissextile, transition d'annee et types.
- school-workspace.html/css : scripts, lien d'evitement et styles responsive.

Les saisies ne sont ni enregistrees ni conservees au changement de fonction. Les jours du calendrier ne portent aucune information de presence. Le mois affiche est celui de la machine, pas l'annee scolaire active. Les valeurs par defaut et contraintes natives ne sont pas toutes reproduites par l'apercu de type. Les fichiers restent desactives.

Le travail porte sur les details visuels. Les sous-parcours listes comme non charges/non raccordes ne sont pas termines ni operationnels ; pas de changement d'authentification, schema, moteur, scan, recuperation ou Studio Card.

## Tests observes

- 9 assertions Node : reussies ; syntaxe school-details.js : valide.
- Calendrier : passage octobre vers novembre 2026, selection 2026-11-10 et focus conserve sur la date.
- Champ multiple : 2 options saisies, select multiple rendu.
- Configuration : 9 onglets, une seule section visible, deux tables Extensions ; Home revient a Application.

- Fenetre de copie : seul bouton Fermer ; Escape ferme et rend le focus au bouton d'origine.
- 12 controles responsive : calendrier/champs/configuration/copie a 390, 768 et 1280 px ; largeur reelle verifiee, aucun debordement de page ni champ sans etiquette.
- Sous-menu repliable ajoute sur mobile apres observation de sa hauteur ; affiche en permanence sur ordinateur. Lien d'evitement dirige le focus au contenu sans changer la fonction.

Ces tests ne prouvent ni permissions natives ni operations metier.
