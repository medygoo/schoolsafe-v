# Lot du 28/10 — sécurité + menus + secours JASPE

1. **Menu SchoolSafe regroupé** (Menu.php) : 7 sections natives RosarioSIS
   (Présence & Scans, Familles & Récupération, Cartes & Impressions,
   Notifications, Rattrapage, Frais & Paiements, Équipements).
   Les clés du catalogue hors regroupement restent au premier niveau.
2. **Droit fantôme comblé** : modules/Student_ID_Card/StudentIDCard.php —
   la passerelle réelle derrière AllowUse('Student_ID_Card') renvoie vers
   le Studio de cartes (le droit coché dans Profiles protège enfin un
   programme existant).
3. **Secours JASPE** : ProgramFunctions/SchoolSafeJaspeFallbackLLM.fnc.php
   + le branchement dans jaspe-chat.php (catch). Dormant sans les fichiers
   /var/lib/schoolsafe-jaspe/fallback-{url,key,model} — actif avec Groq
   gpt-oss-120b (testé bout en bout : « Test de secours reçu »).
4. **En-têtes de sécurité** (Caddyfile, site app.shoolsafe.tech) :
   HSTS 1 an + includeSubDomains, X-Content-Type-Options nosniff,
   X-Frame-Options SAMEORIGIN, Referrer-Policy strict-origin-when-cross-origin,
   Server masqué. Caddy validate puis reload, site 200 vérifié.

Découvertes : le palmarès tourne déjà (cron schoolsafe-dashboard-daily,
snapshots quotidiens école 3 ✓) ; le rattrapage s'armera aux premiers
cours/types de devoir de l'école ; Strix 1.7.0 installé (uv + Python 3.12
isolé) — premier scan partiellement aveugle (sandbox), audit en-têtes
fait à la main en complément.
