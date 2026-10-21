# ProDéli — présentation seule, 21 octobre 2026

Mandat : le propriétaire autorise une correction directe en production, après sauvegarde et tests. Ce lot extrait les corrections de présentation du worktree d'harmonisation sans importer ses nouvelles mutations natives.

## Périmètre strict

Base : `d12a6398e473ea2b5238dba338af19d0ebf7df8a`, image PHP déployée `98f978b397fd0c24b5c5f2204ee92665578aa7c88b97f4135a23fb39d8b10832`.

Quatre fichiers seulement sous `plugins/SchoolSafe_Keycloak/` : `Management.php`, `manage.php`, `Wizard.php`, `central-ui.js`.

- Administrateurs scolaires et équipe centrale séparés ; accès à la vue centrale réservé à l'administrateur global.
- Écoles nommées ; compte natif actif distinct de la configuration d'une identité gérée.
- État du compte distinct de sa première connexion ; profils administratifs désactivés visibles.
- Suivi d'accueil limité aux comptes scolaires actifs en attente de première connexion.
- Conservation de la vue centrale au rechargement et dans les retours de confirmation.
- Marque SchoolSafe et logo original conservés ; mention ProDéli dans l'espace global.

Les traitements de création, de mutation des identités, l'assistant scolaire et ses écritures restent identiques à la base. Les anciennes actions gérées restent disponibles selon leurs contrôles existants. Aucune nouvelle commande native de suspension/réactivation, aucune correction d'époque, aucun changement de mot de passe ou de schéma. Les correctifs de logo/persistance et les autres travaux locaux ne sont pas livrés ici. LOT1A, Node et WhatsApp restent intacts.

## Preuves avant livraison

- Contrats de présentation : 0/16 avant, 16/16 après ; DB/droits substitués, aucun fournisseur appelé.
- Six groupes navigateur/PHP/PostgreSQL fictif : deux largeurs (1280/390), trois états natifs, noms des écoles, population centrale séparée et conservée au rechargement, suivi d'accueil, refus HTTP 403 de la vue centrale pour un acteur scolaire. Trois comptes temporaires nettoyés.
- Web local dédié `schoolsafe-prodeli-presentation-qa`, sur le réseau fictif existant ; aperçu 8094 non remplacé. Base de qualification cache + source du candidat : ce n'est pas encore une preuve de l'image de production.
- `scripts/prodeli-presentation-scope.py` vérifie les quatre chemins et l'identité des traitements métier préexistants, dont l'assistant scolaire et les handlers POST.
- PHP lint et syntaxe JavaScript contrôlés.
- Harnais : délai de démarrage de copie Windows dépassé puis service constaté sain ; délai augmenté. Mot de passe fictif initial trop long pour `maxlength=42`, corrigé à 32 caractères sans changer l'application. Échec conservé hors Git.

Preuves locales : `C:/Users/PC/SchoolSafeProductionUpdate-20261021-evidence`. Aucun identifiant ou contenu de session dans Git.

## Exécution opérateur

Le répertoire privé de livraison est `/opt/schoolsafe-releases/prodeli-presentation-20261021`. Le paquet ne contient que quatre blobs Git dans `payload/`, la recette, les scripts, les contrats et les preuves non sensibles. `release.json` identifie le commit, les empreintes attendues et l'image construite. Pas de transfert du runtime complet ni de reprise de la préproduction interrompue.

1. Publier la branche de release, vérifier son SHA et récupérer uniquement cette branche sur le VPS. Ne pas tirer globalement main.
2. `python3 prepare.py` : contrôle des sources réellement servies, build dérivé de l'image actuelle, comparaison intégrale des fichiers (contenu/mode/propriétaire), trois lints PHP et 16 contrats sur l'image exacte, réseau `none`, aucune base utilisée. Capture privée de la configuration actuelle.
3. `python3 deploy.py` : exige toutes les preuves, refuse des données nouvelles non préservées dans la couche du conteneur, recontrôle après arrêt, conserve le conteneur précédent, reprend exactement environnement/montages/ports et transfère les sessions avec vérification contenu/propriétaire/mode. Bascule PHP seulement ; contrôles login local/HTTPS, refus anonymes et empreintes servies. Aucun test mutant en production.
4. Retour arrière : `python3 deploy.py --rollback`. Contrôle de l'image et des données avant tout arrêt, sessions courantes transférées sans réactiver des fichiers de sessions supprimés depuis la bascule ; aucun restore DB. Si des données nouvelles non préservées apparaissent, arrêt du rollback avant perte, intervention ciblée requise.

La bascule implique une courte interruption PHP. Les autres services, les anciennes images de rollback et les fichiers partiels de préproduction sont préservés. Le résultat de production ne doit être annoncé qu'après `deployment-proof.json` et contrôle indépendant. La recette connectée du propriétaire reste distincte des probes anonymes. CI sécurité et qualifications métier non concernées restent ouvertes.
