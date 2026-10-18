# Vue generale detaillee et emplacement Palmares

Modification autorisee par le proprietaire le 11/10/2026. Presentation isolee uniquement. Revision suivante autorisee par GO : informations reunies dans une seule Vue generale.

- Administration et direction : effectifs par cycle/classe, inscrits, filles, garcons, non renseigne ; totaux.
- Situation journaliere datee : presents, absents, retards, non verifies par classe.
- Enseignants attendus : synthese et liste des affectations, statut et heure d'arrivee.
- Palmares : emplacement reserve aux 10 meilleurs eleves. Aucun classement, aucune note, aucune nouvelle destination Rosario.
- Tous les effectifs et horaires de preview sont fictifs. Les 8 classes totalisent 180 inscrits, 89 filles, 91 garcons, 140 presents et 40 absents. Les 8 enseignants attendus sont distincts des 22 membres du personnel.

## Organisation unifiee

Une seule section contient la date, la synthese compacte, les eleves, les enseignants, le palmares, les acces rapides et les dossiers/notifications repliables.
Sur grand ecran (1200 px et plus), la grille place les classes a gauche, les enseignants et le palmares a droite. La liste enseignante defile independamment.
En dessous de 1200 px, les onglets Eleves / Enseignants / Palmares affichent un seul panneau. Fleches gauche/droite, Home et End naviguent entre les onglets ; actualiser conserve l'onglet. Le changement de session remet la vue a zero.
Sous 700 px, les lignes de classe deviennent des listes regroupees par cycle, repliables, avec tous les chiffres accessibles sans defilement horizontal.
Les indicateurs sont calcules uniquement a partir du DTO valide : inscrits, classes, eleves presents (presents + retards), enseignants presents (statut Present + Retard) / attendus. Exemple enseignant : 7 / 8, dont 1 en retard, 6 a l'heure et 1 absent. Les colonnes A l'heure et Retards restent exclusives. Les comptes inconnus sont conserves et affiches lorsqu'ils existent.
Aucun nouveau droit ni changement du catalogue Rosario. Seules les presentations administrateur et direction utilisent cette disposition. Les autres postes gardent leur tableau de bord.

## Contrat de presentation pour Cursor

Extension optionnelle de `loadDashboard` : `schoolOverview: {date, classes, teachers}`.
Chaque classe : `cycle, name, enrolled, girls, boys, unspecified, present, absent, late, unverified`.
Chaque enseignant attendu : `name, assignment, status, arrival`. Statuts acceptes : `Present`, `Absent`, `Retard`, `Non verifie`.
Les nombres sont des entiers positifs ou nuls. Sommes exigees : filles + garcons + non renseigne = inscrits ; presents + absents + retards + non verifies = inscrits. Retard est ici une categorie exclusive, pas un sous-total des presents. Adapter explicitement les statuts natifs sans compter deux fois.
Sans ce contrat, afficher les details indisponibles, sans les deduire des compteurs existants. Le controle `dashboard` avant/apres chargement reste obligatoire ; Cursor doit limiter le perimetre et les champs au serveur. Aucun endpoint nouveau n'a ete raccorde.
Les fixtures de synthese ne constituent pas un registre exhaustif des dossiers exemples des autres ecrans. La date de demonstration ne prouve pas un jour scolaire reel.

## Palmares : decisions requises

Valider periode, publication officielle, criteres de comparaison des cycles/classes, traitement des ex aequo et droits de consultation. Aucun calcul ni classement transversal automatique. L'ancien catalogue de 211 destinations reste inchange.

## Verification

Test reproductible : `node docs/codex-ui-v1/overview-detail-tests.cjs`.
PASS : administration/direction, 5 largeurs de 320 a 1920 pixels, 2 themes ; verification des totaux et du contrat absent/incoherent, onglets clavier, actualisation, changement de largeur, cycles repliables, conservation de l'acces au dossier exact. Aucune erreur navigateur ni requete native observee.
Preuves : `lot2-tests/overview-unified-results.json` et `lot2-tests/overview-unified-*.png`. Captures ordinateur et telephone examinees visuellement.
La suite complete LOT 2 a depuis ete relancee pendant la passe UI/UX Pro Max et passe apres correction du repliement asynchrone des dossiers. Voir UI_UX_PRO_MAX_REVIEW.md. Aucune integration Cursor ni modification Rosario.
