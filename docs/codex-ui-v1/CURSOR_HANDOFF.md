# SchoolSafe Codex UI V1 - Passation Cursor

Document historique du premier apercu. Pour le LOT 1 autorise, utiliser LOT1_DELIVERY.md,
LOT1_CURSOR_INTEGRATION.md et LOT1_MANIFEST.json. Les instructions ci-dessous ne
constituent pas une autorisation d'integration et sont remplacees par ces livrables.

## Livraison

Codex a ajoute un paquet visuel autonome, non branche au projet principal :

- `app/modules/codex-ui-v1/codex-ui-v1.js`
- `app/modules/codex-ui-v1/preview.js`
- `app/modules/codex-ui-v1/preview.html`
- `app/styles/codex-ui-v1/codex-ui-v1.css`
- `docs/codex-ui-v1/INVENTAIRE.md`
- `docs/codex-ui-v1/CURSOR_HANDOFF.md`
- `docs/codex-ui-v1/MANIFEST.json`

Ces fichiers ne modifient pas Rosario, le backend, PostgreSQL, les migrations, l'authentification, JASPE, les cartes, le scanner, la recuperation ou le controle financier.

## Composants fournis

`window.SchoolSafeCodexUiV1` expose :

- `button(label, options)`
- `badge(label, tone)`
- `metricCard(item)`
- `subnav(groups)`
- `dashboard(config)`
- `jaspePanel(config)`
- `mount(target, html)`

Le rendu est en HTML/CSS/JavaScript vanilla pour rester coherent avec l'application existante, qui n'utilise ni React ni framework de composants.

## Apercu

Ouvrir :

`app/modules/codex-ui-v1/preview.html`

L'apercu montre trois roles representatifs :

- administrateur;
- enseignant;
- parent.

Les donnees de l'apercu sont fictives et signalees comme prototype visuel. Elles ne doivent pas etre presentees comme raccordees.

## Points de raccordement proposes

Cursor peut integrer progressivement les composants sans remplacer l'existant :

1. Charger `app/styles/codex-ui-v1/codex-ui-v1.css` apres les tokens SchoolSafe.
2. Charger `app/modules/codex-ui-v1/codex-ui-v1.js` apres `lucide.min.js`.
3. Remplacer seulement des fragments de rendu de dashboard par `SchoolSafeCodexUiV1.dashboard(config)`.
4. Construire `config` depuis les donnees deja presentes :
   - `roleCatalog[role]`;
   - `workspaceVisibleBranches`;
   - `SchoolSafeAccess.filterBranches`;
   - APIs reelles des modules quand disponibles.
5. Garder `workspaceNav` comme menu principal et `workspaceContextNav` comme panneau de sous-fonctions.
6. Pour JASPE, utiliser `jaspePanel(config)` uniquement comme habillage visuel autour des controles existants. Ne pas toucher a `jaspe2d`, au routeur de capacites ni a la gouvernance.

## Tableaux de bord harmonises attendus

Le paquet propose une structure commune :

- hero bleu uni, label or, actions claires;
- sous-navigation independante;
- KPI en cartes stables;
- table d'etat avec proprietaire, statut, limite;
- badges success, warning, danger, info;
- adaptation desktop/tablette/mobile.

Mapping suggere :

| Profil | Config a construire | Source existante |
|---|---|---|
| Administrateur | pilotage global, validations, acces, cartes, licence | `roleCatalog.admin`, pilotage API, access console |
| Direction | approbations, presence, alertes, rapports | `roleCatalog.school_head`, `pilotageModule` |
| Enseignant | classes, presences, devoirs, notes, rattrapage | `roleCatalog.teacher`, `pedagogyModule` |
| Parent | enfants, presence, devoirs, frais, recus, personnes autorisees | `roleCatalog.parent`, parent portal, finance status |
| Gardien | scanner, entree, retrait, verification, anomalies | `roleCatalog.guard`, `scanControl`, `securityModule` |
| Services financiers | frais, paiements, controle, caisse, rapports | `roleCatalog.finance`, `financeModule`, `feeControlModule` |

## Limites a respecter

- Ne pas brancher de faux compteur comme donnee reelle.
- Ne pas remplacer `SchoolSafeAccess`.
- Ne pas dedoubler les permissions.
- Ne pas ouvrir au parent des donnees hors `own_children`.
- Ne pas utiliser JASPE comme autorite.
- Ne pas activer les boutons dont le handoff indique `BACKEND_LATER`, `BACKEND_GAP`, `Non disponible` ou `STOP`.

## Problemes non resolus a remonter

- Creation de comptes personnel en production depend d'une identite externe.
- Controleur financier staff : menu scanner non ouvert dans l'interface.
- Impression officielle cartes et lot ZIP bloquent.
- Production cartes toute ecole reste desactivee.
- Camera physique non testee.
- Bulletins et convocations reelles restent a raccorder.
- RH, inventaire, comptabilite legale et publications site restent des surfaces frontend ou futures.

## Verification executee par Codex

- Lecture des documents de continuite imposes.
- Lecture du manifeste snapshot racine.
- Extraction de la navigation et des roles depuis `app/app.js`.
- Creation de fichiers additifs uniquement.

Verification technique a faire apres raccordement Cursor :

- ouvrir l'apercu;
- verifier desktop 1440, tablette 1024, mobile 390;
- lancer les QA existants proportionnes au raccordement;
- ne declarer operationnelles que les routes testees en session reelle.
