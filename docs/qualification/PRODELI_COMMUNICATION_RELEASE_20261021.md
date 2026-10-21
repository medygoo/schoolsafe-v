# ProDéli — finition des réglages de communication

## Mandat et périmètre

GO explicite du propriétaire : terminer le lot Communication après vérification de l'organisation des messages/notifications existante. Ne pas reconstruire cette organisation ni ses fournisseurs ; Keycloak reste en pause. Ce lot est distinct de l'achèvement global des comptes, du premier accès et de la récupération.

Base de production : source `38856445143050895b6502c51be18285e9309042`, image `sha256:9177067d048e865785b57af67571140dcb5cfa6ca688ba9ca8ef9ccc45c15d12`. Branche de travail `work/prodeli-completion-20261021`, départ documentaire `e57fa53347a5ef89ab1e2c1715b7aef13ab3e1bc`.

Trois fichiers applicatifs seulement :
- `plateforme-prodeli-sms.php` ;
- `plateforme-prodeli-whatsapp.php` ;
- nouvelle feuille `assets/css/prodeli-communication.css`.

## Organisation existante vérifiée et conservée

Le Studio utilise la messagerie native Rosario (`messages`/`messagexuser`), sans second moteur. Les fonctions métier définissent événements et destinataires autorisés selon le profil, l'école, l'année et les rattachements. Neuf types d'événements sont enregistrés dans `SchoolSafeNotifyEventTypes()`. Le dispatcher distribue les notifications et ajoute le miroir WhatsApp aux événements admissibles sans remplacer les livraisons email/push. SMS conserve son rôle existant de récupération/secours et son routage quatre réseaux.

Les fichiers Studio, Notify, SMS, WhatsApp, Brevo, WebPush, dispatcher et reprise sont identiques à la base. `scripts/prodeli-communication-scope.py` contrôle huit composants et les deux préfixes de page comprenant les handlers POST, droits, CSRF, confirmations, SQL et audit : **aucun de ces traitements n'est modifié**. Les règles métier des autres fichiers restent également hors lot, vérifiées par comparaison complète de l'image lors de sa préparation.

## Finition réalisée

- Logo SchoolSafe original, identité et styles du tableau de bord réutilisés ; aucun nouveau menu ni dépendance JavaScript.
- Retour direct au tableau de bord, bloc Communication déjà présent conservé.
- Lien « Modifier » pour chaque ligne SMS/session WhatsApp. Le formulaire reprend ses valeurs enregistrées : numéro, appareil/SIM/quota/seuil ou nom de session. L'identifiant reste fixé dans le formulaire de modification ; changer de ligne se fait par son lien, évitant de sauvegarder les valeurs d'une autre ligne.
- Tables défilables au clavier et sur mobile ; styles clair/sombre système, focus, impression et lien d'évitement.
- Notices : enregistrer un numéro ne connecte pas un compte WhatsApp et ne remplace pas une SIM. Aucun changement de destinataires ou de règles d'envoi ; aucune activation automatique.

## Preuves locales

- 28 contrats exécutant les renderers PHP réels avec bootstrap, autorisation et base substitués : **10 PASS/18 FAIL avant → 28 PASS/0 FAIL après**. Lecture sans écriture, préremplissage, gardes acteur et CSRF, identité/navigation/styles.
- Huit groupes navigateur **HTTP/PHP/PostgreSQL fictif réels** PASS : SMS/WhatsApp à 1280 et 390, clair/sombre, liens depuis le dashboard, sélection et retour ; prévisualisation sans écriture, confirmation, sauvegarde, rechargement et audit ; rejet CSRF ; GET/POST refusés pour un administrateur limité à une école ; aucune nouvelle entrée SMS/WhatsApp dans l'outbox.
- Données fictives modifiées pour la recette restaurées ; compte temporaire et écritures d'audit/quota de la recette nettoyés. Schémas 024/025/036/037 existants installés dans la base locale fictive uniquement. **Aucune migration en production.**
- Cinq groupes de régression du tableau de bord PASS ; deux lints PHP, compilation des recettes Python et `git diff --check` PASS. Captures mobile clair/sombre relues.
- Deux corrections du harnais uniquement : conversion de chemins Git Bash neutralisée ; sélecteurs corrigés car les identifiants `t-*` sont sur les titres, pas sur les sections.

Preuves privées hors Git : `C:/Users/PC/SchoolSafeProductionUpdate-20261021-evidence/communication-completion/`. QA dédiée 8096 et aperçu 8094 conservés. Travaux WhatsApp indépendants et autres worktrees intacts.

## Livraison

**DÉPLOYÉ et contrôlé indépendamment.** Source `90f6238cfee03f81af19a0c3ff4f902f9c134c3e`, image `sha256:3daf00a1c5990f6e7bac1564d6a47bdc322746c05f1e8db06fa7144f74e883fb`, tag `schoolsafe-rosario:prodeli-communication-90f6238cfee0`. `/opt/app-src` propre sur `deployed/prodeli-communication-20261021`. Les commits documentaires suivants ne sont pas la source de l'image.

Recette ciblée `ops/releases/prodeli-communication-20261021/` : image dérivée immuable, allowlist trois chemins, réseau de build désactivé, comparaison complète contenu/mode/propriétaire : **2 755 autres fichiers identiques**, une feuille CSS ajoutée, deux PHP modifiés. **56 contrats PHP PASS** (16 comptes + 12 dashboard + 28 communication), deux lints PASS, aucune base attachée aux tests d'image. Aucun pull global de main.

Bascule : **169 fichiers de session conservés**, contenu/mode/propriétaire contrôlés ; environnement, mounts, ports et configuration de services préservés. Procédure mesurée **14,97 s**, pas une mesure de l'interruption exacte. Autres services mêmes identifiants et démarrages. Aucun compte réel modifié, aucun message émis et aucune migration.

Vérification indépendante PASS : image/source propre et santé PHP ; login public HTTPS 200 ; accès anonyme aux réglages ne révèle aucun formulaire de configuration ; hashes des trois fichiers servis et CSS public exacts ; sauvegardes des deux sources conformes ; empreintes privées de l'environnement, des lignes SMS, des sessions WhatsApp et des consentements identiques avant/après. Cette vérification n'utilise pas les coordonnées privées dans les rapports.

Répertoire privé VPS : `/opt/schoolsafe-releases/prodeli-communication-20261021`, sources avant livraison dans `source-before/`, état Docker et sessions privés. Pas de sauvegarde/restauration DB dans ce lot sans mutation de schéma/données. Ancien conteneur conservé : `schoolsafe-rosario-pre-prodeli-communication-20261021`, image base `9177067d…`. Retour arrière préparé **non exercé** : `python3 /opt/schoolsafe-releases/prodeli-communication-20261021/deploy.py --rollback` ; préserve les sessions courantes, neutralise les obsolètes, bloque en cas de données nouvelles non préservées.

Preuves `prepare-proof.json`, `deployment-proof.json`, `independent-before.json`, `independent-proof.json`, `browser-result.json`, `scope-proof.json` et captures hors Git dans le répertoire privé local indiqué ci-dessus. Validation visuelle connectée du propriétaire restant à réaliser ; elle ne conditionne pas la réalité de la livraison technique.

## Limites conservées, non masquées

- Pas d'envoi externe, de preuve de réception ni d'association matérielle/WhatsApp faite par ce lot. Les numéros réels doivent être renseignés par un gestionnaire habilité ; les secrets restent côté serveur.
- Test SMS existant : le choix de ligne n'est pas imposé au routeur. Ce défaut antérieur n'est pas corrigé par une livraison de présentation. OTP SMS vide et complétude de récupération restent hors de ce sous-lot.
- Les handlers de configuration sont préservés, pas déclarés entièrement réaudités : concurrence/rejeu et atomicité des mises à jour/audits demeurent des réserves distinctes.
- La reprise des styles depuis le dossier historique du connecteur n'active pas Keycloak et n'appelle pas le fournisseur.
- Création/rattachement d'administrateur, premier accès et récupération du chantier global ProDéli restent ouverts. CI sécurité RED et autres réserves historiques restent distinctes.
