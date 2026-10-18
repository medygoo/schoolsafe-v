# Audit et corrections UI/UX Pro Max

## Constats traites

| Priorite | Constat dans les fichiers examines | Correction isolee |
| --- | --- | --- |
| Haute | `lot2-screens.css` et `codex-ui-v1.css` : plusieurs textes a 10/11 px, tailles fixes | Feuille ui-ux-pro-max.css : textes secondaires >=12 px a taille normale, rem, hierarchie reguliere |
| Haute | Bordures de saisie proches du fond ; focus jaune peu visible sur blanc | Tokens de controles/focus par theme, mesures de contraste reproductibles |
| Haute | `unified-interface.js` : menu mobile au-dessus du contenu toujours accessible au clavier | Arriere inert, boucle clavier des menus, Echap et fermeture au changement de poste |
| Haute | L'etat natif du details Dossiers et notifications disparaissait au nouveau rendu asynchrone | Etat de presentation memorise, sans conserver les donnees d'une ancienne session |
| Moyenne | Navigation principale active : hover bleu et texte bleu | Etat actif survole clair avec texte contraste |
| Moyenne | `jaspe-overlay.css` : petits libelles et placeholder natifs, couleurs heterogenes | Typographie, saisie, modes, surfaces et focus opt-in harmonises |
| Moyenne | Etats d'erreur/chargement, espacements et tables variables | Traitement commun des etats, filtres, tableaux, details, pagination et commandes |
| Moyenne | Toutes les consultations partageaient le meme tableau, meme pour des relations familiales ou des seances | Dispositions adaptees aux familles : fiches de classe, relations responsable/enfant, echeances, documents, annuaire et registres chiffres. Voir UI_UX_PRO_MAX_DOMAIN_PRESENTATION.md |
| Moyenne | Branches visuellement identiques et commandes cartes/familles peu regroupees | Treize accents mesures, icones des sous-fonctions, commandes regroupees et fonctions liees. Voir UI_UX_PRO_MAX_BRANCH_PRESENTATION.md |
| Haute | Sous-menu tres long et focus natif sur un contenu etire : titre parfois hors viewport apres navigation | Defilement interne du sous-menu desktop et focus sans saut, titre ramene dans la zone visible ; regression ciblee a 840 px |

## Verification reproductible

Depuis le workspace, serveur de preview deja demarre sur 127.0.0.1:5187 :

```powershell
node docs/codex-ui-v1/lot2-browser-tests.cjs
node docs/codex-ui-v1/overview-detail-tests.cjs
node docs/codex-ui-v1/ui-ux-pro-max-tests.cjs
node docs/codex-ui-v1/domain-presentation-tests.cjs
node docs/codex-ui-v1/branch-presentation-tests.cjs
node docs/codex-ui-v1/package-lot2.cjs
```

Les preuves machine sont dans `lot2-tests/results.json`, `lot2-tests/overview-unified-results.json` et `pro-max-tests/results.json`. Le manifeste final integre ces preuves et les empreintes des fichiers livres.
La revision par fonction ajoute `domain-tests/results.json` et ses captures : 23 schemas a cinq largeurs dans les deux themes, plus la presence par enfant du parent, les colonnes conservees, les dossiers exacts, les filtres et les etats de refus. La revision branches ajoute `branch-tests/results.json`; les suites de non-regression sont rejouees, y compris la vue generale dont les raccourcis portent maintenant les accents de destination.
Le controle Pro Max mesure 26 couples de couleurs semantiques des deux themes, puis les couleurs effectives du menu selectionne survole et du placeholder JASPE. Il verifie libelles, cibles et tailles de texte sur 14 familles de consultations.
Il verifie aussi le clavier mobile, 375x812, 844x390, 768x1024 et 1440x1000, les deux themes, le texte agrandi a 200%, les mouvements reduits, l'image JASPE de preview et le markup JASPE natif avec moteur non execute.
Les captures inspectees visuellement couvrent Vue generale, liste eleves, themes blanc/bleu, telephone et presentation native JASPE. Les captures `pro-max-tests/` correspondent a cette passe. La zone native de personnage peut rester vide dans l'essai de markup seul : aucun moteur n'y est lance.

## Limites de la recette

Tests de presentation et adaptateurs substitues uniquement. Aucune preuve de production, aucune certification WCAG complete, aucun essai avec lecteur d'ecran ou appareil physique. Le test 200% porte sur l'agrandissement du texte via taille racine, pas sur tous les modes de zoom des navigateurs. Les contrastes mesures ne couvrent pas tous les melanges de pixels possibles.
La suite complete LOT 2 a initialement trouve le repliement asynchrone de la section Dossiers ; cette correction fait partie du paquet et le parcours est rejoue apres correction. Toute execution en echec demeure un echec jusqu'au resultat PASS du nouveau passage.
Les blocages fonctionnels de LOT 2 et les 87 ecrans historiques a integrer restent ceux du handoff. CURSOR_INTEGRATION=NOT_STARTED.
