# Comptes Rosario et presentation Appsmith / SchoolSafe

Inspection SSH en lecture seule du 2026-10-11. Source : RosarioSIS 12.9.4 installe dans /opt/rosario-schoolsafe/app. Aucun compte consulte ou cree, aucun hash de mot de passe lu, aucune modification du VPS.

## Mecanismes observes

index.php:118 recherche les identifiants dans staff pour l'annee configuree et verifie le mot de passe avec match_password. En cas d'absence de correspondance, index.php:134 recherche dans students et student_enrollment, avec dates d'inscription. Il s'agit d'un parcours Rosario avec deux sources de comptes, pas d'une authentification SchoolSafe a recreer.

functions/User.fnc.php:44-85 distingue les sessions STAFF_ID et STUDENT_ID. Les comptes staff portent PROFILE, PROFILE_ID et SCHOOLS; le compte eleve est rattache par son inscription.

modules/Users/User.php:133-177 controle l'edition des ecoles et du profil via AllowEdit. Les types generaux des utilisateurs sont admin, teacher, parent et none. Les profils personnalises ont leur PROFILE_ID.

modules/Users/Profiles.php:235-243 cree des profils personnalises bases sur admin, teacher ou parent; les permissions utilisees sont CAN_USE et CAN_EDIT dans profile_exceptions. L'eleve apparait dans les profils presentes, mais le formulaire de creation de nouveaux profils ne propose pas un type libre arbitraire.

ProgramFunctions/FirstLogin.fnc.php:27-44,89,106 utilise FORCE_PASSWORD_CHANGE_ON_FIRST_LOGIN et LAST_LOGIN. Ce mecanisme peut porter le parcours du premier mot de passe. Ne pas remplacer ces regles par le must_change des tables locales auth.*. Activation effective de l'option non verifiee dans la base.

Side.php:405-420 filtre la liste des ecoles selon SCHOOLS si renseigne; une liste vide ne constitue donc pas une restriction. Ce controle de presentation ne suffit pas a prouver l'isolation des ecoles sur toutes les operations : tests directs obligatoires avant activation.

## Correspondance proposee, non configuree

| Usage | Representation Rosario | Interface | Controle necessaire |
| --- | --- | --- | --- |
| Administration centrale | Type admin, profil et acces multi-ecoles explicitement autorises | Appsmith | Compte nominatif, operations et ecoles autorisees, audit; aucune cle de base exposee au navigateur |
| Administrateur d'ecole | Type admin avec profil restreint et ecole(s) explicitement rattachee(s) | SchoolSafe | Retirer l'edition des ecoles et des privileges non delegables; tester l'impossibilite de s'elever ou de sortir du perimetre |
| Enseignant | Type teacher, profil et affectations appropries | SchoolSafe | Classes et fonctions autorisees par Rosario |
| Parent | Type parent et association avec les eleves | SchoolSafe | Acces aux enfants rattaches, pas a une ecole entiere |
| Eleve | Compte students et inscription valide | SchoolSafe | Acces personnel et regles Rosario |
| Direction, gardien, finance, autres postes | Profils personnalises fondes sur un type Rosario compatible | SchoolSafe | Etudier les permissions reelles de chaque fonction; ne pas mapper automatiquement tous les postes sur admin |

Deux niveaux d'administration souhaites, mais pas deux systemes de mots de passe. La categorie none represente l'absence d'acces; elle n'est pas un poste scolaire.

## Appsmith : preuve et limite

Appsmith Community Edition est healthy et /user/login repond 200. Aucun export d'application Appsmith ni inventaire de ses sources de donnees n'a ete consulte. Presence d'une connexion a Rosario, SSO, passage de session et droits effectifs : NON VERIFIES.

Ne pas assimiler le compte technique d'acces Appsmith au compte scolaire Rosario. Aucun SSO n'est promis. L'acces a la console et l'autorisation d'effectuer une operation Rosario sont deux controles distincts tant que leur liaison n'est pas prouvee.

## Plan avant raccordement

1. Examiner un export expurge de secrets des applications et contrats Appsmith existants, ou les consulter via une session autorisee. Ne pas extraire les bases internes ni les secrets Appsmith pour contourner sa connexion.
2. Prepararer localement un Rosario de test correspondant a la version installee, avec fixtures fictives et aucune donnee de production.
3. Verifier les comptes central et ecole, l'attribution des profils, le changement initial, les sessions et les controles de changement d'ecole. Les profils personnalises seuls ne garantissent pas une delegation sure de tous les autres profils.
4. Reutiliser la passerelle existante si prouvee; sinon proposer un adaptateur separe utilisant les controles Rosario, sans ecriture directe des comptes dans staff et sans duplication dans iam/auth.
5. Raccorder les ecrans SchoolSafe uniquement aux contrats verifies. Reprendre creation du premier administrateur, comptes personnel, parents, eleves et recuperation en lots distincts.

Aucune adaptation active realisee dans ce lot. Les routes /native/* du serveur Node local ne sont pas prouvees compatibles avec les sessions PHP Rosario. Studio de cartes conserve; une incompatibilite eventuelle doit etre signalee, pas masquee.

Livraison : ce document uniquement. Tests metier, permissions configurees et integration Appsmith : non executes.
