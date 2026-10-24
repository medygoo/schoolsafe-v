# ProDéli — étape 1, diagnostic de l'état des établissements

**Statut : deux écarts reproduits ; correction et production NON effectuées.** Demande propriétaire de stabiliser/sécuriser ProDéli, progression séquentielle. Cette première étape examine une action sensible précise, pas tous les écrans ni tous les droits de la plateforme.

## Cadre et provenance

Pi seul rédacteur, branche `feat/presence-scan-qr-20261023`, départ `37767b2754fd01d804b118d61ce52b2b5ee71607` égal à origin. Sources d'état/console examinées : `ProgramFunctions/SchoolSafeSchoolState.fnc.php`, `admin-console.php`, `database.inc.php`, `functions/DBGet.fnc.php`, migration additive `016-school-state.sql`. Le formulaire console exige POST+jeton de session et administrateur global pour `school_state`; la date est validée par **forme** `AAAA-MM-JJ`, pas par calendrier. Le setter met à jour l'état puis insère l'audit sans transaction. Le `DBQuery` natif appelle `db_show_error()` qui termine la requête sur erreur SQL : le `catch(Throwable)` du setter ne restaure pas une écriture autocommit antérieure.

## Reproduction bornée

`scripts/prodeli-school-state-audit.py --evidence CHEMIN-PRIVÉ-NEUF` utilise une image PHP QA locale et PostgreSQL17.11 dans un réseau Docker **interne**, base neuve sur tmpfs, sans port publié. Marqueurs fixture/nom d'hôte stricts, sources montée lecture seule, utilisateur/actor de test, aucun contact extérieur ni production. Seule la migration016 est exécutée dans la base jetable. Déclencheur synthétique de panne **uniquement sur une ligne d'audit de l'école fictive999002** ; trois écoles999001–003. Une réussite normale et les refus de frontière existants sont témoins. Nettoyage des seuls conteneur/réseau étiquetés confirmé. Il ne s'agit pas d'un HTTP authentifié ou d'une validation de la base réelle.

- **PD-STAB-01 — atomisation manquante.** Avant : école fictive active,0audit. Avec panne d'INSERT audit : requête PHP terminée sur erreur ; après : `suspended`, **0audit**. L'invariant état+audit atomiques est ROUGE. La base fictive a été supprimée, aucune école réelle concernée.
- **PD-STAB-02 — date civile invalide acceptée.** `2026-99-99` passe la regex de la console ; `SchoolSafeSchoolStateSet()` retourne `true` et enregistre `suspended`/`1970-01-01T00:00:00`. L'état effectif observé est **suspended**, et non active : il ne faut pas annoncer une levée immédiate. Le problème prouvé est une date fausse enregistrée avec succès alors que l'utilisateur a demandé une suspension temporaire datée.
- Témoin valide : une suspension ordinaire renvoie true et devient effective. Gardes préexistantes sur PHP vrai avec données substituées : **84 frontière ProDéli +16 présentation comptes +62 droits natifs =162 PASS**. Les quatre sources inspectées correspondent au Git de départ après normalisation des fins de ligne ; aucun patch produit.

Preuves privées : `C:/Users/PC/SchoolSafeProductionUpdate-20261021-evidence/school-scan-20261023/prodeli-stability-step1/r4/result.json`, `audit-failure-observed.json` et journaux des trois suites. Le premier essai r1 était **INCOMPLETE** : le harnais attendait à tort active après la date invalide ; diagnostic conservé dans `r1/diagnosis.md`. r2/r3 ont mis en évidence des prérequis et clés JSON différents des contrats existants, corrigés **dans le harnais seul**, résultats INCOMPLETE conservés. r4 est le seul passage complet ; aucune tentative interrompue n'est renommée PASS.

## Risque et suite proposée, pas encore autorisée comme livraison

Une panne d'audit doit faire échouer et **annuler** le changement d'état ; le contrôleur doit refuser une date civile invalide avant toute écriture et l'API de mutation doit conserver cette même garantie. Distinguer explicitement l'absence historique de migration016 (fonction inerte et active par défaut, décision produit existante) d'une table présente mais illisible ; ne pas modifier l'accès réel sans contrat de compatibilité/tests. Vérifier ensuite `SchoolSafeAccountSet/Revoke` et les autres mutations sensibles par le même critère avant de conclure que **tout** ProDéli est stable.

Étape suivante si GO : tests RED de refus calendaire et rollback audit sur PostgreSQL jetable + tests console, patch au niveau propriétaire natif en réutilisant transaction/verrous Rosario/PostgreSQL, relecture des états/sessions et régressions. Qualification HTTP/image exacte et éventuel déploiement restent des portes séparées. **Pas de permission réelle, compte, session, fournisseur, migration production, restauration, rollback automatique ni déploiement dans cette étape.**
