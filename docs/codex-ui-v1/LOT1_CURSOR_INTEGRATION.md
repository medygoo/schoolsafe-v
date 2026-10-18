# LOT 1 - Instructions de raccordement pour Cursor

Reference : CODEX_SCHOOLSAFE_UNIFIED_INTERFACES_LOT1_V1. Integration finale en attente d'analyse et d'autorisation du proprietaire. Ces instructions remplacent le raccordement propose dans l'ancien CURSOR_HANDOFF.md. Aucun fichier partage n'a ete modifie par Codex.

## Fichiers et ordre de chargement

Lire LOT1_MANIFEST.json et comparer chaque fichier aux changements faits apres le snapshot 657aaef34c067173bd44a022d6b83a90010aea89. Les composants du paquet se trouvent seulement dans app/modules/codex-ui-v1 et app/styles/codex-ui-v1. Les rapports LOT 0 restent historiques.

Ordre : Lucide existant, codex-ui-v1.js, catalog.js, read-adapter.js, unified-interface.js. Charger codex-ui-v1.css apres les feuilles partagees. Pour la presentation du vrai panneau JASPE, charger jaspe-overlay.css apres jaspe-dashboard.css et ajouter data-codex-ui-v1 au dialog existant uniquement apres approbation. Aucun moteur, gestionnaire ni attribut de gouvernance ne doit etre remplace.

preview.html fonctionne directement en fichier local et contient uniquement une session de demonstration. Ne pas charger preview.js dans l'application finale. Les fixtures n'ont aucune vocation a servir des donnees reelles.

## Contrat de montage

L'API suivante existe dans le paquet. Les variables bridge ci-dessous sont des ports que Cursor doit implementer a partir des fonctions existantes; elles ne sont pas des API SchoolSafe deja exposees.

```javascript
const ui = SchoolSafeCodexUiV1.mountApplication(container, {
  getSession: bridge.getSession,
  authorize: bridge.authorize,
  listAuthorized: bridge.listAuthorized,
  read: SchoolSafeCodexReads.create(bridge.authenticatedGet),
  openExisting: bridge.openExisting,
  openJaspe: bridge.openJaspe
});
await ui.ready;
```

Ne pas passer demo:true en production. Une session contient identityKey (identite opaque), schoolId, revision (version du contrat de session), role (presentation), title, schoolName et scopeLabel (presentation). Le role n'accorde jamais de droit. La navigation reelle examine toutes les destinations via authorize; elle ne depend pas du modele demo du role. Cela permet notamment a un poste staff possedant une delegation de voir Scanner les frais.

authorize(action, session, params, purpose) retourne exclusivement true ou false; purpose vaut navigation, read ou execute. Le resultat doit venir du contrat valide de session et de ses portees/conditions/refus, y compris les delegations. Les permissions null dans le catalogue sont des correspondances non etablies : refuser jusqu'a resolution precise par Cursor. Ne pas remplacer cette resolution par une permission de branche ou par isAdmin. La verification frontend ne remplace jamais le controle serveur.

listAuthorized(kind, session) fournit [{id, label}], kind student ou class. Fournir uniquement les dossiers autorises, depuis les listes officielles existantes. Aucun school_id arbitraire ne doit etre envoye par le navigateur. Le choix d'un dossier ne constitue pas une autorisation de lire sa fiche.

authenticatedGet(path, {method, signal}) est le transport natif existant, authentifie, retournant {data}. Le read-adapter autorise seulement six noms de lecture et quatre chemins GET, sans mutation ni creation de token. Respecter le signal si le transport le permet. Les parametres student_id/class_id doivent etre des UUID. Les validations des reponses masquent les champs non prevus.

Les six lectures sont teacherBoard, schoolBoard, onSiteStudent, onSiteClass, onSiteSchool et scanAudit. Voir read-adapter.js et LOT1_BUTTON_SCREEN_MAPPING.json. Les compteurs exacts schoolBoard incluent total_students, exited et pickups; teacherBoard utilise total. Aucun calcul de presence n'est refait dans le navigateur.

## Ouvrir les moteurs et ecrans existants

openExisting(route, session, action) ouvre une sous-fonction existante et retourne {opened:true} seulement si son ecran est effectivement ouvert. Cela signifie ouverture, pas succes d'une operation metier. Sinon retourner {opened:false}. Le shell affiche alors Fonction non raccordee, sans faux resultat.

route contient branch et action (libelle exact issu du LOT 0); Scanner les frais contient en plus mode:'fees'. Les fonctions openActionByBranch et openScanControlModule sont internes a app.js : Cursor devra preparer une facade d'ouverture explicite dans son propre lot, sans copier l'ancien app.js. L'appel scanner fees doit utiliser openScanControlModule({mode:'fees'}) ou l'ouverture equivalente du moteur existant, pas un QR ou une decision reconstruits.

L'ordre actuel SchoolSafeVisualWiring.open est P1, P2A, P2B, P2C, ClosureA, ClosureB1 puis MAP. Preserver cet ordre et ses refus. Certaines routes par libelle aboutissent aujourd'hui a une cible generique ou un manque de moteur. Valider la destination et retourner opened:false pour une simple notification ou une absence de module. Ne jamais supposer qu'une fonction retournant undefined signifie ouverture reussie.

Les deux boutons Bulletins sont volontairement des ecrans indisponibles. Le Palmares n'est pas developpe ni expose dans le nouveau catalogue. Les libelles fixes 6e A/5e A sont remplaces par la selection des classes autorisees dans Presence d'une classe. Les 203 autres destinations reutilisent les fonctions existantes via ce port; leurs ecrans metier ne sont pas dupliques.

Le nouveau shell est un point de montage isole. Lorsqu'un ecran historique s'ouvre, Cursor doit choisir un seul proprietaire de la navigation : masquer le shell central ou monter la surface existante dans le conteneur compatible. Ne pas juxtaposer deux menus principaux. Restaurer le shell lors du retour depuis le module. Il faut tester ce cycle dans l'application finale.

## JASPE

openJaspe(trigger, mode, session) retourne {opened:true} seulement si le panneau existant est ouvert. SchoolSafeJaspeDashboard.open(trigger, audioBool) est l'API exposee du moteur existant; verifier la gouvernance et l'ouverture effective du dialog apres l'appel. Le port reçoit text ou audio. Aucun clone de conversation reelle n'est cree.

L'apercu isole affiche une image valide, des modes et un formulaire qui annoncent l'indisponibilite; il ne genere pas de reponse, ne capte pas le micro et ne transmet aucun message. La feuille opt-in change seulement la presentation du panneau existant. Le moteur, les animations et les controles ecrit/audio/voix restent les originaux.

## Session, erreurs et cycle de vie

Appeler ui.refreshSession() lors de toute connexion/deconnexion, changement d'ecole, revision des droits ou delegation. Le shell efface les donnees et les selections, interrompt la requete active et ignore les reponses tardives. Recontroler les droits apres la reponse. Le port authorize doit consulter l'etat courant, jamais une copie figee des permissions. Appeler ui.destroy() avant suppression/remontage.

Les etats chargement, erreur, acces refuse, liste vide et non raccorde sont distincts. Aucune erreur n'est remplacee par un compteur zero fictif. Aucune mutation metier n'est emise par les composants. Les paiements, permissions, cartes et remises gardent leurs confirmations existantes dans leurs ecrans d'origine.

## Validation obligatoire par Cursor

Comparer le paquet a l'etat actuel, examiner les 211 destinations, resoudre les autorisations inconnues, puis demander l'accord du proprietaire avant integration. Apres integration : tester chaque role, les portees propres/classes/ecole/portail, les DENY, les delegations frais expirees/revoquees, la licence, la remise avec preuve/photo, le retour des modules et JASPE. Tester les six lectures sur PostgreSQL reel et les viewports 320, 390, 700, 768, 1024, 1440 dans les deux themes. Produire les rapports pre/post integration du protocole.

Les tests Codex emploient des fixtures et des ports substitues explicites. Ils ne sont pas une validation d'API, de PostgreSQL ou de production.
