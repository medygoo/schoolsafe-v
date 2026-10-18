# Utilisateurs et personnel : presentation V1

Preview additive personnel-list.html, composant de liste partage avec
student-list.js. Recherche, filtre par profil et detail minimal fictif.
Le jeu utilisateurs est active uniquement par data-preview-list=users.
Le jeu eleves par defaut est preserve. Aucun stockage, API ou droit.

Profils observes dans le formulaire natif : Enseignant, Administrateur,
Parent, Sans acces. Le titre Utilisateurs et personnel inclut explicitement
les comptes parents ; ne pas assimiler tous ces comptes a des salaries.
Pas de nouveau profil direction/gardien/finance invente dans Rosario.

Source Users/User.php : categories General_Info, Schedule, Other_Info,
controles selon profil et staff_id ; Users/Menu.php : permissions distinctes
pour consultation, creation, profils et exceptions. Les categories et
champs personnalises restent a reprendre dans une fiche complete.

Le filtre preview n'est pas un controle de permission. La portee reelle
est determinee par le serveur Rosario, pas par les options HTML.
Les acces aux droits restent dans la navigation native existante.
Le lien final ouvre Users/User.php sans mutation ni compte cree.

Preview regroupee dans la vue generale. Le formulaire utilisateur deja
valide est reutilise, sans toucher Rosario, Studio Card ou authentification.
