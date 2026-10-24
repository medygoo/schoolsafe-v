# Lot tableau de bord / Scan — remarques ouvertes du propriétaire

Statut : lot global NON terminé. Diagnostic Scan effectué et premier correctif UX qualifié sur image exacte avec HTTP authentifié synthétique : [accès visibles ProDéli](PRODELI_ACCESS_DISCOVERY.md). Aucun déploiement ni octroi réel de droits pour ces remarques.
Base de constat : branche `feat/presence-scan-qr-20261023`, HEAD `752f5910a09de30306a131cab26ed7630d81bd82`, origin vérifié identique. Pi seul écrivain de cette note ; brouillons existants conservés.

## Ordre de traitement

Traiter une étape à la fois, rendre compte puis attendre le GO suivant. La livraison du logiciel de délégation native ne clôt pas ce lot fonctionnel.

Ajout propriétaire : l'interface ProDéli doit présenter des étapes visibles, stables et cohérentes ; boutons d'activation, consultation des profils et gestion des accès faciles à retrouver, sans liens cachés ni confusion entre accès initial, droits et suspension. Le premier sous-lot UX ajoute « Gérer les droits — Scan et accès » dans l'accueil par école et sur la ligne de l'administrateur mono-école ; 16 contrôles PHP +48 contrôles Chromium synthétiques et162 régressions PASS, puis178contrats+37recoveryPG et55HTTPauth sur image exacte, vérification/nettoyage/provenancePASS. Aucune attribution ni livraison production. Les étapes restantes ne sont pas déclarées terminées et l'absence de tout bug n'est pas revendiquée.

### 1. Bouton Scan existant : diagnostic terminé, attribution réelle en attente

Après GO « une étape après l'autre », contrôle production en lecture seule effectué : checkout propre `44e246fdd037318c647ee25e8447bb83e3b88337`, image qualifiée `1b3843de…` saine, inchangés après lecture. Agrégats (sans identité ni session exportée) : trois comptes admin à périmètre strictement mono-école, tous en 2026 ; zéro droit natif Scan dans les profils et exceptions individuelles ; aucun de ces trois comptes n'a un droit de commande Scan ni de gestion des délégations ; zéro audit `access_bootstrap` / `access_assign`. Le gateway refuse donc légitimement l'ouverture tant qu'une attribution native explicite n'est pas faite. Le défaut n'est pas une panne démontrée de caméra ni une raison de supprimer le contrôle.

Preuve privée : `school-scan-20261023/scan-permission-step1/read-only-audit.json` et script borné en `BEGIN READ ONLY`, sans lecture de clé, session, portrait ou mot de passe. Une première requête cherchait une année dans config/SYEAR qui n'existe pas : ses compteurs admin à zéro ne sont pas une preuve d'absence d'admins. Cette sortie est conservée dans `read-only-audit-initial-no-year.json`. La requête corrigée agrège les années réellement présentes sans deviner l'année courante ; seule sa sortie finale établit le nombre d'administrateurs. Le comptage des audits a aussi été aligné sur les actions réelles du writer. Aucun accès réel accordé, aucun runtime ou déploiement changé.

Désignation propriétaire reçue : **COMPLEXE SCOLAIRE LE SAGE**. Lecture seule ciblée confirme une seule école correspondante active, un seul administrateur mono-école2026, aucun droit Scan (`le-sage-read-only.json`). L'ensemble des droits voulu et leur enregistrement dans le parcours natif ProDéli restent à confirmer ; aucun compte n'a été modifié. Ne pas choisir les autres comptes ni tous les droits à la place du propriétaire. Qualifier ensuite ouverture, révocation et refus ; caméra physique distincte.

- Précision propriétaire : le bouton ouvre une PAGE DE SCAN habillée selon l'image, avec lecture de la carte et résultat. Il ne doit pas générer ou afficher un QR à la place du lecteur. Le QR appartient à la carte ; l'émission de carte reste une fonction distincte.
- Le propriétaire confirme que le bouton est présent sur téléphone. Ne pas en créer un deuxième ni traiter son absence comme le défaut courant.
- Résultat rapporté après appui : « Permission requise. ». La session personnelle n'a pas été ouverte ni reproduite ; le mécanisme de refus est établi par le code et l'absence agrégée de droits natifs en production.
- Source : `scanner-native.php` émet précisément ce refus lorsque `SchoolSafeEndpointGateway('scanner-native.php', 'GET')` retourne `deny`.
- Le gateway exige au moins un des cinq droits natifs de Scan. La navigation peut annoncer le lien à un compte scolaire sans vérifier ces droits. Cela explique une possibilité de lien visible avec refus ; la cause exacte sur le compte concerné reste à établir.
- Vérifier le profil natif effectif, les droits Use/Edit, le périmètre scolaire et l'état actif avant correction. Ne pas supprimer le gateway, promouvoir tous les administrateurs ou choisir un bénéficiaire réel implicitement.
- Acceptation : compte scolaire explicitement autorisé ouvre le Scan ; compte non autorisé / ProDéli / autre école reste refusé ; révocation effective dès la requête suivante ; caméra, lecture QR et action métier qualifiées séparément. Un scan d'arrivée ne crée pas automatiquement une présence pédagogique.

### 2. Enregistrement administrateur → profil et réutilisation native

Remarque explicitement ajoutée par le propriétaire à ce lot : toutes les informations enregistrées pendant l'inscription de l'administrateur de l'école doivent être accessibles depuis le profil de cet administrateur. Les informations pertinentes doivent être réutilisées pour les cartes et les en-têtes / pieds de page.

- Conserver les propriétaires natifs : identité, coordonnées et portrait dans le dossier personnel ; identité, coordonnées, logo et mentions de l'école dans ses paramètres. Le profil peut présenter ces deux ensembles en les distinguant, sans recopier les données scolaires dans chaque compte personnel.
- Réutiliser les mêmes sources pour les cartes, l'en-tête et le pied de page selon les champs nécessaires à chaque support. Ne pas afficher toutes les informations personnelles dans les supports publics.
- Ne pas créer de formulaire Studio parallèle, de second registre d'administrateurs ou de second stockage de portraits.
- Vérifier le transfert complet des champs réellement collectés à l'inscription ; une cartographie source → propriétaire natif → profil / support doit précéder les corrections.
- Inclure la remarque sur le portrait : pas de succès annoncé sans fichier effectivement sauvegardé ; conserver une reprise sur échec ; lecture cohérente dans le profil et l'avatar ; conservation lors des déploiements à qualifier.
- Une donnée absente doit être signalée et complétée chez son propriétaire natif, jamais inventée.
- Acceptation : parcours synthétique inscription → profil et supports autorisés, modifications répercutées depuis une seule source, droits et isolation scolaire préservés. Aucune inspection d'un portrait réel ni correction de production n'est revendiquée ici.

### 3. Cinq classes les plus actives en travaux

- Clarification propriétaire : cinq CLASSES, avec noms des enseignants et nombre de travaux donnés ; pas cinq écoles.
- Calcul automatique depuis les travaux natifs enregistrés, sans saisie manuelle de statistiques. Source existante à qualifier : `gradebook_assignments` (enseignant, période, rattachement classe ou cours).
- Afficher total par classe et contributions des enseignants ; ne pas compter les notes ou les copies d'élèves comme des travaux supplémentaires.
- Qualifier l'application d'un travail commun à plusieurs classes ; un même travail ne doit pas être multiplié par les élèves d'une classe.
- La période, les travaux futurs / non datés, les éventuelles autres sources natives et les ex aequo doivent être explicités avant de considérer le calcul validé.
- Périmètre de l'école courante uniquement ; aucun faux classement si aucune donnée. Ce volume mesure l'activité enregistrée, pas la qualité pédagogique.

### 4. Palmarès Top 10

- Reste un besoin distinct du classement des travaux : résultats des élèves, école / classes, placement demandé près des anniversaires et confidentialité parentale.
- Des brouillons locaux de présentation existent mais ne constituent pas une livraison en production. Ne pas les écraser ni les déployer implicitement.

## Limites de cette mise à jour

Diagnostic production en lecture seule et correctif UI qualifié sur fixture/image exacte uniquement ; détails et preuves séparés dans PRODELI_ACCESS_DISCOVERY.md. Aucun droit réel accordé, compte modifié, test caméra physique ou déploiement réalisé pour ces remarques. Le diagnostic et le sous-lot de découverte des boutons ne rétablissent pas à eux seuls l'accès Scan réel. Informer et attendre le GO de l'étape suivante ; ne pas annoncer toute l'interface ProDéli validée.
