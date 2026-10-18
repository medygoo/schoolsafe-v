# Verification scan et recuperation - lecture seule

Sources actuelles examinees dans Pictures/schoolsafe-integration-v1,
sans modification. Verification HTTP locale sans authentification ;
aucun scan, remise, changement de droit ou modification de base.

## Chaine presente

- app/app.js openActionByBranch dirige les actions scan/entree/sortie
  vers openScanControlModule, puis SchoolSafeScanControl.render.
- app/modules/security/scan-control.js appelle GET
  /native/scan-control/capabilities et /locations, puis POST /scan.
- server/src/scancontrol/routes.ts declare ces routes, quatre modes
  entry/pickup/fees/verify et un preHandler requireAuthSession.
- app/modules/school/school-module.js utilise les routes natives family
  pour listes, validation, portraits, suspension et reactivation.
- server/src/familynative/routes.ts declare les routes correspondantes ;
  service.ts appelle les fonctions api.pickup_* de PostgreSQL.
- app/modules/security/security-module.js affiche separement family et
  accredited, les photos et pickup_ready. Une ancienne lecture a un repli
  silencieux si la requete echoue : affichage incomplet possible sans alerte.

## Tests HTTP executes

GET 127.0.0.1:8787/native/scan-control/capabilities : 401 AUTH_REQUIRED.
GET 127.0.0.1:8787/native/family/students/<UUID-fictif>/pickup-authorizations :
401 AUTH_REQUIRED. La route existe et protege la lecture ; aucune
donnee d'enfant n'a ete lue. Ces tests ne valident pas une remise.
GET 127.0.0.1:18082/native/scan-control/capabilities : 404 Apache.

Conclusion : les routes sont sur le serveur SchoolSafe 8787, pas le
serveur PHP Rosario 18082. La vue additive /schoolsafe-view/ ne charge
pas ces modules et n'est pas raccordee a ces fonctions actuellement.

## Session

scan-control.js utilise credentials:include. authnative/middleware.ts
lit le cookie natif du serveur et resolveSession. Il ne traite pas une
session PHP Rosario. Ne pas considerer une connexion PHP comme preuve
d'acces aux API natives. Aucun pont de session valide ici.

## Six personnes

student-record-real.js et studentrecordnative/routes.ts distinguent
pere, mere, tuteur. Les tables et interfaces separent responsables
familiaux et accredites externes.
family/v1/01_pickup_authorizations.sql et
family/v2/01_pickup_person_controls.sql imposent 3 slots actifs aux
accredites externes (generate_series(1,3), PICKUP_LIMIT_REACHED).
Cela confirme le plafond externe de 3, pas a lui seul un plafond global
de 6. La contrainte exacte sur les responsables familiaux et les fonctions
installees dans la base restent a verifier. Aucun resultat fictif de test
de sixieme/septieme personne n'est declare.

## Limites

Chaine de code presente ne signifie pas fonctionnement valide de bout
en bout. Restent a verifier avec session native et base de test : source
API effective, isolation scolaire, photos validees, quotas installes,
refus personne suspendue, confirmation humaine et preuve de remise.
Ne pas substituer Users/AddStudents.php : association du dossier et
autorisation de recuperation sont deux fonctions differentes.

Studio Card, scanner, recuperation et moteurs inchanges.
