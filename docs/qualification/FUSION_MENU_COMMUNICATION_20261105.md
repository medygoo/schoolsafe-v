# Menu « Communication » — analyse « fusion » (5 novembre 2026)

Méthode : `docs/DECISIONS.md`, 4 novembre 2026. Analyse faite sur l'écran réel de la direction, le code et la
base de production (lecture seule). **Proposition à valider par le propriétaire avant tout changement.**

## Constat

- La direction voit **4 liens**, dont un rangé sous le titre « Suivi de mon enfant », prévu pour le parent.
- **Notifications** (`SchoolSafe/Notifications.php`) est la boîte de réception **du parent**. Ouverte par la
  direction, elle affiche seulement « Connexion avec un compte parent SchoolSafe requise » : c'est une impasse.
- **Notifier les parents** (`Custom/NotifyParents.php`) est, selon son propre code, un outil rapide qui
  **réinitialise les mots de passe** des comptes parents puis leur envoie un e-mail. Or **aucun des 9 parents
  n'a d'e-mail**. L'outil est donc inutile, et dangereux en cas de clic par erreur. La création des comptes
  parents se fait déjà dans École › Familles et accès.
- La messagerie interne d'origine est déjà retirée du menu (décision antérieure, `hiddenMenuPath`).

| Donnée | En base |
|---|---|
| Notifications SchoolSafe | 9 événements, 28 envois (e-mail, fil, WhatsApp), le dernier le 3 novembre |
| Annonces du portail, sondages | 0, 0 |
| Messages internes, courriers | 0, 0 |
| Parents avec / sans e-mail | 0 / 9 |

## Décision page par page

| Profil | Aujourd'hui | Décision | Nouveau nom — phrase d'aide |
|---|---|---|---|
| Direction | Notes du portail | garder | **Annonces du tableau de bord** — Un message affiché à l'accueil des parents et du personnel. |
| Direction | Sondages du portail | garder | **Sondages** — Poser une question aux parents ou au personnel. |
| Direction | Notifier les parents | **retirer du menu** | réinitialise les mots de passe ; 0 parent avec e-mail ; reste joignable par son adresse |
| Direction | Notifications (sous « Suivi de mon enfant ») | **retirer du menu** | page réservée aux parents : impasse pour la direction |
| Parent | Notifications | garder | **Messages de l'école** — Devoirs, notes, absences et annonces reçus. |
| Parent | Signaler une absence ou un retard | garder | **Signaler une absence** — Prévenir l'école d'une absence ou d'un retard. |

## Parcours proposé

- **Direction** : **1 · Annonces** — Annonces du tableau de bord · Sondages (2 liens, aucun caché).
- **Parent** : **Mon enfant** — Messages de l'école · Signaler une absence.

Les **convocations et courriers** restent dans Pédagogie › Communiquer et archiver, là où la direction les
prépare. Ils ne sont pas répétés ici, pour qu'il n'y ait qu'un seul endroit.

## Garde-fous

Aucune page effacée, aucun droit changé. Une page retirée du menu reste joignable par son adresse. Un profil sans
plan garde son menu d'origine.

## Réalisation (5 novembre 2026)

- Commit `d71c85d` (worktree `SchoolSafeProdDrift-20261103`), plan `menuPlans.communication`. « Notifications »
  sort aussi du menu de l'enseignant, pour la même raison (compte parent requis).
- **En production** : image `schoolsafe-rosario:menu-communication-20261105`. Contrôle `verify` : service sain,
  200, 0 erreur PHP, autres services inchangés.
- Contrôle à l'écran (direction) : **Communication 2 liens / 2 phrases d'aide**. Finance, Sécurité, École et
  Pédagogie inchangées.
- La vue parent a été testée avec une liste simulée. Elle reste à regarder à l'écran.
