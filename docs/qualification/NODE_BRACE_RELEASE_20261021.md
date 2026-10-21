# Livraison ciblée Node — brace-expansion — 21 octobre 2026

## Statut : livré et vérifié

GO du propriétaire pour publier et déployer le correctif Node qualifié, sans livrer toute la branche main ni modifier PHP, Gitleaks ou les données métier.

- Correctif source publié : `da0693197d574a7d57c455654cc9c59d9cc66ab1`.
- Conteneur actif : `schoolsafe-app`, démarré le **21 octobre 2026 à 04:55:54 UTC**.
- Image : `schoolsafe-app:20261021-brace511-da06931`.
- ID exact : `sha256:3cf6663bfdbeb186ccf4f9b4224d4196717e7e4cc2507cdea7f899355ac29def`.
- Dernière vérification indépendante : **04:58:04 UTC**, conteneur sain ; `/health` et `/ready` répondent 200.
- Version effectivement chargée et verrouillage embarqué : **brace-expansion 5.0.11**.
- Fastify **5.12.4** et Node **v22.23.3** conservés.

La version vulnérable 5.0.9 n'est plus celle résolue par l'application Node active. Cela ne constitue pas un certificat de sécurité de toute l'image ou de toute l'application.

## Périmètre exact de l'image

Base conservée : `sha256:33a181d8c820b84e23d53ab79058a6911d5311c7cbfa7a8601bb7667618a9a1f`.

La recette ajoute uniquement le paquet brace-expansion corrigé et les trois champs de son entrée dans le verrouillage embarqué (`version`, `resolved`, `integrity`). Le tarball officiel npm a été vérifié par son intégrité SHA-512. Aucun npm install, script de paquet, changement de dépendance transitive ni recompilation globale n'a été effectué dans l'image de production.

Les couches de base correspondent exactement. **14 859 fichiers applicatifs hors paquet et verrouillage ciblés ont été comparés et sont identiques**, liens symboliques compris dans la comparaison. Les fichiers applicatifs compilés, les autres dépendances et les ressources statiques n'ont pas été remplacés par ceux de main.

Le verrouillage serveur autonome publié a été réconcilié pour les futurs builds depuis les sources ; il n'a pas été injecté dans cette image de runtime.

## Qualification

### Sources locales

- Installations propres workspace et serveur autonome : PASS.
- Vérification TypeScript et compilation : PASS.
- Suite serveur après correction : **688/688 PASS**, avec répétition depuis une installation workspace propre.
- Contrat ciblé des dépendances : **5/5 PASS** ; trois débordements de pile avaient été reproduits sur la version installée initiale.

### Image exacte de livraison

Recette dans des conteneurs jetables, sans réseau, avec plafonds mémoire/CPU :
- les trois défauts amont sont reproduits avec la base de production et Node v22.23.3 ;
- les mêmes entrées ne provoquent plus de débordement avec l'image livrée ;
- les expansions ordinaires restent correctes ;
- le vrai plugin Fastify static sert une fixture fictive : GET 200 avec contenu attendu, HEAD 200 et fichier absent 404.

Ces tests n'ont ni chargé de données élèves ni appelé une base de production ou un fournisseur de messages.

## Sauvegardes et restauration

Configuration du conteneur, base Node, rôles et répertoire de téléversement conservés dans un répertoire privé du VPS :

`/opt/schoolsafe-releases/node-brace-20261021`

Répertoire 0700 ; fichiers de configuration, dump, rôles et archive de téléversement vérifiés en 0600. Aucune de ces données n'a été ajoutée à Git.

Le dump a été restauré dans un PostgreSQL 17.11 isolé, sans réseau et sans port hôte : **113 tables**. Les rôles ont été rejoués et pg_restore exécuté sans exclusion des propriétaires/ACL. La copie de restauration et son stockage tmpfs ont ensuite été supprimés. Cela vérifie la restauration technique, pas toutes les décisions de permissions métier.

Une première restauration a été arrêtée sur une attribution `GRANTED BY postgres`, incompatible avec l'opérateur fictif choisi initialement. La procédure a été corrigée en restaurant avec le rôle bootstrap postgres ; seule sa création redondante est omise dans la copie de test du script de rôles. Le dump original reste intact. Aucun rôle de production n'a été changé.

## Bascule et préservation

- Un seul conteneur Node de production actif à la fois.
- Ancien conteneur arrêté et conservé : `schoolsafe-app-pre-brace-20261021`.
- Configuration applicative, variables d'environnement, commande de démarrage, réseau host et paramètres hôte conservés.
- Docker représente le paramètre OomKillDisable initialement absent par false lors du clonage. Cette différence unique a été diagnostiquée avant tout arrêt ; dans les deux cas, le mécanisme OOM reste activé. Aucune autre différence de configuration hôte n'est admise.
- Répertoire de téléversement vide au moment de la bascule ; contenu, propriétaires et permissions comparés après copie. La procédure aurait abandonné la bascule si des fichiers étaient apparus avant l'arrêt.
- Aucun autre conteneur n'a changé d'identité, d'état de fonctionnement ou de date de démarrage pendant la livraison et la vérification indépendante.
- PHP reste sain et son checkout reste `d12a6398e473ea2b5238dba338af19d0ebf7df8a` : le LOT 1-A n'a pas été redéployé.
- Aucune migration, restauration de base réelle, révocation de session ou mutation métier de recette.
- Aucun envoi de test WhatsApp/SMS/email.

La première tentative de bascule a été arrêtée avant l'arrêt de Node sur la différence de représentation OOM. Ses preuves sont conservées. La seconde a réussi ; le retour arrière n'a pas été exécuté.

Le script de bascule a duré 7,55 secondes. Ce chiffre **n'est pas une mesure externe de l'indisponibilité** ; Node a été redémarré, sans prétention de zéro interruption. Les réponses HTTP 200 ne constituent pas à elles seules une recette métier.

## Retour arrière préparé

Le conteneur et l'image précédents restent disponibles. Le script privé :

`python3 /opt/schoolsafe-releases/node-brace-20261021/deploy-vps.py --rollback`

vérifie l'image active et l'identité du conteneur retenu, arrête le remplaçant, sauvegarde et transfère les téléversements courants, puis remet l'ancien conteneur. Il exige que le répertoire de l'ancien soit toujours vide pour ne pas réintroduire des fichiers supprimés depuis la bascule. Il ne restaure pas la base : le schéma n'a pas changé.

**Procédure préparée, non exécutée en production.** Réévaluer ses conditions après toute nouvelle livraison ; ne pas la rejouer aveuglément. Les preuves de livraison sont écrites atomiquement et une exécution déjà terminée ne peut pas être relancée silencieusement.

## CI et réserves maintenues

Exécution réelle sur le commit source :
https://github.com/medygoo/schoolsafe-v/actions/runs/39539852156

- Playwright / axe-core / Lighthouse : PASS sur les contrats d'outillage.
- ZAP passif sur cible fictive : PASS.
- Auto-tests des scanners : PASS.
- Scan réel du dépôt : **FAIL** ; workflow global **RED**.

La revue Gitleaks et ses exceptions historiques n'ont pas été publiées dans cette livraison limitée à Node. Les autres réserves de sécurité ne sont pas masquées.

Le scan local après livraison, incluant les mêmes dépendances PHP ignorées que l'audit précédent, conserve **4 signalements de dépendances PHP et 5 de configuration Docker**. Le cinquième emplacement est la nouvelle recette d'image ciblée, qui hérite de l'utilisateur root déjà utilisé par le runtime Node. Ce constat correspond à une configuration préexistante désormais visible dans une recette supplémentaire, pas à une nouvelle élévation de privilèges. Son durcissement reste à qualifier séparément ; aucune exclusion Trivy n'a été ajoutée.

Les bibliothèques PHP signalées restent dans les fichiers locaux conservés, absents aux chemins inspectés dans le conteneur PHP actuel. Elles ne doivent pas être réintroduites sans qualification. Un checkout Git propre ne couvre pas automatiquement ces fichiers ignorés ni l'ensemble du runtime.

## Traçabilité et limites

Preuves locales : `C:/Users/PC/SchoolSafeSecurityAudit-20261021/release`.
Preuves VPS privées : `prepare-proof.json`, `deployment-proof.json`, `final-verification.json`, sauvegardes et historique des tentatives dans le répertoire de livraison.

Le worktree source de la recette est détaché sur le commit publié, sous le répertoire de livraison. `/opt/app-src` reste sur la livraison PHP ; aucune branche production automatisée n'a été déplacée. Aucun git pull global ni reconstruction de toute l'application n'a été effectué.

Les travaux locaux indépendants WhatsApp et captures QA sont préservés. Le projet principal contient les correctifs source ; ses dépendances de développement n'ont pas fait l'objet d'une réinstallation globale. La qualification a utilisé un worktree et des installations dédiés.

Cette livraison clôt le correctif ciblé brace-expansion, **pas** l'harmonisation complète main/production, le LOT 1 global, le build reproductible ou la recette métier générale.
