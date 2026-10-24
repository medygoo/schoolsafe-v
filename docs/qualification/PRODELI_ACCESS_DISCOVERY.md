# ProDéli — accès visibles aux droits scolaires

## Statut

Premier correctif UX qualifié localement ET sur image exacte avec HTTP authentifié synthétique, pas déployé. Le propriétaire demande une interface ProDéli cohérente, des étapes visibles, des boutons faciles à retrouver et une distinction claire entre consulter, préparer et activer. Cette livraison étroite améliore la découverte du gestionnaire des droits ; elle ne prétend pas qualifier toute l'interface ni garantir l'absence de tout bug.

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

## Qualification image / HTTP — terminée après CONTINUE

Source candidate initiale `cc6c4d3a4bb27a63777f683b5f8dc2ced2b516bc`. Source image finale **`b6f588da5f4c79875f9abaca0e1ec0e15f02fc2e`**, image **`sha256:1df5f8578805868544f1a7a73d6a1d437c5be0bf0ecf52cd716b41455e35aea8`**, digest **`e41c8515d0b7c5161685b192333ca33b5c0110c853c38a3e58e0e656ffaa0ea6`**. Wrapper limité aux deux sources sur base production1b3843de ; moteur nonce/fixture/nettoyage réutilisé, pas nouveau moteur. Source du test HTTP final **`6ffde525b4d1bd3ea57ead0a8049a5256f77a8f0`**, SHA256 `a52232082dbf7f28c0777ce4e1e212d2e11a78892f84bd9f20c36eca789bb4d3` ; les deux sources runtime restent identiques à celles de l'image.

Dans `prodeli-discovery-image-r2` :
- Image exacte : **178 contrats de rendu / permissions et37 recovery PostgreSQL PASS**, delta exact **2 fichiers**, **2800 fichiers inchangés**, réseau interne / web readonly / aucun port publié. Les14migrations Scan déjà livrées sont appliquées uniquement dans le clone synthétique pour rendre sa base compatible ; aucune migration production.
- **55 contrôles HTTP authentifiés PASS** via les vrais contrôleurs natifs : connexion de comptes synthétiques, boutons accueil et ligne de compte, formulaire exact école/compte, refus Scan avant bootstrap puis ouverture200 après confirmation, CSRF/confirmation/forme périmée, délégations à trois personnes, retrait immédiat en session ouverte, contrôle des profils partagés, audits atomiques et interdiction scolaire ProDéli avant/après attribution.
- Premier essai HTTP arrêté à la précondition d'accès ProDéli : le profil central synthétique minimal ne contenait pas `Users/User.php`. Correction du HARNAIS : profil central dédié avec lecture native explicite ; profil scolaire de base inchangé. Un contrôle supplémentaire vérifie ce prérequis, soit55contrôles au lieu des54prévus. Aucun contournement de la garde ni droit réel ajouté. Échec et succès conservés dans `access-authenticated-http-discovery-{r1,r2}.private.json`.
- Vérification après échec puis après réussite : hashes des tables fixture identiques, aucun compte/schéma de test résiduel, production inchangée. Nettoyage des ressources possédées PASS.
- `final-provenance-and-cleanup.json` PASS : images/labels/digest et sources Git exactes, code de test identifié, conteneurs/réseaux des deux nonces5c16fb151f07/c6a6e3a8796f absents, checkout production44e246f propre et image1b3843de saine inchangés.

Les48contrôles Chromium sont ceux des fragments synthétiques locaux ; l'image candidate a reçu les55contrôles HTTP ci-dessus, pas une nouvelle suite navigateur authentifiée complète. Aucune caméra physique, connexion fournisseur ou impression officielle qualifiée.

Premier essai `prodeli-discovery-image-r1` : échec de harnais avant création des conteneurs/réseau de test. BuildKit interprète `FROM sha256:<id>` comme dépôt de registre ; ce n'est pas une panne produit. Diagnostic conservé dans remote-error.private.log. Le tag local `schoolsafe-rosario:recovery-qa-c1ed80141175` a été vérifié pour l'image1b3843de, puis le wrapper a été corrigé pour le réutiliser (l'assertion d'égalité exacte tag/image est conservée). Ne pas contourner la protection de l'image de base ou inclure d'autres sources.

## Prochaine étape / arrêt

Qualification image / HTTP terminée : informer puis attendre le GO suivant. Avant livraison : préparer une recette ciblée aux deux sources, vérifier les conditions de conservation/sauvegarde et effectuer un contrôle indépendant après bascule autorisée. Ne pas déployer les brouillons voisins. Le présent correctif n'est pas visible sur le site avant sa livraison autorisée et qualifiée.

L'attribution réelle des droits Scan de LE SAGE, les étapes de création/activation/consultation du profil, la réutilisation des données d'inscription, le portrait, l'habillage du lecteur Scan et les classements restent des étapes distinctes à terminer une après l'autre. Le QR reste sur la carte ; le bouton ouvre un lecteur, pas un générateur de QR.
