-- SchoolSafe L3 — catégories du formulaire adaptatif (profils nommés manquants).
--
-- Additif et idempotent : ne modifie aucun profil existant (1/2/3 natifs,
-- Directeur/Comptable/Gardien de 015), ne réattribue aucun compte. Crée
-- seulement les profils nommés exigés par le formulaire « Nouvel utilisateur » :
--
--   Administrateur scolaire : base Directeur (admin) SANS les 4 écrans réservés
--     aux globaux (Profiles, Exceptions, schools, user_profile). Il gère les
--     comptes de son école via Users/User.php mais ne peut ni s'accorder des
--     droits globaux ni nommer un pair (droits STRICTEMENT inclus dans Directeur).
--   Personnel administratif : base Teacher (enseignant), multi-écoles autorisé,
--     aucune gestion d'utilisateurs ni d'écoles.
--   Cantine : base Teacher (enseignant), fonctions Food_Service existantes,
--     aucune gestion d'utilisateurs ni d'écoles.
--
-- Familles natives couvertes par ailleurs : Direction -> Directeur (015),
-- Enseignant -> Teacher natif (2), Parent/tuteur -> Parent natif (3),
-- Gardien -> Gardien (015). Les dossiers élèves restent gérés séparément
-- (modules/Students) : une inscription d'élève ne crée aucun compte.

BEGIN;

-- Administrateur scolaire : copie Directeur moins les écrans réservés globaux.
INSERT INTO user_profiles (profile, title)
SELECT 'admin', 'Administrateur scolaire'
WHERE NOT EXISTS (SELECT 1 FROM user_profiles WHERE title = 'Administrateur scolaire');

INSERT INTO profile_exceptions (profile_id, modname, can_use, can_edit)
SELECT (SELECT id FROM user_profiles WHERE title = 'Administrateur scolaire'),
       pe.modname, pe.can_use, pe.can_edit
FROM profile_exceptions pe
WHERE pe.profile_id = (SELECT id FROM user_profiles WHERE title = 'Directeur')
  AND pe.modname NOT IN ('Users/Profiles.php', 'Users/Exceptions.php',
    'Users/User.php&category_id=1&schools', 'Users/User.php&category_id=1&user_profile')
  AND NOT EXISTS (
      SELECT 1 FROM profile_exceptions x
      WHERE x.profile_id = (SELECT id FROM user_profiles WHERE title = 'Administrateur scolaire')
        AND x.modname = pe.modname
  );

-- Personnel administratif : copie exacte des droits Teacher natif.
INSERT INTO user_profiles (profile, title)
SELECT 'teacher', 'Personnel administratif'
WHERE NOT EXISTS (SELECT 1 FROM user_profiles WHERE title = 'Personnel administratif');

INSERT INTO profile_exceptions (profile_id, modname, can_use, can_edit)
SELECT (SELECT id FROM user_profiles WHERE title = 'Personnel administratif'),
       pe.modname, pe.can_use, pe.can_edit
FROM profile_exceptions pe
WHERE pe.profile_id = 2
  AND NOT EXISTS (
      SELECT 1 FROM profile_exceptions x
      WHERE x.profile_id = (SELECT id FROM user_profiles WHERE title = 'Personnel administratif')
        AND x.modname = pe.modname
  );

-- Cantine : copie exacte des droits Teacher natif (fonctions Food_Service existantes).
INSERT INTO user_profiles (profile, title)
SELECT 'teacher', 'Cantine'
WHERE NOT EXISTS (SELECT 1 FROM user_profiles WHERE title = 'Cantine');

INSERT INTO profile_exceptions (profile_id, modname, can_use, can_edit)
SELECT (SELECT id FROM user_profiles WHERE title = 'Cantine'),
       pe.modname, pe.can_use, pe.can_edit
FROM profile_exceptions pe
WHERE pe.profile_id = 2
  AND NOT EXISTS (
      SELECT 1 FROM profile_exceptions x
      WHERE x.profile_id = (SELECT id FROM user_profiles WHERE title = 'Cantine')
        AND x.modname = pe.modname
  );

COMMIT;
