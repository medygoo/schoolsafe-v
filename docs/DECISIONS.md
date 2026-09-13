# Décisions validées SchoolSafe

Ce journal conserve les décisions durables. Il ne doit contenir ni secrets ni transcriptions brutes de conversations.

| Date | Décision | Statut |
|---|---|---|
| 2026-09-12 | Supprimer définitivement les traces et dépendances 3D du projet, tout en conservant JASPE 2,5D. | Validée et appliquée au commit `43982cb` |
| 2026-09-12 | Conserver SchoolSafe Control comme couche centrale protégée pour gérer les écoles inscrites, les licences, la supervision et l'impression. | Validée |
| 2026-09-12 | Travailler localement et sur GitHub en miroir, puis intervenir sur le VPS seulement lorsque les lots GitHub sont terminés. | Validée |
| 2026-09-12 | Ne pas utiliser Docker pour la cible finale ; prévoir un déploiement direct sur VPS. | Validée |
| 2026-09-12 | Limiter les tests, tout en maintenant des tests obligatoires pour les points critiques : sécurité, permissions, argent, enfants, isolation entre écoles et migrations. | Validée |
| 2026-09-13 | Améliorer JASPE en consolidant le moteur 2,5D existant plutôt qu'en reconstruisant le personnage. | Validée |
| 2026-09-13 | Le moteur physique JASPE doit couvrir le visage et le corps ; la voix et la synchronisation labiale sont reportées au raccordement Cloudflare. | Validée |
| 2026-09-13 | GitHub et les documents versionnés du dépôt deviennent la mémoire commune entre les deux comptes ChatGPT. | Validée |
| 2026-09-13 | Retenir l'approche 1 pour le physique JASPE : contrôleur unique progressif, v12 principal et WebP de secours. | Validée |
| 2026-09-13 | Supprimer définitivement l'ancien écran visuel `guardian`, ouvrir directement la connexion depuis le splash et conserver les références métier `school.guardian` des tuteurs d'élèves. | Validée |
| 2026-09-13 | Adopter les références SchoolSafe mobile et bureau comme direction visuelle officielle, corrigée pour la lisibilité, l'accessibilité et la performance ; conserver HTML/CSS/JavaScript, le logo existant et JASPE 2,5D, sans React, Ant Design ni 3D. | Validée |
| 2026-09-13 | Retenir quatre expériences adultes visibles — Direction, Enseignant, Gardien et Parent — et conserver les métiers internes comme postes combinables plutôt que comme profils applicatifs séparés. | Validée |
| 2026-09-13 | Autoriser l'administrateur principal d'une école à créer librement des postes et à composer leurs permissions, portées et limites dans l'enveloppe souveraine imposée par SchoolSafe Control et l'Access Law. | Validée |
| 2026-09-13 | Construire menus, tableaux de bord, services d'écosystème et capacités JASPE depuis un même contrat de session calculé par l'Access Law. | Validée |
| 2026-09-13 | JASPE peut exécuter les actions ordinaires autorisées ; les notes, paiements, permissions, suppressions, opérations sensibles de sécurité et données protégées d'enfant exigent une confirmation humaine. | Validée |
| 2026-09-13 | Afficher aux utilisateurs uniquement les services d'écosystème activés et autorisés ; montrer à la Direction les services activables et réserver le catalogue complet à SchoolSafe Control. | Validée |

## Décisions restant à préciser

- Technologie exacte de synthèse vocale Cloudflare lorsque le lot voix commencera.
- Procédure finale de service VPS, de proxy inverse et de sauvegarde avant le déploiement de production.
