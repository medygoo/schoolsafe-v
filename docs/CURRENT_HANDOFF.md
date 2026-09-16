# Handoff courant SchoolSafe

## Lot courant — 16 septembre 2026 : parcours vocal et interruption

Le propriétaire a repris le chantier par « GO » après les conseils sur le naturel
des gestes et la validation du parcours vocal. La clôture précédente est historique.

### Livré

- Boutons **Écouter la réponse / Couper la voix** dans le bandeau et la boîte
  flottante : relecture de la réponse courante, sans historique ni enregistrement.
- Une nouvelle question écrite interrompt le micro et la voix précédents ; une
  réponse identique à la précédente peut être prononcée de nouveau.
- Statuts du micro corrigés à la fin de l'écoute, erreurs distinctes (silence,
  refus, connexion, capture) et retour écrit conservé. Les événements tardifs
  d'une écoute ou d'une voix remplacée sont ignorés.
- Gestes de parole jusqu'à la fin réelle de l'énoncé, interruption immédiate pour
  écouter, également en corps entier. Le moteur entier ne reçoit plus les gestes
  du bandeau lorsqu'il est caché. Les refus/alertes gardent leur priorité.
- Compteur de changement de contexte, sans identifiant exposé : il arrête audio et
  micro même entre deux comptes autorisés ; sortie/onglet caché arrêtent aussi l'audio.
- Dialogue ajusté à `visualViewport` : commandes accessibles quand le clavier réduit
  la hauteur visible. Contrôles en clair/sombre ; cache applicatif renouvelé.

### Fichiers et contrôles

- `dashboard-companion.js`, compteur dans `safe-assistant.js`, boutons dans
  `index.html`, `jaspe-dashboard.css`, `sw.js`, contrat de cache, nouveau
  `qa-jaspe-voice.cjs`, décisions et ce handoff. Aucun asset graphique modifié.
- Défauts reproduits avant correction : statut « Je vous écoute » après fin du
  micro ; champ de saisie inaccessible dans la boîte à 390×360. Corrigés.
- Contrôles navigateur **PASS** : `qa-jaspe-voice` (1440/390, clair/sombre, arrêt/relecture,
  question répétée, événements tardifs, changement de compte autorisé, erreurs,
  commandes à 390×360) et `qa-jaspe-dashboard` (bureau/mobile/paysage, permissions,
  compte, réponse unique, sombre/mouvements réduits). Adaptateurs audio simulés.
  La parole reste active au-delà des anciennes 4,5 secondes ; captures du dialogue
  mobile et à hauteur réduite inspectées.
- Accès assistant, contrat visuel, permissions 3/3, physique 5 tests + contrat,
  syntaxe JS et `git diff --check` : PASS. Aucun test SQL/migration/argent relancé,
  ces couches ne sont pas modifiées.
- Essai dans le navigateur intégré, sans adaptateur : préparation puis fin de
  lecture observées, seconde lecture interrompue par le bouton, démarrage du
  micro observé, micro arrêté à la reprise/arrêt de la lecture. Pas de phrase
  humaine transcrite validée ; qualité acoustique et téléphone réel non vérifiés.
- Aucune modification du backend, des permissions métier, des données ou du SQL.
  GLM/Cloudflare et synchronisation phonétique restent hors de ce lot.

### Suite demandée pendant le lot

Le propriétaire souhaite que JASPE puisse aussi se lever dans le bandeau, visible
par le haut du corps, avec images adaptées à l'arrière-plan. Proposition expliquée :
bureau fixe masquant le bas, étapes assise → appui sur les mains → debout/explique →
retour assis, même identité et deux thèmes. **Idée discutée, images non produites et
transition non implémentée dans ce lot vocal.** À concrétiser à la prochaine demande.

Prochaine action : recueillir le retour sonore du propriétaire et essayer une phrase
au micro sur son appareil ; puis reprendre sa demande de transition assise/debout.
Les réserves métier Le Sage/P5/base réelle restent valides. Les fichiers utilisateur
non suivis (`.claude/` et deux PNG racine) sont préservés hors du lot.

Lot Git : `fix(jaspe): fiabiliser le parcours vocal`. SHA dans l'historique Git ;
annoncer la synchronisation uniquement après comparaison HEAD/branche distante.

---

## Historique — clôture du 16 septembre 2026 : parole avec les deux mains

Le propriétaire a demandé le mouvement de parole avec les deux mains, puis la
clôture du chantier courant. Aucun nouveau développement à lancer sans sa reprise.

- Deux nouvelles planches `parole-deux-mains-clair.png` / `-sombre.png` : quatre
  étapes chacune. Total **10 planches / 40 vignettes**, 16,28 Mio. Les huit planches
  précédentes sont inchangées (SHA-256 comparés au manifeste antérieur).
- Action fermée `speakBoth`, rythme propre, bouton « Parler · deux mains » dans
  l'aperçu ; la lecture vocale conserve le geste sélectionné. Les états métier
  `TalkHandsOpen` / `TalkPassionately` et l'accueil utilisent l'intention existante
  `explain`, traduite en deux mains pour la pose assise. Corps entier inchangé.
- Fichiers : deux PNG, manifeste et prompts dédiés, README des assets,
  `seated-companion.js`, `dashboard-companion.js`, aperçu, contrôles navigateur,
  version du cache et contrat associé, décisions et ce handoff.
- Contrôles : dimensions 1536×1024/grille 2×2/hashes PASS ; syntaxe JS PASS ;
  `qa-jaspe-seated` PASS (5 actions/deux thèmes, voix simulée avec deux mains,
  pause, mouvements réduits, 390/320) ; contrats visuels et accès assistant PASS.
- `qa-jaspe-dashboard` PASS : réponse avec deux mains, sortie entière, retour,
  texte/audio simulé, refus de permission, changement de compte, sombre et
  mouvements réduits. Aucune modification des permissions, données métier ou SQL.
- Limites inchangées : fonds intégrés, animation par poses, pas de synchronisation
  phonétique ; microphone réel/voix réelle non validés par ces tests simulés.
- Aperçu : `http://127.0.0.1:4176/jaspe-assise-preview.html#deux-mains`.
- Lot : `feat(jaspe): parole avec les deux mains`. SHA dans Git, miroir à
  confirmer après push. Fichiers utilisateur non suivis préservés.

**À la reprise :** demander le prochain objectif au propriétaire. Les réserves
métier et de voix du lot précédent restent documentées ci-dessous.

---

## Lot précédent — 16 septembre 2026 : JASPE assise, planches animées

### Demande et livraison

- Le propriétaire a demandé de remplacer la coupure du buste par une JASPE
  réellement assise à son bureau, puis des images regroupées par mouvement.
- **8 planches / 32 vignettes** : parole avec geste de main, réflexion/main dans
  les cheveux, sourire/clignement, lecture souriante ; chaque action en clair et
  sombre. La dernière correction ajoute le sourire pendant la lecture.
- Sources générées avec l'outil intégré, à partir de JASPE v12 ; les deux poses
  statiques initiales sont conservées dans `docs/design/jaspe-assise-v1/`.
- `app/assets/jaspe2d/assise-v1/` : PNG 1536×1024, grille 2×2, cadres 768×512,
  13,01 Mio pour les 8 planches, prompts et manifeste SHA-256.
- `seated-companion.js` : séquences temporisées, chargement à la demande,
  transitions courtes, façade avant du bureau fixe, thèmes, repos/clignement et
  lecture occasionnelle. Pause hors écran/onglet caché, mouvements réduits,
  conservation de la pose précédente si une planche ne charge pas.
- `dashboard-companion.js` raccorde les intentions fiables et les événements
  début/fin de synthèse vocale. Le moteur v12 existant sert toujours la sortie
  entière ; sa surface enregistrée reste `workspace-bust`, avec `isBust=false`.
- Mise en page assise bureau/mobile, scripts/styles chargés et cache renouvelé.
- Aperçu rejouable : **http://127.0.0.1:4176/jaspe-assise-preview.html**.
  Ancien aperçu statique sur 4180, application principale sur 4176.

### Contrôles et limites

- Contrôles de géométrie : 8 PNG, grille/dimensions conformes, hashes sauvegardés.
- `qa-jaspe-seated.cjs` : PASS pour les quatre actions/deux thèmes, changement
  d'images, audio simulé début/fin, pause, pause hors écran, mouvements réduits,
  largeurs 390/320, aucune erreur JavaScript.
- `test:jaspe-physical` (5 tests + contrat), `qa-responsive-visual-system`,
  `qa-safe-assistant-access`, permissions (3 tests) : PASS.
- `qa-jaspe-dashboard.cjs` : PASS bureau/mobile/paysage, appui prolongé,
  moteur entier conservé, fermeture/retour, réponse unique, audio simulé pilotant
  les poses, refus de permission, changement de compte, sombre/mouvements réduits.
  Le test attend maintenant la visibilité après la transition de compte.
- Aucun test SQL/argent/migration relancé : aucune modification de ces couches.
- Les essais de transparence ont produit un damier opaque et ont été écartés.
  Les planches retenues ont un **fond intégré**, pas d'alpha. Petites variations
  génératives possibles entre cadres ; animation de poses, pas vidéo interpolée.
- La voix navigateur est testée par adaptateur simulé ; microphone réel et
  synchronisation phonétique non validés. GLM/Cloudflare reste non raccordé ici.
- Aucun nouveau stockage de conversation, aucune extension de permissions,
  aucune modification du moteur v12 ou de ses assets verrouillés.

### Prochaine action exacte

1. Rejouer les actions dans l'aperçu animé avec le propriétaire et vérifier la
   voix réelle du navigateur. Tester ensuite le bandeau clair/sombre et la sortie
   entière dans l'application principale.
2. Affiner uniquement les gestes ou transitions signalés ; une animation plus
   fine demandera davantage d'intermédiaires ou des calques réellement séparés.
3. Les réserves métier du lot précédent (Le Sage, P5, base réelle) restent valides.
4. `.claude/` et les deux références originales non suivies restent hors du lot.

Lot à identifier par `feat(jaspe): poses assises animees par action` dans Git.
Synchronisation à annoncer après comparaison directe HEAD/GitHub.

---

## Historique — 16 septembre 2026 : buste initial et sortie entière

Cette section conserve le premier lot du 16 septembre. Son cadrage en buste et
sa prochaine action visuelle sont remplacés par l'état actif ci-dessus. La demande
directe du propriétaire autorise la reprise du chantier frontend dans ce compte.

### Demande et résultat

- Reprendre les références bureau/mobile avec **la JASPE existante**.
- Bandeau commun aux tableaux de bord (y compris Parent/Enseignant/Gardien), buste
  v12 animé, accueil lié au profil courant, texte + bouton d'envoi + micro sur place.
- Appui prolongé **450 ms** sur JASPE (personnage ou bouton mobile) : sortie en corps
  entier, même instance déplacée dans une boîte flottante non modale. Clic/clavier
  possibles. Fermeture par croix/Échap, retour au bandeau.
- Corrections du propriétaire intégrées : discussion **sans quitter le bandeau**,
  audio entrant/réponse vocale via Web Speech lorsque disponible, **aucun historique
  de chat**. Seule la réponse courante est gardée en mémoire.
- Interface pâle/discrète, sombre/mouvements réduits ; fonctionnalités avant services
  sur bureau, services existants également accessibles dans la vue mobile.

### Corrections techniques et fichiers

- `app/modules/jaspe2d/dashboard-companion.js` (nouveau) : montage unique, appui
  prolongé, dialogue flottant, écoute explicite, lecture des réponses, contrôle accès.
- `app/styles/modules/jaspe-dashboard.css` (nouveau) : buste/corps entier,
  responsive, chat discret, bouton JASPE mobile et ajustements du tableau de bord.
- `app/index.html`, `app/app.js` : chargement réel de SafeAssistant, raccordement
  des surfaces, bandeau commun ; ancien bloc de panneau extrait/consolidé (retrait
  du polling et des multiples montages à chaque rendu du tableau de bord).
- `app/modules/jaspe2d/jaspe2d.js` : visibilité/cadrage injectables,
  paramètres de connexion conservés par défaut. Aucun nouvel asset ni identité.
- `app/modules/safe/safe-assistant.js` : présentation intégrée, réponse courante
  seule, retrait du journal de 30 entrées, correction de la capture du bouton
  d'envoi, nettoyage sur changement de contexte/refus de permission.
- `app/server.mjs` : microphone limité à `self` (autorisation utilisateur toujours
  requise), caméra et géolocalisation toujours interdites.
- `app/sw.js` et contrats de cache : nouvelle version pour publier les scripts.
- `app/qa-jaspe-dashboard.cjs` (nouveau), contrats JASPE/visuel adaptés à la version.
- `docs/DECISIONS.md`, ce handoff : décisions et résultats du lot.
- Les changements JASPE non committés du 15/09 sont intégrés/consolidés ici.
  Les quatre commits locaux préexistants de `9298c85` à `e26ecef` sont conservés.
  Aucun serveur métier ni SQL modifié.

### Vérifications réellement exécutées

- Playwright `node app/qa-jaspe-dashboard.cjs` : PASS sur 1440×1000, 390×844,
  320×640 et 844×390 ; appui prolongé avant relâchement, même moteur, aucun
  débordement, saisie dans le bandeau, réponse unique, fermeture/retour, absence de
  stockage de conversation, audio simulé dans le bandeau, refus même admin,
  nettoyage entre contextes, Parent, mode sombre et mouvements réduits.
- Captures inspectées : bureau, mobile, sortie entière, petit écran, paysage, sombre.
- `qa-safe-assistant-access`, `qa-responsive-visual-system`,
  `qa-no-guardian-screen` : PASS. `test:jaspe-physical` : 5 tests + contrat PASS.
- Permissions : 3/3 PASS ; manifestes migrations : 9 sets / 27 unités PASS.
- Syntaxe JS contrôlée. En-tête local vérifié : microphone=(self).
- Audio testé avec adaptateur Web Speech simulé, **pas avec un microphone réel**.
  Aucune affirmation de validation vocale réelle ou de connexion Cloudflare.

### Limites et prochaine action exacte

1. Faire valider visuellement le résultat au propriétaire sur le port **4176** et
   essayer le microphone réel dans son navigateur (support/autorisation requis).
2. Les réponses utilisent l'assistant métier/local existant. Le modèle distant
   GLM/Cloudflare et la synchronisation labiale ne sont pas raccordés ici.
3. Ne pas confondre les anciens lots visuels « P4 terminé » avec la preuve du cœur
   métier : le retrait des enfants comporte encore des parcours démo BACKEND_LATER.
   Vérifier ces parcours avant de déclarer Le Sage opérationnel.
4. Puis P5 : sauvegarde/restauration exécutée et SQL sur base réelle. Le désaccord
   documentaire Docker/AGENTS.md est antérieur et non arbitré dans ce lot.
5. `.claude/` et les deux images originales non suivies à la racine appartiennent à
   l'environnement/références de l'utilisateur : préservés hors du commit.

### Livraison Git

Lot à identifier par `feat(jaspe): compagnon flottant et reponse sans historique`.
SHA exact dans `git log`. Synchronisation annoncée seulement après push et comparaison
directe de HEAD avec GitHub ; ce document ne contient pas son propre SHA.

---

## Historique conservé — rapports du 15 septembre (remplacés par l'état actif ci-dessus)

Dernière mise à jour : 15 septembre 2026 — Lot JASPE hero vivant + log intégré.

### Rapport — Lot JASPE hero (2.5D vivant + log de conversation intégré)

```text
PRÉVU :    JASPE 2.5D VIVANTE (moteur jaspe2d.js, pas d'image statique)
           dans le hero desktop + mobile, en BUSTE (membre supérieur,
           corps coupé par le bas) ; LOG DE CONVERSATION COMPLET intégré
           au hero (teinte pâle discrète) ; suppression du bouton séparé
           « Écrire » (remplacé par le champ intégré) ; bouton Audio
           conservé ; clic sur Jaspe → sortie flottante (panneau) ;
           suppression des anciennes sections chat fixes redondantes.
FAIT :     index.html : hôtes vides #jaspeHeroCharacterDesktop/Mobile
           montés par le moteur 2.5D ; log data-jaspe-chat + champ +
           bouton send intégrés sous les 3 badges (desktop + mobile) ;
           anciennes sections chat fixes (desktop + mobile) supprimées.
           app.js : mountJaspeHero2d() monte SchoolSafeJaspe2d.mountShowcase()
           (variants explain/idle/wave en rotation + réactions
           listening/thinking/speaking/congratulate/worried) ; clic sur
           le buste → openJaspePanel(). dashboard-liquid-glass.css :
           recadrage buste (object-fit cover, top center) ; styles
           .jaspe-hero__chat pâles + contre-poids mode sombre.
           safe-assistant.js : câblage du bouton data-jaspe-chat-send
           (même mécanisme que Enter → openWithQuery).
TESTS :    node --check app/app.js OK · node --check safe-assistant.js OK ·
           git grep confirme hôtes + montage + boutons send (desktop 427,
           mobile 572).
NON TOUCHÉ: jaspe-governance, endpoint /native/jaspe/chat, serveur, SQL.
RISQUES :  validation visuelle navigateur (4176) à refaire : buste coupé,
           log pâle lisible clair/sombre, clic → panneau flottant.
COMMIT :   en attente — sandbox bloque .git/index.lock ; commit + push
           avec élévation au prochain tour.
```

Dernière mise à jour : 15 septembre 2026 — Lot JASPE Étape A (buste hero + badges + sortie plein écran).

### Rapport — Lot JASPE Étape A (JASPE en buste dans le hero)

```text
PRÉVU :    JASPE en BUSTE uniquement dans le hero du dashboard (desktop +
           mobile), corps coupé par le bas (overflow hidden, ancrage bas) ;
           bulle de dialogue au-dessus ; 3 badges « Plus simple / Plus sûr /
           Plus serein » ; boutons Écrire + Audio ; au clic sur le bouton
           JASPE ou sur son avatar, elle sort EN TOTALITÉ (panneau plein
           écran existant #jaspePanel), responsive et lisible en mode sombre.
FAIT :     index.html : badges ajoutés sous la bulle desktop + hero mobile
           converti au traitement JASPE (avatar, bulle, badges, boutons
           data-jaspe-hero-action text/audio). dashboard-liquid-glass.css :
           recadrage buste (object-fit: cover, object-position: top center,
           avatar cliquable), styles badges Liquid Glass + contre-poids mode
           sombre, variante mobile. app.js : câblage des boutons du hero vers
           openJaspePanel() avec bascule du mode Écrit/Audio, clic sur
           l'avatar ouvre aussi la conversation plein écran.
TESTS :    node --check app/app.js OK · git grep confirme badges (desktop
           ligne 420, mobile ligne 568) + boutons data-jaspe-hero-action
           câblés (app.js ligne 2842) + styles CSS ligne 1082.
NON TOUCHÉ: moteur SafeAssistant, jaspe-governance, panneau plein écran
           (existant e26ecef), serveur, SQL, permissions.
RISQUES :  validation visuelle navigateur (port 4176) à refaire : vérifier
           que le buste est bien coupé par le bas du hero et que le recadrage
           « top center » ne coupe pas la tête sur petits écrans ; ajuster
           object-position si nécessaire.
SUIVANT :  étape B — poses dynamiques dans le hero (idle/listening/speaking)
           via le manifeste existant, puis docs + commit + push de ce lot.
```

Dernière mise à jour : 15 septembre 2026 — Lot JASPE P4-ter (chat fixe + 2.5D).

## ÉTAT ACTUEL

Architecture verrouillée le 14/09 : **1 plateforme SchoolSafe = plusieurs écoles isolées** sur un VPS central (Hostinger, Docker + Coolify), SchoolSafe Control co-hébergé mais logiquement séparé (séparation par privilèges, pas de conteneur par école ; isolation multi-écoles = `school_id` + PostgreSQL + ACCESS_LAW). License Contract V2 validé (spec G0). Déploiement officiel : **Git → Coolify → Docker → VPS**. Références visuelles officielles versionnées dans `docs/design/references/`. Règle de conduite : **VISION LARGE, LIVRAISON ÉTROITE**. Références design validées et versionnées (`a6d6b8f`).

```
ÉTAT ACTUEL            Phase A + P2 + P3 + P4 complétés + P4-ter JASPE clôturé
DERNIÈRE ÉTAPE         P4-ter JASPE (commit b809149) : bulle de dialogue
                       AU-DESSUS de la tête, historique borné 30 entrées,
                       sections chat fixes dashboard desktop+mobile, JASPE
                       2.5D réel (attente-v13), sortie totale Échap. QA verts.
                       Commit séparé 5de51f9 : rangée Écosystème + Liquid Glass.
ÉTAPE EN COURS         P4-ter clôturé — prêt pour P5 backup + rejeu base réelle
ORDRE À SUIVRE         P5 backup + rejeu base réelle · P6 extensions
```

### Rapport — Lot P4-ter (JASPE : chat fixe dashboard + bulle + 2.5D)

```text
PRÉVU :    bulle de dialogue AU-DESSUS de la tête de Jaspe quand elle flotte ;
           section chat FIXE du dashboard « comme un ChatGPT » (desktop +
           mobile) ; personnage JASPE 2.5D réel ; sortie dans sa totalité.
FAIT :     safe-assistant.js : placement bulle prioritaire "top" (repli
           latéral si espace insuffisant) ; historique de conversation borné
           30 entrées (Vous/Jaspe) rendu dans la bulle ; mirrorDashboardChats()
           synchronise les sections [data-jaspe-chat] (desktop + mobile) avec
           saisie Enter câblée sur openWithQuery() ; fermeture totale par
           Échap en plus du ✕ et du toggle compagnon ; mountJaspe2D() charge
           le personnage réel attente-v13/adossee-detouree.png (repli libellé
           si image manquante). index.html : section chat Jaspe en tête de la
           colonne droite dashboard + section mobile après les alertes.
           dashboard-liquid-glass.css : styles Liquid Glass modérés (log,
           lignes historique, champ pilule) + contre-poids mode sombre +
           prefers-reduced-motion. Commit séparé préalable 5de51f9 : rangée
           Écosystème SchoolSafe + extension Liquid Glass (travail en attente
           isolé pour ne pas mélanger les lots).
TESTS :    node --check safe-assistant.js OK · qa-safe-assistant-access PASS
           (FE-SEC-A3A4) · qa-no-guardian-screen PASS · test:visual-system
           PASS (contrat responsive + suppression guardian).
COMMIT :   5de51f9 (écosystème + liquid glass) · b809149 (JASPE P4-ter).
NON TOUCHÉ: serveur, SQL, permissions.json, spec G0, DECISIONS.md, modules
           métier — lot frontend pur, API SafeAssistant publique inchangée.
RISQUES :  la bulle au-dessus peut recouvrir du contenu si Jaspe est placée
           tout en haut (le clamp existant préserve la lisibilité) ;
           validation visuelle navigateur sur 4176 à refaire avant push.
```

### Rapport — Lot P3 (enforcement backend licence)

```text
PRÉVU :    hook d'application backend bloquant les routes /native/* quand
           la licence n'est pas active ou en grâce ; error code dédié ;
           tests d'enforcement (blocage, exception CORE, grâce, isolation).
FAIT :     server/src/licensenative/gate.ts (nouveau) : hook Fastify
           onRequest avec cache TTL 60s par schoolId, exclusion explicite
           /native/license et /native/trial (CORE non licenciable), lecture
           état via licenseService.readState. server/src/http/errors.ts :
           ajout ApiErrorCode LICENSE_INACTIVE. server/src/native-app.ts :
           création licenseService conditionnelle + registerLicenseGate
           après buildApp. Tests : server/tests/licensenative-gate.test.ts
           (5 scénarios : blocage inactive, exception license, exception
           trial, autorisation grâce, isolation inter-écoles).
TESTS :    licensenative 15/15 PASS · licensenative-gate 5/5 PASS ·
           typecheck PASS · total 20/20 tests licence verts.
MODIFIÉ :  server/src/http/errors.ts (+1 error code) ·
           server/src/native-app.ts (câblage gate + service) ·
           server/src/licensenative/gate.ts (nouveau) ·
           server/tests/licensenative-gate.test.ts (nouveau).
NON TOUCHÉ: frontend, SQL, permissions.json, spec G0, DECISIONS.md.
RISQUES :  cache TTL 60s = révocation prend effet au plus tard après 60s
           (acceptable V1, documenté) ; HMAC octet-exact reste à durcir
           avant exposition publique (même chantier que upload logo).
```

### Rapport — Lot P2 (isolation inter-écoles)

```text
PRÉVU :    balayage complet anti-fuites, corrections, verrou statique.
FAIT :     sweep de TOUS les database/**/*.sql + requêtes serveur :
           138 requêtes métier analysées, 4 candidats — 2 faux positifs
           justifiés (provision_bridge : le prédicat id = v_school EST
           la frontière), 2 corrigés : sommes de solde (record_payment/
           cancel_payment) désormais filtrées p.school_id = v_school_id
           (manifeste baseline régénéré) ; setup.createAdmin rattaché à
           l'école du flux (closure setupSchoolId) au lieu de « la
           dernière école globale ». Verrou nouveau : sweep dépôt avec
           liste blanche documentée. Surfaces saines confirmées :
           auth-adapter/pool/readiness (hors métier), services natifs
           (gate active-path Phase A).
TESTS :    sweep 1/1 · permissions 3/3 · migrations 9 sets/27 ·
           statiques 52/52 · typecheck · 56/292 tests PASS.
COMMIT :   7a7f9e8.
```

### Rapport — Lot 1, Tâche 7 (gate active-path + clôture Phase A)

```text
PRÉVU :    gate de régression statique + suite critique ciblée + clôture
           documentaire (plan, étapes 1-6).
FAIT :     native-access-contract.test.ts (code exact du plan) — les 5
           services humains ne contiennent aucun businessPool.query< et
           contiennent withRequestContext ; suite critique ciblée 9
           fichiers/37 tests ; batterie complète : permissions 3/3,
           migrations 9 sets/27 units, statiques 52/52 (access, cibles,
           baseline, finance, pedagogy, cards, versions), typecheck,
           56 fichiers/292 tests serveur. BASELINE_REPORT : section
           « Phase A » ajoutée (résumé, bugs trouvés, preuves, limites).
           DECISIONS.md non modifié (aucune décision architecturale
           nouvelle dans ce lot).
TESTS :    tous exécutés, tous verts — voir ci-dessus.
COMMIT :   ce lot (code + docs).
RISQUES :  inchangés et documentés — rejeu base réelle (P5), iam machine,
           HMAC octet-exact, allowScripts Esbuild.
```

### Rapport — Lot 1, Tâche 6 (manifestes + contrats statiques modules)

```text
PRÉVU :    enregistrer finance/pedagogy/cards dans l'intégrité des
           migrations + contrats statiques par module (plan, étapes 1-6).
FAIT :     attente étendue 9 sets (échec constaté) ; 3 générateurs + 3
           manifestes (finance 2, pedagogy 1, cards 2 unités) ; contrôleur
           étendu après access ; compteur 27 (26 plan + unité access 03 de
           la tâche 5 — écart documenté) ; 15 tests de contrats nouveaux ;
           BUG RÉEL trouvé par le contrat school_id et corrigé :
           parent_children_list/parent_student_grades utilisaient
           guardian_profile_id/relationship (colonnes inexistantes —
           profile_id/guardian_type), ce qui aurait planté à l'exécution ;
           contraintes school_id ajoutées ; manifeste pedagogy régénéré.
TESTS :    statiques modules 15/15 · access+cibles+baseline 35/35 ·
           migrations 9 sets/27 units PASS (×2 contrôles) · gate
           permissions 3/3 · typecheck PASS · 55 fichiers/287 tests PASS.
MODIFIÉ :  15 fichiers (3 SQL manifestes + 3 générateurs + 3 tests statiques
           + contrôleur + compteur + 1 SQL pedagogy corrigé).
COMMIT :   b5d24b6.
```

### Rapport — Lot 1, Tâche 5 (autorité machine Control)

```text
PRÉVU :    séparer l'autorité machine Control de l'accès humain (plan,
           étapes 1-6) : callbacks signés HMAC, zéro SQL si non signé,
           jamais de profileId humain fabriqué.
FAIT :     SQL api.set_control_context (unité access 03, manifeste
           régénéré 3 unités, compteur 22) ; db/control-authority.ts
           (vérification HMAC fenêtre 300 s + exécuteur machine) ;
           controlprintnative réorganisé (requêtes des routes déplacées
           dans le service ; liste d'impression ENFIN filtrée par école —
           fuite P2 corrigée au passage) ; nouveau callback machine
           POST /native/control/print/status (401 + zéro SQL si non
           signé) ; controlConfig câblé dans les dépendances de routes ;
           code erreur ACCESS_DENIED (union ApiErrorCode existante).
TESTS :    typecheck PASS · 55 fichiers / 287 tests PASS (+3) · gate
           permissions 3/3 · migrations 6 sets/22 units PASS.
MODIFIÉ :  1 SQL + manifeste, 2 fichiers src controlprintnative, 1 nouveau
           db/control-authority.ts, native-app.ts (câblage), 1 test
           nouveau, compteur de migration.
NON TOUCHÉ: cardsnative (callbacks carte restent côté Control minimal),
           frontend, G0-B (enforcement licence = P3).
COMMIT :   07f0508.
RISQUES :  1) sémantique du contexte machine dans iam.* (les RPC Control
           appellent require_access qui exige un profil) — à valider sur
           base réelle en P5 ; 2) signature HMAC porte sur JSON.stringify
           (re-sérialisation) — vérification octet exact à durcir avant
           exposition publique (même chantier que l'upload logo).
```

### Rapport — Lot 1, Tâche 4 (contextualisation des services natifs)

```text
PRÉVU :    toute requête humaine s'exécute dans withRequestContext
           (plan, étapes 1-8) : students (2 méthodes), finance (14),
           pedagogy (30), cards (méthodes DB) ; routes construisant le
           contexte uniquement depuis la session.
FAIT :     test élève étendu d'abord (ordre exact liste + ROLLBACK
           brouillon refusé) ; 3 fichiers de test nouveaux (paires
           lecture/écriture du plan) ; services réécrits méthode par
           méthode (aucune requête pool directe restante côté humain,
           vérifié par les tests d'ordre transactionnel) ; routes avec
           helper contextFrom(request) — aucun identifiant accepté du
           navigateur ; type de vérification : import manquant
           withRequestContext corrigé.
TESTS :    typecheck PASS · 54 fichiers / 284 tests PASS (était 51/276,
           +8 nouveaux, zéro régression) · gate permissions 3/3 ·
           migrations PASS · native-app + integration PASS.
MODIFIÉ :  7 fichiers src (students/finance/pedagogy/cards services+routes)
           + 4 fichiers de test.
NON TOUCHÉ: controlprintnative (tâche 5), licensenative/sessionnative/
           trialnative (déjà contextualisés), SQL, frontend.
COMMIT :   0ae7ef6.
RISQUES :  getStudentFee lit encore app.student_fees en direct DANS la
           transaction contextualisée (même rôle, comportement conservé —
           à reprendre en RPC dédié dans un lot futur si souhaité).
```

### Rapport — Lot 1, Tâche 3 (cibles exactes des RPC)

```text
PRÉVU :    chaque opération métier fournit student_id/class_id/subject_id/
           contexte campagne à iam.require_access (plan, étapes 3-5).
FAIT :     test statique des cibles écrit d'abord → 3/3 échecs constatés →
           finance (frais/paiements/reçu : résolution élève+classe avant
           autorisation ; scan de contrôle : classe + contexte campagne
           jsonb pour assigned_fee_classes) ; pédagogie (listes filtrées
           par ligne iam.can_access ; écritures avec paire classe+matière
           exacte ; update/publish/delete résolvent la cible depuis la ligne) ;
           projections parent (grade.read + classe résolue, notes publiées
           uniquement) ; student_averages qui n'avait AUCUNE vérification
           est désormais autorisé ; cartes (classe résolue avant
           cards.request.print ; fonctions Control déjà contraintes
           school_id — vérifié, inchangées).
TESTS :    cibles 3/3 · access+baseline 32/32 · gate permissions 3/3 ·
           migrations PASS · typecheck PASS · 276/276 tests serveur.
MODIFIÉ :  4 SQL natifs + 1 test statique nouveau.
NON TOUCHÉ: services TypeScript (tâche 4), frontend, manifestes (ensembles
           non encore enregistrés — tâche 6).
COMMIT :   05e75e9.
RISQUES :  RPC prouvés par contrats statiques ; rejeu sur base réelle
           planifié avant VPS (P5) — inchangé.
```

### Rapport — Lot 1, Tâche 2 (vocabulaire canonique)

```text
PRÉVU :    mapping exact des codes legacy SQL vers le catalogue canonique
           (table du plan) + 4 permissions ajoutées + grants rôles.
FAIT :     test échouant écrit d'abord (doesNotMatch legacy) → échec constaté ;
           33 littéraux remplacés ligne par ligne selon le contexte fonction
           (devoirs→pedagogy.assignment.*, notes→pedagogy.grade.*, affectations
           enseignants→school.structure.manage, palmarès→palmarques.*,
           caisse ouverte/fermée séparées) ; +4 codes au catalogue et au seed
           (64 total) ; admin 60→63, cashier +caisse.open ; cards.print.manage
           à autorité control, non attribuée aux rôles école ; manifestes
           baseline/access régénérés ; compteurs figés mis à jour.
TESTS :    access-static + baseline static 32 pass/0 fail · gate
           check:permissions 3/3 VERT (était 41 littéraux) · migrations
           6 sets/21 PASS · typecheck PASS · 276/276 tests serveur.
MODIFIÉ :  5 SQL natifs, permissions.json, seed, role_templates, 2 tests
           statiques, 2 manifestes.
NON TOUCHÉ: services TypeScript, frontend, seed des données (ordre SQL préservé).
COMMIT :   eace2e2.
RISQUES :  RPC SQL modifiés non rejoués contre une base réelle dans ce lot
           (contrats statiques uniquement) — rejeu prévu avant VPS, lot P5.
```

## CE QUI ÉTAIT PRÉVU

Étape 0 : corriger `DECISIONS.md`, `V2_CHARTER.md`, finaliser la spec G0, mettre à jour le handoff, vérifier l'absence de décisions obsolètes présentées comme actives, vérifier le diff, committer, donner le SHA — avec les 11 corrections du propriétaire et la règle permanente de continuité (ajout, jamais suppression d'historique).

## CE QUI A ÉTÉ FAIT

- `docs/DECISIONS.md` : décision Docker 12/09 marquée **OBSOLÈTE — remplacée** (conservée) ; 15 décisions du 14/09 ajoutées avec statuts (Docker/Coolify + chaîne Git→Coolify→Docker→VPS et ses 10 règles, VPS central multi-écoles, school_id UUID + school_code, identité globale + memberships avec résolution de contexte corrigée, OTP abstrait, PostgreSQL souverain + retrait progressif Supabase, Control minimal, VISION LARGE/LIVRAISON ÉTROITE, priorités P1-P6 avec distinction cœur/Guardian avancé, sauvegarde/restauration testée, dépôt = vérité opérationnelle, License Contract V2, règle de continuité permanente). La répartition Claude/ChatGPT **n'y figure pas** (règle opérationnelle, voir plus bas).
- `app/docs/V2_CHARTER.md` : § Modèle de déploiement remplacé par la formulation validée (VPS central multi-écoles, school_id/school_code, memberships avec résolution automatique ou sélection tenant-aware, serveur seul arbitre du contexte école) ; § Frontières de sécurité : « Supabase » → « PostgreSQL, ses rôles » ; note d'historique ajoutée (rien de supprimé).
- `docs/PROJECT_CONTEXT.md` : puce « sans Docker » marquée OBSOLÈTE (historique conservé).
- Roadmap 13/09 : bannière de statut ajoutée (points Docker et VPS remplacés, lot 12 à traduire en exploitation Git→Coolify→Docker→VPS ; aucune tâche modifiée).
- Spec G0 (`docs/superpowers/specs/2026-09-14-schoolsafe-license-service-entitlements-design.md`) : finalisée et validée — architecture cible, identités (school_id/school_code, memberships), License Contract V2, migration V1, cycle de vie école, stockage R2 par `school_id`, ordre d'exécution Étape 0→6.
- `ops/deployment/README.md` : créé — chaîne de déploiement officielle et les 10 règles opérationnelles (anciennes méthodes marquées REMPLACÉES, pas supprimées).
- `docs/CURRENT_HANDOFF.md` : ce document, historique des lots précédents conservé ci-dessous.

## CE QUI A ÉTÉ MODIFIÉ vs NON MODIFIÉ

Modifié : uniquement des documents (décisions, charte, contexte, roadmap, spec G0, handoff, ops/deployment/README.md). **Aucun code modifié** — signature et vérification de licence en production inchangées, conformément à la consigne.

## TESTS / VÉRIFICATIONS EXÉCUTÉES

- Recherche des contradictions actives : occurrences de « sans Docker », « propre VPS », « 1 école = 1 VPS » toutes traitées (statut obsolète ou bannière).
- `git diff` relu avant commit (documents uniquement, diff sémantique lisible).
- Fins de ligne des deux fichiers serveur pré-existants isolées dans un commit séparé.

## PROBLÈMES RENCONTRÉS

- Aucun bloquant. Deux fichiers serveur (`server/src/financenative/routes.ts`, `server/src/studentsnative/routes.ts`) portaient des changements de fin de ligne antérieurs à l'Étape 0 : isolés dans un commit séparé pour ne pas polluer le diff documentaire.

## DÉCISIONS PRISES DANS CE LOT

Voir les 15 lignes du 14/09 dans `docs/DECISIONS.md` (toutes « Validées »), la spec G0 validée et `ops/deployment/README.md`.

## COMMIT(S)

- COMMIT A (documentation / Étape 0) : `docs(architecture): etape 0 coherence documentaire et contrat g0` — SHA inscrit dans `git log` (résumé en fin de message agent).
- COMMIT B (fins de ligne) : `chore: normalize line endings` — SHA inscrit dans `git log`.

## CE QUI RESTE À FAIRE / PROCHAINE ÉTAPE EXACTE

**Phase A — Tâche 1 : scanner du contrat de permissions** (`scripts/permission-contract.mjs` + tests + gate `check:permissions`), selon le plan canonique `docs/superpowers/plans/2026-09-13-schoolsafe-canonical-access-law.md`. Compatibilité vérifiée : le plan est compatible avec le modèle multi-écoles et le `school_id` UUID — la tâche 1 est un scanner statique de littéraux de permissions (sans rapport avec le tenant) ; les tâches 2-3 ciblent déjà des RPC scopés par école via `iam.require_access`. **Exécuter le plan tel quel, sans le réécrire.**

Ne commencer **aucune** fonctionnalité Écosystème, JASPE, Watch ou Control avancé entre les deux.

## RISQUES / POINTS À SURVEILLER

- Les trois failles prioritaires restent ouvertes : contexte de requête contourné dans 5 services natifs (Phase A), liste d'impression sans filtre `school_id` (P2), licence non appliquée par hook backend (P3).
- Supabase encore présent dans le dépôt (SDK, scripts, tests) : retrait uniquement via inventaire → migration → tests.
- Points résiduels G0 §11 : canal de provisionnement, supervision de l'activation, catalogue permission→service, fournisseur OTP.

## RÈGLE OPÉRATIONNELLE ACTUELLE (répartition des agents)

**Règle de vérification avant travail (ordre permanent, 14/09)** : avant toute
action sur un point, **vérifier d'abord ce qui est déjà fait sur ce point**
(preuve fraîche dans le code/tests, pas une supposition ni un document),
**corriger si nécessaire**, et **travailler ensuite**. Chaque lot/tâche
commence par ce point de vérification, dans l'ordre verrouillé.

```text
Claude :
- frontend ;
- serveur applicatif ;
- backend ;
- corrections du dépôt.

ChatGPT :
- architecture/exploitation VPS ;
- Docker/Coolify ;
- procédures ops ;
- cohérence infrastructure.

Toute modification d'exploitation produisant une configuration, un script
ou une procédure durable doit être répercutée dans le dépôt (dossier ops/).

Les zones de propriété doivent être explicites afin d'éviter que deux
agents modifient simultanément les mêmes fichiers critiques
(app/app.js, app/index.html : propriété exclusive Claude par lot).
```

## TRAÇABILITÉ

> Artifact Server indisponible dans l'environnement d'exécution de ce lot ; traçabilité assurée par les artefacts versionnés du dépôt (Git, DECISIONS.md, spec G0, handoff, historique des commits).

## Source de vérité

- Dépôt : `https://github.com/medygoo/schoolsafe-v` — branche `main`. Vérifier `git status`, `HEAD` vs `origin/main` avant reprise.
- Le serveur local correct sert le dépôt courant sur `http://127.0.0.1:4176/` ; le port `4175` sert une ancienne copie distincte.

---

# Historique — lots précédents (avant le 2026-09-14, conservé)

## Lots 2026-09-13 — fondations visuelles, baseline, JASPE

- **Lot 0A** : catalogue 60 permissions restauré, contrôle de migrations (6 sets/21 unités), hashes SQL LF/CRLF normalisés, `/config.setup_available` token-réel, CI verte (51 fichiers, 276 tests serveur), audit NPM 0 vulnérabilité. Rapport : `docs/BASELINE_REPORT.md`.
- **Lot 1** : contrat `test:visual-system`, tokens `--ss-*`, retrait glassmorphism/halos/`!important`, bandeau démo neutre, dashboards vérifiés 1440/390 px. Plan : `2026-09-13-schoolsafe-responsive-visual-system.md` (tâches 1-2).
- **Contrôleur physique JASPE** : neuf intentions fermées, traduction déterministe v12/WebP, arbitrage priorité, cycle de vie indépendant `.auth-screen`, raccordement connexion par intentions, repli `SPEAKING`.
- **Suppression écran `guardian`** : splash → connexion directe, styles retirés, permissions métier `school.guardian` conservées.
- Vérifications d'alors : `npm run ci` PASS, 57 tests statiques SQL PASS, `test:jaspe-physical` PASS, `qa-safe-assistant-access` PASS, `test:no-guardian-screen` PASS, navigateur 4176 (JASPE v12 visible, intentions observées), 5 profils sans débordement.
- Limites d'alors (toujours valides sauf mention contraire) : postinstall Esbuild à autoriser avant release ; upload logo à durcir (octets/signatures) ; assistant flottant non activé ; voix/GLM non implémentés ; `qa-pwa.cjs` s'arrête sur son ancien scénario hors session.
- Ancienne « Prochaine action » du 13/09 (remplacée par l'ordre P1-P6 du 14/09) : faire valider puis exécuter le plan canonique Access Law, puis la tâche 3 du plan visuel — cette séquence est maintenant intégrée aux étapes du 14/09.
