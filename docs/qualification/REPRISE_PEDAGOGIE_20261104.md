# Reprise — Pédagogie SchoolSafe (fin de conversation du 4 novembre 2026)

À lire en premier dans la nouvelle conversation, avec `AGENTS.md`.

## État

- **Pédagogie est terminée et en production** (https://app.shoolsafe.tech). Feuille de route : `docs/superpowers/plans/2026-11-04-pedagogie-documents.md` (lots 1 à 11 faits, clôture et harmonisation en fin de fichier).
- Décision du propriétaire : **ne plus ajouter de fonctionnalité à Pédagogie** (progression EPST, compétences, cahier de liaison, palmarès, meilleurs travaux, transfert du dossier : retirés).
- Code : worktree `C:\Users\PC\SchoolSafeProdDrift-20261103`, branche `ops/prod-drift-capture-20261103`, dernier commit `88b348e` (local = distant).
- Image en service : `schoolsafe-rosario:dbget-ecritures-20261104`. Migrations appliquées jusqu'à **069**.
- Mise en ligne : `mkrel.py <nom> <image de base> "<commentaire>" fichiers…` depuis la racine du worktree (préfixe `NEW:` pour les nouveaux fichiers), puis sur le VPS `release.sh prepare`, test dans l'image candidate, `deploy`, `verify`.

## Essai réel en cours (classe 1ère Primaire, élève Josué KABEYA)

Scénario : `docs/qualification/ESSAI_PEDAGOGIE_20261104.md`.

| Partie | État |
|---|---|
| A. Direction : papier de l'école, lieu de naissance, 20 branches appliquées (titulaire Medy Lomela) | **faite** (valeurs temporaires « ESSAI » à remplacer) |
| B. Enseignant Medy Lomela : appel + cahier de textes, devoir « Numération et opérations » /10, correction 4/10 avec corrigé et photo | **à faire par le propriétaire** |
| C. Parent Augustin KABEYA : fil, correction, « J'ai vu », explication lingala, dossier | à faire |
| D. Titulaire : notes du bulletin (1re, 2e période, examen), synthèse, proposer | à faire |
| E. Direction : valider le 1er trimestre, suivi pédagogique, journal des notes | à faire |
| F. Parent : ouvrir le bulletin RDC | à faire |

**Fait avant l'étape B** : regroupement du menu Pédagogie par bloc et par profil, en production (image
`pedagogie-menu-2-20261104`, commits `d6a54c5` puis `c5f1020`). Le détail est dans `docs/qualification/HARMONISATION_PEDAGOGIE_20261104.md`.
L'image en service n'est donc plus celle indiquée plus haut.
Puis mise en page commune des pages (tableaux lisibles sur téléphone, une taille de sous-titre) : image en service
**`pedagogie-mise-en-page-20261104`** (commits `edee573`, `2573db6`). Pages enseignant et parent à regarder pendant l'essai.

**Suite décidée** : appliquer la méthode « fusion » (`docs/DECISIONS.md`, 4 novembre 2026) aux autres menus, un par lot :
École, Sécurité et contrôle, Finance, Communication, Personnel, Vie et bien-être, Stock, Comptabilité,
Administration, Rapports, Utilisateurs. Montrer l’analyse page par page au propriétaire avant tout changement.
**École : fait et en production** (image `menu-ecole-20261105`, `docs/qualification/FUSION_MENU_ECOLE_20261104.md`). **Sécurité et contrôle : fait et en production** (image `menu-securite-20261105`, `docs/qualification/FUSION_MENU_SECURITE_20261105.md`). **Finance : fait et en production** (image `menu-finance-20261105`, `docs/qualification/FUSION_MENU_FINANCE_20261105.md` ; couvre aussi Comptabilité, qui n’est pas un menu séparé). **Communication : fait et en production** (image `menu-communication-20261105`, `docs/qualification/FUSION_MENU_COMMUNICATION_20261105.md`). **Personnel et Utilisateurs : fait et en production** (image `menu-utilisateurs-20261105`, `docs/qualification/FUSION_MENU_UTILISATEURS_20261105.md` ; Personnel n’est pas un menu séparé). **Vie et bien-être : fait et en production** (image `menu-vie-20261105`). Menus restants : Stock, Administration, Contrôle et rapports.

Après chaque étape : vérifier dans la base (lecture seule) et corriger tout écart de présentation ou de fonctionnement, sans nouvelle fonctionnalité.

## Défaut trouvé par l'essai (corrigé)

`functions/DBGet.fnc.php` : `DBGet` n'exécutait une requête texte que si elle commençait par SELECT ; les « INSERT/UPDATE … RETURNING » échouaient (devoir, courrier, archive, cahier de textes, branches). Corrigé (commit `88b348e`). **Leçon** : tester avec les vraies fonctions de l'application, pas avec la version simplifiée de la ligne de commande.

## À faire par le propriétaire

- Remplacer les valeurs « ESSAI » (lieu de naissance de Josué ; commune, code, N° ID, province dans « Papier de l'école ») et vérifier la liste des branches avec un ancien bulletin.
- Créer le compte du professeur d'anglais pour une classe bilingue.
- SMS : téléphone Android + SIM + forfait SMS + application SMSGate.
- Essayer sur téléphone : micro de JASPE, hors connexion.
- Décider : absences automatiques (interrupteur coupé), 6 comptes d'essai à placer ou retirer.

## Règles permanentes rappelées

Répondre en français ; ne jamais saisir de mot de passe ni créer de compte ; aucun secret dans les documents ; aucune suppression de données sans accord ; production seulement par image vérifiée ou SQL transactionnel avec retour arrière ; JASPE ne reçoit jamais les noms des élèves ; l'école garde la main (interrupteurs).
