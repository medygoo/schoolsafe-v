# 08 — TESTS ET PREUVES

## 1. Inventaire

| Famille | Volume | Emplacement | Exécuteur |
|---|---|---|---|
| Tests serveur (API/domaine) | **87 fichiers, 688 tests** | `server/tests/*.test.ts` | Vitest |
| Suites QA applicatives | **73 scripts** | `app/qa-*.cjs` | Node + Playwright |
| Tests QA structurés | `tests/qa/unit`, `tests/qa/rls`, `tests/qa/integration`, `tests/qa/e2e` | `tests/qa/**` | Vitest + Playwright |
| Tests PHP isolés | ~20 scripts | `…/schoolsafe-remediation/tests/*.php` | `php` en conteneur |
| Vérifications de cohérence | 3 | `scripts/check-migration-versions.mjs`, `check:permissions`, `verify-production-image.mjs` | Node |

## 2. Commandes exactes

```bash
cd "C:/Users/PC/SchoolSafeCodex/SCHOOLSAFE_CODEX_LOCAL_SNAPSHOT_V1/workspace"

npm test                                   # Vitest serveur (688 tests)
npm run typecheck                          # tsc --noEmit (serveur)
npm run ci                                 # migrations + permissions + installer + typecheck + test + 3 suites node --test
npm run check:migration-versions           # manifestes de migration
npm run test:qa                            # Vitest qa (unit/rls/integration) + Playwright e2e
npm run test:qa:rls                        # RLS seulement
npm run test:jaspe-physical                # contrat physique JASPE
npm run test:visual-system                 # système visuel
npm run test:login-layout                  # mise en page connexion
npm run test:portal-layout                 # mise en page portail
npm run test:no-guardian-screen            # absence de l'ancien écran interdit
```

Suite PHP isolée (conteneur, base isolée) :

```bash
docker exec schoolsafe-rosario php /var/www/html/schoolsafe-remediation/tests/notify-u0.php
```

## 3. Résultats du 20/10/2026 (rejoués pendant la passation)

| Commande | Résultat | Détail |
|---|---|---|
| `npm run check:migration-versions` | **PASS** | 12 jeux de manifestes, 43 unités ; inventaire historique préservé ; Installation v2 : 88 unités vérifiées par somme de contrôle |
| `npm test` (1re exécution) | **687/688** | 1 échec, **non reproduit** ensuite, nom du test non capturé |
| `npm test` (2e exécution) | **688/688 PASS** | 87 fichiers, 20,7 s |
| `npm test` (3e et 4e exécutions) | **688/688 PASS** | deux exécutions consécutives propres |

Conclusion : suite serveur **GREEN avec un risque de test instable** (1 échec sur 4 exécutions). S'il revient, le capturer avec `npx vitest run --reporter=verbose` avant de conclure.

## 4. Preuves WhatsApp (production, 20/10/2026)

| Preuve | Valeur |
|---|---|
| WAHA état | `WORKING`, `243967552380@c.us`, lid `100060658454708@lid` |
| Evolution état | `open`, `243828432689@s.whatsapp.net` |
| Envoi WAHA → `+243828432689` | id `3EB0A99393278A4B1825CC`, **ack 3** |
| Réception côté Evolution | même id, `fromMe=false`, jid `100060658454708@lid` |
| Envoi Evolution → `+243967552380` | id `3EB08377C593A926DD2118` |
| Réception côté WAHA | même id, `fromMe=false` |
| SchoolSafe → WAHA | `{"outbox_id":3,"status":"accepted","via":"waha"}` |
| SchoolSafe → bascule Evolution (WAHA 503) | `{"outbox_id":4,"status":"accepted","via":"evolution-fallback"}` |
| Rejeu idempotent | `{"outbox_id":3,"status":"accepted","via":"dedup"}` |
| Rejet 4xx | `{"outbox_id":5,"status":"failed","error":"WAHA_ERROR:400"}` (aucune bascule) |
| Correctif d'identifiant | `provider_msg_id = 3EB033B68AB69F1B07B8DB` (au lieu de `Array`) |

Contexte : envois applicatifs exécutés avec `purpose='test'` et `staff_id=0` (mode test hub, sans consentement requis), vers les deux numéros du propriétaire, avec activation **temporaire** des sessions puis retour immédiat à `disabled`.

## 5. Contrôle d'intégrité du code déployé

```bash
git show HEAD:…/SchoolSafeWhatsapp.fnc.php | sha256sum     # 26fea00cbbb786f6…
docker exec schoolsafe-rosario sha256sum /var/www/html/ProgramFunctions/SchoolSafeWhatsapp.fnc.php  # 26fea00cbbb786f6…
sha256sum (arbre de travail)                                # 03a706feacd8521f…  ← correctif non publié
```

## 6. Conditions pour rejouer les suites

- **QA Playwright** : un serveur local (ou une cible `QA_BASE_URL`) et des identifiants de démonstration. Captures dans `app/qa-output/`.
- **Tests serveur** : aucun service externe requis (mocks) ; PostgreSQL local non nécessaire pour `npm test`.
- **Tests PHP isolés** : conteneur `schoolsafe-rosario` + base isolée ; jamais sur la production.
- **Supabase** : les scripts `test:rls` / `supabase:*` relèvent du legacy — ne pas réactiver de route Supabase.

## 7. Erreurs connues

| Symptôme | Statut |
|---|---|
| 1 test serveur instable (1 échec sur 4 exécutions) | ouvert, non identifié |
| `Array to string conversion` dans le module WhatsApp | **corrigé** dans l'arbre de travail, non déployé |
| OPcache sert l'ancien contenu d'un PHP remplacé au même chemin | contourné en publiant sous un nouveau nom |
| Fichier PHP temporaire illisible par Apache (`0600 root`) | cause : droits ; `chown www-data` + `0400` |
