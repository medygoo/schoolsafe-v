# Origine des destinations et limites de raccordement

## Constat

Il existe plusieurs couches, non interchangeables :

1. Rosario PHP : session native et Modules.php, menu filtre par profil.
2. SchoolSafe existant : app/app.js openActionByBranch appelle des modules
   et SchoolSafeVisualWiring ; plusieurs clients utilisent des API JSON.
3. Preview Codex : unified-interface.js demande getSession, authorize et
   openExisting. En demonstration ou sans callback, aucun traitement
   metier n'est execute et le message non raccorde est affiche.
4. La vue /schoolsafe-view/ ajoute des liens natifs et lecteurs de totaux.
   Elle ne connecte pas encore les formulaires de la preview Codex.

## Preuves

- app/app.js, openActionByBranch : interception par SchoolSafeVisualWiring
  avant les modules par branche ; Studio Card a son chemin preserve.
- app/modules/core/visual-wiring.js : registre MAP exporte et handlers
  classes, guardians, staff, documents, sync, authorized-persons.
- Le meme fichier appelle les six extensions visual-output avant MAP.
  Une entree gap dans MAP ne prouve donc pas une absence dans l'application.
- app/modules/pedagogy/pedagogy-api.js : listClasses appelle
  /pedagogy/classes ; listTeacherAssignments appelle
  /pedagogy/teacher-assignments ; authHeaders utilise un Bearer provenant
  de schoolsafe-v2-session. La session PHP n'est pas ce token.
- app/modules/codex-ui-v1/unified-interface.js : allowed exige une
  session non-demo et authorize ; openExisting exige le callback fourni.

## Matrice generee

CONNECTION_PROVENANCE_V1.json comporte une ligne par destination, avec
le lien PHP confirme, l'eventuelle entree du registre visuel existant,
sa ligne de source et une reserve explicite sur l'execution metier.
Le script ne lance aucun handler ni API et ne lit aucun secret de session.

Cette matrice ne mesure pas encore les visuels manquants. Pour chaque
ligne non resolue, examiner d'abord les extensions prioritaires, puis
le module, le client API et le moteur. Ne pas transformer automatiquement
les permissions Bearer SchoolSafe en permissions PHP Rosario.

## Ordre de traitement

1. Eleves et personnel : verifier les ecrans existants et leurs contrats
   avant de choisir entre navigation native et reutilisation de formulaire.
2. Pedagogie : distinguer PHP natif et API pedagogie ; cours/affectations
   de test indispensables avant validation des notes et de l'appel.
3. Administration : separer consultation des comptes, ajout de compte,
   droits et parametres ; conserver les controles natifs distincts.
4. Autres branches : examiner les extensions prioritaires puis documenter
   les lacunes reelles. Studio Card, scanner et moteurs financiers intacts.

Aucun raccordement d'API, changement d'authentification ou integration
au code partage n'est effectue par ce rapport.
