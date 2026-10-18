# SchoolSafe : cloture de la couverture visuelle V1

Reference : `CODEX_SCHOOLSAFE_VISUAL_COVERAGE_V1`.

## Ce qui est livre

La finition couvre les destinations du catalogue actuellement audite, dans la preview Codex uniquement. Elle ne pretend pas couvrir des fonctions Rosario absentes de ce catalogue ni terminer l'integration metier. L'organisation a deux niveaux, les couleurs de branche, les themes blanc/bleu, les tableaux de bord et JASPE sont conserves.

- Les 85 pages d'ouverture historiques restantes ont une presentation adaptee, apres les deux ouvertures traitees dans le lot scolaire precedent.
- Les deux destinations de bulletins ont un etat d'indisponibilite documentaire explicite. Aucun bulletin n'est fabrique.
- 24 destinations deja specialisees disposent d'un espace supplementaire de consultation ou preparation : documents, personnel, presences RH, annees, cycles, matieres, affectations et inscriptions.
- Cette passe ajoute donc 111 destinations presentees par 53 definitions reutilisables et 11 dispositions : registre, agenda, dossiers, rapports, documents, rubriques, messages, historique, equipements, configuration, galerie.
- 20 types de brouillon local permettent une saisie, une validation de forme, une relecture, une modification et un abandon. Ils n'enregistrent rien dans la liste ni dans une base.
- Les fiches documents, organisation scolaire, alertes, indicateurs et acces utilisent maintenant la presentation detaillee commune. Leur projection de champs autorises reste inchangee.

La matrice `FINAL_VISUAL_COVERAGE.json` / `.csv` / `.md` contient les 211 destinations, leurs roles de presentation, la permission deja documentee, le type de presentation, la source candidate et le niveau de preuve. Les roles du catalogue ne remplacent jamais IAM.

## Usage de la preview

Adresse : http://127.0.0.1:5187/modules/codex-ui-v1/preview.html

Les anciennes pages d'ouverture affichent directement leur nouveau contenu. Dans les listes deja existantes, utiliser « Ouvrir l'espace de preparation » ou « Ouvrir l'espace de consultation » pour examiner le complement. Le retour conserve la liste precedente. Le lot scolaire precedent garde ses commandes propres.

Tous les exemples sont fictifs. Les filtres et tris portent uniquement sur les exemples charges. Les brouillons sont temporaires et effaces en quittant leur espace, en actualisant ou en changeant de session. Aucun televersement, courriel, achat, paiement, autorisation, signature ou publication n'est effectue.

## Fichiers et montage

Nouveaux fichiers applicatifs :

- `app/modules/codex-ui-v1/feature-catalog.js` : definitions de presentation, champs, exemples et association explicite aux destinations.
- `app/modules/codex-ui-v1/feature-screens.js` : rendu, filtrage, tri, detail, formulaires, chargement, erreur/refus/vide et annulation des reponses tardives.
- `app/styles/codex-ui-v1/feature-screens.css` : presentation adaptee au contenu disponible, sans changement de theme global.

Points d'extension confines aux fichiers Codex existants : `lot2-views.js`, `unified-interface.js`, `demo-data.js`, `record-presentation.js`, `preview.html`, `preview.js`.

Ordre : charger `feature-catalog.js`, puis `feature-screens.js`, avant `lot2-views.js`. Charger la nouvelle feuille apres les autres feuilles Codex. La preview active explicitement `featureScreens: true`. Le module exige aussi le mode demonstration et une destination exposee par la session. Il ne s'active pas dans une session reelle, meme si l'option est presente.

Ne pas copier aveuglement cette option dans l'application integree. Les exemples declares dans le catalogue sont une proposition visuelle, **pas des schemas API, des permissions ou des DTO officiels**. Les chemins de sources servent de points de comparaison; ils ne prouvent pas l'existence d'un endpoint pour chaque champ.

Les images de la galerie sont uniquement le logo SchoolSafe et JASPE deja presents. Les fichiers graphiques, fontes et Lucide du snapshot sont des prerequis conserves; aucune photographie d'enfant ajoutee.

## Paquet Cursor

`SCHOOLSAFE_VISUAL_V1_CURSOR_PACKAGE.zip` contient les deux repertoires applicatifs Codex, les rapports, les scripts de verification et les captures des six suites reexecutees. Il ne contient aucun fichier applicatif partage ni aucune modification Rosario. Les ressources graphiques et bibliotheques du snapshot restent des prerequis externes au paquet.

`FINAL_VISUAL_PACKAGE.json` donne l'empreinte SHA-256 du ZIP, son inventaire et les empreintes de ses entrees verifiees apres creation. Commencer par ce rapport de cloture et `FINAL_VISUAL_MANIFEST.json`. Les documents LOT 0/1/2 inclus et l'archive de diagnostic sont historiques : leurs anciens chiffres et etats ne remplacent pas la nouvelle matrice de couverture. Ne pas relancer leurs scripts de generation pour actualiser les archives.

Les chemins Playwright des scripts de tests sont ceux de ce poste et seront a adapter sur une autre machine. Le paquet n'est ni un installateur, ni un patch a appliquer automatiquement au projet principal.

## Raccordements pour Cursor

1. Recevoir une version a jour autorisee de la copie apres les corrections de Cursor, sans remplacer les fichiers partages du projet principal par ceux de cette preview.
2. Revalider chaque permission de lecture, preparation et ecriture. Une permission de consultation n'autorise pas une operation. Les comptes, droits IAM, licence, photographies, preuves de remise et frais restent controles par leurs modules existants.
3. Comparer les champs proposes avec les contrats reels. Remplacer les fixtures par des DTO explicitement autorises et projetes. Conserver les identifiants canoniques, l'isolation ecole, les portees et les controles de session.
4. Raccorder les actions aux moteurs existants; afficher les erreurs et resultats reels. Aucune reponse locale « relecture du brouillon » ne vaut enregistrement, envoi ou validation metier.
5. Conserver la pagination serveur et les totaux officiels : un nombre d'exemples ou de lignes chargees n'est pas un effectif total.
6. Faire valider toute proposition de modification du coeur Rosario par le proprietaire. Aucune modification du coeur n'est necessaire pour examiner les presentes interfaces.

Sources de comparaison :

| Domaine | Sources examinees ou referencees dans le catalogue | Limite |
| --- | --- | --- |
| Pedagogie | `server/src/pedagogynative/routes.ts`, sorties visuelles existantes | Emplois du temps, accompagnement et examens proposes visuellement; contrats individuels a verifier |
| RH | `app/modules/hr/hr-demo.js` | Les surfaces source comportent deja des demonstrations et fonctionnalites futures; aucune paie native supposee |
| Comptabilite | `app/modules/accounting/accounting-treasury-demo.js` | Aucun total multidevise, ecriture manuelle, norme comptable ou bilan invente |
| Stock | `app/modules/inventory/inventory-demo.js` | Catalogue, mouvements et preparation existants consultes; aucune mutation de stock |
| Communication | `app/modules/communication/communication-demo.js` | Les champs de brouillons sont inspires de l'existant; aucun envoi ou publication |
| Administration | `app/modules/core/visual-output-closure-a.js` | Aucun changement IAM, licence, langue globale ou fonctionnement hors ligne |
| Vie scolaire | `app/modules/core/visual-output-p1.js` | Aucune information medicale supposee; absence de donnee distincte d'une absence d'allergie |
| Documents | `app/modules/document-center/document-center.js`, `server/src/documents/routes.ts` | Aucun document officiel ou QR reconstitue |
| Rapports | `app/modules/core/visual-wiring.js` | Source de navigation, pas preuve d'un contrat de donnees uniforme |

## Verification et limites de preuve

Les resultats exacts et empreintes sont dans `FINAL_VISUAL_MANIFEST.json`, avec la date de chaque suite reexecutee. Les captures de cette passe sont dans `feature-tests/`. Le script `feature-screens-tests.cjs` controle les 111 destinations, 53 presentations sur 5 largeurs et 2 themes (530 variantes), les 20 formulaires, les erreurs et etats vides/refuses, les 15 postes, le texte agrandi, la navigation et le refus des fixtures hors demonstration.

Le test de fiches a ete etendu de 9 a 14 schemas, soit 140 variantes de detail. Il compare tous les onglets aux champs projetes attendus, conserve les identifiants et controle le refus d'acces sans fuite entre onglets. Relecture visuelle des captures : agenda blanc 1280 px, documents blancs 1280 px, messages bleus 390 px et galerie bleue 1280 px. Aucun chevauchement incoherent constate dans cet echantillon; les tableaux larges gardent leur defilement interne.

UI/UX Pro Max a ete consulte localement, en conservant le systeme visuel deja valide. Recherche ciblee : `data table filtering empty`, recommandations Table Handling et Empty States appliquees. La recommandation generique de modifications en masse n'est pas appliquee : elle necessiterait une autorisation et un raccordement metier distincts.

Les tests de largeur et les captures ne constituent pas une certification WCAG. Lecteurs d'ecran, appareils physiques, impression officielle et integration authentifiee non testes dans cette passe. Les suites anterieures qui ne figurent pas dans la liste des executions du manifeste restent des preuves historiques.

Le rapport de diagnostic `non-raccordement-v1/` et son ZIP ne sont pas regeneres : leurs captures de pages generiques decrivent la revision precedente. Le manifeste du lot scolaire precedent est aussi conserve comme historique. Le nouveau manifeste de cloture fait reference a l'etat actuel.

## Points encore ouverts

| Priorite | Point | Responsable / condition |
| --- | --- | --- |
| P0 | Raccordements reels et permissions par action | Cursor, sur les contrats existants |
| P1 | Decision d'admission et bulletins indisponibles dans les sources examinees | Verifier la version actuelle; validation proprietaire avant toute modification du coeur |
| P1 | Paie, comptabilite et autres sources encore demonstratives | Ne pas declarer un module operationnel sur la seule base de sa presentation |
| P1 | Controle humain des ecrans et contenus proposes | Proprietaire avant integration |
| P2 | Palmares : periode, criteres, resultats publies et perimetre des 10 meilleurs | Emplacement conserve; aucun classement calcule arbitrairement |
| P2 | Activites et calendrier du tableau de bord | Zones preservees en attente d'une source reelle, sans activite inventee |
| P2 | Coherence des jeux d'exemples (180 eleves de synthese, 24 dossiers de consultation) | Jeux distincts deja signales, a unifier avec les donnees autorisees |
| P2 | Traduction anglaise complete et mode hors ligne reel | Non revendiques comme livres |
| P2 | Recette sur appareils physiques et lecteur d'ecran | A realiser apres validation et integration |

`VISUAL_CATALOG_COVERAGE=READY_FOR_OWNER_REVIEW`

`PRODUCTION_DESIGN_ACCEPTANCE=PENDING`

`ROSARIO_NEW_CHANGES=NONE`

`REAL_BUSINESS_INTEGRATION=NOT_EXECUTED`

`CURSOR_INTEGRATION=NOT_STARTED`

Aucun commit, push, merge ou deploiement.
