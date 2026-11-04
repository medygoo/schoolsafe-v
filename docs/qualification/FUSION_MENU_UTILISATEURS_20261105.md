# Menus « Personnel » et « Utilisateurs » — analyse « fusion » (5 novembre 2026)

Méthode : `docs/DECISIONS.md`, 4 novembre 2026. Analyse faite sur l'écran réel de la direction, le code et la
base de production (lecture seule). **Proposition à valider par le propriétaire avant tout changement.**

## Constat

- **Le menu « Personnel » n'existe pas.** Les modules qui l'alimenteraient (ressources humaines, absences du
  personnel) ne sont pas installés. Le personnel se gère dans **Utilisateurs**, et sa paie dans Finance ›
  Personnel. Les deux lots « Personnel » et « Utilisateurs » sont donc traités ensemble ici.
- Menus réels restants après ce lot : Stock (1 lien), Vie et bien-être (5), Administration (3), Contrôle et
  rapports (33).
- Utilisateurs (direction) : **10 liens**, dont 5 « Programmes enseignants » qui servent à agir à la place d'un
  enseignant dans les pages d'origine.

| Donnée | En base |
|---|---|
| Comptes | 4 direction, 2 enseignants, 9 parents |
| Champs de la fiche utilisateur | 3, dont 1 ajouté par l'école |
| Liens parent-élève | 9 |
| Notes finales d'origine, devoirs au carnet, éligibilité | 0, 0, 0 |

## Décision page par page (direction)

| Aujourd'hui | Décision | Nouveau nom — phrase d'aide |
|---|---|---|
| Ajouter un utilisateur | garder | **Ajouter un utilisateur** — Créer le compte d'un membre du personnel ou d'un parent. |
| Informations utilisateur | garder | **Fiche d'un utilisateur** — Rechercher une personne, voir et modifier sa fiche. |
| Association élèves parents | garder | **Relier un parent à ses enfants** — Partir du parent pour choisir ses enfants (6 parents au plus par élève). |
| Champs utilisateur | garder | **Champs de la fiche utilisateur** — Ajouter une information à toutes les fiches. |
| Mes préférences | garder | **Mes préférences** — Langue, thème et affichage de votre compte. |
| Saisie des notes (finales, à la place d'un enseignant) | **retirer du menu** | circuit de bulletin d'origine, 0 note ; le bulletin RDC se corrige dans Synthèse du bulletin |
| Notes du carnet (à la place d'un enseignant) | **retirer du menu** | 0 devoir ; le suivi se fait dans Suivi pédagogique |
| Notes non conformes (à la place d'un enseignant) | **retirer du menu** | dépend du carnet d'origine, 0 donnée |
| Saisir les absences (à la place d'un enseignant) | **retirer du menu** | même rôle que Sécurité › Contrôle des présences |
| Saisir l'éligibilité (à la place d'un enseignant) | **retirer du menu** | activités jamais utilisées (déjà retirées de Pédagogie) |

« Relier un parent à ses enfants » est gardé à côté d'École › « Relier parents et élèves » : c'est le même lien,
mais pris depuis le parent (utile quand il a plusieurs enfants). Les deux noms disent maintenant de quel côté on
part.

**Changement par rapport au lot Pédagogie** : le document `HARMONISATION_PEDAGOGIE_20261104.md` disait que les 5
« Programmes enseignants » restaient dans Utilisateurs. L'analyse de ce lot montre qu'ils font double emploi
avec Synthèse du bulletin, Suivi pédagogique et Contrôle des présences. Ils sortent donc du menu, tout en restant
joignables par leur adresse.

## Parcours proposé

- **Direction** : **1 · Comptes** (Ajouter un utilisateur · Fiche d'un utilisateur · Relier un parent à ses
  enfants) et **2 · Réglages** (Champs de la fiche utilisateur · Mes préférences). 5 liens, aucun caché.
- **Enseignant et parent** : **Mon compte** (Ma fiche — vos informations et votre photo · Mes préférences).

## Garde-fous

Aucune page effacée, aucun droit changé. Une page retirée du menu reste joignable par son adresse. Un profil sans
plan garde son menu d'origine.

## Réalisation (5 novembre 2026)

- Commit `1db69c8` (worktree `SchoolSafeProdDrift-20261103`), plan `menuPlans.users`. `menuKey` reconnaît
  `staff_id=new` comme un ajout.
- **En production** : image `schoolsafe-rosario:menu-utilisateurs-20261105`. Contrôle `verify` : service sain,
  200, 0 erreur PHP, autres services inchangés.
- Contrôle à l'écran (direction) : **Utilisateurs 5 liens / 5 phrases d'aide**. École, Pédagogie, Sécurité,
  Finance et Communication inchangées.
- Les vues enseignant et parent ont été testées avec des listes simulées. Elles restent à regarder à l'écran.
