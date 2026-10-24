#!/usr/bin/env python3
"""Qualify exact full-frame school logo and per-admin native photo fallback."""
import importlib.util
from pathlib import Path
root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('image_qa',root/'scripts/prodeli-access-discovery-image-qa.py')
module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
qa=module.qa
qa.BASE='sha256:2ad1a6fcac1675be4c7fef301831892c464a55f493f98988ad316aabd1c758d1'
qa.BASE_TAG='schoolsafe-rosario:recovery-qa-cd0a5fc1e90c'
qa.BASE_COMMIT='243fe43576a3aaa70c2972a67699405df1935dd6'
qa.FILES=['ProgramFunctions/SchoolSafePhotoDashboard.fnc.php','ProgramFunctions/SchoolSafeStaffPhoto.fnc.php','assets/css/schoolsafe-photo-dashboard.css','central-navigation.php']
qa.NEW_FILES={'ProgramFunctions/SchoolSafeStaffPhoto.fnc.php'};qa.PATCH_SCOPE='school-logo-admin-photo'
if __name__=='__main__':qa.main()
