# ProDéli — reprise du dashboard et recette des parcours, 22/10/2026

## État

**CORRECTIF DÉPLOYÉ, CONTRÔLES TECHNIQUES ET NAVIGATION CONNECTÉE PASS.** Image `59fb6b25…`, source applicative `a3fbffb`, recette `8fc3546`. [Rapport de livraison](PRODELI_WORKSPACE_RELEASE_20261022.md). Le propriétaire a donné son GO explicite pour finir ProDéli et déployer en production. Le dashboard précédent a été rejeté après vérification connectée ; aucune acceptation du candidat n’est déduite du GO. Source de départ `6e90fe5`, production technique précédente `68de604` / image `01c194f4…`. Les preuves récupération/SMS ne constituent pas une acceptation du dashboard. Le propriétaire autorise la correction et les parcours complets, dont une école/un administrateur de test jusqu'au premier accès et au tableau scolaire. Aucun utilisateur existant ne doit être modifié, aucun message réel envoyé sans destinataire expressément autorisé.

## Défauts reproduits avant patch

- Vraie connexion native d'un global fictif dans le navigateur : arrivée `/Modules.php` au lieu de `/schoolsafe-administrators.php?home=1`. Test FAIL avec nettoyage des fixtures PASS.
- Branche de redirection de `index.php` : condition supplémentaire `schoolsafe-keycloak-session`, incohérente avec le parcours natif sans Keycloak. Contrats exécutant la branche avec autorisation substituée : global natif FAIL, global géré et comptes scolaires conservés PASS.
- Sur le navigateur de production connecté par le propriétaire : accueil scolaire sur ordinateur et 390×844, action Vue exécutive -> panneau Modules 1 ; Configuration -> interface FlatSIS/native, pas la coquille SchoolSafe.
- Champ de connexion limité à 42 caractères, incompatible avec les 12–128 caractères acceptés au premier accès. Constat par saisie synthétique et inspection du champ ; un test de connexion longue doit vérifier la correction.
- Cinq sources de diagnostic comparées aux blobs Git par SHA-256 sur production : correspondance exacte. Pas une hypothèse de cache ou de source non livrée.

## Périmètre correctif

Réutiliser les contrôleurs, la base et les gardes existants. Aucun changement de rôle par nom d'utilisateur, aucune activation Keycloak, aucune nouvelle infrastructure de comptes. Dashboard global distinct de l'espace scolaire, sans nouveau menu central ; répertoire/action de création/comptes/communication dans la même page, vrais compteurs limités au périmètre autorisé. École ouverte explicitement, retour central visible pour le global seulement. Habillage SchoolSafe des réglages et de la console native sans remplacement des traitements.

## Matrice de recette obligatoire

La matrice décrit les conditions à couvrir, **pas des PASS implicites**. Les exécutions acquises et leurs limites figurent dans le relevé ci-dessous ; les actions qui n’y sont pas explicitement validées restent NON EXÉCUTÉES. L'inventaire navigateur des contrôles est enregistré dans les preuves privées, sans valeurs de champs, jetons ou mots de passe. Présence d'un lien = inventaire, pas validation de son action. Chaque résultat final doit distinguer test isolé réel, contrôle de production, action manuelle, panne fournisseur ou non exécuté.

| Parcours / action | Attendu | Limite d'essai |
|---|---|---|
| Connexion native globale | Dashboard ProDéli distinct, sans école implicite | Compte fictif ; propriétaire pour vraie session |
| Connexion administrateur scolaire | Son école seulement, pas de privilège central | Compte fictif, scope vérifié |
| Destination explicite après connexion | Destination native sûre conservée | Contrôleur + navigateur |
| Mot de passe long | Aucun tronquage jusqu'à 128 caractères, connexion effective | Secret synthétique, jamais un mot de passe réel dans les outils |
| Accueil global ordinateur/téléphone | Actions utiles visibles, pas de menu scolaire/pagination scolaire | Captures relues, overflow et clavier |
| Recherche des écoles | Filtre, absence de résultat et rétablissement complet | Lecture seule |
| Ouvrir une école / retour ProDéli | Changement natif protégé, bonne école, retour visible | CSRF, année, périmètre et absence d'élévation |
| Créer / reprendre une école | Six étapes, précédent/suivant, brouillon et récapitulatif | École fictive clairement identifiée |
| Images de l'école / du principal | Chargement, cadrage, validation et refus des fichiers invalides | Images synthétiques sans enfant/personne réelle |
| Confirmer création / doublon / reprise | Une école seulement, pas de copie de personnes ou d'argent | PostgreSQL fictif ; production uniquement sur données de test autorisées |
| Préparer / confirmer le principal | Compte, rattachement, suivi et audit cohérents | Profil déléguable, scope unique et rejet/rejeu |
| Remise de l'accès / téléchargement | Remise unique, URL officielle et document confidentiel | Aucun secret en capture/log/doc ; remise réelle par l'humain |
| Premier accès / mot de passe personnel | Politique respectée, accès scolaire bloqué avant achèvement | Sessions distinctes et expiration |
| Reconnexion du nouvel administrateur | Mot de passe personnel réellement utilisable par le formulaire | Pas seulement un POST API ni une session restée ouverte |
| Tableau du nouvel administrateur | SchoolSafe, bonne école, pas de contrôles globaux | Bureau/téléphone, permissions et retour |
| Demandes d'ouverture | Liste, détail et actions réellement permises | Création/validation sur fixtures seulement |
| Liste des administrateurs | Recherche, détail, annulation et liens de retour | Lecture réelle de la base fictive |
| Comptes centraux | Global seul, aucune promotion implicite | Refus scolaire obligatoire |
| Renouvellement de l'accès initial | Confirmation, génération, expiration et anti-rejeu | Compte fictif non encore entré |
| Comptes gérés / liaison | État honnête si fournisseur en pause | Pas de réactivation automatique ni conversion d'identité |
| États d'un compte / révocation | Actif, lecture seule, suspendu/expiré, sessions refusées | Compte fictif seulement, réversibilité contrôlée |
| État d'une école | Actif/suspendu/inactif, données conservées | École fictive seulement ; jamais celle du propriétaire |
| Direction / principal | Choix autorisé, principal unique et retrait contrôlé | École/compte fictifs, concurrence existante rejouée |
| Réglages de l'école | Habillage SchoolSafe, contrôles natifs, bon contexte | Aucune sauvegarde sur école existante réelle |
| Configuration du courriel | Destination cohérente, droits et secret masqué | Aucun courriel d'essai externe |
| SMS quatre réseaux | Liens, édition, annulation, sauvegarde et sélection confirmée | Simulation isolée, aucun envoi/activation réel |
| WhatsApp | Liens/édition/confirmation/droits et retours | Fournisseur indisponible déclaré, aucun envoi réel |
| Récupération | Non-régression OTP/reset/consommation unique et révocation | PostgreSQL fictif et image exacte |
| Console native | Habillage, filtres, CSRF et actions sur cible autorisée | Actions sensibles uniquement sur fixtures |
| Retour, annulation, validation des formulaires | Aucun cul-de-sac ni action silencieuse | Inventaire par contrôle avec résultat effectif |
| Déconnexion et reconnexion | Sessions correctement terminées | Automatisation isolée ; propriétaire pour sa session réelle |

## Relevé d’exécution et limites

### Production, session connectée du propriétaire

- **PASS navigation/lecture seulement** : administrateurs, demandes, comptes centraux, SMS (quatre lignes et formulaire Modifier), WhatsApp (formulaire Modifier), console et retours. Aucun envoi, sauvegarde de fournisseur ni mutation d’un compte existant. Ouvrir un formulaire ne valide pas sa soumission.
- **PASS recherche/accordéon** : recherche de l’école fictive -> un résultat sur deux ; effacement -> rétablissement des deux ; Informations à réunir effectivement ouvert.
- **PASS création autorisée** : école `TEST PRODELI RECETTE 20261022` ID 2 et administrateur `qa.prodeli.20261022` ID 5, profil scolaire 7, scope `,2,`. Contacts fictifs `.invalid`, images synthétiques. Créations effectuées après autorisation spécifique, sans suppression ni envoi.
- **PASS partiel images** : validation de cadrage obligatoire, contain/cover/zoom/flèches/rotation/réinitialisation/retrait/rechargement observés, reprise du brouillon et aperçu ; réception du fichier après téléchargement NON VÉRIFIÉE.
- **FAIL avant livraison, corrigés par le lot** : arrivée native globale scolaire, confirmation Copy School / New school native anglaise, revue principal avec scope brut `,2,`, console ancienne présentation, bouton courriel pointant vers la configuration générale. Après livraison : arrivée globale native, configuration/console SchoolSafe et libellé exact contrôlés dans la vraie session ; confirmation d’école et revue du principal qualifiées sur l’image exacte sans créer une deuxième école de production.
- **NON EXÉCUTÉ humain** : remise confidentielle conservée par le propriétaire, premier accès, changement personnel et reconnexion du nouvel administrateur. Aucun secret lu ou capturé par l’agent.
- **PASS restauration du contexte** : sélection initiale Default School rétablie par le changement natif, puis retour à l’accueil central et nom sélectionné relu. École/compte fictifs conservés, aucune suppression autorisée.

### Candidat local, non committé / non livré

- `workspace-contracts-after-r3.log` : 31 contrats contrôleur/rendu, autorisation substituée.
- `native-after-r1/native-result.json` : 13 groupes navigateur/PostgreSQL fictif et nettoyage PASS, dont premier accès et reconnexion personnelle synthétiques. Ne prouve pas le parcours humain de production.
- `browser-after-r6/workspace-result.json` : **14 groupes navigateur/PostgreSQL fictif PASS, nettoyage true**, avec ancres centrales conservées au rechargement, accordéons, arrivée globale/scolaire, phrase longue, refus CSRF et absence d’élévation.
- `node --test scripts/prodeli-central-navigation.test.cjs` : RED 5 PASS/2 FAIL, puis **7 PASS**. Vrai script JS exécuté avec DOM substitué ; ancre access-title corrigée. Le lien Configuration générale de l’école est désormais nommé selon sa destination réelle, sans inventer un réglage SMTP. JS centrale v16.
- **Ces modifications sont désormais livrées.** `exact-image-r2` : 123 contrats renderer/contrôleur, 37 récupération PG, 14 groupes workspace, 13 native, dix récupération/SMS, concurrence un seul gagnant/époque 1 et deux rollbacks de panne PASS. Snapshot fictif identique, ressources QA retirées. Production : sauvegarde et contrôle séparé PASS ; session existante conservée, `/index.php` -> Tableau de bord ProDéli, mobile sans débordement, ancre après rechargement, réglages/console habillés et six destinations réelles relues. Aucun envoi externe. Acceptation propriétaire : **NON OBTENUE**, distincte de ces preuves.

### Dernières corrections avant qualification immuable

Résultat du wizard : coquille SchoolSafe, suppression de la présentation Copy School / New school uniquement dans ce parcours explicite, traitements natifs et PDF conservés. Revue principal : noms des écoles autorisées via le renderer existant, scope serveur inchangé. Tests navigateur ajoutés avant patch : échec réel sur l’absence de coquille, nettoyage PASS. Après patch : `native-after-r2/native-result.json`, **13 groupes PASS, nettoyage true**, avec assertions de coquille et de nom exact de l’école avant confirmation.

Qualification offline de livraison : cinq tests Python (dont douze sous-cas de preuve invalide), 24 gardes de la procédure de préservation réutilisée et sept tests JavaScript PASS. Ces contrôles n’exécutent ni déploiement ni accès de production. Recette versionnée : `ops/releases/prodeli-workspace-20261022/README.md`.

## Gates avant annonce finale

1. Contrats RED puis GREEN ; vraie connexion/HTTP/PostgreSQL sur données fictives et UI relue bureau/mobile.
2. Étendre et versionner la recette de création/premier accès existante ; conserver les refus, rollback et courses déjà qualifiés.
3. Compléter l'inventaire des boutons/actions à partir des écrans, sans marquer une action exécutée par simple GET du formulaire.
4. Image immuable issue des blobs Git publiée, requalification du payload exact et non-régression du lot récupération/SMS.
5. Livraison distinctement contrôlée avec sauvegardes, sessions/cache/configuration/services préservés ; pas de rollback vulnérable automatique.
6. Avant les mutations de production au navigateur, présenter la liste exacte des créations/actions de test. Le propriétaire saisit lui-même tout secret réel. Un compte de test n'est pas une raison de toucher aux comptes/écoles existants.
7. Rapport des PASS/FAIL/BLOQUÉ/NON EXÉCUTÉ et recette connectée du propriétaire. Aucun pourcentage ou nombre de tests n'est une preuve de conformité produit.
