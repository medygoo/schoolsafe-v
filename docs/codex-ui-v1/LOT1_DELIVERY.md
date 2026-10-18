# Livraison LOT 1

Le paquet contient un shell interactif, un catalogue des destinations, un adaptateur de six lectures GET et la presentation opt-in du panneau JASPE existant. Apercu : app/modules/codex-ui-v1/preview.html. Les matrices et instructions sont dans ce dossier.

## Statuts

CODEX_LOT1=GREEN
DASHBOARDS=GREEN
NAVIGATION=GREEN
BUTTON_SCREEN_MAPPING=GREEN
INTERACTIVE_SCREENS=GREEN
JASPE_VISUAL=GREEN
RESPONSIVE=GREEN
ROSARIO_NEW_CHANGES=NONE
CURSOR_INTEGRATION=PENDING

GREEN qualifie le paquet d'interface isole teste avec donnees de demonstration et adaptateurs substitues. Il ne qualifie pas les raccordements de production : ROSARIO_LIVE_E2E=NOT_EXECUTED. Les ecrans historiques demeurent a raccorder par Cursor. Les permissions individuelles non etablies restent refusees tant que son adaptateur ne les resout pas.

## Contenu

13 branches principales fixes, sous-menu independant et centre interactif; mobile Tableau de bord/JASPE/Menu; themes blanc/bleu; 15 postes de presentation; 211 destinations, dont 6 nouvelles lectures et 2 ecrans de bulletin indisponible. Les 203 destinations restantes ouvrent les ecrans existants par un port explicite ou affichent non raccorde. Les doublons de libelle/branche entre postes sont regroupes. Palmares exclu; classes fixes remplacees par une liste autorisee.

Les six lectures sont presentees dans les branches existantes : synthese enseignant, synthese ecole, presence d'un eleve, d'une classe et de l'ecole, trace des controles. Pas de nouveau bouton pour les 48 fonctions SQL internes candidates. Selection des dossiers, actualisation, filtres, pagination et details sont implementes. Les compteurs proviennent du payload; ils ne sont pas recalcules par l'interface.

La priorite des raccourcis depend du poste pour la presentation; les droits reels dependent exclusivement de l'adaptateur de session. Le controleur staff peut obtenir l'acces frais sans etre transforme en role gardien. Le mode fees est transmis au scanner existant, jamais converti en droit de retrait ou d'encaissement.

## Traitement des douze familles

| Famille LOT 0 | Travail d'interface livre | Ce qui reste |
| --- | --- | --- |
| Studio cartes | Destination et message de limite, ouverture adaptee au moteur existant | Impression officielle, ZIP et toute ecole bloques/non verifies |
| Scanner financier staff | Entree feeControl > Scanner les frais, independente du role demo en session reelle | Cursor doit raccorder mode fees et verifier delegation sur vrai compte |
| Bulletins | Ecran indisponible explicite, aucune generation de resultat | Moteur metier absent dans le diagnostic LOT 0 |
| Comptes personnel | Destination personnel/acces conservee et refusee sans raccordement | Creation depend d'identite externe; aucune modification auth |
| Recadrage cartes/portraits | Ouverture vers les surfaces existantes; aucune recreation de recadrage | Essais reels et corrections du moteur hors lot |
| RH/paie | Sous-fonctions et destinations distinctes, limites annoncees | Moteur reel et raccordement a confirmer |
| Comptabilite | Organisation et destinations distinctes, limite legale visible | Aucun moteur SYSCOHADA ajoute |
| Inventaire | Organisation catalogue/mouvements/achats conservee | Operations stock reelles a raccorder |
| Communication | Destinations par canal et etat non raccorde | Contrats individuels et canaux externes a confirmer |
| Rapports generaux | Destinations separees des lectures scanner | Sources officielles/export des rapports historiques a verifier |
| Epreuves certificatives | Destination et limite preparation seulement | Aucun resultat national produit |
| Apercu Codex | 15 postes, boutons actifs, marquage demo uniforme, asset JASPE corrige | Raccordement final de Cursor |

Il s'agit d'un traitement progressif de la presentation et des destinations, pas de la cloture des blocages metier de ces familles.

## Verification

Voir lot1-tests/results.json et les captures desktop-direction.png, desktop-presence.png, tablet-presence.png, mobile-history.png, mobile-jaspe.png et jaspe-existing-*.png. Le script lot1-browser-tests.cjs utilise Chromium fourni par le runtime de Codex, sans installer de dependances dans le snapshot.

Les controles couvrent chaque destination, les six lectures, les listes et filtres, les 15 postes sur six largeurs et deux themes (180 combinaisons dashboard), le menu fixe, le focus des dialogs, le refus sans adaptateur meme pour admin, les erreurs et la reprise, les reponses tardives, la revocation, l'echappement des donnees, l'allowliste GET et le schema exact des compteurs. La presentation du vrai markup JASPE est examinee avec sa CSS existante et la feuille opt-in; son moteur n'est pas execute par ce test.

Les 1017 fichiers de TREE.sha256 ont ete verifies : aucun absent ou modifie. Les seuls fichiers applicatifs modifies sont les quatre fichiers Codex deja crees, auxquels s'ajoutent les nouveaux modules isoles et la feuille JASPE opt-in. Baseline et dossiers Cursor non modifies. Aucun commit/push/merge/deploiement.

## Limites

Pas de validation authentifiee, PostgreSQL, camera ou impression reelle. Les adaptateurs sont des contrats a implementer par Cursor, pas des raccordements pretendument installes. Les autorisations inconnues de l'audit ne sont pas completees par inference. Les noms des 15 postes sont des metadonnees d'interface; ils n'ajoutent aucune identite applicative ou permission Rosario.

Le rapport LOT 0 et ses nombres restent l'etat de reference avant ce travail; ne pas relancer lot0-audit.cjs pour requalifier automatiquement le LOT 1. Utiliser LOT1_MANIFEST.json, LOT1_BUTTON_SCREEN_MAPPING.json et LOT1_CURSOR_INTEGRATION.md pour la livraison actuelle.
