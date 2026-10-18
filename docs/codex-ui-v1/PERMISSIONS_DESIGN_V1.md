# Profils et droits : preview

Deux onglets, profils et droits individuels. Navigation clavier par
fleches, Home et End. Aucun droit coche ni presume : valeurs non chargees.
Quatre programmes illustrent la distinction consultation/ajout, sans
pretendre constituer la liste complete des programmes et categories.

Sources natives examinees : Users/Profiles.php lit profile_exceptions
(MODNAME, CAN_USE, CAN_EDIT) et les modules actifs ; Users/Exceptions.php
traite staff_exceptions et les categories personnalisees. Le parcours
Exceptions comporte des conditions sur PROFILE_ID : ne pas le presenter
comme une simple surcouche toujours disponible pour tous les comptes.

Profils admin/teacher/parent/student observes dans le code natif. Le
profil eleve dans la gestion des profils ne signifie pas qu'il existe
dans le select de creation des comptes staff. Les profils personnalises
et leur libelle doivent venir de Rosario, pas de cette preview.

Integration future : conserver CAN_USE et CAN_EDIT distincts, modules
actifs, cles completes avec parametres et categories, AllowUse/AllowEdit.
Ne jamais deduire ces droits d'une selection de profil cote navigateur.
Les actions delete/update natives ne sont pas executees ici.

UI/UX Pro Max : labels visibles, controles semantiques, clavier et
etat non charge explicite. Aucun moteur ni authentification modifie.
