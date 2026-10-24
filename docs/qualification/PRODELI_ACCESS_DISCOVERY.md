# ProDéli — accès visibles aux droits scolaires

## Statut

Premier correctif UX préparé et testé localement, pas déployé. Le propriétaire demande une interface ProDéli cohérente, des étapes visibles, des boutons faciles à retrouver et une distinction claire entre consulter, préparer et activer. Cette livraison étroite améliore la découverte du gestionnaire des droits ; elle ne prétend pas qualifier toute l'interface ni garantir l'absence de tout bug.

Base : `752f5910a09de30306a131cab26ed7630d81bd82`, branche `feat/presence-scan-qr-20261023`, Pi seul écrivain. Les brouillons dashboard/palmarès/DECISIONS préexistants restent exclus.

## Cause UX reproduite

Le gestionnaire natif des responsabilités existe dans `admin-console.php`, mais l'accueil ProDéli et la ligne d'un administrateur scolaire n'exposaient aucun lien direct vers ses droits. L'utilisateur devait retrouver une console puis un lien secondaire. Les tests RED ont constaté six attentes manquantes, avec dix contrôles de frontière déjà verts.

## Correctif minimal

Deux fichiers runtime seulement :

- `plugins/SchoolSafe_Keycloak/Wizard.php` : dans « Vos établissements », bouton principal **Gérer les droits — Scan et accès**, accompagné du nom de l'école dans son libellé accessible. Lien GET vers `/admin-console.php?access_school={école}#school-native-access`.
- `plugins/SchoolSafe_Keycloak/manage.php` : même bouton sur la ligne d'un administrateur mono-école existant ; le lien présélectionne aussi le compte via `access_staff`. Aucun lien proposé pour son propre compte, un compte central, multi-école, désactivé ou une école inconnue.

Réutilisation des composants et styles existants, sans nouveau formulaire, JavaScript, table ou moteur d'autorisation. Le choix d'école et le changement d'espace scolaire natifs des utilisateurs scolaires sont conservés. ProDéli administre les droits mais n'entre pas dans l'espace opérationnel scolaire. Les contrôles serveur, droits effectifs, confirmation et CSRF du propriétaire de l'attribution restent inchangés. Cliquer ces liens n'accorde aucun droit.

## Preuves exécutées

Dossier privé : `school-scan-20261023/scan-permission-step1/`.

- `discovery-red.log` : 10 PASS / 6 FAIL avant correctif ; absences réelles des boutons dans les fragments PHP.
- `discovery-green.log` : **16 PASS / 0 FAIL** après correctif. Rendu des boucles PHP réelles avec identités, autorisations et données entièrement synthétiques ; aucune base ni session réelle.
- `discovery-browser.json` : **48 PASS**, Chromium réel aux largeurs **320 / 390 / 1280**, fragments PHP et CSS natifs. Vérification visibilité, zone tactile minimale44px, absence de débordement horizontal, accès clavier/focus visible, URL école/compte et absence d'erreur JS. Navigation de destination interceptée sur `schoolsafe.test` : ce n'est PAS un parcours HTTP authentifié ni une preuve du formulaire serveur après navigation.
- Six captures `discovery-{home,account}-{320,390,1280}.png` ; captures390 consultées visuellement. Écrans synthétiques, pas captures du compte LE SAGE.
- `discovery-regression.log` : **62** contrats d'accès Scan, **84** de frontière ProDéli/écoles et **16** de présentation de comptes, tous PASS ; PHP lint des deux sources et du test PASS.
- `node --check app/qa-prodeli-access-discovery.cjs` et `git diff --check` ciblé PASS.

Tests utiles : `scripts/prodeli-access-discovery-contracts.php`, `app/qa-prodeli-access-discovery.cjs`. Aucun pourcentage de couverture instrumenté revendiqué.

## Séparation avec la mise en service réelle

Lecture seule déjà effectuée : LE SAGE est identifié sans ambiguïté, école active, un administrateur mono-école2026, aucun droit Scan. Ce constat explique le refus, mais ne constitue pas une attribution. Aucune session réelle récupérée ou créée, aucun profil modifié et aucun mot de passe lu/réinitialisé.

Le premier essai de recherche LE SAGE a échoué sur connexion SSH avant exécution ; sortie privée conservée. Reprise bornée30s : lecture seule réussie, checkout et image de production inchangés. La tentative de préambule navigateur gstack a expiré avant de fournir un contexte utilisable ; aucun parcours de connexion réel n'est revendiqué. Les contrôles Chromium ci-dessus sont la qualification synthétique du correctif UI seulement.

## Prochaine étape / arrêt

Informer puis attendre le GO suivant. Avant livraison : qualification du parcours complet authentifié sur une fixture de l'image candidate, impact des deux sources, conservation de la production et recette ciblée. Ne pas déployer les brouillons voisins. Le présent correctif n'est pas visible sur le site avant sa livraison autorisée et qualifiée.

L'attribution réelle des droits Scan de LE SAGE, les étapes de création/activation/consultation du profil, la réutilisation des données d'inscription, le portrait, l'habillage du lecteur Scan et les classements restent des étapes distinctes à terminer une après l'autre. Le QR reste sur la carte ; le bouton ouvre un lecteur, pas un générateur de QR.
