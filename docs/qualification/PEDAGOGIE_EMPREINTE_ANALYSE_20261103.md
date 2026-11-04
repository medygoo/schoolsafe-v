# Pédagogie et pointage par empreinte — analyse de l'existant en production (3 novembre 2026)

Lecture seule. Sources : code de production (`ops/prod-drift-capture-20261103`), écrans réels sur
https://app.shoolsafe.tech (administrateur de l'école 3), base de production (comptages seulement).
[V] = vu dans le code ou à l'écran ; [I] = déduit.

## A. Pointage du personnel par empreinte digitale

### Ce qui existe
- **Matériel** : un lecteur Hikvision DS-K1T808 « Lecteur empreintes entrée principale », adresse
  locale `192.168.0.200`, adaptateur `hikvision_isapi`, rattaché au passage « Portail principal - Entrée » [V écran].
- **Enrôlement** sur la machine elle-même (bouton « Enrôler les empreintes » = page web du terminal) ;
  le serveur ne garde **aucune empreinte**, seulement le nombre de doigts (objectif 5) [V].
  État : jaspe awongi 1/5, Medy Lomela 0/5 [V écran].
- **Trois arrivées des pointages** [V] :
  1. **Envoi par la machine** (`schoolsafe-device-push.php?token=…`, format Hikvision EventNotificationAlert),
     prévu pour un réseau d'école sans adresse publique ;
  2. **Lecture par le serveur** (ISAPI `AcsEvent`, authentification Digest) — impossible depuis le VPS
     vers une adresse 192.168.x [I] ;
  3. **Import manuel** d'un lot.
- **Correspondance** numéro de la machine → membre du personnel (`schoolsafe_device_staff`), avec
  numéros à 6 chiffres générés [V].
- **Enregistrement** dans `entry_exit_staff_records` (heure de Kinshasa, « hors horaires » si en dehors
  de la plage du passage) ; affichage dans « Présences journalières », dans les tableaux de bord
  (« Pointages des enseignants du jour ») et en lecture dans Salaires / Paiements du personnel
  (« aucun calcul automatique de salaire ») [V].

### État réel [V base]
- 12 pointages envoyés par la machine le 27/10, **tous traités** ; 8 lignes de présence du personnel
  (27/10 de 10:15 à 18:09). **Plus rien depuis le 27/10.**
- Collecte automatique : activée mais en échec (`COLLECTOR_RETRY_REQUIRED`), dernière lecture « Jamais » ;
  aucun service de collecte ne tourne en continu sur le VPS.
- Événements refusés : sorties « checkpoint_mismatch » (le passage d'entrée n'accepte pas les sorties),
  un numéro inconnu `EMP002` (non associé).

### Défauts
1. Chaîne non alimentée en continu depuis le 27/10 (machine qui n'envoie plus, ou réseau) [V/I].
2. **« Pointage manuel » cassé** : l'insertion ne correspond pas aux colonnes, bouton hors du
   formulaire, heure perdue, passage forcé à 1, pas de jeton anti-falsification [V].
3. **Présences du personnel non séparées par école** (pas de colonne école) ; l'envoi par la machine
   suppose une seule machine (numéro 1, école 3) [V].
4. Jeton de la machine dans l'adresse (visible dans les journaux), jeton unique, pas de limite [V/I].
5. Un même pointage reçu par envoi et par lecture compterait deux fois (identifiants différents) [V].
6. Aucun retard / absence calculé pour le personnel (seulement « OK » / « Hors horaires ») [V].
7. Table `schoolsafe_device_enroll` absente des scripts SQL du dépôt (présente en base) ; migration 045
   en conflit avec `device-collector.sql` [V].
8. Textes sans accents ou codes bruts : « Equipements de presence », « Derniers evenements »,
   `imported`, `unmapped`, `in/out`, « Employee ID » [V écran].
9. Aucun test [V].

## B. Pédagogie

### Ce que contient le menu [V écran]
- **Organisation pédagogique** : Saisie des notes, Notes du carnet, Notes non conformes, Saisir les absences,
  Saisir l'éligibilité, Emploi du temps élève, Demandes, Emplois du temps groupés, Demandes et retraits
  groupés, Cours, Lancer le planificateur.
- **Évaluation** : Bulletins, Certificats, Livrets, Configuration, Échelles de notation, Commentaires et
  codes du bulletin, Historique des périodes, Notes historiques, Créer des devoirs en masse.
- **Accompagnement** : Activités (élève, assigner, liste, heures de saisie), Rattrapage.
- **Présences** : Arrivées à l'école.
- Enseignant : Devoirs (Assignments), carnet, notes de période… ; parent/élève : Devoirs de l'élève,
  notes, bulletins.

### Données réelles [V base]
0 type de devoir, 0 devoir, 0 note, 0 dossier de rattrapage. L'écran « Créer des devoirs en masse »
affiche « Aucun(e) type de devoir » : **aucun devoir ne peut être créé tant qu'un type n'existe pas**.
La période s'appelle « Quarter 2 ».

### Devoirs et mise en page [V]
- Création native (enseignant) : description riche (éditeur), un fichier joint, remise en ligne possible.
- **Fiche A4** (`schoolsafe-homework.php`) : en-tête de l'école (logo, nom, devises), « DEVOIR N° »,
  matière, dates, consignes, pied de page ; PDF par Chromium.
- Brouillon JASPE (enseignant) : 3 ressources Toy Theater, aperçu, copie en texte ; **rien n'est
  enregistré dans les devoirs**.
- Élève/parent : liste des devoirs, remise de fichier avant la date limite ; correction dans le carnet.

### Défauts
1. **L'administrateur ne peut pas obtenir la fiche A4** (droit vérifié jamais déclaré) [V/I].
2. **La mise en page de l'enseignant est perdue dans le PDF** : alignements, couleurs, tableaux, sauts
   de page retirés ; images WebP/GIF supprimées [V].
3. Fiche pauvre : « DEVOIR N° » = numéro interne, dates brutes, pas de ligne nom/classe de l'élève ni
   de zone de réponse ; pièce jointe absente du PDF ; seulement la période en cours [V].
4. Exports PDF natifs (bulletins…) probablement cassés : moteur configuré sur Chromium avec les
   options de l'ancien moteur [V/I].
5. **Publication d'un devoir sans notification aux parents** (fonction prévue jamais appelée) [V].
6. Brouillon JASPE en impasse (copie texte, illustration non transférée) [V].

### Rattrapage [V]
- Règle : 3 évaluations notées sous 50 % dans une même matière, d'au moins 2 sortes (devoir,
  interrogation, examen) → dossier ; décision du « Directeur pédagogique » ; séances ; réévaluation ;
  gratuit ou payant (frais, répartition 40/60).
- Défauts : **les parents ne reçoivent jamais les notifications** (destinataire jamais renseigné) ;
  écran parent dépendant d'un service de test (`local-isolated-test-only`) → erreur ; les 7 entrées du
  menu ouvrent la même page ; rôle repéré par le titre exact du profil ; détection écrite deux fois
  (PHP de test et SQL de production) ; textes sans accents et statuts anglais (held, scheduled).

### Navigation
- Entrées groupées du module SchoolSafe probablement perdues dans le menu natif [V/I] ; classement
  incohérent (Arrivées, Facturation du rattrapage) ; règles de menu pour des modules absents
  (Cahier de textes, Plans de cours, Quiz) [V].

## C. Proposition : la Pédagogie en 5 parcours (à valider)

| Parcours | Pour qui | Enchaînement sur une page |
|---|---|---|
| 1. Préparer l'année | Direction | niveaux et classes → matières et cours → périodes (en français) → barèmes → **types de devoirs** → emplois du temps, avec état « fait / à faire » |
| 2. Ma journée de classe | Enseignant | classes du jour → appel → cahier de textes → donner un devoir → ressources |
| 3. Devoirs et travaux | Enseignant → élève, parent | créer avec mise en page A4 fidèle (modèles, exercices, images, zone de réponse) → aperçu → PDF → publier avec date → notification aux parents → rendus → correction |
| 4. Évaluer et bulletins | Enseignant → direction | notes → notes manquantes → notes de période → état d'avancement → bulletins (PDF réparé) → palmarès, certificats |
| 5. Accompagner | Direction, enseignant | élèves en difficulté → rattrapage (parents réellement prévenus) → séances → réévaluation → activités |

Parent / élève : une page « Travail de mon enfant » (devoirs, notes, bulletins, absences).

JASPE en pédagogie : prépare une fiche adaptée au niveau, reformule des consignes, suggère des
appréciations, signale les notes manquantes, explique un devoir au parent — toujours validé par
l'enseignant, noms d'élèves masqués avant l'IA, aucun droit élargi.

Pointage : remettre la chaîne en marche (envoi de la machine), réparer le pointage manuel, séparer par
école, retards du personnel selon des horaires fixés par l'école, enrôlement complet (5 doigts).

## D. Livré — parcours 1 « Préparer l'année » (04/11)

- `preparer-annee.php` (menu Pédagogie › Organisation pédagogique, administrateur) : 10 étapes dans
  l'ordre, état réel lu dans le moteur, bouton vers l'écran natif ; commit `4f2f44f`.
- `decoupage-annee.php` (étape 2) : **chaque école choisit** son découpage — 3 trimestres ; 3 trimestres
  de 2 périodes (primaire RDC) ; 2 semestres de 2 périodes (secondaire RDC) ; 2 semestres ; ou découpage
  libre dans l'écran natif. Aperçu modifiable, confirmation, application en une transaction qui réutilise
  les périodes existantes et ne supprime que les périodes sans données ; commit `c3494a4` (28 tests).
- **École 3** (choix du propriétaire « 3 trimestres, 6 périodes ») : « Année scolaire 2026-2027 »
  (01/09/2026 → 02/07/2027), 3 trimestres, 6 périodes de notes ; doublon « Année scolaire complète »
  retiré (sans données) ; cours et emploi du temps toujours rattachés ; période courante « 2e période ».
  Progression : 5 / 10.
- Tranches horaires, barème et nom de classe traduits pour l'école 3 (`school3-traduction-20261104`,
  sauvegarde et `rollback.sql` sur le VPS) ; commit `e987ace`.
- `bareme-annee.php` (étape 7) : **chaque école choisit son barème** — modèle RDC (Élite ≥ 80 %,
  Grande distinction ≥ 70 %, Distinction ≥ 60 %, Satisfaction ≥ 50 %, Échec), sur 20, sur 10, ou libre ;
  **toutes les mentions sont modifiables** avant d'appliquer (code, libellé, seuil, ajout, retrait) et
  ensuite dans l'écran natif ; refus si des notes de bulletin l'utilisent ; classes sans barème reliées.
  Commits `2585c87`, `b31912d` (52 tests). Seuils RDC = usage courant des bulletins et palmarès EPST,
  à confirmer par chaque école.
- **École 3** : barème RDC appliqué, classe reliée. Progression : 7 / 10.
- `types-devoirs.php` (étape 8) : l'école coche, renomme ou ajoute ses types ; chaque type reçoit sa
  « sorte » pour le rattrapage ; création par cours et enseignant, sans doublon. **École 3** : Devoir,
  Interrogation, Examen créés. Progression : 8 / 10.
- Restent pour l'école 3 : horaires d'arrivée, 9 élèves sur 10 à placer dans une classe.

## E. Livré — rattrapage, étape 1 « corriger l'existant » (04/11, image `rattrapage-parents-20261104`, commit `bee3dbe`)

- **Parents réellement prévenus** : chaque événement (proposition, décision, séances, réévaluation,
  participation, paiement) est relayé par le répartiteur vers le fil, l'e-mail et WhatsApp (avec accord)
  des parents de l'enfant, une seule fois (clé `remediation:event:<id>`) ; les événements internes
  (revue de paiement, reconfirmation) ne partent pas. Déclencheurs SQL inchangés.
- **Écran parent réparé** : le responsable principal vient de la fiche famille native
  (`schoolsafe_pickup_guardians`, principal et actif) et non plus du service de test.
- Entrées du menu dirigées vers la bonne partie de la page ; libellés accentués ; statuts des séances en français.
- Tests : 12 messages parents, 61 contrôles de non-régression ; essai réel en base avec retour arrière
  (fil, e-mail, WhatsApp au parent, aucun doublon).

## F. Livré — rattrapage, étape 2 « réglages par école et vigilance » (04/11, image `rattrapage-reglages-20261104`, commit `b9922ee`)

- `reglages-rattrapage.php` (lien depuis la page Rattrapage, administrateur autorisé à configurer
  l'école) : seuil d'échec, nombre d'évaluations (2 à 6), sortes exigées (1 à 3), alerte vigilance,
  part de l'école, note de réussite et progression facultative. **Sans réglage : règle d'origine inchangée.**
- Migration `052-remediation-settings.sql` appliquée en base (sauvegarde `old-refresh.sql`,
  `rollback-052.sql` sur le VPS et dans le dépôt) : tables `schoolsafe_remediation_settings` et
  `schoolsafe_remediation_watch`, détection SQL lisant le réglage, preuves = toutes les notes sous le
  seuil (1 à 50) ; droit du rôle de l'application sur le compteur de la nouvelle table.
- **Vigilance** : dès 2 notes sous le seuil, sans dossier → section « Vigilance » (direction, enseignant
  de la matière) et message unique au parent ; l'alerte se lève quand les notes remontent.
- Partage des paiements lu dans le réglage au moment de la confirmation (défaut 40/60) ; les paiements
  déjà répartis ne changent pas.
- Règle de réussite affichée comme indication sur la réévaluation ; la direction décide.
- Tests : 44 nouveaux, 61 de non-régression ; essais SQL réels sous le rôle de l'application, annulés.
  Vérifié en ligne : règles affichées, refus d'une valeur hors limites. École 3 : aucun réglage
  enregistré (règle d'origine).

## G. Livré — rattrapage, étape 3 « fin du conflit d'intérêt » (04/11, image `rattrapage-conflit-20261104`, commit `a4815f0`)

- Les séances peuvent être confiées à **tout enseignant de l'école** ; la liste indique « enseigne et note
  cette matière » ou « autre enseignant ». Nouveau réglage (désactivé par défaut) : exiger un autre
  enseignant ; le serveur refuse alors l'enseignant de la matière (migration 053, `rollback-053.sql`).
- L'enseignant à qui une séance est confiée voit ce dossier ; la vigilance reste limitée à ses matières.
- Section « Suivi de la direction » : par classe (élèves, dossiers, vigilance, taux, réussis ; signalée à
  partir de 3 élèves et 30 % → accompagner l'enseignement d'abord), par enseignant de la matière, et par
  enseignant qui donne le rattrapage (séances dans sa propre matière comptées).
- Tests : 33 nouveaux, 61 de non-régression (banc de test désormais livré dans l'image) ; essais SQL
  réels sous le rôle de l'application, annulés. Vérifié en ligne (école 3 : 1 classe, 1 enseignant).
- Limite : la part « enseignant » d'un paiement n'est pas encore rattachée nommément à l'enseignant qui a
  donné les séances.

## H. Livré — rattrapage, étape 4 « famille et paiement juste » (04/11, image `rattrapage-famille-20261104`, commit `5ab6c04`)

- **Jamais bloquant** : les frais et paiements du rattrapage sont exclus du contrôle des frais à l'entrée
  et de l'état par type ; un rattrapage impayé ne peut plus faire passer l'élève au rouge au portail.
- **Tarif juste** : réduction en % avec motif obligatoire (100 % = gratuit) ; tarif de base, réduction et
  motif conservés ; le parent voit seulement « tarif réduit accordé ».
- **Parent** : bloc « Plan d'accompagnement » (séances tenues/programmées, présences, prochaine séance,
  payé, reste, rappel « ne dépend jamais du paiement ») et réponse unique : accepter, demander un
  rendez-vous, refuser, avec un mot ; journalisée et visible par l'école. Nouveau droit
  `SchoolSafe/RemediationParentReply.php` (profil parent seulement).
- **Part enseignant** attribuée à l'enseignant qui a tenu les séances ; il est aussi informé des
  événements du dossier.
- Migration 054 (sauvegarde des fonctions remplacées, `rollback-054.sql`). Tests : 32 nouveaux,
  61 rattrapage + contrôle des frais au vert, essais SQL réels annulés.
- **Bloquant découvert** : en base, seuls les profils administrateur ont les droits « Rattrapage ».
  Parents (profils 3 Parent, 6 Gardien) et enseignants (profil 2 Teacher) ne peuvent pas ouvrir la page
  Rattrapage : ils reçoivent les notifications mais ne voient ni le plan, ni la vigilance, ni le suivi
  des séances. **Droits accordés le 04/11 avec l'accord du propriétaire** (commit `34de52f`,
  `rollback-droits.sql`) : profils 3 Parent et 6 Gardien → consulter + répondre au plan ; profil 2
  Teacher → consulter + suivi de séance. Reste : l'enseignant de l'école 3 (compte 12) a le profil 8
  « Personnel administratif » (type enseignant) : mêmes droits enseignant accordés au profil 8 le 04/11
  avec l'accord du propriétaire (`rollback-profil8.sql`).

## J. Inventaire des pages Pédagogie par profil (04/11, lecture seule, base de production)

Écrans ouvrables (`profile_exceptions`, can_use) dans la branche Pédagogie du thème (Scheduling, Grades,
Eligibility, programmes enseignant, extensions pédagogiques, Rattrapage), + appel des présences (rangé
dans « Sécurité et contrôle » par le thème) :

| Profil | Pédagogie | Présences | Observations |
|---|---|---|---|
| 2 Teacher / 8 Personnel administratif | 22 | 2 | saisie des notes, devoirs (2 écrans « Assignments »), notes de période, bulletins, appel (rangé hors Pédagogie) ; a aussi Configuration du carnet, barèmes et codes de commentaires de bulletin |
| 3 Parent / 6 Gardien | 16 | 1 | devoirs, notes, bulletins, relevés, emploi du temps, rattrapage ; aussi palmarès (GPARankList), catalogue des cours, demandes de cours, **photos de classe** |
| 0 Student | 14 | 2 | comme le parent, sans rattrapage |
| 1/4/7/10 administrateurs | 43 à 53 | 8 | tout, dont planificateur et corrections |
| 5 Comptable | 38 | 8 | **corriger les notes de bulletin**, planificateur, désinscriptions en masse, saisie des activités |
| 9 Cantine (type enseignant) | 20 | 2 | **mêmes écrans que l'enseignant** : saisie des notes, devoirs, appel |

Constats : 14 à 22 écrans séparés pour un enseignant ou un parent, sans fil conducteur ; l'appel de
l'enseignant est rangé hors Pédagogie ; deux écrans de devoirs ; plusieurs droits trop larges sur des
données d'enfants (Comptable, Cantine, photos de classe pour les parents) — à revoir avec le
propriétaire, rien n'a été modifié.

Regroupement proposé (parcours validés le 03/11) :
- Enseignant, 22 → 3 portes : « Ma journée de classe » (classes du jour, appel, cahier, devoir à donner),
  « Devoirs et travaux » (créer, A4, publier, rendus, correction), « Évaluer » (notes, notes manquantes,
  notes de période, bulletins) ; + Rattrapage.
- Parent / élève, 16 → 1 porte « Travail de mon enfant » (aujourd'hui, devoirs, notes, bulletins,
  absences, rattrapage, emploi du temps).
- Direction : « Préparer l'année » (fait), « Suivi pédagogique » (avancement des notes, enseignants en
  retard, classes en difficulté, rattrapage), « Bulletins et palmarès ».
- Autres postes (comptable, cantine, gardien) : aucun écran pédagogique, sauf décision contraire.

## I. Livré — rattrapage, étape 5 « séances utiles » (04/11, images `rattrapage-seances-20261104` puis `-2`, commit `0aa8f24`)

- **Séances de groupe** : une séance pour plusieurs élèves de la même matière (15 au plus, portée et
  matière contrôlées par le serveur) ; présence individuelle ; affichage « séance de groupe (N élèves) ».
- **Jours de classe** : si l'école a un calendrier, la séance doit tomber un jour de classe (calendrier +
  événements, fonction existante `SchoolSafeAttendanceIsSchoolDay`).
- **Présence par carte** : carte lue à la caméra (navigateur compatible) ou par un lecteur ; vérifiée
  par le serveur comme au portail (signature, statut, inscription) et rattachée à l'élève du dossier ;
  présence « vérifiée par carte ».
- **JASPE** : brouillon d'exercices (rappel, 5 exercices, corrigés, vérification) à partir de la matière,
  du niveau et de l'objectif seulement ; noms masqués, aucune note ; limité en fréquence ; jamais
  enregistré, l'enseignant relit. Brouillon long : service rapide d'abord (1,4 s mesuré), Worker ensuite
  (le Worker échouait après 29 s sur ce format). La conversation de JASPE garde sa limite.
- Migration 055 (`rollback-055.sql`). Tests : 32 nouveaux, 61 de non-régression, essai SQL réel annulé.

- Suite validée par le propriétaire : ~~2. réglages par école~~ (fait), ~~3. conflit d'intérêt~~ (fait), ~~4. famille et paiement juste~~ (fait), ~~5. séances utiles~~ (fait). Reste le point 8 : remplacer le titre figé « Directeur pédagogique » par une tâche attribuable. (règle de détection, partage 40/60,
  règle de réussite) et alerte « vigilance » à 2 échecs ; 3. fin du conflit d'intérêt (rattrapage par un
  autre enseignant) ; 4. famille (accord du parent, page du plan) et paiement juste (jamais bloquant,
  première séance offerte, tarif réduit) ; 5. séances groupées, présence par carte, aide JASPE ;
  remplacer le titre figé « Directeur pédagogique » par une tâche attribuable.
