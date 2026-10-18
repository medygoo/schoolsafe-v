# Fiche de l’élève

**Grande branche Rosario.** Students.

**Grande branche SchoolSafe.** Élèves.

**Groupe parent.** Aucun. La fiche est le premier lien du menu Élèves, avant les groupes Rapports et Setup.

**Sous-branche.** Dossier. On retrouve un élève et on ouvre sa fiche.

**Nom SchoolSafe.** Fiche de l’élève.

**Fonction Rosario.** `Students/Student.php` (Student Info).

**Où la trouver.** Élèves, lien Student Info, premier élément du menu.

**À quoi elle sert.** Cette page sert à retrouver un élève, puis à ouvrir sa fiche. Indiquez son nom, son prénom, son identifiant ou son niveau. La fiche réunit son identité, son niveau et les informations qui lui sont liées.

**Profils autorisés.** L’administrateur recherche parmi les élèves de l’école. Dans cet échantillon, la liste en contient un. Le bouton d’enregistrement apparaît sur la fiche ; il n’a pas été utilisé. L’enseignant voit le lien, ouvre la page, et ne voit que les élèves que Rosario lui rattache. Dans cet échantillon, cette liste est vide et le bouton d’enregistrement est absent. Le parent et l’élève restent sur la fiche qui les concerne. Ils n’ont pas le formulaire de recherche de toute l’école, ni le bouton d’enregistrement.

**Périmètre des données.** La recherche lit les élèves, leur inscription et leur niveau. La fiche lit l’identité de l’élève sélectionné. Une recherche sans résultat et une lecture de la liste n’ont modifié aucun élève.

**Actions possibles.** Rechercher. Ouvrir une fiche. Enregistrer ou supprimer seulement si Rosario l’affiche. Ajouter un élève est un autre lien du menu.

**Dépendances.** Le niveau vient des niveaux de l’école. L’inscription relie l’élève à l’école et à l’année.
