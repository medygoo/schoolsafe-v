# Design du formulaire eleve V1

Reutilise account-form.css valide : Identite, Connexion de l'eleve,
Informations complementaires, Scolarite. Preview sans soumission,
stockage ni API. Les identifiants, mots de passe, photo et champs
sensibles sont desactives dans cette preview.

Sources examinees en lecture seule :
- modules/Students/Menu.php : cle d'ajout distincte
  Students/Student.php&include=General_Info&student_id=new.
- modules/Students/includes/General_Info.inc.php : nom, photo,
  assign_student_id, students[USERNAME], students[PASSWORD].
- Formulaire natif ouvert en session admin : groupes Identite,
  Informations complementaires, Scolarite et date d'entree observes.
  Aucun champ saisi, aucune inscription enregistree.

Le compte eleve est distinct du formulaire Users/User.php. Les champs
de connexion existent dans sa fiche native ; ne pas inventer un profil
Eleve dans le select des utilisateurs ni changer les regles d'obligation.

Les champs complementaires dependent de la configuration native. La
preview expose ceux observes (sexe, origine ethnique, nom usuel,
securite sociale, naissance, langue), sans imposer leur presence a
toutes les ecoles. Ne pas supprimer ces champs du moteur ni en faire
de nouvelles obligations. Leur utilite locale doit etre validee.

Integration future : conserver les noms des champs, les valeurs et
options natives, les champs caches, tokens, action et controles AllowEdit.
Photo selon permissions ; calendrier et niveau selon l'ecole ; dates
et codes d'inscription selon Rosario. Conserver les erreurs natives.
Le lien de preview ouvre seulement le formulaire natif, lequel applique
les permissions Rosario. Le design n'est pas encore applique a ce form.

Studio Card, scanner, finances et authentification inchanges.
