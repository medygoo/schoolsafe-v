#!/usr/bin/env python3
"""Canonical presentation-only image on active workspace; never includes grading relocation."""
import importlib.util
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('workspace_qa',ROOT/'scripts/prodeli-workspace-image-qa.py')
qa=importlib.util.module_from_spec(spec);spec.loader.exec_module(qa)
qa.BASE='sha256:59fb6b25e57ea4f53237ff36dbdc0ae1122fdf57be34a6ab4fe5b3bf6fc5bb63'
qa.BASE_TAG='schoolsafe-rosario:recovery-qa-682a8e878979'
qa.BASE_COMMIT='8fc3546bc47d23395d5802a9f612185e9193acaa'
qa.FILES=['ProgramFunctions/FirstLogin.fnc.php','ProgramFunctions/SchoolSafePhotoDashboard.fnc.php',
 'ProgramFunctions/SchoolSafeWorkspace.fnc.php','ProgramFunctions/Theme.fnc.php',
 'assets/css/schoolsafe-access.css','assets/css/schoolsafe-native-workspace.css',
 'assets/css/schoolsafe-photo-dashboard.css','assets/css/schoolsafe-recovery.css',
 'compte-recuperation.php','functions/Config.fnc.php','functions/User.fnc.php',
 'modules/SchoolSafe/login-blue.inc.php','modules/School_Setup/Configuration.php','modules/Users/Preferences.php',
 'plugins/SchoolSafe_Keycloak/Wizard.php','plugins/SchoolSafe_Keycloak/central-ui.css','plugins/SchoolSafe_Keycloak/manage.php']
qa.NEW_FILES={'assets/css/schoolsafe-access.css','assets/css/schoolsafe-recovery.css'}
qa.CONTRACTS={**qa.qa.CONTRACTS,'prodeli-workspace-contracts.php':33,'prodeli-access-theme-contracts.php':25}
if __name__=='__main__':qa.main()
