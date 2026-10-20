# 07 — OUTILS P0

**Constat vérifié le 20/10/2026 : aucun outil P0 de sécurité ou de qualité n'est installé.** Ce document distingue ce qui **existe déjà** de ce qui **reste à installer**. Rien n'a été installé pendant cette passation.

## 1. Ce qui existe réellement

| Outil | Version | Emplacement | Configuration | Commande |
|---|---|---|---|---|
| Playwright | **1.62.1** | `node_modules/playwright`, `@playwright/test` | `playwright.config.*`, `tests/qa/e2e`, `app/qa-*.cjs` | `npx playwright test` / `npm run test:qa` |
| Node.js | **v24.19.0** | machine locale | — | — |
| npm | **11.17.0** | machine locale | — | — |
| Vitest | via `server/` | `server/tests/*.test.ts` (79 fichiers) | config Vitest du workspace serveur | `npm test` |
| psql | dans `schoolsafe-rosario-db` | VPS | — | `docker exec -i schoolsafe-rosario-db psql` |

Playwright est le **seul** outil P0 déjà opérationnel.

## 2. Ce qui est absent (aucun binaire, aucun fichier de configuration)

| Outil | Attendu | État constaté | Commande proposée (non exécutée) |
|---|---|---|---|
| Semgrep | analyse statique | **absent** (`command -v` vide, aucun `.semgrep.yml`) | `pipx install semgrep` puis `semgrep --config auto app server` |
| Gitleaks | détection de secrets | **absent** (aucun `.gitleaks.toml`) | `gitleaks detect --source . --no-git` |
| Trivy | vulnérabilités images | **absent** | `trivy image schoolsafe-rosario:20261020-gofinal` |
| Bugsink | suivi d'erreurs | **absent** (aucune référence dans le code) | à provisionner (auto-hébergé ou SaaS) |
| Lighthouse CI | performance web | **absent** (aucun `lighthouserc`) | `npx @lhci/cli autorun` |
| axe-core | accessibilité | **absent** (absent des dépendances, aucune référence `axe-core` dans le dépôt) | `npm i -D @axe-core/playwright` |
| OWASP ZAP | scan dynamique | **absent** | `docker run --rm -t ghcr.io/zaproxy/zaproxy:stable zap-baseline.py -t https://app.shoolsafe.tech` |

Le lot P0 est donc **non commencé**, pas « partiellement fait ».

## 3. Ce que Playwright couvre déjà (et ne couvre pas)

**Couvre** : parcours fonctionnels (connexion, ouverture, comptes, profils, récupération), rendu multi-viewports, JASPE, finance, scans, absence d'écran interdit.

**Ne couvre pas** : sécurité statique, secrets dans l'historique Git, vulnérabilités d'images, accessibilité automatisée, performance Lighthouse, scan dynamique d'attaques.

## 4. Ordre recommandé d'installation (à valider par le propriétaire)

1. **Gitleaks** — le plus urgent : vérifier l'historique Git avant toute publication.
2. **Semgrep** — analyse statique PHP/TS, règles `auto` puis règles maison.
3. **Trivy** — scan des deux images déployées.
4. **axe-core** — greffé sur les suites Playwright existantes (coût faible, gain immédiat).
5. **Lighthouse CI** — après stabilisation de l'interface.
6. **Bugsink** — nécessite un service et une DSN ; à cadrer.
7. **ZAP** — en dernier, sur environnement de test, jamais en écriture sur la production.

## 5. Règles

- Ne jamais installer deux fois le même outil : vérifier `command -v` avant.
- Ne jamais committer de configuration contenant un secret (DSN, token).
- Chaque outil doit produire un rapport horodaté, consigné dans `08_TESTS_ET_PREUVES.md`.
- Un outil absent ne doit **jamais** être présenté comme « passé » : écrire « non exécuté ».
