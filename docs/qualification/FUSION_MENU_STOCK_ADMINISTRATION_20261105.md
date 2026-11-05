# Menus « Stock / Inventaire » et « Administration » — analyse « fusion » (5 novembre 2026)

Méthode : `docs/DECISIONS.md`, 4 novembre 2026. Analyse faite sur l'écran réel de la direction, le code et la
base de production (lecture seule). **Proposition à valider par le propriétaire avant tout changement.**

## Stock / Inventaire

- **1 seul lien : « Ressources »** (`Resources/Resources.php`). Dans le logiciel d'origine, ce n'est pas un
  inventaire : c'est une **liste de liens utiles**.
- Elle ne contient que les **6 liens d'exemple du logiciel d'origine** : Print Handbook, Quick Setup Guide, Forum,
  Contribute, Report a bug, Review RosarioSIS. Ils renvoient vers le site du projet d'origine et n'ont rien à voir
  avec l'école.
- **Aucun module d'inventaire n'est installé.** Le menu « Stock / Inventaire » annonce donc une fonction qui
  n'existe pas.

**Décision : retirer « Ressources » du menu pour tous les profils.** Le menu « Stock / Inventaire », devenu vide,
disparaît. La page reste joignable par son adresse, et aucune donnée n'est touchée.

## Administration

| Aujourd'hui | Donnée | Décision | Nouveau nom — phrase d'aide |
|---|---|---|---|
| Report final (`School_Setup/Rollover.php`) | jamais fait (seule l'année 2026 existe) | garder | **Passage à l'année suivante** — Ouvrir la nouvelle année scolaire : une fois par an, sans retour arrière. |
| Configuration (`School_Setup/Configuration.php`) | réglages en service | garder | **Configuration du logiciel** — Modules et options générales. À manier avec prudence. |
| Champs école (`School_Setup/SchoolFields.php`) | 0 champ | garder | **Champs de la fiche école** — Ajouter une information à la fiche de l'école. |

Les pages **Profils et droits**, **Droits d'un utilisateur** et **Copier une école** sont aussi rangées dans ce
menu. Elles n'apparaissent pas pour le compte vérifié, faute de droit, mais reçoivent un nom et une phrase d'aide
au cas où un compte y a accès.

## Parcours proposé (direction)

1. **Fin d'année** : Passage à l'année suivante
2. **Réglages** : Configuration du logiciel · Champs de la fiche école · (Profils et droits · Droits d'un
   utilisateur · Copier une école, selon les droits)

Le passage à l'année suivante est mis à part, avec un avertissement dans sa phrase d'aide, parce qu'il est
irréversible.

## Garde-fous

Aucune page effacée, aucun droit changé, aucune donnée touchée. Une page retirée du menu reste joignable par son
adresse.

## Réalisation (5 novembre 2026)

- Proposition réalisée telle quelle : le propriétaire a demandé de continuer sans envoyer de correction.
- Commit `75230f8` (worktree `SchoolSafeProdDrift-20261103`), plans `menuPlans.inventory` et
  `menuPlans.administration`.
- **En production** : image `schoolsafe-rosario:menu-stock-admin-20261105`. Contrôle `verify` : service sain,
  200, 0 erreur PHP, autres services inchangés.
- Contrôle à l'écran (direction) : **le menu Stock a disparu** ; **Administration 3 liens / 3 phrases d'aide**.
  Les autres menus faits sont inchangés.
