# Instance réelle SchoolSafe / RosarioSIS sur le VPS — 18 octobre 2026

But : faire tourner **la véritable application SchoolSafe (runtime RosarioSIS)** sur le VPS
`prodeli-hub-01`, sans toucher au site existant `app.shoolsafe.tech`. Tout est isolé : base dédiée,
conteneurs dédiés, réseau Docker dédié, bloc Caddy séparé.

## 1. État livré

| Élément | Valeur |
|---|---|
| URL | `https://rosario.179.198.195.15.sslip.io` (certificat Caddy automatique) |
| Réponse | HTTP 200, `<title>SchoolSafe</title>`, page de connexion complète |
| Application | `schoolsafe-rosario` — image `schoolsafe-rosario:20261018`, `Up (healthy)` |
| Base | `schoolsafe-rosario-db` — PostgreSQL **17.11**, base `rosario_addons_test` |
| Volume des données | `schoolsafe-rosario-pgdata` |
| Réseau | `schoolsafe-rosario-net` (172.16.1.0/24) — l'application joint la base par nom de service |
| Ports hôte | `127.0.0.1:18081 -> 80` (app), `127.0.0.1:5433 -> 5432` (base) — jamais exposés publiquement |
| Configuration | `/opt/schoolsafe-rosario/config.inc.php` (copie de `ops/deployment/config.inc.production.php`), montée en lecture seule |
| Secrets | `/opt/schoolsafe-rosario/app.env`, droits `0600`, jamais dans Git |
| Site V2 existant | `app.shoolsafe.tech` → **HTTP 200**, inchangé |

Contenu de la base au moment de la mise en ligne : 186 tables du schéma `public`, 1 école
(`Default School`, `syear` 2026), 3 comptes de personnel, 1 élève, rôle applicatif `schoolsafe_app`
(**ni superuser ni création de base**, 861 privilèges de table avant la migration `017`, qui en ajoute quatre). L'application ne se connecte jamais
avec `postgres` : vérifié en lisant `config.inc.php` dans le conteneur
(`utilisateur=schoolsafe_app`).

## 2. Preuves d'exécution

- `GET /` → 200, page de connexion rendue (8,3 Ko).
- `POST /index.php` avec `USERNAME`/`PASSWORD` (l'application attend ces noms de champs, en
  majuscules) → authentification acceptée, puis **tableau de bord SchoolSafe** (voir §3 : avant
  correction, la session partait vers l'assistant d'installation Rosario).
- Parcours complet dans le navigateur : menu → *Logout* → page de connexion → `admin` → tableau de
  bord `Modules.php?modname=misc/Portal.php`, « Bonjour, Administrateur », 13 vues.
- Connexion refusée avec un mauvais mot de passe → page de connexion (200), pas d'erreur 500.
- `caddy validate` → *Valid configuration*.
- Requête du tableau de bord (budgets par catégorie) rejouée **en rôle `schoolsafe_app`** → succès
  (0 erreur `relation ... does not exist`).

## 3. Écran de connexion : pourquoi il ne menait nulle part

Symptôme signalé : l'écran de connexion SchoolSafe « ne fonctionne pas » — on saisit ses identifiants et on
ne rentre jamais dans l'application. Le formulaire est pourtant bien branché sur l'authentification Rosario :
`<form action="index.php" method="post">` avec `USERNAME` / `PASSWORD`, traités par `index.php` contre la table
`staff`. Le mot de passe était donc accepté (302), mais la session était **détournée vers l'assistant
d'installation Rosario**.

Cause exacte : la base restaurée contenait `config.LOGIN = 'No'` (school_id 0). Tant que ce drapeau vaut
`No`, `HasFirstLoginForm()` renvoie vrai pour l'administrateur et **chaque** connexion part vers
`index.php?modfunc=first-login`, qui affiche « Confirm Successful Installation » et le sondage
d'installation Rosario (`FirstLoginPoll()`), pas le tableau de bord SchoolSafe.

Correction appliquée (c'est exactement ce que fait la validation de l'assistant) :

```sql
UPDATE config SET config_value = 'Yes' WHERE title = 'LOGIN' AND school_id = 0;
```

Vérifié de bout en bout dans le navigateur : déconnexion → page de connexion SchoolSafe → `admin` →
**tableau de bord** (`Modules.php?modname=misc/Portal.php`, « Bonjour, Administrateur », 13 vues), puis
les **13 vues du tableau de bord** parcourues une à une : aucune erreur SQL, aucune page blanche.

À refaire après chaque restauration de ce dump, sinon le blocage revient.

## 4. Défaut réel trouvé et corrigé

Le journal de l'application a montré, dès la première connexion :

```
ERROR: relation "schoolsafe_accounting_budgets" does not exist
```

`ProgramFunctions/SchoolSafeBudgetPayroll.fnc.php` et `modules/Accounting/Categories.php` lisent
cette table, mais **aucun script du dépôt ne la créait** : elle n'existait que dans les bases créées
à la main, donc tout déploiement neuf cassait le tableau de bord et la page Catégories.

Correction : **`app/modules/codex-ui-v1/extensions-test-runtime/schoolsafe-remediation/017-accounting-budgets.sql`**
(nouveau, idempotent). Il crée la table `schoolsafe_accounting_budgets` (clé
`school_id, syear, category_id`), son index, ses trois clés étrangères (`schools`, `accounting_categories`,
`staff`), le `CHECK amount >= 0` et les droits du rôle `schoolsafe_app`. Trois vérifications : sur la
base de développement locale (table déjà présente → aucune erreur, structure intacte), sur une base
jetable dérivée du schéma du VPS avec la table retirée (déploiement neuf → les 5 contraintes sont
créées), puis rejoué sur cette même base (idempotence). Enfin appliqué au conteneur du VPS ; la copie
`/opt/schoolsafe-rosario/017-accounting-budgets.sql` a la même empreinte SHA-256 que le fichier du
dépôt (`48136338…`).

## 5. Écarts connus (non corrigés dans ce lot)

L'écart entre le code et le schéma est plus large que cette seule table. Mesuré en comparant les
`FROM/JOIN/INTO/UPDATE` du PHP aux tables réellement présentes sur le VPS : **35 tables `schoolsafe_*`
sont utilisées, 15 existent**.

- Déclarées dans un `.sql` du dépôt mais **non appliquées** sur le VPS :
  `schoolsafe_attendance` (`schoolsafe-attendance/001-arrival-ledger.pg.sql`),
  `schoolsafe_device_collectors` et `schoolsafe_device_queue` (`modules/SchoolSafe/device-collector.sql`),
  `schoolsafe_notification_push_{subscriptions,activation,delivery}` (seulement dans un banc local
  `card-data-bridge/local-native-notification-push.sql`).
- **Référencées par le PHP mais déclarées nulle part** dans le dépôt :
  `schoolsafe_identity_links`, `schoolsafe_identity_audit`, `schoolsafe_arrival_source`,
  `schoolsafe_arrival_mapping`, `schoolsafe_birthday_alerts`, `schoolsafe_dashboard_runs`,
  `schoolsafe_opening_requests`, `schoolsafe_opening_events`, `schoolsafe_palmares_{awards,events,snapshots}`,
  `schoolsafe_payment_receipts`, `schoolsafe_pwa_{operations,payment_intents,receipt_reads}`.

Conséquence honnête : **le tableau de bord (13 vues parcourues sans erreur) et la page Catégories
fonctionnent** après `017`, mais les
écrans Arrivée, Device Hub, Palmarès, PWA, Identité, Ouvertures, Reçus et Anniversaires ne peuvent pas
être servis sur une base neuve tant que ces tables n'ont pas de schéma versionné. C'est un lot de
schéma à part entière, pas un problème de déploiement.

Autre limite : les conteneurs ont été créés par `docker run` (pas par le `docker-compose.yml` du
dépôt, qui décrit l'ancienne stack `schoolsafe-app`). Ils survivent à un redémarrage du VPS
(`restart=unless-stopped`), mais un `docker compose up` du dépôt ne les reproduit pas.

## 6. Sécurité

Le mot de passe par défaut RosarioSIS a été **remplacé** le 18/10 (24 caractères aléatoires, SHA-512
`crypt`, même fonction que `encrypt_password()` de l'application). Il est stocké côté serveur, hors Git :

```
/opt/schoolsafe-rosario/admin-password.txt   (0600 root : URL, utilisateur, mot de passe)
```

Vérifié après rotation : connexion avec le nouveau mot de passe → **302** puis tableau de bord
« Bonjour, Administrateur » ; l'ancien mot de passe `admin` est **refusé**. Pour changer de mot de passe
plus tard, le plus simple est depuis l'application (menu utilisateur), ou avec la même méthode :

```bash
docker exec -i schoolsafe-rosario php -r '
$p = trim(stream_get_contents(STDIN));
$salt = "\$6\$" . substr(strtr(base64_encode(random_bytes(12)), "+", "."), 0, 16);
echo crypt($p, $salt);' <<< 'NOUVEAU_MOT_DE_PASSE'
# puis UPDATE staff SET password = '<empreinte>' WHERE staff_id = 1;
```

Reste recommandé : restreindre l'URL de test dans Caddy (`basic_auth` ou liste d'IP), puisqu'elle est
publique et que la base ne contient qu'un administrateur.

## 7. Reproduire / reconstruire

```bash
# sources (arbre de travail local envoyé par morceaux vérifiés SHA-256)
/opt/schoolsafe-rosario/extensions-test-runtime      # contexte de build, 493 Mo

# image
cd /opt/schoolsafe-rosario/extensions-test-runtime
docker build -f Dockerfile.vps -t schoolsafe-rosario:20261018 .

# migrations SchoolSafe sur la base de l'instance
docker cp 017-accounting-budgets.sql schoolsafe-rosario-db:/tmp/017.sql
docker exec schoolsafe-rosario-db psql -U postgres -d rosario_addons_test -v ON_ERROR_STOP=1 -f /tmp/017.sql

# sortir du mode « installation non confirmée » (sinon chaque connexion admin
# est détournée vers le sondage d'installation Rosario) :
docker exec schoolsafe-rosario-db psql -U postgres -d rosario_addons_test \
  -c "UPDATE config SET config_value='Yes' WHERE title='LOGIN' AND school_id=0;"
```

## 8. Retour arrière

Sans effet sur le reste du VPS :

```bash
docker stop schoolsafe-rosario schoolsafe-rosario-db
# puis retirer le bloc "rosario.179.198.195.15.sslip.io" de /etc/caddy/Caddyfile et recharger Caddy
```

Le volume `schoolsafe-rosario-pgdata` conserve les données tant qu'il n'est pas supprimé.
