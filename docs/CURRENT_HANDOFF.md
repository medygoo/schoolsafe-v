# Handoff courant SchoolSafe

Dernière mise à jour : 13 septembre 2026.

## Objectif actif

Préparer puis exécuter par petits lots le système visuel responsive SchoolSafe validé pour la connexion, l'OTP, la navigation et le tableau de bord. La logique métier, SchoolSafe Control, la voix, GLM, la synchronisation labiale, le raccordement JASPE au tableau de bord et le VPS restent hors de ce chantier visuel.

## Source de vérité

- Dépôt : `https://github.com/medygoo/schoolsafe-v`
- Branche : `main`
- Toujours vérifier le commit réel et `git status` avant de reprendre.
- Le serveur local correct sert le dépôt courant sur `http://127.0.0.1:4176/`.
- Le port `4175` sert une ancienne copie distincte et ne doit pas servir à valider le dépôt courant.

## Dernier lot terminé

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

- `npm run test:jaspe-physical` : 5 tests réussis, 0 échec ; contrat physique v12/façade affiché `PASS`.
- `node app/qa-safe-assistant-access.cjs` : `FE-SEC-A3A4 access law + safe assistant gate: PASS`.
- `npm run test:no-guardian-screen` : `Legacy guardian screen removal: PASS`.
- contrôles de syntaxe Node des scripts modifiés : code 0.
- navigateur sur `4176` : bouton `Commencer` vers `#auth.active`, formulaire visible et aucun élément `#guardian`.
- navigateur sur `4176` : JASPE v12 visible ; `listen` observé en `attentive`, `explain` en `guide` et la soumission en `deepThink`.

## Limites vérifiées honnêtement

- La priorité `error/refuse` et le repli lors d'un refus du moteur sont couverts par les tests automatisés.
- L'émulation navigateur de `prefers-reduced-motion` et le blocage réseau du manifeste v12 n'ont pas été rejoués manuellement avec l'outil de navigateur disponible.
- L'assistant flottant de l'espace de travail n'est toujours pas activé.
- La voix, GLM et la synchronisation labiale ne sont pas implémentés dans ce lot.

## Direction visuelle verrouillée

Le propriétaire a validé le 13 septembre 2026 les références SchoolSafe mobile et bureau avec les corrections proposées : moins de verre, de lueurs et d'ombres, meilleure lisibilité, accessibilité et performance. L'implémentation reste en HTML/CSS/JavaScript, utilise `app/schoolsafe-logo.png`, conserve JASPE 2D/2,5D et n'introduit ni React, ni Ant Design, ni 3D.

Spécification : `docs/superpowers/specs/2026-09-13-schoolsafe-responsive-visual-system-design.md`.

Plan : `docs/superpowers/plans/2026-09-13-schoolsafe-responsive-visual-system.md`.

Feuille de route complète : `docs/superpowers/plans/2026-09-13-schoolsafe-complete-delivery-roadmap.md`.

## Prochaine action exacte

1. Exécuter le lot 0 de la feuille de route complète : baseline reproductible et inventaire des écarts démonstration/réel.
2. Exécuter ensuite le lot 1 visuel : contrat QA, consolidation des tokens et réduction des effets globaux excessifs.
3. Continuer dans l'ordre de la feuille de route ; le VPS reste le dernier lot et se fait directement, sans Docker.

## Procédure de reprise depuis l'autre compte

1. Ouvrir ou cloner le dépôt GitHub.
2. Lire `AGENTS.md`, puis `docs/PROJECT_CONTEXT.md`, `docs/DECISIONS.md` et ce fichier.
3. Vérifier `git status`, la branche active et la correspondance avec `origin/main`.
4. Reprendre la section « Prochaine action exacte » sans réinventer les décisions validées.
