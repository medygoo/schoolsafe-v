# Raccordements existants et ecarts visuels - V2

## Realise dans la vue locale separee

URL : http://127.0.0.1:18082/schoolsafe-view/

Les onze lecteurs administratifs natifs sont appeles uniquement si le module est actif et la route de consultation autorisee : SchoolSetup, Students, Users, Scheduling, Grades, Attendance, Eligibility, Discipline, Accounting, StudentBilling, FoodService. Aucune requete de calcul metier n'est recopiee ni ajoutee. Les fonctions dans modules/*/includes/Dashboard.inc.php restent intactes.

Le menu est genere par Menu.php puis filtre avec AllowUse. Observation de la session administrateur : 12 branches et 121 liens natifs generes. Ce nombre ne signifie PAS 121 parcours testes ni 121 ecrans SchoolSafe integres. Les formulaires restent sur les ecrans Rosario natifs.

Donnees observees : 7 connexions enregistrees (ce n'est pas la presence physique), 1 eleve inscrit au niveau 06, 4 utilisateurs (2 enseignants, 1 administrateur, 1 parent), 0 cours, 3 activites. Les six autres lecteurs renvoient des tableaux vides dans le contexte courant et restent marques indisponibles, sans faux zeros.

Tests executes : lint PHP, chargement administrateur des onze sections, absence de debordement horizontal dans le viewport courant, generation des 121 liens, ouverture du groupe Notes et presence du lien GPARankList. Sans cookie : 302 vers connexion ; POST : 405. Tests mobile/tablette et autres profils sur cette version non executes.

## Ce qui manque vraiment

| Element | Visuel existant | Origine native / suite |
| --- | --- | --- |
| Detail effectifs filles/garcons et classes | Oui : lot2-views.js, normalisation girls/boys ; demo-data.js donnees fictives | Le lecteur StudentsAdmin donne les niveaux et effectifs, pas cette ventilation complete. Contrat a etablir, sans modifier le moteur. |
| Presence journaliere enseignants | Oui : lot2-views.js, bloc teachers ; demo-data.js | Le journal de connexion ne prouve pas une presence. Source metier de presence physique a identifier. |
| Palmares top 10 | Emplacement existant : lot2-views.js:149 ; tableau de resultats reel non implemente dans ce bloc | GPARankList.php et HonorRoll.php existent, avec leurs ecrans natifs. Periode, criteres et publication du top 10 a valider ; ne pas inventer de classement. |
| Calendrier et dernieres activites du dashboard | Emplacements existants : lot2-views.js:165 | Calendriers et journal d'acces natifs existent ; definition du flux d'activites et transport a etablir. |
| Formulaires et tableaux SchoolSafe du catalogue | Composants existants, matrice LOT2 de 211 destinations | Les liens de cette vue ouvrent Rosario natif, pas les formulaires SchoolSafe raccordes. Correspondance route/champs/permissions a verifier par destination. |

L'absence de donnees n'est pas une absence de visuel. L'existence d'un visuel n'est pas un raccordement fonctionnel.

## Limites preservees

- Preview 5187 et visuels precedents non remplaces.
- Studio Card, scanner, controle financier et moteur JASPE non modifies.
- Rosario monte readonly ; seules les copies de presentation hors source et la configuration Apache du conteneur de test sont actualisees.
- Pas de modification de compte, SQL, migration, authentification ou deploiement VPS.
- Les lecteurs comptables effectuent leurs lectures natives ; aucune transaction ou modification financiere effectuee.
- Aucune declaration de fin des 211 raccordements, des parcours d'ecriture ou de preparation production.

## Travail restant priorise

1. Correspondance complete entre chaque destination SchoolSafe et son ecran PHP natif, distincte des anciens contrats Node. Confirmer les champs et droits avant chaque raccordement d'un formulaire.
2. Reutiliser les visuels deja presents dans la couche de presentation validee : cette vue simple n'est pas le dashboard unifie complet.
3. Raccorder les details d'effectifs et les indicateurs dont le contrat est confirme ; declarer les autres indisponibles.
4. Completer les parcours de lecture puis d'ecriture autorises dans les sessions de test propres a chaque profil.
5. Tester responsive, isolation d'ecoles et migration avant toute validation de production.

READERS_CONNECTED=11

ADMIN_NATIVE_NAVIGATION_LINKS_GENERATED=121

FULL_SCHOOLSAFE_SCREEN_INTEGRATION=NOT_COMPLETE
