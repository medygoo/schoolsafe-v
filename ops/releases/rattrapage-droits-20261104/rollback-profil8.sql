BEGIN;
DELETE FROM profile_exceptions WHERE (profile_id,modname) IN ((8,'SchoolSafe/RemediationRead.php'),(8,'SchoolSafe/RemediationFollowup.php'));
COMMIT;
