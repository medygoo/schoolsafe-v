# Permissions et premier tableau de bord : controles V2

Date : 11 octobre 2026. Sources Rosario et Studio Card inchanges.

## Controles executes

- config.LOGIN=Yes relu dans la base de test.
- Session administrateur observee au portail : Default School, 2026-2027.
- Navigation vers Users/Profiles.php reussie dans cette session : liste Administrateur, Enseignant, Parent, Eleve visible. Aucun enregistrement effectue.
- Requetes SQL executees dans BEGIN READ ONLY sur codex-ref-v1-db uniquement.

## Regles observees, pas des tests de chaque profil

| Fonction native | Administrateur | Enseignant | Parent | Eleve |
| --- | --- | --- | --- | --- |
| Users/Profiles.php | CAN_USE et CAN_EDIT | Aucune regle observee | Aucune regle observee | Aucune regle observee |
| Users/User.php | CAN_USE et CAN_EDIT | CAN_USE | CAN_USE | Aucune regle observee |
| Students/Student.php | CAN_USE et CAN_EDIT | CAN_USE | CAN_USE | CAN_USE |
| School_Setup/Schools.php | CAN_USE et CAN_EDIT | CAN_USE | CAN_USE | CAN_USE |
| Attendance/TakeAttendance.php | Aucune regle exacte observee | CAN_USE | Aucune regle observee | Aucune regle observee |

Une absence sur une route exacte ne conclut pas une interdiction globale : Rosario utilise aussi des variantes de parametres et les programmes enseignants. CAN_USE ne signifie pas acces a tous les dossiers ; les filtres metier doivent etre testes. AllowEdit pour les non-administrateurs depend aussi du contexte $_ROSARIO['allow_edit'] : ne pas traduire automatiquement CAN_EDIT en droit effectif.

Preuves : functions/AllowEdit.fnc.php:29 et 193 ; Modules.php:34 ; profile_exceptions de la base de test.

## Contrat de presentation a respecter

Tableau de bord -> Vue generale -> misc/Portal.php -> Dashboard() -> fonctions Dashboard par module.

- ProgramFunctions/Dashboard.fnc.php:28 : construction selon modules actifs et menu accessible au profil.
- ProgramFunctions/DashboardModule.fnc.php:76 : controle AllowUse sur le lien de destination.
- ProgramFunctions/DashboardModule.fnc.php:197 : DashboardStudents -> modules/Students/includes/Dashboard.inc.php -> DashboardDefaultStudents.
- ProgramFunctions/DashboardModule.fnc.php:214 : DashboardUsers -> modules/Users/includes/Dashboard.inc.php -> DashboardDefaultUsers.
- ProgramFunctions/DashboardModule.fnc.php:265 : DashboardAttendance -> modules/Attendance/includes/Dashboard.inc.php -> DashboardDefaultAttendance.

Ces fonctions produisent du HTML natif, pas une API JSON confirmee. Le preview du port 5187 ne peut pas leur demander directement un objet de donnees. Ne pas ajouter d'API ni de second login sans contrat d'integration valide.

Les details par classe et sexe, la presence quotidienne des enseignants et le top 10 de l'ecole restent a rapprocher des fonctions metier et des definitions du proprietaire. Leur existence n'est pas prouvee par la seule presence de tables attendance ou grades. Ne pas les remplir avec des chiffres fictifs dans une vue dite raccordee.

## Suite necessitant une session de test adaptee

1. Connexion avec le profil enseignant : consultation autorisee, route profils refusee, saisie des presences selon affectation.
2. Connexion avec parent puis eleve : verifier dossiers lies et interdiction d'acces aux autres dossiers.
3. Deuxieme ecole de test explicitement autorisee pour tester l'isolation ; la base actuelle n'en a qu'une.
4. Deconnexion, expiration et CSRF a tester sans reutiliser les cookies de l'administrateur.
5. Appliquer les composants existants dans une couche de presentation separee sous l'origine Rosario, sans modifier le coeur ni les fichiers partages. Raccordement effectif non realise dans ce controle.

ADMIN_PROFILES_PAGE=VERIFIED_READ_ONLY

TEACHER_PARENT_STUDENT_E2E=NOT_VERIFIED

DASHBOARD_BINDING=NOT_IMPLEMENTED
