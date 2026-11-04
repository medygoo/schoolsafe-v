BEGIN;
DELETE FROM profile_exceptions WHERE (profile_id,modname) IN ((3,'SchoolSafe/RemediationRead.php'),(3,'SchoolSafe/RemediationParentReply.php'),(6,'SchoolSafe/RemediationRead.php'),(6,'SchoolSafe/RemediationParentReply.php'),(2,'SchoolSafe/RemediationRead.php'),(2,'SchoolSafe/RemediationFollowup.php'));
COMMIT;
