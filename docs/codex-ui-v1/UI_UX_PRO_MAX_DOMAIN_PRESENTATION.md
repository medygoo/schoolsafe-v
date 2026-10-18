# UI/UX Pro Max - Presentation par fonction

## Demande et perimetre

Revision de presentation du lot Codex isole : donner une organisation adaptee a chaque famille de fonctions, sans multiplier les styles independants ni modifier Rosario. Les 211 destinations, les permissions, les DTO, les operations et leurs limites restent ceux du LOT 2.

Fichiers applicatifs modifies dans cette revision :
- `app/modules/codex-ui-v1/lot2-views.js` : rendus de consultation par famille, titre avec icone Lucide, conservation des valeurs numeriques nulles (0) dans les registres et leur recherche.
- `app/styles/codex-ui-v1/ui-ux-pro-max.css` : dispositions et hierarchie des informations, adaptation a la largeur effective du panneau via container query.

Aucun nouveau fichier applicatif requis. Les autres composants, les visuels SchoolSafe/JASPE et la navigation fixe a deux niveaux sont conserves. Aucun moteur modifie, aucune dependance installee, aucun branchement metier.

## Usage reel du skill

Skill local consulte : `C:/Users/PC/.codex/skills/ui-ux-pro-max/SKILL.md`.
Les recherches ciblees `school management --domain product -n 2`, puis `education administration --domain product -n 2`, ont retourne zero resultat. Aucun profil produit scolaire trouve ou revendique. Les decisions de cette revision sont une adaptation manuelle des principes generaux du skill et du systeme SchoolSafe deja valide, et non une recommandation issue de ces recherches sans resultat.

La direction data-dense-dashboard verifiee lors de la passe precedente est conservee pour les registres. Identite commune, contrastes, cibles de 44 px, libelles explicites et etats de refus priment sur la decoration. Pas de calendrier fabrique, de photo generique de personne, de classement invente ou d'indicateur financier calcule cote interface.

## Organisation visuelle

| Schema / contexte | Disposition | Informations mises en evidence |
| --- | --- | --- |
| students | Registre scolaire | Identite, classe, matricule en caracteres monospace, etat du dossier |
| classes | Fiches d'affectation | Classe, enseignant et matiere; action de consultation stable |
| family | Lignes de relation | Responsable et lien familial, enfant en regard, etat et consultation |
| attendance, poste parent | Fiches par enfant | Nom, classe, date et presence deja autorises par le meme contrat |
| attendance, autres postes | Registre de presence | Eleve, classe, date numerique, etat fourni |
| subjects | Fiches de matieres | Matiere, classe, coefficient fourni sans recalcul |
| assignments | Liste a echeances | Date limite, devoir, classe/matiere et publication |
| lessons | Liste de preparation | Date, seance, classe/matiere et etat de preparation |
| grades | Registre d'evaluation | Notes alignees a droite et visuellement distinguees |
| campaigns | Liste des campagnes | Echeance, classes autorisees et etat fourni |
| fees / payments / balances | Registres financiers | Montants/soldes a droite, chiffres tabulaires, references et echeances |
| receipts | Liste de pieces | Eleve, reference, date et montant lisibles; aucun recu officiel fabrique |
| documents | Liste documentaire | Titre, dossier concerne, date et etat; icone documentaire |
| staff | Annuaire | Personnel, poste, affectation et etat |
| access | Registre d'acces | Profils, postes et perimetres existants; aucun grant deduit |
| years | Periodes scolaires | Annee/cycle, debut et fin en regard; pas de chronologie reordonnee |
| alerts | File de signalements | Sujet, categorie, date et etat; aucune gravite deduite |
| overview | Fiches d'indicateurs | Valeur fournie, date et source; pas d'agregation ajoutee |
| pickup | Registre de remise | Eleve et personne autorisee visuellement distingues; confirmation serveur toujours requise |
| cash | Registre de caisse | Date, agent/perimetre et etat; aucune ouverture ni cloture |
| cards | Preparation et registre existants | Selection et neuf operations conservees; aucune carte/QR cree |
| scanner | Quatre modes et registre existants | Modes officiels et limites du moteur existant conserves |

Les six lectures gardent leurs surfaces specialisees existantes. Les 87 ouvertures historiques et les deux indisponibilites restent explicites : cette revision ne cree pas leur ecran metier. La vue generale unifiee avec enseignants et reservation du palmares est conservee, ainsi que JASPE.

13 variantes de listes/fiches sont utilisees, en plus du registre. Elles couvrent 12 schemas et une presentation conditionnelle de presence pour le parent. Il ne s'agit pas de 13 nouvelles fonctions. Le schema `children` present dans le fichier de definitions n'est toujours associe a aucune destination; cette revision ne lui invente pas de route.

## Contrats conserves

- Recherche, filtres et pagination locale de huit elements portent sur les memes colonnes autorisees. Le nombre affiche reste celui des lignes chargees, jamais le total presume du serveur.
- Les nouvelles fiches reprennent toutes les colonnes du schema correspondant. L'ordre recu est conserve; aucune date, devise ou priorite interpretee.
- Ouverture par `record_id` exact, details limites a l'onglet autorise, retour, actualisation et controle de session existants.
- Les nouveaux rendus consomment exclusivement les enregistrements projetes par `validateData`; toutes les valeurs sont echappees.
- Les valeurs zero sont conservees et restent recherchables; une valeur absente affiche `Non renseigne`.
- La disposition parent ne donne aucun droit. Elle s'applique apres autorisation au schema de presence existant, avec les seules donnees deja chargees.
- Etats affiches tels que fournis, sans convertir `Autorisation exemple` ou une couleur en decision d'acces. Operations de remise, cartes et finance inchangees.
- Donnees fictives toujours signalees. Aucun appel natif attendu dans les tests de preview.

## Verification et livraison

```powershell
node docs/codex-ui-v1/domain-presentation-tests.cjs
node docs/codex-ui-v1/lot2-browser-tests.cjs
node docs/codex-ui-v1/ui-ux-pro-max-tests.cjs
node docs/codex-ui-v1/package-lot2.cjs
```

Preuve de cette revision : `domain-tests/results.json`. Le script couvre les 23 schemas utilises, la conservation des colonnes, le dossier exact, la recherche, cinq largeurs (320/390/768/1280/1920), deux themes, le perimetre parent, les etats vide/erreur/refus, le texte a 200%, la pagination d'une liste et l'echappement de chaines malveillantes. Les captures sont dans `domain-tests/`.

Les essais restent locaux et substitues. Aucun lecteur d'ecran, appareil physique, paiement, presence reelle, autorisation parent reelle, moteur JASPE ou integration Cursor n'est valide par ces resultats. Les captures plein-page peuvent montrer la barre mobile fixe au milieu de l'image : elles representent le defilement de la page, pas un chevauchement permanent.

Cursor doit reprendre les deux fichiers modifies dans le lot isole et conserver l'ordre de chargement du handoff. Rejouer les parcours sur les veritables DTO et autorisations reste necessaire avant toute declaration operationnelle.
