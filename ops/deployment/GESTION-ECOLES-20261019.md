# Gestion d'école et directeur d'école — console native (19 octobre 2026)

Console : `admin-console.php` (runtime Rosario, aucun service externe). Réservée au **propriétaire
de la plateforme** (`staff.SCHOOLS` vide) ; un directeur voit la même page limitée à ses écoles, sans
les cartes de création.

## 1. Ce qui a été ajouté

| Brique | Où | Effet |
|---|---|---|
| Créer une école | carte « Créer une école » | école créée depuis un gabarit : configuration, niveaux, périodes, périodes de notation, puis **calendrier vérifié** |
| Créer un directeur d'école | carte « Créer un directeur d'école » | compte avec le profil nommé **Directeur**, rattaché à **une** école, mot de passe temporaire et première connexion forcée |
| Direction de l'école | carte « Établissements » | directeur principal, nombre d'adjoints, nomination/retrait du principal, alerte « Aucun directeur principal » |
| Filtre par école | carte « Comptes et accès » | consulter les comptes d'une seule école |

## 2. Une seule logique de création d'école

`SchoolSafeSchoolCreate()` vit dans `ProgramFunctions/SchoolSafeSchoolSetup.fnc.php`. La console **et**
le pont de service `admin-console-bridge.php` l'appellent : les deux chemins créent exactement la même
école, avec le même contrôle de calendrier avant (gabarit cohérent) et après (copie cohérente). En cas
d'échec de copie, l'école partielle est effacée (`SchoolSafeSchoolCreateCleanup`).

## 3. Direction d'école (migration 018)

`schoolsafe-remediation/018-school-leadership.sql`, additive et idempotente :

- `schoolsafe_school_leadership (school_id, staff_id, role, since, changed_by)` ;
- `role ∈ {'principal','adjoint'}` ; **index unique partiel : un seul principal par école** ;
- aucun droit n'est accordé par cette table : les droits restent ceux du profil nommé « Directeur »
  (migration 015). Elle décrit seulement **qui pilote l'école**.

Règles appliquées par la console :

- un directeur créé sans principal existant devient **principal d'office** — une école ne reste pas
  sans direction ;
- nommer un nouveau principal **rétrograde** l'ancien en adjoint (jamais deux principaux) ;
- « Retirer la direction » supprime la ligne ; l'école est alors signalée « Aucun directeur principal »,
  sans promotion automatique.

## 4. Permissions

- Créer une école, créer un directeur, nommer/retirer un principal : **propriétaire uniquement**.
  Un directeur reçoit un refus explicite, et **aucune écriture** n'a lieu (vérifié).
- Le compte créé garde le périmètre d'un directeur : une seule école, pas de création d'administrateur
  de plateforme, pas d'état d'établissement.
- Identifiant unique (staff + students, insensible à la casse), école existante pour l'année, email
  valide, jeton CSRF de session sur chaque action, compte en lecture seule bloqué en écriture.
- Sans le profil nommé « Directeur » (migration 015 absente), la création est refusée avec un message
  qui nomme la migration à appliquer.

## 5. Preuves — `node app/qa-school-leadership.cjs`

Pile Docker réelle (Apache/PHP 8.3/PostgreSQL), école et comptes créés puis **entièrement supprimés**
(contrôle final : 0 école QA, 0 compte QA, 0 ligne de direction).

| Étape | Résultat |
|---|---|
| RED avant le lot | **2 PASS / 11 FAIL** (aucune carte, aucune table, aucune nomination) |
| GREEN après le lot | **13 PASS / 0 FAIL** |
| Migration 018 | appliquée deux fois de suite sans erreur, table et index présents |
| Création d'école | école réelle : 9 niveaux, 7 périodes de notation rattachées, email et slogan enregistrés |
| Identité manquante | refus explicite, **aucune** école créée |
| Gabarit au calendrier cassé | refus, **aucune** école créée, rien de partiel en base |
| Création directeur | profil nommé Directeur, `SCHOOLS=',id,'`, `LAST_LOGIN` vide, principal d'office |
| Connexion du directeur créé | mot de passe temporaire lu dans le flash, connexion réelle → **changement de mot de passe imposé** |
| Second directeur | adjoint accepté, principal inchangé |
| Doublon d'identifiant / école inconnue | refus, aucun compte créé |
| Nomination d'un principal | l'adjoint devient principal, l'ancien est rétrogradé, **un seul** principal |
| Retrait de la direction | ligne supprimée, école signalée sans principal |
| Directeur connecté | ne voit ni la carte école ni la carte directeur ; ses POST sont refusés sans écriture |
| Filtre par école | seuls les comptes de l'école choisie ; la plateforme entière reste visible sans filtre |

Non-régression : `qa-multi-school-bridge.cjs` **18 PASS / 0 FAIL** (dont la création d'école par le
pont, refactorée vers la fonction partagée), `qa-l1-account-state.cjs` **36 PASS / 0 FAIL**,
`scripts/check-migration-versions.mjs` PASS (43 unités), `php -l` OK sur les fichiers touchés.

## 6. Limites

- `qa-school-calendar.cjs` **n'a pas pu être exécuté** : il exige un `SCHOOLSAFE_CONSOLE_TOKEN` et un
  pont exposé en HTTP, absents de l'instance locale. Sa couverture de la création d'école est reprise
  par les deux contrôles ajoutés à `qa-multi-school-bridge.cjs`.
- **Aucun commit, push ni déploiement** ; la production n'a pas reçu la migration 018 ni les cartes.
- Un directeur suspendu ou supprimé laisse son école sans principal : la console le signale, elle ne
  nomme personne d'office.
