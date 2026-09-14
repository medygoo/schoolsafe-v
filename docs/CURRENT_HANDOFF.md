# Handoff courant SchoolSafe

Dernière mise à jour : 14 septembre 2026 — Étape 0 (cohérence documentaire).

## ÉTAT ACTUEL

Architecture verrouillée le 14/09 : **1 plateforme SchoolSafe = plusieurs écoles isolées** sur un VPS central (Hostinger, Docker + Coolify), SchoolSafe Control co-hébergé mais logiquement séparé (séparation par privilèges, pas de conteneur par école ; isolation multi-écoles = `school_id` + PostgreSQL + ACCESS_LAW). License Contract V2 validé (spec G0). Déploiement officiel : **Git → Coolify → Docker → VPS** (règles dans `DECISIONS.md` du 14/09 et `ops/deployment/README.md`). Règle de conduite : **VISION LARGE, LIVRAISON ÉTROITE** — aucune nouvelle grande fonctionnalité tant que le parcours quotidien de la première école (Le Sage) n'est pas stable, sécurisé, sauvegardable et restaurable.

```
ÉTAT ACTUEL            Étape 0 terminée et commitée
DERNIÈRE ÉTAPE         Étape 0 — cohérence documentaire (décisions, charte, G0, handoff)
ÉTAPE EN COURS         aucune (transition)
PROCHAINE ÉTAPE        Phase A — Tâche 1 : scanner du contrat de permissions
ORDRE À SUIVRE         P1 Phase A · P2 fuites inter-écoles · P3 enforcement licence
                       · P4 cœur Le Sage (+ personnes autorisées, photo sortie,
                       validation humaine) · P5 sauvegarde/restauration · P6 extensions
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
