# 00 — LIRE EN PREMIER

Passation SchoolSafe — dossier établi le **20 octobre 2026** à partir de l'état réel du dépôt local, de GitHub et du VPS `prodeli-hub-01`. Aucun secret, aucune donnée personnelle, aucun QR actif ne figure ici.

## Les 8 faits à connaître avant de toucher quoi que ce soit

1. **Le code vit dans un dépôt Git** : `github.com/medygoo/schoolsafe-v`, branche `main`, HEAD `5a946f36156cf0718025c1753c8ee032b5155305` (« feat(auth-ecoles-comptes): lots L2/L3/L3.1/L4/SMS/WhatsApp valides isole (GO FINAL) », 20/10/2026 12:37).
2. **Ce commit est en ligne** : `origin/main` et `prodelifin/main` pointent tous deux sur `5a946f3`. L'image déployée est `schoolsafe-rosario:20261020-gofinal`, et le fichier WhatsApp du conteneur a exactement le SHA-256 du commit (voir `08_TESTS_ET_PREUVES.md`).
3. **Le site public** `https://app.shoolsafe.tech` sert l'application SchoolSafe : `/connexion` renvoie 200, titre `SchoolSafe`, écran `login-blue`. L'ancienne interface Rosario subsiste **après** connexion (voir `09_PROBLEMES_CONNUS.md` §1).
4. **WhatsApp est réellement connecté** sur deux numéros (WAHA `243967552380@c.us`, Evolution `243828432689@s.whatsapp.net`), mais les **sessions applicatives sont `disabled`** : aucune diffusion automatique vers parents/écoles. C'est volontaire.
5. **Un correctif WhatsApp est écrit mais NON déployé** : `provider_msg_id` recevait `"Array"` pour WAHA (`09` §3).
6. **Aucun outil de sécurité P0 n'est installé** (Semgrep, Gitleaks, Trivy, Lighthouse, axe, ZAP, Bugsink : absents). Seul Playwright 1.62.1 est présent (`07_OUTILS_P0.md`).
7. **La base de production s'appelle `rosario_addons_test`** (nom trompeur mais c'est la base utilisée, via `POSTGRES_DB`). La base nommée `schoolsafe` n'est **pas** celle du site.
8. **Le déploiement ne se fait jamais à la main sur le VPS.** Chaîne validée : PC local → Git → GitHub → Coolify → Docker → VPS Hostinger.

## Les 5 interdictions absolues

- Ne jamais publier de secret (clé moteur, mot de passe, jeton, QR) dans Git, un document ou un écran.
- Ne jamais `docker volume rm`, `docker system prune`, `DROP DATABASE`, ni réinitialiser PostgreSQL.
- Ne jamais modifier les données réelles sans sauvegarde **et** autorisation explicite du propriétaire.
- Ne jamais déployer du code non validé, ni pousser sur GitHub sans demande explicite.
- Ne jamais toucher aux travaux indépendants situés sous `C:\Users\PC\Documents\Codex\...` (« Work »).

## Par où commencer (30 premières minutes)

```bash
# 1. État du dépôt
cd "C:/Users/PC/SchoolSafeCodex/SCHOOLSAFE_CODEX_LOCAL_SNAPSHOT_V1/workspace"
git status --short && git rev-parse --short HEAD && git log -3 --oneline

# 2. État du VPS (lecture seule)
ssh prodeli-hub-01 'hostname; docker ps --format "{{.Names}}|{{.Image}}|{{.Status}}"'

# 3. Santé applicative
curl -s -o /dev/null -w "%{http_code}\n" https://app.shoolsafe.tech/connexion
curl -s https://app.shoolsafe.tech/health

# 4. Tests unitaires serveur (le plus rapide)
npm test
```

Puis lire, dans cet ordre : `01_ETAT_PROJET.md` → `02_ARCHITECTURE_ET_CODE.md` → `03_GITHUB_VPS_DEPLOIEMENT.md` → `10_DECISIONS_ET_INTERDICTIONS.md`.

## Ce qui est en attente de VOTRE décision (propriétaire)

- Publier (commit + push + Coolify) le correctif WhatsApp `provider_msg_id`.
- Activer les sessions WhatsApp applicatives (aujourd'hui `disabled`) pour autoriser la diffusion.
- Choisir l'ordre des outils P0 restants (aucun n'est installé).
- Arbitrer le sort de l'écran de connexion historique conservé en secours.

## Fichiers de ce dossier

| Fichier | Contenu |
|---|---|
| `00_LIRE_EN_PREMIER.md` | Essentiels, interdictions, premier quart d'heure |
| `01_ETAT_PROJET.md` | État lot par lot, avec preuves |
| `02_ARCHITECTURE_ET_CODE.md` | Architecture, fichiers, routes, flux |
| `03_GITHUB_VPS_DEPLOIEMENT.md` | Git, VPS, déploiement, sauvegarde, rollback |
| `04_BASE_DE_DONNEES.md` | PostgreSQL, schémas, migrations, isolation |
| `05_FONCTIONNALITES.md` | Fonctionnalité par fonctionnalité |
| `06_COMMUNICATIONS.md` | Studio, push, email, SMS, WAHA, Evolution |
| `07_OUTILS_P0.md` | Outils réellement présents / absents |
| `08_TESTS_ET_PREUVES.md` | Inventaire QA, commandes, preuves |
| `09_PROBLEMES_CONNUS.md` | Symptômes, causes démontrées, hypothèses |
| `10_DECISIONS_ET_INTERDICTIONS.md` | Décisions validées et règles |
| `11_FEUILLE_DE_ROUTE.md` | Ordre exact des travaux restants |
| `12_DEMARRAGE_NOUVEL_AGENT.md` | Message d'accueil et premiers pas |
