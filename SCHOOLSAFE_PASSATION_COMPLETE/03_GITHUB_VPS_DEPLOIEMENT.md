# 03 — GITHUB, VPS ET DÉPLOIEMENT

## 1. Chaîne validée (règle architecturale)

```
PC local → Git → GitHub → Coolify → Docker → VPS Hostinger → SchoolSafe → PostgreSQL
```

Le déploiement normal passe **toujours** par cette chaîne. Le SSH direct sur le VPS est réservé au **diagnostic** et aux opérations d'administration explicitement autorisées.

## 2. Références vérifiées le 20/10/2026

| Référence | Valeur constatée |
|---|---|
| Dépôt | `github.com/medygoo/schoolsafe-v` |
| Branche | `main` |
| HEAD local | `5a946f36156cf0718025c1753c8ee032b5155305` |
| `origin/main` (via `git ls-remote`) | `5a946f36156cf0718025c1753c8ee032b5155305` |
| `prodelifin/main` (via `git ls-remote`) | `5a946f36156cf0718025c1753c8ee032b5155305` |
| Image PHP déployée | `schoolsafe-rosario:20261020-gofinal` |
| Image API déployée | `schoolsafe-app:20261018d` |
| Contrôle d'intégrité | SHA-256 du fichier WhatsApp du conteneur = celui de `HEAD` |

Conclusion : **local = GitHub = VPS** pour le commit `5a946f3`, à l'exception du correctif non publié (`09` §3).

## 3. Ouvrir et démarrer en local

```bash
cd "C:/Users/PC/SchoolSafeCodex/SCHOOLSAFE_CODEX_LOCAL_SNAPSHOT_V1/workspace"
git status --short
git log -3 --oneline
npm ci                       # respecte package-lock.json ; ne pas changer de gestionnaire
npm run typecheck            # serveur TypeScript
npm test                     # Vitest serveur (688 tests)
```

Versions constatées : Node `v24.19.0`, npm `11.17.0`, Playwright `1.62.1`.

API en développement :

```bash
npm run dev --workspace server
```

## 4. Construire l'image PHP

```bash
docker build -f app/modules/codex-ui-v1/extensions-test-runtime/Dockerfile.vps \
  -t schoolsafe-rosario:<AAAAMMJJ>-<tag> \
  app/modules/codex-ui-v1/extensions-test-runtime
```

Ne jamais réutiliser un tag existant : chaque version doit rester identifiable pour le rollback.

## 5. Vérifier l'état du VPS (lecture seule, avant toute action)

```bash
ssh prodeli-hub-01 'hostname'
ssh prodeli-hub-01 'docker info --format "{{.ServerVersion}} {{.ContainersRunning}} running"'
ssh prodeli-hub-01 'docker ps --format "{{.Names}}|{{.Image}}|{{.Status}}"'
ssh prodeli-hub-01 'df -h / | tail -1; free -h | head -2'
curl -s -o /dev/null -w "%{http_code}\n" https://app.shoolsafe.tech/connexion
curl -s https://app.shoolsafe.tech/health
```

État constaté le 20/10/2026 : disque 38 G / 193 G (20 %), mémoire 2,3 Gi utilisés sur 15 Gi, 6 conteneurs `Up`, `schoolsafe-rosario` et `schoolsafe-app` `healthy`.

## 6. Déployer de façon contrôlée

1. `git status` : le commit ne doit contenir **que** les fichiers voulus.
2. Commit + push (**sur demande explicite du propriétaire**) :

```bash
git add <fichiers précis>          # jamais git add -A
git commit -m "<message>"
git push origin main
```

3. Laisser Coolify construire et redéployer, puis contrôler :

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://app.shoolsafe.tech/connexion
curl -s https://app.shoolsafe.tech/health
ssh prodeli-hub-01 'docker ps --format "{{.Names}}|{{.Status}}"'
ssh prodeli-hub-01 'docker logs --tail 50 schoolsafe-rosario'
```

4. Consigner le tag d'image et le SHA déployés dans `08_TESTS_ET_PREUVES.md`.

## 7. Sauvegarde avant toute migration

```bash
ssh prodeli-hub-01 'docker exec schoolsafe-rosario-db pg_dump -U postgres -Fc rosario_addons_test \
  > /opt/schoolsafe-rosario/backup/schoolsafe-pre-$(date +%Y%m%d-%H%M).dump'
```

Une sauvegarde quotidienne existe (02:30) ; elle **ne remplace pas** la sauvegarde avant migration.

## 8. Restaurer

```bash
ssh prodeli-hub-01 'docker exec -i schoolsafe-rosario-db pg_restore -U postgres \
  -d rosario_addons_test --clean --if-exists < /opt/schoolsafe-rosario/backup/<fichier>.dump'
```

Restauration = opération destructive : autorisation explicite obligatoire, application arrêtée pendant l'opération.

## 9. Revenir à la version précédente

```bash
# 1. Identifier l'image précédente
ssh prodeli-hub-01 'docker images schoolsafe-rosario --format "{{.Repository}}:{{.Tag}} {{.CreatedSince}}"'

# 2. Arrêter et RENOMMER le conteneur courant (jamais le supprimer)
ssh prodeli-hub-01 'docker stop schoolsafe-rosario && docker rename schoolsafe-rosario schoolsafe-rosario-failed'

# 3. Recréer avec l'image précédente, mêmes options (env-file, réseau, config montée en ro)
#    → reprendre la commande documentée dans docs/CURRENT_HANDOFF.md (P6) en changeant le tag
```

Règles : jamais `docker volume rm`, jamais `DROP DATABASE`, jamais de remplacement au hasard. Le conteneur fautif est **renommé**, pas supprimé, pour permettre l'analyse.

## 10. Écarts connus

- Le correctif `provider_msg_id` est dans l'arbre de travail mais **pas** dans `5a946f3` ni dans l'image.
- Le lot cartes/scans (5 fonctions PHP, 4 migrations, 7 tests, 3 scripts QA) est **hors Git**.
- La base de production s'appelle `rosario_addons_test` : toujours lire `POSTGRES_DB`, jamais supposer un nom.
- Le conteneur API (`20261018d`) est plus ancien que le conteneur PHP (`20261020-gofinal`) : les deux ne proviennent pas forcément du même commit.
