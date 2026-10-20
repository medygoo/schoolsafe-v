# 02 — ARCHITECTURE ET CARTOGRAPHIE DU CODE

## 1. Dépôts et emplacements

| Élément | Valeur |
|---|---|
| Dépôt officiel | `https://github.com/medygoo/schoolsafe-v.git` (`origin`) |
| Dépôt secondaire configuré | `https://github.com/medygoo/schoolsafePRODELIFIN.git` (`prodelifin`) |
| Branche | `main` |
| Chemin Windows du projet | `C:\Users\PC\SchoolSafeCodex\SCHOOLSAFE_CODEX_LOCAL_SNAPSHOT_V1\workspace` |
| **Racine réelle de l'application Rosario** | `app/modules/codex-ui-v1/extensions-test-runtime/` |
| Chemin VPS (app PHP) | conteneur `schoolsafe-rosario`, `/var/www/html` |
| Fichiers d'exploitation VPS | `/opt/schoolsafe-rosario/` (`config.inc.php`, `app.env`) |
| VPS | `prodeli-hub-01` (Hostinger), réseau Docker `schoolsafe-rosario-net` |

⚠️ Les fichiers `index.php`, `Side.php`, `Bottom.php`, `Warehouse.php`, `Modules.php` ne sont **pas** à la racine de `app/` : ils sont dans `extensions-test-runtime/`, qui est le document root du conteneur.

## 2. Arborescence utile

```
workspace/
├─ app/
│  ├─ modules/codex-ui-v1/
│  │  ├─ extensions-test-runtime/     ← document root PHP (Rosario + SchoolSafe)
│  │  │  ├─ index.php                 connexion (route /connexion)
│  │  │  ├─ Side.php / Bottom.php     chrome natif (menu, en-tête, pied)
│  │  │  ├─ Warehouse.php             rendu de page Rosario
│  │  │  ├─ Modules.php               routeur de modules natifs
│  │  │  ├─ .htaccess                 routes SchoolSafe
│  │  │  ├─ ProgramFunctions/         logique SchoolSafe (*.fnc.php)
│  │  │  ├─ schoolsafe-remediation/   migrations SQL 001→037
│  │  │  └─ assets/themes/            thèmes (SchoolSafeCodex, SchoolSafe)
│  │  └─ card-data-bridge/            ponts Node (cartes, scans, notifications)
│  └─ qa-*.cjs (73)                   suites QA
├─ server/                            API TypeScript (auth, sessions, accès) + 79 tests
├─ worker-jaspe/                      Worker Cloudflare (JASPE + R2)
├─ database/                          schémas SQL versionnés par domaine
├─ docs/                              handoff, décisions, audits
├─ ops/deployment/                    compose, Caddyfile, runbooks
└─ SCHOOLSAFE_PASSATION_COMPLETE/     ce dossier
```

## 3. RosarioSIS : ce qu'on garde, ce qu'on étend

- **On ne reconstruit pas Rosario.** Le natif fournit `staff`, `students`, `schools`, `config`, les sessions, `Warehouse()`, `DBGet/DBQuery/DBInsert`, les modules métier.
- **SchoolSafe ajoute** des fonctions (`ProgramFunctions/*.fnc.php`), des écrans racine, des migrations additives et des routes propres.
- **Règle d'or** : toute nouveauté est *additive*. Aucune table native modifiée, aucune logique native dupliquée.

## 4. Routes publiques (`.htaccess` de `extensions-test-runtime`)

| Route | Cible | Rôle |
|---|---|---|
| `/connexion` | `index.php` | Écran et moteur d'authentification |
| `/demarrer` | `demarrer.php` | Demande d'ouverture + code d'autorisation |
| `/ecoles/<id>/utilisateurs` | `ecole-utilisateurs.php` | Comptes de l'établissement |
| `/ecoles/<id>/profils` | `ecole-profils.php` | Profils et portée |
| `/plateforme/prodeli` | `plateforme-prodeli.php` → `admin-console.php` | Console générale PRODELI |
| `/plateforme/prodeli/sms` | `plateforme-prodeli-sms.php` | 4 lignes SMS, quotas, tests |
| `/plateforme/prodeli/whatsapp` | `plateforme-prodeli-whatsapp.php` | WAHA principal + Evolution secours |
| `/compte/recuperation` | `compte-recuperation.php` | Récupération courriel / OTP |

Les anciens écrans (`Modules.php`, `PasswordReset.php`, `index.php?modfunc=…`) restent accessibles : aucune URL historique n'est cassée.

## 5. Fichiers PHP porteurs de logique (dans `ProgramFunctions/`)

| Fichier | Responsabilité |
|---|---|
| `SchoolSafeWhatsapp.fnc.php` | Transport WhatsApp : consentement, confidentialité, idempotence, WAHA puis Evolution sur échec confirmé |
| `SchoolSafeSms.fnc.php` | 4 lignes SMS, quotas, historique, normalisation `+243` |
| `SchoolSafeNotify.fnc.php` | Événements et distributions de notification (socle U0) |
| `SchoolSafeAccountState.fnc.php` | États de compte, suspension, époque de session |
| `SchoolSafeSchoolScope.fnc.php` | Périmètre école, garde globale PRODELI |
| `SchoolSafeRecovery.fnc.php` | Récupération OTP, anti-abus |
| `SchoolSafeBrevo.fnc.php` | Envoi email transactionnel |
| `SchoolSafeWebPush.fnc.php` | Push navigateur |
| `SchoolSafeJaspeActions.fnc.php` | Actions JASPE |

Hors `ProgramFunctions/` : `admin-console.php` (console PRODELI), `schoolsafe-notify-dispatch.php` (dispatcher CLI, canal `whatsapp` inclus), `demarrer.php`, `ecole-utilisateurs.php`, `ecole-profils.php`, `compte-recuperation.php`.

## 6. Flux principaux

**Connexion**
```
Navigateur → /connexion (index.php)
   → match_password() natif Rosario
   → SchoolSafeAccountState (état / époque)
   → session Rosario → Warehouse('header') → Side.php
```

**Notification → WhatsApp**
```
Événement Studio (schoolsafe_notify_events)
  → distribution (schoolsafe_notify_deliveries, canal 'whatsapp')
  → schoolsafe-notify-dispatch.php --once --channel=whatsapp
  → consentement (schoolsafe_wa_consents) + session active (schoolsafe_wa_sessions)
  → POST {WAHA_BASE}/api/sendText
  → si échec CONFIRMÉ : POST {EVOLUTION_BASE}/message/sendText/<session>
  → schoolsafe_wa_outbox (statut, provider_msg_id) + schoolsafe_wa_audit
```

**Interface**
```
index.php → Warehouse('header') → Side.php (menu, tiroir mobile)
          → thème actif assets/themes/SchoolSafeCodex/ (importe ../SchoolSafe/stylesheet.css)
          → modules/<Module>/<Écran>.php (natif) ou écran racine SchoolSafe
```

## 7. Fichiers locaux NON publiés (20/10/2026)

Modifié (suivi) :
- `…/extensions-test-runtime/ProgramFunctions/SchoolSafeWhatsapp.fnc.php` — correctif `provider_msg_id`.

Non suivis :
- `ProgramFunctions/` : `SchoolSafeCardVerify.fnc.php`, `SchoolSafeEntryScan.fnc.php`, `SchoolSafeFeeScan.fnc.php`, `SchoolSafePickupManage.fnc.php`, `SchoolSafePickupScan.fnc.php`
- `schoolsafe-remediation/` : `020-school-cards.sql`, `021-entry-records.sql`, `022-pickup-guardians.sql`, `023-fee-consults.sql`
- `schoolsafe-remediation/tests/` : `card-verify-a1.php`, `card-verify-a1-db.php`, `scan-entry.php`, `scan-entry-db.php`, `scan-pickup.php`, `scan-pickup-db.php`, `fee-scan.php`
- `app/` : `qa-scan-entry.cjs`, `qa-scan-pickup.cjs`, `qa-scan-verify-a1.cjs`
- `app/qa-output/` : 6 captures PNG (artefacts de tests)

**Ces fichiers ne doivent être ni écrasés, ni supprimés** : ils forment le lot cartes/scans en cours.

## 8. Serveur TypeScript et Worker

- `server/src/` : API native (`authnative`, `sessionnative`, `access`, `cards`…), `server/tests/*.test.ts` (79 fichiers), Vitest.
- `worker-jaspe/` : Worker Cloudflare (`wrangler.toml`), JASPE + R2 ; secrets via `.dev.vars` / Cloudflare, jamais dans Git.
- Conteneur `schoolsafe-app` (image `schoolsafe-app:20261018d`) : API, `healthy`.
