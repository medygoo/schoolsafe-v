# Handoff courant SchoolSafe

Dernière mise à jour : 13 septembre 2026.

## Objectif actif

Préparer l'amélioration du physique de JASPE 2,5D : expressions faciales, regard, tête, respiration, postures et gestes du corps. La voix et la synchronisation des lèvres sont hors périmètre de ce lot.

## État du dépôt au début de ce handoff

- Dépôt : `https://github.com/medygoo/schoolsafe-v`
- Branche : `main`
- Commit de référence avant la mise en place de la mémoire : `43982cb`
- Le dépôt était propre avant la création de ces documents.
- Le commit `43982cb` retire les dernières traces 3D identifiées.

Toujours vérifier le commit réel avec Git avant de reprendre : ce document ne remplace pas l'historique Git.

## Dernier lot terminé

Mise en place de la continuité entre comptes ChatGPT/Codex :

- création de `AGENTS.md` pour imposer la procédure de reprise et de synchronisation ;
- création de `docs/PROJECT_CONTEXT.md` pour les invariants produit et techniques ;
- création de `docs/DECISIONS.md` pour les décisions humaines validées ;
- création de ce handoff courant pour transmettre l'état, les preuves et la prochaine action.

Vérifications de ce lot :

- contrôle de format Git sans erreur ;
- recherche ciblée de formes de secrets sans résultat ;
- aucune modification du code applicatif, du serveur, des données ou des ressources JASPE.

## Audit JASPE déjà effectué

### Éléments solides

- Le moteur v12 possède une machine de transitions, des poses, des intensités, des clignements, un regard, une respiration et des gestes déterministes.
- Les poses sont chargées à la demande et leur empreinte SHA-256 est vérifiée.
- L'animation est limitée à 24 images par seconde, suspendue lorsque l'écran n'est pas visible et adaptée à `prefers-reduced-motion`.
- Le moteur léger fournit un rendu WebP de secours et des états `IDLE`, `LISTENING`, `THINKING`, `SPEAKING`, `ERROR` et `OFFLINE`.
- Le routeur JASPE applique une gouvernance par permissions et portées, avec refus par défaut.
- Le serveur protège `/native/jaspe/chat` par session lorsque l'authentification est branchée et applique une limite de requêtes.

### Défauts confirmés à ne pas ignorer

1. `app/app.js` monte JASPE sur la connexion en mode explicitement visuel uniquement.
2. `app/modules/safe/safe-assistant.js` conserve des noms d'animations historiques (`TalkHandsOpen`, `Agree`, `Shrug`, etc.) qui ne correspondent pas directement aux actions v12.
3. La fonction `playVisual()` de cet assistant ne commande actuellement aucun moteur.
4. L'assistant et sa feuille de style ne sont pas chargés par `app/index.html` dans l'état audité.
5. Aucun écran actif n'appelle `SchoolSafeJaspe2d.chat()`.
6. Le client léger cherche `reply` directement, alors que le serveur renvoie `{ data: { reply } }`.
7. `live-companion.js` suppose une surface `.auth-screen` et ne peut pas être réutilisé tel quel dans l'espace de travail.
8. Aucun moteur vocal, flux audio, système de phonèmes ou synchronisation labiale n'est présent.
9. Les ressources JASPE totalisent environ 63,8 Mo ; v12 représente environ 42,8 Mo et doit rester chargé progressivement.
10. Le moteur doit obtenir un cycle explicite `mount/play/stop/destroy` avant des montages multiples.
11. Le Worker Cloudflare autorise actuellement une origine générique et n'utilise pas encore les variables prévues pour l'identité de l'instance et les origines autorisées. Traiter ce point dans un lot de sécurité distinct.

## Vérifications déjà exécutées

- `node app/qa-safe-assistant-access.cjs` : **PASS**.
- Les tests serveur JASPE n'ont pas été exécutés pendant l'audit, car `vitest` n'était pas installé dans l'environnement local. Ne pas les considérer comme validés.

## Architecture recommandée pour le prochain lot

Créer un contrôleur de présentation unique avec une liste fermée d'intentions :

- `idle`
- `listen`
- `think`
- `speak`
- `explain`
- `reassure`
- `refuse`
- `success`
- `error`

Le contrôleur traduit chaque intention en action v12 autorisée, intensité, expression et priorité. Le moteur v12 est l'adaptateur principal ; les images WebP restent le repli. Aucun modèle IA ne fournit un nom d'animation libre.

## Prochaine action exacte

1. Faire relire et valider par le propriétaire la spécification `docs/superpowers/specs/2026-09-13-jaspe-physical-controller-design.md`.
2. Après cette validation, écrire le plan d'implémentation détaillé du premier lot.
3. Implémenter ensuite le contrôleur et ses tests essentiels conformément au plan validé.
4. Ne pas inclure la voix, les lèvres, le déploiement VPS ni le durcissement du Worker dans ce premier lot physique.

## Procédure de reprise depuis l'autre compte

1. Ouvrir ou cloner ce dépôt GitHub.
2. Lire `AGENTS.md`, puis `docs/PROJECT_CONTEXT.md`, `docs/DECISIONS.md` et ce fichier.
3. Vérifier `git status`, la branche active et la correspondance avec `origin/main`.
4. Demander à l'assistant de reprendre la section « Prochaine action exacte » sans réinventer les décisions validées.
