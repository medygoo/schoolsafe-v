# Session et permissions Rosario : verification locale

Date : 11 octobre 2026. Controle sans modification de compte, de source ou de permission.

## Constats verifies

- Le navigateur affiche Admin A Administrator, profil administrateur, Default School et annee 2026-2027 sur misc/Portal.php.
- La base de test indique encore config.LOGIN = No et staff_id=1 avec LAST_LOGIN NULL.
- FORCE_PASSWORD_CHANGE_ON_FIRST_LOGIN est vide.
- Les trois comptes staff admin, teacher et parent ont LAST_LOGIN NULL.
- Ces observations ne prouvent pas que le mot de passe initial a ete change. Aucune valeur de mot de passe ni de hash n'a ete lue ou divulguee lors de ces controles.
- Une requete sans cookie vers Modules.php?modname=Users/Profiles.php retourne HTTP 200 avec un document de 214 caracteres contenant une redirection HTML vers index.php?modfunc=logout, sans editeur de profils. Ce code HTTP seul ne doit pas etre interprete comme un acces autorise.

## Permissions presentes dans la base

| Profil ID | Regles | CAN_USE=Y | CAN_EDIT=Y |
| --- | --- | --- | --- |
| 0, eleve selon le code | 33 | 33 | 0 |
| 1, administrateur | 138 | 137 | 137 |
| 2, enseignant | 48 | 48 | 4 |
| 3, parent | 36 | 36 | 0 |

Il s'agit de nombres de regles, pas de boutons ni de fonctions testees. Des regles comprennent des variantes de parametres.

## Preuves source

- Warehouse.php:226,253,265 : session RosarioSIS, cookies HttpOnly et SameSite ; Secure conditionnel a HTTPS.
- Warehouse.php:288-321 : absence de STAFF_ID et STUDENT_ID provoque la redirection HTML et l'arret du traitement des modules.
- Modules.php:34 : passage par AllowUse avant chargement du module.
- functions/AllowEdit.fnc.php:193,278 : AllowUse et lecture de profile_exceptions ou staff_exceptions ; profil 0 correspond aux eleves.
- ProgramFunctions/FirstLogin.fnc.php:28-60 : enregistrement du nouveau mot de passe, LAST_LOGIN et activation LOGIN lors du traitement initial approprie.
- ProgramFunctions/FirstLogin.fnc.php:80-123 : formulaire initial et option generale de changement de mot de passe.
- modules/misc/Portal.php:3 : fonctions du tableau de bord dans ProgramFunctions/Dashboard.fnc.php.

## Premier raccordement recommande

Menu Tableau de bord -> Vue generale -> misc/Portal.php et ProgramFunctions/Dashboard.fnc.php.

Conserver les calculs, filtres d'ecole et d'annee, session et autorisations Rosario. Appliquer les composants SchoolSafe dans une couche de presentation separee. Ne pas creer une seconde authentification Node ou Appsmith pour les utilisateurs de l'ecole.

Le preview SchoolSafe sur 5187 et Rosario sur 18082 sont actuellement des origines differentes : le preview ne peut pas etre declare raccorde simplement parce que l'utilisateur est connecte a Rosario. Le contrat de presentation sous l'origine Rosario reste a definir et a tester avant integration aux fichiers partages.

Appsmith local gere le controle administratif ; son compte n'est pas automatiquement un compte Rosario. Aucun partage de session, SSO ou API de gestion n'est confirme par cette verification.

## Avant raccordement declare operationnel

1. Le proprietaire termine la premiere connexion Rosario et choisit lui-meme le nouveau mot de passe. Recontroler LOGIN et LAST_LOGIN ensuite.
2. Tester deconnexion et reconnexion avec intervention du proprietaire pour son mot de passe ; ne pas collecter ses identifiants.
3. Tester les acces permis et refuses des profils enseignant, parent et eleve ; utiliser des comptes de test explicitement valides.
4. Tester l'isolation entre deux ecoles : impossible a conclure avec la seule ecole presente actuellement.
5. Verifier expiration de session, CSRF et parcours de recuperation dans cet environnement de test.
6. Raccorder une premiere vue et comparer ses chiffres aux resultats natifs. Toute donnee non disponible reste indiquee comme telle, sans valeur fictive presentee comme reelle.

SESSION_ADMIN=OBSERVED

UNAUTHENTICATED_MODULE_GUARD=VERIFIED_FOR_PROFILES_PAGE

FIRST_LOGIN_FINALIZATION=NOT_CONFIRMED

ROLE_PERMISSION_E2E=NOT_VERIFIED

DASHBOARD_CONNECTION=NOT_IMPLEMENTED

PRODUCTION_READY=NO
