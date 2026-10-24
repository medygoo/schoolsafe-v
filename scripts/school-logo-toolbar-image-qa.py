#!/usr/bin/env python3
"""Image-isolated dashboard identity alignment over deployed portrait image."""
import importlib.util
from pathlib import Path
root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('discovery_qa',root/'scripts/prodeli-access-discovery-image-qa.py')
module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
qa=module.qa
qa.BASE='sha256:ed97d7d18745e715bf8075ac066967e11444d79d657b9d08e3bbb6f632c549a6'
qa.BASE_TAG='schoolsafe-rosario:recovery-qa-1ee472bfdfcf'
qa.BASE_COMMIT='3a45fa82dc9ddd6a78489828d69e79669fca302f'
qa.FILES=['ProgramFunctions/SchoolSafePhotoDashboard.fnc.php','assets/css/schoolsafe-photo-dashboard.css']
qa.NEW_FILES=set()
qa.PATCH_SCOPE='school-logo-toolbar'
if __name__=='__main__':qa.main()
