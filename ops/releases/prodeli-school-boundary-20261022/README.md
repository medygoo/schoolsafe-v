# Livraison ciblée — frontière ProDéli / écoles, 22/10/2026

GO explicite propriétaire : terminer et sécuriser ProDéli en production avant de traiter le tableau de bord scolaire, sujet séparé. Le propriétaire signale le premier accès résolu ; aucune nouvelle intervention sur son compte ni lecture de secret n’est nécessaire. Ne pas modifier le tableau de bord scolaire ou la notation dans cette livraison.

## Périmètre

Dix fichiers runtime du correctif `2267305`, base de production `8a969d5` / image `a4a3cbf8…`. La recette `deploy.py` réutilise les fonctions déjà livrées de sauvegarde, préservation des sessions/cache, paramètres Docker et contrôle final, sans migration, restauration, test de compte réel ou envoi externe.

- Préparation d’image : `scripts/prodeli-school-boundary-image-qa.py prepare --evidence PRIVATE_DIR` ; source Git canonique uniquement, build sans réseau et image de base vérifiée.
- 114 contrats PHP (nouvelle frontière remplaçant l’ancienne ouverture scolaire globale), 57 contrôles PG natifs/concurrence/rollback et 37 PG récupération/SMS. Migrations de tests canoniques hors web root, dans un schéma isolé supprimé.
- Parcours sur le même ID d’image : 20 groupes natifs/activation/frontière, récupération/SMS et concurrence de reset. Ne pas lancer le vieux parcours workspace qui exige l’entrée des globaux dans les écoles : c’est précisément la politique retirée.
- QA sur réseau Docker interne sans ports publiés ni transport externe. Snapshot fictif étendu au suivi natif, directions, périodes et demandes d’ouverture ; contrôle des conteneurs production avant/après. Retirer uniquement les ressources QA portant le nonce de cette recette.

## Préparation et bascule

Stager les preuves et le manifeste dans `/opt/schoolsafe-releases/prodeli-school-boundary-20261022/` privé. Pousser la source/recette sur `fix/prodeli-school-boundary-20261022`, vérifier le SHA distant, sans merge main/production.

`deploy.py prepare` vérifie le commit/image actuellement actifs, dix hashes de base, configuration et couche mutable, autres services, preuves exactes et sources de recette publiées. Réalise une sauvegarde PG privée 0600 et valide son archive, conserve les dix sources antérieures. `deploy.py deploy` exige cette sauvegarde de moins de 15 minutes, recrée les paramètres Docker, copie sessions/cache après arrêt, conserve le prédécesseur arrêté et n’applique aucune migration. Le checkout de traçabilité devient `deployed/prodeli-school-boundary-20261022`.

`deploy.py status` et une vérification indépendante doivent confirmer l’image saine, dix hashes = Git, moteurs inchangés, configuration/services/sessions/cache conservés et HTTPS public/protection anonyme. Aucun changement de mots de passe réels pendant le déploiement.

## Incident

Pas de rollback ou restauration automatiques. En cas d’échec, conserver les conteneurs, sauvegardes, sessions et preuves ; diagnostiquer avant toute récupération autorisée. Le prédécesseur graphique reste disponible arrêté, mais ne doit pas être redémarré automatiquement : il conserve l’ancienne possibilité d’entrée scolaire globale.

## Sujet suivant, hors de ce lot

Le bug des tableaux de bord/menus concerne les profils d’administrateur scolaire, pas ProDéli. Analyse distincte après clôture de cette livraison ; ne pas profiter de cette bascule pour remanier ces écrans.
