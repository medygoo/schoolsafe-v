# Production — découverte des droits scolaires dans ProDéli

GO production explicite du propriétaire. Périmètre : deux liens visibles dans les propriétaires existants, **aucune attribution réelle**, aucun autre lot.

- Base production : checkout `44e246fdd037318c647ee25e8447bb83e3b88337`, branche `deployed/school-native-access-20261024`, image `sha256:1b3843de24caa121243f231b147ddf0a9597b155deca7271ed699994f306fb86`.
- Source candidate : `b6f588da5f4c79875f9abaca0e1ec0e15f02fc2e`.
- Image qualifiée : `sha256:1df5f8578805868544f1a7a73d6a1d437c5be0bf0ecf52cd716b41455e35aea8`.
- Digest source : `e41c8515d0b7c5161685b192333ca33b5c0110c853c38a3e58e0e656ffaa0ea6`.
- Deux sources runtime : `plugins/SchoolSafe_Keycloak/Wizard.php` et `plugins/SchoolSafe_Keycloak/manage.php`.
- Test HTTP final : `6ffde525b4d1bd3ea57ead0a8049a5256f77a8f0`.

## Portes obligatoires

178 contrats+37recoveryPG,55HTTP authentifiés sur fixture/image exacte ;48contrôles navigateur locaux synthétiques. Les deux catégories ne sont pas confondues. Deux fichiers changés,2800inchangés. Snapshot fixture restitué, ressources temporaires supprimées, provenance vérifiée. Le manifeste de la livraison native précédente contraint aussi71sources conservées, soit73sources cumulées ; les trois fichiers du dashboard sont vérifiés séparément.

## Préparation et bascule

1. Publier la recette uniquement avec les fichiers explicites ; jamais les brouillons voisins. Répertoire privé VPS `/opt/schoolsafe-releases/prodeli-access-discovery-20261024`, mode0700 ; preuves/backups0600.
2. Bundler `deploy.py`, `native-access.py` (recette school-native-access), `recovery.py`, `preservation.py` et les preuves exactes. Tous ces scripts doivent être identiques aux blobs Git du commit publié.
3. `python3 deploy.py prepare` : vérifie Git/branche/image live, qualification,73sources candidate, montage config inchangé, schéma/privilèges natifs en lecture seule, couche writable admissible et services. Crée une sauvegarde récente validée par `pg_restore --list`, jamais restaurée.
4. `python3 deploy.py deploy` : réemploie le moteur éprouvé de préservation/bascule sans modifier ses octets, exige backup<900s, conserve environnement complet/config/HMAC/sessions/cache/cinq voisins. Aucun SQL métier, migration, GRANT, rotation, worker ou envoi.
5. Nouveau checkout `deployed/prodeli-access-discovery-20261024`. Ancien conteneur `schoolsafe-rosario-pre-prodeli-access-discovery-20261024` arrêté et conservé. Les labels OCI de l'image portent le source/digest exacts ; le label scope du moteur de conteneur reste le libellé historique recovery, sans changer le périmètre réel.
6. Vérification indépendante :73sources+3dashboard, checkout propre/branche/image, sessions non expirées/cache, configuration et HostConfig/environnement identiques, cinq voisins, login/récupération HTTPS et refus anonymes, workers toujours détenus. Pas de login réel ou octroi automatique pour prouver un bouton.

Une erreur après arrêt entraîne un diagnostic et une décision de récupération explicite ; pas de restauration de dump ni retour arrière automatique qui écraserait des écritures. Le moteur conserve les conteneurs et une preuve de récupération requise.

## Utilisation après livraison

Dans ProDéli, « Vos établissements » → bouton **Gérer les droits — Scan et accès** à côté de l'école. Le même bouton figure sur la ligne de l'administrateur mono-école, avec présélection du compte. Les droits et confirmations restent ceux du gestionnaire natif. Aucun QR n'est généré ici : le QR reste sur la carte ; le bouton Scan scolaire ouvre le lecteur.

LE SAGE reste sans droits Scan tant que leur sélection n'a pas été confirmée dans l'application. Le GO production des liens n'est pas une autorisation d'accorder tous les droits à un compte réel.
