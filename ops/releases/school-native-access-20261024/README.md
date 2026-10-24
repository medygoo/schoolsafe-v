# Native school access — qualified five-file release

Owner GO for step 3 authorizes this targeted software deployment, not real beneficiary selection, provider/worker activation, physical camera qualification or official printing.

- Previous checkout `e17357bbcf59068d8276c260314d3ffa8a1733a2`, branch `deployed/school-scan-profile-owner-20261024`, image `sha256:7bedfc1e37289136d9a54ac87f82603784053a47bd45c90c1b1d3c159dc7c628`.
- Qualified runtime `5cf4c9cbfaf379a252afc27f76fac17bc6c45d04`.
- Candidate image `sha256:1b3843de24caa121243f231b147ddf0a9597b155deca7271ed699994f306fb86`, source digest `c1ed80141175ab4ecc2c5110839a6b48a75a66e13eea79d27dc61936386cdded`.
- Five paths: `admin-console.php`, `ecole-profils.php`, `ProgramFunctions/SchoolSafeSchoolScope.fnc.php`, new `SchoolSafeSchoolAccess.fnc.php` and `SchoolSafeSchoolAccessView.fnc.php`. Other66 cumulative Scan sources and protected dashboard remain byte-identical.
- No migrations, grant SQL, account selection, credential change, module activation or message dispatch. Native metadata/bootstrap and school permission assignment remain explicit human POST actions, not deployment actions.

## Gates and execution

Run `python scripts/school-native-access-release-contracts.py` offline. Publish recipe and guard from explicit paths only; do not stage dirty dashboard/palmarès drafts. Stage private0700 `/opt/schoolsafe-releases/school-native-access-20261024` from committed Git and the qualified evidence, never from dirty runtime files. Bundle existing recovery and preservation engines unchanged and compare their bytes to the release commit.

Qualification requires387contracts+37recovery, three exact suites,58ScanHTTP,49access/shared-writerHTTP,55PG,21browser, final Git/source/cleanup provenance, restored fixture hashes and isolated fixture cleanup. Image labels/digest and all71cumulative sources are verified; there must be exactly five runtime differences from previous production.

1. `python3 /opt/schoolsafe-releases/school-native-access-20261024/deploy.py prepare`: clean/pinned checkout and healthy expected production; read-only schema and service table/sequence privilege checks; no implicit grant or migration. Read current environment internally only, confirm durable nonzero HMAC without reading escrow or emitting values. Record neighbors/configuration/container privately, validate a fresh0600 custom PG archive via `pg_restore --list` only.
2. `... deploy`: requires backup younger than900seconds; existing preservation engine stages exact image with unchanged environment/config/runtime. Stop old, transfer native sessions/Chromium cache, rename and start candidate, validate health/routes/Git/source hashes/neighbors; retain old container stopped. Source configuration is the sole read-only bind; no dirty tree overlay.
3. Independent verification:71source hashes plus3dashboard, complete environment including signing key and HostConfig, config hash, clean exact Git branch/commit, stopped predecessor, no lost unexpired session, unchanged Chromium cache, HTTPS/public and protected routes, no dispatch/recover workers. Never use a real school account to test attribution.

The inherited recovery engine labels its container patch scope `prodeli-recovery-sms`; authoritative identity for this release is exact image ID, image source labels, patch commit, Git tree and source hashes. Reusing the preservation engine does not restart or activate the recovery/provider workers.

## Failure / recovery

No automatic rollback and never restore a dump over current business writes. Before cutover, any precondition failure aborts with current production untouched. After cutover failure retain old/new containers, proofs and session archives, report actual service state and diagnose. A deliberate app-image rollback must preserve current database writes/audit, signing environment, current sessions/cache and neighbors; do not rerun a historical backup restore. Do not choose people or change live permissions as a deployment workaround.

## Executed closure

Recipe/production checkout `44e246fdd037318c647ee25e8447bb83e3b88337`, branch `deployed/school-native-access-20261024`; qualified image/runtime above deployed.22offline guards and read-only native schema/service privilege preflight PASS. Fresh0600 PG backup685809bytes, archive valid, SHA256 `d1a6bf4e8de2c52b0147e14532a468460072e3b16fe87aa97819e1dd3fd71532`, never restored. Cutover8.68seconds,83session files transferred, configuration/environment/signing key/cache/five neighbors unchanged; stopped old container retained.

Independent PASS:71sources+3protected dashboard hashes, healthy exact image and clean Git, complete HostConfig/environment/config unchanged, no missing unexpired sessions,9cache entries preserved, HTTPS login/recovery200 and anonymous scanner/family401, dispatch/recover workers absent. No production account mutations or external message tests. One final SSH schema-proof download timed out after the independent PASS; status was rechecked, only that proof was fetched, no redeployment.

Private evidence `school-scan-20261023/access-deployment-r1/production-{deployment,backup,schema}-proof.json` and `production-independent-verification-proof.json`. [Full closure](../../../docs/qualification/SCHOOL_NATIVE_ACCESS_20261024.md). Software is deployed; actual school/operator/task choices and physical/provider/printing commissioning remain separate human gates.
