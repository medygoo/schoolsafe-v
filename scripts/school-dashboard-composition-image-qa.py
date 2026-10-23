#!/usr/bin/env python3
"""Three-file composed dashboard on the delivered stability image; QA only, no deployment."""
import importlib.util
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('school_composition_workspace_qa', ROOT/'scripts/prodeli-workspace-image-qa.py')
qa = importlib.util.module_from_spec(spec)
spec.loader.exec_module(qa)
qa.BASE = 'sha256:b271dcc5c4e069623b278d327af6f71013b5fcc88c3bc90cee53c7f3bffe12e7'
qa.BASE_TAG = 'schoolsafe-rosario:recovery-qa-257f7b791358'
qa.BASE_COMMIT = '716545c327800b4aadec23160a750ca6ff0a5267'
qa.FILES = ['ProgramFunctions/SchoolSafePhotoDashboard.fnc.php',
 'assets/css/schoolsafe-photo-dashboard.css', 'assets/js/schoolsafe-photo-dashboard.js']
qa.NEW_FILES = set()
qa.LABEL = 'school-dashboard-composition-20261023'
qa.PATCH_SCOPE = 'school-dashboard-composition'
qa.CONTRACTS = {'prodeli-account-presentation-contracts.php':16,
 'prodeli-dashboard-contracts.php':12, 'prodeli-communication-contracts.php':28,
 'prodeli-native-intent-contracts.php':16, 'prodeli-school-boundary-contracts.php':84,
 'school-arrival-availability-contracts.php':10, 'school-dashboard-composition-contracts.php':20}
qa.qa.REMOTE_COMMON += "\nTABLES += ['schoolsafe_native_onboarding','schoolsafe_school_leadership','school_marking_periods','schoolsafe_opening_requests']\n"
if __name__ == '__main__':
    qa.main()
