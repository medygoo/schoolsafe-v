#!/usr/bin/env python3
"""One-file exact-image qualification on the currently deployed immutable image. No deploy."""
import importlib.util
from pathlib import Path
root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('discovery_qa',root/'scripts/prodeli-access-discovery-image-qa.py')
module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
qa=module.qa
qa.BASE='sha256:1df5f8578805868544f1a7a73d6a1d437c5be0bf0ecf52cd716b41455e35aea8'
qa.BASE_TAG='schoolsafe-rosario:recovery-qa-e41c8515d0b7'
qa.BASE_COMMIT='3ecdf9e5dc0558d5fb5c374a12cfd8df00309ad6'
qa.FILES=['ProgramFunctions/SchoolSafeSchoolState.fnc.php']
qa.NEW_FILES=set()
qa.PATCH_SCOPE='prodeli-school-state-atomicity'
qa.EXTRA_TEST_FILES=['scripts/prodeli-school-state-probe.php']
if __name__=='__main__':qa.main()
