# 05 — FONCTIONNALITÉS

Chaque ligne donne le point d'entrée réel dans l'interface, la logique serveur et le statut vérifié. **Aucune fonctionnalité validée en simulation n'est présentée comme opérationnelle en production.**

## A. Authentification et comptes

| Fonctionnalité | Entrée UI | Logique | Statut |
|---|---|---|---|
| Connexion | `/connexion` | `extensions-test-runtime/index.php` + `match_password()` natif | LIVE (200, titre `SchoolSafe`) |
| Changement de mot de passe | `index.php?modfunc=first-login` | `FirstLogin.fnc.php` | DÉPLOYÉ-NON-VÉRIFIÉ |
| Récupération courriel | `/compte/recuperation` | `compte-recuperation.php` → `PasswordReset.php` | LIVE (chaîne) |
| Récupération téléphone (OTP) | `/compte/recuperation` | `SchoolSafeRecovery.fnc.php`, migration `035` | DÉPLOYÉ-NON-VÉRIFIÉ |
| Suspension / lecture seule | Console PRODELI | `SchoolSafeAccountState.fnc.php`, migration `014` | LIVE (chaîne) |
| Révocation de session | Console PRODELI | `session_epoch` | LIVE (chaîne) |

**Garantie** : aucune seconde identité. Mêmes sessions Rosario, mêmes tables `staff`, mêmes gardes. Aucune route Supabase n'est réactivée.

## B. Ouverture d'établissement (L2)

- Entrée : `/demarrer` → `demarrer.php`.
- Logique : demande (`schoolsafe_opening_requests`), code d'autorisation (`032-opening-codes.sql`), création contrôlée par la console centrale.
- Statut : LIVE (route + migration) ; parcours complet à re-tester avec un vrai dossier.

## C. Comptes et profils (L3 / L3.1)

- Entrées : `/ecoles/<id>/utilisateurs`, `/ecoles/<id>/utilisateurs/nouveau`, `/ecoles/<id>/utilisateurs/<uid>`, `/ecoles/<id>/profils`.
- Logique : `ecole-utilisateurs.php`, `ecole-profils.php`, `SchoolSafeSchoolScope.fnc.php`, migration `034`.
- Règle : un administrateur scolaire agit **uniquement** dans son périmètre ; `SCHOOLS NULL` reste un privilège global.
- Statut : LIVE ; QA `qa-l3-comptes.cjs`, `qa-l3-1-profils-ecole.cjs`, `qa-l3-1-viewports.cjs`.

## D. SMS (4 lignes)

- Entrée : `/plateforme/prodeli/sms`.
- Logique : `SchoolSafeSms.fnc.php`, migration `036-sms-lines.sql` (lignes, quotas, historique), normalisation `+243`.
- Statut : **chaîne LIVE, envois réels d'école NON testés** (aucun envoi de masse autorisé).

## E. WhatsApp (WAHA + Evolution)

- Entrée : `/plateforme/prodeli/whatsapp`.
- Logique : `SchoolSafeWhatsapp.fnc.php`, migration `037-whatsapp.sql`.
- Connectivité vérifiée le 20/10/2026 :
  - WAHA `schoolsafe-waha-1` → `WORKING`, `243967552380@c.us`
  - Evolution `schoolsafe-evolution-1` → `open`, `243828432689@s.whatsapp.net`
- Envois réels croisés : reçus des deux côtés, un seul identifiant par message (aucun doublon).
- Bascule : WAHA 503 → `evolution-fallback` ; WAHA 400 → échec explicite **sans** bascule.
- **Diffusion automatique parents/écoles : désactivée** (`schoolsafe_wa_sessions.status='disabled'`).

## F. Studio des notifications (U0–U5)

- Entrée : menu SchoolSafe (Studio / Notifications).
- Logique : `SchoolSafeNotify.fnc.php`, `schoolsafe-notify-dispatch.php`, migrations `024`–`026`, `030`.
- Canaux : `feed`, `email`, `push`, `whatsapp`.
- Types éligibles WhatsApp : `security.entry`, `student.pickup`, `payment.receipt`, `school.announcement`, `remediation.update`, `attendance.closure`, `absence.detected`, `case.updated`, `security.p1`.
- Statut : DÉPLOYÉ-NON-VÉRIFIÉ ; déclenchement réel WhatsApp **non exécuté** (destinataires réels).

## G. Email (Brevo / SMTP) et Push

- Email : plugin `Email_SMTP`, `SchoolSafeBrevo.fnc.php`, STARTTLS 587, expéditeur `securite@mail.shoolsafe.tech`.
- Push : `SchoolSafeWebPush.fnc.php`, `notification-push-sw.js`, `notification-push-subscription.php`.
- Statut : DÉPLOYÉ-NON-VÉRIFIÉ (aucun envoi de contrôle effectué ici).

## H. JASPE 2.5D

- Entrée : dashboard, module `SchoolSafe_Jaspe`.
- Logique : `modules/SchoolSafe_Jaspe/jaspe2d/**`, `assets/jaspe2d/jaspe2d-manifest.json`, worker `worker-jaspe`.
- Statut : DÉPLOYÉ-NON-VÉRIFIÉ ; 10 scripts `qa-jaspe-*` disponibles.

## I. Cartes, entrées, sorties, frais (A1 / ENTRY / PICKUP / FEES)

- Logique : `SchoolSafeCardVerify`, `SchoolSafeEntryScan`, `SchoolSafePickupScan`, `SchoolSafeFeeScan`, `SchoolSafePickupManage` + migrations `020`–`023`.
- Interface : `card-bridge-*`, `scanner-native.php`, `fee-control.php`, `arrival-attendance.php`.
- Statut : **PUBLIÉ-NON-DÉPLOYÉ** depuis `8f81b8f` (20/10/2026) ; les migrations `020`–`023` ne sont pas encore en production (`09` §11).

## J. Finance

- Écrans : caisse, reçus, exonérations, campagnes, rapports.
- Statut : DÉPLOYÉ-NON-VÉRIFIÉ ; ~15 scripts `qa-finance-*`.

## K. Navigation et habillage

- Entrée : `extensions-test-runtime/Side.php` (menu, tiroir mobile `data-ss-mobile-panel`).
- Thème actif : **`SchoolSafeCodex`**, qui importe `../SchoolSafe/stylesheet.css`.
- État : EN COURS — le chrome natif reste visible après connexion (`09` §1). **Ne pas modifier l'interface dans le cadre de cette passation.**

## L. Console PRODELI

- Entrée : `/plateforme/prodeli` → `admin-console.php`.
- Portée : requête uniquement `staff`, `schools`, `schoolsafe_account_state` — **jamais** les dossiers élèves, notes ou conversations.
- Statut : LIVE (écran accessible, garde globale).
