# 09 — PROBLÈMES CONNUS

Méthode : chaque problème sépare **symptôme observé**, **cause démontrée**, **hypothèse non confirmée**, **preuve** et **correction recommandée**. Aucune interface n'a été modifiée dans cette passation.

---

## 1. L'ancienne interface Rosario apparaît au lieu de la présentation SchoolSafe

**Symptôme (rapporté par le propriétaire)** : après connexion, l'utilisateur voit l'habillage/navigation Rosario au lieu de la présentation SchoolSafe attendue.

**Démontré (20/10/2026)**
- L'écran de connexion **est** SchoolSafe : `GET https://app.shoolsafe.tech/connexion` → 200, `<title>SchoolSafe</title>`, 8 329 octets, classes `blue` (écran `login-blue`), aucune occurrence de `RosarioSIS`.
- Le chrome après connexion est **natif** : `Side.php` / `Bottom.php` / `Modules.php` n'ont pas été remplacés. SchoolSafe ajoute des écrans (`/ecoles/...`, `/plateforme/prodeli/...`, `/compte/recuperation`) mais ne réécrit pas le squelette Rosario.
- Le thème actif documenté est `SchoolSafeCodex`, dont `stylesheet.css` **importe** `../SchoolSafe/stylesheet.css` : si le thème actif d'un utilisateur n'est pas celui-là, l'habillage SchoolSafe ne s'applique pas.

**Hypothèses non confirmées**
- Le thème enregistré pour l'utilisateur concerné (préférence en base) n'est pas `SchoolSafeCodex`.
- L'écran montré au propriétaire est un module natif jamais re-habillé (ex. `Modules.php`, `Students.php`).
- Cache navigateur ou service worker servant un ancien CSS.

**Preuve à obtenir (action n°1)** : capture de l'écran exact + URL + utilisateur, puis vérifier en base la préférence de thème et la feuille de style réellement chargée (onglet réseau).

**Correction recommandée** : ne pas réécrire Rosario. Identifier l'écran, appliquer l'habillage par le thème existant, n'étendre le remplacement qu'aux écrans réellement ciblés.

**Risque de régression** : toucher `Side.php` ou le thème impacte **tous** les écrans, y compris ceux déjà validés. Capture avant/après obligatoire.

---

## 2. Adressage LID chez WhatsApp

**Symptôme** : un contact `+243 828 432 689` apparaît comme `100060658454708@lid` (Evolution) ou `266554881118327@lid` (WAHA) au lieu de `…@c.us`.

**Cause démontrée** : WhatsApp utilise des identifiants de confidentialité (LID) côté réception. L'envoi vers `…@c.us` fonctionne (ack 3), mais les messages **entrants** portent le LID.

**Conséquence** : tout code qui associe un message à un numéro doit accepter les deux formes, sinon les réponses semblent ne concerner aucun contact.

**Correction recommandée** : normaliser à la lecture (mapper LID → numéro quand l'information existe) ; ne jamais supposer `@c.us` pour un message entrant.

---

## 3. `provider_msg_id` valait `"Array"` (corrigé, non déployé)

**Symptôme** : `PHP Warning: Array to string conversion` et `provider_msg_id = Array` en base pour les envois WAHA, rendant le suivi de statut impossible.

**Cause démontrée** : WAHA renvoie `id` sous forme d'**objet** (`{fromMe, remote, id, _serialized}`). Le code faisait `(string) $decoded['id']`.

**Correction appliquée (arbre de travail uniquement)** : helper `SchoolSafeWaMsgId()` extrayant `id.id` / `_serialized`, utilisé pour WAHA **et** Evolution. Vérifié en conteneur : `provider_msg_id = 3EB033B68AB69F1B07B8DB`, plus de warning.

**Statut** : **PRÊT-NON-DÉPLOYÉ**. L'image en ligne contient encore la version fautive (SHA-256 `26fea00c…` = `HEAD`), l'arbre de travail `03a706fe…`.

---

## 4. Page temporaire de QR : trois pièges rencontrés

1. **Troncature** : une page PHP de plus de ~8 Ko envoyée en une seule commande a été coupée → fichier incomplet. Solution : écrire en morceaux ou transférer par `scp`.
2. **Droits** : un jeton en `0600 root` hors webroot donne `readable=0` pour Apache (`www-data`) → 403 permanent. Solution : `chown www-data` + `0400`.
3. **OPcache** : remplacer un fichier PHP au **même chemin** continue de servir l'ancienne version. Solution : publier sous un **nouveau nom**.

**Recommandation** : pour toute page de connexion QR, créer un fichier au nom unique, le supprimer après usage, vérifier `404` ensuite.

---

## 5. WAHA bascule en `FAILED` après un scan non abouti

**Symptôme** : `status: FAILED`, puis `422` sur `GET /api/<session>/auth/qr` (« Session status is not as expected »).

**Cause démontrée** : un QR expiré ou un appairage interrompu laisse la session dans un état non récupérable ; WAHA journalise `Session has been logged out!` puis ferme la page WhatsApp Web.

**Correction** : `POST /api/sessions/<session>/restart` (201), puis récupérer un QR neuf. Ne jamais réutiliser une capture d'écran de QR.

---

## 6. Evolution exige `X-Api-Key` sur certains GET

**Symptôme** : `GET /instance/fetchInstances` → **401** avec l'en-tête `apikey` seul.

**Cause démontrée** : cette route attend `X-Api-Key` ; les POST (`/message/sendText/...`) acceptent `apikey`.

**Correction** : le transport applicatif envoie **les deux** en-têtes — ne pas en supprimer un lors d'un futur refactor.

---

## 7. Nom de base trompeur

**Symptôme** : `psql -d schoolsafe` → base inexistante ou tables absentes.

**Cause démontrée** : l'application utilise `POSTGRES_DB=rosario_addons_test`. Les tables `schoolsafe_wa_*` n'existent **que** dans cette base.

**Correction** : toujours lire `POSTGRES_DB` depuis le conteneur.

---

## 8. Sessions WhatsApp applicatives désactivées

**Symptôme** : un envoi renvoie `NO_WA_SESSION` alors que les moteurs sont connectés.

**Cause voulue** : `schoolsafe_wa_sessions.status='disabled'` — protection contre la diffusion automatique.

**Correction** : activation explicite par le hub PRODELI, après validation du propriétaire.

---

## 9. Test serveur instable

**Symptôme** : 1 échec sur 4 exécutions de `npm test` (687/688), non reproduit.

**Statut** : ouvert. Capturer avec `--reporter=verbose` s'il revient.

---

## 10. Outils P0 absents

**Symptôme** : aucune couverture sécurité/accessibilité/performance.

**Statut** : non commencé, détaillé en `07_OUTILS_P0.md`. Ne jamais présenter ces contrôles comme réalisés.
