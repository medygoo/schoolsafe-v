# Parcours scolaires : premier lot de finition visuelle

Reference : `CODEX_SCHOOL_WORKFLOWS_PREVIEW_V1`.

## Perimetre et statut

Ce lot poursuit la finition du contenu interieur. Il ne remplace pas les visuels valides et ne corrige pas le raccordement metier a la place de Cursor.

- 5 presentations reutilisables, disponibles depuis 9 destinations du catalogue.
- Activation reservee a la preview : `schoolWorkflows: true` ET mode demonstration ET destination presente dans les actions de la session.
- Aucun appel API, stockage local, fichier televerse, ecriture serveur, admission, refus ou import reel.
- Le mode integre garde ses listes et ses contrats anterieurs. Les roles de demonstration ne sont pas des permissions IAM.
- L'audit `non-raccordement-v1/` et son ZIP restent des preuves historiques inchangees. Le present lot est posterieur a leurs captures et empreintes.

## Ecrans livres

| Destinations | Presentation | Interactions locales | Limite |
| --- | --- | --- | --- |
| rosario-152, Creer l'eleve | Identite, scolarite, recapitulatif | Champs requis, erreurs liees aux champs, precedent/suivant, relecture | Aucun brouillon metier cree |
| rosario-012 et rosario-155, imports | Source puis relecture | Nom de fichier, contenu colle, extrait brut echappe | Aucun parseur CSV, controle de lignes, doublon, job ou commit |
| rosario-149 et rosario-150 | Revue de dossier accessible depuis la liste conservee | Selection, identite, points de controle fictifs, observation locale | Pas de verification documentaire officielle |
| rosario-151, Accepter ou refuser | Meme revue de dossier, avec indisponibilite de decision explicite | Selection et observation uniquement | Aucune decision simulee |
| rosario-007, Classes | Organisation et liste des eleves | Selection de classe, enseignant, matiere, dossiers exemples | Pas de nouvelle affectation, aucun effectif serveur revendique |
| rosario-008 et rosario-153, familles | Identite et coordonnees | Selection, nom, lien, telephone, e-mail, adresse, validation locale | Parent principal, photos et autorisations inchanges |

Les nouvelles commandes classes/familles/admissions sont disponibles en tete des listes. Le retour restaure la liste existante. Les 5 commandes sensibles de la famille sont conservees sur cette liste et n'ont pas ete reimplementees. Le bouton d'ouverture existant conserve son garde-fou : il ne peut pas effectuer une operation dans cette demonstration.

Les donnees reutilisent les fixtures deja presentes : 24 dossiers exemples et 2 classes exemples. Ce jeu de consultation n'est pas le calcul des 180 eleves de la synthese generale. Les nombres affiches dans la nouvelle vue classe sont nommes « Dossiers de demonstration » et ne sont pas presentes comme un effectif d'inscription reel.

## Fichiers

- `app/modules/codex-ui-v1/school-workflows.js` : presentations et etat temporaire, delegation d'evenements, chargement annule lors de la navigation.
- `app/styles/codex-ui-v1/school-workflows.css` : styles limites aux nouveaux parcours, tokens blanc/bleu existants, disposition selon la largeur du contenu.
- `app/modules/codex-ui-v1/lot2-views.js` : points d'extension et delegation, sans modification des projections DTO.
- `app/modules/codex-ui-v1/unified-interface.js` : option explicite et interception des formulaires locaux.
- `app/modules/codex-ui-v1/demo-data.js` : source de fixtures scolaire, scenarios vide/erreur/refus conserves.
- `app/modules/codex-ui-v1/preview.html` et `preview.js` : chargement et activation dans la preview uniquement.

## Preuves et raccordement reserve a Cursor

Sources consultees, jamais modifiees :

| Presentation | Source locale | Contrat observe | Travail restant pour Cursor |
| --- | --- | --- | --- |
| Creation | `app/modules/core/visual-output-p2a.js:224` | Droit `school.student.create`; `POST /native/students/drafts` a la ligne 264 | Fournir annees/classes autorisees, mapper `reference` vers `matricule`, relire le schema courant, traiter les erreurs serveur et le resultat reel |
| Import | Meme fichier, lignes 271-298 | `POST /native/family/student-imports`, puis `/:jobId/preview` et `/:jobId/commit` | Utiliser le parseur et la previsualisation du moteur existant; afficher erreurs et resultats officiels avant confirmation |
| Revue | Meme fichier, lignes 180-223 | Lecture liste/dossier/completude; message explicite d'absence de route de decision a la ligne 219 | Mapper les points de completude autorises; verifier l'existence d'un parcours de decision dans la version de Cursor; ne pas inventer de route |
| Classes | Meme fichier, lignes 502-568 | `GET /native/pedagogy/classes`, lectures eleves et affectations | Fournir perimetre, pagination et effectifs serveur; ne pas deduire un total global d'une page de dossiers |
| Familles | Meme fichier, lignes 300-408 | Droit `school.guardian.manage`; `PATCH /native/students/:studentId/guardians/:guardianId` a la ligne 354 | Resoudre les identifiants eleve/personne sans confusion; fournir les contacts autorises et les regles serveur; garder les operations protegees existantes |

Le constat concernant la decision d'admission concerne cette source et ce snapshot, pas toutes les capacites de Rosario. Tout besoin de modification du coeur doit etre soumis au proprietaire; ce lot n'en effectue aucune.

L'implementation actuelle ne doit pas etre activee telle quelle sur des donnees reelles : elle est volontairement fermee hors demonstration. Cursor devra adapter la couche de donnees aux DTO autorises et aux ports existants apres validation, puis tester chaque droit et parcours de bout en bout. Le nom, les roles de presentation ou une ligne de catalogue ne donnent jamais une permission implicite.

## UI/UX Pro Max et accessibilite

Skill locale consultee : `C:/Users/PC/.codex/skills/ui-ux-pro-max/SKILL.md`.

Recherches UX appliquees : `multi step form progress` et `error summary validation`. Les recommandations pertinentes sont les reperes d'etape, le retour de validation explicite, les erreurs en ligne et le resume focalisable relie aux champs. Il s'agit d'une base de recommandations, pas d'un service ayant certifie le produit.

Les couleurs reprennent les tokens valides; les icones viennent de Lucide. Les champs ont un libelle visible et une hauteur minimale de 44 px. Les champs passent en une colonne lorsque le contenu disponible fait 600 px ou moins. Les notes et le contenu brut sont echappes; le changement de dossier, de session ou d'ecran supprime le brouillon. Aucun toast ne pretend qu'un enregistrement metier a reussi.

## Verification

Rapport du lot : `school-workflows-tests/results.json`. Captures dans le meme dossier.

Le harness teste les 9 destinations sur 5 largeurs (320, 390, 768, 1280, 1920) et les 2 themes, soit 90 variantes; erreurs de saisie, precedent/suivant, changement de dossier, scenarios vide/erreur/refus, changements de role, texte a 200 %, couleurs forcees et absence d'appel metier. Il verifie aussi que l'option ne charge aucun exemple en mode integre.

Les regressions reexecutees pour cette revision sont listees avec leurs empreintes et dates dans `SCHOOL_WORKFLOWS_MANIFEST.json`. Les autres resultats historiques ne sont pas de nouvelles validations.

Reproduction avec le serveur local deja actif sur 127.0.0.1:5187 :

```powershell
node docs/codex-ui-v1/school-workflows-tests.cjs
node docs/codex-ui-v1/record-presentation-tests.cjs
node docs/codex-ui-v1/lot2-browser-tests.cjs
node docs/codex-ui-v1/package-school-workflows.cjs
```

## Restant pour la finition complete

1. Parcours interieurs de pedagogie, personnel et documents, en conservant les contrats existants.
2. Autres branches du menu : presentation adaptee a chaque fonction, etats vides/erreur/refus et parcours complets.
3. Validation de la coherence des jeux de demonstration entre synthese et consultations.
4. Reprise de l'interface sur une nouvelle copie autorisee apres les corrections de Cursor; test des raccordements reels et permissions sans modifier Rosario.
5. Revue globale sur appareils physiques et lecteur d'ecran, impression officielle et parcours JASPE raccordes. Non executes dans ce lot.

`VISUAL_BATCH=READY_FOR_OWNER_REVIEW`

`DESIGN_COMPLETE=NO`

`REAL_BUSINESS_INTEGRATION=NOT_EXECUTED`

`CURSOR_INTEGRATION=NOT_STARTED`
