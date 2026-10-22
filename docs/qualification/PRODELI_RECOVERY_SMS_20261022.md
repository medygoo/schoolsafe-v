# ProDéli — récupération et corrections SMS, qualification locale du 22/10/2026

## Statut et périmètre

**QUALIFIÉ SUR IMAGE EXACTE — NON DÉPLOYÉ, NON PUBLIÉ.** Image candidate `sha256:b7c276bec9487aa97d4003411d43bc83671d819b72ed3ca94bf2c4c6e2c161e8`, empreinte source `61eb80821a8a73cf1ccd473c9c42582e59157ee2eae13c3436fa7be0560b363e`. Branche `work/prodeli-completion-20261021`, base `fbc17e28b909ea1b1680ba235af7a53e986d0716` + cinq sources runtime non commitées, vérifiées par manifeste (pas l'image du seul commit de base). Les changements non commités de la session précédente ont été conservés et complétés. Instruction du propriétaire : ne pas refaire le tableau de bord, la création d'école, le rattachement du principal ou son premier accès déjà livrés. Aucun changement de ces fonctionnalités ; leur recette existante a seulement été rejouée en non-régression.

Aucun push, merge, déploiement, envoi externe ou activation de ligne réelle effectué. La QA exacte s'est déroulée dans des conteneurs temporaires internes distincts de la production, retirés ensuite. Seules des métadonnées de schéma de production ont été inspectées en transaction READ ONLY ; aucun compte/donnée métier réelle lu ou modifié pour essai. Keycloak reste en pause ; organisation des notifications et fournisseurs inchangée. Cette qualification ne vaut pas recette de production ni preuve de livraison d'un SMS.

## Défauts reproduits et corrections

- Les preuves de la session précédente montrent neuf échecs sur vingt contrôles : consommation du cinquième essai, concurrence de vérification, émission avant transport, sélection de ligne et confidentialité de l'outbox. Les correctifs locaux avaient porté les contrats à 29 PASS, avant le dernier raccord du hub.
- Nouveau contrat de raccord : le contrôleur de test SMS omettait encore `line_id` malgré le choix confirmé. **30 contrôles : un échec avant, zéro après correction.** Le contrôleur transmet maintenant cet identifiant au routeur, uniquement pour `purpose=test`. Aucune bascule silencieuse vers une autre ligne ; quotas/routage automatique ordinaires préservés.
- Depuis `/compte/recuperation`, les liens relatifs aboutissaient à `/compte/PasswordReset.php` et `/compte/index.php`. Échec navigateur reproduit ; destinations corrigées vers les routes racines existantes.
- Le hub émet le défi avant transport et envoie le même code au numéro enregistré normalisé. Rejet/exception du transport invalide le défi ; la session ne reçoit aucun identifiant utilisable en cas d'échec.
- Vérification OTP par UPDATE atomique : expiration, plafond de cinq essais, consommation immédiate au dernier échec et usage unique sous concurrence. Émission transactionnelle avec verrou du compte et invalidation des anciens défis.
- Suite au GO de qualification : **course native reproduite**, deux clients HTTP indépendants bloqués à une barrière DB réelle réussissaient tous deux à utiliser le même lien (deux succès, époque 2). `SchoolSafeRecoveryCompleteStaffReset()` relit/verrouille le compte, revalide le snapshot natif et l'expiration avec l'horloge après acquisition du verrou, puis met à jour toutes les années et révoque dans la même transaction. **Après : un succès, un refus, époque 1.** Échecs de révocation par retour faux et exception : rollback des mots de passe/échéances prouvé. Aucun nouveau moteur de jeton.
- Le code OTP n'est pas conservé en clair dans l'historique SMS. La simulation reste réservée à la recette isolée.
- Même message et même formulaire pour compte inconnu, téléphone incorrect ou canal non disponible. Une nouvelle demande efface l'ancien défi de la session.
- Les comptes à premier accès encore incomplet et les identités gérées ne passent pas par cette récupération native : garde à l'émission du lien et à sa présentation/consommation dans `PasswordReset.php`, y compris pour un lien émis avant le changement d'état. Moteur natif, cryptographie, politique de mot de passe et révocation par époque existants conservés.

## Fichiers applicatifs : cinq seulement

Sous `app/modules/codex-ui-v1/extensions-test-runtime/` :

1. `ProgramFunctions/SchoolSafeRecovery.fnc.php`
2. `ProgramFunctions/SchoolSafeSms.fnc.php`
3. `compte-recuperation.php`
4. `PasswordReset.php`
5. `plateforme-prodeli-sms.php`

Tests ajoutés : `scripts/prodeli-recovery-sms-contracts.php`, `app/qa-prodeli-recovery-sms.cjs` et `app/qa-prodeli-reset-concurrency.cjs`. Préparation de QA exacte : `scripts/prodeli-recovery-image-qa.py`. Préflight et retour arrière compatible : `ops/releases/prodeli-recovery-sms-20261022/README.md`. Aucune nouvelle migration, route, table de comptes ou infrastructure de communication.

## Preuves exécutées

| Vérification | Résultat | Niveau de preuve |
|---|---|---|
| Contrats récupération/SMS | **37 PASS, 0 FAIL** | Fonctions DB Rosario et PostgreSQL réels, schéma jetable ; transport injecté. Deux processus PHP indépendants pour l'usage unique concurrent de l'OTP. |
| Parcours récupération/SMS | **10 groupes PASS** | Navigateur/HTTP/PHP/PostgreSQL fictif réels ; SMS simulé, aucun destinataire externe. |
| Présentation comptes, communication, dashboard, navigation, intentions natives | **92 contrats PASS** | 16 + 28 + 12 + 20 + 16, données/permissions substituées selon les harnais existants. |
| Non-régression création école/principal/premier accès | **10 groupes PASS** | Harnais navigateur existant, base fictive réelle : assistant complet, confirmation/CSRF/rejeu, expiration/renouvellement, première connexion, mot de passe personnel et portée scolaire. |
| Syntaxe PHP | **5 fichiers runtime PASS** | `php -l`. |
| Syntaxe du nouveau harnais JS et diff | **PASS** | `node --check`, `git diff --check`. |
| Course du formulaire natif et pannes de révocation | **PASS sur image exacte** | Deux vrais clients HTTP/sessions et deux verrous DB observés ; un seul succès, une seule révocation, mot de passe gagnant sur les deux années. Deux pannes injectées restaurent mot de passe et échéance. Le relais local sérialisant les requêtes n'est pas utilisé pour cette preuve. |
| Image exacte | **PASS** | Base `36a9e2fb…`, candidat `b7c276be…`, cinq sources conformes au manifeste ; **2 755 autres fichiers identiques**, contenu/mode/UID/GID. 92 contrats renderer, 37 PG, dix groupes récupération/SMS, course native et dix groupes de non-régression exécutés sur cette image, pas sur un overlay source. |
| Nettoyage et préservation | **PASS** | Snapshot des onze tables fictives contrôlées identique après recette ; pas de compte `qa.recovery.*`/`qa.reset.*` ni de schéma jetable. Conteneurs/réseau temporaires retirés avec garde d'étiquette/nonce. Identifiants, démarrages, images et empreintes des environnements des services de production inchangés ; production toujours saine sur `36a9e2fb…`. |

Les dix groupes de récupération couvrent : liens officiels et largeurs 1280/390 sans débordement ; cache no-store ; CSRF ; non-énumération et effacement de l'ancien défi ; remise OTP au formulaire natif ; nouveau mot de passe, lien non rejouable, ancienne session révoquée et reconnexion ; expiration/cinq essais ; limitation des demandes ; échec du mailer sans changement d'échéance, lien expiré et réponse générique pour courriels inconnus ; premier accès/identité gérée protégés ; droits globaux/confirmation/CSRF et ligne confirmée dans l'outbox SMS simulée.

## Environnement et reproductibilité

- Première qualification locale : web `schoolsafe-prodeli-presentation-qa`, PostgreSQL `schoolcef-harmonization-pg`, base `schoolcef_fixture`, label `schoolcef.qualification=harmonization-20261021`.
- Réseau Docker `schoolcef-harmonization-local` vérifié **internal=true**, relais loopback 8096. Le harnais navigateur refuse les requêtes hors de ce relais ; le transport SMS est simulé. Le transport HTTP réel et la SIM sont exercés seulement par des callbacks fictifs dans les contrats.
- Migration additive existante `035-recovery-epoch-otp.sql` absente de cette base fictive au départ : appliquée localement pour rendre le parcours testable. Elle est exercée séparément dans le schéma jetable des contrats. Aucun schéma de production inspecté ou modifié pendant cette reprise.
- Les contrats PHP se lancent dans le conteneur QA avec `SCHOOLSAFE_CONTRACT_PHP_ROOT=/candidate` et le script copié sous `/tmp/`. Le contrat récupération refuse tout autre hôte/base que ceux de la recette.
- QA finale sur la base immuable déjà présente sur le VPS : build COPY de cinq fichiers, `--network none --pull=false`, puis web read-only et PostgreSQL entièrement fictif dans un réseau `--internal`, sans port publié. Connexion navigateur par tunnel SSH loopback uniquement. Aucun montage ni secret de production. Le dump de recette vient uniquement du PostgreSQL fictif local. Archive entière de l'ancien essai tronquée : non réutilisée, aucun transfert complet retenté.
- `python scripts/prodeli-recovery-image-qa.py prepare|verify|diagnose|cleanup --evidence <dossier-prive>` prépare cette recette, vérifie le scope, les empreintes des fixtures et la préservation des services, sans commande de déploiement. Le candidat est associé au manifeste du worktree ; tout nouveau SHA de publication doit être relié aux mêmes hashes avant livraison.
- Le nouveau harnais navigateur se lance depuis le dépôt avec `node app/qa-prodeli-recovery-sms.cjs`, `PRODELI_RECOVERY_EVIDENCE` vers un dossier privé, Playwright disponible via les dépendances locales ou `NODE_PATH`, et le Chromium installé (chemin remplaçable par `PLAYWRIGHT_CHROMIUM_EXECUTABLE`). Préconditions : migration 035 présente et aucune configuration de simulation préexistante. Il crée des identités aléatoires, active seulement les paramètres fictifs nécessaires, restaure la ligne et retire ses fixtures.
- Incidents de harnais résolus : précondition SQL manquante, boucle de comptage IP incompatible avec le relais bornée/remplacée par le marqueur du run, variables serveur manquantes lors d'une émission CLI et noms des champs de confirmation alignés sur le formulaire natif. Nettoyages ciblés des deux runs interrompus effectués ; aucun compte existant supprimé.

Preuves privées : `C:/Users/PC/SchoolSafeProductionUpdate-20261021-evidence/recovery-sms/` ; preuve finale **`exact-image-r2/`** (`image-prepare-proof.json`, `exact-integration-result.json`, `image-verify-proof.json`, `image-cleanup-proof.json`, manifeste, résultats récupération/course/native). Défaut de course avant/après dans `reset-concurrency-before.log` / `reset-concurrency-after.log`. Aucun secret ou artefact de session dans ce rapport.

### Incident de harnais pendant la qualification exacte

Premier run exact : six groupes passent puis `socket hang up` du client HTTP via tunnel, sans résultat d'assertion applicative. Ressources temporaires nettoyées, production inchangée. Investigation bornée : Apache annonce keep-alive 5 s, vérifications SQL/SSH intercalées ; sonde GET/POST après attente et avec/sans connexion persistante réussie, conteneurs sans OOM ni redémarrage. **La cause précise de la coupure n'est pas reproduite**, l'expiration d'une connexion persistante reste une hypothèse, pas un défaut produit démontré. Le harnais ferme maintenant ses connexions entre requêtes ; **aucun POST n'est retenté automatiquement**. Run final complet PASS, diagnostics conservés et QA retirée. Aucun correctif produit spéculatif pour cet incident.

## Revue de sécurité ciblée

- Identifiant/numéro relus côté serveur, téléphone normalisé et égal au numéro enregistré ; codes à six chiffres, identifiants SQL numériques et chaînes échappées suivant le moteur Rosario existant.
- Garde CSRF du hub et du test SMS, refus scolaire de l'administration globale, confirmation explicite de ligne et interdiction de forcer une ligne pour une notification ordinaire.
- Messages/formulaires génériques vérifiés ; limitation des demandes et cinq essais vérifiés. Simulation confinée à la recette et aucun code dans le navigateur ou l'historique SMS.
- OTP et lien natif consommés une fois sous concurrence ; mot de passe/révocation transactionnels avec rollback aux pannes injectées, pas de succès partiel.
- Identités gérées et premier accès exclus à l'émission et à la remise. Pas de changement de cryptographie native, de fournisseur, de politique de mot de passe ou de permissions.
- Métadonnées READ ONLY : huit tables requises présentes sur la cible, image active et santé inchangées. Aucune nouvelle migration nécessaire à ce candidat ; le préflight final doit encore vérifier colonnes/contraintes et dérive avant bascule.
- Revue limitée à ce lot personnel natif ; pas d'audit complet des parcours élèves, de la politique globale proxy/IP ou des dépendances. Le RED CI sécurité historique n'est pas déclaré corrigé par ces tests.

## Reste avant clôture et livraison

1. **Qualification demandée terminée** : course native, revue ciblée et image exacte PASS. Ne pas refaire ces lots sans changement ou dérive.
2. Obtenir l'autorisation spécifique de publication/déploiement ; le GO de qualification n'a déclenché aucun push, merge ou bascule. Committer la source et rattacher son SHA aux hashes exacts avant publication.
3. Exécuter le préflight final, sauvegarde privée et recette de bascule dédiée selon `ops/releases/prodeli-recovery-sms-20261022/README.md` ; préserver sessions et cache d'impression. Pas de nouvelle migration, pas de restauration de données pour un essai.
4. Retour arrière préparé/documenté **non exercé** : aucun fallback automatique vers une image réintroduisant les failles. Préférer un correctif compatible ; maintien/maintenance ciblée à valider séparément en incident, jamais de suppression d'OTP/onboarding ni de reset d'époque pour contourner un garde.
5. Vérification après livraison puis recette connectée. Réception externe seulement avec canal configuré et destinataire expressément autorisé. Un statut SMSGate `accepted` ne prouve ni envoi physique ni réception.

**Ne pas présenter l'ensemble ProDéli comme terminé et ne pas refaire les lots déjà livrés.**
