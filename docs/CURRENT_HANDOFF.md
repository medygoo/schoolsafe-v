# Handoff courant SchoolSafe

Dernière mise à jour : 13 septembre 2026.

## Objectif actif

Faire relire puis planifier l'harmonisation validée des expériences, postes, accès, tableaux de bord, services d'écosystème et capacités JASPE. La connexion, l'OTP et l'espace JASPE responsive restent le prochain lot visuel à exécuter après cette clarification d'architecture.

## Source de vérité

- Dépôt : `https://github.com/medygoo/schoolsafe-v`
- Branche : `main`
- Toujours vérifier le commit réel et `git status` avant de reprendre.
- Le serveur local correct sert le dépôt courant sur `http://127.0.0.1:4176/`.
- Le port `4175` sert une ancienne copie distincte et ne doit pas servir à valider le dépôt courant.

## Dernier lot terminé

### Lot 1 — fondations visuelles responsive

- contrat statique `test:visual-system` ajouté : dépendances, identifiants DOM, logo, ordre CSS, focus, mouvements réduits et absence de l'ancien écran `guardian` ;
- tokens `--ss-*` complétés pour les surfaces, contrôles de 48 px, cibles tactiles de 44 px, focus visible, largeurs et ombres mesurées ;
- anciens alias `--ds-*` centralisés comme transition vers les tokens `--ss-*` ;
- glassmorphism global, halos fixes, surcharges `!important` et flèches automatiques retirés ;
- composants partagés harmonisés sans cibler globalement tous les champs et boutons ;
- import Google Fonts retiré après détection par le navigateur : la CSP reste fermée aux styles externes et la pile locale prend immédiatement le relais ;
- bandeau de démonstration ramené à l'étiquette neutre `Mode aperçu — données fictives.` ; il reste visible en session fictive et disparaît avec un vrai jeton API ; le cache PWA a été versionné pour livrer immédiatement le nouveau CSS ;
- tableaux de bord administrateur, parent, enseignant, caisse et contrôle vérifiés en 1440 px et 390 px ; aucune régression de navigation ni débordement horizontal ;
- SchoolSafe Control, JASPE 2D/2,5D, routes et logique métier inchangés.

Plan suivi : `docs/superpowers/plans/2026-09-13-schoolsafe-responsive-visual-system.md` (tâches 1 et 2 cochées).

## Lots précédents

### Lot 0A — baseline critique réparée

- catalogue partagé des 60 permissions restauré et vérifié contre le seed SQL ;
- contrôle de migrations en lecture seule ajouté pour 6 ensembles et 21 unités ;
- hashes SQL normalisés et prouvés identiques sous LF/CRLF ;
- `/config.setup_available` dépend du vrai token setup ; anciennes routes `/session/bootstrap` et `/auth/lookup-phone` absentes ;
- upload de test isolé et nettoyé, anciens faux logos retirés ;
- Fastify `5.12.4`, Vitest `4.1.11` et `fast-uri` corrigés ; audit NPM à zéro vulnérabilité ;
- CI verte : 51 fichiers, 276 tests serveur, typecheck et contrôle migrations réussis ;
- 57 tests statiques/contrats SQL et multi-plateformes réussis.

Rapport : `docs/BASELINE_REPORT.md`.

### Contrôleur physique JASPE

- ajout de neuf intentions fermées : `idle`, `listen`, `think`, `speak`, `explain`, `reassure`, `refuse`, `success`, `error` ;
- traduction déterministe vers le moteur v12 et le repli WebP ;
- arbitrage par priorité, minuterie, arrêt et destruction ;
- cycle de vie v12 indépendant de `.auth-screen` ;
- nettoyage des écouteurs et de la boucle d'animation à la destruction ;
- raccordement de la connexion par intentions au lieu des références directes de packs ;
- correction du conflit qui annulait `listen` pendant la saisie ;
- ajout du repli `SPEAKING` et contournement ciblé de l'ancien cache des scripts.

### Suppression de l'ancien écran `guardian`

- le splash ouvre directement la connexion ;
- suppression des styles `.guardian`, `.guardian-copy`, `.children-line`, `.overlay-brand` et `.gallery-source` ;
- retrait de `guardian` de la détection de surface de l'ancien assistant ;
- correction des scénarios QA qui attendaient encore la galerie intermédiaire ;
- conservation explicite des permissions et données métier `school.guardian` relatives aux tuteurs d'élèves.

## Vérifications exécutées

- `npm run ci` : migrations PASS, typecheck PASS, 51 fichiers/276 tests serveur PASS.
- tests statiques SQL + contrôle LF/CRLF : 57 tests PASS.
- `npm audit --json` : 0 vulnérabilité.
- `npm run test:jaspe-physical` : 5 tests réussis, 0 échec ; contrat physique v12/façade affiché `PASS`.
- `node app/qa-safe-assistant-access.cjs` : `FE-SEC-A3A4 access law + safe assistant gate: PASS`.
- `npm run test:no-guardian-screen` : `Legacy guardian screen removal: PASS`.
- `npm run test:visual-system` : contrat visuel et suppression de l'ancien écran `guardian` PASS.
- contrôle grand écran/téléphone : 5 profils ouverts en démonstration sur 1440 × 1000 et 390 × 844, captures relues, aucun débordement mobile.
- contrôles de syntaxe Node des scripts modifiés : code 0.
- navigateur sur `4176` : bouton `Commencer` vers `#auth.active`, formulaire visible et aucun élément `#guardian`.
- navigateur sur `4176` : JASPE v12 visible ; `listen` observé en `attentive`, `explain` en `guide` et la soumission en `deepThink`.

## Limites vérifiées honnêtement

- NPM avertit encore que la politique `allowScripts` n'autorise pas explicitement les postinstall Esbuild ; ce choix doit être fixé avant la release VPS.
- L'upload de logo filtre aujourd'hui par type MIME déclaré et taille ; la vérification des octets/signatures d'image reste à durcir avant l'exposition publique.
- La priorité `error/refuse` et le repli lors d'un refus du moteur sont couverts par les tests automatisés.
- L'émulation navigateur de `prefers-reduced-motion` et le blocage réseau du manifeste v12 n'ont pas été rejoués manuellement avec l'outil de navigateur disponible.
- L'assistant flottant de l'espace de travail n'est toujours pas activé.
- La voix, GLM et la synchronisation labiale ne sont pas implémentés dans ce lot.
- Le contrôle navigateur multi-profils termine ses assertions fonctionnelles et ses captures, mais son collecteur de console signale `ERR_CONNECTION_REFUSED` tant que l'API locale sur le port 8787 n'est pas démarrée ; ce n'est pas une erreur CSS.
- `app/qa-pwa.cjs` confirme le contrôle du service worker et la disponibilité des assets en ligne/hors ligne, puis s'arrête sur son ancien scénario qui tente encore de mettre une opération sensible `administration` en file sans session serveur ; la loi d'accès actuelle la refuse correctement.

## Direction visuelle verrouillée

Le propriétaire a validé le 13 septembre 2026 les références SchoolSafe mobile et bureau avec les corrections proposées : moins de verre, de lueurs et d'ombres, meilleure lisibilité, accessibilité et performance. L'implémentation reste en HTML/CSS/JavaScript, utilise `app/schoolsafe-logo.png`, conserve JASPE 2D/2,5D et n'introduit ni React, ni Ant Design, ni 3D.

Spécification : `docs/superpowers/specs/2026-09-13-schoolsafe-responsive-visual-system-design.md`.

Plan : `docs/superpowers/plans/2026-09-13-schoolsafe-responsive-visual-system.md`.

Feuille de route complète : `docs/superpowers/plans/2026-09-13-schoolsafe-complete-delivery-roadmap.md`.

## Prochaine action exacte

1. Faire valider puis exécuter `docs/superpowers/plans/2026-09-13-schoolsafe-canonical-access-law.md`, produit comme premier plan après la spécification d'harmonisation.
2. Vérifier et synchroniser chacun de ses sept lots techniques avant de commencer l'éditeur de postes personnalisés.
3. Reprendre ensuite la tâche 3 du plan visuel en l'alignant sur le contrat de session validé.
4. Continuer dans l'ordre des plans séparés ; le VPS reste le dernier lot et se fait directement, sans Docker.

## Procédure de reprise depuis l'autre compte

1. Ouvrir ou cloner le dépôt GitHub.
2. Lire `AGENTS.md`, puis `docs/PROJECT_CONTEXT.md`, `docs/DECISIONS.md` et ce fichier.
3. Vérifier `git status`, la branche active et la correspondance avec `origin/main`.
4. Reprendre la section « Prochaine action exacte » sans réinventer les décisions validées.
