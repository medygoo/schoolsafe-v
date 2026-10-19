# Suspension de compte — anomalie corrigée et décision d'architecture (18 octobre 2026)

## 1. Décision : un seul système

Pas de deuxième architecture. SchoolSafe/Rosario gère déjà le multi-écoles nativement :

- `staff.SCHOOLS` porte la portée d'un compte (`',1,2,'` = écoles 1 et 2 ; vide = toutes) ;
- `schoolsafe_account_state` porte l'état **par personne ET par école** (`school_id = 0` = toutes) ;
- `schoolsafe_school_state` coupe un établissement entier sans supprimer une donnée ;
- une seule base peut héberger plusieurs écoles, chacune avec ses classes, élèves et personnel.

Conclusion : la « centrale PRODELI » est une **couche de gestion** au-dessus de ce système, pas une
seconde pile. Elle n'est utile que s'il existe réellement plusieurs installations séparées ; sinon la
console intégrée suffit. L'authentification reste native Rosario.

## 2. L'anomalie de suspension

Deux chemins d'administration existaient et ne faisaient pas la même chose :

| Chemin | Avant |
|---|---|
| Console web `admin-console.php` | `SchoolSafeAccountSet(..., suspended, revoke)` → état + **epoch +1**, session tuée, journal |
| Bridge service `admin-console-bridge.php` | `PROFILE = 'none'` seulement → **aucune révocation**, et le profil restait « No Access » |

Conséquences réelles :
1. une session déjà ouverte **continuait** après une suspension par le bridge ;
2. la console web refusait ensuite de réactiver le compte (« Retablissez son profil dans l'écran
   Utilisateurs »), parce que `PROFILE = 'none'` n'était pas un état qu'elle pouvait changer ;
3. deux sources de vérité pour la même notion.

## 3. Correction (additive, un seul fichier)

`app/modules/codex-ui-v1/extensions-test-runtime/admin-console-bridge.php`

- `suspend` utilise désormais **l'état de compte** : `SchoolSafeAccountSet(username, school_id,
  'suspended', raison, fin éventuelle, revoke=true)`. L'epoch est incrémenté → les sessions ouvertes
  tombent à la requête suivante. Le `PROFILE` natif **n'est plus touché** quand la migration `014` est
  appliquée.
- `activate` remet l'état `active` (epoch +1 pour qu'aucune session antérieure ne reprenne) et
  **répare** un compte resté en `PROFILE = 'none'` par l'ancien mécanisme.
- Nouveaux paramètres acceptés : `school_id` (0 = toutes les écoles, sinon l'école doit exister pour
  l'année), `reason`, `until` (date ou date-heure, format validé).
- Réponse enrichie : `state`, `school_id`, `until`, `session_revoked`, `session_epoch`,
  `state_source` (`account_state` ou `profile`).
- `list-admins` renvoie maintenant `STATE` et `STATE_SOURCE` par compte : une suspension par état et
  une suspension par profil se lisent pareil.
- **Repli** : si la migration `014` n'est pas appliquée, le bridge retombe sur `PROFILE = 'none'`
  (comportement d'avant) et le signale via `state_source: "profile"`, `session_revoked: false` —
  aucune fausse promesse.

Aucune modification du cœur Rosario, aucune table nouvelle, aucune migration.

## 4. Preuves — test de bout en bout (instance locale L1, base réelle)

Compte de test créé via le bridge, puis supprimé après les essais.

| Étape | Résultat observé |
|---|---|
| `create-admin` | `201`, `first_login_force: true` |
| Connexion avant suspension | `302` (session ouverte) |
| `suspend` | `state: suspended`, `session_revoked: true`, `session_epoch: 1`, `state_source: account_state` |
| Requête du tableau de bord **avec la session déjà ouverte** | `302` → `index.php?reason=account_suspended` — **session coupée** |
| Connexion pendant la suspension | `200`, message « Ce compte est suspendu. Contactez l'administration de votre etablissement. » |
| `activate` | `state: active`, `session_epoch: 2`, `PROFILE` resté `admin` |
| Connexion après réactivation | `302` (nouvelle session) |
| Ligne en base | `probe.bridge | 0 | active | 4` (epoch incrémenté à chaque changement) |
| Nettoyage | compte, états et journal de test supprimés (0 ligne restante) |

`php -l` sur le fichier modifié : **aucune erreur de syntaxe**.

## 5. État de déploiement

Le correctif est dans le dépôt et testé, **pas encore dans l'image de production** : le bridge n'est
pas activé sur `app.shoolsafe.tech` (`SCHOOLSAFE_CONSOLE_TOKEN` absent → il répond `503`, échec fermé).
La console web, elle, applique déjà la bonne logique et tourne en production.

Pour l'activer quand la centrale sera branchée :
1. générer un jeton et l'ajouter à `/opt/schoolsafe-rosario/app.env` (`SCHOOLSAFE_CONSOLE_TOKEN`) ;
2. reconstruire l'image avec ce fichier et recréer le conteneur ;
3. appeler `admin-console-bridge.php` avec l'en-tête `X-SchoolSafe-Console-Token`.

## 6. Reste à faire pour compléter la console PRODELI

- gérer les profils autres qu'administrateur (enseignant, parent, élève) : la table d'état est prête,
  seule l'exposition manque ;
- exposer `school_state` (suspension d'établissement) côté bridge, comme la console web le fait.

## 7. Correctif multi-écoles (19 octobre 2026)

Le gestionnaire PRODELI est la **console native** `admin-console.php` — AppSmith n'est pas
nécessaire. Trois défauts corrigés, sans nouvelle table ni migration.

### 7.1 État le plus restrictif + détail par école

`list-admins` (bridge) et la console affichaient l'état de la **première** école trouvée : un compte
suspendu dans l'école 2 mais actif dans l'école 1 s'affichait « Actif ».

- `SchoolSafeAccountStates()` : détail par personne et par école (ligne `0` = toutes les écoles) ;
- `SchoolSafeAccountWorstState()` : `suspendu > expiré > lecture seule > actif` ;
- bridge : `STATE` = pire état, `SCHOOL_STATES` = détail structuré, `STATE_DETAIL` = texte lisible ;
- console : badge = pire état, ligne « Ecole A : Actif · Ecole B : Suspendu » sous le badge ;
- le propriétaire peut viser **une seule école** du compte dans le formulaire d'état
  (`state_school_id`), les autres gardent leur accès. Une école hors du compte est refusée.

### 7.2 Une suspension ciblée ne coupe plus les autres écoles

Deux causes réelles :

| Cause | Avant | Après |
|---|---|---|
| Connexion | `SchoolSafeAccountEffective` renvoyait l'état de la première école → refus global | `SchoolSafeAccountAccessibleSchools` : refus seulement si **aucune** école ne reste ouverte ; ligne globale coupée = tout coupé |
| Session | époque = **max** de toutes les lignes → suspendre l'école 1 coupait la session ouverte dans l'école 2 | époque **par école** (`SchoolSafeAccountSchoolEpoch`), suivie par session (`SchoolSafeEpochs`) ; révocation globale (école 0) coupe toujours tout |

En complément :

- à la connexion, l'école d'entrée est l'école habituelle du compte si elle reste ouverte, sinon la
  première école utilisable ;
- si la session courante est dans une école suspendue, la garde ramène la personne dans une école
  ouverte au lieu de détruire la session ;
- le sélecteur d'école refuse une école suspendue (`403`), sans toucher à la session.

### 7.3 Preuves — `node app/qa-multi-school-bridge.cjs`

Pile Docker réelle (Apache/PHP 8.3/PostgreSQL), compte `qa.multi.*` créé puis supprimé, bridge lancé
en PHP intégré avec jeton temporaire.

| Étape | Résultat |
|---|---|
| RED avant correctif | **3 PASS / 10 FAIL** (bridge sans `SCHOOL_STATES`, connexion refusée, session coupée) |
| GREEN après correctif | **16 PASS / 0 FAIL** |
| `list-admins` : école 1 suspendue / école 2 active | `STATE=suspended`, `1:suspended / 2:active` |
| `list-admins` : école 1 active / école 2 suspendue | `STATE=suspended` (le pire état prime, pas le premier trouvé) |
| `list-admins` : lecture seule ciblée | `STATE=readonly` |
| Ligne globale suspendue | compte suspendu partout, détail `0:suspended` propagé aux écoles |
| Connexion avec l'école 1 suspendue | acceptée, école d'entrée = école 2 |
| Suspension de l'école 1 pendant une session ouverte dans l'école 2 | session **conservée** (portail 200, aucune redirection `reason=account_*`) |
| Changement vers l'école 1 suspendue | **403**, session conservée dans l'école 2 |
| Toutes les écoles suspendues, ou ligne globale coupée | connexion refusée, message de suspension |
| Console native : suspension ciblée par le propriétaire | 302, ligne école 1 `suspended`, **aucune** ligne globale, connexion école 2 acceptée |
| Console native : école étrangère au compte | refus explicite, 0 ligne créée |
| Bridge sans jeton / mauvais jeton | `401`, aucune liste exposée |

Non-régression : `node app/qa-l1-account-state.cjs` → **36 PASS / 0 FAIL**.
`php -l` OK sur `admin-console.php`, `admin-console-bridge.php`, `index.php`, `Side.php`,
`ProgramFunctions/SchoolSafeAccountState.fnc.php`.

### 7.4 Ouvrir le gestionnaire PRODELI

1. Se connecter à `https://app.shoolsafe.tech/` avec un **administrateur global**
   (`staff.SCHOOLS` vide = propriétaire de la plateforme).
2. Ouvrir `https://app.shoolsafe.tech/admin-console.php` (lien direct ; en local :
   `http://127.0.0.1:8081/admin-console.php`).
3. Le tableau « Comptes et accès » liste les comptes du périmètre, avec l'état le plus restrictif et
   son détail école par école ; les colonnes d'action permettent de changer l'état (école visée
   comprise), de révoquer les sessions et de régénérer un mot de passe.
4. Un directeur (administrateur délégué, `SCHOOLS` non vide) voit la même page, limitée à ses écoles ;
   la création d'administrateur de plateforme et l'état d'établissement lui sont refusés.

Aucun jeton de service, aucune AppSmith : la console est une page PHP du runtime Rosario.
