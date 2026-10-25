-- SchoolSafe — profils métier nommés.
--
-- Additif et idempotent : ne modifie aucun profil existant (1 admin, 2 teacher,
-- 3 parent), ne réattribue aucun compte. Crée seulement des profils nommés pour
-- que l'écran Utilisateurs et la console parlent le langage de l'école :
--
--   Directeur : responsable d'une école cliente (base admin, mêmes droits).
--   Comptable : base admin SANS gestion des utilisateurs ni des écoles.
--   Gardien   : base parent (personne de récupération autorisée à se connecter).
--
-- Les matrices sont copiées des profils natifs : rien n'est inventé, rien n'est
-- retiré aux profils existants. Ajustez ensuite ligne par ligne dans
-- Users > Profiles si une école veut un périmètre différent.

BEGIN;

-- Directeur : copie exacte des droits de l'administrateur natif.
INSERT INTO user_profiles (profile, title)
SELECT 'admin', 'Directeur'
WHERE NOT EXISTS (SELECT 1 FROM user_profiles WHERE title = 'Directeur');

INSERT INTO profile_exceptions (profile_id, modname, can_use, can_edit)
SELECT (SELECT id FROM user_profiles WHERE title = 'Directeur'),
       pe.modname, pe.can_use, pe.can_edit
FROM profile_exceptions pe
WHERE pe.profile_id = 1
  AND NOT EXISTS (
      SELECT 1 FROM profile_exceptions x
      WHERE x.profile_id = (SELECT id FROM user_profiles WHERE title = 'Directeur')
        AND x.modname = pe.modname
  );

-- Comptable : droits de l'administrateur, moins toute gestion des comptes,
-- des profils et des établissements (aucune auto-attribution de droits).
INSERT INTO user_profiles (profile, title)
SELECT 'admin', 'Comptable'
WHERE NOT EXISTS (SELECT 1 FROM user_profiles WHERE title = 'Comptable');

INSERT INTO profile_exceptions (profile_id, modname, can_use, can_edit)
SELECT (SELECT id FROM user_profiles WHERE title = 'Comptable'),
       pe.modname, pe.can_use, pe.can_edit
FROM profile_exceptions pe
WHERE pe.profile_id = 1
  AND pe.modname NOT LIKE 'Users/%'
  AND pe.modname NOT LIKE 'School_Setup/%'
  AND NOT EXISTS (
      SELECT 1 FROM profile_exceptions x
      WHERE x.profile_id = (SELECT id FROM user_profiles WHERE title = 'Comptable')
        AND x.modname = pe.modname
  );

-- Gardien : base parent (consultation de l'enfant et des notifications).
INSERT INTO user_profiles (profile, title)
SELECT 'parent', 'Gardien'
WHERE NOT EXISTS (SELECT 1 FROM user_profiles WHERE title = 'Gardien');

INSERT INTO profile_exceptions (profile_id, modname, can_use, can_edit)
SELECT (SELECT id FROM user_profiles WHERE title = 'Gardien'),
       pe.modname, pe.can_use, pe.can_edit
FROM profile_exceptions pe
WHERE pe.profile_id = 3
  AND NOT EXISTS (
      SELECT 1 FROM profile_exceptions x
      WHERE x.profile_id = (SELECT id FROM user_profiles WHERE title = 'Gardien')
        AND x.modname = pe.modname
  );

COMMIT;
