# ProDéli — présentation corrigée et livrée le 21 octobre 2026

## Résultat

**DÉPLOYÉ, contrôles techniques PASS ; validation connectée du propriétaire attendue.** Autorisation explicite du propriétaire : travailler directement en production, mettre à jour les derniers changements, puis corriger le mélange entre présentation et nouvelles mutations natives.

Source livrée : `6e741ba8a289866dc443dc61bf4108a3ecb29672`, branche source `release/prodeli-presentation-20261021`, base `d12a6398e473ea2b5238dba338af19d0ebf7df8a`. Premier commit applicatif : `aaf4f3918b8265ce6019aade577890de15ffd2c6` ; le second corrige seulement l'exploitation du build.

Image exacte : `sha256:b45be4ea091235616a2670f300a101cdabba7b754734442fb4b1a4a56b1fcaf4`.
Tag : `schoolsafe-rosario:prodeli-presentation-6e741ba8a289`.
Checkout VPS : `/opt/app-src`, branche `deployed/prodeli-presentation-20261021`, SHA source ci-dessus, propre lors du contrôle indépendant. Pas de pull global de main : les différences hors lot sont volontairement exclues.

## Quatre fichiers applicatifs, pas de mutations nouvelles

Sous `app/modules/codex-ui-v1/extensions-test-runtime/plugins/SchoolSafe_Keycloak/` :

| Fichier | SHA256 servi |
|---|---|
| `Management.php` | `47c5616aa2b893b2c9c3e72c8ac485429d4bb3756d1608031cbe6b34ba172ed1` |
| `manage.php` | `23a17e9c650c9d7e89d20c26b336b0e60ea8ebf7dd5c2a55cc9ae0b1fe6ac0f6` |
| `Wizard.php` | `762dccd0602a085cf85df5b0cbf1ce3908e5521b37e5ba7c2af2fddcb1d0aae6` |
| `central-ui.js` | `ff51f4157d59d9a33625301a68d453a632c7055f37588fd1513e4a602c1209fd` |

La liste distingue administrateurs scolaires et équipe centrale. Les noms d'écoles et les états natifs/gérés sont présentés séparément de la première connexion. La vue centrale persiste au rechargement ; retours de confirmation adaptés. Le suivi d'accueil ne présente plus le compte central natif connecté comme un accès scolaire à préparer. SchoolSafe et son logo original sont conservés, mention ProDéli pour le global.

Les handlers métier et l'assistant scolaire préexistants sont inchangés, prouvés par comparaison de leurs blocs. Les actions gérées anciennes restent régies par leurs contrôles existants. Les nouvelles commandes `native_suspend`/`native_resume`, la correction d'époques, le nouveau traitement de logo, les changements d'authentification/thème et le travail WhatsApp indépendant ne sont **pas** déployés par ce lot.

## Preuves graduées

- Contrats de présentation : 0/16 avant, 16/16 après, DB et permissions substituées.
- Six groupes réels navigateur/PHP/PostgreSQL fictif : listes bureau/mobile 1280/390, trois états natifs, noms d'écoles, populations séparées et rechargement central, suivi d'accueil, refus 403 d'un acteur scolaire sur la vue centrale. Trois comptes QA nettoyés à chaque passage.
- Rejeu des six groupes avec les quatre blobs Git exacts servis dans un web local distinct. L'aperçu 8094 n'a pas été remplacé. Aucun fournisseur d'identité appelé.
- Sur l'image exacte construite au VPS : 16 contrats PASS, trois lints PHP, réseau `none`, aucune base utilisée. **2 753 autres fichiers identiques** à l'image précédente, contenu/mode/propriétaire ; seulement les quatre fichiers autorisés changent.
- Bascule PHP : 15,48 secondes pour la procédure mesurée, pas une mesure précise de l'indisponibilité HTTP. **90 fichiers de session transférés**, contenu/propriétaire/mode vérifiés. Environnement, montages, ports, réseau, politique de redémarrage et limites préexistantes préservés.
- Connexion publique HTTPS 200 ; routes ProDéli/console protégées sans authentification ; quatre empreintes servies identiques. Autres conteneurs : mêmes identifiants et dates de démarrage.
- Contrôle indépendant après livraison : image/source attendues, checkout propre, PHP sain, autres services en marche ; JavaScript public et logo original identiques aux empreintes attendues, correctif `ecole-profils.php` LOT1A inchangé.

Aucune migration, aucun test mutant ou action de gestion sur les comptes réels en production. Pas de recette connectée de production effectuée à la place du propriétaire. Les 129 tests du grand lot local restent des preuves distinctes, pas le bilan de cette livraison.

## Sauvegarde / retour arrière

Sauvegarde préalable des 21 fichiers du grand lot local : `/opt/schoolsafe-releases/prodeli-update-20261021-preflight`, empreintes vérifiées ; aucun de ces 21 fichiers n'est implicitement annoncé livré.

Release privée : `/opt/schoolsafe-releases/prodeli-presentation-20261021`. Configuration complète et archives de sessions conservées privées sur le VPS, jamais dans Git ni rapatriées dans ces rapports.

Ancien conteneur conservé arrêté : `schoolsafe-rosario-pre-prodeli-presentation-20261021`, ancienne image `98f978b397fd0c24b5c5f2204ee92665578aa7c88b97f4135a23fb39d8b10832`.

Commande de retour arrière préparée, **non exécutée** :

```sh
python3 /opt/schoolsafe-releases/prodeli-presentation-20261021/deploy.py --rollback
```

Elle refuse une cible inattendue ou des nouvelles données non préservées dans la couche du conteneur, conserve les sessions courantes sans réactiver les sessions supprimées, restaure la source précédente et n'effectue aucune restauration de base. Retour arrière complet non exercé sur la production ; ne pas le présenter comme testé de bout en bout.

## Incidents maîtrisés

- Démarrage de la fixture locale plus long que l'attente de 60 s : copie Windows encore en cours, puis état sain constaté ; attente étendue, pas de recréation globale.
- Mot de passe synthétique de 64 caractères incompatible avec `maxlength=42` : harnais corrigé à 32, application inchangée.
- Premier build : `FROM sha256:…` interprété comme un dépôt distant par BuildKit. Corrigé par le tag local vérifié contre l'ID exact avant build. Contexte désormais limité par `.dockerignore` à Dockerfile et quatre sources, excluant les captures privées d'exploitation. Échec avant toute bascule, aucune substitution de base ni téléchargement de remplacement.

## État restant

Validation humaine : ouvrir `https://app.shoolsafe.tech/schoolsafe-administrators.php`, examiner les deux populations et les états avec son compte habituel. Les nouveautés natives de suspension/réactivation, concurrence/audit, rattachement principal, premier accès/récupération, logo/persistance et suppression restent des lots séparés à qualifier. CI sécurité RED et réserves LOT2 inchangées.

La préproduction interrompue sous `/opt/schoolsafe-preprod` n'a été ni installée ni nettoyée. Les autres worktrees, leurs modifications non committées, WhatsApp et les captures QA sont conservés.

Preuves locales hors Git : `C:/Users/PC/SchoolSafeProductionUpdate-20261021-evidence/` (`deployment-proof.json`, `independent-verification.json`, `prepare-vps-after.log`, `presentation-qa-exact.log`, `candidate-source-proof.json`, contrats avant/après). Recette et scripts suivis : `ops/releases/prodeli-presentation-20261021/`.
