#!/usr/bin/env python3
"""Exact-image qualification for screenshot-matched large right-hand mobile logo."""
import importlib.util
from pathlib import Path
root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('image_qa',root/'scripts/prodeli-access-discovery-image-qa.py')
module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
qa=module.qa
qa.BASE='sha256:c2ca7bd72cf44bdd029d095f3580fa9d48d8111e35a8de550c59dff06f882bf0'
qa.BASE_TAG='schoolsafe-rosario:recovery-qa-38d358552a99'
qa.BASE_COMMIT='51f46342c2a41149cb63a10cbef026a93fda8898'
qa.FILES=['ProgramFunctions/SchoolSafePhotoDashboard.fnc.php','assets/css/schoolsafe-photo-dashboard.css']
qa.NEW_FILES=set();qa.PATCH_SCOPE='school-logo-red-area'
if __name__=='__main__':qa.main()
