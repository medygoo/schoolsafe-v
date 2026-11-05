# Sécurité et contrôle — analyse en profondeur et propositions (5 novembre 2026)

Méthode verrouillée : `docs/DECISIONS.md`, 5 novembre 2026. Sources :
- le code (worktree `SchoolSafeProdDrift-20261103`) ;
- les 10 pages ouvertes avec le compte de direction, sans rien enregistrer, toutes sans erreur.

Les deux constats les plus graves (S1, S2) ont été revérifiés dans le code. **Rien n'est corrigé : tout attend la
validation du propriétaire.**

## 1. Ce qui fonctionne

- **Scan des cartes au portail** :
  - vérification de la carte (signature) ;
  - arrivée enregistrée dans une transaction, sans doublon ni rejeu ;
  - parent prévenu (fil, e-mail, push, WhatsApp) ;
  - photo comparée lors d'une remise d'enfant.
- **Incidents** : modification et suppression limitées à l'école et à l'année.

## 2. Corrections proposées

### A. Sécurité et intégrité des données (priorité 1)

| # | Problème (fichier:ligne) | Correction proposée |
|---|---|---|
| S1 | **Supprimer un champ du formulaire d'incident efface la colonne** pour tous les incidents de toutes les écoles (`DisciplineForm.php:198-211`). La confirmation dit seulement « Category ». | Interdire la suppression d'un champ qui contient des données ; proposer « Ne plus utiliser » à la place, avec un message clair. |
| S2 | **« Pointage manuel » ne fonctionne pas** : 6 colonnes pour 5 valeurs, bouton hors du formulaire, heure jamais reçue, point de passage fixé à 1 (`AddRecords.php:9-31`). | Réécrire l'enregistrement : date et heure, sens, motif, auteur, école. |
| S3 | **« Vider le journal des connexions » efface celui de toutes les écoles**, sans droit de modification ni trace (`AccessLog.php:35-40`) ; une purge se lance à chaque ouverture de la page (`:50-56`). | Réserver l'effacement à la plateforme, garder une trace de l'effacement, retirer la purge à l'affichage. |
| S4 | « Ajouter des absences » enregistre **sans contrôle du droit de modification**, écrase les absences existantes et accepte n'importe quel code (`AddAbsences.php:32, 158-176`). | Exiger le droit de modification, valider les codes et les dates, prévenir avant d'écraser. |
| S5 | Outils cachés : « Effacer les absences redondantes » efface **aussi l'original** ; « Recalculer les absences journalières » **efface les commentaires de la direction** ; aucun contrôle de droit (`DuplicateAttendance.php:78`, `FixDailyAttendance.php:60`). | Garder un enregistrement sur deux et conserver les commentaires ; exiger le droit de modification. |
| S6 | Un enseignant peut modifier **n'importe quel incident** de l'école par une requête directe (`Referrals.php:75`). | Limiter la modification à ses propres élèves, sur le serveur. |
| S7 | « Signaler un incident » : enregistre malgré une valeur invalide ; ne vérifie pas que les élèves sont de l'école (`MakeReferral.php:55, 78`). | Bloquer l'enregistrement en cas d'erreur ; vérifier l'école. |
| S8 | Un code de présence utilisé peut être supprimé (`AttendanceCodes.php:100`). | Refuser la suppression d'un code utilisé (comme les codes d'inscription, lot E1). |
| S9 | Le commentaire du jour s'enregistre sans contrôle du droit de modification (`Administration.php:203`). | Exiger le droit de modification. |
| S10 | Les pointages du personnel de **toutes les écoles** sont visibles (`Entry_Exit/includes/common.fnc.php:21`). | Filtrer sur l'école en cours. |
| S11 | Pas de trace : vérifications de carte, corrections de présence par la direction, suppressions d'incidents. | Journal (qui, quand, ancienne valeur). |

### B. Cohérence

| # | Problème | Correction proposée |
|---|---|---|
| B1 | Le scanner affiche « Entrée non autorisée — contrôle rouge » alors que le refus est désactivé (`scanner-native.js:38`). | Message conforme au comportement réel. |
| B2 | Jusqu'à 4 liens « Scanner les cartes » identiques dans le menu (`central-navigation.php:62`). | Un seul lien, qui propose ensuite le mode (arrivée, remise, frais). |
| B3 | Les textes disent « un élève » sur des pages qui traitent plusieurs élèves ; noms internes d'Entry_Exit incohérents. | Textes alignés sur le menu. |

### C. Normes RDC et clarté

| # | Problème | Correction proposée |
|---|---|---|
| C1 | **Codes de présence et formulaire d'incident en anglais** à l'écran (« Present, Tardy », « Violation, Detention Assigned »). | Données, après accord : Présent, Absent, Retard, Absence justifiée ; Faute, Sanction, Parents prévenus par l'enseignant, Parents prévenus par la direction, Exclusion (direction), Commentaires. |
| C2 | Les incidents ne sont envoyés que **par e-mail au personnel** ; les absences ajoutées par la direction ne préviennent pas les parents. | Prévenir les parents par le fil de l'application (et WhatsApp si accepté). |
| C3 | Aucun mode hors connexion au portail : un scan échoue si le réseau tombe. | Ajout à décider : mettre les scans en attente, puis les envoyer au retour du réseau. |
| C4 | Messages anglais : « Referral not emailed », « Category ». | Traduire. |

### D. Mise en page moderne

Étendre la présentation moderne (comptes et École) aux pages de Sécurité : formulaires d'incident, contrôle des
présences et journal.

## 3. Ordre de réalisation proposé

1. **Lot S1 sécurité** : S1 à S11, B1. Code seulement.
2. **Lot S2 cohérence et mise en page** : B2, B3, C4, D.
3. **Lot S3 données**, après accord : C1 (codes de présence et formulaire d'incident en français).
4. **Décisions** : C2 (prévenir les parents), C3 (mode hors connexion).

## Réalisation — lot S1 (5 novembre 2026)

- Réalisés : S1 à S6, S8 à S10. Commit `3df4e44` ; les deux fichiers Entry_Exit (exclus de git) sont conservés
  dans `ops/releases/securite-s1-20261105`.
- **En production** (confirmation du propriétaire) : image `schoolsafe-rosario:securite-s1-20261105`, service sain,
  0 erreur PHP. À l'écran : Pointage manuel fonctionnel (bouton dans le formulaire ; personnel, date, heure, motif),
  et 7 autres pages ouvertes sans erreur.
- **Reportés** :
  - S7 (Signaler un incident qui enregistre malgré une valeur invalide) ;
  - S11 (journal des actions : un ajout, à décider) ;
  - B1 (message « Entrée non autorisée » du scanner, introuvable dans les fichiers attendus).
- Non rejoué : un vrai pointage, un ajout d'absences, une suppression.
- Reste : lot S2 (cohérence, mise en page), lot S3 (codes de présence et formulaire d'incident en français),
  décisions C2 (prévenir les parents) et C3 (mode hors connexion).

## Réalisation — lot S3 (5 novembre 2026)

- Appliqué en production (accord du propriétaire) :
  - codes de présence : Absent, **Présent**, **Retard (R)**, **Absence justifiée (J)** ;
  - formulaire d'incident : Faute, Retenue, Exclusion (direction seulement), Parents prévenus par l'enseignant,
    Parents prévenus par la direction, Commentaires, avec leurs choix en français.
- L'effet des codes est inchangé, et aucun incident n'existait. Sauvegardes `schoolsafe_bak_s3_*` ; retour
  arrière : `ops/releases/securite-s3-20261105/rollback.sql` (commit `ba0c911`).
- Contrôle à l'écran : un libellé restait en anglais, « Parents Contacted By Teacher » (B majuscule, une autre
  ligne de `discipline_fields`). Traduit aussi, dans une transaction ; plus aucun titre anglais dans la table.
  Script complété (commit `45dd94b`).
- Reste pour Sécurité : lot S2 (cohérence, mise en page), points reportés S7, S11, B1, et décisions C2, C3.

## Réalisation — lot S2 (5 novembre 2026)

- **En production** (accord du propriétaire) : image `schoolsafe-rosario:securite-s2-20261105` (commit `ab3e104`).
  Service sain, 0 erreur PHP.
  - B2 : un seul lien « Scanner les cartes » ; le scanner propose ensuite arrivée, remise d'enfant ou frais.
  - B3 : textes alignés sur le menu (Contrôle des présences, Ajouter des absences, Signaler un incident,
    Formulaire d'incident, Tableau des présences) ; « Entrées et sorties » devient **Pointages du personnel**.
  - C4 : confirmation et liste du formulaire d'incident, message d'envoi d'e-mail, en français.
  - D : présentation moderne des formulaires étendue aux pages de Sécurité.
- Contrôle à l'écran : 8 pages sans erreur ni texte anglais visible. Sur « Pointage manuel », la phrase d'aide
  passait en bas et le nom « Personnel » sous sa liste.
- **Correctif S2b en production** (accord du propriétaire) : image `securite-s2b-20261105` (2 fichiers CSS,
  commit `d0433eb`). Service sain, 0 erreur PHP. À l'écran, sur « Pointage manuel », la phrase d'aide est en haut
  et chaque nom de champ est au-dessus de sa valeur.
- Reste pour Sécurité : points reportés S7, S11, B1, et décisions C2, C3.
