#!/usr/bin/env python3
"""Two-file Git-only discovery candidate; reuse isolated image QA, never deploy."""
import importlib.util
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('discovery_workspace_qa', ROOT/'scripts/prodeli-workspace-image-qa.py')
qa = importlib.util.module_from_spec(spec)
spec.loader.exec_module(qa)
qa.BASE = 'sha256:1b3843de24caa121243f231b147ddf0a9597b155deca7271ed699994f306fb86'
# BuildKit treats FROM sha256:<id> as a registry repository; use the verified local tag.
qa.BASE_TAG = 'schoolsafe-rosario:recovery-qa-c1ed80141175'
qa.BASE_COMMIT = '752f5910a09de30306a131cab26ed7630d81bd82'
qa.FILES = ['plugins/SchoolSafe_Keycloak/Wizard.php', 'plugins/SchoolSafe_Keycloak/manage.php']
qa.NEW_FILES = set()
qa.CONTRACTS = {'prodeli-access-discovery-contracts.php':16,
 'prodeli-account-presentation-contracts.php':16,
 'prodeli-school-boundary-contracts.php':84,
 'school-scan-native-access-contracts.php':62}
# Retain the proven HTTP runner's explicit nonce qualification boundary.
qa.LABEL = 'school-scan-20261023'
qa.PATCH_SCOPE = 'prodeli-access-discovery'
qa.EXTRA_TEST_FILES = []
qa.qa.REMOTE_COMMON += "\nTABLES += ['schoolsafe_native_onboarding','schoolsafe_school_leadership','school_marking_periods','schoolsafe_opening_requests','schoolsafe_school_state']\n"
original = qa.preparation_code

def preparation_code():
    code = original()
    needle = "result=json.loads(output.decode().splitlines()[-1]);assert result['passed']==expected and result['failed']==0;passed+=expected"
    assert code.count(needle) == 1
    code = code.replace(needle, "result=json.loads(output.decode().splitlines()[-1])\n  if 'passed' not in result:result={'passed':result['pass'],'failed':result['fail']}\n  assert result['passed']==expected and result['failed']==0,(name,result,expected)\n  passed+=expected")
    needle = "run(['docker','exec','-i',DB,'pg_restore','-U','schoolcef_fixture','-d','schoolcef_fixture','--no-owner','--no-acl','--exit-on-error'],(ROOT/'fixture.private.dump').read_bytes())"
    assert code.count(needle) == 1
    # Apply already-delivered additive Scan schema ONLY in the nonce-owned clone.
    names = ['020-school-cards.sql','021-entry-records.sql','022-pickup-guardians.sql','023-fee-consults.sql',
             '024-notify-events.sql','025-notify-distributions.sql','026-notify-push.sql','031-delivery-status.sql',
             '037-whatsapp.sql','039-scan-passage-scope.sql','040-fee-control-context.sql',
             '041-entry-request-receipts.sql','042-scan-financial-notification.sql','043-notify-dispatch-claims.sql']
    migration = "\n for name in " + repr(names) + ":\n  data=cli(image,['-r','echo file_get_contents(\"/var/www/html/schoolsafe-remediation/\".$argv[1]);',name])\n  assert data.strip(),name\n  sql(data.decode())\n"
    code = code.replace(needle, needle + migration)
    compile(code, 'discovery-contained-prepare', 'exec')
    return code

qa.preparation_code = preparation_code
if __name__ == '__main__':
    qa.main()
