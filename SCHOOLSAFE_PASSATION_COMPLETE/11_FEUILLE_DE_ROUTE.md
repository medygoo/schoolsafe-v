# 11 — FEUILLE DE ROUTE

L'ordre ci-dessous **respecte les priorités déjà décidées par le propriétaire** (ACCESS_LAW d'abord, toute fuite inter-écoles bloquante, enforcement licence, cœur quotidien de la première école, puis seulement les nouvelles fonctionnalités). Rien n'a été réordonné.

## Étape 0 — Vérifications immédiates (lecture seule, aucune autorisation requise)

| # | Action | Preuve attendue |
|---|---|---|
| 0.1 | Obtenir du propriétaire une capture de l'écran « ancienne interface » + URL + compte | image + URL |
| 0.2 | Vérifier en base la préférence de thème de ce compte et le CSS réellement chargé | nom du thème + feuille de style |
| 0.3 | Confirmer que les deux sessions WhatsApp restent connectées | `WORKING` / `open` |
| 0.4 | Envoyer un email de contrôle vers une boîte réelle désignée | réception confirmée |
| 0.5 | Relancer `npm test` trois fois et capturer tout échec avec `--reporter=verbose` | 3 rapports |

Dépendance : aucune. Risque : nul (lecture seule).

## Étape 1 — Publier le correctif WhatsApp `provider_msg_id`

Contenu : helper `SchoolSafeWaMsgId()` + ses deux appels, déjà écrits et vérifiés.

Dépendances : aucune technique. **Accord requis** : commit + push + build Coolify.

Risque : faible (fonction isolée) ; rollback = image précédente `schoolsafe-rosario:20261019`.

Après déploiement : renvoyer un message de test et vérifier que `provider_msg_id` est un identifiant réel (jamais `Array`).

## Étape 2 — Décider de l'activation WhatsApp

Aujourd'hui les sessions sont `disabled` : aucun envoi automatique ne peut partir.

Séquence proposée (chaque sous-étape soumise à accord) :
1. Activer **une** session, garder l'autre désactivée.
2. Déclencher **un** événement éligible vers un destinataire de test (le propriétaire), consentement enregistré.
3. Vérifier réception, statut, absence de doublon, absence de bascule inutile.
4. Activer la seconde session et rejouer.
5. Le propriétaire décide ensuite de l'ouverture aux écoles réelles.

Dépendance : étape 1 déployée. Risque : envoi réel à un vrai destinataire → cadrage obligatoire.

## Étape 3 — Sortir le lot cartes/scans de l'ombre

Aujourd'hui hors Git : 5 fonctions PHP, 4 migrations (`020`–`023`), 7 tests PHP, 3 scripts QA, 6 captures.

Dépendance : décision du propriétaire sur le périmètre à publier. **Accord requis** (commit).

Risque : migrations `020`–`023` non appliquées en production — les rejouer en base isolée avec sauvegarde.

## Étape 4 — Outils P0 (dans cet ordre)

1. **Gitleaks** (aucune dépendance) — le plus urgent avant toute publication.
2. **Semgrep** — règles `auto` puis règles maison PHP/TS.
3. **Trivy** — scan de `schoolsafe-rosario:20261020-gofinal` et `schoolsafe-app:20261018d`.
4. **axe-core** greffé sur les suites Playwright existantes.
5. **Lighthouse CI** — après stabilisation visuelle.
6. **Bugsink** — nécessite un service et une DSN (**accord requis** : hébergement).
7. **ZAP** — sur environnement de test uniquement, jamais en écriture sur la production.

Dépendance : étape 0.5 terminée (base de tests saine). Risque : faible, outillage.

## Étape 5 — Problème d'interface post-connexion

Ne rien modifier avant l'étape 0.1/0.2. Ensuite : cibler l'écran, corriger par le thème, capture avant/après.

**Accord requis** : toute modification de `Side.php`, `Bottom.php` ou du thème touche tous les écrans.

## Étape 6 — Dette de tests

Identifier le test instable, le rendre déterministe, puis seulement ajouter de la couverture (notamment WhatsApp : cas LID, bascule, quotas).

## Points en attente de décision du propriétaire

| Sujet | Question posée |
|---|---|
| Correctif WhatsApp | Publier maintenant ? |
| Sessions WhatsApp | Activer pour tests réels ? Lesquelles, dans quel ordre ? |
| Lot cartes/scans | Publier dans le prochain commit ? |
| Outils P0 | Installer Gitleaks et Semgrep en premier ? |
| Interface | Corriger l'habillage post-connexion, ou accepter le chrome natif ? |
| Bugsink | Service auto-hébergé ou externe ? |

## Ce qui n'est PAS dans cette feuille de route

Aucune nouvelle fonctionnalité métier. Conformément à « VISION LARGE, LIVRAISON ÉTROITE », rien ne doit être ajouté tant que le parcours quotidien de la première école n'est pas stable, sécurisé et sauvegardé.
