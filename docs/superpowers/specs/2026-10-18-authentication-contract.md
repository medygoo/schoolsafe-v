# Contrat d'authentification SchoolSafe — état au 18 octobre 2026

Statut : **document de travail**. Aucun code modifié par ce document. Il nomme les
invariants d'accès, l'endroit où chacun est appliqué, le test qui le prouve, et les
décisions qui restent au propriétaire.

## 1. Portée

Ce contrat couvre l'identité et l'accès : qui se connecte, sous quelle école, avec
quels droits, et quand l'accès tombe. Il ne couvre pas le contenu pédagogique, ni les
performances, ni la sécurité réseau (TLS, pare-feu), traités ailleurs.

## 2. Constat : deux piles existent, une seule est déployée

| Pile | Emplacement | Identité | Déployée par `ops/deployment/docker-compose.yml` |
|---|---|---|---|
| Application PHP (Rosario/SchoolSafe) | `app/modules/codex-ui-v1/extensions-test-runtime/` | `staff` / `students`, mot de passe `crypt` `$6$` (`encrypt_password()`) | **Oui** — service `web` |
| Serveur Node (Fastify) | `server/src/authnative`, `sessionnative`, `accessnative`, `access` | profils natifs, Argon2 (`@node-rs/argon2`), jetons Bearer | **Non** — absent du compose |

Le compose déploie `db`, `web`, trois workers et Caddy. Le serveur Node n'y figure pas :
la pile PHP est donc **la seule voie d'authentification en production** aujourd'hui, et
la pile Node est une cible présente dans le dépôt, testée séparément.

Conséquence directe : deux formats de mot de passe et deux modèles de session coexistent
dans le dépôt. Tant que la pile Node n'est pas soit déployée, soit explicitement gelée,
un correctif d'accès peut être appliqué au mauvais endroit sans que rien ne le signale.

## 3. Invariants

Chaque invariant est numéroté pour être cité dans une revue ou un test.

### I1 — Une seule identité par personne et par année
Le compte vit dans `staff` (adultes) ou `students` (élèves), pour un `SYEAR` donné. La
connexion retrouve la personne par son nom d'utilisateur, insensible à la casse.
*Appliqué* : `index.php` (connexion), `functions/User.fnc.php`.
*Preuve* : `qa-l1-account-state.cjs` (connexions réelles de chaque profil), `qa-dashboard-periods.cjs`.

### I2 — Session liée à une école, jamais héritée d'une démo
Une session ouverte porte `STAFF_ID` ou `STUDENT_ID` et `UserSchool`. Aucune session
native ne retombe sur l'école de démonstration ni sur des permissions de démo.
*Appliqué* : `Warehouse.php`, `app/modules/core/access.js`.
*Preuve* : `qa-native-access-console.cjs` (contexte cookie, `schoolId`, zéro repli démo).

### I3 — État de compte vérifié à chaque requête
`active` / `readonly` / `suspended` / `expired`, par personne **et** par école
(`schoolsafe_account_state`, migration 014). Une suspension survit au changement d'année,
une personne présente dans deux écoles peut être suspendue dans l'une sans perdre l'autre.
`session_epoch` révoque les sessions déjà ouvertes.
*Appliqué* : `ProgramFunctions/SchoolSafeAccountState.fnc.php`, appelé depuis `Warehouse.php` (ligne 520).
*Preuve* : `qa-l1-account-state.cjs` — suspension, lecture seule, expiration, révocation de session, date de fin dépassée.

### I4 — État d'école vérifié à chaque requête
`active` / `suspended` / `inactive` (`schoolsafe_school_state`, migration 016). Une école
coupée coupe tous ses utilisateurs ; une personne de deux écoles bascule silencieusement
sur celle qui reste ouverte ; le propriétaire de la plateforme garde l'accès pour administrer.
*Appliqué* : `ProgramFunctions/SchoolSafeSchoolState.fnc.php` → `SchoolSafeSchoolSessionGuard()`, appelé depuis `Warehouse.php` (ligne 529).
*Preuve* : `qa-l1-account-state.cjs` — école suspendue, réactivée, désactivée, date de fin, élève et parent multi-écoles.

### I5 — Lecture seule = aucune écriture, nulle part
Un compte `readonly` se connecte et consulte, mais `AllowEdit()` refuse l'écriture, y
compris depuis la console d'administration (`SchoolSafeAccountBlocksWrite()`).
*Appliqué* : `functions/AllowEdit.fnc.php`, `admin-console.php`.
*Preuve* : `qa-l1-account-state.cjs` — connexion autorisée, écriture refusée, `AllowEdit()` faux.

### I6 — Portée des droits
Propriétaire de la plateforme (`SCHOOLS` vide) : toutes les écoles, y compris les comptes
globaux. Directeur : uniquement les personnes de ses écoles, jamais les comptes globaux.
Élève et parent : leur périmètre d'inscription.
*Appliqué* : `SchoolSafeGlobalAdmin()`, `SchoolSafeScopeParse()`, restrictions SQL de `admin-console.php`.
*Preuve* : `qa-l1-account-state.cjs` — directeur école 2 : ni école 1, ni comptes globaux, fiche d'un utilisateur de l'école 1 refusée (403), sa propre fiche accessible.

### I7 — Première connexion forcée
Un compte neuf (`LAST_LOGIN` nul + configuration globale) passe par l'écran natif de
changement de mot de passe avant d'atteindre le portail.
*Appliqué* : `ProgramFunctions/FirstLogin.fnc.php`, `index.php`.
*Preuve* : `qa-l1-account-state.cjs` — compte créé par un directeur : première connexion ⇒ changement forcé.

### I8 — Réinitialisation de mot de passe et fermeture des sessions
Une réinitialisation génère un mot de passe temporaire, remet `LAST_LOGIN` à nul et
déconnecte les sessions ouvertes (epoch).
*Appliqué* : `PasswordReset.php`, `admin-console.php`, `admin-console-bridge.php`.
*Preuve* : `qa-l1-account-state.cjs` — « Mot de passe » puis première connexion ; révocation via la console.

### I9 — Isolation multi-écoles par la base (pile Node)
RLS + `school_id` + ACCESS_LAW, deux pools aux rôles incompatibles : `schoolsafe_auth`
(RPC `api.auth_*` uniquement) et `schoolsafe_api` (métier + ACCESS_LAW).
*Appliqué* : `server/src/db/pool.ts`, `server/src/access/guard.ts`, `database/access/v1/`.
*Preuve* : `server/tests/db-pools.test.ts`, `db-startpools.test.ts`, `access-guard.test.ts`, `sessionnative` via `api.session_bootstrap()`.
**Non déployé** par le compose actuel : cette isolation ne protège pas encore la production PHP.

### I10 — Secrets de service
Jeton de console (`SCHOOLSAFE_CONSOLE_TOKEN`, comparaison `hash_equals`, échec fermé 503
si absent) et signature HMAC du Worker JASPE (fenêtre anti-rejeu de 300 s, corps canonique).
Aucun secret dans les fichiers versionnés ; `.dev.vars` et `.env*` ignorés.
*Preuve* : `worker-jaspe/test/worker.test.ts` (signature, rejeu), `qa-school-calendar.cjs` (jeton de console exigé).

### I11 — Pas de retour à Supabase ni au mode démo
Aucune route Supabase réactivée ; l'absence de session valide ne produit jamais de droits
de démonstration.
*Preuve* : `qa-native-access-console.cjs` ; voir la compétence `schoolsafe-supabase-legacy`.

## 4. Inventaire des preuves

| Suite | Ce qu'elle prouve | Exécutée récemment |
|---|---|---|
| `app/qa-l1-account-state.cjs` | I1, I3, I4, I5, I6, I7, I8 (27 contrôles) | oui — 27 PASS sur l'image `Dockerfile.vps` |
| `app/qa-dashboard-periods.cjs` | I1 (connexion réelle), robustesse des écrans dérivés | oui — 5 PASS |
| `app/qa-school-calendar.cjs` | I6 (console propriétaire), intégrité à la création d'école | oui — 4 PASS |
| `app/qa-native-access-console.cjs` | I2, I11 (contexte cookie, DENY, portées, expiration, zéro écriture) | non rejouée dans ce lot |
| `app/qa-a51-session.cjs`, `qa-a51-targeted-denies.cjs` | validité temporelle et refus ciblés (lot A5.1) | non rejouées dans ce lot |
| `app/qa-native-access-directory.cjs`, `qa-native-custom-roles.cjs` | annuaire d'accès et rôles personnalisés | non rejouées dans ce lot |
| `server/tests/*` (Vitest) | I9 et pile Node | non rejouées dans ce lot |

## 5. Trous connus, à ne pas masquer

1. **Deux formats de mot de passe** (`$6$` PHP, Argon2 Node) sans règle de migration. Un
   compte ne peut pas être vérifié par les deux piles en l'état.
2. **Aucun test ne relie un invariant à sa preuve** : si quelqu'un retire l'appel à
   `SchoolSafeSchoolSessionGuard()`, seule une suite de bout en bout le verra. Ce document
   est la carte ; il ne remplace pas un test de contrat.
3. **Échec ouvert assumé** dans la garde d'école : si le périmètre d'une personne est
   illisible, la garde laisse le natif trancher (commentaire explicite dans le code). À
   confirmer comme choix, pas comme oubli.
4. **Anti-force brute** : `FAILED_LOGIN` existe côté `staff`, mais aucun test de ce lot ne
   prouve un verrouillage. À vérifier avant de l'affirmer.
5. **La pile Node n'est pas dans le compose** : son isolation RLS/ACCESS_LAW est prouvée
   par ses tests, pas par la production.

## 6. Décisions qui reviennent au propriétaire

- **D1** — Quelle pile fait autorité pour la production ? Recommandation : la pile PHP,
  puisque c'est la seule déployée ; la pile Node reste une cible, explicitement gelée ou
  planifiée, mais pas un second chemin d'accès silencieux.
- **D2** — Si la pile Node doit remplacer la pile PHP : par quel chemin migrent les mots
  de passe, et à quelle échéance ?
- **D3** — L'échec ouvert de la garde d'école est-il accepté ?

## 7. Runbook — ajouter un profil ou une école sans casser l'accès

1. Créer le compte par le chemin prévu (écran Utilisateurs, console propriétaire ou pont
   de console), jamais par SQL direct.
2. Vérifier l'état de compte (`active`) et l'état d'école (`active`) ; sinon l'accès sera
   coupé dès la première requête.
3. Vérifier la portée : un directeur ne doit voir que ses écoles ; un compte global ne doit
   avoir aucune école assignée.
4. Se connecter réellement avec le nouveau compte : première connexion, changement de mot
   de passe, portail, puis un écran métier.
5. Rejouer `node app/qa-l1-account-state.cjs` si la modification touche l'état, la portée,
   la session, la première connexion ou le mot de passe.

## 8. Checklist avant tout déploiement touchant l'accès

- [ ] `node app/qa-l1-account-state.cjs` — 27 PASS.
- [ ] `node app/qa-dashboard-periods.cjs` et `node app/qa-school-calendar.cjs` — 5 et 4 PASS.
- [ ] `node scripts/verify-production-image.mjs` — exit 0 sur l'image `Dockerfile.vps`.
- [ ] Aucune migration appliquée hors d'un déploiement autorisé, après sauvegarde.
- [ ] Aucune route Supabase réactivée ; aucun secret dans le diff.
- [ ] Commit de production identifié par un SHA (retour arrière possible).
