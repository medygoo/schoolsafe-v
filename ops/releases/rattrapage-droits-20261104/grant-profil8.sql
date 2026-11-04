BEGIN;
INSERT INTO profile_exceptions(profile_id,modname,can_use,can_edit)
SELECT v.p,v.m,'Y',v.e FROM (VALUES (8,'SchoolSafe/RemediationRead.php',NULL),(8,'SchoolSafe/RemediationFollowup.php','Y')) v(p,m,e)
WHERE NOT EXISTS (SELECT 1 FROM profile_exceptions x WHERE x.profile_id=v.p AND x.modname=v.m);
COMMIT;
