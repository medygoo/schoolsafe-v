# Verification de la liaison Appsmith - Rosario

Inspection distante du 2026-10-11, en lecture seule. Aucun compte, source de donnees, fichier distant ou service modifie. Aucun dump des bases internes ni secret consulte.

## Constats

Le README de /opt/prodeli-control indique explicitement : configuration de deploiement Appsmith Community Edition uniquement; aucun business data, datasource ou credential inclus; SchoolSafe, Rosario et Supabase hors perimetre du depot. Il indique egalement que les controles de deploiement n'ont cree aucune source de donnees.

L'inventaire limite des fichiers non secrets du depot ne contient aucun export d'application Appsmith ni adaptateur Rosario identifiable. Cela ne prouve pas l'absence d'applications creees depuis la console et conservees dans le volume Appsmith.

GET /api/v1/users/me sans cookie repond HTTP 200 avec responseMeta.success=true et data.isAnonymous=true. Un HTTP 200 n'est donc pas une preuve d'acces administrateur. Aucune session administrateur Appsmith n'est disponible dans cette verification SSH.

L'inventaire des repertoires Rosario examine ne montre pas de repertoire racine api ni de module dont le nom contient API. Les plugins listes sont Content_Security_Policy et Moodle. Cette recherche limitee ne prouve pas l'absence de toute API ou passerelle externe.

Un relevé des reseaux Docker a echoue sur une erreur de formatage du template; aucune conclusion reseau n'en est tiree.

## Etat

APPSMITH_SERVICE=CONFIRMED
APPSMITH_ADMIN_SESSION=NOT_AVAILABLE
APPSMITH_ROSARIO_DATASOURCE=NOT_VERIFIED
ROSARIO_ACCOUNT_BRIDGE=NOT_VERIFIED
INTEGRATION_CHANGES=NONE
DEPLOYMENT=NONE

## Prochaine action

Ouvrir la console Appsmith via une URL accessible ou une session navigateur autorisee et laisser le proprietaire s'authentifier. Examiner les applications, actions et sources de donnees sans afficher les secrets; alternativement fournir un export expurge des identifiants secrets et des donnees personnelles. Ne pas lire les secrets du volume ni de la base interne pour contourner cette connexion.

Si aucune liaison n'existe, preparer une proposition de passerelle separee utilisant les controles Rosario, tester localement avant autorisation de production. Ne pas connecter directement un formulaire aux tables de comptes pour reproduire ou contourner la logique Rosario.
