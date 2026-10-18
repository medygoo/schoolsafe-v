# Periodes scolaires : presentation isolee

Source examinee en lecture seule : C:/Users/PC/Documents/rosario/modules/School_Setup/MarkingPeriods.php.

## Correspondance native

- FY > SEM > QTR > PRO ; identifiants parents year_id, semester_id, quarter_id. Listes filtrees par UserSchool() et UserSyear().
- TITLE (50), SHORT_NAME (10), SORT_ORDER (-9999 a 9999).
- START_DATE, END_DATE, POST_START_DATE, POST_END_DATE.
- DOES_GRADES et DOES_COMMENTS ; visibilite et edition conditionnelles natives.
- AllowEdit() controle les mutations. Dates verifiees par VerifyDate et controles chronologiques.
- Chevauchement des dates des periodes hors PRO : erreur ; chevauchement de saisie des notes : avertissement dans cette version.
- Suppression : DeletePrompt, restrictions du bouton pour annee/periode unique, DBTransDryRun et MarkingPeriodDeleteSQL. Ne pas remplacer ces protections par un simple bouton visuel.

## Livrable et limites

marking-periods.html reutilise account-form.css, le logo et Lucide existants. UI/UX Pro Max consulte : etiquettes associees et noms accessibles. Aucune periode fictive imposee, aucun POST, API, stockage ou sauvegarde. Hierarchie et options metier desactivees tant que les donnees ne sont pas chargees.

Le lien ouvre le module natif, sans connecter les champs de la previsualisation. Les champs de date HTML ne remplacent pas encore le format DateInput natif. Le raccordement devra conserver les validations, erreurs, avertissements, choix de periode et controles de permission Rosario. Le fonctionnement metier de ce nouveau visuel reste non teste.

Aucun changement au moteur, a l'authentification, a la base, au Studio Card ou aux systemes de scan et recuperation.
