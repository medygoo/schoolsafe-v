# Remise a Cursor : SchoolSafe visuel V1

Reference : `CODEX_TO_CURSOR_VISUAL_HANDOFF_V1`.
Protocole : `SCHOOLSAFE_CODEX_CURSOR_CONTROLLED_INTEGRATION_V1`.

## Mission immediate

Le proprietaire demande la remise du travail visuel Codex a Cursor. Cette remise autorise la reception et l'examen du lot; elle ne constitue pas une validation de production ni une autorisation d'integration automatique.

Cursor reste l'unique integrateur. Commencer par un audit en lecture seule, puis presenter au proprietaire un nouveau `CURSOR_CODEX_PRE_INTEGRATION_AUDIT.md` fonde sur la version actuelle de son projet. Attendre sa validation explicite avant integration. Aucun commit, push, merge ni deploiement dans cette phase de reception.

## Emplacement exact

Dossier de livraison local, deja accessible sur cette machine :

```text
C:\Users\PC\SchoolSafeCodex\SCHOOLSAFE_CODEX_LOCAL_SNAPSHOT_V1\workspace\docs\codex-ui-v1
```

Archive a examiner :

```text
C:\Users\PC\SchoolSafeCodex\SCHOOLSAFE_CODEX_LOCAL_SNAPSHOT_V1\workspace\docs\codex-ui-v1\SCHOOLSAFE_VISUAL_V1_CURSOR_PACKAGE.zip
```

SHA-256 attendu :

```text
63380a6facd639cba9b3825c9ff500dca1796c096a52ed756deddb6f44ad86bc
```

Taille : 13 351 765 octets. Nombre d'entrees : 234.
Ne pas utiliser l'archive `.initial.zip`, conservee uniquement comme historique technique.

Le fichier `FINAL_VISUAL_PACKAGE.json` fournit l'inventaire et les empreintes des 234 entrees. `CURSOR_RECEPTION_VERIFICATION.json` consigne le controle de remise. La presente consigne et le controle de remise sont des documents complementaires : ils ne modifient pas l'archive visuelle deja figee.

## Ordre de lecture

Tous les documents suivants sont dans le dossier de livraison, sauf mention contraire.

1. `FINAL_VISUAL_HANDOFF.md` : portee, limites, montage et raccordements attendus.
2. `FINAL_VISUAL_MANIFEST.json` et `FINAL_VISUAL_PACKAGE.json` : fichiers, provenance, empreintes et dates de tests.
3. `FINAL_VISUAL_COVERAGE.json` (ou `.md` / `.csv`) : 211 destinations du catalogue, presentation, roles indicatifs, permissions documentees, contrats candidats et statut non valide en reel.
4. `LOT1_BUTTON_SCREEN_MAPPING.json`, `LOT2_SCREEN_SPECS.json`, `LOT2_BUTTON_SCREEN_FUNCTIONAL_MATRIX.json` : definitions de navigation et contrats proposes. Les documents des anciens lots sont historiques.
5. `LOT0_BUTTONS.json`, `LOT0_API_RPC.json`, `LOT0_RACCORDEMENTS.md`, `LOT0_FONCTIONS_SANS_ECRAN.md` : indices sources, fonctions sans interface et correspondances a revalider. Les 517 occurrences/modeles de boutons du LOT 0 ne sont pas les 211 destinations du catalogue visuel.
6. `non-raccordement-v1/REPORT.md`, `non-raccordement-v1/CURSOR_HANDOFF.md`, `non-raccordement-v1/findings.json` : diagnostic historique des interactions non raccordees. Ces fichiers sont aussi dans `SCHOOLSAFE_NON_RACCORDEMENT_CURSOR_V1.zip`, inclus dans le paquet visuel. Certaines anciennes captures precedent les nouvelles presentations.
7. Les resultats JSON et captures dans `feature-tests/`, `school-workflows-tests/`, `record-tests/`, `lot2-tests/`, `pro-max-tests/` et `overview-composition-tests/`.

Attention : le fichier deja livre `CURSOR_CODEX_PRE_INTEGRATION_AUDIT.md` est le rapport statique historique du LOT 0. Il n'est PAS l'audit de la version actuelle de Cursor et ne vaut pas autorisation d'integration. Le conserver comme preuve historique.

## Ce que contient le lot

- Code visuel isole : `app/modules/codex-ui-v1/` et `app/styles/codex-ui-v1/`.
- Navigation a deux niveaux, themes blanc/bleu, accents de branche, tableaux de bord et presentation JASPE conserves.
- 211 destinations du catalogue : 13 branches et 15 postes de demonstration. Ce chiffre ne certifie pas toutes les fonctions possibles de Rosario.
- Derniere passe : 85 anciennes pages generiques remplacees, deux etats explicites de bulletins indisponibles, 24 complements d'ecrans existants, 53 definitions reutilisables et 20 types de brouillon local.
- Six suites de verification visuelle reussies. La derniere passe comprend 530 variantes responsive; les fiches detaillees comprennent 140 variantes. Voir les rapports pour les autres perimetres, sans additionner des categories qui se recouvrent.
- Aucune integration authentifiee, aucun test PostgreSQL reel, aucune certification WCAG. Les tests utilisaient des donnees fictives et, lorsque necessaire, des adaptateurs substitues.

Le snapshot provient de la branche declaree `cursor/rosario-navigation-integration-v1`, HEAD historique `657aaef34c067173bd44a022d6b83a90010aea89`, avec ses modifications non commitees. Ce HEAD n'est pas une consigne de retour arriere. Les changements plus recents de Cursor n'ont pas ete examines par Codex.

## Regles de reception

1. Identifier le checkout reel de Cursor, sa branche, son HEAD et ses modifications locales. Ne pas supposer que les anciens dossiers `schoolsafe-integration-v1` et `schoolsafe-navigation-v1` sont identiques ou a jour.
2. Verifier l'empreinte du ZIP, son inventaire et les preuves de remise. Arreter si une empreinte ne correspond pas; ne pas regenerer les preuves pour masquer un ecart.
3. Examiner les composants dans une zone de revue separee. Ne pas extraire le ZIP directement sur le projet principal. Ne pas remplacer des fichiers partages par ceux du snapshot.
4. Etablir pour chaque destination : bouton, ecran, fonction existante, source officielle, contrat reel, portee, permission exacte, raccordement, conflit eventuel et test attendu. Les schemas proposes pour l'interface ne sont pas des DTO ni des grants IAM.
5. Conserver les moteurs Rosario et JASPE, l'authentification, les protections de cartes, scanner, remise d'eleves et controle des frais. Ne pas reconstruire un moteur existant. Toute modification du coeur Rosario exige une justification, ses risques et une autorisation specifique du proprietaire.
6. Distinguer lecture, preparation, ecriture et decision sensible. Les roles de demonstration ne conferent aucun droit reel; les DENY, portees ecole, affectations et enfants autorises restent controles par la session et le serveur.
7. Les options `featureScreens` et `schoolWorkflows` activent des parcours de demonstration seulement. Ne pas activer artificiellement le mode demonstration dans une session reelle pour faire apparaitre les ecrans.
8. Conserver les erreurs et limites visibles tant qu'un raccordement n'est pas prouve. Une relecture locale n'est ni un enregistrement, ni une publication, ni une validation metier.

## Priorites a rapporter au proprietaire

| Priorite | Question a resoudre |
| --- | --- |
| P0 | Contrats reels et autorisations de chaque action; conflits avec les corrections recentes de Cursor |
| P1 | Admission et bulletins : verifier ce qui existe dans la version actuelle, sans conclure a une absence definitive a partir du snapshot |
| P1 | Sources encore demonstratives de paie, comptabilite et autres modules : ne pas les declarer operationnelles |
| P1 | Validation humaine du rendu visuel avant integration |
| P2 | Palmares : periode, criteres, ex aequo, resultats publies, visibilite et source des 10 meilleurs |
| P2 | Activites et calendrier : source officielle; ne pas fabriquer des evenements |
| P2 | Jeux d'exemples distincts (180 eleves en synthese, 24 dossiers en consultation), a remplacer par des donnees coherentes autorisees |
| P2 | Tests sur appareils physiques, lecteur d'ecran, impression et parcours authentifies apres raccordement |

## Livrables attendus de Cursor

Produire d'abord le rapport pre-integration avec : etat exact du projet receveur, fichiers recus, matrice de raccordement, conflits, risques, liste des modifications envisagees, elements bloques et demande de validation au proprietaire.

Apres autorisation seulement, integrer progressivement dans son propre projet, tester les parcours reels et produire `CURSOR_CODEX_POST_INTEGRATION_AUDIT.md`. Ne pas marquer une fonction operationnelle sur la seule base d'un test visuel Codex.

## Statut de transmission

`PACKAGE=PREPARED_AND_LOCAL`

`DIRECT_MESSAGE_TO_CURSOR=NOT_SENT`

`CURSOR_RECEIPT=NOT_CONFIRMED`

`OWNER_PRODUCTION_DESIGN_ACCEPTANCE=PENDING`

`CURSOR_INTEGRATION=NOT_STARTED_BY_CODEX`

Le fichier est pret a etre lu par Cursor sur cette machine. Codex ne dispose pas, dans cette session, d'un canal direct de messagerie vers Cursor; aucune reception ni prise en charge n'est donc declaree.
