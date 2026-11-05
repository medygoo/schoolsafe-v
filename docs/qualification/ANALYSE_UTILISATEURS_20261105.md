# Utilisateurs — analyse en profondeur et propositions (5 novembre 2026)

Méthode verrouillée : `docs/DECISIONS.md`, 5 novembre 2026. Sources :
- le code (worktree `SchoolSafeProdDrift-20261103`, références `fichier:ligne`) ;
- les pages ouvertes avec le compte de direction, **sans rien enregistrer** ;
- la base de production en lecture seule.

**Rien n'est corrigé : toutes les propositions attendent la validation du propriétaire.**

## 1. Ce qui existe aujourd'hui

### Ajouter un utilisateur — deux chemins différents

| | Formulaire d'origine (lien du menu) | Assistant SchoolSafe `/ecoles/3/utilisateurs/nouveau` |
|---|---|---|
| Accès | Menu Utilisateurs › Ajouter un utilisateur | **dans aucun menu** |
| Rôle | liste de profils techniques, dont « Obsolète — Accès école 3… » | 7 catégories claires : Direction, Enseignant, Administrateur scolaire, Parent/tuteur, Gardien, Personnel administratif, Cantine |
| Identifiant | facultatif | obligatoire |
| Mot de passe | **facultatif** (`General_Info.inc.php:187`) | temporaire de 12 caractères, généré automatiquement |
| Doublons | identifiant seulement (`User.php:414-429`) | identifiant, e-mail et téléphone (`SchoolSafeIdentity.fnc.php:90`) |
| Champs RDC | Titre, Prénom, **Second prénom**, Nom, **Suffixe (Jr, Sr, II…)** | Prénom, Nom, Identifiant, E-mail, Téléphone |
| Textes | — | jargon technique (« Teacher natif », « profil 033 », « Users/User.php ») |

Procédure de l'assistant :
1. Choisir la catégorie et remplir le formulaire.
2. Contrôle des doublons, bloquant tant que la case « doublon vérifié » n'est pas cochée.
3. Écran de confirmation.
4. Création par le moteur d'origine : mot de passe chiffré, insertion dans `staff`, puis liaison Keycloak.

À la première connexion, le changement de mot de passe est **imposé** : `FORCE_PASSWORD_CHANGE_ON_FIRST_LOGIN = Y`,
vérifié en production.

### Les autres pages

- **Fiche d'un utilisateur** : recherche, puis 3 onglets (Informations générales, Emploi du temps, Cantine). Le
  bouton Supprimer demande une confirmation (`User.php:817-825`), mais il **disparaît sans explication** quand la
  suppression est impossible (`User.php:974-983`). Changer le mot de passe ici déconnecte les autres sessions.
- **Relier un parent à ses enfants** : choisir le parent, puis cocher les enfants. Le retrait demande une
  confirmation et vérifie l'école.
- **Champs de la fiche utilisateur** : ajouter ou supprimer des champs, avec confirmation. **Effet sur toutes les
  écoles.**
- **Mes préférences** : **8 onglets** (Affichage, Impression, Liste d'élèves, Mot de passe, Champs élèves,
  Widgets, Champs utilisateur, Widgets utilisateur). L'ancien mot de passe est exigé.

### Ce que montre la base (15 comptes)

| Constat | Nombre |
|---|---|
| Comptes jamais connectés | 8 sur 15 |
| Parents sans téléphone (et aucun avec e-mail) | 8 sur 9 |
| Compte du personnel sans école | 1 |
| Suffixe rempli (Medy Lomela « Jr », probablement par erreur) | 1 |
| Profils de base en anglais | Administrator, Teacher, Student |
| Profil technique « Obsolète — Accès école 3… » encore visible dans les listes | 1 |
| Enseignant avec les droits « Personnel administratif » (Medy Lomela) | 1 |
| Homonymes, e-mail ou téléphone en double, compte sans mot de passe | 0 |

## 2. Propositions de corrections, classées par importance

### A. Sécurité et données (priorité 1)

| # | Aujourd'hui | Problème | Correction proposée |
|---|---|---|---|
| A1 | Le formulaire d'origine accepte un compte sans mot de passe (`General_Info.inc.php:187`, `Password.php:28`). | Le compte n'a pas de mot de passe et aucun avertissement ne le signale. | Créer les comptes **par l'assistant** (B1), qui génère toujours un mot de passe temporaire ; sinon, rendre le mot de passe obligatoire. |
| A2 | La limite de **6 parents par élève** n'existe que côté élève (`Students/AddUsers.php:16-50`), pas côté parent (`Users/AddStudents.php:44-55`). | Une règle de l'école peut être contournée. | Appliquer la même limite des deux côtés, et vérifier que le compte relié est bien un parent. |
| A3 | Côté élève, le retrait d'un parent ne vérifie pas l'école et n'affiche pas le nom (`Students/AddUsers.php:66-82`). | Retrait possible hors de l'école, confirmation peu claire. | Mêmes contrôles que côté parent : école vérifiée, nom affiché. |
| A4 | La robustesse du mot de passe n'est vérifiée que dans le navigateur (`Inputs.php:201`). | Elle se contourne facilement. | Vérifier aussi sur le serveur, à la création et au changement. |
| A5 | Changer son mot de passe dans Mes préférences ne déconnecte pas les autres sessions (`Preferences.php:127-138`). | Un appareil perdu reste connecté. | Faire comme la fiche : déconnecter les autres sessions. |
| A6 | Le mot de passe temporaire de l'assistant transite en clair dans un champ caché (`ecole-utilisateurs.php:335`) et se perd si la création échoue. | Exposition du mot de passe ; l'utilisateur reste bloqué. | Générer le mot de passe sur le serveur au moment de l'enregistrement, l'afficher une seule fois, et revenir à l'assistant en cas d'erreur. |
| A7 | Supprimer un parent efface ses liens avec les élèves sans les montrer (`User.fnc.php:27`). | Un élève peut perdre son seul parent sans que personne le voie. | Lister les enfants concernés dans la confirmation, et signaler l'élève qui n'aurait plus de parent. |
| A8 | Un directeur peut modifier les champs de la fiche utilisateur de **toutes les écoles** (`UserFields.php:28, 164`). | Une école peut changer les fiches des autres. | Réserver cette page à la plateforme (ProDéli). **C'est un changement de droit : décision du propriétaire.** |

### B. Cohérence

| # | Problème | Correction proposée |
|---|---|---|
| B1 | Deux chemins pour créer un compte : le plus sûr (l'assistant) n'est dans aucun menu. | **« Ajouter un utilisateur » ouvre l'assistant SchoolSafe** : rôle clair, contrôle des doublons, mot de passe temporaire. Le formulaire d'origine sert seulement à modifier une fiche. |
| B2 | L'assistant affiche du jargon (« Teacher natif », « profil 033 », « Users/User.php », « Custom/CreateParents »). | Remplacer par une phrase simple par catégorie, par exemple « Enseignant : voit ses classes, fait l'appel et donne les devoirs ». |
| B3 | Les titres de page ne suivent pas le menu : « Informations utilisateur », « Association élèves parents », « Champs utilisateur ». | Mêmes noms que le menu : Fiche d'un utilisateur, Relier un parent à ses enfants, Champs de la fiche utilisateur. |
| B4 | La liste des profils propose des profils inutilisables, refusés seulement à l'enregistrement (`General_Info.inc.php:254`, `User.php:346`), dont « Obsolète… ». | Ne proposer que les profils valables pour l'école. |
| B5 | Profils de base en anglais (Administrator, Teacher, Student) ; « Gardien » est un profil de type parent. | Renommer : Administrateur, Enseignant, Élève ; « Gardien » → « Tuteur ». **Données de production : SQL transactionnel avec retour arrière, après accord.** |
| B6 | Le bouton Supprimer disparaît sans explication (`User.php:974-983`). | Afficher la raison, par exemple « Cet enseignant a des cours : retirez-le de ses cours ou désactivez le compte ». |
| B7 | Message de la limite de 6 parents sans accents et non traduit ; plusieurs messages en anglais (`User.php:205`, `General_Info.inc.php:290, 313`). | Traduire et accentuer. |
| B8 | Le doublon de nom n'est jamais détecté (`User.php:414-429`). | Avertir si le prénom et le nom existent déjà (résolu par B1 pour la création). |

### C. Clarté du formulaire (normes RDC)

| # | Problème | Correction proposée |
|---|---|---|
| C1 | « Second prénom » : en RDC, on écrit **Nom, Postnom, Prénom**. | Renommer « Second prénom » en **Postnom** et l'afficher dans l'ordre RDC. |
| C2 | Champ « Suffixe » (Jr, Sr, II, III), sans usage en RDC, et rempli par erreur pour Medy Lomela. | Retirer ce champ du formulaire ; vider la valeur de Medy (donnée, après accord). |
| C3 | La civilité propose « Mme » deux fois. | M., Mme, Mlle, Dr — une seule fois chacune. |
| C4 | Mes préférences : 8 onglets techniques pour un enseignant ou un parent. | Enseignant et parent : **Mot de passe** et **Langue / affichage** seulement ; les autres onglets restent pour la direction. |
| C5 | 8 parents sur 9 sans téléphone, aucun e-mail. | **Téléphone obligatoire pour un parent** (au moins un moyen de joindre la famille), contrôlé par l'assistant. |
| C6 | 1 compte du personnel sans école ; un enseignant avec les droits « Personnel administratif ». | À corriger par la direction dans la fiche (liste à fournir). |

## 3. Propositions d'amélioration ou d'ajout (sur accord, chacune dans son propre lot)

1. **Fiche « Identifiants de connexion » à imprimer** : nom, identifiant et mot de passe temporaire, remise en main
   propre au parent qui n'a ni e-mail ni téléphone. C'est le cas le plus fréquent dans les écoles.
2. **Désactiver un compte au lieu de le supprimer** : le compte ne peut plus se connecter, mais son historique
   (notes, appels, paiements) est gardé. C'est la norme des logiciels scolaires.
3. **Journal des comptes** : qui a créé, modifié, supprimé ou relié un compte, et quand. Aujourd'hui, seuls les
   changements de droits sont tracés (`SchoolSafeSchoolAccess.fnc.php:246`).
4. **Blocage d'un compte après plusieurs mauvais mots de passe**. Aujourd'hui, 30 essais sont permis par appareil,
   et rien n'est compté par compte (`index.php:176-197`).
5. **Liste « comptes jamais connectés »** (8 sur 15 aujourd'hui), avec réimpression des identifiants.
6. **Créer les comptes parents à partir de la fiche élève par téléphone**. L'outil actuel exige un e-mail, et aucun
   parent n'en a.

## 4. Ordre de réalisation proposé

1. **Lot U1, sécurité** : A1 à A7. Code seulement, sans changement de droit.
2. **Lot U2, cohérence et clarté** : B1 à B4, B6 à B8 et C1 à C4.
3. **Lot U3, données**, après accord : B5 (renommer les profils), C2 (suffixe de Medy), retrait du profil
   « Obsolète », C6.
4. **Décisions du propriétaire** : A8 (droit sur les champs), C5 (téléphone obligatoire), et chaque ajout de la
   partie 3.

## Garde-fous

Aucune page effacée, aucun compte réel créé, aucun mot de passe saisi pendant l'analyse. Chaque lot sera testé, puis
mis en ligne par image vérifiée, après accord.

## Réalisation — lot U1 sécurité (5 novembre 2026)

- A1 à A7 réalisés. Commit `a04b46a` (worktree `SchoolSafeProdDrift-20261103`). Nouveau fichier
  `ProgramFunctions/SchoolSafePasswordPolicy.fnc.php`. La création Keycloak (`sskc_intent`) est exemptée de A1,
  car sa connexion passe par Keycloak.
- Image candidate : syntaxe PHP correcte pour les 6 fichiers. Règle de mot de passe testée : « abc »,
  « motdepasse », « 12345678 » et un mot de passe identique à l'identifiant sont refusés ; « medy2026 » et
  « Kinshasa2026 » sont acceptés ; 0 refus sur 5 000 mots de passe temporaires de l'assistant.
- **En production** : image `schoolsafe-rosario:utilisateurs-u1-20261105`. Contrôle `verify` : service sain, 200,
  0 erreur PHP, autres services inchangés, 346 sessions conservées, 0 révocation.
- **Non rejoué** : les vraies créations, liaisons et suppressions, pour ne pas toucher aux données réelles.
  Essai conseillé : créer un compte d'essai depuis l'assistant, puis changer un mot de passe dans Mes préférences.

## Nouveau constat, à traiter dans U2

- **B9** : sans session, l'assistant `/ecoles/{id}/utilisateurs…` renvoie vers `index.php` en adresse relative.
  Le navigateur aboutit à `/ecoles/3/utilisateurs/index.php`, qui répond « Not Found ». Correction : renvoyer
  vers `/index.php`. Défaut ancien, sans lien avec U1.
