# LOT 2 - Rapport des tests navigateur

Preview HTTP effectivement ouverte dans le navigateur Codex : http://127.0.0.1:5187/modules/codex-ui-v1/preview.html. Le parcours tableau de bord vers dossier a aussi ete examine dans ce navigateur. Tests automatises : Chromium fourni par le runtime local, aucune dependance installee.

## Passe UI/UX Pro Max

La passe actuelle applique UI/UX Pro Max au perimetre isole. 6 groupes de controles supplementaires passent : contrastes, lisibilite/controles, navigation clavier mobile, portrait/paysage et texte a 200%, mouvements reduits et JASPE. Voir UI_UX_PRO_MAX_REVIEW.md, UI_UX_PRO_MAX_DESIGN_SYSTEM.md et pro-max-tests/results.json.

## Presentation par fonction

Fiches et formulaires : 9 schemas, 90 variantes de fiches et 5 groupes cibles. Les trois brouillons restent locaux, sans sauvegarde ni transmission. Voir RECORD_PRESENTATION_HANDOFF.md et record-tests/results.json.

Pendant cette passe des contenus interieurs, la suite LOT 2 a ete reexecutee; les derniers ajustements de validation au fil de la saisie et de libelles par schema ont ensuite ete verifies par record-presentation-tests et les controles Pro Max. Les controles de composition Vue generale ont aussi ete reexecutes. Les resultats domain-tests, branch-tests et overview-unified-results restent des preuves des revisions precedentes, pas de nouvelles executions de cette passe.

5 groupes cibles passent sur 23 schemas, avec 230 combinaisons de largeur/theme, puis les parcours parent, les etats de refus et les donnees malveillantes. Les colonnes, identifiants et contrats sont conserves. La suite complete LOT 2, les controles Pro Max et ceux de Vue generale ont aussi ete reexecutes pendant la revision des branches. Voir UI_UX_PRO_MAX_DOMAIN_PRESENTATION.md et domain-tests/results.json.

## Accents et organisation des branches

6 groupes cibles passent : 13 branches, 211 destinations, 130 combinaisons branche/largeur/theme et 78 mesures texte/fond, plus les icones, etats actifs, groupes de commandes, texte agrandi, couleurs forcees et navigation mobile. Voir UI_UX_PRO_MAX_BRANCH_PRESENTATION.md et branch-tests/results.json.

## Revision Vue generale unifiee

Etape 2, composition de reference : 28 combinaisons responsive et 6 groupes cibles passent. Voir OVERVIEW_COMPOSITION_HANDOFF.md et overview-composition-tests/results.json. Les valeurs graphiques proviennent du DTO existant; aucune courbe horaire, activite ou calendrier invente.

La revision actuelle est verifiee par overview-detail-tests.cjs : 20 variantes responsive, groupes par cycle, totaux, onglets et clavier, actualisation, redimensionnement, acces au dossier exact, DTO absent/incoherent. Resultat : PASS. Captures overview-unified-*.png examinees. Voir OVERVIEW_DETAIL_HANDOFF.md.

## Resultats LOT 2 reexecutes

| Groupe | Resultat |
| --- | --- |
| HTTP ouvert, 211 destinations, 13 branches, 15 postes, aucun Palmares | PASS |
| 15 dashboards x 5 largeurs x 2 themes, indicateurs/listes/notifications | PASS |
| 211 boutons -> destination exacte, aucun succes metier fictif | PASS |
| Eleves: recherche, filtres, pagination, dossier et onglets clavier | PASS |
| Retour a la destination precedente et synthese -> dossier exact | PASS |
| Devoirs, notes et preparation: brouillons visuels valides, jamais publies | PASS |
| Cartes: 9 operations propres, selection et limites impression/ZIP | PASS |
| Familles: 5 operations, demande distincte de reactivation | PASS |
| Scanner: quatre modes, aucun QR/camera, frais sans droit de retrait | PASS |
| Six lectures, perimetres, actualisation, historique filtre et detail | PASS |
| Scenarios chargement, vide, erreur, refus et reprise | PASS |
| Ecrans prioritaires x 5 largeurs x 2 themes, menu fixe et retour mobile | PASS |
| Contrats substitues: refus detail famille, projections et session revoquee | PASS |
| Modes live substitues: refus pickup, delegation fees distincte du role | PASS |
| Operation familiale substituee: suspension refusee, demande seule ouverte | PASS |
| Validation donnees, XSS echappe, aucune API reelle ni erreur JS | PASS |
| Version finale rechargee: double ouverture bloquee pendant la requete | PASS |

150 combinaisons dashboards (15 postes x 5 largeurs x 2 themes), 180 combinaisons ecrans prioritaires (18 destinations x 5 largeurs x 2 themes), verification des 211 destinations, plus les parcours clavier, erreurs et autorisations. Largeurs : 320, 390, 768, 1280, 1920; captures principales a 1440. Les tableaux defilent dans leur propre conteneur, pas la page.

La suite complete LOT 2 a ete reexecutee pendant la passe UI/UX Pro Max, apres correction du repliement asynchrone de Dossiers et notifications. Les resultats actuels couvrent aussi la double ouverture. Les 14 groupes LOT 1 et final-check.json sont des preuves historiques conservees, pas des executions supplementaires revendiquees pour cette passe. Les derniers ajustements de contraste du menu et de JASPE ont ete verifies par le controle cible Pro Max.

Erreurs pageerror : 0. Appels natifs Rosario observes dans la preview : 0. Aucun resultat substitue ne constitue un test de production.

## Captures

- [cartes.png](lot2-tests/cartes.png)
- [dashboard-admin.png](lot2-tests/dashboard-admin.png)
- [dashboard-finance.png](lot2-tests/dashboard-finance.png)
- [dashboard-guard.png](lot2-tests/dashboard-guard.png)
- [dashboard-parent.png](lot2-tests/dashboard-parent.png)
- [dashboard-school_head.png](lot2-tests/dashboard-school_head.png)
- [dashboard-teacher.png](lot2-tests/dashboard-teacher.png)
- [dossier-famille.png](lot2-tests/dossier-famille.png)
- [eleves.png](lot2-tests/eleves.png)
- [etat-denied.png](lot2-tests/etat-denied.png)
- [etat-empty.png](lot2-tests/etat-empty.png)
- [etat-error.png](lot2-tests/etat-error.png)
- [familles.png](lot2-tests/familles.png)
- [finance.png](lot2-tests/finance.png)
- [jaspe.png](lot2-tests/jaspe.png)
- [mobile-eleves.png](lot2-tests/mobile-eleves.png)
- [overview-admin-1280.png](lot2-tests/overview-admin-1280.png)
- [overview-admin-390.png](lot2-tests/overview-admin-390.png)
- [overview-school_head-1280.png](lot2-tests/overview-school_head-1280.png)
- [overview-school_head-390.png](lot2-tests/overview-school_head-390.png)
- [overview-unified-admin-1280.png](lot2-tests/overview-unified-admin-1280.png)
- [overview-unified-admin-390.png](lot2-tests/overview-unified-admin-390.png)
- [overview-unified-school_head-1280.png](lot2-tests/overview-unified-school_head-1280.png)
- [overview-unified-school_head-390.png](lot2-tests/overview-unified-school_head-390.png)
- [pedagogie.png](lot2-tests/pedagogie.png)
- [presences.png](lot2-tests/presences.png)
- [scanner.png](lot2-tests/scanner.png)

Les images sont des captures de demonstration. La capture mobile plein-page montre la barre fixe a la position du premier viewport; en usage elle reste au bas de l ecran, pas entre les lignes d un tableau. Les ecrans eleves, cartes, familles, scanner et pedagogie ont ete captures apres ouverture de leur propre surface.

## Reproduction

Depuis le workspace, lancer le serveur local puis :

```powershell
node docs/codex-ui-v1/record-presentation-tests.cjs
node docs/codex-ui-v1/overview-composition-tests.cjs
node docs/codex-ui-v1/overview-detail-tests.cjs
node docs/codex-ui-v1/lot2-browser-tests.cjs
node docs/codex-ui-v1/ui-ux-pro-max-tests.cjs
node docs/codex-ui-v1/domain-presentation-tests.cjs
node docs/codex-ui-v1/branch-presentation-tests.cjs
```

Le chemin du runtime Playwright dans le harness correspond a la machine Codex. Cursor devra adapter uniquement ce chemin si necessaire, puis reproduire les tests sur son integration autorisee. La variable SCHOOLSAFE_PREVIEW_URL permet de choisir une autre adresse locale.

## Non execute / risques restants

Aucun compte authentifie, PostgreSQL, preuve de remise, photo reelle, camera, voix JASPE, paiement, recu officiel, publication, impression ou ZIP teste. Les 87 ouvertures historiques ne sont pas les ecrans metier integres. Les droits individuels inconnus, normalisations de DTO et pagination serveur restent a resoudre par Cursor. Les tests de non-debordement ne constituent pas une certification WCAG exhaustive; lecteur d ecran et appareils physiques non testes.
