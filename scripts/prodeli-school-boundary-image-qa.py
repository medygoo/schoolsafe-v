#!/usr/bin/env python3
"""Canonical school-boundary image: scoped central routes, synthetic isolated QA.

Replaces historical global-school-entry navigation criteria with deny/activation
contracts. No real account, production database, provider or deployment mutation.
"""
import importlib.util
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('boundary_workspace_qa', ROOT/'scripts/prodeli-workspace-image-qa.py')
qa = importlib.util.module_from_spec(spec)
spec.loader.exec_module(qa)
qa.BASE = 'sha256:a4a3cbf821083f60629031075c46dafdec8615fb91d591835408fa4afb260bf3'
qa.BASE_TAG = 'schoolsafe-rosario:recovery-qa-11d25627ea51'
qa.BASE_COMMIT = '8a969d5bcb4f9a97eda4181165d7db5dd2af2f38'
qa.FILES = ['Bottom.php', 'Warehouse.php', 'Side.php', 'admin-console.php',
 'ProgramFunctions/SchoolSafeNativeOnboarding.fnc.php', 'ProgramFunctions/SchoolSafeSchoolScope.fnc.php',
 'ProgramFunctions/SchoolSafeWorkspace.fnc.php', 'ProgramFunctions/SchoolSafePhotoDashboard.fnc.php',
 'modules/School_Setup/Configuration.php', 'plugins/SchoolSafe_Keycloak/Management.php',
 'plugins/SchoolSafe_Keycloak/Wizard.php', 'plugins/SchoolSafe_Keycloak/manage.php']
qa.NEW_FILES = set()
qa.LABEL = 'prodeli-school-boundary-20261022'
qa.PATCH_SCOPE = 'prodeli-school-boundary'
qa.CONTRACTS = {'prodeli-account-presentation-contracts.php':16, 'prodeli-dashboard-contracts.php':12,
 'prodeli-communication-contracts.php':28, 'prodeli-native-intent-contracts.php':16,
 'prodeli-school-boundary-contracts.php':84}
qa.EXTRA_TEST_FILES = ['scripts/prodeli-director-transaction.php',
 qa.PREFIX+'schoolsafe-remediation/018-school-leadership.sql',
 qa.PREFIX+'schoolsafe-remediation/038-native-onboarding.sql']
qa.qa.REMOTE_COMMON += "\nTABLES += ['schoolsafe_native_onboarding','schoolsafe_school_leadership','school_marking_periods','schoolsafe_opening_requests']\n"
_original_preparation_code = qa.preparation_code

def preparation_code():
    code = _original_preparation_code()
    marker = " STATE.update({'image':image"
    assert code.count(marker) == 1
    extra = ''' run(['docker','exec',WEB,'mkdir','-p','/tmp/boundary-tests'])
 for name in ['prodeli-director-transaction.php','018-school-leadership.sql','038-native-onboarding.sql']:
  run(['docker','exec','-i',WEB,'php','-r','file_put_contents($argv[1],stream_get_contents(STDIN));','/tmp/boundary-tests/'+name],(ROOT/'extra-tests'/name).read_bytes())
 output=run(['docker','exec','-e','SCHOOLSAFE_CONTRACT_PHP_ROOT=/var/www/html','-e','PRODELI_NATIVE_MIGRATION_ROOT=/tmp/boundary-tests',WEB,'php','/tmp/boundary-tests/prodeli-director-transaction.php'])
 (ROOT/'native-transaction-contracts.log').write_bytes(output)
 assert b'57 checks, 0 failures; isolated schema removed' in output
'''
    code = code.replace(marker, extra + marker)
    code = code.replace("'recovery_contracts':37,", "'recovery_contracts':37,'native_transaction_contracts':57,")
    return code

qa.preparation_code = preparation_code
if __name__ == '__main__':
    qa.main()
