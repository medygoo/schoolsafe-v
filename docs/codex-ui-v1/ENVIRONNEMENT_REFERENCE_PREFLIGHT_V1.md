# Verification des environnements et portes de validation

Date : 2026-10-11. Inspection locale et lecture distante autorisee. Aucune migration, installation, creation de compte, modification du moteur, integration Cursor ou publication. Aucun secret affiche. Les tests de contrat utilisent leurs substituts; ils ne prouvent pas une connexion a la production.

## Reference retrouvee

RosarioSIS local : C:/Users/PC/Documents/rosario. Warehouse.php declare 12.9.4, comme le VPS. Branche audit/rosario-ui-master-map-v1, HEAD fdcb5f8bb131dead8dd69f0c5b198474c299c7e7. 138 entrees git modifiees/non suivies au releve : conserver toutes les modifications existantes.

Ce dossier contient le vrai moteur PHP, les programmes comptes/profils, un theme assets/themes/SchoolSafe, le design ui-system et des matrices docs/ui. Ces travaux ne doivent pas etre remplaces par la nouvelle interface. Le document SCHOOLSAFE_UI_LOT_PLAN rapporte des parcours visuels precedemment verifies; ce sont des preuves historiques, pas des tests rejoues dans ce lot.

Projet Node SchoolSafe : C:/Users/PC/Pictures/schoolsafe-integration-v1. Branche cursor/rosario-navigation-integration-v1, HEAD 657aaef34c067173bd44a022d6b83a90010aea89. 104 entrees git modifiees/non suivies au releve. Contrats /native/*, tables iam.*, auth.*, app.* distincts du moteur PHP. Le dossier de composants Codex demeure separe.

Conclusion : le moteur cible pour l'authentification Rosario existe maintenant en local et est identifie. La correspondance de tous les contrats Node vers ce moteur n'est pas etablie. Le Studio de cartes depend des contrats Node; le garder intact exige de qualifier cette coexistence avant de retirer une couche ou de publier.

## Services actifs sur Windows

| Element | Preuve | Limite |
| --- | --- | --- |
| SchoolSafe port 8787 | /health HTTP 200, processus Node | Source exacte du processus et cible PostgreSQL effective non certifiees; aucun appel metier ni secret examine |
| Preview Codex port 5187 | preview.html HTTP 200 | Demonstration, pas application metier connectee |
| PostgreSQL conteneur | schoolsafe-native-accounts, image postgres:17.11, port 55436, pg_isready accepte | Schema, fonctions et isolation non qualifies dans ce lot |
| PostgreSQL Windows | Installation C:/Program Files/PostgreSQL/17, processus et ecoute 5432 | Base cible et droits non inspectes |
| Appsmith port 18081 | Processus SSH 6544 | Tunnel vers le VPS; ce n'est pas une installation locale |
| Appsmith local | Aucun conteneur present dans docker ps -a du contexte desktop-linux | Configuration locale retrouvee, mais pas moteur local actif prouve |
| Rosario PHP local | Sources presentes; aucun processus PHP ni executable PHP retrouve sur PATH, aucun conteneur PHP actif dans le contexte consulte | Autre runtime possible hors inventaire; URL locale executable non identifiee |

Docker Desktop fonctionne dans le contexte desktop-linux. Les images locales recensees sont PostgreSQL 17.11 et 17.11-bookworm. Aucun demarrage de service effectue.

## Appsmith local retrouve

C:/Users/PC/Documents/Codex/2026-10-08/cr/outputs/prodeli-control-appsmith contient un Compose et des rapports de validation locale historiques. Le paquet prodeli-control-administration-platform voisin contient la configuration epinglee au digest b9b44edb71de4638d7b662e6dc0fc30bf5ef0e1f95353afb03e68ee518c8b460.

Le README precise configuration de deploiement uniquement et aucune source de donnees/integration. Les secrets .env n'ont pas ete lus. Le dossier Documents/prodeli-control- est vide dans le releve. Ne pas confondre presence d'un Compose avec instance actuellement operationnelle. Ne pas tirer automatiquement la balise release mutable.

## Comparaison de quatre fichiers Rosario

Comparaison SHA256 locale/VPS, avec normalisation des fins de ligne CRLF vers LF pour eviter un faux ecart Windows.

| Fichier | Resultat |
| --- | --- |
| index.php | Identique apres normalisation |
| ProgramFunctions/FirstLogin.fnc.php | Identique apres normalisation |
| modules/Users/User.php | Different; diff local/HEAD montre des ajouts de presentation SchoolSafeCard |
| modules/Users/Profiles.php | Different; diff local/HEAD montre un ajout de presentation SchoolSafeCard |

Ces quatre fichiers ne constituent pas un audit exhaustif des deux arbres. Les ajouts visuels locaux ne sont pas des preuves de modification de l'authentification. Ne pas copier tout le checkout sale sur le VPS.

## Tests executes dans ce lot

- npm run typecheck : PASS, exit 0, sans emission de code.
- Dix suites serveur ciblees : PASS, 93 tests, exit 0. authnative, authnative-routes, authnative-profiles, authnative-recovery, sessionnative, access-provision et quatre suites cardsnative (access, batch, school-identity, academic-year). Tests avec substituts, pas e2e PostgreSQL ou navigateur.
- node --check native-read-bridge.js : PASS.
- node scripts/check-migration-versions.mjs --require-installable : FAIL. Le controle initial des 12 ensembles/43 unites passe, puis l'inventaire echoue sur database/family/v2/01_pickup_person_controls.sql : empreinte attendue 39cd740e39595c8d4b47de02c20d9033b950678f28b17f2711a7c50afbd5e0a6, actuelle e987034b535e35bc32cad8d8b69e9303d6b74c1adeeee67958535220223b3e21.
- node --test scripts/permission-contract.test.mjs : 1 reussi, 2 echoues. Les deux echecs proviennent du meme manifeste, avant validation des autorisations. Ne pas les presenter comme deux vulnerabilites de permission prouvees.

Manifeste non regenere : il faut examiner les modifications en cours et etablir le SQL de reference avant toute mise a jour de somme. La suite complete et la CI ne sont pas declarees vertes.

## Staging et production

Le VPS accessible contient Rosario 12.9.4, Appsmith et leurs bases, plus Control/activation/Supabase. Aucun environnement applicatif staging distinct n'a ete identifie dans l'inventaire limite des conteneurs et dossiers /opt. Un conteneur rehearsal PostgreSQL n'est pas a lui seul un staging complet. Les domaines app/control ne se resolvaient pas lors du controle precedent; pas de nouvelle certification DNS dans ce lot.

Acces SSH disponible ne signifie pas preparation au deploiement. Aucune restauration de sauvegarde, aucun rollback et aucun test de compatibilite des schemas n'ont ete effectues.

## Portes obligatoires avant publication

1. Figer un perimetre de reference sans ecraser les deux checkouts sales; distinguer PHP Rosario, Node cartes et composants Codex. Inventorier les travaux existants et leurs responsabilites.
2. Etablir les cibles PostgreSQL effectives et garantir que tous les essais utilisent une base locale de test. Ne pas executer de script d'integration qui pourrait charger une connexion de production.
3. Disposer d'un runtime Rosario 12.9.4 local et d'une instance Appsmith locale isolee avec image epinglee, ports loopback et donnees fictives. Leur installation n'est pas realisee par ce preflight.
4. Qualifier la liaison des sessions, comptes et permissions Rosario avec les ecrans; rechercher la passerelle existante avant d'en proposer une. Ne pas faire de double base de mots de passe.
5. Examiner le SQL divergent et remettre les manifestes en coherence apres validation. Rejouer les controles d'installation et permission, puis une installation neuve sur base jetable.
6. Raccorder Pilotage et Eleves, verifier DTO, actions, refus de droit, changements d'ecole, recuperation et expiration. Les pages manquantes doivent etre signalees au proprietaire.
7. Conserver le Studio; rejouer ses vrais parcours locaux. Le remplacement 500 et la persistance du recadrage rapportes par Cursor restent ouverts tant que non retestes. Camera et impression physique ne sont pas prouvees par des tests simules.
8. Completer Appsmith : gestion centrale, ecoles, administrateurs et appels controles Rosario. Aucun compte Appsmith ne doit conferer implicitement des droits Rosario.
9. Tests navigateur desktop/tablette/telephone, donnees reelles de test, isolation multi-ecoles, CSRF et sessions. Suite complete puis validation explicite du proprietaire.
10. Identifier et preparer un vrai staging separe; DNS/TLS, configuration secrete, image reproductible, sauvegarde et restauration testee. Rejouer les parcours avant autorisation de publication.
11. Production seulement apres validation : sauvegarde, plan de retour arriere, release exacte et controles apres publication. Aucun deploiement autorise par le seul inventaire.

## Etat de cloture

MOTEUR_PHP_LOCAL=IDENTIFIED_12_9_4
LOCAL_REFERENCE=PARTIALLY_VERIFIED
APPSMITH_LOCAL_RUNTIME=NOT_CONFIRMED_ACTIVE
AUTH_BRIDGE=NOT_VERIFIED
INSTALLATION_GATE=FAILED_CHECKSUM
STAGING=NOT_VERIFIED
PRODUCTION_READY=NO

Nouveau fichier uniquement : docs/codex-ui-v1/ENVIRONNEMENT_REFERENCE_PREFLIGHT_V1.md. Aucun autre code source edite dans ce lot; outils de tests susceptibles de produire leurs caches techniques habituels. Aucun secret consulte ni aucune donnee de production modifiee.
