# Patches 2026-10-25/26 — corrections appliquées en production (app.shoolsafe.tech)

Copies de sécurité des fichiers corrigés cette nuit, appliqués directement dans le
conteneur `schoolsafe-rosario` (source PHP non encore réconciliée dans Git — alerte
gouvernance). Backups des originaux : VPS `/root/backups/*orig*`.

| Fichier | Patch | Re-test |
|---|---|---|
| `044-dashboard-palmares.sql` | Migration appliquée à la base live `rosario_addons_test` : 5 tables (palmares_snapshots/awards/events, dashboard_runs, birthday_alerts) + flag SCHOOLSAFE_PALMARES=Y école 3 | Portail : erreur SQL disparue, palmarès affiché |
| `Bottom.php:91` | B0.2 XSS : `AttrEscape( User( 'NAME' ) )` | Nom affiché à l'identique, HTML propre |
| `modules/misc/Portal.php:562` | B0.7 garde `SCHOOLS IS NULL` (événements, sémantique identique à notes:236) | Portail vivant ; sémantique NULL validée en SQL ; le compte prodeli (SCHOOLS=NULL) était le profil affecté |
| `ProgramFunctions/SchoolSafeDashboardNative.fnc.php:169` | B0.3a try/catch BIRTHDAY_SCOPE_TOO_LARGE (widget parent) | Portail vivant, anniversaires OK |
| `ProgramFunctions/SchoolSafeDashboardNative.fnc.php:166-167` | B0.8 filtre inscription active (anniversaires enfants), aligné BirthdayRows:16-17 | Portail vivant |
| `ProgramFunctions/SchoolSafeDashboardDaily.php:27-34` | B0.3b try/catch — le job quotidien ne meurt plus sur >200 anniversaires | Job : `{"status":"ok","schools":3}` |
| `schoolsafe-card-bridge/RosarioCardRead.fnc.php` | Lecteur e-mail carte : lit `config.SCHOOLSAFE_SCHOOL_EMAIL` (onboarding) d'abord, repli legacy school-fields.json | Studio : e-mail chargé (« school.email » absent des manquants) |

## Ops annexes appliquées (non fichiers)
- Cron `/etc/cron.d/schoolsafe-dashboard-daily` (04:05) — job quotidien dashboard
- Cron `/etc/cron.d/schoolsafe-engines` — scheduler présence */10 + dispatch notifications */5
- Permissions `profile_exceptions` : 19 clés cartes/scan/familles Y/Y pour profils 1 (prodeli) et 10 (admin école)
- Données : checkpoints scan (entrée/sortie, école 3), seuils ponctualité (3/120/30), élève témoin → école 3
- `config.inc.php` : français par défaut activé par redémarrage du conteneur (autorisé par le propriétaire) — vérifié `lang="fr"` pour visiteur sans cookie
