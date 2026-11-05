# Communication — analyse en profondeur et propositions (5 novembre 2026)

Méthode verrouillée : `docs/DECISIONS.md`, 5 novembre 2026. Complète `FUSION_MENU_COMMUNICATION_20261105.md`.
Sources :
- le code (worktree `SchoolSafeProdDrift-20261103`) ;
- les pages ouvertes avec le compte de direction, sans rien enregistrer ;
- la base de production en lecture seule.

Les constats C1, C3 et C5 ont été revérifiés dans le code. **Rien n'est corrigé : tout attend la validation du
propriétaire.**

## 1. État réel

- **Notifications SchoolSafe** : 9 événements en 2 jours (rappels de frais, contrôle des frais, entrée, remise
  d'enfant). Envois : fil de l'application 12 réussis, WhatsApp 7 acceptés, **e-mail 9 échecs sur 9** (« aucun
  e-mail du destinataire » : aucun des 9 parents n'a d'adresse). Un seul parent a donné son accord pour WhatsApp.
- **Annonces et sondages du tableau de bord** : 0 et 0.
- **Ancienne messagerie et journal des e-mails** : modules absents ; la messagerie, ouverte par son adresse,
  provoque une **erreur du serveur** (fichier manquant).

## 2. Ce qui fonctionne

- Annonces et sondages : enregistrement et suppression avec droit de modification, filtrés sur l'école et l'année ;
  le texte des annonces est nettoyé ; un parent ne voit que les annonces des écoles de ses enfants.
- Envoi des notifications : le lien parent-enfant est revérifié à l'envoi ; WhatsApp seulement avec accord ;
  textes contenant un mot de passe refusés ; 15 messages WhatsApp au plus par passage, avec pause ; 12 essais au
  plus en 6 heures ; journal des envois.
- Boîte de réception du parent : affichage sans risque d'injection de code.

## 3. Corrections proposées

### A. Sécurité et vie privée (priorité 1)

| # | Problème (fichier:ligne) | Correction proposée |
|---|---|---|
| C1 | **« Notifier les parents » réinitialise des mots de passe sans contrôle du droit de modification ni du profil** (`Custom/NotifyParents.php:22, 56`) : une requête forgée peut réinitialiser le mot de passe d'un administrateur ou d'un enseignant de l'école. Mot de passe prévisible (`identifiant + rand()`, :70), envoyé en clair par e-mail. | Désactiver la page (message clair) : la création des comptes parents passe par École › Familles et accès. À défaut : droit de modification, parents seulement, jamais connectés, mot de passe aléatoire sûr. |
| C2 | Le **« mode essai » réinitialise quand même les vrais mots de passe**, et un parent sans e-mail perd l'accès à son compte (`NotifyParents.php:74-78, 106`). | Même correction que C1. |
| C3 | **Sondages : bourrage de votes** et vote sur un sondage non visible (`PortalPollsNotes.fnc.php:24-30, 89, 94`) : le sondage est lu par son numéro seul, sans contrôle de l'école, du profil ni des dates ; une même réponse peut être comptée plusieurs fois. | Vérifier l'école, le profil, les dates et l'exclusion ; n'accepter que des choix existants, une seule fois chacun. |
| C4 | Sondages : les questions d'un sondage d'une **autre école** peuvent être modifiées (`PortalPolls.php:83-90`). | Filtrer sur l'école et l'année du sondage. |
| C5 | **« Signaler une absence » excuse directement l'élève**, sans validation de l'école, et **remplace le motif déjà saisi par l'école** (`signaler-absence.php:35-36`) ; un retard prévu excuse toute la journée ; nombre de signalements illimité. | Enregistrer le signalement comme **demande** ; l'école la valide ; ne jamais écraser un motif de l'école ; retard ≠ absence ; limite raisonnable. |
| C6 | **Données d'enfants envoyées au fournisseur WhatsApp** : prénoms, notes, commentaires, liens vers les copies, frais en retard (`SchoolSafeGradeNotice.fnc.php:61-67`, `SchoolSafeNotifyRecipient.fnc.php:12`), et textes complets gardés sans limite de durée (`SchoolSafeWhatsapp.fnc.php:306, 410`). | Décision du propriétaire : contenu minimal par WhatsApp (« Nouvelle information sur l'application SchoolSafe ») ou contenu complet ; durée de conservation des textes (par exemple 90 jours). |
| C7 | **Accord WhatsApp faible** : n'importe quel champ dont le titre contient « WhatsApp », cochable par la direction, sans date ni origine ; pas de retrait par réponse « STOP » (`SchoolSafeWhatsapp.fnc.php:205-209`). | Accord donné par le parent lui-même dans son compte, daté ; retrait possible à tout moment ; « STOP » respecté. |
| C8 | **Envoi en masse sans confirmation** dans « Convocations et courriers » (jusqu'à 800 élèves) avec le seul droit de voir les élèves (`courriers.php:15, 70, 185`). | Droit de modification exigé ; écran de confirmation avec le nombre de familles et le canal. |

### B. Cohérence

| # | Problème | Correction proposée |
|---|---|---|
| B1 | Les **signalements des parents n'arrivent dans aucune boîte** : la direction ne voit qu'un nombre au tableau de bord. | Une page « Signalements des parents » (valider, refuser, répondre au parent). |
| B2 | **Annonces, devoirs, notes et absences n'apparaissent pas dans l'application du parent** : marqués « envoyés » sur le fil, mais le fil ne les lit pas. | Les afficher dans « Messages de l'école ». |
| B3 | WhatsApp n'est envoyé que si un e-mail ou une notification push existe. | WhatsApp comme canal à part entière. |
| B4 | Annonces et sondages restent sur le web : aucun relais vers le fil ou WhatsApp. | Option « Prévenir les parents » à la publication (fil, WhatsApp si accord). |
| B5 | L'ancienne messagerie ouverte par son adresse provoque une erreur du serveur. | Message clair « Cette page n'existe plus ». |
| B6 | Enfants partis de l'école encore inclus dans la boîte du parent ; reçus et anniversaires sans filtre. | Filtrer les inscriptions terminées ; filtres complets. |

### C. Normes RDC

| # | Problème | Correction proposée |
|---|---|---|
| N1 | Pas d'accusé de lecture des convocations et courriers. | « Lu » côté parent, visible par la direction. |
| N2 | E-mail encore utilisé comme canal principal (9 échecs sur 9). | Ne plus créer d'envoi e-mail pour un parent sans adresse ; fil et WhatsApp d'abord. |
| N3 | Boîte du parent chargée en entier (jusqu'à 200 messages) : lourd sur réseau faible. | Charger par pages de 20. |

### D. Textes et mise en page

| # | Problème | Correction proposée |
|---|---|---|
| D1 | Messages en anglais au vote (« Poll does not exist », « User excluded from this poll ») ; WhatsApp de secours affichant un code technique (`grade.published`). | Traduire ; texte lisible. |
| D2 | Accents manquants : « arriere-plan », « Recu de paiement », « Refuse », « Anniversaire a venir », « Top 10 de l'ecole » ; « Aucun(e) note ». | Corriger. |
| D3 | « Annonces du tableau de bord » : le texte de l'annonce et les options sont cachés derrière une loupe. | Formulaire simple « Nouvelle annonce » (titre, texte, pour qui, du… au…), présentation moderne. |

## 4. Ordre de réalisation proposé

1. **Lot K1 sécurité** : C1 à C5, C8, B5. Code seulement.
2. **Décisions** : C6 (contenu WhatsApp et durée de conservation), C7 (accord donné par le parent).
3. **Lot K2 cohérence** : B1 à B4, B6, N1 à N3.
4. **Lot K3 textes et mise en page** : D1 à D3.

Non vérifié : ce que le fournisseur WhatsApp garde de son côté.

## Réalisation — lot K1 sécurité (5 novembre 2026)

- **En production** (accord du propriétaire) : image `schoolsafe-rosario:communication-k1-20261105`, commit
  `ac3063a` (6 fichiers). Service sain, 0 erreur PHP.
- Réalisés : C1, C2 (« Notifier les parents » désactivée, fichier gardé), C3, C4 (sondages : vote seulement si le
  sondage est visible, choix existants une seule fois, questions limitées à l'école), C5 (signalement du parent :
  jamais de remplacement du motif de l'école, retard sans retrait de l'alerte, 10 jours au plus, mention « à
  confirmer par l'école »), C8 (courriers : droit de modifier, confirmation, classe non cochée par défaut), B5.
- Vérifié à l'écran : « Notifier les parents » affiche l'explication ; l'ancienne messagerie affiche « Cette page
  n'existe plus » (au lieu d'une erreur serveur) ; annonces, sondages, tableau de bord, courriers sans erreur.
  Tri des votes essayé dans l'image (doublons et choix inexistants écartés).
- Non rejoué : vrai vote, vrai signalement, vrai envoi (rien n'est enregistré par l'assistant).
- Reste : décisions C6 (contenu WhatsApp, durée de conservation) et C7 (accord du parent) ; lot K2 (dont B1,
  validation des signalements par l'école) ; lot K3.
