# Scanner SchoolSafe : intégration et transfert

Les modifications de présentation et de raccordement doivent être livrées depuis les fichiers réels, pas depuis les copies de travail.

## Sources à inclure
- `scanner-native.php`, `scanner-relay.php`, `modules/SchoolSafe/` : session, droits et relais natifs.
- `schoolsafe-card-bridge/scanner-native.js` et ses styles : interface et caméra.
- `schoolsafe-card-bridge/RosarioCardRead.fnc.php` : fournisseur natif du portrait, avec ses contrôles d'accès.
- `ProgramFunctions/SchoolSafeCanteenScan.fnc.php` : contrôle cantine sans débit.
- Le module voisin `card-data-bridge/`, dont `local-http-server.cjs`, `native-scan-routes.cjs` et ses dépendances.
- Les sources du moteur dans `server/src/scancontrol/` et les migrations de cartes, familles, habilitations et contrôles.

## Dépendances d'exécution
L'application PHP et sa base, le moteur Node et ses dépendances compilées, la base isolée des cartes et les correspondances d'identités sont nécessaires. La copie des seuls fichiers PHP ne suffit pas.

Le service de cartes et sa base existants sont relancés en local et configurés en reprise `unless-stopped`. Leurs noms locaux ne constituent pas une configuration de production.

## Secrets
Les signatures, mots de passe et clés restent dans les emplacements privés existants. Aucun secret ne doit être ajouté au dépôt ou à cette documentation. Préparer les secrets du VPS séparément lors d'une mission de déploiement autorisée.

## Portraits
L'identifiant natif est retrouvé côté moteur après résultat admissible, jamais accepté du navigateur. Le fournisseur natif recontrôle le périmètre et le droit de consulter la carte. Sans droit ou sans photo, aucune image de remplacement inventée n'est présentée.

## Conditions avant transfert
- Vérifier les migrations et les versions réellement installées des deux bases.
- Construire et inclure les dépendances du moteur ; ne pas dépendre d'un volume Docker oublié.
- Configurer les lieux, barèmes et habilitations selon l'école.
- Tester les vrais QR, les photos et la caméra en HTTPS.
- Finaliser la présentation graphique approuvée et les tests des cinq modes.

Cette note ne constitue pas une validation de préparation complète au déploiement. Aucun accès au VPS n'est effectué.
