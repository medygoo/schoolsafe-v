# Verification enseignant locale

Date : 11 octobre 2026. Controles navigateur, aucune modification des comptes ou droits.

- Portail misc/Portal.php : profil enseignant confirme, Default School, annee 2026-2027.
- Selecteur de classe : Aucun cours trouve.
- Navigation directe vers Users/Profiles.php : retour automatique a misc/Portal.php ; aucun editeur de profils affiche. Refus observe pour cette route dans cette session.
- Navigation directe vers Attendance/TakeAttendance.php : ecran Saisir les absences affiche, avec erreur Vous ne pouvez pas saisir les absences pour cette classe.
- Aucun appel enregistre. La consultation de l'ecran ne valide pas la saisie des presences.
- Affectation de cours/classe a verifier pour ce compte avant de tester l'appel. Le selecteur vide ne constitue pas a lui seul une preuve de panne du moteur.

## Suite

1. Dans Rosario natif, verifier et preparer un cours avec affectation de l'enseignant, une tranche horaire autorisant l'appel et des eleves inscrits, pour les tests locaux uniquement.
2. Reexecuter le parcours autorise et comparer aux restrictions d'autres classes.
3. Tester parent et eleve dans leurs sessions propres.
4. Le raccordement du tableau de bord SchoolSafe reste non realise ; aucun contournement des permissions ni remplacement du moteur n'est autorise par ces tests.

TEACHER_LOGIN=VERIFIED

TEACHER_PROFILES_ROUTE_DENIAL=OBSERVED

TEACHER_ATTENDANCE_SAVE=NOT_TESTED
