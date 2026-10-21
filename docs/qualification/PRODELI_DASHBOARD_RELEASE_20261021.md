# ProDéli — tableau de bord livré, achèvement fonctionnel encore ouvert

## Livraison du tableau de bord

Mandat explicite : achever les parcours puis livrer les lots vérifiés. Lot livré : entrée unique et tableau de bord sans menu central supplémentaire, sans changement de moteur d'identité ou de communication.

- Source : `38856445143050895b6502c51be18285e9309042`, branche publiée `work/prodeli-completion-20261021`.
- Image : `sha256:9177067d048e865785b57af67571140dcb5cfa6ca688ba9ca8ef9ccc45c15d12`.
- Tag : `schoolsafe-rosario:prodeli-dashboard-388564451430`.
- Source VPS `/opt/app-src`, branche `deployed/prodeli-dashboard-20261021`, SHA source propre au contrôle indépendant.
- Trois fichiers applicatifs changés : `plateforme-prodeli.php`, `plugins/SchoolSafe_Keycloak/Wizard.php`, `plugins/SchoolSafe_Keycloak/central-ui.css` ; **2 754 autres fichiers identiques** à l'image précédente en contenu/mode/propriétaire.

L'entrée GET globale simple `/plateforme/prodeli` rejoint l'accueil SchoolSafe existant. GET paramétrés et POST restent traités par la console native ; la console scolaire n'est pas redirigée. Menu central remplacé par déconnexion, JASPE si autorisé et retour à l'accueil depuis les sous-écrans. Liens vers services SMS quatre réseaux, WhatsApp, réglages courriel et commandes natives conservés. Les pages annexes ne sont pas annoncées toutes réhabillées par ce lot.

## Vérifications réelles

- 12 contrats du tableau de bord (renderer réel, données/permissions substituées), 16 contrats états.
- Cinq cas d'exécution de l'entrée exacte avec bootstrap et handler natif substitués : GET global sans paramètres redirigé, GET scolaire conservé, POST global/scolaire conservés, GET global paramétré conservé. Ce ne sont pas des essais des mutations natives historiques.
- Cinq groupes navigateur/PHP/PostgreSQL fictif, dont 1280/390, retour vers accueil et GET des pages existantes de communication/console ; six groupes régression administrateurs PASS. Aucun test d'envoi ni fournisseur appelé, comptes temporaires nettoyés.
- Image exacte au VPS : 28 contrats PASS, deux lints PHP, réseau `none`, aucune base utilisée.
- Production : PHP sain ; login HTTPS 200 et routes sensibles refusées aux anonymes ; trois empreintes servies exactes. 78 fichiers de session transférés avec contenu/mode/propriétaire vérifiés ; configuration, ports, réseau et montages préservés. Autres services : mêmes identifiants/dates de démarrage.
- Durée de procédure mesurée : 14,42 secondes (ne représente pas une mesure exacte de coupure HTTP).
- Contrôle indépendant : image et checkout attendus, PHP sain, tous services en marche ; CSS public v14 correspond à l'empreinte de release.

Aucune migration ni action sur des comptes réels. Pas de validation connectée de production à la place du propriétaire. Le grand lot local, notamment les correctifs d'époques, n'est pas livré en bloc.

## Retour arrière

Ancien conteneur conservé arrêté : `schoolsafe-rosario-pre-prodeli-dashboard-20261021`, image `b45be4ea…`. Tous les anciens conteneurs de rollback restent conservés.

Commande préparée, non exercée en production :

```sh
python3 /opt/schoolsafe-releases/prodeli-dashboard-20261021/deploy.py --rollback
```

Vérifie les images, refuse les données nouvelles non préservées, transfère les sessions actuelles sans réactiver celles supprimées, restaure la branche PHP précédente. Aucun restore DB. Captures complètes de configuration et archives de sessions restent privées sur le VPS.

## Travaux non terminés — ne pas les annoncer livrés

1. Création native ProDéli : le formulaire existant appelle toujours Keycloak pour préparer le compte ; il ne suffit pas de masquer le fournisseur. Les callbacks de provisioning sont enregistrés par le plugin : le POST final vers `Modules.php` doit être vérifié quand le plugin est désactivé, sans activer implicitement toute son authentification.
2. Rattachement principal : table leadership absente dans la production inspectée ; avant migration additive, sauvegarde adaptée/restauration de preuve et test de compatibilité requis. Transaction compte/rattachement/audit, unicité/reprise et non-rétrogradation d'un principal existant restent à qualifier. Ne pas remplacer l'identité du principal sur un simple ajout d'administrateur.
3. Première connexion native : dépend du mécanisme `FirstLogin.fnc.php` et du réglage `FORCE_PASSWORD_CHANGE_ON_FIRST_LOGIN`; `LAST_LOGIN=NULL` seul n'est pas une preuve d'obligation effective. Ne pas changer globalement cette politique sans analyser les comptes existants.
4. Remise : la fiche existante garde l'ancien lien `sslip.io`, pas encore corrigé par cette release. Son usage unique et sa présentation restent à tester dans le parcours natif futur.
5. Récupération : branche OTP appelle un SMS vide avant émission du code ; correctif à qualifier avec expiration/échec/usage unique et sans journalisation du secret. Test d'une ligne SMS ne force pas encore la ligne sélectionnée ; ne pas attribuer au réseau choisi un test passé sur un autre réseau.
6. Communication physique : quatre lignes désactivées/sans credentials serveur complets à la dernière inspection ; aucun accès physique ou destinataire autorisé de test reçu. Garder la configuration actuelle et ne pas prétendre une réception réelle validée. Courriel et WhatsApp conservés, qualification d'envoi distincte.

La livraison de ce tableau de bord **n'achève pas** les six points. L'autorisation générale est déjà reçue, pas besoin de redemander le même GO ; poursuivre avec les gardes nécessaires et signaler les vrais prérequis externes.

Preuves hors Git : `C:/Users/PC/SchoolSafeProductionUpdate-20261021-evidence/dashboard-completion/` : `prepare.log`, `deployment-proof.json`, `independent-proof.json`, tests et captures. Scripts suivis : `ops/releases/prodeli-dashboard-20261021/`.
