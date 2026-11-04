# Menu « École » — analyse « fusion » (4 novembre 2026)

Méthode : `docs/DECISIONS.md`, 4 novembre 2026. Analyse faite sur l'écran réel de la direction, le code et la
base de production (lecture seule). **Proposition à valider par le propriétaire avant tout changement.**

## Constat

- La direction voit **16 liens en 5 groupes**, rangés par module technique.
- **Défaut** : le lien « Personnes de récupération » (onglet Famille et récupération de la fiche élève) est envoyé
  par le serveur, mais le filtre du menu le rejette, parce que son adresse contient `&category_id=7`. C'est le même
  défaut que celui du « Mode d'emploi » corrigé dans Pédagogie. Le lien n'apparaît donc **jamais**.
- Les 5 pages d'« Organisation scolaire » sont aussi les étapes de **Préparer l'année** (Pédagogie).
- **Toutes les pages ont des données réelles** : rien n'est à retirer.

| Donnée | En base |
|---|---|
| Élèves, inscriptions | 11 élèves, 10 inscriptions |
| Codes d'inscription | 6, dont 2 utilisés |
| Champs de la fiche élève | 14, dont 2 ajoutés par l'école (ex. lieu de naissance) |
| Parents reliés à un élève | 9 liens, 9 comptes parents |
| Accès bloqués | 3 |
| Formulaire d'inscription des parents | réglé (1 réglage) |
| Niveaux, tranches horaires, périodes, jours de classe | 27, 33, 24, 219 |

## Décision page par page (direction)

| Aujourd'hui | Décision | Nouveau nom et phrase d'aide |
|---|---|---|
| Ajouter un élève | garder | **Ajouter un élève** — Inscrire un nouvel élève et créer sa fiche. |
| Informations élève | garder | **Fiche élève** — Rechercher un élève, voir et modifier sa fiche. |
| Assigner des infos élève en groupe | garder | **Modifier plusieurs élèves à la fois** — Remplir le même champ pour un groupe d'élèves. |
| *(absent : défaut)* Personnes de récupération | **réparer et garder** | **Personnes de récupération** — Qui peut venir chercher l'enfant, avec photo. |
| Association parents élèves | garder | **Relier parents et élèves** — Rattacher le compte d'un parent à son enfant (6 au plus). |
| Créer des utilisateurs parents | garder | **Créer les comptes parents** — Créer les comptes à partir de l'e-mail de la fiche élève. |
| Inscription | garder | **Formulaire d'inscription des parents** — Ce que les parents remplissent en ligne. |
| Bloquer l'accès | garder | **Bloquer ou rendre l'accès** — Couper ou rétablir l'accès d'un élève et de ses parents. |
| Informations de l'école | garder | **Informations de l'école** — Nom, adresse, téléphone, logo, responsable. |
| Calendriers | garder | **Calendrier des jours de classe** — Jours de classe, congés et fériés. |
| Niveaux scolaires | garder | **Niveaux et passage de classe** — Les niveaux de l'école et le niveau suivant. |
| Tranches horaires | garder | **Tranches horaires** — Les heures de la journée de classe. |
| Périodes scolaires | garder | **Année et périodes** — Trimestres, périodes et examens de l'année. |
| Champs élèves | garder | **Champs de la fiche élève** — Ajouter une information à toutes les fiches. |
| Codes d'inscription | garder | **Codes d'inscription** — Motifs d'entrée et de sortie d'un élève. |
| Studio de cartes | garder | **Studio de cartes** — Créer et imprimer les cartes SchoolSafe. |

## Parcours proposé (direction) : 5 étapes, 16 liens, aucun caché

1. **Élèves** : Ajouter un élève · Fiche élève · Modifier plusieurs élèves à la fois
2. **Familles et accès** : Personnes de récupération · Relier parents et élèves · Créer les comptes parents ·
   Formulaire d'inscription des parents · Bloquer ou rendre l'accès
3. **Organisation de l'école** : Informations de l'école · Calendrier des jours de classe · Année et périodes ·
   Niveaux et passage de classe · Tranches horaires
4. **Réglages de la fiche élève** : Champs de la fiche élève · Codes d'inscription
5. **Cartes SchoolSafe** : Studio de cartes

Les noms suivent ceux des étapes de **Préparer l'année**, pour qu'une même page porte le même nom partout.

## Autres profils

- **Enseignant**, s'il en a le droit : « Mes élèves » (Fiche élève, Parents associés) et « Mon école »
  (Informations, Calendrier, Année et périodes, Tranches horaires).
- **Parent** : « Mon enfant » (Fiche de mon enfant, Formulaire d'inscription) et « L'école » (Informations,
  Calendrier, Année et périodes).

Ce qui s'affiche dépend des droits de chaque profil, qui ne changent pas.

## Garde-fous

Aucune page effacée, aucun droit changé. Seuls changent l'ordre, les noms affichés dans le menu et les phrases
d'aide. Un profil qui n'a aucune de ces pages garde son menu d'origine.

## Réalisation (5 novembre 2026)

- Commit `b0bf8d6` (worktree `SchoolSafeProdDrift-20261103`). Le menu se range avec une **mécanique commune**
  (`menuPlans` et `organizeByPlan` dans le `scripts.js` du thème). Les prochains menus n'auront qu'à ajouter
  leur plan.
- **En production** : image `schoolsafe-rosario:menu-ecole-20261105` (paquet `ops/releases/menu-ecole-20261105`).
  Contrôle `verify` : service sain, 200, 0 erreur PHP, autres services inchangés.
- Contrôle à l'écran (direction) : **16 liens, 16 phrases d'aide**, en 5 étapes. « Personnes de récupération »
  est présent et ouvre l'onglet `category_id=7`. Pédagogie est inchangée.
- Les menus enseignant et parent ont été testés avec des listes simulées. Ils restent à regarder à l'écran.
