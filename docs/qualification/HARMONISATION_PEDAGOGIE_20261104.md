# Harmonisation de la page Pédagogie — proposition (4 novembre 2026)

Demande du propriétaire : quand on ouvre Pédagogie, trop de cases (« sous-profils ») apparaissent. Regrouper
les fonctions dans peu de pages cohérentes, par profil, sans ajouter de fonctionnalité ni rien supprimer.

## Pourquoi il y a tant de cases (constat dans le code)

La page Pédagogie réunit deux sources (`assets/themes/SchoolSafeCodex/scripts.js`, fonction `destination`) :

1. **Le parcours SchoolSafe** (`central-navigation.php`) : 8 à 10 entrées selon le profil.
2. **Tous les programmes d'origine** des modules Notes (`Grades`), Emplois du temps (`Scheduling`),
   Activités (`Eligibility`) et « Programmes enseignant » (`Users/TeacherPrograms.php`).

Pour la direction, cela fait jusqu'à **environ 55 cases** : 10 SchoolSafe + 19 Notes + 17 Emplois du temps +
6 Activités + 4 programmes enseignant. Elles sont rangées par module technique (« Évaluation »,
« Organisation pédagogique », « Accompagnement »), et non par tâche.

Doublons visibles :

| Tâche | Page SchoolSafe (parcours) | Page d'origine qui fait la même chose |
|---|---|---|
| Bulletin | Synthèse du bulletin, Bulletin | Bulletins (ReportCards), Notes finales, Saisie des notes finales |
| Devoirs et notes | Devoirs et travaux | Carnet de notes (Grades), Devoirs (Assignments), Création groupée de devoirs |
| Branches | Branches et maxima | Cours (Scheduling/Courses) |
| Suivi | Suivi pédagogique | Notes des élèves, Saisie par enseignant, Répartition des notes |

Limite : ce qui s'affiche réellement dépend aussi des droits de chaque profil dans la base. Je n'ai pas pu lire
ces droits en production : la liste exacte reste à confirmer, soit par une capture de la page pour chaque
profil, soit par une lecture de la base que le propriétaire autorise.

## Principe du regroupement

- Sur la page Pédagogie, **seulement les grandes tâches du profil**, dans l'ordre d'usage (le même que la
  barre de parcours en haut des pages).
- Les pages de détail sont rangées **sous la tâche à laquelle elles servent**, dans une liste repliée
  « Outils détaillés ».
- Les pages d'origine sans usage dans le parcours RDC sont réunies dans un seul bloc replié en bas, « Autres
  outils (logiciel d'origine) ». **Rien n'est supprimé.**
- **Les droits ne changent pas** : on modifie seulement le classement des liens, et chaque page continue de
  vérifier qui peut l'ouvrir.

## Regroupement proposé

### Direction — 4 blocs

| Bloc | Cases visibles | Outils détaillés (repliés) |
|---|---|---|
| 1. Organiser l'année | Préparer l'année · Branches et maxima · Papier de l'école | Cours · Barèmes de notes · Réglages des notes · Commentaires du bulletin · Codes de commentaires · Périodes historiques · Inscriptions groupées aux cours · Retraits groupés · Emploi du temps d'un élève |
| 2. Suivre et évaluer | Suivi pédagogique · Synthèse du bulletin · Rattrapage · Arrivées à l'école | Notes des élèves · Notes finales · Saisie par enseignant (3 pages) · Avancement des enseignants · Répartition des notes · Rapports de progrès · Notes historiques · Création groupée de devoirs |
| 3. Communiquer et archiver | Convocations et courriers · Archives de l'école | Imprimer listes de classe · photos de classe · emplois du temps |
| 4. Aide | Mode d'emploi Pédagogie | — |
| Autres outils (replié) | — | Bulletins d'origine · Relevés · Tableau d'honneur · Classement GPA · Demandes de cours · Planificateur · Rapports d'emplois du temps · Activités (6 pages) |

### Enseignant — 1 bloc « Ma classe »

| Cases visibles | Outils détaillés (repliés) |
|---|---|
| Ma journée de classe · Devoirs et travaux · Notes du bulletin · Synthèse du bulletin (titulaire seulement) · Rattrapage · Arrivées à l'école (si autorisé) · Mode d'emploi | Carnet de notes d'origine · Notes des élèves · Emploi du temps · Listes de classe |

### Parent et élève — 1 bloc « Mon enfant » (« Mon travail » pour l'élève)

| Cases visibles | Outils détaillés (repliés) |
|---|---|
| Travail de mon enfant · Dossier de l'année · Bulletin · Mode d'emploi | Notes (page d'origine) · Emploi du temps |

Aujourd'hui, les pages du parent sont rangées dans « Communication › Suivi de mon enfant », et Pédagogie ne
lui montre que des pages d'origine. Le regroupement réunit tout à un seul endroit, Pédagogie, et laisse un
raccourci dans Communication.

## Réalisation (4 novembre 2026, commit `d6a54c5`, worktree `SchoolSafeProdDrift-20261103`)

- Constat sur l'écran réel de la direction : **42 cases**, avec les pages d'origine en premier. Après le
  regroupement : **13 cases visibles** dans 4 blocs, plus les outils repliés (9 + 7 + 10).
- Classement testé avec la vraie liste de la direction, et avec des listes simulées pour l'enseignant et le
  parent. Rendu vérifié par un aperçu dans le navigateur, sans modification sur le serveur.
- Écart avec la proposition : le parent ne garde **pas** de raccourci dans Communication, car le menu
  supprime les liens en double. Communication garde « Signaler une absence » et « Notifications ».
- Défaut corrigé au passage : le lien « Mode d'emploi » (`guide-pedagogie.php#…`) était écarté pour tous les
  profils par le filtre des destinations.
- **En production le 4 novembre 2026** : image `schoolsafe-rosario:pedagogie-menu-20261104`, construite sur
  `dbget-ecritures-20261104`. Contrôle `verify` : service sain, 200 en local et en public, 0 erreur PHP,
  sessions conservées, autres services inchangés. Contrôle à l'écran (direction) : 10 liens visibles en
  4 blocs, Mode d'emploi présent. Les vues enseignant et parent restent à regarder avec leurs comptes.
  Retour arrière possible : `release.sh rollback`.

## Version 2 — parcours sans outil caché (correction du propriétaire, 4 novembre 2026)

Correction du propriétaire : cacher 10 outils derrière « Autres outils » ne les rend pas compréhensibles. Il faut
analyser chaque page, puis la **réunir** dans le parcours ou la **retirer**, pour que l'application soit facile.

Analyse (code et base de production, en lecture seule) :

| Pages d'origine | Constat | Décision |
|---|---|---|
| Demandes élève, Demandes groupées, Lancer le planificateur | 0 demande ; « Branches et maxima » inscrit directement les élèves | retirées du menu |
| Activités (4 pages) et Saisir l'éligibilité | seulement les exemples livrés avec le logiciel (Basketball, Chess Team), 0 élève | retirées du menu |
| Bulletins de notes, Livrets scolaires, Certificats, Notes finales | 0 note : le bulletin RDC a ses propres tables (`schoolsafe_bulletin_marks`) | retirées du menu ; le bulletin RDC les remplace |
| Commentaires du bulletin, Codes de commentaires | servent seulement au bulletin d'origine | retirées du menu |
| Historique des périodes, Notes historiques | 0 donnée | retirées du menu |
| Cours, Emplois du temps groupés, Échelles de notation, Création de devoirs en masse | déjà ouvertes par les étapes de « Préparer l'année » | **réunies** dans « Préparer l'année » |
| Saisie à la place d'un enseignant (5 pages) | double emploi avec le parcours enseignant | restent dans **Utilisateurs › Programmes enseignants** |
| Carnet de notes, Notes des élèves (enseignant) | remplacés par « Devoirs et travaux » ; carnet ouvert depuis « Ma journée » | retirés du menu enseignant |
| Notes, devoirs, bulletin et emploi du temps d'origine (parent) | déjà ouverts depuis « Travail de mon enfant » | retirés du menu parent |
| Configuration des notes | réglage général encore utile | **gardée**, sous le nom « Réglages des notes » |

Aucune page n'est effacée et aucun droit ne change : une page retirée du menu reste joignable par son adresse
et par la page qui l'utilise. Un profil sans parcours SchoolSafe garde son menu d'origine.

Résultat pour la direction : **11 liens**, en 4 étapes, chacun avec une phrase qui dit à quoi il sert. Aucun
bouton « Outils détaillés » ni « Autres outils ».

**En production le 4 novembre 2026** : image `schoolsafe-rosario:pedagogie-menu-2-20261104` (commits `c5f1020`
et `bde640e`, worktree `SchoolSafeProdDrift-20261103`). Contrôle `verify` : service sain, 200, 0 erreur PHP,
autres services inchangés. Contrôle à l'écran (direction) : 11 liens, 11 phrases d'aide, 0 repli. Les 5 pages
« Programmes enseignants » sont toujours dans Utilisateurs. Les menus enseignant et parent restent à regarder
pendant l'essai. Retour arrière : `release.sh rollback`.

## Fichiers concernés

- `assets/themes/SchoolSafeCodex/scripts.js` : classement des pages d'origine dans les blocs, blocs repliés.
- `central-navigation.php` : noms et ordre des blocs SchoolSafe, liens du parent.
- Feuille de style du thème, pour les blocs repliés.

Mise en ligne par image vérifiée (`mkrel.py`, puis `release.sh prepare`, test, `deploy`, `verify`),
avec contrôle visuel des quatre profils.
