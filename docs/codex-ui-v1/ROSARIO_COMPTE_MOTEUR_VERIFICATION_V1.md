# Verification du moteur et de la creation des comptes

Date : 2026-10-11. Inspection locale en lecture seule. Aucun acces VPS, aucune interrogation PostgreSQL et aucune creation de compte. Appsmith et Studio de cartes inchanges.

## Resultat confirme dans la copie locale

Le serveur livre est Node.js/Fastify/TypeScript : server/package.json et Dockerfile. La recherche des fichiers PHP dans ce depot ne retourne aucun fichier. Les tables iam.users, iam.profiles et app.schools sont definies par database/baseline/v1/05_iam.sql et 04_app_tables.sql.

La creation locale d'un membre suit ce chemin :

SchoolSafeSchoolAPI.createPerson -> POST /native/access/users -> requireAuthSession et requireWriteIntent -> createAdultProvisioner.provisionPerson -> branche locale si aucun administrateur d'identites externe -> api.provision_local_school_member(jsonb) -> iam.users, iam.profiles, auth.identities, auth.credentials et attribution de roles.

Preuves : app/modules/school/school-api.js; server/src/accessnative/routes.ts:128; server/src/accessnative/provision.ts:109,126,153; database/auth/v9/02_local_school_member.sql:11,109-125.

Le mot de passe temporaire est genere cote serveur et hache Argon2. auth.credentials.must_change est initialise a true. Le mot de passe temporaire n'est retourne que lorsque password_activation est vrai. Ces observations prouvent le comportement du code, pas son execution sur le VPS.

La creation de membres est limitee aux codes teacher, school_head, pedagogy, cashier, guard, parent, fee_control, hr et staff. Cette route n'est donc pas le contrat de creation de l'administrateur central ou du premier administrateur d'ecole. Ces parcours doivent etre qualifies separement, sans etendre cette route par supposition.

## Comparaison avec RosarioSIS officiel

Le code officiel consulte dans la branche mobile de francoisjacquet/rosariosis, modules/Users/User.php, cree les utilisateurs dans staff et applique les profils et controles d'edition propres a RosarioSIS. Son installation est PHP avec PostgreSQL ou MySQL. La copie SchoolSafe examinee suit un autre modele de tables et de serveur.

Sources :
- https://github.com/francoisjacquet/rosariosis/blob/mobile/modules/Users/User.php
- https://github.com/francoisjacquet/rosariosis/blob/mobile/INSTALL.md
- https://www.rosariosis.org/documentation/school-administrator-handbook/

Cette comparaison ne determine pas la version installee sur le VPS. La branche publique consultee n'est pas une preuve de la version de production. Le mot Rosario utilise dans certains commentaires SchoolSafe ne constitue pas a lui seul une preuve de dependance au logiciel officiel.

## Conclusions et limites

CONFIRME : chemin local de creation de membres et obligation de changement initial dans le code.

NON CONFIRME : version/instance RosarioSIS installee, execution des migrations, disponibilite effective des routes, creation du premier administrateur, authentification officielle Rosario et raccordement Appsmith.

Aucune passerelle vers les programmes PHP RosarioSIS n'a ete identifiee dans le chemin local examine. Ne pas annoncer "tout depend de Rosario" avant verification de l'installation et de son contrat d'integration. Ne pas remplacer le systeme actuel ni synchroniser deux bases de comptes sans decision du proprietaire.

## Prochaine preuve necessaire

Obtenir l'URL de l'instance Rosario existante ou le chemin exact de son code/deploiement, ainsi qu'un acces de lecture approuve. Inspecter la version, les services deployes et les contrats de session/provisioning sans afficher de secrets. Verifier ensuite les trois parcours distincts : administrateur central, premier administrateur d'ecole et membres de l'ecole. Les ecrans seront raccordes uniquement au contrat effectivement confirme.

Livraison : ce rapport uniquement. Aucun moteur, compte, SQL, fichier partage ou deploiement modifie.
