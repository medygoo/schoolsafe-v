# SchoolSafe — Console AppSmith (gestion des administrateurs et des écoles)

> **Note du 19 octobre 2026 — AppSmith n'est pas nécessaire.** Le gestionnaire PRODELI est la
> **console native** `admin-console.php` du runtime Rosario (aucun service externe, aucun jeton) :
> elle liste les comptes (filtre par école), affiche l'état le plus restrictif et son détail par
> école, et permet de changer un état (école visée comprise), révoquer des sessions, régénérer un
> mot de passe, **créer une école** et **créer un directeur d'école** (direction : principal et
> adjoints). Voir `ops/deployment/COMPTES-SUSPENSION-20261018.md` §7.4 pour l'ouverture et
> `ops/deployment/GESTION-ECOLES-20261019.md` pour l'école et la direction. Ce document reste la
> référence du pont de service `admin-console-bridge.php`, utilisé seulement si une console externe
> est branchée un jour.

Date : 15 octobre 2026. Endpoint : `admin-console-bridge.php` (déployé sur le VPS, servi en
HTTPS via Caddy). Aucune modification du cœur Rosario : l'endpoint réutilise les mécanismes
natifs (hachage `$6$`, INSERT staff identique à `Users/User.php`, forçage de première
connexion, suspension `PROFILE='none'`, création d'école à la `CopySchool.php`).

## 0. L'application AppSmith est DÉJÀ CONSTRUITE

L'application « SchoolSafe Console » existe dans l'espace « LOMS's apps » de
`https://control.shoolsafe.tech` (créée et recettée par ZCode le 15/10/2026) :
- 6 requêtes API sur la datasource intégrée (jeton en en-tête, chiffré par AppSmith) :
  `listAdmins` (GET, exécutée au chargement), `createAdmin`, `resetPassword`,
  `suspendAdmin`, `activateAdmin`, `createSchool` (POST).
- Page1 « Administrateurs » : titre, table `tblAdmins` (ID, Utilisateur, Prénom, Nom,
  Email, Statut, Dernière connexion), formulaire de création (prénom, nom, username,
  email, IDs écoles), boutons Créer / Réinitialiser / Suspendre / Réactiver, et modale
  `mdlTemp` affichant le mot de passe temporaire UNE seule fois.
- URL d'édition : `https://control.shoolsafe.tech/app/schoolsafe-console/page1-6ad08b3a65be568ccb08208f/edit`
- Les deux messages « Cannot read data from createAdmin » du Debug sont bénins : la
  modale référence la réponse d'une requête qui n'a pas encore été exécutée.

Pour une page « Écoles » supplémentaire : reproduire le formulaire `createSchool`
(ref. §1) — la requête existe déjà dans l'application.

## 1. Référence API (pour AppSmith)

URL de base : `https://schoolsafe.179-198-195-15.sslip.io/admin-console-bridge.php`
En-tête obligatoire sur CHAQUE appel : `X-SchoolSafe-Console-Token: <JETON>`
(Jeton stocké dans `/opt/schoolsafe-app/workspace/ops/deployment/.env` sur le VPS — ne jamais
le copier dans un document ni un dépôt ; AppSmith le chiffre dans sa datasource.)
Sans jeton : 401. Jeton absent du serveur : 503 (échec fermé).

| Action | Méthode | Corps JSON | Réponse clé |
|---|---|---|---|
| `list-admins` | GET `?action=list-admins` | — | Tableau des comptes admin (+suspendus) |
| `create-admin` | POST | `{"action":"create-admin","first_name":"…","last_name":"…","username":"…","email":"…","school_ids":[1]}` | `data.temp_password` (**afficher UNE fois**), `first_login_force:true` |
| `reset-password` | POST | `{"action":"reset-password","staff_id":7}` | Nouveau `temp_password` (UNE fois) |
| `suspend` | POST | `{"action":"suspend","staff_id":7}` | `status:suspended` (connexion refusée) |
| `activate` | POST | `{"action":"activate","staff_id":7}` | `status:active` |
| `create-school` | POST | `{"action":"create-school","title":"…","email":"…","slogan":"…","address":"…","city":"…","phone":"…","template_school_id":1,"assign_to_staff_id":7}` | `data.school_id` |

Erreurs : `USERNAME_TAKEN` (409, insensible à la casse), `SCHOOL_NOT_FOUND` (400),
`ACCOUNT_SUSPENDED`/`ALREADY_SUSPENDED`/`ALREADY_ACTIVE` (409), `NAME_REQUIRED`,
`USERNAME_INVALID`, `EMAIL_INVALID`, `IDENTITY_REQUIRED`, `TEMPLATE_NOT_FOUND` (404).

Comportements garantis (mécanismes natifs vérifiés en local puis sur le VPS) :
- Un admin créé/reçoit un mot de passe temporaire ; à sa première connexion il est **forcé**
  d'en choisir un nouveau (`FORCE_PASSWORD_CHANGE_ON_FIRST_LOGIN='Y'` activé, ligne globale) ;
- `reset-password` remet le compte dans le même état de première connexion ;
- La suspension conserve le rôle ; la réactivation restaure `admin` tel quel ;
- La création d'école copie niveaux/periodes/periodes de notation du gabarit + identité
  SchoolSafe (email + slogan obligatoires) ; calendriers, cours et élèves restent à créer
  dans SchoolSafe (comme l'écran officiel).

## 2. Construction de l'application AppSmith (15 minutes)

Sur `https://control.shoolsafe.tech` (connexion administrateur AppSmith) :

1. **Nouvelle application** → nommer « SchoolSafe Console ».
2. **Datasources → REST API** : créer une datasource « SchoolSafeBridge » :
   - URL de base : (voir §1)
   - Headers : `X-SchoolSafe-Console-Token` = le jeton lu sur le VPS
     (`grep SCHOOLSAFE_CONSOLE_TOKEN /opt/schoolsafe-app/workspace/ops/deployment/.env`).
3. **Requêtes** (APIs, toutes sur cette datasource) :
   - `listAdmins` : GET `/admin-console-bridge.php?action=list-admins`
   - `createAdmin` : POST, body JSON
     `{"action":"create-admin","first_name":{{inpFirstName.text}},"last_name":{{inpLastName.text}},"username":{{inpUsername.text}},"email":{{inpEmail.text}},"school_ids":{{inpSchoolIds.text.split(',').map(Number)}}}`
   - `resetPassword` : POST `{"action":"reset-password","staff_id":{{tblAdmins.selectedRow.STAFF_ID}}}`
   - `suspendAdmin` : POST `{"action":"suspend","staff_id":{{tblAdmins.selectedRow.STAFF_ID}}}`
   - `activateAdmin` : POST `{"action":"activate","staff_id":{{tblAdmins.selectedRow.STAFF_ID}}}`
   - `createSchool` : POST, body JSON avec les champs du formulaire école.
4. **Page « Administrateurs »** :
   - Table `tblAdmins` : `Table data` = `{{listAdmins.data.data}}` ;
     colonnes STAFF_ID, USERNAME, FIRST_NAME, LAST_NAME, EMAIL, PROFILE, LAST_LOGIN.
     `PROFILE='none'` = compte suspendu.
   - Formulaire de création (inputs `inpFirstName`, `inpLastName`, `inpUsername`,
     `inpEmail`, `inpSchoolIds` — ex. `1`) + bouton → `run createAdmin` puis
     `run listAdmins` ; afficher le résultat dans une **modale** :
     `{{createAdmin.data.data.username}} — mot de passe temporaire (à communiquer
     maintenant, affiché une seule fois) : {{createAdmin.data.data.temp_password}}`.
   - Boutons sur ligne sélectionnée : Réinitialiser (`resetPassword` + modale du même
     type), Suspendre (`suspendAdmin`), Réactiver (`activateAdmin`), puis `run listAdmins`.
5. **Page « Écoles »** : formulaire (titre, email, slogan, adresse, ville, téléphone,
   école gabarit=1, `assign_to_staff_id`) → `run createSchool` → message avec
   `{{createSchool.data.data.school_id}}`.
6. **Sécurité AppSmith** : l'app reste accessible uniquement aux comptes AppSmith admin ;
   le jeton vit dans la datasource (chiffré par `APPSMITH_ENCRYPTION_PASSWORD` du VPS).

## 3. Recette exécutée (preuves)

Local (préview 8080) puis VPS (HTTPS public) :
- 401 sans/mauvais jeton ; liste OK avec jeton.
- `create-admin` → compte créé, unicité insensible à la casse (409 au doublon).
- Connexion avec le mot de passe temporaire → **redirection forcée first-login** ;
  ancien mot de passe refusé après reset ; nouveau mot de passe choisi → portail OK.
- `suspend` → connexion refusée ; `activate` → connexion rétablie.
- `create-school` → école créée (id 2), 9 niveaux copiés, admin auto-affecté `',1,2,'`.
