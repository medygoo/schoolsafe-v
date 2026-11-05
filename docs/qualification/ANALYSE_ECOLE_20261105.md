# École — analyse en profondeur et propositions (5 novembre 2026)

Méthode verrouillée : `docs/DECISIONS.md`, 5 novembre 2026, avec la mise en page moderne. Sources :
- le code (worktree `SchoolSafeProdDrift-20261103`, références `fichier:ligne`) ;
- les 15 pages ouvertes avec le compte de direction, **sans rien enregistrer** ;
- la base de production en lecture seule.

Les trois constats les plus graves ont été revérifiés dans le code. **Rien n'est corrigé : tout attend la validation
du propriétaire.**

## 1. Ce qui existe aujourd'hui

- **Les 15 pages s'ouvrent sans erreur**, et leurs titres suivent le menu (règle B3).
- **Ajouter un élève** : Prénom et Nom obligatoires ; « Second prénom » (et non Postnom) ; suffixe Jr / Sr / II ;
  identifiant et mot de passe facultatifs ; niveau, calendrier et « école suivante » obligatoires **seulement dans le
  navigateur**. Le texte d'aide de la page dit « Recherchez d'abord un élève », phrase de la fiche, sans sens ici.
- **Fiche élève** : 7 onglets. La disposition est **ancienne et désordonnée** : certaines valeurs sont au-dessus de leur
  nom, d'autres à côté ; « 07 années 3 mois 28 joursÂge » est collé ; les champs ne sont pas alignés.
- **Personnes de récupération** : 3 responsables familiaux (un seul principal) et 3 personnes accréditées, avec
  photo, validation et motif de retrait ; règles garanties par la base (`047-pickup-family-guardrails.sql`).
- **Créer les comptes parents** : exige un **e-mail**. Le mot de passe est dérivé de l'e-mail, généré par `rand()`
  et affiché en clair (`CreateParents.php:179-211, 329`).
- **Bloquer ou rendre l'accès** : ajoute un préfixe au nom d'utilisateur de l'élève ; peut aussi passer le parent en
  « Sans accès ».
- **Organisation** (calendrier, périodes, niveaux, tranches horaires) : suppression avec confirmation, mais les
  dépendances ne sont pas toujours vérifiées (voir A6 à A8).

### Ce que montre la base

| Constat | Nombre |
|---|---|
| Élèves inscrits à l'école 3 en 2026 | 10 |
| **Élèves sans aucun parent relié** | **5 sur 11** |
| Élèves sans date de naissance | 1 |
| Paires d'élèves homonymes | 2 (des élèves d'essai : « auto testcomplet », « complet inscription ») |
| Élèves avec un postnom saisi (dans « Second prénom ») | 2 |
| Codes d'inscription | 6, **tous en anglais et de vocabulaire américain** (« Moved from District », « Expelled », « Transferred in District »…) |
| Champ « Ethnie » sur la fiche élève | présent, rempli pour 1 élève |

## 2. Corrections proposées, classées par importance

### A. Sécurité et intégrité des données (priorité 1)

| # | Aujourd'hui | Problème | Correction proposée |
|---|---|---|---|
| A1 | Si la date d'entrée est vide, l'inscription est ignorée en silence (`SaveEnrollment.fnc.php:21-28`) ; le niveau n'est obligatoire que dans le navigateur (`Enrollment.inc.php:126`). | Un élève existe, a son numéro et sa carte, mais **n'est inscrit nulle part** : il disparaît des listes. | Refuser l'enregistrement d'un nouvel élève sans date d'entrée, niveau ni calendrier, avec un message clair. |
| A2 | Effacer la date d'entrée supprime l'inscription sans confirmation (`SaveEnrollment.fnc.php:110-127`). | Perte de l'inscription d'un clic. | Demander une confirmation et garder une trace. |
| A3 | Aucun contrôle de doublon d'élève (`Student.php:349-475`). | Double inscription : deux numéros, deux cartes. | Avertir si un élève de même nom, postnom et date de naissance existe déjà, avec une case de confirmation (comme pour les comptes). |
| A4 | « Créer les comptes parents » relie un parent **sans compter les 6 déjà liés** (`CreateParents.php:283-298`). Le comptage de `AddUsers.php` prend toutes les années (`:12-18`). | La règle des 6 parents est contournée, ou atteinte à tort après le passage d'année. | Même contrôle partout, compté sur l'année en cours. |
| A5 | L'onglet « Famille et récupération » (catégorie 7) **peut être supprimé** depuis Champs de la fiche élève : seules les catégories 1 à 4 sont protégées (`Fields.fnc.php:376`). | Un clic ferait disparaître l'onglet des personnes de récupération. | Protéger la catégorie 7 et son contenu technique (« INCLUDE »). |
| A6 | Supprimer un calendrier ne vérifie pas s'il est utilisé ; le calendrier par défaut n'est pas remplacé (`Calendar.php:413-437`). | Des élèves rattachés à un calendrier qui n'existe plus. | Refuser la suppression d'un calendrier utilisé ; désigner un autre calendrier par défaut. |
| A7 | Les codes d'inscription sont supprimables même s'ils sont utilisés, et communs à toutes les écoles (`EnrollmentCodes.php:50-54`). | Les inscriptions perdent leur motif. | Refuser la suppression d'un code utilisé. |
| A8 | Un niveau utilisé est seulement caché du bouton, pas protégé côté serveur (`GradeLevels.php:46-58`). | Niveau supprimé par une adresse forgée ; « classe supérieure » orpheline. | Revérifier sur le serveur avant de supprimer. |
| A9 | Retirer un parent ne retire pas son rôle de responsable principal (`AddUsers.php:105-107`). | Ses actions échouent ensuite (« interdit ») sans explication. | Prévenir avant le retrait s'il est responsable principal, et indiquer comment le remplacer. |
| A10 | Mots de passe des comptes élèves sans règle de robustesse (`Student.php:307-314`). | Même faille que celle corrigée pour le personnel (U1). | Appliquer la règle de mot de passe SchoolSafe aux élèves. |
| A11 | « Créer les comptes parents » : mot de passe tiré de l'e-mail par `rand()`, affiché en clair (`:208-211, 329`). | Mot de passe faible et devinable. | Mot de passe temporaire sûr, changé à la première connexion (comme l'assistant U1). |
| A12 | « Bloquer ou rendre l'accès » : bloquer deux fois empile le préfixe ; « rendre » réactive tout parent « Sans accès » ; aucune confirmation (`RemoveAccess.php:28-61`). | Comptes mal rétablis, ou rétablis à tort. | Bloquer une seule fois, ne rétablir que ce qui a été bloqué ici, demander confirmation. |
| A13 | Retrait d'un élève (date de fin) sans confirmation ni motif obligatoire. | Sortie d'élève sans trace du motif. | Confirmation et code de sortie obligatoire. |

### B. Cohérence

| # | Problème | Correction proposée |
|---|---|---|
| B1 | Le texte d'aide de « Ajouter un élève » dit « Recherchez d'abord un élève ». | Texte propre : « Remplissez l'identité, puis l'inscription (niveau, date d'entrée). » |
| B2 | « Relier parents et élèves » renvoie à « SchoolSafe → Familles → Enregistrer une personne familiale », un nom qui n'existe plus (`AddUsers.php:130`). | Renvoyer à « École › Personnes de récupération ». |
| B3 | « Formulaire d'inscription des parents » dit qu'une famille peut « demander une place » ; en réalité, il complète la fiche d'un élève déjà relié (`Registration.php:12, 22`). | Texte exact : « Les parents complètent ici la fiche de leur enfant. » |
| B4 | Le bouton Supprimer de la fiche élève disparaît sans explication (`Student.php:818-828`). | Afficher la raison, comme pour les comptes (U2). |
| B5 | Le menu mène à un onglet vide si le droit sur la catégorie 7 manque (`Student.php:99`). | Aligner ce droit sur celui du menu. |
| B6 | Messages en anglais ou sans accents : « Done. », « Configuration », « No et avenue », « Telephone », « carte de l eleve », « ID universel de eleve », exemple « 6th grade ». | Traduire et accentuer ; exemple « 1re Primaire, 7e EB ». |

### C. Normes RDC et clarté

| # | Problème | Correction proposée |
|---|---|---|
| C1 | Élève : « Second prénom », ordre Prénom / Second prénom / Nom. | **Postnom**, comme pour les comptes (U2) ; afficher dans l'ordre **Nom, Postnom, Prénom**. |
| C2 | Suffixe Jr / Sr / II pour les élèves. | Retiré, comme pour les comptes (une valeur existante reste modifiable). |
| C3 | Date de naissance, sexe et lieu de naissance facultatifs. | Obligatoires pour un nouvel élève : ils identifient l'élève sur la carte, le bulletin et le palmarès. |
| C4 | Adresse : « N° » et « Avenue » en double, pas de **Commune**. | N°, Avenue, Quartier, **Commune**, Ville. |
| C5 | Codes d'inscription anglais et américains. | Codes RDC (données, après accord) : Nouvelle inscription, Réinscription, Transfert entrant, Transfert sortant, Abandon, Exclusion, Fin de cycle. |
| C6 | Champ « Ethnie » sur la fiche d'un enfant. | **Décision du propriétaire** : donnée sensible ; le masquer, sans l'effacer. |

### D. Mise en page moderne

- **Fiche élève et Ajouter un élève** : même présentation que les comptes. Le nom du champ passe au-dessus de sa
  valeur, la valeur est encadrée si elle est modifiable, les champs sont alignés en grille, et l'âge est séparé de son
  étiquette.
- **Pages d'organisation** (niveaux, tranches horaires, codes) : tableaux aérés, en-têtes lisibles, bouton
  Supprimer en rouge discret avec confirmation.
- **Photo de l'élève** : le cadrage des photos est **déjà actif** (même champ « photo » que les comptes).

## 3. Améliorations ou ajouts proposés (sur accord, chacun dans son lot)

1. **Créer les comptes parents par téléphone** : identifiant tiré du nom, mot de passe temporaire, fiche
   d'identifiants à imprimer. Aujourd'hui, l'e-mail est obligatoire, et aucun parent n'en a.
2. **Liste « élèves sans parent relié »** (5 sur 11 aujourd'hui) et « élèves sans personne de récupération », avec un
   lien direct vers la fiche.
3. **Fiche d'inscription imprimable** (identité, parents, personnes de récupération, photo) pour le dossier papier.
4. **Passage d'année guidé** : contrôle des niveaux suivants avant le « Passage à l'année suivante ».

## 4. Ordre de réalisation proposé

1. **Lot E1, sécurité et intégrité** : A1 à A13. Code seulement, sans changement de droit.
2. **Lot E2, cohérence, normes RDC et mise en page moderne** : B1 à B6, C1 à C4, D.
3. **Lot E3, données**, après accord : C5 (codes d'inscription RDC), retrait des élèves d'essai si vous le voulez.
4. **Décisions du propriétaire** : C6 (champ « Ethnie »), ajouts de la partie 3.

## Garde-fous

Aucune page effacée, aucun droit changé, aucune donnée modifiée pendant l'analyse. Chaque lot sera testé dans une
image candidate, puis mis en ligne après votre accord.

## Réalisation — lot E1 sécurité et intégrité (5 novembre 2026)

- A1 à A13 réalisés. Commit `e95781f` (worktree `SchoolSafeProdDrift-20261103`), 11 fichiers.
- Image candidate : syntaxe PHP correcte. Requêtes essayées en lecture seule : le calendrier de l'école (33 usages)
  serait protégé ; l'onglet Famille est reconnu comme protégé ; un second « Josué KABEYA » serait signalé ; Josué
  compte 2 parents pour l'année en cours.
- **En production** (confirmation du propriétaire) : image `schoolsafe-rosario:ecole-e1-20261105`. Contrôle
  `verify` : service sain, 200, 0 erreur PHP, autres services inchangés. À l'écran : case « homonyme » présente sur
  Ajouter un élève, et 8 pages ouvertes sans erreur.
- **Non rejoué** : les vraies créations d'élèves et suppressions. Essai conseillé : ajouter un élève d'essai sans
  date d'entrée, qui doit être refusé.
- **Reste** : lot E2 (cohérence, normes RDC, mise en page moderne de la fiche élève), lot E3 (codes d'inscription
  RDC), décisions C6 (« Ethnie ») et les ajouts.

## Réalisation — lot E2 cohérence, normes RDC, fiche moderne (5 novembre 2026)

- Réalisés : B1 à B4, B6, C1 à C3, D. Commit `2dc3150`. Image `schoolsafe-rosario:ecole-e2-20261105` en production
  (confirmation du propriétaire) : service sain, 0 erreur PHP.
- À l'écran (fiche de Josué) : nom du champ au-dessus de la valeur, âge séparé de son étiquette, « Postnom »,
  suffixe absent.
- Non fait : C4 (champ **Commune**, nouveau champ en base : décision à part ; « Ville » devient « Ville ou
  commune »), B5 (droit sur l'onglet Famille : changement de droit).
- Reste : lot E3 (codes d'inscription RDC), décision C6 (« Ethnie »), ajouts proposés.

## Réalisation — lot E3 codes d'inscription RDC (5 novembre 2026)

- Appliqué en production (accord du propriétaire) : 7 codes RDC — Nouvelle inscription, Réinscription, Transfert
  entrant, Transfert sortant, Abandon, Exclusion, Fin de cycle. Les 2 codes utilisés gardent leur numéro et leur sens ;
  les 10 inscriptions restent rattachées. Sauvegarde `schoolsafe_bak_e3_enrollment_codes` ; retour arrière :
  `ops/releases/ecole-e3-20261105/rollback.sql` (commit `670f163`).
- Reste pour École : décisions C4 (champ Commune), C6 (« Ethnie »), B5 (droit sur l'onglet Famille), et les ajouts.
