# Authentification : analyse avant creation des ecrans

Date : 2026-10-11. Source lue uniquement : C:/Users/PC/Pictures/schoolsafe-integration-v1. Aucun appel de connexion, aucune modification de compte, aucun changement backend. Studio de cartes intact. Statuts ci-dessous etablis par inspection statique, pas par test authentifie.

## Conclusion d'architecture

Le chemin principal du formulaire SchoolSafe appelle SchoolSafeAuthNative.login puis sessionBootstrap (app/app.js:4060,4085). Le cookie schoolsafe_session est HttpOnly (server/src/authnative/cookie.ts:7). Le navigateur ne doit pas inventer de session ni de droits.

Il serait toutefois inexact de declarer ce systeme "Rosario de bout en bout" sur cette seule inspection. Le service existant verifie des mots de passe Argon2, utilise les fonctions SQL api.auth_* et des tables auth.* liees a iam.users/profiles (database/auth/v1/01_auth_tables.sql:20,38,48). Ces fichiers constituent une couche d'authentification ajoutee au projet; son equivalence avec l'authentification officielle Rosario n'est pas prouvee ici.

Le service peut aussi verifier des identites Control avant la branche locale (server/src/authnative/service.ts:239). Des routes et fonctions Supabase sont conservees (server/src/authnative/routes.ts:160; app/modules/authnative/auth-native.js:74). Leur presence ne prouve pas que le formulaire principal les utilise actuellement : celui-ci appelle la connexion native. Ne supprimer ni ne remplacer ces chemins sans decision explicite du proprietaire et preuve du contrat attendu.

## Inventaire des parcours

| Parcours | Interface existante | Contrat observe | Etat et besoin |
| --- | --- | --- | --- |
| Connexion identifiant/mot de passe | app/index.html, formulaire pilote par app/app.js:4000-4107 | POST /auth/native/login | Raccordement present dans le code; test reel non effectue. Conserver et harmoniser, pas recreer la logique. |
| Premier mot de passe obligatoire | app/app.js:4042,4076 | POST /auth/native/change-password puis nouvelle connexion | Interface existante. Verification de tout le parcours multi-profil et fermeture/revocation a effectuer. |
| Choix du profil a la connexion | app/app.js:4069, renderProfileChoice | POST /auth/native/login avec profileId | Interface existante; choix impose par le serveur, pas par le role de demonstration. |
| Changement de profil/ecole | app/app.js:3849,3879 | GET /auth/native/profiles, POST /auth/native/switch-profile | Interface existante; nouvelle session et revocation de l'ancienne prevues dans le service. Test reel a effectuer. |
| Reprise de session | app/app.js:4628,4630 | GET /auth/native/me, GET /native/session/bootstrap | Parcours technique present; presentation des etats expire/refuse/incomplet a verifier en navigateur. |
| Deconnexion | app/app.js:3889 | POST /auth/native/logout | Action existante. Le catch ignore l'erreur serveur : verifier la presentation d'un echec de revocation, ne pas annoncer une revocation non confirmee. |
| Recuperation parent/autre profil | app/app.js:4109,4159,4161 | POST /auth/recover/parent, POST /auth/recover/profile | Modal et formulaires existants. Ne pas modifier ce mecanisme de recuperation. |
| Recuperation avec code de l'ecole | app/app.js:4157 | POST /auth/recovery/admin/redeem | Formulaire existant. |
| Generation du code par l'administrateur | app/modules/administration/access-console.js:185 | POST /auth/recovery/admin/generate | Action existante, pas un ecran manquant. Permissions serveur a tester. |
| Nouveau mot de passe apres recuperation | app/app.js:4174,4191 | POST /auth/native/reset | Modal avec confirmation existant pour les parcours precedents. |
| Demande de lien par e-mail | client auth-native.js:110; aucun appel utilisateur trouve dans app hors definition/export | POST /auth/native/forgot, routes.ts:265 | Parcours visuel manquant. Envoi conditionne par configuration Brevo et AUTH_RECOVERY_URL, native-app.ts:41. Ne pas afficher "envoye" comme preuve de livraison. |
| Arrivee depuis le lien de recuperation e-mail | aucun traitement de token de lien trouve dans le code applicatif examine hors SDK fournisseur | POST /auth/native/reset | Parcours d'entree manquant dans l'application examinee. La modal existante recoit un jeton des recuperations autonomes, pas automatiquement du lien e-mail. Destination externe AUTH_RECOVERY_URL non verifiee. |
| Recuperation SMS | client auth-native.js:134,138 | chemins /auth/sms-recovery/request et /redeem | Pas d'appel UI ni de declaration de route retrouvee dans server/src. Methode de service requestSmsRecovery presente, mais pas preuve d'une API HTTP disponible. Bloque avant creation d'un ecran actif. |
| Enregistrement WebAuthn | client auth-native.js:143,147 | chemins /auth/webauthn/register/options et /register | Pas d'appel UI ni de route HTTP retrouvee. Module cryptographique present, pas preuve de parcours operationnel. |
| Recuperation WebAuthn | client auth-native.js:151,155 | chemins /auth/webauthn/recovery/options et /assert | Meme distinction : fonctions client et module serveur ne prouvent pas un raccordement HTTP. |

Le selecteur de methodes getRecoveryMethods expose egalement /auth/recovery-methods (auth-native.js:120), sans route HTTP retrouvee dans server/src. Ne pas construire un selecteur actif sur ce contrat non etabli.

## Ecrans a proposer au proprietaire

1. Demander un lien e-mail : identifiant, attente, message generique anti-enumeration, erreur et retour a la connexion.
2. Ouvrir le lien e-mail : jeton valide/invalide/expire, nouveau mot de passe, confirmation, retour a la connexion. Reutiliser les regles serveur et ne jamais inscrire un jeton dans les rapports, logs ou stockage persistant.
3. Etats transversaux de session : verification en cours, expiration, acces refuse, profil incomplet et echec de deconnexion. Il s'agit d'etats a verifier/completer, pas de cinq ecrans absents prouves.

SMS et WebAuthn : aucune creation active proposee tant que la disponibilite du contrat serveur et leur inclusion dans le perimetre ne sont pas etablies. Aucun changement du systeme de recuperation sans validation.

## Prochaines decisions et controles

P0 : clarifier ce que le proprietaire appelle Rosario : le service natif existant est-il le contrat accepte, ou exige-t-il une autre authentification officielle? L'analyse montre un ecart possible d'architecture, pas une raison de modifier le moteur sans autorisation.

P1 : valider les deux parcours e-mail manquants avant leur creation visuelle separee. Verifier la destination effective du lien sans lire ni publier les secrets de configuration.

P1 : tester avec des comptes autorises connexion, premier mot de passe, profils multiples, changement d'ecole, expiration, recuperation et deconnexion. Aucune preuve de production fournie par cet audit.

P2 : inventorier les anciens consommateurs Bearer avant raccordement aux visuels; un cookie natif ne devient pas un jeton Bearer et le frontend ne doit pas contourner le serveur.

## Livraison de ce lot

Un seul nouveau fichier : docs/codex-ui-v1/AUTHENTIFICATION_ANALYSE_V1.md. Aucun ecran cree, aucun raccordement modifie, aucun test metier execute. L'absence d'un appel ou d'une route est le resultat de la recherche dans cette copie, pas une preuve de l'absence de tout service externe.
