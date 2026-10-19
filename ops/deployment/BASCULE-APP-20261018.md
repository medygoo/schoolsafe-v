# Bascule de `app.shoolsafe.tech` vers l'application SchoolSafe/Rosario — 18 octobre 2026

## 1. Avant / après

| | Avant | Après |
|---|---|---|
| `app.shoolsafe.tech` | V2 statique + API Node sur `127.0.0.1:8787` | **Application SchoolSafe (runtime RosarioSIS)** sur `127.0.0.1:18081` |
| Application V2 | publique | **toujours en service**, mais plus publiée (`127.0.0.1:8787`, HTTP 200 en local) |
| Instance de test | `rosario.179.198.195.15.sslip.io` | conservée, inchangée |

Le domaine public sert désormais le vrai SchoolSafe : page de connexion SchoolSafe, authentification
Rosario (table `staff`), tableau de bord et console d'administration des comptes.

## 2. Ce qui a changé dans Caddy

Un seul bloc, sauvegardé avant modification dans **`/etc/caddy/Caddyfile.bak-20261018T223043Z`** :

```caddyfile
app.shoolsafe.tech {
	encode zstd gzip
	@not_public path_regexp (?i)\.(sql|md|cjs|ts|map|lock|ya?ml|sh|py|env|ini|log)$
	respond @not_public 404
	reverse_proxy 127.0.0.1:18081
}
```

La règle qui bloquait `*.php` a dû sauter : l'application **est** du PHP. En revanche les schémas, les
migrations et la documentation ne doivent jamais sortir en statique — sur l'instance Rosario, avant
cette règle, `/rosariosis.sql` répondait **200**. Vérifié depuis l'extérieur après bascule :

| URL | Résultat |
|---|---|
| `/` | **200**, `<title>SchoolSafe</title>` |
| `/rosariosis.sql` | **404** |
| `/schoolsafe-remediation/017-accounting-budgets.sql` | **404** |
| `/README.md` | **404** |
| `/assets/login/blue/blue.css` | **200** |

Certificat : Let's Encrypt, CN `app.shoolsafe.tech`, valide jusqu'au **24 décembre 2026**.
`caddy validate` → *Valid configuration*, service `active` après `systemctl reload caddy`.

## 3. Vérifications d'authentification sur le domaine public

- `POST /index.php` (`USERNAME`/`PASSWORD`) → **302**, puis `Modules.php?modname=misc/Portal.php`
  contient « Bonjour, Administrateur » : **connexion réelle acceptée**.
- `GET /admin-console.php` avec la session administrateur → **200**, titre « Console d'administration |
  SchoolSafe », en-tête « Comptes administrateurs — année scolaire 2026 », actions `reset`, `revoke`,
  `state` et jeton CSRF de session présents.
- Page de connexion rendue dans le navigateur sur `https://app.shoolsafe.tech/` (SchoolSafe, FR/EN,
  mot de passe oublié, bandeau « Accès protégé »).

## 4. Gestion des utilisateurs administrateurs (déjà implémentée)

Elle s'appuie sur le natif Rosario, sans le modifier :

- **Comptes** : table `staff` (profils Rosario), mot de passe haché par `encrypt_password()`
  (`crypt` SHA-512, préfixe `$6$`) — la console réutilise exactement le même format.
- **Console** : `admin-console.php`, réservée aux administrateurs **globaux** (`staff.SCHOOLS` vide =
  toutes les écoles) ; un directeur (administrateur délégué) ne voit que les personnes de ses écoles.
  Jeton CSRF de session obligatoire sur chaque action ; le mot de passe temporaire n'est affiché
  **qu'une fois** (flash de session).
- **États de compte** (`schoolsafe_account_state`, migration `014`) : `active`, `readonly` (écriture
  refusée), `suspended` (connexion refusée), `expired` (date de fin). L'état porte sur la **personne**
  (nom d'utilisateur en minuscules) **et l'école** (`school_id = 0` = toutes), donc il survit au
  rollover et une personne de deux écoles peut être suspendue dans l'une seulement.
- **Révocation de session** : compteur `session_epoch` ; une session déjà ouverte est invalidée à la
  requête suivante. Si la migration n'est pas appliquée, tout est « actif » : jamais bloquant.
- **Premier login** : `LAST_LOGIN` vide + `FORCE_PASSWORD_CHANGE_ON_FIRST_LOGIN` → changement de mot
  de passe imposé (`FirstLogin.fnc.php`).

Fichiers : `admin-console.php`, `ProgramFunctions/SchoolSafeAccountState.fnc.php`,
`schoolsafe-remediation/014-account-state.sql`, `015-account-profiles.sql`, `016-school-state.sql`.

## 5. Retour arrière

```bash
cp -a /etc/caddy/Caddyfile.bak-20261018T223043Z /etc/caddy/Caddyfile
caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile
systemctl reload caddy
```

`app.shoolsafe.tech` repointe alors vers la V2 (`127.0.0.1:8787`), toujours en service. Aucune donnée
n'est touchée par ce retour : les deux applications ont des bases séparées
(`rosario_addons_test` pour Rosario, la base de la stack `schoolsafe-app` pour la V2).

## 6. Limites connues

- La V2 n'a plus d'URL publique : pas d'enregistrement DNS pour un sous-domaine (`v2.shoolsafe.tech`
  ne résout pas). Accès local par tunnel SSH si besoin :
  `ssh -L 8787:127.0.0.1:8787 prodeli-hub-01` puis `http://127.0.0.1:8787`.
- Les écrans Arrivée, Device Hub, Palmarès, PWA, Identité, Ouvertures, Reçus et Anniversaires restent
  indisponibles : leurs tables n'ont pas de schéma versionné (voir `ROSARIO-INSTANCE-20261018.md` §5).
- Les comptes parents/élèves présents dans la base sont ceux du jeu de données initial (3 personnels,
  1 élève) ; la console sert à créer les vrais comptes.
