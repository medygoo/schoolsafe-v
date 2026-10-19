# SchoolSafe — Runbook de déploiement VPS (stack applicative RosarioSIS SchoolSafe)

Date : 15 octobre 2026. Cible : VPS prodeli-hub-01 (Ubuntu 24.04, Docker, Caddy hôte 2.11.4, UFW).
Ce runbook couvre la **stack applicative SchoolSafe** (web PHP + PostgreSQL + workers) telle que
prouvée en local et codifiée dans `docker-compose.yml`. Il complète, sans le remplacer,
`SCHOOLSAFE_DIRECT_VPS_REPORT.md` (déploiement du socle natif/SQL v2) et `ops/deployment/README.md`
(les 10 règles opérationnelles, toujours applicables).

## 1. Ce que déploie ce package

| Service | Rôle | Équivalent local prouvé |
|---|---|---|
| `web` | Apache/PHP 8.3 — application SchoolSafe (runtime RosarioSIS modifié) | `codex-addons-web` |
| `db` | PostgreSQL 17.11 — base de l'application | `codex-addons-db` |
| `arrival-worker` | Transfert registre d'arrivée (config privée en volume) | `codex-arrival-worker` |
| `dashboard-worker` | Projection tableau de bord | `codex-dashboard-worker` |
| `device-collector` | Device Hub (collecte terminaux) | `codex-device-collector` |
| `caddy` (profil `edge`) | Entrée HTTPS 80/443 — **uniquement si Caddy ne tourne pas sur l'hôte** | — |

Hors périmètre de ce compose (bancs d'essai locaux séparés, intégration ultérieure) :
`codex-card-bridge-http` et `codex-card-target-db-v17` (système de cartes natif), ainsi que le
serveur Node natif (port 8787) du dépôt `medygoo/schoolsafe-v`, qui suit sa propre mission.

## 2. Installation sur le VPS

Prérequis conformes à l'état documenté : Docker + Compose actifs, Caddy hôte maître de 80/443,
UFW (SSH 22, 80, 443 uniquement), **aucun port PostgreSQL publié**.

```bash
# 1. Récupérer le code (source Git officielle ; jamais de copie manuelle de production).
sudo mkdir -p /opt/schoolsafe-app && sudo chown "$USER" /opt/schoolsafe-app
cd /opt/schoolsafe-app
git clone <depot-officiel-schoolsafe> repo          # read-only deploy key
cd repo/workspace/ops/deployment

# 2. Environnement : secrets générés localement, jamais commités.
cp .env.example .env
chmod 600 .env
# Éditer .env : POSTGRES_PASSWORD (openssl rand -base64 32), POSTGRES_DB/USER, domaine.

# 3. Build et démarrage.
docker compose build
docker compose up -d db web arrival-worker dashboard-worker device-collector
docker compose ps
```

Le service `web` n'écoute que sur `127.0.0.1:8080` côté hôte.

## 3. Caddy hôte (entrée publique unique)

Ajouter un site au Caddyfile de l'hôte puis recharger :

```caddy
schoolsafe.179-198-195-15.sslip.io {
	encode zstd gzip
	reverse_proxy 127.0.0.1:8080
	header {
		Strict-Transport-Security "max-age=31536000; includeSubDomains"
		X-Content-Type-Options "nosniff"
		Referrer-Policy "strict-origin-when-cross-origin"
	}
}
```

`systemctl reload caddy`. Vérifier `https://<domaine>` : la page de connexion SchoolSafe
doit s'afficher (les assets `assets/themes/SchoolSafeCodex/`, `assets/login/` et le manifeste PWA
doivent répondre 200).

### Initialisation de la base (première école)

La base `schoolsafe` démarre vide. L'installation du schéma suit la procédure RosarioSIS
`InstallDatabase.php` (réservée à la phase d'installation, à désactiver ensuite) ou l'import du
dump `rosariosis.sql` par un opérateur. Les migrations `schoolsafe-remediation/001..009` sont
applicables sur la base de production (testé : 0 erreur) ; `010..013` et le ledger
`schoolsafe-attendance/001` portent un garde volontaire limité à la base de test locale
`rosario_addons_test` : ils refusent toute autre base et ne doivent PAS être contournés.

`014-account-state.sql`, `015-account-profiles.sql` et `016-school-state.sql` sont **additives et
idempotentes** : elles ne modifient aucune table native (nouvelles tables `schoolsafe_account_*`
et `schoolsafe_school_*`, plus trois profils nommés). Vérifié sur PostgreSQL 17.11 (image
`postgres:17.11-bookworm`, celle de la stack) chargé avec `rosariosis.sql` : application sans
erreur, puis rejeu sans erreur (NOTICE, aucune réécriture). Elles sont donc applicables en
production, dans l'ordre, après sauvegarde. Tant qu'elles ne sont pas appliquées, l'application
reste **inerte** : tout est `active` (aucun blocage), comportement voulu — jamais bloquant.

**Quand** : ces migrations ne s'appliquent **jamais** automatiquement. Elles s'exécutent uniquement
pendant un **déploiement explicitement autorisé** par le propriétaire, après la sauvegarde ci-dessous,
dans l'ordre `014` → `015` → `016`. Aucun script de vérification n'applique de migration :
`scripts/verify-production-image.mjs` refuse d'en appliquer, et le runner `deploy-isolated.ps1` reste
borné à la base de test locale `rosario_addons_test`.
Toute installation est une opération **validée par le propriétaire** ; le refus d'une base non
prévue et le ledger d'installation restent la norme (cf. `docs/INSTALLATION_V2.md`).

Une fois l'installation terminée : vérifier `diagnostic.php` en session admin (doit répondre
« properly configured »), puis créer l'administrateur principal via le parcours SchoolSafe.

### Vérifier une correction sur l'image de production (avant déploiement)

Ces vérifications rejouent la suite QA du lot contre l'image réelle (`Dockerfile.vps`), pas seulement
contre le conteneur de qualification :

```bash
node scripts/verify-production-image.mjs
```

Le script construit l'image, contrôle la syntaxe PHP des fichiers modifiés avec le PHP 8.3 de l'image,
lance un conteneur **jetable** sur `127.0.0.1:<port libre>` avec `config.inc.production.php`, attend un
`200` sur `index.php`, exécute `app/qa-l1-account-state.cjs` (`SCHOOLSAFE_L1_URL` pointé sur ce
conteneur), puis supprime uniquement ce conteneur. Il ne touche ni à la pile en cours, ni à la base,
ni à un environnement distant, n'applique aucune migration, et propage le code de sortie de la suite
(`--suite` pour une autre suite, `--port`, `--image` pour changer de tag).

## 4. Durcissement et sécurité

- `POSTGRES_PASSWORD` ≥ 32 caractères, uniquement dans `.env` (600). Aucun secret en Git.
- PostgreSQL sans port publié ; seuls `web`, workers et Caddy le joignent sur `backend`.
- `web` sans `CAP_*` particulier mais exposé uniquement en loopback + réseau `frontend`.
- Workers : `read_only`, `no-new-privileges`, `cap_drop ALL`, limites mémoire/CPU/PIDs,
  logs rotatifs — conformes à la spécification `schoolsafe-attendance/TRANSFER-WORKER.md`.
- PHP durci dans l'image : `session.cookie_httponly=On`, `session.use_strict_mode=On`,
  `expose_php=Off`. **Après vérification HTTPS** : décommenter `session.cookie_secure = On`
  dans le `Dockerfile.vps` (ligne prévue) et rebuild.
- La config PHP de production est montée **read-only** depuis
  `ops/deployment/config.inc.production.php` (aucun secret écrit dans le webroot).
- Le compte `InstallDatabase.php` et `diagnostic.php` : diagnostic reste en accès admin stock ;
  envisager de bloquer `InstallDatabase.php` au niveau Caddy après installation (règle à ajouter
  au lot de durcissement edge).

## 5. Sauvegardes

```bash
# Backup avant toute migration (règle n°8) — hors conteneur, dossier root.
sudo mkdir -p /opt/schoolsafe-app/backups && sudo chmod 700 /opt/schoolsafe-app/backups
docker compose -f /opt/schoolsafe-app/repo/workspace/ops/deployment/docker-compose.yml exec -T db \
  pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB" | sudo tee /opt/schoolsafe-app/backups/schoolsafe-$(date +%F-%H%M).dump >/dev/null
```

- Tester une **restauration réelle** sur une base jetable avant la première école réelle
  (décision du 14/09 : une sauvegarde jamais restaurée n'est pas une garantie).
- Planification/rétention distante (préfixe `schoolsafe/postgres/`) : restant à établir,
  cf. rapport VPS.

## 6. Exploitation courante

```bash
docker compose ps                                  # état + santé
docker compose logs -f web                         # journaux applicatifs
docker compose exec web php /var/www/html/diagnostic.php   # (session admin requise pour le complet)
docker compose up -d --build web                   # mise à jour applicative
docker compose down && docker compose up -d        # redémarrage propre (volumes conservés)
```

Chaque version déployée porte le SHA Git du checkout (règle n°9 : retour arrière possible en
repliant le SHA précédent et `up -d --build`).

## 7. Limites connues avant mise en production (à traiter par lots autorisés)

1. **Export PDF historique RosarioSIS** (`functions/PDF.php`, phpwkhtmltopdf) : la config pointe
   vers chromium, binaire incompatible avec la CLI wkhtmltopdf ; le pipeline SchoolSafe
   (`SchoolSafeSchoolDocument.fnc.php`, chromium headless `--print-to-pdf`) fonctionne.
   Le diagnostic stock signale l'écart. Décision à prendre : installer un vrai wkhtmltopdf dans
   l'image, ou désactiver/documenter l'export stock. Ne pas modifier le cœur Rosario sans autorisation.
2. **Cutover Gateway.php incomplet** : 19/32 endpoints custom couverts par le garde natif ; les 13
   restants gardent leurs vérifications session/permission propres (aucun endpoint sans protection
   identifié), mais le rattrapage suit `modules/SchoolSafe/INTEGRATION.md` (tests mockés d'abord,
   activation et permissions revues séparément).
3. **Impression officielle des cartes** (studio) : échec `permission denied for schema app` +
   ZIP 500 constatés en E2E local (handoff 11/10) — lié au banc cartes natif, hors ce compose.
4. **Création de comptes natifs** : POST /native/access/users en 503 `DEPENDENCY_UNAVAILABLE`
   (identité externe absente) — comportement attendu du lot courant.
5. **Portail de licence** : `setup_complete_school` n'écrit pas `ops.supabase_school_access` ;
   l'activation requiert les paramètres `ACTIVATION_*` via le service dédié.
6. **Sauvegarde distante R2** : non configurée (planification/rétention restant à établir).

Aucun déploiement n'est implicite : l'ordre humain de mise en production reste requis
(AGENTS.md, mission VPS du 20/09/2026).
