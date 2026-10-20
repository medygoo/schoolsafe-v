# 06 — COMMUNICATIONS

Aucune valeur sensible (clé, mot de passe, jeton, QR) ne figure ici. Ces valeurs vivent uniquement en variables d'environnement du conteneur PHP et dans les conteneurs moteurs.

## 1. Vue d'ensemble

```
Studio (événement) ──► feed | email (Brevo) | push (WebPush) | whatsapp
                                                              ├─ WAHA (principal)
                                                              └─ Evolution API (secours)
SMS : 4 lignes configurables (canal séparé, quotas propres)
```

Variables attendues côté application (noms seulement) : `WAHA_BASE`, `WAHA_KEY`, `EVOLUTION_BASE`, `EVOLUTION_KEY`, plus la configuration SMTP du plugin. Vérifié le 20/10/2026 : les quatre variables existent dans `schoolsafe-rosario` et les clés sont **identiques** à celles des moteurs.

## 2. WhatsApp — état vérifié le 20/10/2026

| Moteur | Session | Statut | Identité connectée | Rôle |
|---|---|---|---|---|
| WAHA | `schoolsafe-waha-1` | `WORKING` | `243967552380@c.us` (lid `100060658454708@lid`) | principal |
| Evolution API v2.3.7 | `schoolsafe-evolution-1` | `open` | `243828432689@s.whatsapp.net` (profil « Loms ») | secours |

- Les deux numéros ont été appairés par scan réel depuis WhatsApp → Appareils connectés.
- Les noms de sessions en base sont **identiques** à ceux des moteurs : aucune divergence.
- Les deux sessions applicatives sont `status='disabled'`, `conn_state='unknown'` : **aucune diffusion automatique**.

### Envois réels effectués (preuves)

| Test | Résultat |
|---|---|
| WAHA → `+243 828 432 689` | accepté, id `3EB0…93278A4B1825CC`, **ack 3** ; message retrouvé côté Evolution (`fromMe=false`) |
| Evolution → `+243 967 552 380` | accepté, id `3EB0…C593A926DD2118` ; retrouvé côté WAHA (`fromMe=false`) |
| SchoolSafe → WAHA → `+243 828 432 689` | `accepted`, `via=waha`, reçu côté Evolution |
| SchoolSafe → bascule (WAHA 503 simulé) → Evolution → `+243 967 552 380` | `accepted`, `via=evolution-fallback`, reçu côté WAHA |
| Rejeu même `dedup_key` | `via=dedup`, **aucun** second envoi |
| WAHA 400 (rejet métier) | `failed`, `WAHA_ERROR:400`, **pas** de bascule |

Transport, bascule et anti-doublon sont donc **prouvés en production**, pas en simulation.

### Délais mesurés (même réseau Docker)

| Opération | WAHA | Evolution |
|---|---|---|
| État des sessions | 3,4 ms | 75 ms (`findMessages`) |
| Envoi de texte | **33 ms** | **489 ms** |
| Génération du QR | ~55 ms (image) | ~2,2 s |

### Particularités à connaître

1. **Adressage LID** : les messages entrants arrivent en `…@lid` (`100060658454708@lid` pour `+243 828 432 689`), pas en `…@c.us`. L'envoi sortant vers `@c.us` fonctionne (ack 3), mais tout code comparant des JID doit accepter les deux formes.
2. **En-têtes Evolution** : `GET /instance/fetchInstances` refuse `apikey` seul (401) et exige `X-Api-Key`. Le transport envoie **les deux** : ne pas en retirer un.
3. **QR expiré** : un scan raté met WAHA en `FAILED` (422 sur `/auth/qr`). Redémarrer la session (`POST /api/sessions/<nom>/restart`) régénère un QR valide.
4. **Format d'identifiant** : WAHA renvoie `id` sous forme d'objet, Evolution sous forme de chaîne (voir correctif `09` §3).

## 3. Email

- Plugin officiel `Email_SMTP` (PHPMailer 6.9+), STARTTLS port 587, hôte `smtp-relay.brevo.com`.
- Expéditeur : `securite@mail.shoolsafe.tech`.
- Mot de passe SMTP fourni par l'environnement, jamais en base, jamais affiché.
- Statut : DÉPLOYÉ-NON-VÉRIFIÉ. Test de réception réel à faire (le domaine expéditeur n'a pas de MX : utiliser une boîte réelle désignée par le propriétaire).

## 4. Push web

- `notification-push-sw.js`, `notification-push-subscription.php`, `SchoolSafeWebPush.fnc.php`.
- Statut : DÉPLOYÉ-NON-VÉRIFIÉ.

## 5. SMS — 4 lignes

- Migration `036-sms-lines.sql` : lignes configurables, quotas, historique, tests autorisés.
- Normalisation `+243` partagée avec WhatsApp (`SchoolSafeSmsNormalize`).
- Statut : chaîne LIVE, **aucun envoi réel d'école** dans cette passation.

## 6. Garde-fous communs (à ne pas contourner)

| Garde | Effet |
|---|---|
| Consentement (`schoolsafe_wa_consents`, `consented='Y'`) | sans ligne valide : `CONSENT_REQUIRED`, rien n'est envoyé (sauf test hub `purpose=test` + `staff_id=0`) |
| Confidentialité (`SchoolSafeWaSanitize`) | texte contenant mot de passe, code ou lien secret → `SENSITIVE_BLOCKED`, rien n'est envoyé |
| Idempotence (`idempotency_key` UNIQUE) | un rejeu rend le statut existant, jamais un second envoi |
| Bascule | seulement sur échec **confirmé** (réseau, timeout, 0, 429, 5xx) ; jamais sur 4xx métier |
| Sessions | une par moteur, noms distincts, activation manuelle par le hub PRODELI |
| Quotas SMS | par ligne, contrôle avant envoi |

## 7. Sessions à reconnecter (si un jour elles se déconnectent)

1. L'administrateur PRODELI se connecte à l'application.
2. Une page temporaire dédiée affiche les QR des deux moteurs — elle n'existe pas en permanence : on la crée, on l'utilise, on la supprime (`09` §4).
3. WhatsApp du numéro concerné → Appareils connectés → Connecter un appareil → scan.
4. Vérifier le badge : WAHA `WORKING`, Evolution `open`, avec l'identifiant affiché.

**Ne jamais publier un QR, un jeton ou une clé**, même temporairement.

## 8. Vérifications encore manquantes

- Déclenchement réel d'une notification SchoolSafe éligible vers WhatsApp (bloqué volontairement).
- Envoi email de contrôle avec réception confirmée.
- Test push sur un navigateur abonné.
- Test SMS réel sur les 4 lignes (coût et consentement à cadrer).
- Mesure de débit sur volume (aucune campagne de masse).
