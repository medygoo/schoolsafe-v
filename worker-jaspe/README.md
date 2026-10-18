# SchoolSafe JASPE 2.5D — Worker Cloudflare

Pont entre le VPS SchoolSafe et Cloudflare AI.

## Architecture

```
VPS SchoolSafe                     Cloudflare
┌──────────────┐     POST /chat    ┌──────────────────────┐
│  JASPE       │ ────────────────▶ │  schoolsafe-jaspe    │
│  Service     │                   │  (Cloudflare Worker) │
│              │ ◀──────────────── │                      │
│  JASPE_      │  { reply: "..." } │  AI Gateway ───▶ Workers AI
│  WORKER_URL  │                   │                      │
└──────────────┘                   └──────────────────────┘
```

## Déploiement

```bash
cd worker-jaspe

# Installer
npm install

# Développement local
npm run dev

# Déployer sur Cloudflare
npm run deploy
```

## Configuration

| Variable | Description | Défaut |
|---|---|---|
| `JASPE_MODEL` | Modèle Workers AI | `@cf/meta/llama-3.3-70b-instruct` |
| `JASPE_SYSTEM_PROMPT` | Prompt système JASPE | "Tu es JASPE, l'assistante..." |
| `RESPAN_API_KEY` | Clé Respan (secret Worker). Absente ⇒ aucun suivi | — |
| `RESPAN_BASE_URL` | Base Respan (facultatif) | `https://api.respan.ai` |

**Bindings (wrangler.toml) :**
- `AI` — Workers AI binding
- `AI_GATEWAY` — optionnel, pour AI Gateway

## API

### `POST /`

```json
{
  "message": "Bonjour JASPE !",
  "session_key": "u:uuid:uuid"
}
```

Réponse (200) :
```json
{
  "reply": "Bonjour ! Comment puis-je t'aider ?"
}
```

## Suivi Respan (optionnel)

Le Worker peut envoyer des **métriques** à Respan en OTLP/HTTP, sans SDK :
`POST https://api.respan.ai/api/v2/traces`, en-tête `Authorization: Bearer <clé>`.

Ce qui part : durée de l'appel (début/fin), modèle demandé et modèle de réponse
(si le fournisseur le donne), jetons d'entrée/sortie/total (si le fournisseur les
donne), issue `ok`/`error` avec un **code interne** (`PROVIDER_ERROR`), et un
identifiant de conversation **haché** (SHA-256 du `session_key`, tronqué).

Ce qui ne part **jamais** : le message de l'élève ou du parent, la réponse de
JASPE, le prompt système, les noms, les identifiants d'élève et d'école.
Respan reçoit des métriques, pas des données scolaires.

```bash
# Activer (la clé reste un secret Worker, jamais dans wrangler.toml)
npx wrangler secret put RESPAN_API_KEY

# Vérifier sans réseau ni déploiement
npm run typecheck
npm test
```

Sans `RESPAN_API_KEY`, aucun envoi n'a lieu et JASPE répond exactement comme
avant. Un échec Respan (réseau, 4xx, 5xx, délai de 1,5 s) ne change jamais la
réponse : l'envoi passe par `ctx.waitUntil`, donc après la réponse, et ses
erreurs sont silencieuses (aucune donnée utilisateur en journal).

Détail de l'étude, limites et checklist de mise en service :
`docs/audit/JASPE_RESPAN_RAPPORT.md` (document local).

## Côté VPS

Dans `.env` du serveur SchoolSafe :
```env
JASPE_WORKER_URL=https://schoolsafe-jaspe.nom-utilisateur.workers.dev
JASPE_CHAT_TIMEOUT_MS=12000
JASPE_RATE_PER_MINUTE=20
```