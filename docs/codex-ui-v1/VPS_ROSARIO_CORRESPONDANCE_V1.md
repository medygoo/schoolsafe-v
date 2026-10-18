# Correspondance moteur local / VPS

Verification du 2026-10-11, par SSH root sur 179.198.195.15, commandes d'inspection uniquement. Aucun deploiement, redemarrage, modification de fichier ou interrogation des donnees utilisateurs. Les commandes distantes produisent les traces techniques habituelles de connexion.

## Preuves VPS

- hostname : prodeli-hub-01; noyau Linux 6.8.0-139-generic.
- rosario-schoolsafe-app : source montee de /opt/rosario-schoolsafe/app vers /var/www/html, publication sur 127.0.0.1:18080, HTTP 200.
- Warehouse.php:22 declare ROSARIO_VERSION 12.9.4; CHANGES.md confirme cette version dans son premier chapitre.
- modules/Users/User.php utilise staff, profile_exceptions et encrypt_password.
- modules/Users/Profiles.php, modules/School_Setup/Configuration.php et PasswordReset.php sont presents.
- Configuration.php:471-472 expose FORCE_PASSWORD_CHANGE_ON_FIRST_LOGIN. Presence du mecanisme confirmee; sa valeur active dans la base n'a pas ete consultee.
- rosario-schoolsafe-db : image postgres:16, conteneur healthy. Etat technique uniquement, schema et donnees non inspectes.
- prodeli-control-appsmith : image appsmith/appsmith-ce avec digest b9b44edb71de4638d7b662e6dc0fc30bf5ef0e1f95353afb03e68ee518c8b460; healthy; publication 127.0.0.1:8080; HTTP 200. Contenu des applications et connexions de donnees non inspecte.
- Control, activation et Supabase sont egalement presents dans l'inventaire des conteneurs. Aucun conteneur correspondant clairement au serveur Node SchoolSafe du depot local n'a ete identifie dans cet inventaire.

## Comparaison locale

Le depot C:/Users/PC/Pictures/schoolsafe-integration-v1 livre un serveur Node/Fastify, des schemas iam.*, auth.*, app.* et une creation native via api.provision_local_school_member. La source Rosario du VPS est PHP, avec staff et ses programmes Users.

Il s'agit de contrats differents. Le nom Rosario dans les documents locaux n'etablit pas leur correspondance. Aucun raccordement entre ces deux contrats n'a ete prouve dans le parcours local examine. Cela ne prouve pas l'absence de toute passerelle externe ou application Appsmith.

## Consequence pour la publication

Ne pas publier le frontend en supposant qu'il peut appeler ses routes /native/* contre l'installation PHP telle quelle. Ne pas migrer la base Rosario vers les schemas locaux, ne pas dupliquer les comptes et ne pas modifier le coeur pour masquer cet ecart.

Etape suivante : relever en lecture seule les contrats et connexions non secretes de l'application Appsmith et l'emplacement eventuel de la passerelle existante. Si elle existe, verifier session, permissions, ecole et creation de compte. Si elle manque, soumettre une proposition d'adaptateur separe au proprietaire avant implementation. Les tests restent locaux avec une base jetable; VPS conserve intact.

Non verifies : connexion utilisateur, livraison de mot de passe temporaire, activation effective du changement initial, gestion des ecoles via Appsmith, DNS/publication des sous-domaines, fonctionnement metier complet.

Livraison : ce rapport uniquement, dans le perimetre Codex. Aucun fichier du depot Cursor ni du VPS modifie.
