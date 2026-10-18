# SchoolSafe - Branches et organisation interieure

## Regle pour la suite

Le proprietaire demande de faire appel a UI/UX Pro Max pour les travaux visuels. Pour chaque nouvelle tache de conception, d'organisation des ecrans ou de recette UI dans ce lot, consulter le skill local `C:/Users/PC/.codex/skills/ui-ux-pro-max/SKILL.md`, rechercher les recommandations pertinentes et verifier leur adequation. Cela ne donne aucune autorisation de modifier Rosario ou d'integrer dans Cursor.

Les composants et ressources graphiques existants sont conserves. La reference image fournie par le proprietaire guide la hierarchie et la coherence; elle n'est pas une source de donnees metier ni une raison de remplacer le logo ou JASPE.

## Recommandation utilisee

Recherche executee : `python -B C:/Users/PC/.codex/skills/ui-ux-pro-max/scripts/search.py "color alone meaning" --domain ux -n 2`.
Deux correspondances pertinentes, categorie Accessibility : Color Only et Color Contrast. Application : une couleur ne remplace ni le texte, ni l'icone, ni l'etat actif explicite. Verification des contrastes dans les deux themes. La palette ci-dessous est un choix de presentation SchoolSafe, pas une palette scolaire fournie par le moteur de recherche.

## Identite des 13 branches

| Branche | Accent | Theme blanc | Theme bleu / menu sombre |
| --- | --- | --- | --- |
| Pilotage | Bleu | #2455c6 | #91b6ff |
| Ecole | Sarcelle | #08766b | #7bddc5 |
| Pedagogie | Violet | #7445a6 | #d4b5ff |
| Securite et controle | Grenat | #a5324d | #ffa6b8 |
| Finance | Vert | #23682d | #9fd38b |
| Controle des frais | Ocre | #8a5900 | #f2cd7b |
| Comptabilite | Indigo | #4e5990 | #adb6f4 |
| Personnel | Mauve rose | #9c446b | #eab0d1 |
| Stock / Inventaire | Cuivre | #9a4f20 | #efb98e |
| Communication | Bleu cyan | #066e93 | #83d3eb |
| Vie et bien-etre | Rose | #ad3d5b | #ffb8ce |
| Administration | Gris bleute | #526170 | #bfccd6 |
| Controle et rapports | Olive | #5e6c1b | #d1db85 |

Ces accents restent limites aux contours, reperes lateraux et icones. Aucun fond de page multicolore. Le menu principal reste bleu; les surfaces et textes conservent leurs tokens SchoolSafe. Le menu actif utilise un fond clair, un contour marque, son icone et `aria-current`. Les variantes claires/sombres sont mesurees, pas supposees equivalentes.

La couleur suit la branche dans le menu principal, l'en-tete du sous-menu, le fil d'Ariane, les titres, les onglets selectionnes, les acces rapides et les espaces du dashboard. Une fonction liee prend l'accent de sa destination, pas celui de la page courante. Ouvrir un autre sous-menu ne recolore pas le dossier deja affiche : le panneau et le contenu ont chacun leur attribut `data-ui-branch`.

Les couleurs sont decoratives. Elles ne signifient jamais autorisation, presence valide, paiement reussi ou gravite d'incident. Les etats erreur/refus/succes restent distincts et conserves. Les libelles et icones restent indispensables, notamment pour les utilisateurs qui distinguent mal certaines teintes. Le mode couleurs forcees conserve une bordure active explicite.

## Organisation des fonctions

Le catalogue reste a deux niveaux et conserve ses groupes et destinations. Les sous-fonctions gagnent une icone liee a leur schema de consultation; les ouvertures historiques utilisent une fleche et les indisponibilites un indicateur explicite. Les groupes existants sont separes par des lignes legeres, sans nouveau niveau de navigation.

Sur ordinateur/tablette, le sous-menu dispose de son propre defilement borne a la hauteur disponible. Le changement d'ecran conserve le focus sur le contenu mais ramene le titre dans la zone visible, au lieu de laisser le navigateur centrer un conteneur etire par un menu tres long. Une regression ciblee couvre l'ouverture d'une classe apres defilement en bas d'une longue liste.

Les dispositions metier de UI_UX_PRO_MAX_DOMAIN_PRESENTATION.md sont maintenues : dossiers scolaires, fiches de classes, liens familiaux, echeances, documents et registres financiers.

- Cartes : Preparation (individuelle, classe, photographie, recadrage), Production (preview, impression), Suivi des cartes (remplacement, perte, revocation). Les neuf index/codes et leurs routes restent inchanges.
- Familles : Personnes (photographies), Autorisations (lecture, suspension), Demandes (demande de reactivation, etat). Les cinq index/codes restent inchanges; une demande ne devient pas une reactivation.
- Consultation, detail, brouillon et operation ont des surfaces nommees via `data-screen-surface`, avec separation visuelle des formulaires/details. Aucune nouvelle etape metier.
- Les fonctions liees autorisees sont regroupees sous un titre commun; la rubrique est absente quand aucun lien autorise n'existe.
- Les groupes de commandes deviennent une colonne en panneau etroit. Les titres longs se replient sans repousser le bouton d'actualisation hors de sa ligne.

Les 87 ouvertures historiques et les deux fonctions indisponibles ne sont pas transformees en faux ecrans operationnels. La decoration de leurs en-tetes n'est pas un raccordement. Les six lectures, la vue generale unifiee, le detail filles/garcons, les enseignants du jour, la reservation Palmares et JASPE sont conserves.

## Fichiers et raccordement

Fichiers applicatifs de cette revision :
- Nouveau : `app/styles/codex-ui-v1/branch-presentation.css`.
- Ajustes : `app/modules/codex-ui-v1/unified-interface.js`, `lot2-views.js`, `preview.html`.

Charger les styles dans l'ordre : `codex-ui-v1.css`, `lot2-screens.css`, `ui-ux-pro-max.css`, puis `branch-presentation.css`. Ne pas remplacer la feuille commune de l'application finale. La feuille JASPE opt-in reste independante.

`SchoolSafeCodexLot2.iconFor(action)` est un resolveur d'icone de presentation. Il ne lit aucune donnee personnelle et ne fournit aucun droit. Le catalogue, les specifications de DTO, les adaptateurs, les moteurs et les donnees de demonstration ne sont pas modifies.

## Recette

Preuve ciblee : `branch-tests/results.json`, produite par `node docs/codex-ui-v1/branch-presentation-tests.cjs`.
Couverture : 13 identites, 78 mesures de contraste texte/fond (13 x 2 themes x 3 surfaces), contrastes des icones et du menu actif, 211 destinations, groupes cartes/familles, 130 combinaisons branche/largeur/theme, commandes sur petit ecran, texte a 200%, couleurs forcees, clavier mobile et changement de session.

La premiere passe a releve un contraste insuffisant de l'accent pedagogie sur fond selectionne sombre (4.37:1); la variante sombre a ete eclaircie avant le nouveau passage. Seul un resultat PASS est revendique.

Reexecuter aussi `lot2-browser-tests.cjs`, `ui-ux-pro-max-tests.cjs`, `domain-presentation-tests.cjs` et `overview-detail-tests.cjs`, puis `package-lot2.cjs`. Les empreintes du manifeste couvrent les nouveaux fichiers et preuves. Les essais restent sur fixtures/adaptateurs substitues, sans requete native. Aucun appareil physique, lecteur d'ecran ni backend authentifie valide par cette recette.

CURSOR_INTEGRATION=NOT_STARTED. Aucun commit, push ou deploiement.
