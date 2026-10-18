# Association parents eleves

Presentation conforme au parcours Users/AddStudents.php : selection
du compte parent, affichage des associations existantes, recherche et
selection des eleves disponibles. La preview est interactive avec deux
dossiers clairement fictifs ; aucune API, persistance ou soumission.

Preuve source : modules/Users/AddStudents.php verifie le profil parent,
UserStaffID(), AllowEdit(), les inscriptions dans les ecoles autorisees,
puis insere dans students_join_users. La suppression suit DeletePrompt.
Les eleves deja associes sont exclus de la recherche native.

Integration : reutiliser les controles serveur natifs, les selections
de session et les permissions de la route complete. Ne pas reconstruire
ces controles seulement dans le navigateur. Conserver la confirmation
native pour retirer une association et les messages d'erreur/succes.

Une association donne acces au dossier de l'eleve. Elle ne constitue
pas une autorisation de recuperation. Scanner et recuperation intacts.
Pas d'ajout de parent principal obligatoire : ce parcours natif ne
definit pas cette distinction. Pas de reinitialisation de mot de passe.

Le CSS reutilise le design des formulaires valide. UI/UX Pro Max :
labels visibles, controles clavier, tableaux semantiques et responsive.
Le lien final ouvre le parcours natif, pas une action d'association.
