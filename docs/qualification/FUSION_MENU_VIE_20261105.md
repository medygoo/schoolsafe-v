# Menu « Vie et bien-être » — analyse « fusion » (5 novembre 2026)

Méthode : `docs/DECISIONS.md`, 4 novembre 2026. Analyse faite sur l'écran réel de la direction, le code et la
base de production (lecture seule). **Proposition à valider par le propriétaire avant tout changement.**

## Constat

- La direction voit **5 liens**, tous pour la **cantine**, rangés sous « Action immédiate », « Organisation » et
  « Suivi ».
- La cantine **n'a jamais été réglée par l'école** : seules restent les valeurs d'exemple en anglais livrées avec
  le logiciel. **Aucun repas n'a été servi.**
- L'argent de la cantine (comptes, recharges, bilan) est déjà rangé dans **Finance › Cantine**. Ce menu garde la
  partie repas : préparer les menus et servir.
- Le module **Dossier médical** est installé mais n'apparaît pas pour la direction, faute de droit. Les droits ne
  changent pas dans ce lot.

| Donnée | En base |
|---|---|
| Types de repas | 1 : « Lunch » (exemple, anglais) |
| Plats et produits | 4 : Student Lunch, Milk, Extra, Extra Pizza (exemples, anglais) |
| Menus du jour | 4 lignes d'exemple |
| Repas servis | 0 |

La cantine est **gardée** : c'est un vrai service d'école, déjà relié à Finance › Cantine et au Contrôle des frais
(« le repas est toujours servi »). Ce n'est ni un doublon ni une fonction étrangère.

## Parcours proposé (direction) : 2 étapes, 5 liens, aucun caché

| Étape | Lien (ancien nom) | Nouveau nom — phrase d'aide |
|---|---|---|
| 1 · Préparer les repas | Repas | **Types de repas** — Petit-déjeuner, déjeuner, goûter… |
| | Éléments du repas | **Plats et produits** — Ce qui est servi, avec son prix. |
| | Menus journaliers | **Menus du jour** — Le menu de la cantine, jour par jour. |
| 2 · Servir | Servir les repas | **Servir les repas** — Noter le repas pris par chaque élève, au moment du service. |
| | Prévisualisation écran | **Écran de la cantine** — Afficher le menu du jour sur un écran. |

## Autres profils

- **Enseignant et parent** : « Cantine », avec Menu du jour et Plats et prix (pages d'origine `DailyMenus`,
  `MenuItems`). Leur solde reste dans Finance › Cantine.

## À faire par l'école (pas par ce lot)

Remplacer les exemples en anglais par les vrais repas et plats de l'école, directement dans **Types de repas** et
**Plats et produits**. Ce sont des données de l'école : elles ne sont pas modifiées ici.

## Garde-fous

Aucune page effacée, aucun droit changé. Seuls changent l'ordre, les noms affichés dans le menu et les phrases
d'aide.

## Réalisation (5 novembre 2026)

- Commit `d8f9f3b` (worktree `SchoolSafeProdDrift-20261103`), plan `menuPlans.care`.
- **En production** : image `schoolsafe-rosario:menu-vie-20261105`. Contrôle `verify` : service sain, 200,
  0 erreur PHP, autres services inchangés.
- Contrôle à l'écran (direction) : **Vie et bien-être 5 liens / 5 phrases d'aide**. Les autres menus faits sont
  inchangés.
