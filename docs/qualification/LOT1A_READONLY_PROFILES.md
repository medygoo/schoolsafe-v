# LOT 1-A — lecture seule dans le hub des profils

> Rapport historique de qualification avant publication. Pour la livraison ultérieure autorisée sur le local, GitHub et le VPS, voir [LOT1A_RELEASE.md](LOT1A_RELEASE.md). Les mentions « NON DÉPLOYÉ » ci-dessous décrivent l'état au moment de cette qualification.

Date : 20 octobre 2026. Statut : GREEN en qualification isolée pour le périmètre décrit ; NON DÉPLOYÉ. Le LOT 1 global reste RED avec ses autres réserves.

## Mandat et périmètre

GO utilisateur pour reproduire puis corriger le contrôle readonly sur la création, modification et suppression des profils scolaires. Travail limité à la qualification ; aucun push/commit, accès VPS, changement de Work ou migration de production.

Base Git : 84c8891083cc3e436fe6c60209babe30e14afcb8, branche qualification/phase2-20261020. Les modifications préexistantes ont été conservées ; comparaison des statuts avant/après, aucune entrée initiale disparue. Le projet principal n'a pas reçu le correctif.

## Correctif

Fichier : app/modules/codex-ui-v1/extensions-test-runtime/ecole-profils.php.

Ajout de neuf lignes avant le dispatch des commandes, après vérification CSRF :
- réutilisation de SchoolSafeAccountBlocksWrite() ;
- vérification de SchoolSafeAccountStateForSchool() pour l'école ciblée, qui peut différer de l'école de session ;
- refus HTTP 403 via SchoolSafeProfilsDeny() pour readonly/suspended/expired selon ces mécanismes.

Aucun nouveau modèle de droits, changement de schéma, refonte Rosario ou changement des règles de délégation. GET inchangé. Les boutons restent visibles : la garantie livrée est le refus serveur, pas une refonte visuelle du mode readonly.

SHA-256 exact avant : 82202143888aa964ff5f26a66fcdb1403d0b43f205058d77daf59b961fb3d2eb
SHA-256 exact après : 8277afa1b7267527dc89c76fc652a0067b89881bb09e0f9a50c2f7dddd8b01ac

## Environnement jetable

Dossier de preuves : C:/Users/PC/SchoolSafeLot1A-20261020.

- Conteneurs dédiés : schoolsafe-lot1a-web, schoolsafe-lot1a-pg.
- Réseau dédié --internal : schoolsafe-lot1a-net.
- PostgreSQL 17.11, base lot1a en tmpfs ; aucun dump d'une base existante.
- Image PHP déjà disponible : schoolsafe-rosario:20261020-phase2-candidate2-84c8891.
- Socle rosariosis.sql puis migrations existantes 014, 015, 016, 034 appliqués uniquement à cette base neuve.
- Comptes de démonstration neutralisés ; cinq identités fictives, mots de passe aléatoires non conservés.
- Plugins désactivés, aucun secret de communication, aucun worker de communication lancé.
- Seul le fichier corrigé est monté depuis la qualification, en lecture seule dans le conteneur.

Le réseau interne Docker Desktop ne publiait pas le port demandé : relais temporaire Windows sur 127.0.0.1:8091 vers Apache du seul conteneur via docker exec. Après des délais intermittents, le relais a utilisé un canal PHP/cURL persistant, sans ouvrir d'accès réseau sortant au conteneur. Le relais ne reproduit pas toute la chaîne proxy de production : TLS et cookies Secure ne sont pas qualifiés par ce lot.

## Reproduction avant correction

Commande : node scripts/lot1a-readonly-profiles.cjs before

Résultat valide de reproduction : 12 PASS, 10 FAIL, code 1.

Les créations, modifications de droits et suppressions modifiaient réellement les tables malgré readonly : restriction école, restriction globale, école cible readonly d'un compte multi-écoles (9 échecs). Un passage readonly sur une session déjà ouverte laissait aussi passer la mutation (10e échec).

Les lectures, opérations actives, refus CSRF et refus hors école passaient déjà. Les échecs sont conservés dans before.json, et ne sont pas des simulations.

## Vérification après correction

Commande : node scripts/lot1a-readonly-profiles.cjs after

Résultat final : 24 PASS, 0 FAIL, code 0.

- 3 lectures maintenues : readonly scolaire, global, école cible du compte multi-écoles.
- 9 mutations readonly refusées : 3 actions x 3 contextes ; HTTP 403 et snapshots métier strictement identiques.
- 3 mutations actives conservées : création, modification, suppression vérifiées en SQL.
- 3 refus CSRF conservés, données inchangées.
- 3 refus hors école conservés, HTTP 403 et données inchangées.
- 1 transition active -> readonly sur session déjà ouverte : refus sans mutation métier.
- 2 contrôles de formulaire Chromium : création active à 1280x850, refus readonly à 390x850, preuves SQL et captures.

Les snapshots couvrent user_profiles, profile_exceptions et staff_exceptions ; les sessions et journaux techniques ne sont pas présentés comme immuables. Les tests navigateur bloquent les ressources autres que les documents locaux ; ils prouvent le formulaire natif et son refus, pas l'ensemble du frontend.

php -l ecole-profils.php : PASS.
git diff --check : PASS, avertissements de fins de ligne sur des fichiers préexistants sans changement de ce lot.

Pas de mesure instrumentée de couverture globale. Pas de preuve de concurrence, de fonctionnement de toutes les routes ou de conformité visuelle globale. Les chemins suspended/expired sont refusés par le garde ajouté, mais leurs scénarios complets ne sont pas ajoutés à cette recette readonly.

## Incidents de harnais distingués

Première tentative : port inaccessible, aucun scénario métier exécuté. Après correction : un timeout de transport puis une interruption du harnais avant sa dernière preuve navigateur. Ces tentatives ne sont pas comptées comme GREEN. after-transport-failure.json conserve un échec de transport ; le résultat final complet est after.json. Le journal de transport contenant des en-têtes de sessions fictives a été supprimé ; aucun secret réel n'a été utilisé.

## Artefacts

Dans C:/Users/PC/SchoolSafeLot1A-20261020 :
- setup.py et relay.py : infrastructure de reproduction locale ; démarrer le relais après setup.py avant le harnais ;
- environment.json : socle et empreintes ;
- ecole-profils.before.php : sauvegarde exacte ;
- before.json, after.json, proof-summary.json ;
- after-active-1280.png, after-readonly-390.png ;
- traces ZIP correspondantes, privées (sessions fictives désormais détruites) ;
- cleanup.json : destruction des deux conteneurs et du réseau confirmée.

Harnais conservé dans scripts/lot1a-readonly-profiles.cjs. Il cible exclusivement les noms de conteneurs/base et l'URL locale du lot. Il dépend du Playwright 1.62.1 préexistant dans le workspace principal. Ne pas le rediriger vers la production.

## Nettoyage et retour arrière

Les deux conteneurs, la base tmpfs, leurs ressources anonymes et le réseau dédiés ont été supprimés. Le relais temporaire a été arrêté. Les conteneurs préexistants n'ont pas été modifiés par le lot.

Retour arrière du code : retirer uniquement le bloc ajouté, après vérification du diff ; ou restaurer la sauvegarde exacte si le fichier n'a pas reçu de travail ultérieur. Ne jamais faire git reset --hard ni restaurer tout le dépôt : il contient des travaux préexistants non publiés. Aucun rollback SQL de production nécessaire.

## Reste ouvert

Défaut de compteurs de révocation, garde des sessions élèves, profils sécurité/tuteur, configuration legacy du module SchoolSafe, isolation des portails et concurrence/preuve de remise : inchangés, non résolus par ce lot. Aucune permission de publication ou de déploiement n'est implicite.

STOP après rapport ; reprise de l'outillage ou autre correctif sur nouvelle validation.
