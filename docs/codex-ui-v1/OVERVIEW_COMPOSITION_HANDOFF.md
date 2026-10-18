# Vue generale - composition de reference, etape 2

Reference : SCHOOLSAFE_CODEX_OVERVIEW_COMPOSITION_V1.
Autorisation : GO du proprietaire apres proposition de la deuxieme etape.
Perimetre : composants Codex isoles uniquement. Integration Cursor non commencee.

## Choix visuel

La reference du proprietaire, Tableau de bord scolaire SchoolSafe.png, guide la composition : indicateurs en haut, synthese de presence et repartition, alertes laterales, acces rapides, details et suivi. Les ressources de marque, les couleurs des 13 branches, la navigation a deux niveaux et les interfaces deja creees sont conservees.

UI/UX Pro Max a ete consulte :

```text
python -B C:/Users/PC/.codex/skills/ui-ux-pro-max/scripts/search.py "administrative data dashboard" --domain style -n 1
python -B C:/Users/PC/.codex/skills/ui-ux-pro-max/scripts/search.py "part whole comparison" --domain chart -n 2
```

Resultats retenus : Data-Dense Dashboard et Part-to-Whole. Le second recommande notamment une alternative lisible au camembert : des barres accompagnees de valeurs textuelles. Les regles natives de pro-rules.md sont interpretees pour le web; aucune nouvelle bibliotheque n'est necessaire pour ces barres CSS statiques.

## Composition livree

- Quatre indicateurs compacts, avec accent distinct et donnees secondaires existantes.
- Presence journaliere : taux arrondi, nombre present/inscrit, repartition a l'heure/retard/absent/non verifie.
- Repartition par cycle : effectifs, parts arrondies et nombre de classes. Les arrondis individuels peuvent ne pas totaliser 100 %.
- Alertes visibles a droite sur les grands espaces (conteneur > 900 px), puis dessous sur les espaces etroits. Les dossiers existants sont regroupes dans un volet ouvrable qui conserve son etat au rafraichissement.
- Une bande d'acces rapides utilisant uniquement les destinations autorisees existantes.
- Detail eleves/classes conserve : effectifs, filles, garcons, presences et totaux; enseignement journalier et heures d'arrivee conserves. Palmares top 10 toujours reserve, sans classement.
- Sur tablette et mobile : onglets Eleves / Enseignants / Palmares conserves; cycles depliables sur telephone.
- Zones Dernieres activites et Calendrier scolaire : explicitement Non raccorde, sans evenement invente ni bouton inactif simulant une action.

La synthese est un instantane du jour fourni, pas une courbe horaire, un historique d'activites ou une prevision. Les metriques de demonstration restent 180 inscrits / 89 filles / 91 garcons / 140 presents, 7 enseignants presents sur 8. Les cycles existants sont Maternelle 40, Primaire 80 et Secondaire 60; les chiffres differents de l'image ne remplacent pas les fixtures validees.

## Code et raccordement futur

- lot2-views.js : schoolOverviewHtml utilise seulement schoolOverview.classes, schoolOverview.teachers, items et alerts deja projetes par validateDashboard. Aucun changement de DTO, route, droit, lecture ou permission.
- overview-composition.css : composition limitee a la Vue generale. A charger apres branch-presentation.css. Aucun remplacement des feuilles precedentes.
- preview.html : charge la nouvelle feuille. demo-data.js et tous les composants metier restent inchanges.
- Cursor conserve les ports existants loadDashboard/authorize. Cette passe n'en cree pas et ne les raccorde pas.
- Activites et calendrier : contrats de lecture, perimetre temporel/fuseau, permissions et destinations a faire valider avant implementation. Les zones restent indisponibles tant qu'aucun contrat n'est defini.
- Palmares : periode, criteres, eligibilite de publication et resultats officiels a valider; aucun classement calcule localement.

## Verification

overview-composition-tests.cjs : 28 variantes (administrateur/direction x blanc/bleu x 7 dimensions : 320x800, 390x844, 768x1024, 844x390, 1280x900, 1440x1000, 1920x1080).
Controles : rail lateral, boutons sans texte coupe, icones resolues, totaux derives, maintien du dossier exact, disclosure lors du rafraichissement, vide/erreur/refus, effectif nul, retard/non-verifie, libelles echappes, texte 200 %, couleurs forcees et mouvements reduits. Les tests rendent des DTO substitues uniquement.

Captures : overview-composition-tests/overview-*.png et viewport-*.png. Les captures viewport blanc/bleu 1440 et mobile sont examinees visuellement; les anciens parcours sont conserves dans overview-detail-tests.cjs et lot2-browser-tests.cjs. Le controle de ce dernier a ete adapte au deplacement intentionnel des listes vers le rail, sans retirer les assertions de compte ni de navigation.

Le manifeste LOT2_MANIFEST.json repertorie les fichiers et preuves; TREE.sha256 reste la reference des 1017 fichiers originaux. Aucun test de production, compte authentifie, appareil physique ou lecteur d'ecran effectue. Ce lot ne certifie pas une conformite WCAG exhaustive.

## Reproduction

```powershell
node docs/codex-ui-v1/overview-composition-tests.cjs
node docs/codex-ui-v1/overview-detail-tests.cjs
node docs/codex-ui-v1/lot2-browser-tests.cjs
node docs/codex-ui-v1/ui-ux-pro-max-tests.cjs
node docs/codex-ui-v1/package-lot2.cjs
```

Preview locale : http://127.0.0.1:5187/modules/codex-ui-v1/preview.html
Le serveur existant sert uniquement les fichiers statiques en boucle locale.
