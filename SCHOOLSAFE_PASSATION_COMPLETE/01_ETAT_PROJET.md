# 01 — ÉTAT EXACT DU PROJET

Statuts utilisés, sans ambiguïté :

- **LIVE** : tourne en production sur `app.shoolsafe.tech` et a été vérifié.
- **DÉPLOYÉ-NON-VÉRIFIÉ** : présent dans l'image déployée, mais pas re-testé dans cette passation.
- **PRÊT-NON-DÉPLOYÉ** : terminé et testé, absent de l'image en ligne.
- **EN COURS** : partiellement fait.
- **SIMULATION** : validé uniquement avec `WHATSAPP_SIMULATE='Y'` ou données fictives en environnement isolé.
- **BLOQUÉ** : dépend d'une décision ou d'un accès.

## Tableau de synthèse

| Domaine / lot | Statut | Preuve ou limite |
|---|---|---|
| Connexion (`/connexion`, écran `login-blue`) | LIVE | `GET /connexion` → 200, `<title>SchoolSafe</title>`, 8 329 o |
| L1 — état de compte + révocation de session | LIVE | migration `014-account-state.sql` ; `qa-l1-account-state.cjs` |
| L2 — parcours d'ouverture (`/demarrer`) | LIVE | règle `.htaccess` + `demarrer.php` ; `qa-l2-ouverture.cjs`, `qa-l2-ouverture-viewports.cjs` |
| L3 — comptes et profils par école | LIVE | routes `/ecoles/<id>/utilisateurs`, `/ecoles/<id>/profils` ; `qa-l3-comptes.cjs`, `qa-l3-1-profils-ecole.cjs` |
| L3.1 — profils / portée école | LIVE | migration `034-profile-school-scope.sql` |
| L4 — récupération de compte | LIVE | `/compte/recuperation` ; `qa-l4-recuperation.cjs` |
| SMS — 4 lignes + quotas | LIVE (chaîne) / SIMULATION (envois) | `qa-sms.cjs` ; migration `036-sms-lines.sql` ; aucun envoi réel d'école |
| WhatsApp — connexion des 2 numéros | LIVE, vérifié le 20/10/2026 | WAHA `WORKING`, Evolution `open`, envois réels reçus des deux côtés |
| WhatsApp — envoi applicatif (SchoolSafe → moteur) | LIVE, vérifié | WAHA accepté, bascule Evolution OK, anti-doublon OK |
| WhatsApp — diffusion automatique parents/écoles | **VOLONTAIREMENT INACTIVE** | sessions `status='disabled'` ; dispatcher bloquerait en `NO_WA_SESSION` |
| U0 — Studio notifications (socle) | DÉPLOYÉ-NON-VÉRIFIÉ | commit `60cbd1e` « U0-U5 … isole GREEN » |
| U1–U5 — canaux feed/email/push | DÉPLOYÉ-NON-VÉRIFIÉ | même commit ; `tests/notify-u0.php` (11 cas) existe |
| Email — Brevo / SMTP STARTTLS | DÉPLOYÉ-NON-VÉRIFIÉ | plugin `Email_SMTP`, config par environnement |
| Push web | DÉPLOYÉ-NON-VÉRIFIÉ | `SchoolSafeWebPush.fnc.php`, `notification-push-sw.js` |
| Navigation / menu (tiroir, thème `SchoolSafeCodex`) | EN COURS | thème actif documenté ; chrome natif encore visible après connexion |
| JASPE 2.5D | DÉPLOYÉ-NON-VÉRIFIÉ | `modules/SchoolSafe_Jaspe/**`, 10+ scripts `qa-jaspe-*` |
| Cartes / scans (A1, ENTRY, PICKUP, FEES) | **PUBLIÉ-NON-DÉPLOYÉ** (20/10/2026) | commit `8f81b8f` : 5 fonctions PHP, migrations `020`–`023`, 7 tests, `qa-scan-*` ; 7 suites GREEN en isolé (`09` §11) |
| Finance (caisse, reçus, exonérations) | DÉPLOYÉ-NON-VÉRIFIÉ | ~15 scripts `qa-finance-*` |
| Outils P0 (Semgrep, Gitleaks, Trivy, Lighthouse, axe, ZAP, Bugsink) | **NON COMMENCÉ** | aucun binaire, aucune configuration (`07`) |

## Ce qui est explicitement NON démontré

- Aucun envoi **réel** vers un parent ou une école n'a été effectué : interdit avant validation.
- Aucun déclenchement réel de notification SchoolSafe → WhatsApp n'a été exécuté.
- Le rendu visuel de l'ensemble des écrans après connexion n'a pas été audité ; seul l'écran de connexion a été vérifié en direct.
- Les lots U1–U5, finance et JASPE sont dans l'image mais n'ont pas été re-testés ici.
- Le lot cartes/scans n'a **pas** été testé via HTTP en session (secret HMAC absent du conteneur L1) : seules les 7 suites CLI ont été jouées.

## Écarts local ↔ GitHub ↔ VPS

| Élément | État |
|---|---|
| `main` local | `8f81b8f` |
| `origin/main` | `8f81b8f` (identique) |
| `prodelifin/main` | `5a946f3` (l'image déployée ; deux commits de retard) |
| Image en ligne | `schoolsafe-rosario:20261020-gofinal` |
| Code WhatsApp dans le conteneur | SHA-256 identique à `HEAD` pour ce fichier |
| Modifications locales | 1 fichier modifié (correctif `provider_msg_id`) + 6 captures QA non suivies — **non publiés** |

## Ce qui reste à faire avant de dire « production validée »

1. Publier le correctif `provider_msg_id` (sinon la réconciliation WhatsApp écrit `"Array"`).
2. Décider de l'activation des sessions WhatsApp applicatives.
3. Vérifier visuellement les écrans post-connexion (`09` §1).
4. Installer et lancer les outils P0, puis consigner leurs résultats.
