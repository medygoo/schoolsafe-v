# SchoolSafe — intégration des fonctionnalités, Rôles et accès en premier

Date : 16 septembre 2026. Point de départ : `main`, `3ca73fa`.

## Mandat et état

Le propriétaire demande de compléter les interfaces, les backends et les liens
avec l'écosystème des 16 rubriques ci-dessous. Il précise ensuite : **commencer
par Rôles et accès, corriger et rendre fonctionnel, conserver l'existant, avancer
étape par étape**. Cette priorité remplace le démarrage immédiat par les appareils.

Le présent suivi complète la roadmap de livraison du 13/09. Les autres modules
ne sont pas déclarés terminés par la présence d'un écran ou d'un fichier serveur.
Le miroir concerne le code et la documentation versionnés, pas les bases métier,
les secrets, ni une mise en production automatique.

## Première séquence : Rôles et accès

### A1 — contexte natif et consultation des droits : livré, preuves avec substituts

- Défaut reproduit avant correction : `getCurrentUser()` renvoyait `demo-school-1`
  pour une session native valide sans bearer. La navigation, les documents,
  plusieurs modules et JASPE pouvaient ainsi utiliser un contexte démonstratif.
- Session native reconnue par son marqueur `native`, et non par la présence d'un
  token JavaScript. Le cookie HttpOnly reste l'autorité de session côté serveur.
- Console native : ouverture/actualisation → `GET /native/session/bootstrap` →
  identité/école/rôles/permissions/portées/refus réels du compte → catalogue local
  pour les libellés → lecture filtrable. Aucune nouvelle route ni nouvelle table.
- Le rôle affiché « admin » ne donne aucun droit implicite. La console requiert
  `roles.manage` avec portée `school`, et un refus explicite l'emporte.
- Les différentes cibles d'une même permission restent visibles. « Attribuée »
  décrit le paquet de session ; l'autorisation d'une action précise reste soumise
  aux conditions et aux contrôles serveur. Les UUID sont affichés faute de
  projection sûre des libellés de cibles dans ce lot.
- Une erreur ne réaffiche pas d'anciens droits. Refus/expiration → reconnexion ;
  changement d'école/profil inattendu → rejet ; réponse tardive après fermeture
  → ignorée. Le cache du catalogue peut être retenté après un échec de chargement.
- Ancien éditeur conservé en démonstration. Aucun de ses brouillons ne devient
  une attribution réelle. Le bouton de brouillon refuse toute session réelle.

Sources principales : `app/app.js`, `app/modules/administration/access-console.js`,
`app/modules/core/access.js`, `server/src/sessionnative/routes.ts`,
`server/src/sessionnative/service.ts`, `database/projections/v1/02_session_bootstrap.sql`.

Preuves : `app/qa-native-access-console.cjs` (échec initial puis réussite),
contrôles Access Law/JASPE, 23 tests serveur ciblés, contrats permissions/isolation,
régressions paiement/campagnes et dashboard JASPE. API navigateur et dépendances
PostgreSQL substituées : **aucune validation sur une base réelle annoncée**.

### A2 — consulter les profils de l'école et leurs attributions : prochaine étape

1. Relire la console et l'Administration existantes : `staffSamples` reste une
   fixture, et `SchoolSafeSchoolAPI` appelle encore `/school/staff` et
   `/school/roles`. Ces routes legacy ne sont pas assemblées par `buildNativeApp`.
2. Réutiliser `iam.profiles`, `iam.roles`, `iam.profile_roles`, les grants et leurs
   portées. Distinguer profil d'accès et futur dossier RH ; ne créer aucun doublon
   de personne ou second moteur de permissions.
3. Préparer une projection native de profils/rôles/grants lisible uniquement sous
   `roles.manage` dans l'école résolue par le serveur. Vérifier les champs utiles,
   la pagination et les cibles ; ne transmettre aucun secret d'authentification.
4. Brancher la liste de personnes existante sur cette projection ; conserver les
   états chargement, erreur, vide et refus. Ne pas remplacer une réponse vide par
   les noms de démonstration. Faire apparaître le lien rôle → droit → périmètre.
5. Prouver : deux écoles, utilisateur sans droit, DENY, profil d'une autre école,
   changement de session pendant chargement. Valider aussi les migrations sur une
   base de test isolée avant de prétendre que le parcours SQL fonctionne.

### A3 — attribution et révocation persistantes : à réaliser après A2

- Relire les règles canoniques et les fonctions IAM avant de définir le contrat
  de mutation. Réutiliser la transaction et l'audit existants ; ne pas écrire
  directement dans les tables depuis le navigateur ou rejouer un brouillon offline.
- Encadrer les rôles attribuables et les cibles de portée. Les permissions Control
  ne sont pas accordables depuis la console d'une école. Aucun paramètre client
  ne remplace le contexte scolaire résolu par la session.
- Définir et tester la protection contre la perte du dernier administrateur, les
  modifications concurrentes, l'expiration/révocation et les tentatives d'élévation.
  Les détails non décrits dans les règles existantes sont à expliciter dans le lot.
- Enregistrer atomiquement, journaliser acteur/cible/changement, puis relire les
  droits. Reconnexion et changement d'école ne doivent pas restaurer un ancien droit.
- Ensuite seulement : restrictions individuelles/conditions avancées et inspection
  des décisions d'accès pour les autres modules, sans moteur parallèle.

## Cartographie vérifiée du menu

État observé dans le code au 16/09, pas un pourcentage de disponibilité en production.
Les liens écosystème indiquent la cible de raccordement, pas une intégration livrée.

| Rubrique | Existant et raccordement restant | Liens à préserver / construire |
| --- | --- | --- |
| Pilotage | UI + client `/pilotage/*` ; services legacy non assemblés en natif | Projections des modules, alertes, Control selon autorité |
| École | Client natif élèves pour liste/lecture/brouillon ; structure/familles encore partiellement démo ou legacy | SchoolSafe ID, Guardian, cartes/Pass |
| Personnel | UI RH démonstrative ; comptes IAM existants ; dossiers RH/présences à persister | SchoolSafe ID, StaffID, Device Hub pour les événements |
| Pédagogie | Backend `/native/pedagogy/*` assemblé ; client encore `/pedagogy/*` et portail démo | Classes/élèves, enseignants, Parent, documents |
| Sécurité et contrôle | UI scanner/portail ; backend sécurité legacy non assemblé en natif | Pass, Guardian, photos/validation humaine, événements Device Hub |
| Finance | Backend `/native/finance/*` assemblé ; client encore `/finance/*` | Élève, caisse, reçu, statut limité Parent/Pédagogie, Comptabilité |
| Comptabilité | Synthèses/trésorerie de démonstration ; persistance métier à compléter | Finance, justificatifs, rapprochements ; pas de soldes inventés |
| Stock / Inventaire | Catalogue/mouvements/achats démonstratifs ; backend à construire | Achats, Comptabilité, équipements Lab selon périmètre produit |
| Communication | Brouillons locaux ou de session ; services notifications/email existants non raccordés à ce parcours natif | Parents/personnel, annonces, alertes et documents |
| Administration | Écrans existants ; comptes/rôles appellent le client École legacy | SchoolSafe ID, Rôles et accès, paramètres ; Control reste distinct |
| Contrôle et rapports | Entrée de menu encore « prochaine étape » | Projections autorisées des modules, exports, audit |
| Centre de documents | Registre d'aperçus et contrôles frontend existants ; sorties officielles à raccorder par domaine | Tous les modules ; impression de cartes via Control |
| Paramètres | Entrée système encore « prochaine étape » ; préférences et réglages dispersés existants | École, années/classes, comptes, intégrations |
| Rôles et accès | A1 ci-dessus ; attributions persistantes et autres profils restent A2/A3 | Tous les modules, SchoolSafe ID ; JASPE limité aux mêmes droits |
| Audit et journaux | Entrée système encore « prochaine étape » ; tables/triggers/services existants | Actions sensibles, changements de droits, corrections traçables |
| Intégrations | Entrée encore « prochaine étape » ; connecteurs hétérogènes présents | Device Hub, notifications, stockage, Control ; secrets côté serveur |

Preuves de raccordement : `server/src/native-app.ts` est l'assemblage natif actif ;
`server/src/app.ts` n'enregistre les routes legacy que si leurs dépendances sont
fournies. Les clients API sont sous `app/modules/`. Les entrées système et la
navigation sont dans `app/app.js` ; les cartes écosystème dans `app/index.html`.

Attention : les cartes Sécurité, Cartes élèves et Guardian dirigent actuellement
toutes vers le module Sécurité. Leur badge « Actif » ne prouve pas un parcours
complet. StaffID, Watch et Lab restent marqués « Bientôt ». La description Lab
dans l'interface (« expérimentations pédagogiques ») diffère de la référence du
propriétaire (« équipements ») : conserver et préciser le périmètre au lot Lab.

## Ordre après les accès et conditions de clôture

Ordre de travail recommandé : École/identités/classes/tuteurs → Personnel de base
→ Sécurité/Pass/Guardian et présences → Finance/Comptabilité → Pédagogie →
Communication/documents → Stock/Inventaire. Pilotage et rapports sont raccordés
progressivement aux sources réellement prêtes. Paramètres, audit et intégrations
accompagnent chaque lot ; les libellés du menu ne sont pas réordonnés ici.

Chaque lot est fermé uniquement après un parcours frontend → session/permission
serveur → stockage → relecture, ses tests critiques, le handoff, le commit et le
push avec égalité vérifiée du SHA local/distant. Une API ou base substituée est
signalée ; elle ne suffit pas à déclarer une mise en production prête.

La validation structurelle Device Hub demandée au §56 reste distincte et en
attente ; voir `docs/superpowers/specs/2026-09-16-schoolsafe-device-hub-audit-design.md`.
Les incohérences SQL d'admission et le rejeu/restauration P5 restent ouverts.
Watch, la refonte de JASPE et le déploiement ne sont pas des dépendances d'A1.
Ce lot ne change aucune décision d'exploitation ni configuration VPS.
