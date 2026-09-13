# Baseline de livraison SchoolSafe

Date : 13 septembre 2026
Commit audité : `4b864ef5f96456ec4169c361c991748999602878`
Branche : `main`
Port local de référence : `http://127.0.0.1:4176/`

## Résultat exécutif

Le dépôt est installable et le serveur TypeScript compile, mais la baseline n'est pas encore entièrement verte. Les tests confirment que la majorité des fonctions serveur sont déjà présentes : 49 fichiers de tests passent et 274 tests sur 276 réussissent. Les écarts restants sont concentrés dans le catalogue de permissions, le contrôle des migrations, deux contrats de l'application VPS native, la reproductibilité des manifestes SQL sous Windows et les dépendances signalées par NPM.

Le chantier visuel peut être préparé, mais son exécution doit commencer seulement après le lot 0A de réparation de baseline défini dans la feuille de route.

## Environnement observé

- Système observable : Microsoft Windows NT `10.0.26200.0` ; la lecture détaillée de l'édition Windows a été refusée par l'environnement.
- Node.js : `v24.19.0`.
- NPM : `11.17.0`.
- `npm ci` racine : réussi, 212 paquets ajoutés.
- `npm ci --workspace server` : réussi, 201 paquets ajoutés.
- Serveur local `4176` : HTTP `200`.
- Le port `4175` reste exclu des preuves car il sert une ancienne copie.

## Vérification Git

- Branche locale : `main`.
- `HEAD` avant le lot : `4b864ef5f96456ec4169c361c991748999602878`.
- `origin/main` avant le lot : même identifiant.
- Le dépôt était propre avant les commandes.
- Les manifestes réécrits par les générateurs et le logo temporaire créé par les tests ont été identifiés comme artefacts du lot, puis retirés pour restaurer l'état propre avant documentation.

## Résultats des commandes

| Contrôle | Résultat | Preuve synthétique |
|---|---|---|
| `npm run check:migration-versions` | ÉCHEC | `scripts/check-migration-versions.mjs` est déclaré mais absent. |
| `npm run typecheck` | PASS | TypeScript `tsc --noEmit`, code 0. |
| `npm test` | ÉCHEC | 49 fichiers passent, 2 échouent ; 274 tests passent, 2 échouent ; 1 suite ne démarre pas. |
| `npm run test:jaspe-physical` | PASS | 5 tests réussis, 0 échec ; contrat v12 PASS. |
| `npm run test:no-guardian-screen` | PASS | ancien écran `guardian` absent. |
| Tests statiques SQL ciblés | ÉCHEC | 54 tests passent sur 55 ; le test restant exige `shared/permissions.json`. |
| Vérification directe des empreintes | ÉCHEC PARTIEL | `baseline` passe ; `auth`, `access`, `license`, `trial` et `projections` divergent. |
| `npm audit --json` | ÉCHEC | 4 vulnérabilités : 3 modérées, 1 élevée. |

## Écarts bloquants confirmés

### B0-1 — Catalogue de permissions absent

`server/src/access/permission-catalog.ts` et `database/baseline/v1/tests/static-contract.test.mjs` lisent `shared/permissions.json`, mais le dossier/fichier n'existe pas dans le dépôt.

Conséquences observées :

- `server/tests/permission-catalog.test.ts` ne démarre pas ;
- le contrôle du référentiel canonique des 60 permissions échoue ;
- les consommateurs du catalogue ne disposent pas d'une source partagée versionnée.

### B0-2 — Contrôle de versions de migrations absent

Le script NPM `check:migration-versions` appelle `scripts/check-migration-versions.mjs`, absent du dépôt. La commande critique de CI échoue avant toute comparaison.

### B0-3 — Deux contrats VPS natifs divergent

`server/tests/native-app.test.ts` signale :

1. `/config` renvoie `setup_available: true`, tandis que le test attend `false` ;
2. `/session/bootstrap` et `/auth/lookup-phone` répondent `400`, tandis que le contrat attendu exige leur absence en `404`.

La correction doit déterminer le contrat produit voulu à partir de l'application native, puis aligner code et tests sans réactiver l'ancien parcours Supabase.

### B0-4 — Manifestes SQL non reproductibles sur le poste Windows

Les générateurs recalculent correctement leurs fichiers, mais les octets du working tree Windows produisent des SHA-256 différents des manifestes suivis pour :

- `auth` : 2 unités divergentes ;
- `access` : 2 unités divergentes ;
- `license` : 1 unité divergente ;
- `trial` : 1 unité divergente ;
- `projections` : 2 unités divergentes.

`baseline` est reproductible. La cause probable est la conversion de fins de ligne : les générateurs hachent les octets du working tree et Git annonce des conversions LF/CRLF. Le correctif doit rendre le hachage identique sur Windows et Linux, puis ajouter une vérification non destructive.

### B0-5 — Artefact de test non nettoyé

La suite serveur a créé `server/server/uploads/logos/799001df-4a95-4597-8d10-ac65611b42ab.png` sans le supprimer. Le fichier a été retiré après vérification. Le test ou le service de test doit utiliser un répertoire temporaire et garantir son nettoyage.

### B0-6 — Vulnérabilités de dépendances

L'audit NPM signale :

- `fast-uri` : élevée, dépendance transitive, avis de confusion d'hôte/SSRF ;
- `fastify` `5.12.0` : modérée, correctif non majeur disponible en `5.12.4` ;
- `vitest` et `@vitest/mocker` : modérées, correctif proposé par NPM en version majeure `5.0.0`.

Aucun `npm audit fix` ni `--force` n'a été exécuté. Les mises à jour doivent être faites explicitement avec typecheck et tests frais.

### B0-7 — Scripts d'installation en attente d'approbation

NPM avertit que les scripts postinstall de `esbuild@0.28.2` et `esbuild@0.25.12` ne sont pas couverts par `allowScripts`. L'installation et le typecheck réussissent, mais la politique doit être décidée explicitement avant la release.

## Inventaire démonstration/réel

La recherche `demo|placeholder|BACKEND_LATER`, hors fichiers minifiés et assets, produit 1 111 correspondances dans 68 fichiers.

### Démonstrations autorisées et clairement annoncées

- 15 modules nommés `*-demo.js` pour école, élèves, parent, enseignant, gardien, administration, communication, RH, inventaire et comptabilité ;
- sélection volontaire du rôle de démonstration dans `app/app.js` et `app/index.html` ;
- 17 scripts QA qui vérifient précisément que la démo ne se présente pas comme officielle ;
- données fictives, documentation d'étape et page `test-card.html`.

Ces éléments peuvent rester pendant le raccordement, à condition que le mode réel n'utilise jamais leurs mutations locales.

### Interfaces à raccorder aux API réelles

- Lot 4 : structure scolaire et administration ;
- Lot 5 : cycle élève, famille/tuteurs, dossier et préparation de carte ;
- Lot 6 : scanner, présences, récupérations, incidents et portail gardien ;
- Lot 7 : affectation des frais, caisse, reçus, rapports et comptabilité ;
- Lot 8 : devoirs, notes, remédiation, bulletins et palmarès ;
- Lot 9 : messages, notifications et documents ;
- Lot 10 : assistant JASPE de l'espace de travail.

Les marqueurs `BACKEND_LATER` rendent honnêtement ces limites visibles aujourd'hui ; ils doivent disparaître parcours par parcours uniquement lorsque l'API correspondante est réellement utilisée.

### Occurrences normales, non bloquantes

- attributs HTML `placeholder` des champs ;
- classes CSS telles que `chart-placeholder` ;
- méthodes abstraites de `document-engine/render-context.js` qui lèvent volontairement `not implemented` ;
- rectangle de remplacement si une image PDF ne peut pas être chargée ;
- fixtures et adaptateurs locaux utilisés exclusivement par les tests.

### Dette fonctionnelle explicite

- `app/modules/document-engine/frontend-renderer.js` produit actuellement un contenu de type CSV présenté comme un placeholder XLSX ; une release ne doit jamais fournir ce contenu avec l'extension `.xlsx`.
- plusieurs surfaces sécurité, finance, pédagogie et communication restent frontend uniquement malgré l'existence de services serveur partiels.

## Lot 0A obligatoire avant le design

Ordre de réparation :

1. restaurer le catalogue canonique `shared/permissions.json` et faire passer ses deux consommateurs ;
2. créer le contrôle non destructif `scripts/check-migration-versions.mjs` et stabiliser les fins de ligne/hachages ;
3. aligner les trois assertions VPS natives sur le contrat produit validé sans ancien auth Supabase ;
4. isoler et nettoyer l'upload temporaire du test ;
5. mettre à jour Fastify/fast-uri sans version majeure, puis traiter Vitest séparément si la migration 5 reste compatible ;
6. relancer typecheck, suite serveur, tests statiques SQL, JASPE et absence de `guardian`.

Le lot 1 visuel commence seulement lorsque la baseline repasse au vert ou lorsqu'une limite explicitement non bloquante est consignée et acceptée.
