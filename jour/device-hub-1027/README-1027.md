# État du 27/10 (soir) — Device Hub + ID 6 chiffres + Atelier technique

## Device Hub / empreintes
- `schoolsafe-device-push.php` : récepteur de poussée — formats normalisé + Hikvision EventNotificationAlert (XML/JSON), jeton, anti-doublon à identifiant stable, doigts refusés ignorés proprement.
- `schoolsafe-device-enrollstatus.php` : remontée « Empreintes N/5 » (UPSERT schoolsafe_device_enroll).
- `DevicesRead.php` : écran enrichi — bouton « Enrôler les empreintes » (ouvre la machine), tableau Enrôlement N/5, génération automatique d'ID 6 chiffres (2xxxxx) à l'association.
- `bridge.py` : pont local école → serveur (tirage AcsEvent paginé, recul 60 min, poussée pointages + enrôlement, 1 passage/minute prévu).
- `046` : table schoolsafe_person_ids (ID universel 6 chiffres, staff 2xxxxx / élèves 3xxxxx). Lomela = 200001.
- `047` : table schoolsafe_device_enroll.

## Atelier technique (7 briques validées)
- Bibliothèques locales dans assets/libs/ : pdfjs, maplibre (+turf), tesseract (fra+eng) — le .htaccess protège /vendor/, dossier nommé libs.
- Proxys session-gated avec cache : ss-proxy-weather.php (30 min, clé OpenWeather dans /var/lib/schoolsafe-api/openweather-key — à installer), ss-proxy-dico.php (24 h, Wiktionary FR + dictionaryapi EN), ss-proxy-openverse.php (1 h).
- `sw-nouveau.js` = notification-push-sw.js augmenté : cache statique type Workbox en vanilla (SWR + immuable tesseract), navigateur jamais mis en cache (inchangé), push inchangé.
- `outils-demo.html` : page de test des 7 briques (connecté requis pour les proxys).

## Chaîne validée de bout en bout
Empreinte → appareil (192.168.0.200, heure Kinshasa corrigée) → pont → serveur → file → worker → entry_exit_staff_records → tableau de bord. 6 pressions réelles importées le 27/10.
