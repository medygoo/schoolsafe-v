# ProDéli — livraison corrective, 22 octobre 2026

## Résultat

**DÉPLOYÉ. Contrôles techniques et navigation réelle connectée PASS. Acceptation propriétaire non déduite.**

- URL : https://app.shoolsafe.tech
- Source applicative de l’image : `a3fbffb158ce057d4d7742803bb394f38678bcc4`.
- Recette/source VPS : `8fc3546bc47d23395d5802a9f612185e9193acaa`, branche `deployed/prodeli-workspace-20261022`. Seule la lecture des preuves de livraison diffère du commit image ; les 16 blobs runtime sont identiques et vérifiés.
- Image active : `sha256:59fb6b25e57ea4f53237ff36dbdc0ae1122fdf57be34a6ab4fe5b3bf6fc5bb63`.
- Empreinte source : `682a8e8789796434a3f0e2ee856482b17bd68ece7fa8fe97bae96cf939b185ce`.
- Image précédente conservée arrêtée : `01c194f4…`, conteneur `schoolsafe-rosario-pre-prodeli-workspace-20261022`.

## Ce qui est livré

Arrivée native des administrateurs globaux directement sur le **Tableau de bord ProDéli** ; les comptes scolaires restent dans leur école. Dashboard distinct, compteurs réels, recherche, raccourcis conservés à l’actualisation, retours explicites. « Modules de l’école » n’est plus présenté comme une vue exécutive centrale. Réglages et console conservent leurs formulaires/traitements avec la présentation SchoolSafe. Confirmation de création d’école dans cette même présentation, revue du principal avec nom de l’école au lieu du scope brut. Connexion acceptant la limite existante de 128 caractères du premier accès.

Le bouton Configuration générale de l’école est nommé selon sa destination réelle ; aucun écran SMTP fictif ni nouveau fournisseur. Droits, scope, traitements Rosario, récupération atomique, quatre réseaux SMS, courriel et WhatsApp conservés. Keycloak reste en pause.

## Qualification de l’image exacte

Build hors réseau depuis les blobs Git canoniques, jamais le worktree Windows. 14 modifications et deux ajouts runtime ; **2 746 autres fichiers identiques**, hash/mode/UID/GID compris.

- 123 contrats contrôleur/rendu (autorisation substituée), 37 contrats PostgreSQL récupération PASS.
- 14 groupes navigateur/HTTP/PostgreSQL workspace PASS, dont connexion native globale/scolaire, reconnexion longue, ancres, accordéons, destinations, refus CSRF et séparation des périmètres.
- 13 groupes native PASS : six étapes, création/principal, école nommée dans la revue, remise unique, premier accès, renouvellement/expiration/rejeu et reconnexion personnelle via vrais formulaires.
- Dix groupes récupération/SMS PASS ; course HTTP indépendante : un gagnant, une époque ; deux erreurs de révocation annulent la transaction.
- Snapshot des onze tables fictives contrôlées identique après recette ; ressources QA internes éphémères retirées. Aucun secret/montage/donnée de production dans ces essais.
- Premier essai exact arrêté avant livraison sur une assertion immédiate du retour scolaire. Capture : contrôle correctement monté après l’assertion. Harnais corrigé pour attendre sa visibilité, sans nouvelle mutation POST ni changement runtime ; totalité rejouée sur l’image finale. La première image n’a pas été déployée.

## Sauvegarde, livraison et contrôle séparé

- Sauvegarde PostgreSQL custom : **646 977 octets**, mode 0600, liste valide ; SHA-256 `67dfce5b4135424fd28b6485e7ab3aefd9a7e183c32ead6cdd59b4ee5e196f1c` ; 14 sources préexistantes sauvegardées. Pas de restauration d’essai en production.
- Préflight puis bascule autorisée ; **91 fichiers de session et dix entrées cache Chromium préservés**. Paramètres runtime, montages, configuration et autres services inchangés. Durée de procédure 10,47 secondes, pas une mesure indépendante d’indisponibilité.
- Contrôle séparé PASS : source/image/Git, 16 hashes actifs, cinq sources récupération/SMS inchangées, sauvegardes, sessions/cache, configuration et services. Routes publiques connexion/récupération/reset 200 ; admin anonyme 302, console/SMS/WhatsApp 403, récupération no-store.
- Aucune migration, aucun changement de compte existant, aucun envoi externe, aucun fallback automatique ou restore DB. Tous les anciens conteneurs historiques restent conservés.

## Observation dans la vraie session après livraison

Session propriétaire conservée. Ouverture `/index.php` aboutit directement à `/schoolsafe-administrators.php?home=1`, titre **Tableau de bord ProDéli**, deux écoles réelles autorisées et Default School sélectionnée. Mobile 390×844 sans débordement, ancre Administrateurs et accès conservée après rechargement ; captures mobile/bureau relues. Configuration et console montrent le nouvel en-tête SchoolSafe et leur retour central.

Six destinations réelles administrateurs/comptes centraux/demandes/SMS/WhatsApp/console contrôlées sans login imposé, erreur applicative ou débordement mobile. Navigateur laissé sur l’accueil ProDéli, viewport auto. Aucun logout ni mot de passe propriétaire saisi/lu par l’agent.

## Limites explicites

L’école fictive ID 2 et l’administrateur ID 5 précédemment créés avec accord restent conservés ; aucune deuxième création de production ni suppression. Leur premier accès humain n’est pas vérifié. Les parcours complets équivalents sont qualifiés sur PostgreSQL fictif et l’image exacte, pas présentés comme une intervention humaine de production.

Les sauvegardes de fournisseurs, actions sensibles sur comptes/écoles existants et envois externes ne sont pas exécutés en production. Un formulaire ouvert n’est pas une preuve de sa soumission. Réception SMS/courriel/WhatsApp et acceptation visuelle/métier du propriétaire restent distinctes. Ne pas transformer cette livraison corrective en une affirmation de validation exhaustive de tous les modules SchoolSafe.

Preuves privées : `C:/Users/PC/SchoolSafeProductionUpdate-20261021-evidence/dashboard-owner-audit-20261022/exact-image-r2/`. Recette privée VPS : `/opt/schoolsafe-releases/prodeli-workspace-20261022/`. Aucun secret ni preuve privée dans Git.
