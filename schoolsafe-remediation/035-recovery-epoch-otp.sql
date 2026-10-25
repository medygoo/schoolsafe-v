-- SchoolSafe — recuperation des comptes (LOT L4) : epoque de mot de passe et
-- defis OTP canal simule.
--
-- Additif et idempotent : aucune table native n'est modifiee en dehors de
-- deux colonnes NULLABLES/DEFAULT (aucune donnee existante reecrite, aucun
-- comportement change sans le code L4). Utilise par
-- ProgramFunctions/SchoolSafeRecovery.fnc.php (epoque de session, OTP),
-- index.php (tampon d'epoque a la connexion), Warehouse.php (eviction des
-- anciennes sessions), PasswordReset.php (increment a chaque changement) et
-- compte-recuperation.php (parcours /compte/recuperation).
--
-- Regles de securite portees par le schema :
-- - invalidation des sessions : AUCUNE colonne ici — on reutilise l'epoque
--   existante (schoolsafe_account_state.SESSION_EPOCH, lot L1) via
--   SchoolSafeAccountRevoke() appelee a chaque changement de mot de passe ;
--   la garde SchoolSafeAccountSessionGuard() (Warehouse) evince deja ;
-- - OTP : seul le hash SHA-256 du code est conserve ; code_plain n'est
--   renseigne QUE si config RECOVERY_SMS_SIMULATE='Y' (piles isolees de
--   recette, JAMAIS en production : sans SMSGate aucun SMS ne part) ;
-- - expiration obligatoire, usage unique (consumed_at), tentatives bornees.

BEGIN;

CREATE TABLE IF NOT EXISTS schoolsafe_recovery_otp (
    id serial PRIMARY KEY,
    user_type varchar(16) NOT NULL DEFAULT 'staff',
    user_id integer NOT NULL,
    channel varchar(16) NOT NULL DEFAULT 'sms',
    code_hash varchar(64) NOT NULL,
    code_plain varchar(12) NULL,
    expires_at timestamp NOT NULL,
    max_attempts integer NOT NULL DEFAULT 5,
    attempts integer NOT NULL DEFAULT 0,
    consumed_at timestamp NULL,
    created_ip varchar(64) NOT NULL DEFAULT '',
    created_at timestamp NOT NULL DEFAULT current_timestamp,
    CONSTRAINT schoolsafe_recovery_otp_user_allowed
        CHECK (user_type IN ('staff', 'student')),
    CONSTRAINT schoolsafe_recovery_otp_channel_allowed
        CHECK (channel IN ('sms')),
    CONSTRAINT schoolsafe_recovery_otp_hash_format
        CHECK (code_hash ~ '^[0-9a-f]{64}$'),
    CONSTRAINT schoolsafe_recovery_otp_attempts_bounds
        CHECK (attempts >= 0 AND max_attempts BETWEEN 1 AND 10
            AND attempts <= max_attempts)
);

CREATE INDEX IF NOT EXISTS schoolsafe_recovery_otp_user_idx
    ON schoolsafe_recovery_otp (user_type, user_id);

CREATE INDEX IF NOT EXISTS schoolsafe_recovery_otp_active_idx
    ON schoolsafe_recovery_otp (user_type, user_id, channel)
    WHERE consumed_at IS NULL;

COMMIT;
