# Livraison ciblée — administrateur scolaire natif et premier accès

Base immuable : `sha256:c8d10345fc7018098c87bcb56551fc41f253dbe4f1d578a96733382c55afe45b`, source `3eb2e9e35984de9c40e22c571b212e727b13a2bb`. Aucun pull/merge global. Dix chemins runtime seulement, dont deux nouveaux fichiers. `prepare.py` compare **tous** les fichiers de l'image (contenu/mode/propriétaire), exécute 92 contrats sans réseau/base et vérifie neuf lints PHP.

## Qualification obligatoire avant production

- 47 contrôles transactionnels sur PostgreSQL fictif : création/rattachement/audit, profils, identités liées, courses, panne/reprise, expiration/renouvellement, mot de passe personnel et consommation.
- Dix groupes navigateur HTTP/PG fictif : assistant complet, remise une fois, URL officielle, droits, expiration/renouvellement et rejeu, premier accès et garde des routes, autres sessions, école cible, 1280/390.
- 27 groupes navigateur de régression : comptes, accueil, communication, navigation.
- Scope contre la source déployée : 17 composants intégralement identiques et handlers gérés/assistant historique conservés. Aucun changement de fournisseur, SMS/courriel/WhatsApp, récupération ou de plugin global.
- Restauration réelle d'une sauvegarde **fictive locale** dans une base jetable ; 118 tables et nombres de lignes comparés, migrations 018/038 depuis absence rejouées, six tables existantes inchangées, base jetable retirée.
- Rejouer contrôles transactionnels et navigateur sur **l'image exacte**, isolée sur un réseau fictif privé (local, ou conteneurs éphémères VPS sans port publié ni donnée de production), avant `deploy.py`. Le fichier `exact-image-integration.json` doit correspondre à l'ID préparé.

## Séquence

1. Publier le candidat sur la branche de travail, pas main. Préparer un dossier privé `/opt/schoolsafe-releases/prodeli-native-access-20261021` (0700), payload/source manifeste et preuves sans secrets dans Git. Sauvegarder les huit sources existantes ; deux nouveaux chemins doivent être absents.
2. `python3 prepare.py` : base et dérive vérifiées, image `--network none --pull=false`, comparaison exhaustive, 92 contrats et neuf lints. La préparation ne modifie ni conteneur actif ni base.
3. Effectuer la recette sur l'image exacte, dans Docker local ou dans une QA VPS éphémère sur réseau interne, PostgreSQL entièrement fictif, sans port publié, accès navigateur par tunnel SSH loopback. Copier uniquement ses preuves et ID dans le dossier privé de release ; retirer les seuls conteneurs/réseau temporaires étiquetés appartenant à cette recette.
4. `deploy.py` appelle `migrate.py` **avant** d'arrêter le web. Sauvegarde complète PostgreSQL au format custom et lecture de l'archive ; aucun dump de production n'est transféré vers la QA. Migrations additives 018/038, ensemble dans une transaction ; aucune modification de comptes existants ni de politique globale. Les empreintes de six tables protégées sont conservées dans la transaction ; table de premier accès initialement vide.
5. Bascule immuable : mêmes variables/montages/ports/options, transfert exact contenu/mode/propriétaire des sessions, ancien conteneur conservé, contrôles HTTPS/public/droits anonymes et sources. Le cache d'impression existant `/tmp/.config/chromium/` est également copié et contrôlé par empreinte/mode/propriétaire ; aucune autre dérive de fichiers n'est tolérée. Contrat hors ligne : `python3 scripts/prodeli-native-deploy-cache-contracts.py`. Pas de création de compte/école réel ni envoi externe d'essai.
6. Contrôle indépendant puis rapport/handoff. Le propriétaire valide ensuite connecté ; la recette réelle de création ne se fait que sur une école et un destinataire expressément autorisés.

## Retour arrière et préservation

Commande préparée, **ne pas annoncer exercée** :

```text
python3 /opt/schoolsafe-releases/prodeli-native-access-20261021/deploy.py --rollback
```

L'ancien conteneur est `schoolsafe-rosario-pre-prodeli-native-access-20261021`. Les tables additives restent en place ; **aucun DROP ni restauration automatique de base**. Les sessions courantes sont transférées et les anciennes sessions neutralisées.

**Garde supplémentaire obligatoire :** dès qu'un compte a été créé via le nouveau parcours (une ligne existe dans `schoolsafe_native_onboarding`, même complétée), le rollback vers l'ancienne image est **bloqué**, y compris le fallback automatique. L'ancienne image ne possède pas la garde de premier accès ; la rétablir laisserait contourner le changement du mot de passe. Dans ce cas préserver le candidat, sauvegarder et préparer un correctif compatible ou une procédure distincte. Ne jamais effacer ces lignes pour faire passer le garde.

La sécurité CI RED préexistante reste distincte de ces gates. La récupération et les deux défauts SMS restent hors de ce lot ; Keycloak reste en pause.
