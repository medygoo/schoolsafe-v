BEGIN;
INSERT INTO profile_exceptions(profile_id,modname,can_use,can_edit)
SELECT v.p,v.m,'Y',v.e FROM (VALUES
 (3,'SchoolSafe/RemediationRead.php',NULL),(3,'SchoolSafe/RemediationParentReply.php','Y'),
 (6,'SchoolSafe/RemediationRead.php',NULL),(6,'SchoolSafe/RemediationParentReply.php','Y'),
 (2,'SchoolSafe/RemediationRead.php',NULL),(2,'SchoolSafe/RemediationFollowup.php','Y')) v(p,m,e)
WHERE NOT EXISTS (SELECT 1 FROM profile_exceptions x WHERE x.profile_id=v.p AND x.modname=v.m);
COMMIT;
