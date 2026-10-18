# Revue des raccordements locaux V3

## Correction appliquee

La vue separee SchoolSafe utilise maintenant SchoolSafeMenuProgramTitle(),
le lecteur natif de menu, pour verifier la presence d'une destination dans
le menu du profil courant. Le dossier d'un programme ne determine pas
necessairement sa branche de navigation. Custom/Menu.php ajoute notamment
Custom/NotifyParents.php dans Users et Custom/AttendanceSummary.php dans
Attendance. AllowUse() reste obligatoire ; aucune permission n'est creee.
Les quatre raccourcis suivent egalement la presence dans le menu natif.

## Destination a ne pas confondre

Custom/NotifyParents.php n'est pas un composeur email generique.
Le fichier indique que les parents sans connexion recoivent un message
et que leur mot de passe est reinitialise. Le traitement modfunc=save
contient un DBUpdate de staff.PASSWORD. Aucun raccordement du bouton
Email et aucune execution de ce traitement n'ont ete effectues.

## Etat exact

- 211 destinations recensees ; 22 correspondances vers des ecrans PHP.
- Ces correspondances permettent une navigation, pas une certification
  des operations de creation, modification ou enregistrement.
- Le Studio Card est preserve, sans modification.
- La preview existante reste une demonstration. La vue locale raccordee
  est distincte : /schoolsafe-view/.
- Les 188 destinations sans correspondance confirmee ne doivent pas
  etre declarees comme autant d'ecrans manquants. Leur moteur et leur
  ecran existant restent a verifier individuellement.
- Les details filles/garcons, presences enseignants et palmares ont
  encore besoin d'une source et de regles de presentation validees.
- Les operations metier, profils parent/eleve, isolation entre ecoles,
  responsive multi-viewport et Appsmith restent a tester/raccorder.

## Fichiers sources examines (lecture seule)

- Rosario modules/misc/includes/SchoolSafeDashboard.inc.php :
  SchoolSafeMenuProgramTitle et raccourcis par profil.
- Rosario modules/Custom/Menu.php : programmes classes dans d'autres menus.
- Rosario modules/Custom/NotifyParents.php : reinitialisation des mots de passe.
- Vue additive app/modules/codex-ui-v1/rosario-view/index.php.

Aucune modification du moteur Rosario, de l'authentification, de la base
de donnees ou de la production.
