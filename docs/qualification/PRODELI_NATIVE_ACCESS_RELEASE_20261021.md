# ProDéli — administrateur principal natif et premier accès

## Statut courant

**Candidat local qualifié ; image exacte et livraison à effectuer.** Production toujours sur source `3eb2e9e35984de9c40e22c571b212e727b13a2bb`, image `c8d10345…` à la dernière inspection lecture seule. Ce rapport ne déclare pas la récupération ni tout ProDéli achevés.

Base du travail : `42b9ae5`, branche `work/prodeli-completion-20261021`. Base de livraison immuable : source production `3eb2e9e`, pas main. Recette `ops/releases/prodeli-native-access-20261021/`.

## Résultat fonctionnel

- Préparation/confirmation du compte scolaire : connexion native sans appeler le fournisseur. Contrôle global habilité, droits/profil déléguable/année, **une seule école**, identité confirmée conservée côté serveur. Le POST final explicite ne dépend plus des hooks d'un plugin désactivé. Keycloak reste en pause ; handlers gérés historiques inchangés, pas de conversion d'identité liée.
- Transaction unique : compte + principal/adjoint + premier accès ciblé + suivi d'ouverture de l'assistant + audit. Un principal préexistant n'est jamais remplacé implicitement. Le profil scolaire choisi est conservé, pas remplacé silencieusement par Directeur.
- Assistant école natif : chargement ciblé des contrôleurs historiques sur son POST, sans activation globale du plugin. Correction de l'attribution native de l'école : un global avec `SCHOOLS=''` ne perd plus sa portée globale ; UPDATE limité à l'année. Corps de l'assistant et handlers gérés conservés.
- Remise confidentielle : affichage une fois, fenêtre de remise dix minutes, document local et URL officielle `https://app.shoolsafe.tech/index.php`. Aucun envoi automatique. Un fichier téléchargé contient un secret et doit être détruit après usage.
- Mot de passe initial valable 24 heures ; changement personnel obligatoire avant toute route métier, même en saisissant directement son URL. Politique globale inchangée. Formulaire Rosario réutilisé, logo SchoolSafe original, texte FR/EN ; serveur exige 12–128 caractères UTF-8, refuse NUL et conservation du temporaire.
- Premier changement : nouveau hash + consommation + audit atomiques ; ancien temporaire invalide et autres sessions temporaires refusées. La génération de session est contrôlée sur chaque requête.
- Accès initial perdu/expiré : nouvelle remise **native** après revue, CSRF, droits et nonce unique lié à acteur/cible/année/échéance. Nouveau hash/échéance/génération/audit atomiques ; sessions précédentes rejetées. Ce n'est pas une réinitialisation d'un compte ayant déjà terminé sa première connexion : celui-ci relève de la récupération existante, lot suivant.
- Portrait : traitement existant conservé après création ; échec signalé sans proposer de recréer le compte. Aucune nouvelle preuve de persistance physique des images n'est revendiquée.

## Périmètre

Dix fichiers runtime : FirstLogin, SchoolSetup, nouveau helper NativeOnboarding, Warehouse, index, CopySchool, Management, Wizard, manage et migration 038. Migration 018 existante inchangée, à appliquer en production où leadership est absente. Migration 038 additive pour les **nouveaux** comptes du parcours ; aucun compte existant ni réglage global modifié.

17 composants intégralement inchangés : connecteur Keycloak/functions et Opening, récupération et état des comptes, Studio/Notify/SMS/WhatsApp/Brevo/dispatch, Side, PasswordReset/compte-recuperation, pages de réglages Communication et JS/CSS centraux. Suffixe des handlers gérés de Management et corps historiques de l'assistant conservés. `scripts/prodeli-native-access-scope.py` vérifie cette limite contre la source déployée.

## Preuves locales

- **47 contrôles PostgreSQL 17.11 réels fictifs** : création et apostrophe, profil choisi, principal préservé, doubles créations concurrentes, identifiant personnel/élève et identité liée d'une autre année, panne audit/rattachement et reprise, premier accès expiré/renouvelé, session d'ancienne génération, CSRF, mot de passe faible/UTF-8/NUL/temporaire, consommation et rejeu. Fonctions DB et Password Rosario réelles ; contexte acteur/hooks substitués. Schéma isolé supprimé.
- **16 contrats de contrôleur** avec DB/auth substitués : droits, portée, année, expiration, acteur, CSRF/confirmation, refus des champs forgés, consommation sur échec/rejeu, remise limitée, aucun appel fournisseur dans le parcours natif.
- **Dix groupes navigateur HTTP/PHP/PostgreSQL fictif** : assistant complet et suivi atomique, portée globale conservée, expiration/renouvellement/rejeu, création sur école existante, refus CSRF, fiche affichée une fois/URL officielle, obligation de premier changement et refus des URL directes, mot de passe faible, sessions temporaires rejetées, acteur scolaire refusé pour provisionner un principal, arrivée dans la bonne école, 1280/390. Formulaire de premier accès et portail sans débordement horizontal. Comptes/écoles d'essai nettoyés.
- **92 contrats** : présentation comptes 16 + dashboard 12 + communication 28 + navigation 20 + intentions natives 16.
- **27 groupes navigateur de régression** : comptes 6, dashboard 5, communication 8, navigation 8.
- Sauvegarde/restauration réellement exécutée sur la **base fictive locale** : 118 tables restaurées, nombres de lignes comparés ; 018/038 appliquées depuis absence et rejouées, six tables existantes inchangées. Base jetable supprimée. Aucun dump de production utilisé en QA.
- Neuf lints PHP et contrôle de diff requis avant candidat ; pas de couverture globale ni de correction de CI sécurité RED annoncée.

Incidents de recette : nettoyage de l'école copiée initialement incomplet (dépendances `program_config` et `report_card_grade_scales`) ; dépendances vérifiées et nettoyage restreint aux IDs/titres fictifs rétabli. Écoles intermédiaires retirées. Précontrôle VPS initial incapable de déduire les credentials de `POSTGRES_USER` absent : corrigé en lecture de la configuration PHP effective, sans exposer de secret ni changer le service.

Preuves privées : `C:/Users/PC/SchoolSafeProductionUpdate-20261021-evidence/native-director/`. Copie sur QA 8096 : **pas encore image exacte**. Aperçu 8094 et anciens worktrees/rollbacks conservés.

## Livraison et rollback à qualifier

Image basée strictement sur `c8d10345…`, dix chemins allowlistés ; build sans réseau, comparaison exhaustive et 92 contrats offline. Rejouer transactions/navigateur sur l'image exacte avec données fictives avant déploiement.

Sauvegarde PostgreSQL privée complète avant les migrations. 018/038 exécutées ensemble dans une transaction ; aucune école/compte réel créé pour les tests de production. Conservation des sessions et des paramètres de conteneur, puis contrôle indépendant de santé/source/configuration communication.

**Retour arrière préparé, non exercé.** Aucun restore automatique ni DROP des tables additives. Dès qu'une ligne de premier accès existe, rollback ancien code **bloqué** : ce code ne contient pas la garde obligatoire et ne doit pas être remis en service en contournant le premier changement. Préserver le candidat et préparer un correctif compatible. Ne jamais vider la table pour contourner ce garde.

## Restant hors lot

Récupération après première connexion ; raccord OTP vide et test SMS sans ligne forcée ; réception externe non prouvée ; aucune activation implicite des lignes ou sessions WhatsApp. Validation humaine connectée du propriétaire après livraison. CI sécurité RED et autres travaux historiques inchangés.
