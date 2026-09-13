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

## Décisions restant à préciser

- Technologie exacte de synthèse vocale Cloudflare lorsque le lot voix commencera.
- Procédure finale de service VPS, de proxy inverse et de sauvegarde avant le déploiement de production.
