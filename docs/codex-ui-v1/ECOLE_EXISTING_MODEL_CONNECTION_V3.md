# Ecole : reprise du modele existant, V3

Date : 2026-10-11. Statut : implementation partielle, pas de cloture de branche.

## Travail effectivement execute

- Informations de l'ecole : reutilisation des trois regroupements de school-form.html. Les onze champs standards utilisent les noeuds reels du formulaire Rosario, pas une copie des valeurs.
- Periodes scolaires : reutilisation des regroupements identite, dates, notation de marking-periods.html. Les liens natifs annee / semestre / trimestre restent en place. L'identifiant provient de l'action native du formulaire.
- Listes : regroupement des tables natives dans des zones identifiees, avec defilement horizontal local et focus clavier. Aucun remplacement des donnees par des exemples.
- Navigation AJAX : observation des remplacements du contenu pour reappliquer la presentation. Protection contre les insertions multiples et contre les reponses de modele devenues obsoletes.
- Correction d'un decalage effectivement constate : le plugin de menu fixe ajoutait un espace reserve et un positionnement inline qui repoussaient le contenu. Correction CSS uniquement, dans la couche isolee.

Les formulaires, methodes POST, actions, jetons, noms, valeurs, contraintes, controles supplementaires et gestionnaires natifs restent dans leur document Rosario. Les templates d'edition native sont deplaces avec leurs conteneurs. Les champs personnalises et boutons non repositionnes restent presents dans le formulaire original. Aucun SQL, endpoint de sauvegarde, moteur d'authentification ou validation metier alternatif n'a ete ajoute.

La technique conserve un iframe pour le document et les scripts natifs. Elle ne constitue PAS un remplacement integral de tous les anciens ecrans HTML autonomes. Ceux-ci sont preserves et restent des previsualisations.

## Etat par fonction

| Fonction | Etat de cette livraison | Reste |
| --- | --- | --- |
| Informations ecole | 11 champs regroupes dans le modele existant, formulaire natif conserve | Tests sauvegarde apres nouvelle presentation, lecture seule et champs personnalises |
| Periodes scolaires | Regroupements du modele existant, dates natives et navigation conservees | Tests notation, creation, suppression et profils ; aucune operation sensible executee |
| Niveaux | Liste native regroupable, vrai formulaire conserve | Reprise specifique des groupes du formulaire grade-levels.html |
| Creneaux | Liste native regroupable, vrai formulaire conserve | Reprise specifique de l'editeur SchoolSafe |
| Calendrier | Acces natif existant conserve | Reprise specifique des editeurs de calendrier et evenement |
| Annonces | Acces natif et listes conserves | Reprise specifique des editeurs et fenetres |
| Sondages | Acces natif et listes conserves | Reprise specifique des questions, publication et resultats |
| Champs ecole | Acces natif et listes conserves | Reprise specifique ; changements de structure hors executions de ce lot |
| Configuration | Acces natif conserve | Reprise specifique de chaque onglet ; aucun parametre modifie |
| Copier ecole | Lien natif avec avertissement conserve | Presentation specifique et tests differes ; autorisation d'expansion d'acces non accordee |
| Passage annee | Lien natif avec avertissement conserve | Presentation specifique et tests sensibles differes |
| Journal | Lien natif avec avertissement conserve | Eviter ouverture automatique : possible purge native des anciennes entrees |

## Controles de cette session

- Syntaxe JavaScript des deux nouveaux adaptateurs : reussie.
- 16 tests existants de filtrage de la presentation native : reussis. Ils ne testent PAS les nouveaux deplacements de controles.
- Inspection navigateur : champ TITLE de l'ecole editable apres deplacement ; action POST native conservee, maxlength=100 conserve.
- Inspection navigateur : regroupements des periodes visibles, selection native de Semester 1 puis Semester 2, reapparition des regroupements.
- Defaut visuel du menu fixe observe puis correction visible apres actualisation des ressources versionnees.
- Aucune sauvegarde metier, suppression, copie, vote, modification de compte ou droit dans cette session.
- Tests complets responsive et sauvegarde apres V3 : NON EXECUTES, selon le report demande par le proprietaire. Les preuves des versions precedentes ne valident pas cette version.

## Manifeste du delta

Sous app/modules/codex-ui-v1/rosario-view/ :

1. school-existing-form.js : nouveau, reprise des groupes des modeles existants.
2. school-existing-form.css : nouveau, presentation des groupes et listes.
3. school-existing-lists.js : nouveau, presentation des vraies listes natives.
4. school-live.php : modifie, chargement des adaptateurs.
5. school-native-presentation.css : modifie, correction du menu fixe.
6. school-native-presentation.js : modifie, version du stylesheet.
7. test-school-native-presentation.cjs : modifie, version attendue du stylesheet.

Dependances existantes a conserver : school-form.html, marking-periods.html, school-catalog.php, school-shell-navigation.js, account-form.css, school-workspace.css, school-live.css et ressources de la presentation native. Le paquet delta ne remplace pas ces dependances.

## Suite necessaire, par ordre

1. Reprendre les groupes visuels des niveaux et creneaux sans perdre l'edition native multi-lignes.
2. Reprendre calendriers, annonces, sondages et leurs fenetres natives.
3. Reprendre champs et onglets de configuration, sans executer de modification de schema ou de securite.
4. Finaliser la presentation des trois outils sensibles sans les executer automatiquement.
5. Campagne de validation complete locale, puis decision du proprietaire. Aucun deploiement autorise par cette livraison.

Rosario, Studio Cartes, scan, recuperation des enfants, JASPE, dossier Cursor et VPS : non modifies dans cette session.
