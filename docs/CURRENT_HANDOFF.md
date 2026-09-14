# Handoff courant SchoolSafe

Dernière mise à jour : 14 septembre 2026 — Étape 0 (cohérence documentaire).

## ÉTAT ACTUEL

Architecture verrouillée le 14/09 : **1 plateforme SchoolSafe = plusieurs écoles isolées** sur un VPS central (Hostinger, Docker + Coolify), SchoolSafe Control co-hébergé mais logiquement séparé (séparation par privilèges, pas de conteneur par école ; isolation multi-écoles = `school_id` + PostgreSQL + ACCESS_LAW). License Contract V2 validé (spec G0). Déploiement officiel : **Git → Coolify → Docker → VPS**. Références visuelles officielles versionnées dans `docs/design/references/`. Règle de conduite : **VISION LARGE, LIVRAISON ÉTROITE**. Références design validées et versionnées (`a6d6b8f`).

```
ÉTAT ACTUEL            Phase A complète + P2 complété + **P3 complété**
DERNIÈRE ÉTAPE         fix(ui) nom école générique 'Toutes les écoles'
                       avant sélection contexte (6a2177c)
ÉTAPE EN COURS         P4 — cœur quotidien Le Sage (FRONTEND Lots 4-5
                       visuels, personnes autorisées, photo sortie,
                       validation humaine gardien)
ORDRE À SUIVRE         P4 cœur Le Sage · P5 backup + rejeu base réelle
                       · P6 extensions
```

### Rapport — Lot P3 (enforcement backend licence)

```text
PRÉVU :    hook d'application backend bloquant les routes /native/* quand
           la licence n'est pas active ou en grâce ; error code dédié ;
           tests d'enforcement (blocage, exception CORE, grâce, isolation).
FAIT :     server/src/licensenative/gate.ts (nouveau) : hook Fastify
           onRequest avec cache TTL 60s par schoolId, exclusion explicite
           /native/license et /native/trial (CORE non licenciable), lecture
           état via licenseService.readState. server/src/http/errors.ts :
           ajout ApiErrorCode LICENSE_INACTIVE. server/src/native-app.ts :
           création licenseService conditionnelle + registerLicenseGate
           après buildApp. Tests : server/tests/licensenative-gate.test.ts
           (5 scénarios : blocage inactive, exception license, exception
           trial, autorisation grâce, isolation inter-écoles).
TESTS :    licensenative 15/15 PASS · licensenative-gate 5/5 PASS ·
           typecheck PASS · total 20/20 tests licence verts.
MODIFIÉ :  server/src/http/errors.ts (+1 error code) ·
           server/src/native-app.ts (câblage gate + service) ·
           server/src/licensenative/gate.ts (nouveau) ·
           server/tests/licensenative-gate.test.ts (nouveau).
NON TOUCHÉ: frontend, SQL, permissions.json, spec G0, DECISIONS.md.
RISQUES :  cache TTL 60s = révocation prend effet au plus tard après 60s
           (acceptable V1, documenté) ; HMAC octet-exact reste à durcir
           avant exposition publique (même chantier que upload logo).
```

### Rapport — Lot P2 (isolation inter-écoles)

```text
PRÉVU :    balayage complet anti-fuites, corrections, verrou statique.
FAIT :     sweep de TOUS les database/**/*.sql + requêtes serveur :
           138 requêtes métier analysées, 4 candidats — 2 faux positifs
           justifiés (provision_bridge : le prédicat id = v_school EST
           la frontière), 2 corrigés : sommes de solde (record_payment/
           cancel_payment) désormais filtrées p.school_id = v_school_id
           (manifeste baseline régénéré) ; setup.createAdmin rattaché à
           l'école du flux (closure setupSchoolId) au lieu de « la
           dernière école globale ». Verrou nouveau : sweep dépôt avec
           liste blanche documentée. Surfaces saines confirmées :
           auth-adapter/pool/readiness (hors métier), services natifs
           (gate active-path Phase A).
TESTS :    sweep 1/1 · permissions 3/3 · migrations 9 sets/27 ·
           statiques 52/52 · typecheck · 56/292 tests PASS.
COMMIT :   7a7f9e8.
```

### Rapport — Lot 1, Tâche 7 (gate active-path + clôture Phase A)

```text
PRÉVU :    gate de régression statique + suite critique ciblée + clôture
           documentaire (plan, étapes 1-6).
FAIT :     native-access-contract.test.ts (code exact du plan) — les 5
           services humains ne contiennent aucun businessPool.query< et
           contiennent withRequestContext ; suite critique ciblée 9
           fichiers/37 tests ; batterie complète : permissions 3/3,
           migrations 9 sets/27 units, statiques 52/52 (access, cibles,
           baseline, finance, pedagogy, cards, versions), typecheck,
           56 fichiers/292 tests serveur. BASELINE_REPORT : section
           « Phase A » ajoutée (résumé, bugs trouvés, preuves, limites).
           DECISIONS.md non modifié (aucune décision architecturale
           nouvelle dans ce lot).
TESTS :    tous exécutés, tous verts — voir ci-dessus.
COMMIT :   ce lot (code + docs).
RISQUES :  inchangés et documentés — rejeu base réelle (P5), iam machine,
           HMAC octet-exact, allowScripts Esbuild.
```

### Rapport — Lot 1, Tâche 6 (manifestes + contrats statiques modules)

```text
PRÉVU :    enregistrer finance/pedagogy/cards dans l'intégrité des
           migrations + contrats statiques par module (plan, étapes 1-6).
FAIT :     attente étendue 9 sets (échec constaté) ; 3 générateurs + 3
           manifestes (finance 2, pedagogy 1, cards 2 unités) ; contrôleur
           étendu après access ; compteur 27 (26 plan + unité access 03 de
           la tâche 5 — écart documenté) ; 15 tests de contrats nouveaux ;
           BUG RÉEL trouvé par le contrat school_id et corrigé :
           parent_children_list/parent_student_grades utilisaient
           guardian_profile_id/relationship (colonnes inexistantes —
           profile_id/guardian_type), ce qui aurait planté à l'exécution ;
           contraintes school_id ajoutées ; manifeste pedagogy régénéré.
TESTS :    statiques modules 15/15 · access+cibles+baseline 35/35 ·
           migrations 9 sets/27 units PASS (×2 contrôles) · gate
           permissions 3/3 · typecheck PASS · 55 fichiers/287 tests PASS.
MODIFIÉ :  15 fichiers (3 SQL manifestes + 3 générateurs + 3 tests statiques
           + contrôleur + compteur + 1 SQL pedagogy corrigé).
COMMIT :   b5d24b6.
```

### Rapport — Lot 1, Tâche 5 (autorité machine Control)

```text
PRÉVU :    séparer l'autorité machine Control de l'accès humain (plan,
           étapes 1-6) : callbacks signés HMAC, zéro SQL si non signé,
           jamais de profileId humain fabriqué.
FAIT :     SQL api.set_control_context (unité access 03, manifeste
           régénéré 3 unités, compteur 22) ; db/control-authority.ts
           (vérification HMAC fenêtre 300 s + exécuteur machine) ;
           controlprintnative réorganisé (requêtes des routes déplacées
           dans le service ; liste d'impression ENFIN filtrée par école —
           fuite P2 corrigée au passage) ; nouveau callback machine
           POST /native/control/print/status (401 + zéro SQL si non
           signé) ; controlConfig câblé dans les dépendances de routes ;
           code erreur ACCESS_DENIED (union ApiErrorCode existante).
TESTS :    typecheck PASS · 55 fichiers / 287 tests PASS (+3) · gate
           permissions 3/3 · migrations 6 sets/22 units PASS.
MODIFIÉ :  1 SQL + manifeste, 2 fichiers src controlprintnative, 1 nouveau
           db/control-authority.ts, native-app.ts (câblage), 1 test
           nouveau, compteur de migration.
NON TOUCHÉ: cardsnative (callbacks carte restent côté Control minimal),
           frontend, G0-B (enforcement licence = P3).
COMMIT :   07f0508.
RISQUES :  1) sémantique du contexte machine dans iam.* (les RPC Control
           appellent require_access qui exige un profil) — à valider sur
           base réelle en P5 ; 2) signature HMAC porte sur JSON.stringify
           (re-sérialisation) — vérification octet exact à durcir avant
           exposition publique (même chantier que l'upload logo).
```

### Rapport — Lot 1, Tâche 4 (contextualisation des services natifs)

```text
PRÉVU :    toute requête humaine s'exécute dans withRequestContext
           (plan, étapes 1-8) : students (2 méthodes), finance (14),
           pedagogy (30), cards (méthodes DB) ; routes construisant le
           contexte uniquement depuis la session.
FAIT :     test élève étendu d'abord (ordre exact liste + ROLLBACK
           brouillon refusé) ; 3 fichiers de test nouveaux (paires
           lecture/écriture du plan) ; services réécrits méthode par
           méthode (aucune requête pool directe restante côté humain,
           vérifié par les tests d'ordre transactionnel) ; routes avec
           helper contextFrom(request) — aucun identifiant accepté du
           navigateur ; type de vérification : import manquant
           withRequestContext corrigé.
TESTS :    typecheck PASS · 54 fichiers / 284 tests PASS (était 51/276,
           +8 nouveaux, zéro régression) · gate permissions 3/3 ·
           migrations PASS · native-app + integration PASS.
MODIFIÉ :  7 fichiers src (students/finance/pedagogy/cards services+routes)
           + 4 fichiers de test.
NON TOUCHÉ: controlprintnative (tâche 5), licensenative/sessionnative/
           trialnative (déjà contextualisés), SQL, frontend.
COMMIT :   0ae7ef6.
RISQUES :  getStudentFee lit encore app.student_fees en direct DANS la
           transaction contextualisée (même rôle, comportement conservé —
           à reprendre en RPC dédié dans un lot futur si souhaité).
```

### Rapport — Lot 1, Tâche 3 (cibles exactes des RPC)

```text
PRÉVU :    chaque opération métier fournit student_id/class_id/subject_id/
           contexte campagne à iam.require_access (plan, étapes 3-5).
FAIT :     test statique des cibles écrit d'abord → 3/3 échecs constatés →
           finance (frais/paiements/reçu : résolution élève+classe avant
           autorisation ; scan de contrôle : classe + contexte campagne
           jsonb pour assigned_fee_classes) ; pédagogie (listes filtrées
           par ligne iam.can_access ; écritures avec paire classe+matière
           exacte ; update/publish/delete résolvent la cible depuis la ligne) ;
           projections parent (grade.read + classe résolue, notes publiées
           uniquement) ; student_averages qui n'avait AUCUNE vérification
           est désormais autorisé ; cartes (classe résolue avant
           cards.request.print ; fonctions Control déjà contraintes
           school_id — vérifié, inchangées).
TESTS :    cibles 3/3 · access+baseline 32/32 · gate permissions 3/3 ·
           migrations PASS · typecheck PASS · 276/276 tests serveur.
MODIFIÉ :  4 SQL natifs + 1 test statique nouveau.
NON TOUCHÉ: services TypeScript (tâche 4), frontend, manifestes (ensembles
           non encore enregistrés — tâche 6).
COMMIT :   05e75e9.
RISQUES :  RPC prouvés par contrats statiques ; rejeu sur base réelle
           planifié avant VPS (P5) — inchangé.
```

### Rapport — Lot 1, Tâche 2 (vocabulaire canonique)

```text
PRÉVU :    mapping exact des codes legacy SQL vers le catalogue canonique
           (table du plan) + 4 permissions ajoutées + grants rôles.
FAIT :     test échouant écrit d'abord (doesNotMatch legacy) → échec constaté ;
           33 littéraux remplacés ligne par ligne selon le contexte fonction
           (devoirs→pedagogy.assignment.*, notes→pedagogy.grade.*, affectations
           enseignants→school.structure.manage, palmarès→palmarques.*,
           caisse ouverte/fermée séparées) ; +4 codes au catalogue et au seed
           (64 total) ; admin 60→63, cashier +caisse.open ; cards.print.manage
           à autorité control, non attribuée aux rôles école ; manifestes
           baseline/access régénérés ; compteurs figés mis à jour.
TESTS :    access-static + baseline static 32 pass/0 fail · gate
           check:permissions 3/3 VERT (était 41 littéraux) · migrations
           6 sets/21 PASS · typecheck PASS · 276/276 tests serveur.
MODIFIÉ :  5 SQL natifs, permissions.json, seed, role_templates, 2 tests
           statiques, 2 manifestes.
NON TOUCHÉ: services TypeScript, frontend, seed des données (ordre SQL préservé).
COMMIT :   eace2e2.
RISQUES :  RPC SQL modifiés non rejoués contre une base réelle dans ce lot
           (contrats statiques uniquement) — rejeu prévu avant VPS, lot P5.
```

## CE QUI ÉTAIT PRÉVU

Étape 0 : corriger `DECISIONS.md`, `V2_CHARTER.md`, finaliser la spec G0, mettre à jour le handoff, vérifier l'absence de décisions obsolètes présentées comme actives, vérifier le diff, committer, donner le SHA — avec les 11 corrections du propriétaire et la règle permanente de continuité (ajout, jamais suppression d'historique).

## CE QUI A ÉTÉ FAIT

- `docs/DECISIONS.md` : décision Docker 12/09 marquée **OBSOLÈTE — remplacée** (conservée) ; 15 décisions du 14/09 ajoutées avec statuts (Docker/Coolify + chaîne Git→Coolify→Docker→VPS et ses 10 règles, VPS central multi-écoles, school_id UUID + school_code, identité globale + memberships avec résolution de contexte corrigée, OTP abstrait, PostgreSQL souverain + retrait progressif Supabase, Control minimal, VISION LARGE/LIVRAISON ÉTROITE, priorités P1-P6 avec distinction cœur/Guardian avancé, sauvegarde/restauration testée, dépôt = vérité opérationnelle, License Contract V2, règle de continuité permanente). La répartition Claude/ChatGPT **n'y figure pas** (règle opérationnelle, voir plus bas).
- `app/docs/V2_CHARTER.md` : § Modèle de déploiement remplacé par la formulation validée (VPS central multi-écoles, school_id/school_code, memberships avec résolution automatique ou sélection tenant-aware, serveur seul arbitre du contexte école) ; § Frontières de sécurité : « Supabase » → « PostgreSQL, ses rôles » ; note d'historique ajoutée (rien de supprimé).
- `docs/PROJECT_CONTEXT.md` : puce « sans Docker » marquée OBSOLÈTE (historique conservé).
- Roadmap 13/09 : bannière de statut ajoutée (points Docker et VPS remplacés, lot 12 à traduire en exploitation Git→Coolify→Docker→VPS ; aucune tâche modifiée).
- Spec G0 (`docs/superpowers/specs/2026-09-14-schoolsafe-license-service-entitlements-design.md`) : finalisée et validée — architecture cible, identités (school_id/school_code, memberships), License Contract V2, migration V1, cycle de vie école, stockage R2 par `school_id`, ordre d'exécution Étape 0→6.
- `ops/deployment/README.md` : créé — chaîne de déploiement officielle et les 10 règles opérationnelles (anciennes méthodes marquées REMPLACÉES, pas supprimées).
- `docs/CURRENT_HANDOFF.md` : ce document, historique des lots précédents conservé ci-dessous.

## CE QUI A ÉTÉ MODIFIÉ vs NON MODIFIÉ

Modifié : uniquement des documents (décisions, charte, contexte, roadmap, spec G0, handoff, ops/deployment/README.md). **Aucun code modifié** — signature et vérification de licence en production inchangées, conformément à la consigne.

## TESTS / VÉRIFICATIONS EXÉCUTÉES

- Recherche des contradictions actives : occurrences de « sans Docker », « propre VPS », « 1 école = 1 VPS » toutes traitées (statut obsolète ou bannière).
- `git diff` relu avant commit (documents uniquement, diff sémantique lisible).
- Fins de ligne des deux fichiers serveur pré-existants isolées dans un commit séparé.

## PROBLÈMES RENCONTRÉS

- Aucun bloquant. Deux fichiers serveur (`server/src/financenative/routes.ts`, `server/src/studentsnative/routes.ts`) portaient des changements de fin de ligne antérieurs à l'Étape 0 : isolés dans un commit séparé pour ne pas polluer le diff documentaire.

## DÉCISIONS PRISES DANS CE LOT

Voir les 15 lignes du 14/09 dans `docs/DECISIONS.md` (toutes « Validées »), la spec G0 validée et `ops/deployment/README.md`.

## COMMIT(S)

- COMMIT A (documentation / Étape 0) : `docs(architecture): etape 0 coherence documentaire et contrat g0` — SHA inscrit dans `git log` (résumé en fin de message agent).
- COMMIT B (fins de ligne) : `chore: normalize line endings` — SHA inscrit dans `git log`.

## CE QUI RESTE À FAIRE / PROCHAINE ÉTAPE EXACTE

**Phase A — Tâche 1 : scanner du contrat de permissions** (`scripts/permission-contract.mjs` + tests + gate `check:permissions`), selon le plan canonique `docs/superpowers/plans/2026-09-13-schoolsafe-canonical-access-law.md`. Compatibilité vérifiée : le plan est compatible avec le modèle multi-écoles et le `school_id` UUID — la tâche 1 est un scanner statique de littéraux de permissions (sans rapport avec le tenant) ; les tâches 2-3 ciblent déjà des RPC scopés par école via `iam.require_access`. **Exécuter le plan tel quel, sans le réécrire.**

Ne commencer **aucune** fonctionnalité Écosystème, JASPE, Watch ou Control avancé entre les deux.

## RISQUES / POINTS À SURVEILLER

- Les trois failles prioritaires restent ouvertes : contexte de requête contourné dans 5 services natifs (Phase A), liste d'impression sans filtre `school_id` (P2), licence non appliquée par hook backend (P3).
- Supabase encore présent dans le dépôt (SDK, scripts, tests) : retrait uniquement via inventaire → migration → tests.
- Points résiduels G0 §11 : canal de provisionnement, supervision de l'activation, catalogue permission→service, fournisseur OTP.

## RÈGLE OPÉRATIONNELLE ACTUELLE (répartition des agents)

**Règle de vérification avant travail (ordre permanent, 14/09)** : avant toute
action sur un point, **vérifier d'abord ce qui est déjà fait sur ce point**
(preuve fraîche dans le code/tests, pas une supposition ni un document),
**corriger si nécessaire**, et **travailler ensuite**. Chaque lot/tâche
commence par ce point de vérification, dans l'ordre verrouillé.

```text
Claude :
- frontend ;
- serveur applicatif ;
- backend ;
- corrections du dépôt.

ChatGPT :
- architecture/exploitation VPS ;
- Docker/Coolify ;
- procédures ops ;
- cohérence infrastructure.

Toute modification d'exploitation produisant une configuration, un script
ou une procédure durable doit être répercutée dans le dépôt (dossier ops/).

Les zones de propriété doivent être explicites afin d'éviter que deux
agents modifient simultanément les mêmes fichiers critiques
(app/app.js, app/index.html : propriété exclusive Claude par lot).
```

## TRAÇABILITÉ

> Artifact Server indisponible dans l'environnement d'exécution de ce lot ; traçabilité assurée par les artefacts versionnés du dépôt (Git, DECISIONS.md, spec G0, handoff, historique des commits).

## Source de vérité

- Dépôt : `https://github.com/medygoo/schoolsafe-v` — branche `main`. Vérifier `git status`, `HEAD` vs `origin/main` avant reprise.
- Le serveur local correct sert le dépôt courant sur `http://127.0.0.1:4176/` ; le port `4175` sert une ancienne copie distincte.

---

# Historique — lots précédents (avant le 2026-09-14, conservé)

## Lots 2026-09-13 — fondations visuelles, baseline, JASPE

- **Lot 0A** : catalogue 60 permissions restauré, contrôle de migrations (6 sets/21 unités), hashes SQL LF/CRLF normalisés, `/config.setup_available` token-réel, CI verte (51 fichiers, 276 tests serveur), audit NPM 0 vulnérabilité. Rapport : `docs/BASELINE_REPORT.md`.
- **Lot 1** : contrat `test:visual-system`, tokens `--ss-*`, retrait glassmorphism/halos/`!important`, bandeau démo neutre, dashboards vérifiés 1440/390 px. Plan : `2026-09-13-schoolsafe-responsive-visual-system.md` (tâches 1-2).
- **Contrôleur physique JASPE** : neuf intentions fermées, traduction déterministe v12/WebP, arbitrage priorité, cycle de vie indépendant `.auth-screen`, raccordement connexion par intentions, repli `SPEAKING`.
- **Suppression écran `guardian`** : splash → connexion directe, styles retirés, permissions métier `school.guardian` conservées.
- Vérifications d'alors : `npm run ci` PASS, 57 tests statiques SQL PASS, `test:jaspe-physical` PASS, `qa-safe-assistant-access` PASS, `test:no-guardian-screen` PASS, navigateur 4176 (JASPE v12 visible, intentions observées), 5 profils sans débordement.
- Limites d'alors (toujours valides sauf mention contraire) : postinstall Esbuild à autoriser avant release ; upload logo à durcir (octets/signatures) ; assistant flottant non activé ; voix/GLM non implémentés ; `qa-pwa.cjs` s'arrête sur son ancien scénario hors session.
- Ancienne « Prochaine action » du 13/09 (remplacée par l'ordre P1-P6 du 14/09) : faire valider puis exécuter le plan canonique Access Law, puis la tâche 3 du plan visuel — cette séquence est maintenant intégrée aux étapes du 14/09.
