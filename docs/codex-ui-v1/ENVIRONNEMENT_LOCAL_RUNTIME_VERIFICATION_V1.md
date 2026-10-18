# Verification des services locaux - 11 octobre 2026

## Resultat

Rosario PHP et Appsmith disposent maintenant de services de test locaux distincts.
Ce resultat confirme leur demarrage, PAS le raccordement des interfaces SchoolSafe ni une aptitude a la production.

## Preuves executees

| Controle | Resultat observe |
| --- | --- |
| Construction image codex-ref-v1-php:local | Reussie, PHP 8.3 Apache |
| Extensions pgsql, pdo_pgsql, gettext, intl, mbstring, gd, curl, xml, zip | 9 / 9 presentes par extension_loaded |
| Syntaxe configuration PHP de test | php -l reussi |
| Syntaxe script de demarrage | node --check reussi apres ajustement reseau, code 0 |
| PostgreSQL | Conteneur codex-ref-v1-db en marche ; requetes de lecture reussies |
| Version en base | config.VERSION = 12.9.4 |
| Donnees d'initialisation | 1 ecole, 3 comptes staff, tous sur SYEAR 2026 ; exemples du fichier SQL, pas des donnees de production |
| Changement de mot de passe general a la premiere connexion | FORCE_PASSWORD_CHANGE_ON_FIRST_LOGIN vide dans la base de test |
| Sources et configuration Rosario | Montages Docker readonly=true |
| Publication HTTP Rosario | 127.0.0.1:18082 -> 80, HTTP 200 |
| Verification navigateur Rosario | Page ROSARIOSIS LOGIN, logo SchoolSafe, nom utilisateur, mot de passe, langue et aide visibles |
| Appsmith | Conteneur running healthy, HTTP 200 sur 127.0.0.1:18083 |
| Verification navigateur Appsmith | /setup/welcome ; formulaire du premier compte avec nom, email et mot de passe |

Le telechargement Appsmith a ete long avec des reprises reseau, puis a abouti. La tentative de l'arreter n'a arrete aucun processus : le telechargement et le script etaient deja termines avec code 0.

## Correctif de preparation

Le reseau PostgreSQL interne ne publiait pas effectivement le port HTTP du conteneur Rosario.
Un second reseau codex-ref-v1-web a ete ajoute et connecte a Rosario.
Le script start.cjs reprend cette correction. PostgreSQL reste seulement sur le reseau interne, sans port hote publie.
Le conteneur web Rosario peut disposer d'une sortie Internet ; il ne faut pas presenter cet ensemble comme totalement coupe du reseau.

## Perimetre preserve

- Aucun changement dans les sources C:/Users/PC/Documents/rosario.
- Aucun changement dans le depot Cursor C:/Users/PC/Pictures/schoolsafe-integration-v1.
- Aucun acces de mutation, deploiement, migration ou modification de compte sur le VPS.
- Aucun changement du moteur de cartes, du moteur JASPE ou de l'authentification Rosario.
- Nouvelle base creee uniquement dans un nouveau volume Docker de test.
- Aucune datasource de production configuree dans cette nouvelle instance Appsmith.

## Fichiers techniques nouveaux

- docs/codex-ui-v1/local-reference/Dockerfile
- docs/codex-ui-v1/local-reference/config.inc.php
- docs/codex-ui-v1/local-reference/start.cjs
- docs/codex-ui-v1/local-reference/README.md
- docs/codex-ui-v1/ENVIRONNEMENT_LOCAL_RUNTIME_VERIFICATION_V1.md

Les fichiers generes .codex-local-reference-v1/db.env et appsmith.env contiennent des secrets locaux. Ne pas les distribuer, les afficher ou les inclure dans un paquet Cursor.

## Etapes encore obligatoires

1. Le proprietaire cree le compte initial Appsmith local et choisit lui-meme son mot de passe.
2. Le proprietaire securise les comptes Rosario de demonstration ; le premier administrateur dispose d'un traitement de premiere connexion dans le code. L'option generale vide ne garantit pas ce comportement pour tous les autres profils.
3. Verifier la connexion Rosario, la deconnexion, l'expiration de session, les permissions et le refus d'acces entre ecoles, avec des comptes de test adaptes.
4. Definir le contrat entre Appsmith de gestion et Rosario, sans remplacer l'authentification Rosario et sans donner d'acces direct non controle aux tables sensibles.
5. Raccorder chaque ecran SchoolSafe a sa fonction locale effective ; signaler toute fonction sans ecran. Le service Node port 8787 n'est pas automatiquement le moteur PHP Rosario.
6. Tester les parcours Studio Card sans modifier son moteur. Les televersements et autres ecritures de fichiers necessitent un espace writable de test dedie ; les sources restent readonly. PDF wkhtmltopdf non configure.
7. Resoudre le checksum SQL divergent signale dans ENVIRONNEMENT_REFERENCE_PREFLIGHT_V1.md avant de valider les migrations SchoolSafe. Ne pas regenerer le manifeste pour masquer la divergence.
8. Effectuer ensuite les tests metier complets et responsive sur les ecrans raccordes, puis valider un staging clairement identifie avant toute production.

## Statuts

ROSARIO_LOCAL_RUNTIME=VERIFIED_STARTUP_ONLY

APPSMITH_LOCAL_RUNTIME=VERIFIED_SETUP_SCREEN

LOCAL_AUTH_E2E=NOT_VERIFIED

SCHOOLSAFE_ROSARIO_CONNECTION=NOT_VERIFIED

APPSMITH_ROSARIO_CONNECTION=NOT_CONFIGURED

PRODUCTION_READY=NO
