# Controle responsive des quatre previews

Tests navigateur local : 390x844, 768x1024 et 1280x900.
Dimensions effectives innerWidth/innerHeight controlees, pas seulement
dimensions demandees. Douze combinaisons page/taille verifiees.

| Preview | Mobile | Tablette | Ordinateur | Labels absents |
| --- | --- | --- | --- | --- |
| student-list.html | aucun debordement | aucun debordement | aucun debordement | 0 |
| student-form.html | aucun debordement | aucun debordement | aucun debordement | 0 |
| account-form.html | aucun debordement | aucun debordement | aucun debordement | 0 |
| family-association.html | aucun debordement | aucun debordement | aucun debordement | 0 |

Critere de debordement : documentElement.scrollWidth <= innerWidth.
Logo charge et icones SVG presents dans chacune des douze combinaisons.
Ce controle ne constitue pas un audit complet WCAG ni une validation
des formulaires metier. Les previews n'enregistrent aucune operation.

Ajustements : zone des cases de selection du tableau familial au moins
44x44 ; alignement flex explicite du choix d'ecole utilisateur.
La recherche existante de l'utilisateur est preservee : tests dans
un nouvel onglet temporaire. Taille navigateur restauree ensuite.

Les tests concernent uniquement les quatre previews, pas toute
l'application ni les formulaires natifs qui restent inchanges.
