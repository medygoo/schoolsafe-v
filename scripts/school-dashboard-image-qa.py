#!/usr/bin/env python3
"""Four-file school navigation candidate on the delivered immutable ProDeli image.
Reuses nonce-owned, isolated synthetic PostgreSQL qualification and cleanup.
This script prepares/verifies QA only; it does not deploy production.
"""
import importlib.util
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('school_navigation_workspace_qa', ROOT/'scripts/prodeli-workspace-image-qa.py')
qa = importlib.util.module_from_spec(spec)
spec.loader.exec_module(qa)
qa.BASE = 'sha256:1e8259e4af681d8d905c7a23d80cca860fe64bc44dd795adfcdab0d14c43981f'
qa.BASE_TAG = 'schoolsafe-rosario:recovery-qa-cd4172e453ea'
qa.BASE_COMMIT = '2f8b9f10ed3026ff6f5a3ced4452058d8222881f'
qa.FILES = ['ProgramFunctions/SchoolSafePhotoDashboard.fnc.php',
 'assets/css/schoolsafe-photo-dashboard.css', 'assets/js/schoolsafe-photo-dashboard.js',
 'assets/themes/SchoolSafeCodex/scripts.js']
qa.NEW_FILES = set()
qa.LABEL = 'school-dashboard-stability-20261022'
qa.PATCH_SCOPE = 'school-dashboard-navigation'
qa.CONTRACTS = {'prodeli-account-presentation-contracts.php':16,
 'prodeli-dashboard-contracts.php':12, 'prodeli-communication-contracts.php':28,
 'prodeli-native-intent-contracts.php':16, 'prodeli-school-boundary-contracts.php':84}
qa.qa.REMOTE_COMMON += "\nTABLES += ['schoolsafe_native_onboarding','schoolsafe_school_leadership','school_marking_periods','schoolsafe_opening_requests']\n"
if __name__ == '__main__':
    qa.main()
