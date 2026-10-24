#!/usr/bin/env python3
"""Exact-image isolated qualification of native school ranking matrix."""
import importlib.util
from pathlib import Path
root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('image_qa',root/'scripts/prodeli-access-discovery-image-qa.py')
module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
qa=module.qa
qa.BASE='sha256:f2d23fdc61df4ed28516ec00e4fc70bdbd1efdbd9e2cd16379c497cce6d3488e'
qa.BASE_TAG='schoolsafe-rosario:recovery-qa-6c930402f9b4'
qa.BASE_COMMIT='1deda155d7877435fe8bc383a05e49f1ccf476db'
qa.FILES=['ProgramFunctions/SchoolSafePhotoDashboard.fnc.php','ProgramFunctions/SchoolSafeSchoolLife.fnc.php','assets/css/schoolsafe-photo-dashboard.css','assets/js/schoolsafe-photo-dashboard.js']
qa.NEW_FILES=set();qa.PATCH_SCOPE='school-palmares-matrix'
if __name__=='__main__':qa.main()
