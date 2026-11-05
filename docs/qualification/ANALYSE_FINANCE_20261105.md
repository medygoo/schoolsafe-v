# Finance — analyse en profondeur et propositions (5 novembre 2026)

Méthode verrouillée : `docs/DECISIONS.md`, 5 novembre 2026. Sources :
- le code (worktree `SchoolSafeProdDrift-20261103`, `modules/Student_Billing`, `Accounting`, `Food_Service`,
  `fee-control.php`, `remediation-payments.php`) ;
- les 22 pages du menu ouvertes avec le compte de direction, sans rien enregistrer, toutes sans erreur ;
- la base de production en lecture seule.

Les constats les plus graves (F1, F2, F4, F5, F7, F8) ont été revérifiés dans le code. **Rien n'est corrigé : tout
attend la validation du propriétaire.**

## 1. État réel

- Frais de l'école : 3 frais (scolarité tranche 1 et tranche 2, frais d'examen), 220 000 au total ; 1 paiement de
  75 000. Paie, trésorerie et cantine : vides, aucune catégorie de trésorerie.
- Monnaie : un seul réglage, « $ », montants affichés à l'anglaise (`75,000.00 $`).

## 2. Ce qui fonctionne

- Frais par tranches avec date d'échéance ; « Contrôle des frais » (seuils vert, jaune, rouge, utilisés au scan).
- Paiements du rattrapage : jeton anti-rejeu, verrou, filtre école et année, refus d'un montant supérieur au reste dû.
- Brouillons de paiements hors connexion : montant exact, date non future, transaction, pas de doublon.
- Un parent ne voit que ses enfants ; un enseignant ne voit que son propre salaire.

## 3. Corrections proposées

### A. Sécurité et intégrité de l'argent (priorité 1)

| # | Problème (fichier:ligne) | Correction proposée |
|---|---|---|
| F1 | **Aucun reçu n'est produit.** Le code des reçus numérotés existe (`SchoolSafeReceipts.fnc.php`, A5, signature, cachet), mais la table `schoolsafe_payment_receipts` n'existe pas en production et aucune page n'en crée. Le paiement de 75 000 n'a pas de reçu. | Créer la table (schéma versionné) ; produire un reçu à chaque paiement (simple, groupé, rattrapage) avec un **numéro qui se suit par école et par année** ; bouton « Imprimer le reçu » sur chaque paiement. |
| F2 | **Remboursement et exemption lisent un paiement ou un frais de n'importe quel élève ou école** (`StudentPayments.php:145`, `StudentFees.php:125-143`). | Exiger que le paiement ou le frais appartienne à l'élève, à l'école et à l'année en cours. |
| F3 | **Toute colonne peut être réécrite à l'enregistrement** (école, élève, auteur) par une requête forgée (`StudentPayments.php:74-103`, `StudentFees.php:48-57`, `Salaries.php`, `StaffPayments.php`, `Incomes.php`, `Expenses.php`). | N'accepter que les colonnes du formulaire (montant, date, titre, commentaire). |
| F4 | **Montants non contrôlés** : montant vide accepté en modification, montants négatifs acceptés partout (`StudentPayments.php:85`, `MassAssignPayments.php:11`, `MassAssignFees.php:10`) ; titre de frais facultatif. | Montant strictement positif (le remboursement reste un bouton à part) ; titre obligatoire. |
| F5 | **Cantine : un montant entier ≥ 100 est divisé par 100** (2 500 devient 25,00) ; plus de 7 chiffres refusés (`Food_Service/Transactions.php:93-108`). Inutilisable en francs congolais. | Prendre le montant tel qu'il est saisi. |
| F6 | **Suppressions définitives, sans trace** : paiements, frais, salaires, recettes, dépenses, transactions cantine. Un paiement de rattrapage peut être effacé depuis « Encaisser un paiement ». | Remplacer « Supprimer » par **« Annuler »** avec motif obligatoire : la ligne reste, barrée, avec l'auteur et la date ; le reçu passe à « Paiement annulé ». |
| F7 | La page **Dépenses peut modifier ou supprimer un paiement de salaire** (même table, pas de filtre « hors personnel ») (`Expenses.php:57-65, 103-106`). | Filtrer les dépenses sur les lignes sans membre du personnel. |
| F8 | **Suppression d'un compte cantine sans contrôle du droit de modification** (`Food_Service/Users/Accounts.php:102-110`) ; un enseignant peut atteindre le compte d'un parent de sa classe. | Exiger le droit de modification et l'école en cours. |
| F9 | « Facturer une classe » et « Encaisser pour plusieurs élèves » peuvent viser des élèves d'une autre école (`MassAssignFees.php:16-38`, `MassAssignPayments.php:17-38`). | Ne retenir que les élèves inscrits dans l'école en cours. |
| F10 | **Soldes mélangés entre écoles** (`StudentBalances.php:21-28`, `StaffBalances.php:19-26`, totaux de `StudentFees`/`StudentPayments`). | Filtrer sur l'école en cours. |
| F11 | **Double clic = double paiement** : seul le brouillon hors connexion est protégé (`StudentPayments.php:100`, `MassAssignPayments.php:34`, salaires). | Jeton d'envoi unique et bouton désactivé après le premier clic. |
| F12 | **Aucun contrôle de trop-perçu** : un paiement peut dépasser le reste dû sans avertissement. | Avertir et demander confirmation (le surplus devient un crédit affiché). |
| F13 | L'impression des relevés « toutes écoles » peut laisser l'utilisateur basculé sur une autre école (`Statements.php:73, 95`). | Rétablir l'école d'origine après l'impression. |

### B. Cohérence

| # | Problème | Correction proposée |
|---|---|---|
| B1 | « Contrôle des frais » s'appelle « Consulter » mais exige le droit de modifier : une personne en lecture seule reçoit un refus (`fee-control.php:12`). | Lecture pour qui consulte, modification pour qui a le droit. |
| B2 | Le verrou du barème peut être levé par la même personne, sans historique (`fee-control.php:28-30`). | Garder l'historique : qui, quand, ancienne valeur. |
| B3 | Frais scolaires et frais connexes ne sont distingués que par un mot dans le titre. | Type de frais choisi dans une liste (Scolarité, Examen, Connexes…). |
| B4 | Deux en-têtes « SchoolSafe » emboîtés sur « Contrôle des frais » et « Paiements du rattrapage ». | Une seule en-tête, celle de l'application. |

### C. Normes RDC

| # | Problème | Correction proposée |
|---|---|---|
| C1 | **Une seule monnaie, « $ »**, montants à l'anglaise. | Décision : monnaie de l'école (FC ou USD) et format français (`75 000,00 FC`) ; **plus tard, à décider** : deux monnaies côte à côte avec taux du jour. |
| C2 | Pas de **clôture de caisse** : la caisse du jour n'est qu'un rapport. | Ajout à décider : clôture journalière signée (total encaissé, reçus du jour, écart). |
| C3 | Les tranches ne sont que des frais à échéance, sans plan de paiement. | Ajout à décider : modèle de tranches par classe appliqué en une fois. |

### D. Textes et mise en page

| # | Problème | Correction proposée |
|---|---|---|
| D1 | Sur téléphone, les noms de colonnes ne sont plus en face de leurs valeurs (« Frais d'un élève », « Encaisser un paiement » : « MONTANT » à côté d'une date). | Corriger la présentation des listes sur téléphone. |
| D2 | Le lien « Brouillons de paiements hors connexion » est bleu foncé sur fond vert foncé, illisible. | Lien lisible, placé dans le cadre. |
| D3 | Messages sans accents (« enregistre », « recu », « refusee », « categorie ») ; cantine : « Deposit », « Credit », « Debit » enregistrés en anglais. | Traduire et accentuer. |
| D4 | La présentation moderne ne couvre pas encore Finance. | L'étendre aux pages de Finance. |

## 4. Ordre de réalisation proposé

1. **Lot F1 sécurité de l'argent** : F2 à F5, F7 à F13. Code seulement.
2. **Lot F2 reçus et annulation** : F1 (table, numérotation, impression) et F6 (annuler au lieu de supprimer). Touche
   la base : script réversible, essai à blanc, accord.
3. **Lot F3 cohérence et mise en page** : B1 à B4, D1 à D4.
4. **Décisions** : C1 (monnaie), C2 (clôture de caisse), C3 (plan de tranches).

Non vérifié : droits des profils parent et enseignant en base, cascades des tables du rattrapage.
