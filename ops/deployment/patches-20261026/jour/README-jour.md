# PATCHS DU JOUR — 2026-10-26 (après-midi/soir)

Suite des correctifs de la nuit. Tous appliqués au conteneur `schoolsafe-rosario`
et **re-testés en production** le jour même. Backups originaux : `/root/backups/*orig-*`,
`/root/backups/*.a1-*` sur le VPS.

## Contenu corrigé (par dose, chacune re-testée)

1. **E1+E2 — SQLi** (dose 1) : échappement au point unique `SchoolSafeSaveSchoolIdentitySetting`
   (SchoolReceiptIdentity.fnc.php) + 4 champs identity dans CopySchool.php.
   Re-test : slogan avec apostrophes enregistré exactement puis restauré.
2. **E4+E5+E6 — gardes école/année** (dose 2) : 4 requêtes Calendar.php + contrôle
   d'appartenance avant MarkingPeriodDeleteSQL. Re-test : attaques inter-écoles bloquées
   (calendrier 1 et 7 périodes de l'école 1 survivants), flux légitime intact.
3. **A1 — retrait sans garde école** (AddStudents.php) : miroir du correctif #376 sur le
   DELETE. Re-test : attaque inter-écoles bloquée, retrait légitime OK.
4. **A2 — bandeau parent sélectionné** + **A5 — confirmation nominative** (AddStudents.php).
   Re-test : capture écran + note nominative.
5. **Mes préférences** (Preferences.php + thème SchoolSafeCodex/scripts.js) :
   - explication contextuelle PAR onglet (2 couches : PHP `$ss_tab_explanations` +
     thème `pageHelp` avec bloc tab-aware, imité du cas Email_Log) — 8 onglets testés ;
   - B1 garde profil sur l'onglet widgets ;
   - B2 hidden StudentFieldsSearch + purge des lignes quand tout est décoché
     (ligne témoin purgée) ;
   - B3 validation couleurs #rrggbb + longueur ≤ 100 (script/chaîne 200 car. refusés).
   - B4 : RAS — unicité (username, syear) déjà contrainte en base (`staff_ind4`),
     vérifiée par INSERT doublon rejeté. Ne PAS cibler STAFF_ID (le rollover change
     les STAFF_ID, Rollover.php:561-566 — l'ancien va dans ROLLOVER_ID).

## Fichiers (état corrigé, à copier vers le webroot)

| Fichier ici | Destination webroot |
|---|---|
| AddStudents.php | /var/www/html/modules/Users/AddStudents.php |
| Preferences.php | /var/www/html/modules/Users/Preferences.php |
| Calendar.php | /var/www/html/modules/School_Setup/Calendar.php |
| MarkingPeriods.php | /var/www/html/modules/School_Setup/MarkingPeriods.php |
| CopySchool.php | /var/www/html/modules/School_Setup/CopySchool.php |
| SchoolReceiptIdentity.fnc.php | /var/www/html/modules/School_Setup/includes/SchoolReceiptIdentity.fnc.php |
| theme-SchoolSafeCodex-scripts.js | /var/www/html/assets/themes/SchoolSafeCodex/scripts.js |

Patchers (reproductibles) : patch_dose2.py (École), patch_a1.py + patch_a2_a5.py
(AddStudents), patch_prefs.py (Preferences), patch_theme.py (scripts.js).

## MD5 (fichiers installés au conteneur, vérifiés)

- AddStudents.php : 27521b3a2ab4ff014d5672d1715fa79e
- Preferences.php : 597980cbe03eea7980179ff26e966c59
- Calendar.php : f83311b81f16bd3d6d33a930c739aa50
- MarkingPeriods.php : 7ab923facbd8bdb5f01e0d32706c3c63
- CopySchool.php : 30bdda81e940a7975642e2d12ad2dfb5
- SchoolReceiptIdentity.fnc.php : 602f2ac6895461edaeaf7f325d7e6873
- scripts.js : 5b246a014657cd8d1e750def1c492851

## Restes catalogués (non urgents)

École : E3 (décision propriétaire), E7-E14 🟠, E15-E21 🟡, test E1 complet via prodeli ·
Association : A3/A4 i18n global, rémanence par clé Vue · Mes préférences : B5-B11 🟡,
énumérations B3, légendes par réglage, B8 confirmation mot de passe.
