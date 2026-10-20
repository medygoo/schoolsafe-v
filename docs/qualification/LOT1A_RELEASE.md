# LOT 1-A — livraison locale, GitHub et VPS

## Périmètre et autorisation

Autorisation explicite du propriétaire : « GO LOCAL + GITHUB + VPS », pour le correctif LOT 1-A uniquement. Les travaux cartes/scans et autres corrections non publiées sont exclus. Cette livraison ne clôt pas le LOT 1 global ni l'harmonisation complète du produit.

## Versions

- Correctif sur main officiel et local principal : `7c55f0a700b049ff8c8b03c840669abf1ddf3485`, basé sur `84c8891`.
- Branche officielle `release/lot1a-readonly-20261020` : `d12a6398e473ea2b5238dba338af19d0ebf7df8a`, basé sur le checkout de production `5a946f3`.
- Checkout VPS `/opt/app-src` : même commit `d12a6398`, branche locale `deployed/lot1a-readonly-20261020`, propre après livraison.
- Image PHP : `schoolsafe-rosario:20261020-lot1a-d12a639`.
- Image exécutée : `sha256:98f978b397fd0c24b5c5f2204ee92665578aa7c88b97f4135a23fb39d8b10832`.
- Base exacte conservée : `sha256:5af1640377abffec0599e8ff87c1ab263d381c95cc665a3fe8deb72a156d3756`.

Les deux branches Git diffèrent intentionnellement : aucun déploiement global de main ni déplacement de la branche production automatisée. L'image est une couche ciblée ajoutant le seul fichier PHP corrigé à l'image déjà en production ; elle ne prétend pas constituer un rebuild intégral de tout le dépôt.

## Concordance du correctif

Fichier : `app/modules/codex-ui-v1/extensions-test-runtime/ecole-profils.php`.

SHA-256 normalisé LF : `02b88867b9e55cd19f1aa62a3d66592c52c450bc0e706d12c3cb18b30a137057`.

Identité confirmée entre qualification locale, local principal et fichier exécuté sur le VPS. Les helpers Warehouse, AccountState, SchoolScope et AllowEdit ont été comparés avant déploiement avec les sources de livraison. Les changements applicatifs se limitent aux neuf lignes de garde readonly déjà qualifiées.

## Sauvegarde et vérification

- Dump cohérent de la base PHP `rosario_addons_test`, rôles PostgreSQL et configuration conservés dans un répertoire privé du VPS.
- Dump effectivement restauré dans PostgreSQL 17.11 isolé, sans réseau ni application connectée ; restauration sans propriétaires/ACL pour ce contrôle, 147 tables publiques ordinaires constatées.
- La restauration complète des rôles et privilèges n'est pas certifiée par cet essai ; leur sauvegarde est conservée séparément.
- Copie de restauration détruite après vérification.
- Image de livraison exacte testée sur un deuxième environnement isolé, utilisant uniquement des données fictives : **24 PASS, 0 FAIL, code 0**, dont les formulaires Chromium ordinateur et téléphone.
- Une adaptation de transport SSH a été nécessaire pour éviter les délais du Docker distant. Les assertions métier du harnais n'ont pas été modifiées.
- Une permission trop restrictive du modèle public de configuration de la fixture a été corrigée avant les tests. Cela ne concernait pas la configuration de production.

## Bascule et contrôles en production

Seul le conteneur PHP `schoolsafe-rosario` a été remplacé. Ports, configuration d'environnement et montage de configuration conservés. Les 110 fichiers de session présents au moment de la bascule ont été copiés avec comparaison des contenus, propriétaires et permissions avant démarrage.

Résultats :
- conteneur sain (`healthy`), image et empreinte du fichier attendues ;
- page de connexion publique HTTPS : 200, présence effective du formulaire utilisateur/mot de passe ;
- route publique des profils sans authentification : redirection 302 ;
- aucune migration ni écriture métier de recette sur la base de production ;
- aucun redémarrage des bases, du service Node, d'Evolution ou de WAHA ; proxy inchangé ;
- conteneurs, base et réseau de qualification distants supprimés.

Le script distant a enregistré une séquence de bascule de 12,73 secondes ; ce n'est pas une mesure externe de l'indisponibilité utilisateur. La commande SSH côté PC a ensuite dépassé son délai : son résultat n'a pas été présumé. Une inspection distincte a confirmé le fichier de preuve de succès, puis recontrôlé l'image, le code, HTTPS et l'état sain du service.

Aucune connexion avec un compte réel ni tentative de mutation métier en production. Les résultats fonctionnels readonly proviennent des 24 tests sur l'image identique et les données fictives ; les sondes de production sont des vérifications de livraison et d'accès non authentifié, pas une recette complète.

## Retour arrière et preuves

VPS, répertoire privé : `/opt/schoolsafe-releases/lot1a-readonly-20261020` (0700 ; sauvegarde de base 0600).

- `deployment-proof.json`, `prepare-proof.json`, `delivery-image-tests.json` ;
- `deploy.py`, contexte de construction `image/`, source de livraison `source/` ;
- sauvegardes privées de base, rôles, configuration, métadonnées et sessions ; ne jamais publier ces fichiers ;
- `rollback.sh`, à examiner avant exécution et uniquement sur autorisation si un retour arrière devient nécessaire.

Ancien conteneur conservé arrêté : `schoolsafe-rosario-pre-lot1a-20261020`. Le rollback prévu remet cet ancien conteneur et la branche main VPS antérieure ; il ne restaure pas automatiquement une base plus ancienne. Après de nouvelles évolutions ou une longue période d'utilisation, réévaluer cette procédure plutôt que l'exécuter aveuglément.

Preuves locales complémentaires : `C:/Users/PC/SchoolSafeLot1A-20261020/release/`, notamment `final-vps-verification.json`, `delivery-image-after.json` et `delivery-tests.log`. Aucune sauvegarde de données réelles n'a été rapatriée dans le dépôt ni publiée.

## Verdict

**LOT 1-A : synchronisé pour ce correctif, publié et déployé ; contrôles techniques PASS.**

Les historiques complets restent différents, les modifications préexistantes sont préservées, et le reste du LOT 1 demeure ouvert. Arrêt après compte rendu ; aucun autre lot, outil ou déploiement enclenché implicitement.
