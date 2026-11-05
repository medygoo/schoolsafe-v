# Menu « Contrôle et rapports » — analyse « fusion » (5 novembre 2026)

Méthode : `docs/DECISIONS.md`, 4 novembre 2026. Analyse faite sur l'écran réel de la direction, le code et la
base de production (lecture seule, chiffres relevés aux lots précédents). **Proposition à valider par le
propriétaire avant tout changement.**

## Constat

- La direction voit **33 liens dans un seul groupe « Rapports »**. Ce sont les sections « Rapports » de chaque
  module d'origine, mises bout à bout, sans aucune phrase d'aide.
- **Noms en double** : 3 « État d'avancement » (notes, présences, activités) et 2 « Rapport des ajouts /
  retraits » (élèves, cours).
- **12 rapports dépendent de circuits vides ou doublés** par le parcours SchoolSafe :
  - notes et bulletins d'origine : 0 note, remplacés par le bulletin RDC et Suivi pédagogique ;
  - demandes de cours : 0 demande ;
  - activités : exemples seulement ;
  - lettres en publipostage : en double avec Pédagogie › Convocations et courriers.

## Décision page par page (direction)

| Étape | Lien (ancien nom) | Décision | Nouveau nom — phrase d'aide |
|---|---|---|---|
| 1 · Élèves | Rapport avancé | garder | **Rechercher et lister des élèves** — Choisir des critères, afficher, imprimer ou exporter. |
| | Répartition des élèves | garder | **Répartition des élèves** — Combien d'élèves par niveau, sexe ou autre champ. |
| | Rapport des ajouts / retraits (élèves) | garder | **Arrivées et départs d'élèves** — Inscriptions et sorties sur une période. |
| | Imprimer les infos des élèves | garder | **Imprimer des fiches élèves** — La fiche complète d'un ou plusieurs élèves. |
| | Imprimer des étiquettes | garder | **Imprimer des étiquettes** — Étiquettes nominatives (cahiers, enveloppes). |
| 2 · Classes et horaires | Imprimer les listes de classe | garder | **Listes de classe** — La liste des élèves de chaque classe, à imprimer. |
| | Imprimer les photos de classe | garder | **Trombinoscope** — Les photos des élèves de chaque classe. |
| | Imprimer les emplois du temps | garder | **Emplois du temps** — L'emploi du temps d'un élève ou d'une classe, à imprimer. |
| | Rapport emploi du temps | garder | **Effectifs par cours** — Combien d'élèves suivent chaque cours. |
| | Emplois du temps incomplets | garder | **Élèves sans cours** — Les élèves qui ne sont inscrits à aucun cours. |
| | Rapport des ajouts / retraits (cours) | garder | **Changements de cours** — Les élèves ajoutés ou retirés d'un cours. |
| 3 · Présences | Présence journalière moyenne | garder | **Taux de présence** — La présence moyenne par jour et par classe. |
| | Tableau des absences | garder | **Absences jour par jour** — Le tableau des présences de chaque élève. |
| | Rapport des absences | garder | **Bilan des absences par élève** — Absences et retards cumulés de chaque élève. |
| 4 · Discipline | Journal de discipline | garder | **Journal des incidents** — Tous les incidents, à imprimer. |
| | Répartition par catégorie | garder | **Incidents par type** — Combien d'incidents de chaque sorte. |
| | Répartition par catégorie dans le temps | garder | **Incidents dans le temps** — L'évolution des incidents mois par mois. |
| | Répartition par champ élève | garder | **Incidents par groupe d'élèves** — Par niveau, sexe ou autre champ. |
| 5 · Cantine | Rapport d'activité | garder | **Repas servis** — Les repas servis, jour par jour. |
| | Rapports des repas | garder | **Repas par menu** — Combien de repas de chaque menu. |
| | Rappels | garder | **Rappels cantine** — Prévenir les familles dont le solde cantine est bas. |
| — | Imprimer des lettres | **retirer du menu** | en double avec Pédagogie › Convocations et courriers |
| — | Imprimer les demandes, Rapport des demandes | **retirer du menu** | 0 demande de cours |
| — | Notes des élèves, Bulletins intermédiaires, État d'avancement (notes), Répartition des notes, Notes finales, Liste moyenne / classement | **retirer du menu** | circuit de notes d'origine, 0 note ; bulletin RDC et Suivi pédagogique |
| — | État d'avancement (présences) | **retirer du menu** | même rôle que Suivi pédagogique (appels faits par enseignant) |
| — | Liste des élèves, État d'avancement (activités) | **retirer du menu** | activités jamais utilisées |

**Résultat : 5 étapes, 21 liens, chacun avec sa phrase d'aide. 12 liens sortent du menu** et restent joignables
par leur adresse.

## Autres profils

- **Enseignant** : « Mes classes » (Rechercher et lister mes élèves, Listes de classe, Trombinoscope, Emplois du
  temps, Étiquettes). Les rapports de notes d'origine (Notes des élèves, Notes finales, Classement, Bulletins
  intermédiaires) et Imprimer des lettres sortent du menu, pour les mêmes raisons.
- **Parent** : « Mon enfant » (Emploi du temps, Photos de la classe).

## Garde-fous

Aucune page effacée, aucun droit changé. Une page retirée du menu reste joignable par son adresse. Un profil sans
plan garde son menu d'origine.

## Réalisation (5 novembre 2026)

- Commit `487c05c` (worktree `SchoolSafeProdDrift-20261103`), plan `menuPlans.reports`.
- **En production** : image `schoolsafe-rosario:menu-rapports-20261105`. Contrôle `verify` : service sain, 200,
  0 erreur PHP, autres services inchangés.
- Contrôle à l'écran (direction) : **Contrôle et rapports 21 liens / 21 phrases d'aide**. Ensemble des menus :
  **94 liens, 94 phrases d'aide**.
