# ProDéli — procédure native de création et rattachement, historique du sous-lot local

**Mise à jour du 21 octobre :** ce document décrit la qualification historique à 29 contrôles du commit `42b9ae5`, pas l'état courant de livraison. Le raccord complet et le premier accès ont ensuite été qualifiés à 47 contrôles PG et dix groupes navigateur sur l'image exacte, puis **déployés** source `3670dad`, image `36a9e2fb…`, avec migrations 018/038 et contrôle indépendant PASS. Lire le [rapport courant](PRODELI_NATIVE_ACCESS_RELEASE_20261021.md) ; les « prochaines actions » et limites ci-dessous sont historiques.

## Statut

**Procédure qualifiée sur PostgreSQL fictif local, non déployée.** Ce sous-lot prépare le lot 2 de `PRODELI_COMPLETION.md` ; il ne clôture pas son raccord au formulaire ProDéli, les droits du POST final, la remise ni la première connexion. Keycloak reste en pause. Production inchangée : source `3eb2e9e35984de9c40e22c571b212e727b13a2bb`, image `c8d10345…` selon la dernière livraison ; pas de nouveau contrôle VPS dans ce sous-lot.

Base de travail : `2bfe7e664e5c0b4344615baff9a2042f78711ebf`, branche `work/prodeli-completion-20261021`. Agent pi. Aucun changement dans les autres worktrees, la communication, les conteneurs de rollback ou les données réelles.

## Existant et correction ciblée

`ProgramFunctions/SchoolSafeSchoolSetup.fnc.php::SchoolSafeCreateDirector()` est déjà appelée par la console native. Elle utilisait `DBInsert` avec des valeurs non échappées, annonçait la réussite indépendamment du rattachement et appelait `SchoolSafeLeadershipSet()`, qui rétrograde un principal préexistant.

La même procédure est conservée :

- Validation des noms/identifiant/courriel conservée, longueur courriel bornée à 255.
- Transaction PostgreSQL propre à l'opération ; une transaction déjà ouverte par l'appelant est refusée, jamais implicitement validée.
- Compte, rattachement et audit de succès validés ensemble. En cas d'erreur SQL, annulation et réponse générique sans secret ; pas de page SQL terminant le processus avant rollback.
- Table leadership ou audit absente/inutilisable : refus, jamais de faux succès.
- Profil nommé Directeur exactement unique et de catégorie `admin` ; école de l'année vérifiée et verrouillée.
- Valeurs échappées selon le contrat Rosario `DBInsertSQL`, ID récupéré par `RETURNING STAFF_ID`, école courante initialisée.
- Principal explicitement demandé alors qu'il existe déjà : `PRINCIPAL_EXISTS`, aucun nouveau compte ni rétrogradation. Adjoint explicitement demandé : adjoint si un principal existe ; sinon principal comme auparavant.
- Aucun compte existant modifié, aucune conversion ni création d'identité liée ; aucune requête fournisseur.
- Verrous de tables `staff`, `students`, leadership pendant l'opération, y compris vis-à-vis des écrivains natifs sans verrou consultatif. Délai d'attente de verrou borné à cinq secondes ; hachage avant prise des verrous. Ce choix privilégie la sûreté des rares créations, pas une promesse de débit de production.

Les droits acteur, CSRF, année courante et délégation restent à contrôler par le contrôleur appelant. Le helper n'est pas une route HTTP. L'ancien setter de remplacement explicite de principal n'est pas modifié ; ses autres usages ne sont pas qualifiés par ce lot.

## Preuves réellement exécutées

`scripts/prodeli-director-transaction.php`, exécuté par PHP dans `schoolsafe-prodeli-presentation-qa`, contre PostgreSQL **17.11** `schoolcef-harmonization-pg`. Données et schéma dédiés `qa_director_<aléatoire>`, supprimés dans `finally` ; aucune table publique modifiée.

Les fonctions DB Rosario réelles (`database.inc.php`, `DBGet`, `DBInsertSQL`) sont chargées. Authentification, hooks et fonction de hachage applicative sont substitués ; le hachage bcrypt PHP réel est vérifié. **Pas de preuve HTTP/UI, ni de première connexion, ni de couverture globale annoncée.**

- Source initiale extraite de Git et rejouée avec le harnais corrigé : échec lors de l'INSERT du nom avec apostrophe. Le harnais transforme l'avertissement PostgreSQL en exception sans exposer SQL/secret et garantit le nettoyage.
- Source corrigée : **29 contrôles PASS**, dont identité avec apostrophe, hachage, rattachement, audit, refus principal existant, doublons case-insensitive/personnel/élève, adjoint, école inconnue, mauvais profil, migration absente, panne audit/rattachement, reprise, refus de transaction imbriquée.
- Quatre processus PHP indépendants : deux essais simultanés du même identifiant → une création/un refus ; deux principaux distincts simultanés pour une école → une création/un refus, un seul compte et un seul principal.
- Migration **018 inchangée**, exécutée deux fois dans le schéma isolé ; écoles préservées. Ce n'est **pas** une qualification de sauvegarde/restauration de production.
- **76 contrats de régression PASS** : comptes 16, dashboard 12, communication 28, navigation 20 ; substituts des harnais existants, pas une nouvelle recette navigateur.
- Lint PHP de la procédure et `git diff --check` PASS. Contrôle indépendant par `psql` : zéro schéma `qa_director_%` restant.

Preuves privées : `C:/Users/PC/SchoolSafeProductionUpdate-20261021-evidence/native-director/` (`before.log`, `after.log`, quatre journaux de régression et copie de source initiale). Aucun secret committé, aucun envoi externe.

Commande locale (le harnais refuse toute autre cible DB et tout contexte non CLI) :

```text
docker cp scripts/prodeli-director-transaction.php schoolsafe-prodeli-presentation-qa:/tmp/prodeli-director-transaction.php
docker exec schoolsafe-prodeli-presentation-qa php /tmp/prodeli-director-transaction.php
```

Le runtime courant est monté en lecture seule sur `/candidate`. La copie web QA et l'image de production ne sont pas remplacées par ce test.

## Prochaine action / conditions avant livraison

1. Raccorder la préparation/confirmation du formulaire ProDéli à cette procédure sans dépendre des hooks d'un plugin désactivé. Revérifier droits, une école, profil déléguable, confirmation liée à acteur/année, refus d'une identité liée et rejeu.
2. Concevoir le raccord à une transaction unique : le helper possède sa transaction ; ne pas l'appeler depuis un `BEGIN` existant ni annoncer atomique un suivi d'ouverture ajouté après le commit.
3. Remise native limitée et URL officielle ; forçage de changement de mot de passe ciblé, sans changer la politique globale. Le mot de passe temporaire de ce helper n'a pas encore d'expiration dédiée.
4. Qualifier migration 018, sauvegarde/restauration, image exacte, parcours HTTP/PG fictif et droits avant livraison ciblée. Aucun déploiement de ce helper seul tant que le premier accès et l'ensemble du contrôleur ne sont pas qualifiés.
5. Préserver toutes les réserves récupération/SMS/réception externe et CI sécurité RED ; elles ne sont pas corrigées par cette transaction.
