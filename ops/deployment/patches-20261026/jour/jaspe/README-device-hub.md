# DEVICE HUB — système empreinte digitale / présence personnel

Analysé et mis en place le 2026-10-27. Le système existait à 90 % dans le code
(Hikvision ISAPI) — il manquait 2 tables, le cron et la configuration.

## Architecture
Appareil Hikvision (IP locale privée) → worker.php collecteur supervisé
(capture présence 100/page, file durable anti-doublons sha256, identifiants
chiffrés sodium) → schoolsafe_device_staff (n° employé → staff_id, profils
admin/teacher) → contrôles (checkpoint, période scolaire, horaires
EntryExitIsUnauthorized) → entry_exit_staff_records → tableau de bord
(OverviewFeed, MatrixDetails, PayrollAttendance = présence → paie).

## Ce qui a été créé/installé
- 045-device-hub-queue-collectors.sql : 2 tables manquantes (queue + collectors)
- Clé privée sodium : /var/lib/schoolsafe-device-hub/key (install-key.php)
- Cron /etc/cron.d/schoolsafe-device-hub : keep-alive du superviseur
  (health.php vérifie le heartbeat → relance worker.php --supervise)
- Garde ISOLATED_DATABASE adaptatif : le nom d'hôte DB codé en dur
  (codex-addons-db) remplacé par SCHOOLSAFE_DB_HOST du déploiement
  (worker.php) — backup .orig-1027

## Pour enregistrer un appareil (quand il est branché sur le réseau)
1. Écran SchoolSafe → Gestion des appareils (DevicesManage, profil admin)
   : label, marque, modèle, série, adapter hikvision_isapi,
   IP locale (10.x/192.168.x), port 80/443, checkpoint_id
2. Configurer le collecteur : intervalle (30-3600 s), date de début,
   identifiants de l'appareil (scellés sodium)
3. Mapper les n° d'employé de l'appareil → membres du personnel
   (schoolsafe_device_staff)
4. Test : une empreinte → entry_exit_staff_records → tableau de bord + paie

## Autre marque d'appareil
2e adaptateur prévu : normalized_json (poussée d'événements normalisés :
id, employee_id, time ISO+tz, direction in/out).
