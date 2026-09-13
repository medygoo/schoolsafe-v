# Consignes de continuité SchoolSafe

Ce dépôt est la source de vérité commune pour tous les comptes ChatGPT/Codex et tous les assistants qui travaillent sur SchoolSafe.

## Avant toute intervention

1. Lire entièrement `docs/PROJECT_CONTEXT.md`, `docs/DECISIONS.md` et `docs/CURRENT_HANDOFF.md`.
2. Vérifier la branche, le commit local, le commit distant et l'état du dépôt.
3. Si le dépôt contient des changements non documentés, ne pas les écraser et déterminer à qui ils appartiennent.
4. Vérifier les affirmations importantes dans le code ou les tests. Les documents de continuité donnent le contexte, mais ne remplacent pas le dépôt.
5. Ne jamais copier une conversation complète dans le dépôt. Ne conserver que les décisions et informations nécessaires à la reprise.

## Règles permanentes

- GitHub et le dépôt local doivent rester en miroir à la fin de chaque lot validé.
- Travailler par lots petits, cohérents et vérifiables.
- Ne pas supprimer un module, des données ou une infrastructure existante sans demande explicite du propriétaire.
- Ne pas réintroduire de 3D. JASPE reste en 2D/2,5D.
- Ne pas ajouter Docker. Le déploiement final visé est direct sur VPS.
- SchoolSafe Control reste la couche centrale protégée d'administration, supervision, licence et impression.
- JASPE n'élargit jamais les permissions de l'utilisateur et ne remplace jamais une autorité humaine.
- Aucun mot de passe, token, cookie, clé API, clé privée ou donnée personnelle sensible ne doit entrer dans ces documents.
- Limiter les tests aux contrôles importants, mais tester obligatoirement les permissions, les données d'enfants, l'argent, l'isolation entre écoles, les migrations et les contrats critiques.

## Fin d'un lot

1. Exécuter les vérifications proportionnées au risque et noter leurs résultats réels.
2. Mettre à jour `docs/CURRENT_HANDOFF.md` avec le travail terminé, les fichiers touchés, les tests, les risques et la prochaine action exacte.
3. Ajouter dans `docs/DECISIONS.md` toute nouvelle décision validée qui modifie l'architecture ou le produit.
4. Committer puis pousser le lot validé sur GitHub.
5. Vérifier que le commit local et le commit distant sont identiques avant d'annoncer la synchronisation.

## Passage entre deux comptes

- Le compte qui quitte le projet doit pousser son dernier lot et mettre à jour le handoff.
- Le compte qui reprend doit récupérer GitHub avant de modifier le code, puis commencer par la section « Prochaine action » du handoff.
- Pour un travail séquentiel, utiliser une seule branche active. Pour un travail réellement simultané, utiliser des branches distinctes et fusionner après revue afin d'éviter les écrasements.
