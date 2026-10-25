-- SchoolSafe L3.1 — périmètre d'établissement des profils nommés.
--
-- Additif et idempotent : ajoute seulement une colonne nullable. Les profils
-- existants gardent school_id NULL, c'est-à-dire « global » (droits et
-- comportement natif inchangés). Aucune réécriture, aucune suppression,
-- aucune valeur existante écrasée lors d'une réexécution : IF NOT EXISTS et
-- aucun UPDATE de rattrapage.
--
-- Sémantique (appliquée côté serveur, jamais dans ce fichier seul) :
--   school_id NULL  -> profil global ( natif + écrans Profiles/Exceptions
--                      réservés au GlobalAdmin, gardes inchangées ) ;
--   school_id = N    -> profil propre à l'école N : création / modification /
--                      suppression via le hub /ecoles/{id}/profils uniquement,
--                      attribution refusée hors de l'école N et aux comptes
--                      multi-écoles (fuite de permissions impossible).
--
-- profile_exceptions est volontairement inchangé : le périmètre est porté par
-- le profil, chaque ligne de droits reste liée à son PROFILE_ID.

BEGIN;

-- Pas de FOREIGN KEY : la clé de schools est (id, syear), jamais id seul
-- (même convention que staff.SCHOOLS, simple liste sans contrainte).
-- L'appartenance est vérifiée côté serveur à chaque écriture.
ALTER TABLE user_profiles
  ADD COLUMN IF NOT EXISTS school_id INTEGER NULL;

CREATE INDEX IF NOT EXISTS user_profiles_school_id_idx
  ON user_profiles(school_id);

COMMIT;
