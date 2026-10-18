# Diagnostic de non-raccordement - SchoolSafe / Rosario

Reference : CODEX_SCHOOLSAFE_NON_RACCORDEMENT_DIAGNOSTIC_V1. Audit du 11 octobre 2026. Statut : READY_FOR_CURSOR_REVIEW.

## Conclusion

Les deux messages viennent du shell Codex, pas d une reponse Rosario. Le mode demonstration bloque volontairement openExisting, et la preview n injecte aucun raccordement metier. Un second probleme est ergonomique : un statut generique et des boutons actifs font paraitre les consultations fictives en panne. Les pages generiques restantes ne satisfont pas la cible finale : chaque fonction autorisee doit disposer d une interaction SchoolSafe adaptee, par reutilisation ou complement visuel, sans modification de Rosario.

## Perimetre et limites

Snapshot declare : cursor/rosario-navigation-integration-v1, HEAD 657aaef34c067173bd44a022d6b83a90010aea89, avec modifications non commitees. Le dossier ne contient pas .git. Aucun dossier original de Cursor, distant Git, compte authentifie, base PostgreSQL ou service de production examine. Les constats du handoff historique ne sont pas presentes comme des essais rejoues.

Les 211 destinations sont celles du catalogue Codex. Elles ne constituent pas toutes les fonctions internes, API ou boutons dynamiques de Rosario. Les sous-commandes sont repertoriees separement. Les contrats API de famille sont des candidats, pas des correspondances validees pour chaque libelle.

## Synthese chiffree

| Mesure | Nombre | Interpretation |
| --- | ---: | --- |
| Destinations | 211 | 13 branches et 15 postes de presentation |
| Avertissement generique potentiel | 203 | Selection type existing en mode demo, deduit du code |
| Consultations specialisees | 116 | Presentation et fixtures; pas 116 workflows complets |
| Pages d ouverture seules | 87 | Pas 87 fonctions absentes de Rosario |
| Lectures GET | 6 | Adaptateur present, transport reel absent dans preview |
| Entrees indisponibles | 2 | Bulletins, blocage explicite du snapshot |
| Permission precise absente du catalogue Codex | 204 | 202 existing et 2 unavailable; aucune conclusion sur IAM Rosario |

Repartition des 87 pages d ouverture : school 6; people 8; pedagogy 11; accounting 13; inventory 8; communication 13; administration 8; reports 8; care 12.

## Constats et corrections proposees

### NR-01 - La demonstration interdit toute ouverture metier

Priorite : P1 - cadrage avant correction. Nature : Limite volontaire confirmee; pas panne Rosario.

**Constat :** preview.js fournit une session demo et l option demo. openExisting arrete le parcours avant tout appel au port. Les deux messages sont produits localement, sans reponse d erreur Rosario.

**Impact :** Un utilisateur peut interpreter la garde de demonstration comme une panne generale. Enlever la garde ou changer demo:false seul ne raccorde rien.

**Action proposee :** Conserver une entree de demonstration distincte de l entree integree. Ne pas charger preview.js/demo-data.js en production. Ne pas supprimer la garde pour faire disparaitre le message.

**Responsable :** Cursor pour le montage integre; Codex pour la presentation separee apres validation

**Critere de validation :** La preview conserve ses donnees fictives et ne provoque aucune mutation. L application integree obtient sa session officielle et n importe aucune fixture.

**Preuves :** `app/modules/codex-ui-v1/preview.js:4` (E01); `app/modules/codex-ui-v1/preview.js:10` (E02); `app/modules/codex-ui-v1/unified-interface.js:83` (E03); `app/modules/codex-ui-v1/unified-interface.js:297` (E05); `docs/codex-ui-v1/preview-server.cjs:8` (E23); `app/modules/codex-ui-v1/preview.html:22` (E33).

### NR-02 - Les ports de raccordement ne sont pas injectes

Priorite : P1 - raccordement frontend. Nature : Adaptateurs absents du montage de preview; existence metier non deduite.

**Constat :** La preview ne fournit pas authorize, openExisting, consult, loadDashboard, read, listAuthorized ou openJaspe. Hors demonstration, les gardes et les loaders distinguent port absent, refus et erreur. Le serveur 5187 sert des fichiers, pas des API.

**Impact :** Passer simplement en mode reel laisserait les droits refuses et les lectures sans transport. Les fonctions historiques ne sont pas appelees par cette entree.

**Action proposee :** Qualifier chaque destination dans le checkout autorise de Cursor. Reutiliser session, transport authentifie, routeur et moteurs deja presents; normaliser uniquement les donnees autorisees. Ne pas inventer des endpoints depuis les libelles du menu.

**Responsable :** Cursor, integration seulement apres validation du proprietaire

**Critere de validation :** Pour chaque action, preuve de la sous-vue exacte ouverte et de la lecture attendue. Un retour opened:true n est pas une preuve de sauvegarde ou d operation reussie.

**Preuves :** `app/modules/codex-ui-v1/preview.js:10` (E02); `app/modules/codex-ui-v1/unified-interface.js:298` (E07); `app/modules/codex-ui-v1/unified-interface.js:91` (E08); `app/modules/codex-ui-v1/lot2-views.js:100` (E09); `app/modules/codex-ui-v1/lot2-views.js:101` (E10); `app/modules/codex-ui-v1/lot2-views.js:86` (E11); `app/modules/codex-ui-v1/unified-interface.js:272` (E12); `app/app.js:3087` (E24); `app/modules/core/visual-wiring.js:432` (E25); `docs/codex-ui-v1/LOT2_CURSOR_HANDOFF.md:55` (E32); `app/modules/codex-ui-v1/read-adapter.js:3` (E34).

### NR-03 - Un statut generique masque plusieurs situations distinctes

Priorite : P1 - clarte utilisateur. Nature : Defaut de presentation confirme.

**Constat :** Le message Interaction visuelle est emis des la selection de chacune des 203 destinations existing en demonstration, independamment du succes de sa consultation fictive. Le second message confond garde demo, port absent et ouverture non confirmee.

**Impact :** Une liste ou une fiche consultable parait en panne; Cursor et le proprietaire ne peuvent pas identifier la cause a partir du message seul.

**Action proposee :** Separer mode de donnees, disponibilite de lecture, disponibilite de l action et resultat de l action. Proposer des causes precises : DEMO_ONLY, ADAPTER_MISSING, TARGET_NOT_OPENED, ACCESS_DENIED, LOAD_ERROR et VERIFIED_GAP. Ces codes sont proposes, non implementes.

**Responsable :** Codex pour les etats visuels valides; Cursor pour les causes effectivement remontees

**Critere de validation :** La consultation fictive reussie n est pas affichee comme echec. Refus, donnees vides, erreur et absence de raccordement restent distincts, sans transformer une erreur en succes.

**Preuves :** `app/modules/codex-ui-v1/unified-interface.js:259` (E04); `app/modules/codex-ui-v1/unified-interface.js:297` (E05); `app/modules/codex-ui-v1/unified-interface.js:301` (E06); `app/modules/codex-ui-v1/lot2-views.js:100` (E09); `app/modules/codex-ui-v1/lot2-views.js:101` (E10).

### NR-04 - Le bouton d ouverture reste actif alors que son issue est bloquee

Priorite : P1 - attente d interaction. Nature : Defaut ergonomique confirme dans la preview.

**Constat :** Ouvrir la fonction existante est propose comme une commande active en demonstration. Le clic sur Eleves aboutit au message Aucune operation effectuee, sans ouverture du module historique.

**Impact :** Le bouton promet une action que cette entree ne peut pas executer. Les formulaires cartes/famille et les modes scanner partagent cette garde.

**Action proposee :** Dans la preview seulement, differencier une interaction locale disponible d une commande metier indisponible et en donner la raison avant le clic. Dans l application cible, livrer une vraie interaction pour chaque fonction autorisee, pas une desactivation permanente ni une simple suppression du bouton.

**Responsable :** Codex presentation apres validation; Cursor raccordement

**Critere de validation :** Chaque bouton visible a une issue comprensible et verifiable. Aucun message de succes, paiement, remise, impression ou publication n est simule.

**Preuves :** `app/modules/codex-ui-v1/unified-interface.js:297` (E05); `app/modules/codex-ui-v1/lot2-views.js:217` (E14); `app/modules/codex-ui-v1/lot2-views.js:228` (E16); `app/modules/codex-ui-v1/lot2-views.js:284` (E18).

### NR-05 - Une page de lancement ou une liste ne remplace pas l ecran de travail

Priorite : P1 - couverture des ecrans. Nature : Parcours incomplets dans la preview, pas absence prouvee de moteurs.

**Constat :** 87 destinations rendent une page generique d ouverture. Les 116 consultations specialisees ne prouvent pas 116 workflows complets. Exemple observe : rosario-152 Creer l eleve affiche la liste des dossiers, sans formulaire de creation, alors que renderCreate existe dans l ecran historique. Import massif est legacy dans Codex, mais renderImport existe deja.

**Impact :** La cible du proprietaire, une interface interactive pour chaque fonction autorisee, n est pas encore atteinte par la preview.

**Action proposee :** Completer la matrice fonction > sous-vue > champs > action > retour. Reutiliser et harmoniser en priorite les ecrans historiques; ne creer une nouvelle presentation que pour un manque etabli. Respecter menu principal fixe et panneau de sous-fonctions independant.

**Responsable :** Cursor qualifie l existant; Codex prepare les complements visuels autorises; Cursor integre

**Critere de validation :** Creer l eleve ouvre un formulaire adapte avec les droits requis; Import massif ouvre son parcours existant. Toutes les autres destinations recoivent une sous-vue appropriee ou un manque documente et arbitre.

**Preuves :** `app/modules/codex-ui-v1/lot2-views.js:228` (E13); `app/modules/codex-ui-v1/lot2-views.js:225` (E15); `app/app.js:3087` (E24); `app/modules/core/visual-wiring.js:432` (E25); `app/modules/core/visual-output-p2a.js:224` (E30); `app/modules/core/visual-output-p2a.js:271` (E31).

### NR-06 - Le catalogue visuel ne definit pas tous les droits precis

Priorite : P1 - prealable de securite. Nature : Resolution d autorisation a effectuer, pas preuve de droits manquants dans Rosario.

**Constat :** 204 destinations ont permission:null dans le catalogue Codex : 202 existing et 2 unavailable. Les six lectures et scan-fees ont un code explicite. Les indices de screen-specs et les postes de demonstration ne sont pas des grants IAM.

**Impact :** Un raccordement par simple nom de role ou branche risquerait un acces trop large. Le code hors demonstration exige authorize et refuse si le contrat est absent.

**Action proposee :** Resoudre chaque commande et chaque sous-operation sur les droits existants : ecole, ressource, enfant/classe affectes, condition, delegation et DENY. Conserver l autorite serveur, sans nouvelle permission implicite ni modification IAM.

**Responsable :** Cursor, en reutilisant les controles existants

**Critere de validation :** Cas autorise/refuse, autre ecole, parent hors enfant, enseignant hors affectation, delegation expiree et revocation en vol testes. Masquer un bouton ne remplace pas le controle serveur.

**Preuves :** `app/modules/codex-ui-v1/unified-interface.js:91` (E08); `docs/codex-ui-v1/LOT2_CURSOR_HANDOFF.md:55` (E32).

### NR-07 - Certaines mentions non raccorde sont inscrites en dur

Priorite : P2 - coherence apres integration. Nature : Texte statique confirme; risque de faux etat apres raccordement.

**Constat :** Le rendu legacy porte Non raccorde dans la preview sans tester le mode; les notes des formulaires portent egalement Non raccorde sans consulter une disponibilite. Ces textes resteraient visibles si l extension etait montee avec un port reel.

**Impact :** Un raccordement correct pourrait encore etre presente comme absent. Un changement de texte seul ne prouverait toutefois aucune operation.

**Action proposee :** Faire dependre les statuts des capacites et resultats effectivement etablis par l integration. Conserver la distinction entre ouverture d un moteur et execution d une mutation.

**Responsable :** Codex presentation puis Cursor validation integree

**Critere de validation :** Tester la meme vue avec demo, port absent, port present, opened:false, opened:true, refus et erreur. Aucun statut metier reel ne depend seulement de la presence d un bouton.

**Preuves :** `app/modules/codex-ui-v1/unified-interface.js:301` (E06); `app/modules/codex-ui-v1/lot2-views.js:228` (E13); `app/modules/codex-ui-v1/lot2-views.js:217` (E14).

### NR-08 - Le bouton generique ne conserve pas le dossier selectionne

Priorite : P2 - contrat de navigation. Nature : Risque constate dans les parametres; intention produit a confirmer.

**Constat :** Le bouton generique appelle openExisting() sans record_id ni onglet. Les formulaires operationnels transmettent des parametres, mais le bouton present dans une fiche peut seulement demander l ouverture du module general.

**Impact :** Si le parcours attendu est de continuer sur le meme eleve ou dossier, le contexte peut etre perdu. L ouverture volontaire du module general n est pas en soi un bug.

**Action proposee :** Preciser pour chaque commande si elle ouvre le module ou le dossier courant. Lorsqu un dossier doit etre conserve, transporter son identifiant canonique avec validation et reautorisation; ne jamais reutiliser un libelle ou un id de demande comme id eleve.

**Responsable :** Cursor pour le contrat; validation produit avant adaptation visuelle

**Critere de validation :** Deux dossiers homonymes ouvrent chacun la bonne ressource, retour conserve, refus apres perte de droit. Les operations cartes/famille gardent leurs confirmations et gardes actuelles.

**Preuves :** `app/modules/codex-ui-v1/unified-interface.js:334` (E17); `app/modules/codex-ui-v1/lot2-views.js:284` (E18); `app/modules/codex-ui-v1/unified-interface.js:298` (E07).

### NR-09 - Les deux entrees Bulletins ont un blocage specifique

Priorite : P1 - qualification distincte. Nature : Blocage explicite dans le snapshot; execution serveur non verifiee.

**Constat :** Le catalogue marque Bulletins et Bulletins a consulter indisponibles. La couche historique P2C affiche REPORT_CARDS=BACKEND_GAP pour sa cible report. Ce constat ne prouve pas l absence de toute fonction bulletin dans Rosario ou dans une revision plus recente.

**Impact :** Raccorder openExisting ne garantit pas un bulletin officiel disponible. Confondre ce cas avec la garde de demonstration ferait conclure trop vite a une correction terminee.

**Action proposee :** Cursor doit verifier la version courante, les ecrans et contrats deja existants. Si un changement du coeur semble indispensable, documenter besoin, solution et risques, puis attendre l autorisation du proprietaire; aucune implementation de moteur dans ce lot.

**Responsable :** Cursor diagnostic; proprietaire pour tout changement de perimetre

**Critere de validation :** Conclure par une preuve d un parcours existant reutilisable ou par un blocage documente. Aucun faux bulletin, aucune publication simulee.

**Preuves :** `app/modules/core/visual-output-p2c.js:767` (E26); `app/modules/core/visual-wiring.js:432` (E25).

### NR-10 - Le palmares ne doit pas etre classe moteur absent

Priorite : P1 - reutilisation du palmares. Nature : Fonction historique presente, correspondance Top 10 ecole a qualifier.

**Constat :** Le catalogue Codex l exclut historiquement du LOT 1; la Vue generale reserve un emplacement. Pourtant P2C expose une cible rankings, un ecran de liste/detail et des routes /native/pedagogy/rankings existent. Leur execution reelle n a pas ete testee ici.

**Impact :** Recreer le calcul de classement serait contraire a la demande de conserver Rosario. Un palmares par periode ou classe n est pas automatiquement le Top 10 de toute l ecole.

**Action proposee :** Verifier le format officiel, la publication, la periode, la portee et la definition du classement demande. Reutiliser les lectures et l ecran existants. Ne pas calculer un classement local a partir d une liste partielle ou comparer des niveaux sans regle validee.

**Responsable :** Cursor qualification des contrats; proprietaire definition du Top 10; Codex presentation

**Critere de validation :** Dix positions au maximum issues de la source autorisee et de regles validees, aucun eleve ou score invente; etats absence de publication/refus/vide distincts.

**Preuves :** `app/modules/core/visual-output-p2c.js:15` (E27); `app/modules/core/visual-output-p2c.js:525` (E28); `server/src/pedagogynative/routes.ts:225` (E29).

### NR-11 - Activites et calendrier ne recoivent pas de donnees

Priorite : P2 - widgets restants. Nature : Sources non raccordees dans la composition Codex.

**Constat :** Les zones Dernieres activites et Calendrier scolaire affichent des textes statiques Non raccorde. L audit ne demontre pas que leurs fonctions sont absentes du systeme historique.

**Impact :** La presentation suggere des rubriques prevues, mais ne fournit ni consultation ni interaction avec leurs sources.

**Action proposee :** Rechercher et qualifier les sources existantes. Un registre de passages n est pas automatiquement un journal de toutes les activites; un emploi du temps n est pas automatiquement un calendrier complet. Definir les liens vers les vrais ecrans et les portees.

**Responsable :** Cursor inventaire des contrats; Codex presentation apres validation

**Critere de validation :** Sources/date/fuseau et autorisations definis; donnees vides et indisponibles distinguees; aucune activite ou evenement fictif en mode reel.

**Preuves :** `app/modules/codex-ui-v1/lot2-views.js:163` (E20); `app/modules/codex-ui-v1/lot2-views.js:86` (E11).

### NR-12 - La fenetre JASPE de preview ne raccorde pas la conversation

Priorite : P2 - assistant distinct. Nature : Demonstration JASPE, non defaut prouve du moteur.

**Constat :** Le dialogue demo affiche une indisponibilite texte/audio. Hors demo, le shell attend openJaspe. Il ne remplace ni la gouvernance ni le moteur existants.

**Impact :** Le personnage visible ne prouve pas qu une conversation ou une voix natives sont branchees.

**Action proposee :** Ouvrir et harmoniser l interface JASPE deja existante via son integration autorisee; ne pas recreer un assistant ou elargir les droits.

**Responsable :** Cursor raccordement; Codex presentation seulement

**Critere de validation :** Conversation/voix qualifiees separement selon leur disponibilite reelle; perte de session et de droits respectee; aucune permission supplementaire accordee a JASPE.

**Preuves :** `app/modules/codex-ui-v1/unified-interface.js:311` (E21); `app/modules/codex-ui-v1/unified-interface.js:337` (E22).

### NR-13 - Verifier un brouillon ne signifie pas l enregistrer

Priorite : P2 - saisie pedagogique. Nature : Limite volontaire a conserver jusqu au raccordement.

**Constat :** Les trois familles de brouillons devoirs/notes/preparations valident les champs localement et affichent explicitement Non sauvegarde et non transmis.

**Impact :** Une validation visuelle peut etre confondue avec un enregistrement si l integration reutilise ce parcours sans definir sa persistance.

**Action proposee :** Pour une vraie saisie, reutiliser les ecrans et contrats pedagogiques existants; distinguer brouillon, sauvegarde et publication, avec confirmations et permissions actuelles. Ne pas connecter une mutation directement au simple controle visuel.

**Responsable :** Cursor pour le workflow existant; Codex pour les etats visuels

**Critere de validation :** Un resultat enregistre est relu depuis sa source autorisee. Un brouillon local demeure annonce comme tel. La publication n est jamais deduite de la validation des champs.

**Preuves :** `app/modules/codex-ui-v1/lot2-views.js:288` (E19); `app/modules/codex-ui-v1/lot2-views.js:225` (E15).

## Reproduction navigateur

- B01: Ecole > Eleves. Observe : Liste Dossiers des eleves, donnees fictives, 24 lignes chargees et message Interaction visuelle. Fonction metier non raccordee.
- B02: Ecole > Eleves > Ouvrir la fonction existante. Observe : La liste reste presente; le statut devient Fonction non raccordee. Aucune operation effectuee.
- B03: Ecole > Inscription > Creer l eleve. Observe : Titre Creer l eleve, mais contenu Dossiers des eleves identique a la liste, sans formulaire de creation; avertissement generique.
- B04: Ecole > Organisation > Import massif. Observe : Page de destination school / Import massif, Etat Non raccorde dans la preview, bouton Ouvrir la fonction existante, sans parcours d import.

Observation dans la preview existante uniquement. Pas de session authentifiee, pas de mutation, pas de preuve de production. Les counts sont derives des catalogues, pas d un clic sur les 211 destinations.

## Transmission

Lire CURSOR_HANDOFF.md puis destinations.csv/json. evidence.json fournit extraits et empreintes; secondary-controls.json couvre les commandes generees. verification.json consigne les controles effectues. Aucune correction applicative n est incluse dans ce dossier.

## Integrite

1017 fichiers sources et 1017 fichiers baseline conformes a TREE.sha256; 17 fichiers applicatifs Codex conformes au manifeste LOT2 precedent. Aucun fichier partage, backend, moteur, SQL ou authentification modifie.

UI/UX Pro Max consulte : recherche locale "disabled action explanation", domaine ux, regle Disabled States. Recommandation retenue : distinguer visuellement les actions indisponibles. Ce n est ni une preuve technique de raccordement, ni une solution finale consistant a desactiver toutes les fonctions.

ROSARIO_NEW_CHANGES=NONE
BUSINESS_INTEGRATION=NOT_STARTED
LIVE_BUSINESS_TESTS=NOT_EXECUTED
CURSOR_TRANSMISSION=PREPARED_NOT_SENT
