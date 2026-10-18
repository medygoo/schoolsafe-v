# Premiere vue de lecture SchoolSafe / Rosario

Adresse locale : http://127.0.0.1:18082/schoolsafe-view/

## Implementation isolee

- app/modules/codex-ui-v1/rosario-view/index.php
- app/modules/codex-ui-v1/rosario-view/view.css
- docs/codex-ui-v1/local-reference/schoolsafe-view.conf
- Reutilisation sans modification de app/styles/codex-ui-v1/codex-ui-v1.css.

Les fichiers sont copies dans /opt/schoolsafe-view du conteneur de test, hors des sources Rosario montees readonly. Une configuration Alias Apache de test est ajoutee ; le moteur Rosario, ses comptes et le Studio Card ne sont pas modifies. Aucun changement de navigation dans l'application principale.

## Contrat

- GET uniquement, sans parametres ; POST et toute query string refuses avec HTTP 405.
- Warehouse.php initialise la session native. Absence de STAFF_ID et STUDENT_ID : redirection vers /index.php.
- AllowUse sur le portail et chaque lien ; lecteurs administratifs reserves au profil admin et modules actifs.
- DashboardStudentsAdmin et DashboardUsersAdmin reutilises sans recopier leurs requetes ou inventer un calcul.
- Donnees affichees escapees, Cache-Control no-store.
- Eleves et utilisateurs presentent les valeurs natives et leurs ventilations, pas les fixtures du preview.
- Aucun appel aux routes Node /native, aucun nouveau login, aucun raccordement Appsmith.

## Verifications executees

- PHP lint : succes.
- Sans cookie : HTTP 302 vers index.php.
- POST : HTTP 405.
- Navigateur : session actuellement administrateur ; 1 eleve, niveau 06 : 1 ; 4 utilisateurs dont 2 enseignants, 1 administrateur et 1 parent.
- Lecture PostgreSQL de verification des comptes du meme contexte : 2 enseignants, 1 administrateur, 1 parent.
- Liens Eleves, Cours, Profils visibles selon droits natifs.

La session utilisee lors du controle de cette vue est administrateur, pas celle du controle enseignant anterieur. L'affichage de cette vue avec parent/eleve/enseignant reste a tester dans leurs sessions propres. Aucun parcours d'ecriture n'est valide.

## Limites explicites

Cette premiere tranche n'est pas le tableau de bord complet du preview 5187 : ce preview est conserve intact. Elle prouve le raccordement de deux lecteurs natifs sous l'origine Rosario.

Restent : details filles/garcons par classe, presences quotidiennes enseignants, palmares top 10 et autres indicateurs. Ils restent indiques comme non raccordes, sans chiffres fictifs.

Responsive : CSS comporte une grille a une colonne sous 640 px ; tests visuels multi-viewports non encore executes. Ne pas declarer le responsive valide sur cette seule base.

Installation runtime manuelle de test ; start.cjs ne reinstalle pas encore cette vue apres recreation du conteneur. Pour transfert : copier les deux fichiers de vue et le CSS existant dans /opt/schoolsafe-view, puis placer schoolsafe-view.conf dans conf-enabled et verifier/recharger Apache. N'appliquer ce montage qu'en environnement local explicitement valide.

FIRST_NATIVE_READ_SLICE=VERIFIED_ADMIN

FULL_DASHBOARD=INCOMPLETE

PRODUCTION_READY=NO
