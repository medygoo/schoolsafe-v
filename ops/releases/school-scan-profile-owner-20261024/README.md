# Scan — navigation vers les profils scolaires natifs

Correctif limité à quatre runtime : reader/handoff, relais, page et JSfamille. Source `127d6cd`, image `7bedfc1e…`, digest `ebebf34e…`. Base déployée `d4359b7b…` / recette `ec59446`. Le build cumulatif conserve les65fichiers Scan antérieurs ; le delta réel de cette livraison est3modifications+1ajout. Aucun dashboard/palmarès/notation, migration, attribution réelle, initialisation centrale, activation provider ou worker.

Qualification :372contrats image+37PG recovery ;58HTTP authentifiés fictifs dont8nouveaux, gestionnaire scolaire GET réellement atteint ; suites native/recovery/concurrency sur cette même image. Restitution exacte fixture et absence résidus/production inchangée vérifiées puis nettoyage des conteneurs/réseau possédés. Tests locaux :257PG jetables,19contrats,41Chromeinterceptés+42famille. Ces preuves ne valident pas l'enregistrement de droits dans le gestionnaire ni les choix réels de bénéficiaires.

La recette réutilise les helpers versionnés de préservation `prodeli-recovery-sms-20261022/deploy.py` et `prodeli-native-access-20261021/deploy.py` (copies Git exactes sous recovery.py/preservation.py dans la release privée). Le manifeste/proofs restent privés hors Git. Chaque préparation vérifie source/delta/image/preuves/schema034 existant et prend une sauvegarde PG0600 validée. Aucune restauration automatique. Sessions courantes, cache Chromium, configuration, environnement complet dont HMAC durable, services voisins et ancien conteneur sont conservés. Pas de lecture/rotation du fichier d'escrow de signature.

Après staging contrôlé dans `/opt/schoolsafe-releases/school-scan-profile-owner-20261024/`0700 :

```text
python3 /opt/schoolsafe-releases/school-scan-profile-owner-20261024/deploy.py prepare
python3 /opt/schoolsafe-releases/school-scan-profile-owner-20261024/deploy.py deploy
python3 /opt/schoolsafe-releases/school-scan-profile-owner-20261024/deploy.py status
```

Déployer exige une sauvegarde de moins de15minutes et la même instance saine/source propre. En cas d'erreur après arrêt, diagnostic et reprise explicites : aucune ancienne image lancée automatiquement, aucun dump restauré ou claim relâché. Le worker reste détenu inactif ; aucun test d'enfant/compte réel ou message externe pour vérifier la livraison.

Limite de commissioning maintenue : habilitations et points réels à désigner ; bootstrap ProDéli non réparé par ce correctif, frontière centrale inchangée ; caméra et connexion WhatsApp humaines non prouvées, impression officielle distincte.
