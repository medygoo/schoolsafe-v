# 10 — DÉCISIONS ET INTERDICTIONS

Source de vérité : `docs/DECISIONS.md` (325 lignes, daté, statut « Validée ») et `docs/CURRENT_HANDOFF.md`. Ne jamais contredire une décision validée sans accord explicite du propriétaire.

## 1. Architecture et exploitation

| Date | Décision validée |
|---|---|
| 2026-09-14 | Docker + Coolify comme architecture d'exploitation du VPS central. |
| 2026-09-14 | **Git → Coolify → Docker → VPS**. Jamais de copie manuelle sur le VPS ; image reproductible. |
| 2026-09-14 | **Un seul VPS central** pour plusieurs écoles. « 1 école = 1 VPS » est incorrect. |
| 2026-09-14 | PostgreSQL du VPS = **base de production souveraine**. Supabase : inventorier → classifier → migrer → tester → retirer. |
| 2026-09-14 | Le dépôt versionné est la **source de vérité** (procédures, configs non secrètes, runbooks). |
| 2026-09-14 | Sauvegarde automatique + rétention + **restauration réellement testée** avant la première école réelle. |

## 2. Identité, écoles, permissions

| Date | Décision validée |
|---|---|
| 2026-09-14 | `school_id` (UUID) = identité technique canonique ; `school_code` = identifiant lisible. |
| 2026-09-14 | Identité d'authentification **globale** à la plateforme ; l'accès aux écoles passe par des rattachements. |
| 2026-09-13 | Quatre expériences adultes visibles (Direction, Enseignant, Gardien, Parent) ; les métiers internes sont des postes combinables. |
| 2026-09-13 | L'administrateur principal d'une école compose librement ses postes **dans l'enveloppe souveraine**. |
| 2026-09-13 | Menus, tableaux de bord et capacités JASPE découlent d'un **même contrat de session** calculé par l'Access Law. |

## 3. Interface

| Date | Décision validée |
|---|---|
| 2026-09-13 | Supprimer définitivement l'ancien écran visuel `guardian` ; ouvrir directement la connexion. |
| 2026-09-13 | Références SchoolSafe mobile/bureau = direction visuelle officielle. |
| — | L'ancien écran de connexion `login-reference` est **conservé sur disque** pour rollback immédiat (jamais supprimé). |

## 4. JASPE

| Date | Décision validée |
|---|---|
| 2026-09-13 | Consolider le moteur 2,5D existant, **ne pas reconstruire** le personnage. |
| 2026-09-13 | Le physique couvre visage et corps ; voix/labial reportés au raccordement Cloudflare. |
| 2026-09-13 | Contrôleur unique progressif, v12 principal, WebP de secours. |
| 2026-09-13 | JASPE exécute les actions ordinaires autorisées ; **jamais** les notes, paiements, permissions, suppressions, opérations sensibles ni données protégées. |

## 5. Ordre des priorités (validé 2026-09-14)

1. Phase A ACCESS_LAW.
2. Toute fuite inter-écoles = **bloquante**.
3. Enforcement backend de la licence.
4. Cœur quotidien de la première école.
5. **VISION LARGE, LIVRAISON ÉTROITE** : aucune nouvelle grande fonctionnalité tant que le parcours quotidien de la première école n'est pas stable et sécurisé.

## 6. WhatsApp — décisions opérationnelles du 20/10/2026

- WAHA = **principal**, Evolution API = **secours**, jamais en parallèle.
- Bascule **uniquement** sur échec confirmé (réseau, timeout, 0, 429, 5xx) ; jamais sur une 4xx métier.
- Une session par moteur, noms **distincts** (`schoolsafe-waha-1`, `schoolsafe-evolution-1`), jamais partagés.
- Sessions **désactivées** tant que les tests réels ne sont pas validés par le propriétaire.
- Consentement explicite obligatoire pour tout envoi à un parent/école.
- Aucun secret (clé, jeton, QR, mot de passe) dans Git, un document ou un écran.

## 7. Interdictions permanentes

| Interdiction | Raison |
|---|---|
| `docker system prune`, `docker volume rm`, `DROP DATABASE` | perte de données irréversible |
| `ALTER TABLE` direct sur une table native Rosario | casse les migrations et les montées de version |
| Réactiver une route Supabase | legacy en cours de retrait |
| Réécrire Rosario / dupliquer ses moteurs | double logique = double bug |
| Publier un secret ou un QR | compromission immédiate |
| Déployer sans validation ni sauvegarde | rollback impossible |
| Toucher aux travaux indépendants de `C:\Users\PC\Documents\Codex\...` (« Work ») | travail d'un autre agent |
| Installer deux fois le même outil | pollution de l'environnement |
| Présenter une simulation comme une validation production | fausse le jugement du propriétaire |
| `git add -A` / `git commit` / `git push` sans demande | mélange de travaux, publication non autorisée |
| Modifier les données réelles sans autorisation | responsabilité légale et pédagogique |

## 8. Règle de continuité

Toutes les instructions, décisions, plans et handoffs se conservent. Une nouvelle instruction **s'ajoute** à l'historique ; elle ne l'efface pas. En cas de contradiction apparente, la plus récente et la plus explicite du propriétaire prévaut, et l'écart doit être consigné.
