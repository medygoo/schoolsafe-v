#!/usr/bin/env python3
"""Git-only Scan candidate on the protected dashboard image; isolated synthetic QA.
Reuses existing build/nonce/fixture/production-invariance guards. NOT a deployment.
"""
import importlib.util
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('school_scan_workspace_qa',ROOT/'scripts/prodeli-workspace-image-qa.py')
qa=importlib.util.module_from_spec(spec);spec.loader.exec_module(qa)
qa.BASE='sha256:8770153af9759e423008028268248973a230c47327c76b371f76a1f12028cac6'
qa.BASE_TAG='schoolsafe-rosario:recovery-qa-9a84afb7f386'
qa.BASE_COMMIT='9d5cfad163c1427d367e80a0a5cab25f896e5d2d'
qa.FILES=('''ProgramFunctions/SchoolSafeBrevo.fnc.php
ProgramFunctions/SchoolSafeCanteenScan.fnc.php
ProgramFunctions/SchoolSafeCardVerify.fnc.php
ProgramFunctions/SchoolSafeEntryRequest.fnc.php
ProgramFunctions/SchoolSafeEntryScan.fnc.php
ProgramFunctions/SchoolSafeFeePolicy.fnc.php
ProgramFunctions/SchoolSafeFeeScan.fnc.php
ProgramFunctions/SchoolSafeFeeSnapshot.fnc.php
ProgramFunctions/SchoolSafeMailTemplate.fnc.php
ProgramFunctions/SchoolSafeNotificationFeed.fnc.php
ProgramFunctions/SchoolSafeNotify.fnc.php
ProgramFunctions/SchoolSafeNotifyDispatch.fnc.php
ProgramFunctions/SchoolSafeNotifyRecipient.fnc.php
ProgramFunctions/SchoolSafePickupManage.fnc.php
ProgramFunctions/SchoolSafePickupScan.fnc.php
ProgramFunctions/SchoolSafeScanBackend.fnc.php
ProgramFunctions/SchoolSafeScanLocationManage.fnc.php
ProgramFunctions/SchoolSafeScanNotice.fnc.php
ProgramFunctions/SchoolSafeScanNotify.fnc.php
ProgramFunctions/SchoolSafeScanPassage.fnc.php
ProgramFunctions/SchoolSafeSms.fnc.php
ProgramFunctions/SchoolSafeWaSession.fnc.php
ProgramFunctions/SchoolSafeWhatsapp.fnc.php
assets/themes/SchoolSafeCodex/scripts.js
central-navigation.php
family-management.php
parent-notifications-view.php
parent-notifications.php
plateforme-prodeli-whatsapp.php
scan-locations.php
scanner-native.php
scanner-relay.php
schoolsafe-attendance/ArrivalRead.php
schoolsafe-attendance/arrival-dashboard-20261012-unit.php
schoolsafe-card-bridge/family-management.js
schoolsafe-card-bridge/parent-notification-data.js
schoolsafe-card-bridge/parent-notifications-view.js
schoolsafe-card-bridge/scan-locations.js
schoolsafe-card-bridge/scanner-native.js
schoolsafe-notify-cli.php
schoolsafe-notify-dispatch.php
schoolsafe-notify-recover.php
schoolsafe-remediation/020-school-cards.sql
schoolsafe-remediation/021-entry-records.sql
schoolsafe-remediation/022-pickup-guardians.sql
schoolsafe-remediation/023-fee-consults.sql
schoolsafe-remediation/039-scan-passage-scope.sql
schoolsafe-remediation/040-fee-control-context.sql
schoolsafe-remediation/041-entry-request-receipts.sql
schoolsafe-remediation/042-scan-financial-notification.sql
schoolsafe-remediation/043-notify-dispatch-claims.sql
schoolsafe-remediation/tests/card-verify-a1-db.php
schoolsafe-remediation/tests/card-verify-a1.php
schoolsafe-remediation/tests/fee-scan.php
schoolsafe-remediation/tests/scan-entry-db.php
schoolsafe-remediation/tests/scan-entry.php
schoolsafe-remediation/tests/scan-pickup-db.php
schoolsafe-remediation/tests/scan-pickup.php
ProgramFunctions/SchoolSafeCardIssue.fnc.php
schoolsafe-card-bridge/NativeCardRegistryRead.fnc.php
card-bridge-issue.php
schoolsafe-card-bridge/NativeCardRelay.fnc.php
schoolsafe-card-bridge/restored-studio-native.js
schoolsafe-card-bridge/studio-native-client.js
studio-native.php''').splitlines()
# Baseline existence only; the payload allowlist above is never derived from HEAD/worktree.
qa.NEW_FILES={f for f in qa.FILES if qa.qa.local(['git','-C',str(ROOT),'ls-tree','--name-only',qa.BASE_COMMIT,'--',qa.PREFIX+f]).decode().strip()==''}
qa.LABEL='school-scan-20261023';qa.PATCH_SCOPE='school-presence-scan-qr'
qa.EXTRA_TEST_FILES=[qa.PREFIX+'schoolsafe-remediation/'+name for name in ['024-notify-events.sql','025-notify-distributions.sql','026-notify-push.sql','031-delivery-status.sql','037-whatsapp.sql']]
qa.CONTRACTS={'prodeli-account-presentation-contracts.php':16,'prodeli-dashboard-contracts.php':12,
 'prodeli-communication-contracts.php':28,'prodeli-native-intent-contracts.php':16,
 'prodeli-school-boundary-contracts.php':84,'school-arrival-availability-contracts.php':14,
 'school-dashboard-composition-contracts.php':20,'school-scan-native-access-contracts.php':62,
 'school-canteen-scan-contracts.php':12,'school-scan-notify-contracts.php':23,
 'school-wa-acceptance-contracts.php':21,'school-wa-session-contracts.php':20,
 'school-card-native-issue-contracts.php':21,'school-pickup-native-db-contracts.php':4}
qa.qa.REMOTE_COMMON+="\nTABLES += ['schoolsafe_native_onboarding','schoolsafe_school_leadership','school_marking_periods','schoolsafe_opening_requests']\n"
original=qa.preparation_code

def preparation_code():
 code=original()
 # Build context timestamps must advance the native filemtime asset cache key.
 needle="for f in MANIFEST['payload_hashes']:(ROOT/'payload'/f).chmod(0o644)"
 assert code.count(needle)==1
 code=code.replace(needle,"import os\n for f in MANIFEST['payload_hashes']:\n  p=ROOT/'payload'/f;p.chmod(0o644);os.utime(p,(time.time(),time.time()))")
 needle="result=json.loads(output.decode().splitlines()[-1]);assert result['passed']==expected and result['failed']==0;passed+=expected"
 assert code.count(needle)==1
 code=code.replace(needle,"try: result=json.loads(output.decode().splitlines()[-1])\n  except json.JSONDecodeError:\n   assert not any(line.startswith('FAIL ') for line in output.decode().splitlines())\n   result={'passed':sum(line.startswith('PASS ') for line in output.decode().splitlines()),'failed':0}\n  if 'passed' not in result: result={'passed':result['pass'],'failed':result['fail']}\n  assert result['passed']==expected and result['failed']==0,(name,result,expected);passed+=expected")
 # All migrations target the nonce-owned disposable database, NEVER production.
 needle="run(['docker','exec','-i',DB,'pg_restore','-U','schoolcef_fixture','-d','schoolcef_fixture','--no-owner','--no-acl','--exit-on-error'],(ROOT/'fixture.private.dump').read_bytes())"
 assert code.count(needle)==1
 migration="\n for name in ['020-school-cards.sql','021-entry-records.sql','022-pickup-guardians.sql','023-fee-consults.sql','024-notify-events.sql','025-notify-distributions.sql','026-notify-push.sql','031-delivery-status.sql','037-whatsapp.sql','039-scan-passage-scope.sql','040-fee-control-context.sql','041-entry-request-receipts.sql','042-scan-financial-notification.sql','043-notify-dispatch-claims.sql']:\n  path=ROOT/'payload'/'schoolsafe-remediation'/name\n  if not path.exists():path=ROOT/'extra-tests'/name\n  sql(path.read_text())\n"
 code=code.replace(needle,needle+migration)
 # Synthetic HMAC only. No real card/provider credentials or outbound network.
 needle="'-e','SCHOOLSAFE_DB_HOST=schoolcef-harmonization-pg','-e','SCHOOLSAFE_DEFAULT_SYEAR=2026',image]"
 assert code.count(needle)==1
 code=code.replace(needle,"'-e','SCHOOLSAFE_DB_HOST=schoolcef-harmonization-pg','-e','SCHOOLSAFE_DEFAULT_SYEAR=2026','-e','SCHOOLSAFE_CARD_HMAC_SECRET="+'0'*64+"',image]")
 compile(code,'contained-scan-prepare','exec')
 return code
qa.preparation_code=preparation_code
if __name__=='__main__':qa.main()
