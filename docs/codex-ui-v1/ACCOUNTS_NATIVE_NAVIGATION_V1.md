# Comptes : navigation native Rosario

La vue locale ajoute un groupe Comptes et droits comportant six acces
independants. Chaque acces exige sa presence dans le menu courant et
AllowUse avec la cle complete du programme natif.

| Acces | Programme natif | Fonction |
| --- | --- | --- |
| Consulter les utilisateurs | Users/User.php | Recherche et fiche utilisateur |
| Ajouter un utilisateur | Users/User.php&staff_id=new | Formulaire natif de creation |
| Profils utilisateurs | Users/Profiles.php | Definitions de profils |
| Droits d acces | Users/Exceptions.php | Permissions utilisateur |
| Associer les eleves aux parents | Users/AddStudents.php | Association native |
| Mes preferences | Users/Preferences.php | Preferences du compte connecte |

Preuve : modules/Users/Menu.php distingue expressivement l'ajout de la
consultation pour ne pas perdre la permission Add. Les menus teacher
et parent exposent User.php et Preferences.php, pas l'administration
des profils. Le profil student retire User.php du menu parent.

Aucune nouvelle authentification ni traduction de roles SchoolSafe
n'est introduite. Les programmes ouverts conservent leurs controles
Rosario. Aucun compte, mot de passe, profil ou droit n'est modifie.

Ces six raccourcis ne sont pas six nouvelles destinations du catalogue
de 211 lignes. Ils decomposent l'acces composite Comptes et droits.
La preview Codex et ses formulaires ne sont pas encore raccordes par
ces liens ; la vue additive utilise les formulaires natifs existants.

Les comptes enseignants, parents et autres profils doivent suivre les
choix permis par le formulaire Rosario, pas une liste cliente inventee.
Le role d'Appsmith dans la gestion inter-ecoles reste a valider separement.

## Verification locale effectuee

La session admin affiche les six liens. Le clic Ajouter un utilisateur
ouvre le formulaire natif Users/User.php&staff_id=new. Les choix observes
sont Parent, Enseignant, Administrateur et Sans acces. Le formulaire
comporte identite, identifiant, mot de passe, droits et ecoles.
Aucune saisie et aucun clic Enregistrer n'ont ete effectues.
La creation d'un compte eleve ne doit pas etre inventee dans cette liste :
elle releve du parcours eleve a examiner separement.
PHP lint passe pour la vue modifiee.
