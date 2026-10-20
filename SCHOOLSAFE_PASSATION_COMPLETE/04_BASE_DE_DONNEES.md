# 04 — BASE DE DONNÉES

## 1. Instance et nom réel

| Élément | Valeur constatée |
|---|---|
| Conteneur | `schoolsafe-rosario-db` |
| Image | `postgres:17.11-bookworm` |
| Hôte vu de l'app | `SCHOOLSAFE_DB_HOST=schoolsafe-rosario-db`, port `5432` |
| **Base utilisée par l'application** | **`rosario_addons_test`** (via `POSTGRES_DB`) |
| Bases présentes | `postgres`, `schoolsafe`, `schoolsafe_test_rosario`, `rosario_addons_test` |
| Tables schéma `public` | 147 |
| Tables `schoolsafe*` | 45 |

⚠️ **Piège majeur** : la base nommée `schoolsafe` n'est **pas** celle du site. Toujours résoudre dynamiquement :

```bash
DB=$(docker exec schoolsafe-rosario printenv POSTGRES_DB | tr -d '\r\n')
docker exec -i schoolsafe-rosario-db psql -U postgres -d "$DB" -c '\dt schoolsafe_*'
```

## 2. Deux familles de schémas

**Natif Rosario (ne pas modifier)** : `staff`, `students`, `schools`, `config`, `program_config`, `access_log`, `school_years`, `marking_periods`, `courses`, `schedule`, `grades`, `attendance`…

**Additif SchoolSafe (créé par migrations)** :

| Table | Rôle |
|---|---|
| `schoolsafe_account_state` | État par personne × école : `active`, `readonly`, `suspended`, `expired` + `session_epoch` |
| `schoolsafe_account_audit` | Journal des changements d'état (qui, quand, pourquoi) |
| `schoolsafe_notify_events` | Événements du Studio (U0) |
| `schoolsafe_notify_deliveries` | Distribution par canal (`feed`, `email`, `push`, `whatsapp`) |
| `schoolsafe_notify_migrations` | Registre des migrations de notification (nom + SHA-256) |
| `schoolsafe_wa_sessions` | Une session par moteur (`waha`, `evolution`) : nom, statut, état de connexion |
| `schoolsafe_wa_consents` | Consentement explicite (école, destinataire, numéro) |
| `schoolsafe_wa_outbox` | File d'envoi : idempotence, statut, `provider_msg_id` |
| `schoolsafe_wa_audit` | Journal des actions WhatsApp |
| `schoolsafe_sms_lines` + quotas | 4 lignes SMS configurables, quotas, historique |
| `schoolsafe_opening_*` | Demandes d'ouverture et codes d'autorisation |

## 3. Isolation multi-écoles

- Toute donnée métier porte `school_id`.
- `staff.SCHOOLS` : `NULL` ou vide = **toutes les écoles** = privilège global réservé (PRODELI). Un compte ordinaire ne l'obtient jamais implicitement.
- Gardes applicatives (`SchoolSafeGlobalAdmin`, `SchoolSafeSchoolAllowed`, `SchoolSafeSchoolScopedProfileRequiresSchool`) = première barrière ; RLS PostgreSQL = seconde sur les schémas `database/`.
- **Jamais** un VPS ni un conteneur par école : un seul déploiement central, isolation par données.

## 4. Migrations

- **37 fichiers** dans `app/modules/codex-ui-v1/extensions-test-runtime/schoolsafe-remediation/`, de `001-native.sql` à `037-whatsapp.sql`.
- Toutes **additives et idempotentes** (`CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`).
- Les dernières : `030-p1-alerts.sql`, `031-delivery-status.sql`, `032-opening-codes.sql`, `033-l3-account-categories.sql`, `034-profile-school-scope.sql`, `035-recovery-epoch-otp.sql`, `036-sms-lines.sql`, `037-whatsapp.sql`.
- Migrations **non publiées** (lot cartes/scans) : `020-school-cards.sql`, `021-entry-records.sql`, `022-pickup-guardians.sql`, `023-fee-consults.sql`.
- Vérification :

```bash
npm run check:migration-versions     # lecture
npm run ci                           # vérifie aussi que tout est installable
```

- Registre applicatif : `schoolsafe_notify_migrations` (nom + SHA-256) ; un rejeu met à jour le SHA sans dupliquer.

## 5. Appliquer une migration (procédure sûre)

```bash
# 0. Sauvegarde AVANT (03 §7) + autorisation du propriétaire
DB=$(docker exec schoolsafe-rosario printenv POSTGRES_DB | tr -d '\r\n')
docker exec -i schoolsafe-rosario-db psql -U postgres -d "$DB" -v ON_ERROR_STOP=1 \
  < app/modules/codex-ui-v1/extensions-test-runtime/schoolsafe-remediation/<fichier>.sql
```

Puis vérifier les objets créés et **rejouer** la migration une seconde fois pour confirmer l'idempotence.

## 6. Sauvegarde / restauration

- Sauvegarde quotidienne automatique à 02:30 (constatée dans `docs/CURRENT_HANDOFF.md`).
- Sauvegarde manuelle avant migration : voir `03` §7.
- Dernier dump cité dans le handoff : `schoolsafe-pre-20261019-ea5d2ce.dump` (408 Ko).

## 7. Interdits

- `DROP DATABASE`, `TRUNCATE` sur une table réelle, `UPDATE` massif sans `WHERE`.
- `ALTER TABLE` direct sur une table native Rosario (passer par une migration additive).
- Exposer PostgreSQL publiquement (le port n'est pas publié).
- Se connecter en superutilisateur applicatif.
- Supposer le nom de la base : toujours lire `POSTGRES_DB`.
