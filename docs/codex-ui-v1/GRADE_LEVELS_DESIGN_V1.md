# Niveaux scolaires : presentation isolee

## Source examinee en lecture seule

`C:/Users/PC/Documents/rosario/modules/School_Setup/GradeLevels.php`

- `_makeTextInput` : TITLE limite 50 caracteres ; SHORT_NAME limite 3 ; SORT_ORDER numerique, min -9999, max 9999, attribut maxlength 3 natif.
- `_makeGradeInput` : NEXT_GRADE_ID provient des niveaux de UserSchool(), tri natif.
- Mise a jour sous AllowEdit() ; nom et nom court necessaires pour insertion.
- `_makeRemoveButton` masque la suppression quand un eleve est inscrit au niveau ; suppression native avec DeletePrompt et filtrage SCHOOL_ID.

## Livraison

`grade-levels.html` reutilise account-form.css et les ressources existantes. Aucun niveau fictif impose, aucune liste de progression inventee. Aucun formulaire POST, sauvegarde, suppression, API ou stockage ajoute.

Le lien ouvre le module natif ; il ne constitue pas un raccordement du formulaire SchoolSafe. Les permissions restent controlees par Rosario. Aucun test metier ni modification des donnees effectue.

## Raccordement ulterieur

Charger la liste autorisee de l'ecole et ses identifiants ; respecter l'ordre natif, les validations et les protections de suppression. Adapter la liste editable existante et les retours d'erreurs sans modifier le moteur. La presente previsualisation ne remplace pas encore cette liste native.
