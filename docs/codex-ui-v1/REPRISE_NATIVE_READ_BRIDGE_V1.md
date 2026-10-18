# Premier lot de raccordement separe

## Etat

Adaptateur prepare dans la copie Codex uniquement. Aucune integration dans le dossier Cursor. Aucune modification Rosario, backend, authentification ou base. Aucun appel metier reel realise. Le paquet visuel anterieur est conserve sans modification.

## Preuves examinees dans schoolsafe-integration-v1

- app/modules/pilotage/pilotage-api.js : sessionStorage schoolsafe-v2-session, Authorization Bearer et routes /pilotage/*.
- server/src/pilotage/dashboard/routes.ts : extraction Bearer et permission pilotage.dashboard.read. Un cookie seul ne remplace pas ce contrat.
- app/modules/school/school-api.js : listStaff utilise le transport Bearer, createPerson utilise /native/access/users avec cookie.
- app/modules/school/school-native-api.js : liste, detail et creation de brouillons via /native/students avec cookie.
- app/modules/school/school-module.js : seul render est exporte. Les modales privees ne doivent pas etre appelees comme une API publique.
- server/src/scancontrol/routes.ts : routes natives board/teacher, board/school, on-site et audit avec requireAuthSession.
- CURSOR_CODEX_VISUAL_PREINTEGRATION_AUDIT_V1.md : reception du paquet visuel confirmee par le rapport local de Cursor; aucune integration visuelle annoncee dans ce rapport.

## Nouveaux fichiers

1. app/modules/codex-ui-v1/native-read-bridge.js
2. docs/codex-ui-v1/test-native-read-bridge.cjs
3. docs/codex-ui-v1/REPRISE_NATIVE_READ_BRIDGE_V1.md

## Contrat de raccordement

Charger read-adapter.js puis native-read-bridge.js. Construire SchoolSafeCodexNativeReadBridge.create avec getSession et authorizeRead provenant de l'application authentifiee. Fournir le resultat comme option read de l'interface unifiee. Ne jamais prendre ces valeurs dans demo-data.js ni transformer un role visuel en permission serveur.

getSession doit retourner identityKey, schoolId et revision issus de la session reelle. authorizeRead doit verifier les droits reels du lecteur et du dossier, puis retourner strictement true. Le serveur reste l'autorite finale. Sans ces ports, aucune activation reelle n'est possible.

Les six lecteurs existants sont teacherBoard, schoolBoard, onSiteStudent, onSiteClass, onSiteSchool et scanAudit. Les lectures ciblees exigent un UUID. Les listes de dossiers autorises restent un port distinct listAuthorized a fournir; aucune liste fictive ne doit servir en production.

Le transport est GET uniquement, inclut le cookie, interdit les redirections et ne remplace jamais une erreur par des donnees de demonstration. Une origine API differente exige allowCrossOrigin explicite et une configuration CORS/cookie deja validee. Cette option ne resout pas les contraintes navigateur.

## Verification

Executer node docs/codex-ui-v1/test-native-read-bridge.cjs depuis workspace. Tests de contrat simules, pas une preuve de connexion a Rosario. Aucun test authentifie ni responsive ajoute dans ce lot, qui ne modifie aucun visuel.

## Priorites restantes

1. Fournir les ports de session/permission et les listes autorisees, puis tester les six lectures avec des comptes autorises et interdits et des dossiers hors ecole.
2. Tester expiration/revocation et changement d'ecole dans le navigateur reel.
3. Resoudre le contrat Bearer/native de Pilotage et Mon equipe sans modifier Rosario ni inventer un jeton.
4. Raccorder creation/import/detail eleves aux parcours existants. Respecter leurs formulaires et identifiants reels.
5. Examiner chaque autre destination avant activation; aucun branchement automatique de comptabilite, paiement, cartes, scanner ou JASPE.

Ce lot n'autorise aucune ecriture metier et ne rend pas toutes les fonctionnalites operationnelles.
