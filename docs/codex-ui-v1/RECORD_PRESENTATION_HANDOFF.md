# Fiches et formulaires - premiere passe interieure

Reference : SCHOOLSAFE_CODEX_RECORD_PRESENTATION_V1.
Autorisation : GO apres proposition de commencer par les eleves, classes, personnel, presences, familles et pedagogie. Perimetre Codex isole, integration Cursor non commencee.

## Couverture

Neuf schemas : students, classes, staff, attendance, family, subjects, assignments, grades, lessons. Ils sont utilises par 51 destinations existantes; ce ne sont pas 51 nouveaux ecrans metier raccordes.

- Fiches : identite et etat en tete, regroupement des seuls champs autorises en scolarite/affectation, dates, contacts, documents/suivi et textes longs. Une rubrique vide n'est pas inventee.
- Textes : libelles d'interface et onglets accentues, titres et labels adaptes. Les cles techniques d'onglet et les valeurs serveur ne sont pas traduites ni interpretees.
- Listes : effacement des filtres actifs; recherche, classe, etat et page restent conserves pendant l'aller-retour vers un dossier.
- Pedagogie : trois brouillons locaux (devoir, evaluation, preparation), memes noms de champs, zones multilignes pour consigne, objectifs, deroulement et observation.
- Validation locale : erreurs a cote des champs et resume focalisable avec acces direct au champ; les erreurs deja signalees disparaissent quand le champ est corrige, sans deplacer le curseur. Champs remplis conserves, y compris au changement de theme. Fermer ou changer de session efface le brouillon.
- Aucun stockage local persistant, publication, enregistrement ou calcul de note ajoute. Le message de verification ne signifie pas validation pedagogique ou sauvegarde.

## UI/UX Pro Max

Skill local relu et recherches executees avec Python -B :

```text
search.py "error summary validation" --domain ux -n 2
search.py "form labels" --domain ux -n 1
```

Resultats pertinents : Focusable Error Summary, Error Messages, Form Labels. Application : resume + erreurs locales, labels associes, focus au submit invalide et non a chaque frappe. Les regles natives de pro-rules.md sont adaptees au web. Couleurs SchoolSafe, icones Lucide existantes, tailles lisibles et sections sans cartes imbriquees conservees.

## Fichiers et chargement

- app/modules/codex-ui-v1/record-presentation.js : composant de presentation pur, limite aux neuf schemas; aucune lecture reseau ni acces a la session.
- app/styles/codex-ui-v1/record-presentation.css : presentation des fiches et brouillons.
- lot2-views.js : appelle le composant apres validateData; gestion du brouillon en memoire et effacement des filtres.
- preview.html : chargement des deux nouveaux fichiers.

Ordre scripts : codex-ui-v1.js, catalog.js, read-adapter.js, screen-specs.js, record-presentation.js, lot2-views.js, unified-interface.js; Lucide existant avant montage. Ajouter record-presentation.css apres overview-composition.css. Sans record-presentation.js, les details utilisent le rendu generique existant.

## Frontieres de donnees

Le composant detail recoit uniquement v.data.fields, projete par le schema et la liste blanche de l'onglet courant. Il ne doit jamais recevoir une ligne de liste brute ni reconstituer le nom d'un onglet depuis un autre onglet. Les champs manquants ne sont pas deduits. Les etats sont affiches tels que recus, sans couleur donnant une decision d'autorisation. Les valeurs sont echappees; zero reste zero; les retours a la ligne sont conserves.

Les 211 destinations, autorisations, six lectures et contrats sont inchanges. Aucun nouveau champ expose. En particulier, la presentation ne corrige pas les limites de projection deja presentes dans certains onglets du personnel : un champ absent du contrat courant reste absent.

Operations famille, cartes, scanner, remise et controle financier : aucun formulaire metier ni moteur change. Les autres schemas conservent leurs details generiques. JASPE et Vue generale sont conserves. Les 87 ouvertures historiques ne sont pas reconstruites par cette passe.

## Tests

record-presentation-tests.cjs couvre les neuf schemas et chacun de leurs onglets, la correspondance exacte des champs/identifiants, 90 variantes responsive de fiches (9 x 5 largeurs x 2 themes), les trois brouillons, la conservation de la saisie, les erreurs et le clavier. Les textes malveillants, valeurs zero, absence de fallback sur la liste et refus d'un onglet sont testes avec des adaptateurs substitues.

Les captures sont dans record-tests/. Les scripts de regression LOT 2 sont adaptes uniquement pour saisir aussi les textarea et attendre le libelle visible Scolarite accentue; la cle du contrat reste Scolarite.

```powershell
node docs/codex-ui-v1/record-presentation-tests.cjs
node docs/codex-ui-v1/lot2-browser-tests.cjs
node docs/codex-ui-v1/ui-ux-pro-max-tests.cjs
node docs/codex-ui-v1/package-lot2.cjs
```

Les resultats et empreintes courantes sont dans LOT2_MANIFEST.json. Aucun test reel de backend, appareil physique ou lecteur d'ecran; aucune certification d'accessibilite exhaustive. Cette livraison reste une proposition visuelle a examiner avant extension aux autres familles.
