# SchoolSafe — présentation livrée en production, 22 octobre 2026

## Résultat

**DÉPLOYÉ après validation de l’aperçu et GO explicite, puis contrôle séparé PASS.** Le propriétaire fera la validation visuelle connectée. Aucun premier accès humain ou message réellement reçu n’est déduit des tests fictifs.

- URL : https://app.shoolsafe.tech
- Image active : `sha256:a4a3cbf821083f60629031075c46dafdec8615fb91d591835408fa4afb260bf3`.
- Source runtime canonique publiée : `9c9b338046a3fd213c9b274726a39f0838fdba06`.
- Recette/checkout VPS : `8a969d5bcb4f9a97eda4181165d7db5dd2af2f38`, branche `deployed/prodeli-presentation-20261022`.
- Branche publiée de livraison : `release/schoolsafe-presentation-20261022`.
- Digest source : `11d25627ea5144279d83e692812abdfd61c13b82489e51b00192e2cc375afdea`.

## Périmètre

Deux lots visuels, 17 runtime : récupération, en-têtes/configuration/espace scolaire/central et préambules mobiles ; connexion, premier accès, fiche confidentielle isolée et présentation SchoolSafe effective unique. Fichiers des anciens thèmes et valeurs historiques conservés ; choix alternatifs suspendus. Héritage SchoolSafeCodex → SchoolSafe → FlatSIS maintenu.

**Notation exclue** : release construite sur le workspace effectivement déployé `8fc3546`, pas sur toute la branche de développement contenant `7893bc5`. Les fichiers Grades et Schools restent ceux de production ; le wizard ne change que par sa version CSS 16 → 17. Aucun changement de langue imposé aux utilisateurs, de permissions, de fournisseur ou de schéma.

## Qualification de l’image exacte

150 contrats PHP + 37 PG PASS ; 14 groupes natifs/premier accès, 17 workspace, dix récupération/SMS, course HTTP avec un gagnant et époque unique, deux rollbacks de révocation PASS. 2 747 autres fichiers identiques à la base, modes/propriétaires compris. Snapshot fictif final identique ; conteneurs/réseaux de recette seulement retirés. Téléchargement fictif contrôlé en mémoire ; captures et PDF expurgés avant persistance.

Preuves natives de `exact-r7`, workspace de `exact-r9`, récupération/concurrence/contrôle final dans `exact-r10` : **même ID d’image immuable**, et non simple identité de tag. Les ressources fictives ont été recréées autour de cette image sans rebuild, tous les hashes canoniques et contrats ont été revérifiés ; provenance/SHA des résultats conservés. Aucune validation rouge requalifiée en verte. Les difficultés de harnais et leurs corrections sont décrites dans [la recette](../../ops/releases/prodeli-presentation-20261022/README.md). Aucun runtime modifié après la qualification de l’image.

## Conservation et bascule

- Sauvegarde PostgreSQL privée 0600, 647 890 octets ; archive vérifiée, SHA-256 `9710f95a769c765edad30172e75adc0684b4b9e51b3eea6f02e11ae7ddddc7f4`.
- 15 sources précédentes sauvegardées (deux CSS nouvelles), préflight source/image/branche/configuration PASS.
- Bascule : 9,21 secondes ; **169 fichiers de session conservés**, dix entrées du cache Chromium conservées avec hashes/modes/propriétaires. Les nouvelles sessions anonymes créées par les contrôles ne remplacent pas les sessions conservées.
- Réglages du conteneur, configuration et autres services inchangés ; ancien workspace conservé arrêté sous `schoolsafe-rosario-pre-prodeli-presentation-20261022`.
- Aucune migration, restauration de base, émission externe ou manipulation de compte réel pour essai. Aucun rollback automatique.

## Contrôle indépendant après livraison

PASS séparé du script de déploiement : image healthy, 17 hashes = Git publié, cinq sources moteur récupération/SMS/onboarding inchangées, config et autres services identiques, sauvegarde valide/privée, sources précédentes et sessions/cache vérifiés, Git VPS propre sur le commit annoncé.

HTTPS : connexion, récupération et réinitialisation 200 ; administrateurs 302 anonyme, console/SMS/WhatsApp 403. `deploy.py status` PASS également. Les contrats de permissions et scope ont été testés avec fixtures, pas en créant une nouvelle école de production.

## Reste au propriétaire

Validation visuelle connectée sur ordinateur/téléphone, avec sa session habituelle. Ne pas ouvrir une fiche réelle à usage unique juste pour contrôler son graphisme. Réception de message réel, premier accès humain et certification de tous les PDF/modules restent distincts. Le déplacement de la base de notation demeure publié dans la branche de travail mais **non livré**.

Preuves privées : `C:/Users/PC/SchoolSafeProductionUpdate-20261021-evidence/presentation-release-20261022/exact-r10/` ; release VPS privée `/opt/schoolsafe-releases/prodeli-presentation-20261022/` (sauvegardes et secrets de session non publiés).
