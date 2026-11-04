# Menu « Sécurité et contrôle » — analyse « fusion » (5 novembre 2026)

Méthode : `docs/DECISIONS.md`, 4 novembre 2026. Analyse faite sur l'écran réel de la direction, le code et la
base de production (lecture seule). **Proposition à valider par le propriétaire avant tout changement.**

## Constat

- La direction voit **12 liens en 5 groupes**, rangés par module technique (« Action et contrôle »,
  « Autorisation et suivi »…).
- L'**enseignant** voit « Prendre les présences » (`Attendance/TakeAttendance.php`), qui fait la même chose que
  **Ma journée** (Pédagogie) : deux endroits pour faire l'appel.
- Les **codes de présence sont restés en anglais** dans les 3 écoles (Present, Absent, Tardy, Excused Absence),
  comme les **6 champs du formulaire d'incident** (Violation, Detention Assigned…). Ce sont les valeurs livrées
  avec le logiciel, jamais réglées par l'école.

| Donnée | En base |
|---|---|
| Journal des connexions | 343 connexions, la dernière le 4 novembre |
| Entrées et sorties pointées | 3, la dernière le 3 novembre |
| Appels par période, absences journalières | 0, 0 (l'appel réel commence avec l'essai) |
| Incidents de discipline | 0 |
| Codes de présence | 4 par école, en anglais |
| Champs du formulaire d'incident | 6, en anglais |

## Décision page par page (direction)

| Aujourd'hui | Décision | Nouveau nom et phrase d'aide |
|---|---|---|
| Scanner les cartes | garder | **Scanner les cartes** — Vérifier une carte à l'entrée : élève, parent, récupération, frais. |
| Présences journalières (Entry_Exit) | garder | **Entrées et sorties** — Qui est entré ou sorti de l'école, et à quelle heure. |
| Pointage manuel | garder | **Pointage manuel** — Noter une entrée ou une sortie sans carte. |
| Administration (Attendance) | garder | **Contrôle des présences** — Voir et corriger les présences d'une journée, classe par classe. |
| Ajouter absences | garder | **Ajouter des absences** — Marquer absents plusieurs élèves à la fois (sortie, maladie…). |
| Ajouter un incident | garder | **Signaler un incident** — Noter un incident de discipline pour un élève. |
| Incidents | garder | **Incidents** — Tous les incidents signalés, élève par élève. |
| Journal d'accès | garder | **Journal des connexions** — Qui s'est connecté à SchoolSafe, et quand. |
| Codes de présence | garder | **Codes de présence** — Présent, absent, retard… et leur effet sur la journée. |
| Formulaire de discipline | garder | **Formulaire d'incident** — Les informations demandées pour chaque incident. |
| Recalculer les absences journalières | **retirer du menu** | outil de maintenance technique ; aucune absence en base ; reste joignable par son adresse |
| Effacer les absences redondantes | **retirer du menu** | outil de maintenance technique ; aucune absence en base ; reste joignable par son adresse |

## Parcours proposé (direction) : 5 étapes, 10 liens, aucun caché

1. **Contrôle à l'entrée** : Scanner les cartes · Entrées et sorties · Pointage manuel
2. **Présences et absences** : Contrôle des présences · Ajouter des absences
3. **Discipline** : Signaler un incident · Incidents
4. **Historique** : Journal des connexions
5. **Réglages** : Codes de présence · Formulaire d'incident

## Autres profils

- **Enseignant** : « Prendre les présences » **sort du menu**, car l'appel se fait dans **Ma journée**
  (Pédagogie), et la page reste joignable par son adresse. Restent : Tableau des présences, Signaler un incident,
  Incidents.
- **Parent** : « Présences de mon enfant » et « Incidents de mon enfant ».

## À décider à part : traduire les codes et le formulaire (données)

Les parents et les enseignants verront « Present / Tardy » et « Violation / Detention Assigned » tant que ces
valeurs restent en anglais. Les traduire (Présent, Absent, Retard, Absence justifiée ; Faute, Sanction, Parents
prévenus par l'enseignant, Parents prévenus par la direction, Exclusion (direction), Commentaires) **modifie des
données de production**. Cela demande un accord distinct, puis un SQL transactionnel avec retour arrière. Ce
n'est pas compris dans ce lot de menu.

## Garde-fous

Aucune page effacée, aucun droit changé. Seuls changent l'ordre, les noms affichés dans le menu et les phrases
d'aide, plus 2 outils de maintenance (direction) et 1 doublon (enseignant) qui sortent du menu. Un profil sans
plan garde son menu d'origine.

## Réalisation (5 novembre 2026)

- Commit `ca9ef4c` (worktree `SchoolSafeProdDrift-20261103`). Plan `menuPlans.security` dans le `scripts.js` du
  thème. Une ligne dont le nom vaut `null` sort du menu ; la page reste joignable par son adresse.
- **En production** : image `schoolsafe-rosario:menu-securite-20261105`. Contrôle `verify` : service sain, 200,
  0 erreur PHP, autres services inchangés.
- Contrôle à l'écran (direction) : **10 liens, 10 phrases d'aide**, en 5 étapes. École (16/16) et Pédagogie
  (11/11) inchangées.
- Les menus enseignant et parent ont été testés avec des listes simulées. Ils restent à regarder à l'écran.
- **Toujours en attente d'un accord à part** : traduction des codes de présence et du formulaire d'incident.
