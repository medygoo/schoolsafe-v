# Menu « Finance » — analyse « fusion » (5 novembre 2026)

Méthode : `docs/DECISIONS.md`, 4 novembre 2026. Analyse faite sur l'écran réel de la direction, le code et la
base de production (lecture seule). **Proposition à valider par le propriétaire avant tout changement.**

## Constat

- La direction voit **21 liens en 7 groupes**.
- **Deux paires portent le même nom** pour des choses différentes :
  - « Imprimer les relevés » (élèves / personnel) ;
  - « Transactions journalières » (caisse des élèves / trésorerie de l'école).
- **Le menu « Comptabilité » n'existe pas à part** : salaires, recettes et dépenses sont déjà dans Finance. Le lot
  « Comptabilité » prévu plus tard est donc couvert ici.
- La cantine est partagée entre deux menus : l'argent (comptes, transactions) dans Finance, les repas (menus,
  service) dans Vie et bien-être. Ce partage est gardé : la partie repas sera traitée avec Vie et bien-être.
- « Contrôle des frais » (SchoolSafe) règle les **seuils vert / jaune / rouge** des frais payés, utilisés par le
  scan et les rappels aux parents. C'est un réglage, pas un doublon.

| Donnée | En base |
|---|---|
| Frais des élèves | 3 frais, 1 élève, le 3 novembre |
| Paiements des élèves | 1 paiement, le 3 novembre |
| Contrôle des frais consulté | 2 fois |
| Salaires, paiements du personnel | 0, 0 |
| Recettes, dépenses, catégories | 0, 0, 0 |
| Cantine | 6 comptes élèves, 0 transaction |
| Paiements du rattrapage | 0 |

Les pages sans donnée (salaires, trésorerie, cantine) sont **gardées** : ce ne sont ni des doublons ni des
fonctions étrangères à une école, simplement pas encore utilisées.

## Parcours proposé (direction) : 6 étapes, 21 liens, aucun caché, aucun retiré

| Étape | Lien (ancien nom) | Nouveau nom — phrase d'aide |
|---|---|---|
| 1 · Frais des élèves | Assigner les frais de scolarité en masse | **Facturer une classe** — Ajouter le même frais à un groupe d'élèves. |
| | Frais de scolarité | **Frais d'un élève** — Voir, ajouter ou corriger les frais d'un élève. |
| | Soldes des élèves | **Soldes des élèves** — Qui doit combien, élève par élève. |
| 2 · Encaissements | Paiements | **Encaisser un paiement** — Enregistrer ce qu'une famille paie. |
| | Assigner paiements en masse | **Encaisser pour plusieurs élèves** — Le même paiement pour un groupe d'élèves. |
| | Transactions journalières (élèves) | **Caisse du jour** — Frais et paiements des élèves sur une journée. |
| | Imprimer les relevés (élèves) | **Relevés des élèves** — Imprimer le compte d'un élève pour sa famille. |
| | Paiements de rattrapage | **Paiements du rattrapage** — Ce que les familles paient pour les séances de rattrapage. |
| 3 · Personnel | Salaires | **Salaires** — Le salaire dû à chaque membre du personnel. |
| | Paiements du personnel | **Paiements du personnel** — Enregistrer un salaire ou une avance versés. |
| | Soldes du personnel | **Soldes du personnel** — Ce qui reste à payer à chacun. |
| | Imprimer les relevés (personnel) | **Relevés du personnel** — Imprimer le compte d'un membre du personnel. |
| 4 · Trésorerie de l'école | Recettes | **Recettes** — Argent reçu par l'école, hors frais des élèves. |
| | Dépenses | **Dépenses** — Achats et charges payés par l'école. |
| | Transactions journalières (école) | **Journal de trésorerie** — Recettes et dépenses sur une période. |
| 5 · Cantine | Comptes | **Comptes cantine** — Le solde cantine de chaque élève. |
| | Transactions | **Transactions cantine** — Recharger un compte ou noter un repas payé. |
| | Rapports | **Relevés cantine** — Le détail du compte cantine d'un élève. |
| | Rapport des transactions | **Bilan cantine** — Toutes les transactions de la cantine sur une période. |
| 6 · Réglages | Contrôle des frais | **Contrôle des frais** — Seuils vert, jaune, rouge des frais payés (scan et rappels). |
| | Catégories | **Catégories de trésorerie** — Les rubriques de recettes et de dépenses. |

## Autres profils

- **Parent** : « Mon enfant » (Frais de mon enfant, Paiements effectués, Historique du compte) et « Cantine »
  (Compte cantine, Relevé cantine).
- **Enseignant** : « Mon salaire » (Mon salaire, Mes paiements reçus) et « Cantine » (Compte cantine, Relevé
  cantine).

Ce qui s'affiche dépend des droits de chaque profil, qui ne changent pas.

## Correction trouvée pendant l'analyse (menu Sécurité)

La page **Pointage manuel** (`Entry_Exit/AddRecords.php`) affichait « Il y a un problème » : sa requête SQL
appelait `UserSchool()`, une fonction PHP, à l'intérieur du texte SQL. Corrigé : le numéro d'école est inséré
par PHP, comme sur la ligne voisine. La requête corrigée, essayée en lecture seule, trouve les 3 membres du
personnel de l'école 3. Le même défaut n'existe nulle part ailleurs. La correction partira avec ce lot.

## Garde-fous

Aucune page effacée, aucun droit changé. Seuls changent l'ordre, les noms affichés dans le menu et les phrases
d'aide. Un profil sans plan garde son menu d'origine.

## Réalisation (5 novembre 2026)

- Commit `7bcfade` (worktree `SchoolSafeProdDrift-20261103`), plan `menuPlans.finance`. Le correctif du Pointage
  manuel est conservé dans le paquet `ops/releases/menu-finance-20261105`, car le dossier `modules/Entry_Exit`
  est exclu de git par `.gitignore`.
- **En production** : image `schoolsafe-rosario:menu-finance-20261105`. Contrôle `verify` : service sain, 200,
  0 erreur PHP, autres services inchangés.
- Contrôle à l'écran (direction) : **Finance 21 liens / 21 phrases d'aide** en 6 étapes. Sécurité, École et
  Pédagogie inchangées. **Pointage manuel s'ouvre sans erreur** et liste le personnel.
- Les menus enseignant et parent ont été testés avec des listes simulées. Ils restent à regarder à l'écran.
