# 12 — DÉMARRAGE DU NOUVEL AGENT

## Message d'accueil (à remettre tel quel)

> Bonjour et bienvenue sur **SchoolSafe**.
>
> Tu reprends un projet déjà largement avancé, en production, avec de vrais utilisateurs et de vrais élèves. Ton travail doit **continuer** ce qui existe, jamais repartir de zéro.
>
> **Où est le code** : `C:\Users\PC\SchoolSafeCodex\SCHOOLSAFE_CODEX_LOCAL_SNAPSHOT_V1\workspace`, dépôt `github.com/medygoo/schoolsafe-v`, branche `main`. Le commit en ligne est `5a946f3`.
>
> **Ce qui tourne** : `https://app.shoolsafe.tech` (application SchoolSafe/RosarioSIS), PostgreSQL dans `schoolsafe-rosario-db`, deux moteurs WhatsApp connectés (WAHA et Evolution API).
>
> **Ce qui est fragile** : le chrome de navigation après connexion est encore celui de Rosario ; les sessions WhatsApp applicatives sont volontairement désactivées ; aucun outil de sécurité P0 n'est installé ; un correctif WhatsApp attend d'être publié ; le lot cartes/scans est publié (`8f81b8f`) mais pas déployé.
>
> **Tes règles** : jamais de secret publié, jamais de suppression de données, jamais de déploiement sans accord, jamais de réécriture de Rosario, jamais de simulation présentée comme une validation réelle.
>
> **Ta première action** : lire `00_LIRE_EN_PREMIER.md`, puis `01_ETAT_PROJET.md` et `11_FEUILLE_DE_ROUTE.md`. Ne code rien avant d'avoir lu `10_DECISIONS_ET_INTERDICTIONS.md`.
>
> **Pose des questions** au propriétaire dès qu'une action est irréversible ou sort du périmètre. Il préfère une question à un incident.

---

## 1. Compétences à charger avant d'agir

| Compétence | Quand |
|---|---|
| `schoolsafe-context` | à chaque début de session (AGENTS.md, handoff, décisions) |
| `schoolsafe-git-github` | avant tout `git status/commit/push` |
| `schoolsafe-lot` | procédure de lot (reconnaissance → implémentation → QA → handoff) |
| `schoolsafe-access-law` | authentification, rôles, pools PostgreSQL |
| `schoolsafe-postgres` | migrations, sauvegarde, RLS |
| `schoolsafe-vps-deploy` | intervention VPS ou déploiement |
| `schoolsafe-qa` | choisir la bonne suite de tests |
| `schoolsafe-observer` | état des lieux sans rien modifier |

## 2. Séquence de reprise recommandée

```bash
cd "C:/Users/PC/SchoolSafeCodex/SCHOOLSAFE_CODEX_LOCAL_SNAPSHOT_V1/workspace"

# a. Contexte
sed -n '1,80p' AGENTS.md
git status --short
git log -3 --oneline

# b. Santé
npm run check:migration-versions
npm test

# c. Production
curl -s -o /dev/null -w "connexion=%{http_code}\n" https://app.shoolsafe.tech/connexion
curl -s https://app.shoolsafe.tech/health
ssh prodeli-hub-01 'docker ps --format "{{.Names}}|{{.Status}}"'
```

Puis lire `docs/CURRENT_HANDOFF.md` en ciblant les sections utiles (3 800 lignes : ne pas le lire d'un bloc).

## 3. Les 6 vérités qui évitent les erreurs coûteuses

1. La base de production s'appelle **`rosario_addons_test`**, pas `schoolsafe`.
2. Remplacer un fichier PHP au même chemin ne suffit pas : **OPcache** sert l'ancienne version. Utiliser un nouveau nom.
3. Un QR WhatsApp **expire** ; un scan raté met WAHA en `FAILED` → redémarrer la session.
4. WhatsApp entrant arrive en **`@lid`**, pas en `@c.us`.
5. Evolution exige **`X-Api-Key`** sur `GET /instance/fetchInstances` (401 sinon).
6. Le lot cartes/scans est désormais dans Git (`8f81b8f`) ; seuls 6 captures QA et le correctif WhatsApp restent non publiés — ne jamais les écraser.

## 4. Ce qu'il ne faut pas faire le premier jour

- Ne pas déployer, ne pas pousser, ne pas migrer.
- Ne pas « nettoyer » les fichiers non suivis (6 captures QA + `SchoolSafeWhatsapp.fnc.php` modifié).
- Ne pas modifier `Side.php`, `Bottom.php` ni le thème pour « voir ».
- Ne pas activer les sessions WhatsApp sans accord.
- Ne pas installer d'outil P0 sans vérifier qu'il n'est pas déjà présent.

## 5. Comment travailler avec le propriétaire

- Il valide les directions ; il attend des **preuves** (identifiants, codes HTTP, captures), pas des affirmations.
- Signaler explicitement ce qui a échoué ou n'a pas été testé.
- Toute action destructive ou externe (push, déploiement, envoi réel, suppression) est **soumise à son accord**.

---

Le projet est en meilleur état que son historique ne le suggère : la connexion, les comptes, la récupération et WhatsApp fonctionnent réellement. Ce qui reste est de la finition, de la preuve et de la discipline.
