#!/usr/bin/env python3
"""Build per-school logo reader/legacy consumers from immutable Git over deployed Scan image."""
import importlib.util
from pathlib import Path
root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('discovery_qa',root/'scripts/prodeli-access-discovery-image-qa.py')
module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
qa=module.qa
qa.BASE='sha256:14209bb46a9ca9d31f00c9f64ad1dab4301ef8894249f9c39e6373c9373d0183'
qa.BASE_TAG='schoolsafe-rosario:recovery-qa-851743855287'
qa.BASE_COMMIT='68e422d47b2c0179b9b6318eb71afa70ce69b5dc'
qa.FILES=['ProgramFunctions/SchoolSafeSchoolLogo.fnc.php','ProgramFunctions/SchoolSafePhotoDashboard.fnc.php',
'assets/css/schoolsafe-photo-dashboard.css','assets/js/schoolsafe-school-logo.js',
'modules/Grades/includes/ReportCards.fnc.php','modules/Grades/includes/Transcripts.fnc.php',
'modules/School_Setup/Configuration.php','modules/School_Setup/Schools.php',
'modules/School_Setup/includes/SchoolReceiptIdentity.fnc.php','modules/Students/PrintStudentInfo.php',
'plugins/SchoolSafe_Keycloak/Wizard.php','schoolsafe-card-bridge/RosarioCardRead.fnc.php']
qa.NEW_FILES={'ProgramFunctions/SchoolSafeSchoolLogo.fnc.php','assets/js/schoolsafe-school-logo.js'}
qa.PATCH_SCOPE='school-logo-persistent-owner'
if __name__=='__main__':qa.main()
