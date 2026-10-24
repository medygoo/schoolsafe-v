#!/usr/bin/env python3
"""Exact-image qualification over deployed palmares matrix."""
import importlib.util
from pathlib import Path
root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('image_qa',root/'scripts/prodeli-access-discovery-image-qa.py')
module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
qa=module.qa
qa.BASE='sha256:8f2366bc38e684ce20c07a480e180c68fc7ae0a60fd031c34074b6f9c7b6f918'
qa.BASE_TAG='schoolsafe-rosario:recovery-qa-8a6a27fc446b'
qa.BASE_COMMIT='62411aab6ce98ba9ab13c8184588b800388f5adf'
qa.FILES=['ProgramFunctions/SchoolSafePhotoDashboard.fnc.php','ProgramFunctions/SchoolSafePracticalClasses.fnc.php','assets/css/schoolsafe-photo-dashboard.css']
qa.NEW_FILES={'ProgramFunctions/SchoolSafePracticalClasses.fnc.php'};qa.PATCH_SCOPE='school-practical-classes'
if __name__=='__main__':qa.main()
