# Raccordements locaux V4

## Deux correspondances supplementaires

| Destination SchoolSafe | Ecran natif | Portee | Preuve |
| --- | --- | --- | --- |
| rosario-029 - Evaluations et notes | Grades/Grades.php | Carnet de notes du cours et de la periode de l'enseignant. Ne couvre pas toutes les evaluations. | modules/Grades/Menu.php, entree teacher ; modules/Grades/Grades.php, UserCoursePeriod et presentation Gradebook |
| rosario-082 - Parametres | School_Setup/Configuration.php | Parametres natifs de l'ecole, pas les parametres de tous les moteurs SchoolSafe. | modules/School_Setup/Menu.php, entree admin ; modules/School_Setup/Configuration.php, onglets school/modules/plugins |

La vue filtre ces liens avec le menu natif et AllowUse. Ouvrir un ecran
ne certifie pas l'enregistrement de son formulaire. Aucun reglage de
securite et aucune note n'ont ete modifies pendant cette intervention.

## Correspondances volontairement non ajoutees

- Gestion des autorisations (rosario-079) appartient au groupe Scanner :
  ne pas la confondre avec Users/Exceptions.php.
- Cycles (rosario-084) ne doit pas etre assimile sans preuve aux niveaux
  scolaires de School_Setup/GradeLevels.php.
- Comptes et droits (rosario-083) implique plusieurs ecrans et des
  permissions distinctes, notamment l'ajout d'utilisateur. Un lien
  Users/User.php ne couvrirait pas toute cette fonctionnalite.
- Email (rosario-073) ne doit pas appeler NotifyParents, qui reinitialise
  des mots de passe.

## Comptage

211 destinations : 24 correspondances de navigation, 1 Studio Card
preserve, 186 correspondances encore non confirmees. Ces 186 lignes
ne constituent pas un inventaire de 186 visuels manquants.

Le carnet de notes enseignant necessite un cours et des eleves affectes
pour tester un parcours complet. La base locale precedemment observee
ne contient aucun cours. La preview de demonstration n'est pas remplacee.

## Verification de cette intervention

- Generateur : 211 lignes, 24 correspondances, aucun chemin absent.
- Session administrateur : Parametres visible ; Grades/Grades.php masque
  car absent du menu de ce profil. Cela ne remplace pas un test enseignant.
- Clic Parametres : page native Configuration chargee, titre Parametres
  de l'ecole et formulaire Enregistrer presents. Aucun enregistrement.
- Test CLI isole test-menu-lookup.php : menu croise, menu ordinaire,
  programme absent, permission ajoutee distincte, menu vide.
