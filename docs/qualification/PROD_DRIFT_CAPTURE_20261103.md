# Capture des modifications faites à chaud en production — 3 novembre 2026

Base : `5d15e133b743494591b591b71270f6b15c521980` (source déployée le 25/10, branche VPS `deployed/school-logo-search-below-20261025`).
Branche : `ops/prod-drift-capture-20261103`, commit de capture `97cae07`. Aucun changement fonctionnel : ce commit reproduit l'état réel du conteneur.

## Constat

`docker diff schoolsafe-rosario` (conteneur démarré le 25/10 23:00 UTC) : fichiers ajoutés ou modifiés dans la couche du conteneur, hors image et hors Git, entre le 25/10 et le 30/10 (sauvegardes `.orig-1027` à `.orig-1030b` laissées sur place).

Correspondance : `/var/www/html/` ↔ `app/modules/codex-ui-v1/extensions-test-runtime/`.

## Contenu du commit

89 fichiers de code capturés par `docker cp` depuis le conteneur en cours d'exécution ; 78 diffèrent de `5d15e13`, 11 y étaient déjà identiques. Principaux :
- fichiers natifs RosarioSIS modifiés : `Warehouse.php`, `Bottom.php`, `modules/misc/Portal.php`, `modules/Students/Student.php`, `modules/Students/AddUsers.php`, `modules/Students/includes/General_Info.inc.php`, `modules/Users/User.php`, `modules/Users/Profiles.php`, `modules/Users/AddStudents.php`, `modules/Users/Preferences.php`, `modules/School_Setup/{Calendar,MarkingPeriods,CopySchool}.php` ;
- module `Entry_Exit` (présences journalières du personnel) et `modules/Student_ID_Card/StudentIDCard.php` ajoutés ;
- navigation centrale, menu et catalogue SchoolSafe, tableaux de bord, JASPE, studio de cartes, hub d'équipements ;
- deux scripts SQL de remédiation (`045-device-hub-queue-collectors.sql`, `046-person-ids-6digits.sql`) ;
- bibliothèques tierces servies par l'application : MapLibre/Turf, PDF.js, Tesseract (≈ 20 Mo).

Preuves : 89/89 empreintes SHA-256 identiques entre le conteneur et ce worktree ; `php -l` exécuté dans le conteneur sur les 48 fichiers PHP : 48 OK.

## Exclus volontairement

- `assets/StudentPhotos/2026/*.jpg` (7 fichiers) — **photos d'élèves, données d'enfants** : jamais dans Git. Stockées dans la couche du conteneur, **sans volume** (contrairement à `UserPhotos` et `school-logos`) : une recréation du conteneur les supprime. À déplacer sur un volume persistant avant tout redéploiement.
- `assets/libs/tesseract/lang/{eng,fra}.traineddata` — données OCR binaires volumineuses, à fournir comme ressource d'image.
- `modules/SchoolSafe_Jaspe/modules-value.txt`, `Assistant.php.pre-debug-1027`, tous les `*.orig-*` — artefacts de débogage.

## Points à traiter ensuite

1. Volume persistant pour `assets/StudentPhotos` (bloquant avant tout redéploiement).
2. Pages de maquette servies publiquement (`m-admin-pc.html`, `m-par-tel.html`, `maquette-parent.html`, `outils-demo.html`, `liquid-outils.html`, `apercu-ambiance.html`…) : à retirer de la production après vérification de leur usage.
3. Reconstruire une image Rosario à partir de ce commit et prouver qu'elle est identique au conteneur avant de l'utiliser.
4. Relire une par une les modifications de fichiers natifs Rosario pendant la remise en état des parcours.
5. Aucun push effectué : publication sur `medygoo/schoolsafe-v` à autoriser par le propriétaire.
