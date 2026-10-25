# Champs personnalisés de l’école

**Nom SchoolSafe.** Champs personnalisés de l’école.

**Grande branche.** École.

**Groupe.** Setup.

**Sous-branche.** Établissement. Ces champs complètent la fiche de l’école. Ce n’est pas la fiche elle-même : ici on définit le champ, là-bas on écrit sa valeur.

**Où la trouver.** École, groupe Setup, lien School Fields, juste après Copy School.

**À quoi elle sert.** Cette page ajoute des informations supplémentaires à la fiche de l’école, lorsque les champs habituels ne suffisent pas. Le nom, le type et l’ordre se règlent ici. La valeur se saisit ensuite sur la fiche de l’école.

**Qui peut la gérer.** L’administrateur peut créer, modifier et supprimer un champ. L’enseignant, le parent et l’élève ne voient pas cette page. Ils peuvent voir la valeur sur la fiche de l’école, sans bouton Enregistrer, si Rosario les autorise à ouvrir la fiche.

**Où les champs créés apparaissent.** Sur la fiche de l’école (School Information), sous les informations habituelles.

**Ce qu’on peut faire.** Ajouter un champ, choisir son type (texte, texte long, nombre, date, case, liste, fichiers), indiquer une valeur proposée, le rendre obligatoire, régler son ordre, le modifier, puis le supprimer. Supprimer un champ retire aussi les valeurs déjà saisies. Il n’y a pas de catégories.

**Fonction Rosario correspondante.** `School_Setup/SchoolFields.php` (School Fields). La définition est dans `school_fields`. La valeur est enregistrée sur l’école, dans `schools`.
