# Modifier plusieurs élèves

**Grande branche.** Élèves.

**Groupe parent.** Aucun. Le lien vient juste après Add a Student.

**Sous-branche.** Dossier. Les informations sont celles de la fiche et de l’inscription. L’opération change le nombre d’élèves, pas la nature des données. Elle ne crée pas une nouvelle couleur.

**Nom SchoolSafe.** Modifier plusieurs élèves.

**Fonction Rosario.** `Students/AssignOtherInfo.php` (Group Assign Student Info).

**Où la trouver.** Élèves, lien Group Assign Student Info, troisième élément du menu.

**À quoi elle sert.** Rechercher des élèves, cocher ceux qui doivent changer, puis appliquer la même information à tous les élèves cochés.

**Qui peut l’utiliser.** L’administrateur voit le lien, ouvre la page et peut enregistrer. L’enseignant, le parent et l’élève ne voient pas le lien. Une adresse directe ne leur laisse pas la page.

**Comment sélectionner les élèves.** La recherche utilise le nom, le prénom, l’identifiant, l’adresse ou le niveau, dans l’école en cours. La liste indique combien d’élèves ont été trouvés. Chaque ligne a une case. La case de l’en-tête coche toute la liste affichée, pas les élèves absents de cette recherche.

**Informations modifiables.** Avec toutes les catégories : les champs de la fiche, les champs médicaux personnalisés, le niveau, le calendrier, le passage ou le redoublement, la date d’entrée et le code d’entrée. Le choix d’une catégorie limite les champs à cette catégorie. Le niveau et l’inscription n’apparaissent qu’avec toutes les catégories ou la catégorie General Info.

**Risque d’écrasement.** Une valeur saisie remplace la valeur déjà enregistrée pour chaque élève coché. Un champ texte laissé vide ne change pas la fiche. « No Value » dans une liste efface la valeur. Une case à cocher visible est enregistrée même décochée, donc elle peut effacer une case déjà cochée. L’inscription modifiée est la dernière inscription de l’élève dans l’école et l’année en cours.

**Confirmation.** Rosario n’affiche pas de confirmation avant l’enregistrement. Le bouton enregistre directement. L’avertissement SchoolSafe rappelle de vérifier la sélection.

Les changements peuvent s’appliquer à plusieurs élèves en une seule fois. Vérifiez la sélection avant d’enregistrer.
