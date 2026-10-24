#!/usr/bin/env python3
"""Qualify the user's mobile search-above-logo correction on the exact base image."""
import importlib.util
from pathlib import Path
root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('image_qa',root/'scripts/prodeli-access-discovery-image-qa.py')
module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
qa=module.qa
qa.BASE='sha256:01802082ffba8cc9600862accfe39b68fd8df8795b2705fd2ddd6b2bee527305'
qa.BASE_TAG='schoolsafe-rosario:recovery-qa-fb974b25b166'
qa.BASE_COMMIT='2894d6407ad83001c05bd5d48b4f0e60c860fecb'
qa.FILES=['ProgramFunctions/SchoolSafePhotoDashboard.fnc.php','assets/css/schoolsafe-photo-dashboard.css']
qa.NEW_FILES=set()
qa.PATCH_SCOPE='school-logo-search-below'
if __name__=='__main__':qa.main()
